<?php

namespace App\Service;

use App\Enum\CategoryType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Identifie un vêtement (ou lit son étiquette) à partir d'une photo, via un modèle de vision OpenRouter.
 * Les modèles sont essayés dans l'ordre : si l'un échoue (quota, erreur, réponse illisible), on passe au suivant.
 */
final class ClothingScanner
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    // Temps total accordé à l'analyse : nginx coupe la requête à 60 s par défaut
    private const TIME_BUDGET = 50.0;
    // Durée maximale d'un appel à un modèle
    private const MODEL_TIMEOUT = 30.0;
    // Pause avant la seconde tentative sur un modèle saturé (429) ou en erreur côté fournisseur (5xx)
    private const RETRY_DELAY_US = 1_500_000;

    // Consignes communes : réponse JSON stricte, en français
    private const PROMPTS = [
        'photo' => <<<'TXT'
            Tu aides à ranger une garde-robe. Regarde la photo et identifie l'objet principal (en général un vêtement).
            Réponds UNIQUEMENT avec un objet JSON, sans texte autour, de la forme :
            {"name": "nom court pour l'objet, ex. Pull bleu marine", "type": "type de vêtement, ex. pull",
             "category": "top | bottom | shoes | media | other", "color": "couleur principale",
             "description": "description légère en une phrase (matière, motif, coupe)"}
            "category" : top pour un haut, bottom pour un bas, shoes pour des chaussures, media pour un disque/livre/film, other sinon.
            Si la photo ne montre pas d'objet identifiable, mets "name" à null.
            TXT,
        'label' => <<<'TXT'
            Tu aides à ranger une garde-robe. La photo montre l'étiquette d'un vêtement : lis-la.
            Réponds UNIQUEMENT avec un objet JSON, sans texte autour, de la forme :
            {"name": "nom court déduit de l'étiquette, ex. T-shirt Petit Bateau", "type": "type de vêtement s'il est indiqué, sinon null",
             "category": "top | bottom | shoes | media | other", "color": "couleur si visible, sinon null",
             "brand": "marque ou null", "size": "taille ou null", "composition": "composition (ex. 100 % coton) ou null",
             "description": "description légère en une phrase à partir des informations lues"}
            Si aucune étiquette n'est lisible, mets "name" à null.
            TXT,
    ];

    /** @var list<string> */
    private readonly array $models;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(OPENROUTER_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(OPENROUTER_MODELS)%')]
        string $models,
    ) {
        $this->models = array_values(array_filter(array_map('trim', explode(',', $models))));
    }

    public function isConfigured(): bool
    {
        return '' !== $this->apiKey && [] !== $this->models;
    }

    /**
     * @param string $imageDataUrl image JPEG encodée en base64 (data:image/jpeg;base64,...)
     * @param string $mode         'photo' (identifier l'objet) ou 'label' (lire l'étiquette)
     *
     * @return array{name: ?string, type: ?string, categoryType: string, color: ?string, description: ?string, brand: ?string, size: ?string, composition: ?string, model: string}
     *
     * @throws ScanFailedException si aucun modèle n'a pu répondre
     */
    public function analyze(string $imageDataUrl, string $mode = 'photo'): array
    {
        $prompt = self::PROMPTS[$mode] ?? self::PROMPTS['photo'];
        $deadline = microtime(true) + self::TIME_BUDGET;
        $errors = [];
        // Reste vrai tant que tous les échecs viennent d'une saturation des modèles gratuits
        $onlyBusy = true;

        foreach ($this->models as $model) {
            for ($attempt = 1; $attempt <= 2; ++$attempt) {
                $remaining = $deadline - microtime(true);
                if ($remaining < 5) {
                    $errors[] = 'budget de temps dépassé, modèles suivants non essayés';
                    break 2;
                }

                try {
                    $content = $this->ask($model, $prompt, $imageDataUrl, min(self::MODEL_TIMEOUT, $remaining));
                    $data = $this->extractJson($content);
                    if (null !== $data) {
                        return $this->normalize($data) + ['model' => $model];
                    }
                    $errors[] = \sprintf('%s : réponse illisible', $model);
                    $onlyBusy = false;
                } catch (\RuntimeException $e) {
                    $status = $e->getCode();
                    $errors[] = \sprintf('%s (essai %d) : %s', $model, $attempt, $e->getMessage());
                    // Saturé ou erreur passagère du fournisseur : une seconde tentative après une pause
                    if (429 === $status || $status >= 500) {
                        if (1 === $attempt) {
                            usleep(self::RETRY_DELAY_US);
                            continue;
                        }
                    } else {
                        $onlyBusy = false;
                    }
                } catch (ExceptionInterface $e) {
                    // Délai dépassé ou réseau : on passe directement au modèle suivant
                    $errors[] = \sprintf('%s : %s', $model, $e->getMessage());
                }
                break;
            }
        }

        $this->logger->warning('Scan OpenRouter : aucun modèle n\'a répondu. '.implode(' | ', $errors));

        throw new ScanFailedException($onlyBusy
            ? 'Les modèles d\'IA gratuits sont saturés en ce moment : réessaie dans une minute.'
            : 'Aucun modèle n\'a pu analyser la photo. Réessaie dans un instant.', $errors);
    }

    /**
     * @throws \RuntimeException avec le code HTTP en code d'erreur si le modèle répond en erreur
     */
    private function ask(string $model, string $prompt, string $imageDataUrl, float $maxDuration): string
    {
        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                // En-têtes facultatifs d'identification de l'application auprès d'OpenRouter
                'X-Title' => 'Penderie',
            ],
            'json' => [
                'model' => $model,
                'temperature' => 0.2,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]],
                    ],
                ]],
            ],
            'timeout' => $maxDuration,
            'max_duration' => $maxDuration,
        ]);

        $status = $response->getStatusCode();
        try {
            $body = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            // Corps vide ou non JSON : on garde le code HTTP pour décider d'une nouvelle tentative
            throw new \RuntimeException(\sprintf('HTTP %d réponse non JSON', $status), $status);
        }
        if ($status >= 400 || isset($body['error'])) {
            throw new \RuntimeException(\sprintf('HTTP %d %s', $status, $body['error']['message'] ?? ''), $status);
        }

        $content = $body['choices'][0]['message']['content'] ?? '';
        // Certains modèles renvoient le contenu sous forme de liste de blocs
        if (\is_array($content)) {
            $content = implode('', array_map(fn (array $part) => $part['text'] ?? '', $content));
        }

        return (string) $content;
    }

    /**
     * Récupère l'objet JSON de la réponse, même entouré de texte ou d'un bloc ```json.
     *
     * @return array<string, mixed>|null
     */
    private function extractJson(string $content): ?array
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if (false === $start || false === $end || $end < $start) {
            return null;
        }

        $data = json_decode(substr($content, $start, $end - $start + 1), true);

        return \is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{name: ?string, type: ?string, categoryType: string, color: ?string, description: ?string, brand: ?string, size: ?string, composition: ?string}
     */
    private function normalize(array $data): array
    {
        $text = static function (mixed $value): ?string {
            if (!\is_scalar($value)) {
                return null;
            }
            $value = trim((string) $value);

            return '' === $value || 'null' === strtolower($value) ? null : mb_substr($value, 0, 255);
        };

        // Catégorie inconnue ou absente : "other"
        $categoryType = CategoryType::tryFrom(strtolower((string) ($data['category'] ?? '')))?->value ?? CategoryType::Other->value;

        return [
            'name' => $text($data['name'] ?? null),
            'type' => $text($data['type'] ?? null),
            'categoryType' => $categoryType,
            'color' => $text($data['color'] ?? null),
            'description' => $text($data['description'] ?? null),
            'brand' => $text($data['brand'] ?? null),
            'size' => $text($data['size'] ?? null),
            'composition' => $text($data['composition'] ?? null),
        ];
    }
}
