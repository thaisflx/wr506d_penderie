<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use App\Repository\RelationshipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

// Profil de l'utilisateur connecté, utilisé par le front (en-tête, page profil)
final class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(#[CurrentUser] User $user, RelationshipRepository $relationships): JsonResponse
    {
        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'avatar' => $user->getAvatar(),
            'bio' => $user->getBio(),
            'roles' => $user->getRoles(),
            'isMinor' => $user->isMinor(),
            'itemCount' => $user->getItems()->count(),
            'followerCount' => $relationships->countReceived($user, RelationshipStatus::Accepted, RelationshipType::Follower),
            'closeFriendCount' => $relationships->countReceived($user, RelationshipStatus::Accepted, RelationshipType::CloseFriend),
            'pendingRequestCount' => $relationships->countReceived($user, RelationshipStatus::Pending),
            // Comptes enfants rattachés
            'children' => $user->getChildren()->map(fn (User $child) => [
                'id' => $child->getId(),
                'username' => $child->getUsername(),
                'avatar' => $child->getAvatar(),
                'isMinor' => $child->isMinor(),
            ])->getValues(),
        ]);
    }
}
