<?php

namespace App\Repository;

use App\Entity\Relationship;
use App\Entity\User;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Relationship>
 */
class RelationshipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Relationship::class);
    }

    // Nombre de demandes reçues par un utilisateur, par type et par statut
    public function countReceived(User $addressee, RelationshipStatus $status, ?RelationshipType $type = null): int
    {
        $criteria = ['addressee' => $addressee, 'status' => $status];
        if (null !== $type) {
            $criteria['type'] = $type;
        }

        return $this->count($criteria);
    }

    /**
     * Une demande acceptée de requester vers addressee existe-t-elle, pour l'un des types donnés ?
     *
     * @param list<RelationshipType> $types
     */
    public function hasAccepted(User $requester, User $addressee, array $types): bool
    {
        return $this->count([
            'requester' => $requester,
            'addressee' => $addressee,
            'status' => RelationshipStatus::Accepted,
            'type' => $types,
        ]) > 0;
    }

    // Supprime toutes les relations entre deux personnes, dans les deux sens et de tous types (blocage)
    public function deleteBetween(User $a, User $b): int
    {
        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->where('(r.requester = :a AND r.addressee = :b) OR (r.requester = :b AND r.addressee = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->getQuery()
            ->execute();
    }
}
