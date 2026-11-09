<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Relationship;
use App\Entity\User;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Une relation n'existe que pour son demandeur et son destinataire : pour les autres, 404.
 * Filtres de la liste : ?direction=received|sent, ?status=pending|accepted, ?type=follower|close_friend.
 */
final class RelationshipExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Relationship::class !== $resourceClass) {
            return;
        }

        $alias = $this->restrictToMine($queryBuilder, $queryNameGenerator);
        $filters = $context['filters'] ?? [];
        $viewer = $this->security->getUser();

        $direction = $filters['direction'] ?? null;
        if (null !== $direction) {
            $side = match ($direction) {
                'received' => 'addressee',
                'sent' => 'requester',
                default => throw new BadRequestHttpException('direction : received ou sent.'),
            };
            $param = $queryNameGenerator->generateParameterName('me');
            $queryBuilder->andWhere(\sprintf('%s.%s = :%s', $alias, $side, $param))->setParameter($param, $viewer);
        }

        if (isset($filters['status'])) {
            $status = RelationshipStatus::tryFrom((string) $filters['status']) ?? throw new BadRequestHttpException('status : pending ou accepted.');
            $param = $queryNameGenerator->generateParameterName('status');
            $queryBuilder->andWhere(\sprintf('%s.status = :%s', $alias, $param))->setParameter($param, $status);
        }

        if (isset($filters['type'])) {
            $type = RelationshipType::tryFrom((string) $filters['type']) ?? throw new BadRequestHttpException('type : follower ou close_friend.');
            $param = $queryNameGenerator->generateParameterName('type');
            $queryBuilder->andWhere(\sprintf('%s.type = :%s', $alias, $param))->setParameter($param, $type);
        }
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        if (Relationship::class === $resourceClass) {
            $this->restrictToMine($queryBuilder, $queryNameGenerator);
        }
    }

    private function restrictToMine(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator): string
    {
        $alias = $queryBuilder->getRootAliases()[0];
        $param = $queryNameGenerator->generateParameterName('viewer');
        $viewer = $this->security->getUser();
        // Sans utilisateur connecté : aucun résultat
        $queryBuilder
            ->andWhere(\sprintf('%1$s.requester = :%2$s OR %1$s.addressee = :%2$s', $alias, $param))
            ->setParameter($param, $viewer instanceof User ? $viewer : null);

        return $alias;
    }
}
