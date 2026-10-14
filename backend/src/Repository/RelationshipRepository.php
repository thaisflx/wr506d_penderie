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
}
