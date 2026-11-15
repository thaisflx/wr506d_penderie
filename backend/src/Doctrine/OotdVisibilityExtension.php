<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Block;
use App\Entity\Ootd;
use App\Entity\Relationship;
use App\Entity\User;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Looks du jour qu'un utilisateur peut voir (docs/decisions.md, lot 2) : la même règle que
 * RelationshipChecker::canSeeOotdOf, écrite en DQL (les deux sont testées l'une contre l'autre).
 *
 * - Brouillon : le propriétaire seul.
 * - Publié : le propriétaire, ses comptes enfants (+18 et -18), ses amis proches et abonnés acceptés,
 *   jamais une personne liée par un blocage, dans un sens ou dans l'autre.
 *
 * Un look invisible n'est jamais trouvé : 404, sans révéler s'il existe.
 */
final class OotdVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    // Noms des opérations de l'entité Ootd
    public const FEED = 'ootd_feed';
    public const MINE = 'ootd_mine';
    public const USER = 'ootd_user';

    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Ootd::class !== $resourceClass) {
            return;
        }
        $viewer = $this->viewer($queryBuilder);
        if (null === $viewer) {
            return;
        }
        $alias = $queryBuilder->getRootAliases()[0];

        // Mes looks (profil) : brouillons et publiés, filtrables avec ?published=true|false
        if (self::MINE === $operation?->getName()) {
            $queryBuilder->andWhere(\sprintf('%s.owner = :ootdViewer', $alias))->setParameter('ootdViewer', $viewer);
            $published = $context['filters']['published'] ?? null;
            if (\in_array($published, ['true', '1'], true)) {
                $queryBuilder->andWhere(\sprintf('%s.publishedAt IS NOT NULL', $alias));
            } elseif (\in_array($published, ['false', '0'], true)) {
                $queryBuilder->andWhere(\sprintf('%s.publishedAt IS NULL', $alias));
            }

            return;
        }

        // Fil et looks d'un profil : uniquement des looks publiés, mes brouillons n'y sont jamais
        $queryBuilder->andWhere(\sprintf('%s.publishedAt IS NOT NULL', $alias));
        $this->restrictToVisible($queryBuilder, $queryNameGenerator, $alias, $viewer);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        if (Ootd::class !== $resourceClass) {
            return;
        }
        $viewer = $this->viewer($queryBuilder);
        if (null === $viewer) {
            return;
        }
        $alias = $queryBuilder->getRootAliases()[0];

        // Mes brouillons ne sont visibles que par moi
        $queryBuilder->andWhere(\sprintf('%1$s.publishedAt IS NOT NULL OR %1$s.owner = :ootdViewer', $alias));
        $this->restrictToVisible($queryBuilder, $queryNameGenerator, $alias, $viewer);
    }

    // Personne connectée ; sinon aucun résultat (le pare-feu exige déjà un jeton)
    private function viewer(QueryBuilder $queryBuilder): ?User
    {
        $viewer = $this->security->getUser();
        if (!$viewer instanceof User) {
            $queryBuilder->andWhere('1 = 0');

            return null;
        }

        return $viewer;
    }

    private function restrictToVisible(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $alias, User $viewer): void
    {
        $block = $queryNameGenerator->generateJoinAlias('ootdBlock');
        $relationship = $queryNameGenerator->generateJoinAlias('ootdRelationship');

        $conditions = [
            // Propriétaire
            \sprintf('%s.owner = :ootdViewer', $alias),
            // Abonné ou ami proche accepté (un ami proche a au moins les droits d'un abonné)
            \sprintf(
                'EXISTS (SELECT %2$s FROM %3$s %2$s WHERE %2$s.requester = :ootdViewer AND %2$s.addressee = %1$s.owner AND %2$s.status = :ootdAccepted AND %2$s.type IN (:ootdTypes))',
                $alias,
                $relationship,
                Relationship::class,
            ),
        ];
        // Compte enfant : le propriétaire est mon parent
        if (null !== $viewer->getParent()) {
            $conditions[] = \sprintf('%s.owner = :ootdParent', $alias);
            $queryBuilder->setParameter('ootdParent', $viewer->getParent());
        }

        $queryBuilder
            ->andWhere(implode(' OR ', $conditions))
            // Aucun blocage entre nous, dans un sens ou dans l'autre (sauf sur mes propres looks)
            ->andWhere(\sprintf(
                '%1$s.owner = :ootdViewer OR NOT EXISTS (SELECT %2$s FROM %3$s %2$s WHERE (%2$s.blocker = :ootdViewer AND %2$s.blocked = %1$s.owner) OR (%2$s.blocker = %1$s.owner AND %2$s.blocked = :ootdViewer))',
                $alias,
                $block,
                Block::class,
            ))
            ->setParameter('ootdViewer', $viewer)
            ->setParameter('ootdAccepted', RelationshipStatus::Accepted)
            ->setParameter('ootdTypes', [RelationshipType::Follower, RelationshipType::CloseFriend]);
    }
}
