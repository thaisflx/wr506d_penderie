<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Block;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Je ne vois que les blocages que j'ai posés : la personne bloquée ne voit jamais qu'elle l'est (404).
 */
final class BlockExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->restrictToMine($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->restrictToMine($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function restrictToMine(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        if (Block::class !== $resourceClass) {
            return;
        }

        $viewer = $this->security->getUser();
        $param = $queryNameGenerator->generateParameterName('blocker');
        // Sans utilisateur connecté : aucun résultat
        $queryBuilder
            ->andWhere(\sprintf('%s.blocker = :%s', $queryBuilder->getRootAliases()[0], $param))
            ->setParameter($param, $viewer instanceof User ? $viewer : null);
    }
}
