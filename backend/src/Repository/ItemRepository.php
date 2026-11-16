<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\Ootd;
use App\Enum\Visibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /**
     * Objets partageables, en requête : même règle qu'Item::isShareable() (ni l'objet, ni son rangement, sa pièce ou
     * son logement privé ou perso). La règle est donc écrite deux fois : ItemRepositoryTest vérifie qu'elles disent
     * la même chose pour chaque objet.
     */
    public function restrictToShareable(QueryBuilder $queryBuilder, string $alias): QueryBuilder
    {
        return $queryBuilder
            ->join(\sprintf('%s.category', $alias), 'shareCategory')
            ->join('shareCategory.storage', 'shareStorage')
            ->join('shareStorage.room', 'shareRoom')
            ->join('shareRoom.place', 'sharePlace')
            ->andWhere(\sprintf('%s.isPersonal = false', $alias))
            ->andWhere('shareStorage.isPersonal = false AND shareStorage.visibility <> :sharePrivate')
            ->andWhere('shareRoom.isPersonal = false AND shareRoom.visibility <> :sharePrivate')
            ->andWhere('sharePlace.visibility <> :sharePrivate')
            ->setParameter('sharePrivate', Visibility::Private);
    }

    /**
     * Objets montrés à d'autres personnes : partageables et portés dans au moins un look publié
     * (ce que l'admin peut voir, docs/decisions.md, « Back office »).
     */
    public function restrictToShownInPublishedLooks(QueryBuilder $queryBuilder, string $alias): QueryBuilder
    {
        return $this->restrictToShareable($queryBuilder, $alias)
            ->andWhere(\sprintf(
                'EXISTS (SELECT shownOotd.id FROM %s shownOotd JOIN shownOotd.items shownItem WHERE shownItem = %s AND shownOotd.publishedAt IS NOT NULL)',
                Ootd::class,
                $alias,
            ));
    }

    // Nombre de looks publiés où l'objet est porté
    public function countPublishedLooks(Item $item): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(ootd.id)')
            ->from(Ootd::class, 'ootd')
            ->join('ootd.items', 'item')
            ->where('item = :item AND ootd.publishedAt IS NOT NULL')
            ->setParameter('item', $item)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function isShownInPublishedLooks(Item $item): bool
    {
        return $item->isShareable() && $this->countPublishedLooks($item) > 0;
    }
}
