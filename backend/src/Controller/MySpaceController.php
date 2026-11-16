<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\MySpace;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

// « Mon espace » (docs/decisions.md) : mes chiffres et l'export de mes données, pour la personne connectée seulement
final class MySpaceController extends AbstractController
{
    public function __construct(private readonly MySpace $mySpace)
    {
    }

    #[Route('/api/me/stats', name: 'api_me_stats', methods: ['GET'])]
    public function stats(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->mySpace->stats($user));
    }

    // Fichier JSON à télécharger (droit d'accès et à la portabilité, RGPD art. 15 et 20)
    #[Route('/api/me/export', name: 'api_me_export', methods: ['GET'])]
    public function export(#[CurrentUser] User $user, ClockInterface $clock): JsonResponse
    {
        $response = new JsonResponse($this->mySpace->export($user), json: false);
        $response->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            \sprintf('lendly-mes-donnees-%s.json', $clock->now()->format('Y-m-d')),
        ));

        return $response;
    }
}
