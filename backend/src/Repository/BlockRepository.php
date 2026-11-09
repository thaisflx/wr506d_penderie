<?php

namespace App\Repository;

use App\Entity\Block;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Block>
 */
class BlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Block::class);
    }

    // Un blocage existe-t-il entre les deux personnes, dans un sens ou dans l'autre ?
    public function existsBetween(User $a, User $b): bool
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('(b.blocker = :a AND b.blocked = :b) OR (b.blocker = :b AND b.blocked = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
