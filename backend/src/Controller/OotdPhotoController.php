<?php

namespace App\Controller;

use App\Entity\Ootd;
use App\Security\Voter\OotdVoter;
use App\Service\OotdPhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Photo d'un look du jour : prise avec la caméra ou choisie dans la galerie, envoyée en JPEG base64.
 * Mêmes codes que l'API des looks : 404 si je ne peux pas voir le look, 403 si je le vois sans en être
 * le propriétaire (OotdVoter).
 */
final class OotdPhotoController extends AbstractController
{
    use PhotoRequestTrait;

    public function __construct(
        private readonly OotdPhotoStorage $storage,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/api/ootds/{id<\d+>}/photo', name: 'api_ootd_photo_upload', methods: ['POST'])]
    public function upload(int $id, Request $request): JsonResponse
    {
        $ootd = $this->findEditableOotd($id);
        if ($ootd instanceof JsonResponse) {
            return $ootd;
        }

        $image = $this->photoFromRequest($request);
        if ($image instanceof JsonResponse) {
            return $image;
        }

        try {
            $photo = $this->storage->store($ootd, $image);
        } catch (\InvalidArgumentException $e) {
            return $this->photoError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        $this->entityManager->flush();

        return $this->json(['photo' => $photo]);
    }

    #[Route('/api/ootds/{id<\d+>}/photo', name: 'api_ootd_photo_delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $ootd = $this->findEditableOotd($id);
        if ($ootd instanceof JsonResponse) {
            return $ootd;
        }
        // Un look publié a forcément une photo : on la remplace, ou on supprime le look
        if ($ootd->isPublished()) {
            return $this->photoError('error.ootd_published_keeps_photo', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->storage->remove($ootd);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function findEditableOotd(int $id): Ootd|JsonResponse
    {
        $ootd = $this->entityManager->getRepository(Ootd::class)->find($id);
        if (null === $ootd || !$this->isGranted(OotdVoter::VIEW, $ootd)) {
            return $this->photoError('error.ootd_not_found', Response::HTTP_NOT_FOUND);
        }
        if (!$this->isGranted(OotdVoter::EDIT, $ootd)) {
            return $this->photoError('error.ootd_photo_owner_only', Response::HTTP_FORBIDDEN);
        }

        return $ootd;
    }
}
