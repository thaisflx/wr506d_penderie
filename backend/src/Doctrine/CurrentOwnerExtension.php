<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Place;
use App\Entity\Room;
use App\Entity\Storage;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Limite la hiérarchie (place > room > storage > category > item) au contenu de l'utilisateur connecté.
 * Solution provisoire : les Voters (amis proches, abonnés, comptes enfants) ouvriront l'accès ensuite.
 */
final class CurrentOwnerExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    // Chemin de jointures depuis chaque entité jusqu'à celle qui porte le propriétaire
    private const OWNER_PATHS = [
        Place::class => [],
        Room::class => ['place'],
        Storage::class => ['room', 'place'],
        Category::class => ['storage', 'room', 'place'],
        Item::class => [],
    ];

    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->restrictToOwner($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->restrictToOwner($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function restrictToOwner(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        if (!\array_key_exists($resourceClass, self::OWNER_PATHS)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        foreach (self::OWNER_PATHS[$resourceClass] as $association) {
            $joinAlias = $queryNameGenerator->generateJoinAlias($association);
            $queryBuilder->innerJoin(\sprintf('%s.%s', $alias, $association), $joinAlias);
            $alias = $joinAlias;
        }

        // Sans utilisateur connecté, aucun résultat
        $parameter = $queryNameGenerator->generateParameterName('currentOwner');
        $queryBuilder
            ->andWhere(\sprintf('%s.owner = :%s', $alias, $parameter))
            ->setParameter($parameter, $this->security->getUser());
    }
}
