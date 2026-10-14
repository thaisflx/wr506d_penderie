<?php

namespace App\Controller;

use App\Service\ClothingScanner;
use App\Service\ScanFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Scan d'un vêtement : le front envoie la photo redimensionnée (1024 px max) en JPEG base64,
 * on la transmet au modèle de vision et on renvoie couleur, type et description.
 * L'appel passe par le serveur pour que la clé OpenRouter ne soit jamais exposée au navigateur.
 */
final class ScanController extends AbstractController
{
    // Une image 1024 px en JPEG pèse quelques centaines de Ko : au-delà, la requête est refusée
    private const MAX_IMAGE_LENGTH = 3_000_000;

    #[Route('/api/scan', name: 'api_scan', methods: ['POST'])]
    public function __invoke(Request $request, ClothingScanner $scanner): JsonResponse
    {
        if (!$scanner->isConfigured()) {
            return $this->error('Le scan n\'est pas configuré : ajoute OPENROUTER_API_KEY dans backend/.env.local.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $payload = json_decode($request->getContent(), true);
        $image = \is_array($payload) ? ($payload['image'] ?? null) : null;
        $mode = \is_array($payload) && 'label' === ($payload['mode'] ?? null) ? 'label' : 'photo';

        if (!\is_string($image) || !str_starts_with($image, 'data:image/jpeg;base64,')) {
            return $this->error('Envoie une image JPEG encodée en base64 (data:image/jpeg;base64,...).', Response::HTTP_BAD_REQUEST);
        }
        if (\strlen($image) > self::MAX_IMAGE_LENGTH) {
            return $this->error('Image trop lourde : redimensionne-la à 1024 px avant l\'envoi.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            return $this->json($scanner->analyze($image, $mode));
        } catch (ScanFailedException $e) {
            return $this->error($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }
    }

    private function error(string $message, int $status): JsonResponse
    {
        return $this->json(['message' => $message], $status);
    }
}
