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
    use PhotoRequestTrait;

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
            return $this->photoError('Cet objet est introuvable.', Response::HTTP_NOT_FOUND);
        }

        $image = $this->photoFromRequest($request);
        if ($image instanceof JsonResponse) {
            return $image;
        }

        try {
            $photo = $this->storage->store($item, $image);
        } catch (\InvalidArgumentException $e) {
            return $this->photoError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        $this->entityManager->flush();

        return $this->json(['photo' => $photo]);
    }

    #[Route('/api/items/{id<\d+>}/photo', name: 'api_item_photo_delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $item = $this->findOwnItem($id);
        if (null === $item) {
            return $this->photoError('Cet objet est introuvable.', Response::HTTP_NOT_FOUND);
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
}
