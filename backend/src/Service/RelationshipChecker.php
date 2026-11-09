<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\RelationshipType;
use App\Repository\BlockRepository;
use App\Repository\RelationshipRepository;

/**
 * Liens entre deux personnes et droits qui en découlent (voir docs/decisions.md).
 * Le sens compte toujours : $viewer regarde le contenu de $owner.
 */
final class RelationshipChecker
{
    public function __construct(
        private readonly RelationshipRepository $relationships,
        private readonly BlockRepository $blocks,
    ) {
    }

    // Abonné accepté. Un ami proche accepté a au moins les droits d'un abonné
    public function isFollower(User $viewer, User $owner): bool
    {
        return $this->relationships->hasAccepted($viewer, $owner, [RelationshipType::Follower, RelationshipType::CloseFriend]);
    }

    public function isCloseFriend(User $viewer, User $owner): bool
    {
        return $this->relationships->hasAccepted($viewer, $owner, [RelationshipType::CloseFriend]);
    }

    // Compte enfant direct (les petits-enfants ne comptent pas)
    public function isChildOf(User $child, User $parent): bool
    {
        return $child->getParent() === $parent;
    }

    // Un blocage agit dans les deux sens
    public function isBlocked(User $a, User $b): bool
    {
        return $a !== $b && $this->blocks->existsBetween($a, $b);
    }

    // OOTD publié : propriétaire, comptes enfants, amis proches et abonnés acceptés
    public function canSeeOotdOf(User $viewer, User $owner): bool
    {
        if ($viewer === $owner) {
            return true;
        }
        if ($this->isBlocked($viewer, $owner)) {
            return false;
        }

        return $this->isChildOf($viewer, $owner) || $this->isFollower($viewer, $owner);
    }

    // Commentaire : propriétaire, comptes enfants majeurs et amis proches acceptés
    public function canCommentOotdOf(User $viewer, User $owner): bool
    {
        if ($viewer === $owner) {
            return true;
        }
        if ($this->isBlocked($viewer, $owner)) {
            return false;
        }

        return ($this->isChildOf($viewer, $owner) && !$viewer->isMinor()) || $this->isCloseFriend($viewer, $owner);
    }
}
