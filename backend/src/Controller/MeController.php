<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use App\Repository\RelationshipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Profil de l'utilisateur connecté, utilisé par le front (en-tête, page profil)
final class MeController extends AbstractController
{
    public function __construct(private readonly RelationshipRepository $relationships)
    {
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->profile($user));
    }

    /**
     * Change la langue du compte (docs/decisions.md, « Internationalisation ») : seul le champ "locale" est accepté,
     * pour que cette route ne permette de modifier ni l'email, ni les rôles, ni rien d'autre.
     */
    #[Route('/api/me', name: 'api_me_update', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] User $user,
        Request $request,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || ['locale'] !== array_keys($payload) || !\is_string($payload['locale'])) {
            return $this->json(['detail' => $translator->trans('error.me_locale_only')], Response::HTTP_BAD_REQUEST);
        }

        $previous = $user->getLocale();
        $user->setLocale($payload['locale']);
        $violations = $validator->validateProperty($user, 'locale');
        if (\count($violations) > 0) {
            $user->setLocale($previous);
            $message = (string) $violations[0]->getMessage();

            return $this->json([
                'detail' => $message,
                'violations' => [['propertyPath' => 'locale', 'message' => $message]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $entityManager->flush();

        return $this->json($this->profile($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'avatar' => $user->getAvatar(),
            'bio' => $user->getBio(),
            'roles' => $user->getRoles(),
            'locale' => $user->getLocale(),
            'isMinor' => $user->isMinor(),
            'itemCount' => $user->getItems()->count(),
            'followerCount' => $this->relationships->countReceived($user, RelationshipStatus::Accepted, RelationshipType::Follower),
            'closeFriendCount' => $this->relationships->countReceived($user, RelationshipStatus::Accepted, RelationshipType::CloseFriend),
            'pendingRequestCount' => $this->relationships->countReceived($user, RelationshipStatus::Pending),
            // Comptes enfants rattachés
            'children' => $user->getChildren()->map(fn (User $child) => [
                'id' => $child->getId(),
                'username' => $child->getUsername(),
                'avatar' => $child->getAvatar(),
                'isMinor' => $child->isMinor(),
            ])->getValues(),
        ];
    }
}
