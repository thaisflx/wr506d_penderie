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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Comptes qu'un utilisateur peut trouver (recherche) ou consulter (profil public), voir docs/decisions.md :
 * jamais un compte suspendu ni une personne liée à moi par un blocage, dans un sens ou dans l'autre ;
 * jamais un compte -18 dans la recherche, et son profil seulement pour son parent.
 * Un compte introuvable répond 404, sans révéler pourquoi.
 */
final class PublicUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    private const MIN_SEARCH_LENGTH = 2;

    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (User::class !== $resourceClass) {
            return;
        }

        $search = trim((string) ($context['filters']['username'] ?? ''));
        if (mb_strlen($search) < self::MIN_SEARCH_LENGTH) {
            throw new BadRequestHttpException(\sprintf('Indique au moins %d caractères du pseudo.', self::MIN_SEARCH_LENGTH));
        }

        $alias = $this->restrict($queryBuilder, $queryNameGenerator);
        if (null === $alias) {
            return;
        }

        // Pseudo qui contient la recherche, sans tenir compte de la casse ; % et _ sont cherchés tels quels
        $searchParam = $queryNameGenerator->generateParameterName('username');
        $adultParam = $queryNameGenerator->generateParameterName('adultBefore');
        $queryBuilder
            ->andWhere(\sprintf('%s <> :%s', $alias, 'publicViewer'))
            ->andWhere(\sprintf('LOWER(%s.username) LIKE :%s', $alias, $searchParam))
            ->andWhere(\sprintf('%s.birthDate <= :%s', $alias, $adultParam))
            ->setParameter($searchParam, '%'.addcslashes(mb_strtolower($search), '%_\\').'%')
            ->setParameter($adultParam, new \DateTimeImmutable('today -18 years'));
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        if (User::class !== $resourceClass) {
            return;
        }

        $alias = $this->restrict($queryBuilder, $queryNameGenerator);
        if (null === $alias) {
            return;
        }

        // Un compte -18 n'est visible que par lui-même et par son parent
        $adultParam = $queryNameGenerator->generateParameterName('adultBefore');
        $queryBuilder
            ->andWhere(\sprintf('%1$s.birthDate <= :%2$s OR %1$s = :publicViewer OR %1$s.parent = :publicViewer', $alias, $adultParam))
            ->setParameter($adultParam, new \DateTimeImmutable('today -18 years'));
    }

    /**
     * Règles communes ; renvoie l'alias du compte, ou null si personne n'est connecté (aucun résultat).
     */
    private function restrict(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator): ?string
    {
        $viewer = $this->security->getUser();
        if (!$viewer instanceof User) {
            $queryBuilder->andWhere('1 = 0');

            return null;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $block = $queryNameGenerator->generateJoinAlias('block');
        $queryBuilder
            ->andWhere(\sprintf('%s.isSuspended = false', $alias))
            ->andWhere(\sprintf(
                'NOT EXISTS (SELECT %2$s FROM %3$s %2$s WHERE (%2$s.blocker = :publicViewer AND %2$s.blocked = %1$s) OR (%2$s.blocker = %1$s AND %2$s.blocked = :publicViewer))',
                $alias,
                $block,
                Block::class,
            ))
            ->setParameter('publicViewer', $viewer);

        return $alias;
    }
}
