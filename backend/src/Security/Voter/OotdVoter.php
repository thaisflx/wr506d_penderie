<?php

namespace App\Security\Voter;

use App\Entity\Ootd;
use App\Entity\User;
use App\Service\RelationshipChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un look du jour (docs/decisions.md, lot 2), premier Voter du projet.
 *
 * - OOTD_VIEW : brouillon, le propriétaire seul ; publié, RelationshipChecker::canSeeOotdOf.
 * - OOTD_EDIT : modifier, publier, supprimer, changer la photo : le propriétaire seul.
 *
 * @extends Voter<string, Ootd>
 */
final class OotdVoter extends Voter
{
    public const VIEW = 'OOTD_VIEW';
    public const EDIT = 'OOTD_EDIT';

    public function __construct(private readonly RelationshipChecker $relationships)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof Ootd;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $owner = $subject->getOwner();
        if (!$user instanceof User || null === $owner) {
            return false;
        }
        if ($user === $owner) {
            return true;
        }

        return self::VIEW === $attribute && $subject->isPublished() && $this->relationships->canSeeOotdOf($user, $owner);
    }
}
