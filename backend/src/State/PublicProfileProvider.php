<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Enum\RelationshipType;
use App\Repository\RelationshipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Profil public : le compte est lu par le provider Doctrine (PublicUserExtension s'y applique),
 * puis on y ajoute mon lien avec lui : mes demandes d'abonnement et d'ami proche envoyées.
 *
 * @implements ProviderInterface<User>
 */
final class PublicProfileProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<User> $itemProvider
     */
    public function __construct(
        #[Autowire(service: ItemProvider::class)]
        private readonly ProviderInterface $itemProvider,
        private readonly Security $security,
        private readonly RelationshipRepository $relationships,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?User
    {
        $profile = $this->itemProvider->provide($operation, $uriVariables, $context);
        $viewer = $this->security->getUser();
        // Introuvable (inexistant, suspendu, bloqué, mineur) : 404
        if (!$profile instanceof User || !$viewer instanceof User) {
            return null;
        }

        return $profile->setViewerRelationship([
            'isMe' => $viewer === $profile,
            'follower' => $this->state($viewer, $profile, RelationshipType::Follower),
            'closeFriend' => $this->state($viewer, $profile, RelationshipType::CloseFriend),
        ]);
    }

    /**
     * @return array{status: string, id: ?int}
     */
    private function state(User $viewer, User $profile, RelationshipType $type): array
    {
        $relationship = $this->relationships->findOneBy(['requester' => $viewer, 'addressee' => $profile, 'type' => $type]);

        return [
            // none : aucune demande, pending : en attente, accepted : acceptée
            'status' => $relationship?->getStatus()->value ?? 'none',
            // Permettra d'annuler la demande ou de se désabonner
            'id' => $relationship?->getId(),
        ];
    }
}
