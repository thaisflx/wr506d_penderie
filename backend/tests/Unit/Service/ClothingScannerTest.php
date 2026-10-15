<?php

namespace App\Tests\Unit\Service;

use App\Service\ClothingScanner;
use App\Service\ScanFailedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Le scanner est testé sans réseau (MockHttpClient) et sans attente réelle (MockClock) :
 * les pauses de nouvelle tentative et le budget de temps avancent une horloge simulée.
 */
final class ClothingScannerTest extends TestCase
{
    private const IMAGE = 'data:image/jpeg;base64,/9j/AAAA';
    private const START = '2026-10-15 10:00:00';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(self::START);
    }

    public function testIsConfiguredWithKeyAndModels(): void
    {
        self::assertTrue($this->scanner([])->isConfigured());
    }

    public function testIsNotConfiguredWithoutApiKey(): void
    {
        self::assertFalse($this->scanner([], apiKey: '')->isConfigured());
    }

    public function testIsNotConfiguredWithoutModels(): void
    {
        // Les entrées vides ou faites d'espaces sont ignorées
        self::assertFalse($this->scanner([], models: ' , ,')->isConfigured());
    }

    public function testPhotoScanSucceedsWithFirstModel(): void
    {
        $response = $this->ok('{"name": "Pull bleu marine", "type": "pull", "category": "top", "color": "bleu", "description": "Pull en laine."}');
        $result = $this->scanner([$response])->analyze(self::IMAGE);

        self::assertSame([
            'name' => 'Pull bleu marine',
            'type' => 'pull',
            'categoryType' => 'top',
            'color' => 'bleu',
            'description' => 'Pull en laine.',
            'brand' => null,
            'size' => null,
            'composition' => null,
            'model' => 'model-a',
        ], $result);

        // Requête envoyée à OpenRouter : clé, modèle, consigne « photo », image et délai maximal de 30 s
        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('https://openrouter.ai/api/v1/chat/completions', $response->getRequestUrl());
        $options = $response->getRequestOptions();
        self::assertSame(['Authorization: Bearer sk-test'], $options['normalized_headers']['authorization']);
        self::assertSame(30.0, $options['timeout']);
        self::assertSame(30.0, $options['max_duration']);

        $body = json_decode($options['body'], true);
        self::assertSame('model-a', $body['model']);
        self::assertStringContainsString('identifie l\'objet principal', $body['messages'][0]['content'][0]['text']);
        self::assertSame(self::IMAGE, $body['messages'][0]['content'][1]['image_url']['url']);
    }

    public function testLabelModeUsesLabelPromptAndReturnsLabelFields(): void
    {
        $response = $this->ok('{"name": "T-shirt Petit Bateau", "category": "top", "brand": "Petit Bateau", "size": "M", "composition": "100 % coton"}');
        $result = $this->scanner([$response])->analyze(self::IMAGE, 'label');

        self::assertSame('Petit Bateau', $result['brand']);
        self::assertSame('M', $result['size']);
        self::assertSame('100 % coton', $result['composition']);
        self::assertStringContainsString('étiquette', $this->prompt($response));
    }

    public function testUnknownModeFallsBackToPhotoPrompt(): void
    {
        $response = $this->ok('{"name": "Pull"}');
        $this->scanner([$response])->analyze(self::IMAGE, 'inconnu');

        self::assertStringContainsString('identifie l\'objet principal', $this->prompt($response));
    }

    public function testJsonSurroundedByTextIsExtracted(): void
    {
        $content = "Voici le résultat :\n```json\n{\"name\": \"Jean brut\", \"category\": \"bottom\"}\n```\nBonne journée !";
        $result = $this->scanner([$this->ok($content)])->analyze(self::IMAGE);

        self::assertSame('Jean brut', $result['name']);
        self::assertSame('bottom', $result['categoryType']);
    }

    public function testContentAsListOfBlocksIsJoined(): void
    {
        $blocks = [
            ['type' => 'text', 'text' => '{"name": "Baskets",'],
            ['type' => 'image'], // bloc sans texte : ignoré
            ['type' => 'text', 'text' => ' "category": "shoes"}'],
        ];
        $result = $this->scanner([$this->ok($blocks)])->analyze(self::IMAGE);

        self::assertSame('Baskets', $result['name']);
        self::assertSame('shoes', $result['categoryType']);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function categoryProvider(): iterable
    {
        yield 'catégorie connue' => ['media', 'media'];
        yield 'majuscules' => ['SHOES', 'shoes'];
        yield 'catégorie inconnue' => ['chapeau', 'other'];
        yield 'catégorie absente' => [null, 'other'];
    }

    #[DataProvider('categoryProvider')]
    public function testCategoryIsNormalized(mixed $category, string $expected): void
    {
        $result = $this->scanner([$this->ok(json_encode(['name' => 'Objet', 'category' => $category]))])->analyze(self::IMAGE);

        self::assertSame($expected, $result['categoryType']);
    }

    public function testTextFieldsAreCleaned(): void
    {
        $result = $this->scanner([$this->ok(json_encode([
            'name' => '  Pull  ',          // espaces retirés
            'type' => 'null',              // "null" écrit en texte par le modèle
            'color' => '   ',              // vide
            'description' => str_repeat('a', 300), // tronqué à 255 caractères
            'brand' => ['Nike'],           // pas un texte
            'size' => 42,                  // nombre converti en texte
        ]))])->analyze(self::IMAGE);

        self::assertSame('Pull', $result['name']);
        self::assertNull($result['type']);
        self::assertNull($result['color']);
        self::assertSame(255, mb_strlen((string) $result['description']));
        self::assertNull($result['brand']);
        self::assertSame('42', $result['size']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableProvider(): iterable
    {
        yield 'texte sans JSON' => ['Je ne sais pas ce que c\'est.'];
        yield 'accolades inversées' => ['} rien {'];
        yield 'JSON invalide' => ['{nom: Pull}'];
        yield 'contenu vide' => [''];
    }

    #[DataProvider('unreadableProvider')]
    public function testUnreadableResponseTriesNextModel(string $content): void
    {
        $client = new MockHttpClient([$this->ok($content), $this->ok('{"name": "Pull"}')]);
        $result = $this->scanner($client)->analyze(self::IMAGE);

        self::assertSame('model-b', $result['model']);
        // Réponse illisible : pas de seconde tentative sur le même modèle
        self::assertSame(2, $client->getRequestsCount());
    }

    public function testRateLimitedThenSuccessRetriesSameModelAfterPause(): void
    {
        $client = new MockHttpClient([
            $this->error(429, 'Rate limit exceeded'),
            $this->ok('{"name": "Pull"}'),
        ]);
        $result = $this->scanner($client)->analyze(self::IMAGE);

        self::assertSame('model-a', $result['model']);
        self::assertSame(2, $client->getRequestsCount());
        $this->assertSleptSeconds(1.5);
    }

    public function testServerErrorTwiceMovesToNextModel(): void
    {
        $client = new MockHttpClient([
            $this->error(503, 'Service unavailable'),
            $this->error(500, 'Internal error'),
            $this->ok('{"name": "Pull"}'),
        ]);
        $result = $this->scanner($client)->analyze(self::IMAGE);

        self::assertSame('model-b', $result['model']);
        self::assertSame(3, $client->getRequestsCount());
        // Une seule pause : entre les deux essais sur model-a
        $this->assertSleptSeconds(1.5);
    }

    public function testNonJsonServerErrorIsRetried(): void
    {
        // Page HTML d'une passerelle en erreur : le code HTTP suffit pour décider de réessayer
        $client = new MockHttpClient([
            new MockResponse('<html>Bad gateway</html>', ['http_code' => 502]),
            $this->ok('{"name": "Pull"}'),
        ]);
        $result = $this->scanner($client)->analyze(self::IMAGE);

        self::assertSame('model-a', $result['model']);
        self::assertSame(2, $client->getRequestsCount());
    }

    public function testClientErrorMovesToNextModelWithoutRetry(): void
    {
        $client = new MockHttpClient([
            $this->error(401, 'Invalid API key'),
            $this->ok('{"name": "Pull"}'),
        ]);
        $result = $this->scanner($client)->analyze(self::IMAGE);

        self::assertSame('model-b', $result['model']);
        self::assertSame(2, $client->getRequestsCount());
        $this->assertSleptSeconds(0);
    }

    public function testErrorInBodyWithStatus200IsTreatedAsFailure(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"error": {"message": "Provider returned error"}}', ['http_code' => 200]),
            $this->ok('{"name": "Pull"}'),
        ]);

        self::assertSame('model-b', $this->scanner($client)->analyze(self::IMAGE)['model']);
    }

    public function testNetworkErrorMovesToNextModelWithoutRetry(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['error' => 'Could not resolve host: openrouter.ai']),
            $this->ok('{"name": "Pull"}'),
        ]);
        $result = $this->scanner($client)->analyze(self::IMAGE);

        self::assertSame('model-b', $result['model']);
        self::assertSame(2, $client->getRequestsCount());
        $this->assertSleptSeconds(0);
    }

    public function testAllModelsBusyGivesBusyMessageAndLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(self::stringContains('model-a (essai 1) : HTTP 429'));

        $scanner = $this->scanner(new MockHttpClient([
            $this->error(429, 'Rate limit'), $this->error(429, 'Rate limit'),
            $this->error(503, 'Overloaded'), $this->error(503, 'Overloaded'),
        ]), logger: $logger);

        $exception = $this->expectScanFailure($scanner);
        self::assertSame('Les modèles d\'IA gratuits sont saturés en ce moment : réessaie dans une minute.', $exception->getMessage());
        self::assertCount(4, $exception->errors);
    }

    public function testFailureNotOnlyCausedByBusyModelsGivesGenericMessage(): void
    {
        $scanner = $this->scanner(new MockHttpClient([
            $this->error(429, 'Rate limit'), $this->error(429, 'Rate limit'),
            $this->ok('pas de JSON'),
        ]));

        $exception = $this->expectScanFailure($scanner);
        self::assertSame('Aucun modèle n\'a pu analyser la photo. Réessaie dans un instant.', $exception->getMessage());
        self::assertContains('model-b : réponse illisible', $exception->errors);
    }

    public function testNetworkErrorOnEveryModelGivesGenericMessage(): void
    {
        $scanner = $this->scanner(new MockHttpClient([
            new MockResponse('', ['error' => 'Timeout']),
            new MockResponse('', ['error' => 'Timeout']),
        ]));

        $exception = $this->expectScanFailure($scanner);
        self::assertSame('Aucun modèle n\'a pu analyser la photo. Réessaie dans un instant.', $exception->getMessage());
        self::assertSame(['model-a : Timeout', 'model-b : Timeout'], $exception->errors);
    }

    public function testRetryTimeoutIsLimitedByRemainingBudget(): void
    {
        // Le premier essai consomme 25 s, puis 1,5 s de pause : il reste 23,5 s sur les 50 s
        $responses = [];
        $client = new MockHttpClient(function () use (&$responses) {
            if ([] === $responses) {
                $this->clock->sleep(25);
                $responses[] = $this->error(500, 'Timeout upstream');
            } else {
                $responses[] = $this->ok('{"name": "Pull"}');
            }

            return end($responses);
        });
        $this->scanner($client)->analyze(self::IMAGE);

        self::assertSame(30.0, $responses[0]->getRequestOptions()['timeout']);
        self::assertSame(23.5, $responses[1]->getRequestOptions()['timeout']);
    }

    public function testTimeBudgetExceededStopsBeforeNextModels(): void
    {
        // Le premier modèle met 46 s à répondre 429 : après la pause, il reste moins de 5 s
        $client = new MockHttpClient(function () {
            $this->clock->sleep(46);

            return $this->error(429, 'Rate limit');
        });

        $exception = $this->expectScanFailure($this->scanner($client));
        self::assertSame(1, $client->getRequestsCount());
        self::assertSame([
            'model-a (essai 1) : HTTP 429 Rate limit',
            'budget de temps dépassé, modèles suivants non essayés',
        ], $exception->errors);
    }

    /**
     * @param MockHttpClient|list<MockResponse> $responses
     */
    private function scanner(
        MockHttpClient|array $responses,
        string $apiKey = 'sk-test',
        string $models = 'model-a, model-b',
        ?LoggerInterface $logger = null,
    ): ClothingScanner {
        $client = $responses instanceof MockHttpClient ? $responses : new MockHttpClient($responses);

        return new ClothingScanner($client, $logger ?? new NullLogger(), $this->clock, $apiKey, $models);
    }

    /**
     * @param string|list<array<string, string>> $content
     */
    private function ok(string|array $content): MockResponse
    {
        return new MockResponse((string) json_encode(['choices' => [['message' => ['content' => $content]]]]), ['http_code' => 200]);
    }

    private function error(int $status, string $message): MockResponse
    {
        return new MockResponse((string) json_encode(['error' => ['message' => $message]]), ['http_code' => $status]);
    }

    private function prompt(MockResponse $response): string
    {
        return json_decode($response->getRequestOptions()['body'], true)['messages'][0]['content'][0]['text'];
    }

    private function assertSleptSeconds(float $seconds): void
    {
        $elapsed = (float) $this->clock->now()->format('U.u') - (float) (new \DateTimeImmutable(self::START))->format('U.u');
        self::assertEqualsWithDelta($seconds, $elapsed, 0.001);
    }

    private function expectScanFailure(ClothingScanner $scanner): ScanFailedException
    {
        try {
            $scanner->analyze(self::IMAGE);
        } catch (ScanFailedException $e) {
            return $e;
        }

        self::fail('ScanFailedException attendue');
    }
}
