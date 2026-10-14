<?php

namespace App\Controller;

use App\Entity\Item;
use App\Service\ItemPhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Photo d'un objet : prise au scan ou choisie dans la galerie, envoyée en JPEG base64.
 * Seul le propriétaire de l'objet peut la changer ou la retirer.
 */
final class ItemPhotoController extends AbstractController
{
    // Même limite que pour le scan : une image 1024 px en JPEG pèse quelques centaines de Ko
    private const MAX_IMAGE_LENGTH = 3_000_000;

    public function __construct(
        private readonly ItemPhotoStorage $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id<\d+>}/photo', name: 'api_item_photo_upload', methods: ['POST'])]
    public function upload(int $id, Request $request): JsonResponse
    {
        $item = $this->findOwnItem($id);
        if (null === $item) {
            return $this->error('Cet objet est introuvable.', Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $image = \is_array($payload) ? ($payload['image'] ?? null) : null;
        if (!\is_string($image)) {
            return $this->error('Envoie une image JPEG encodée en base64 (data:image/jpeg;base64,...).', Response::HTTP_BAD_REQUEST);
        }
        if (\strlen($image) > self::MAX_IMAGE_LENGTH) {
            return $this->error('Image trop lourde : redimensionne-la à 1024 px avant l\'envoi.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $photo = $this->storage->store($item, $image);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        $this->entityManager->flush();

        return $this->json(['photo' => $photo]);
    }

    #[Route('/api/items/{id<\d+>}/photo', name: 'api_item_photo_delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $item = $this->findOwnItem($id);
        if (null === $item) {
            return $this->error('Cet objet est introuvable.', Response::HTTP_NOT_FOUND);
        }

        $this->storage->remove($item);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    // L'objet d'un autre utilisateur répond 404, comme dans le reste de l'API
    private function findOwnItem(int $id): ?Item
    {
        $item = $this->entityManager->getRepository(Item::class)->find($id);

        return null !== $item && $item->getOwner() === $this->getUser() ? $item : null;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return $this->json(['message' => $message], $status);
    }
}
