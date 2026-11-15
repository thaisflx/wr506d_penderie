<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lecture commune d'un envoi de photo ({"image": "data:image/jpeg;base64,..."}) et format des erreurs,
 * pour les objets et les looks du jour.
 */
trait PhotoRequestTrait
{
    /**
     * Renvoie la data URL envoyée, ou la réponse d'erreur à renvoyer telle quelle.
     */
    private function photoFromRequest(Request $request): string|JsonResponse
    {
        // Même limite que pour le scan : une image 1024 px en JPEG pèse quelques centaines de Ko
        $maxLength = 3_000_000;

        $payload = json_decode($request->getContent(), true);
        $image = \is_array($payload) ? ($payload['image'] ?? null) : null;
        if (!\is_string($image)) {
            return $this->photoError('Envoie une image JPEG encodée en base64 (data:image/jpeg;base64,...).', Response::HTTP_BAD_REQUEST);
        }
        if (\strlen($image) > $maxLength) {
            return $this->photoError('Image trop lourde : redimensionne-la à 1024 px avant l\'envoi.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $image;
    }

    private function photoError(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
