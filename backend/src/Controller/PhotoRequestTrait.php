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
            return $this->photoError('photo.invalid', Response::HTTP_BAD_REQUEST);
        }
        if (\strlen($image) > $maxLength) {
            return $this->photoError('photo.too_heavy', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $image;
    }

    // Message traduit dans la langue de la requête (clé du domaine messages) ; le contrôleur fournit $translator
    private function photoError(string $key, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $this->translator->trans($key)], $status);
    }
}
