<?php

namespace App\Controller;

use App\Service\ClothingScanner;
use App\Service\ScanFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Scan d'un vêtement : le front envoie la photo redimensionnée (1024 px max) en JPEG base64,
 * on la transmet au modèle de vision et on renvoie couleur, type et description.
 * L'appel passe par le serveur pour que la clé OpenRouter ne soit jamais exposée au navigateur.
 */
final class ScanController extends AbstractController
{
    // Une image 1024 px en JPEG pèse quelques centaines de Ko : au-delà, la requête est refusée
    private const MAX_IMAGE_LENGTH = 3_000_000;

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    #[Route('/api/scan', name: 'api_scan', methods: ['POST'])]
    public function __invoke(Request $request, ClothingScanner $scanner): JsonResponse
    {
        if (!$scanner->isConfigured()) {
            return $this->error('scan.not_configured', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $payload = json_decode($request->getContent(), true);
        $image = \is_array($payload) ? ($payload['image'] ?? null) : null;
        $mode = \is_array($payload) && 'label' === ($payload['mode'] ?? null) ? 'label' : 'photo';

        if (!\is_string($image) || !str_starts_with($image, 'data:image/jpeg;base64,')) {
            return $this->error('photo.invalid', Response::HTTP_BAD_REQUEST);
        }
        if (\strlen($image) > self::MAX_IMAGE_LENGTH) {
            return $this->error('photo.too_heavy', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            // L'IA répond dans la langue de la requête (LocaleSubscriber)
            return $this->json($scanner->analyze($image, $mode, $request->getLocale()));
        } catch (ScanFailedException $e) {
            return $this->error($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }
    }

    // Message traduit dans la langue de la requête (clé du domaine messages)
    private function error(string $key, int $status): JsonResponse
    {
        return $this->json(['message' => $this->translator->trans($key)], $status);
    }
}
