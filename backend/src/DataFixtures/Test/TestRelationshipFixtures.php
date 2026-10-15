<?php

namespace App\DataFixtures\Test;

use App\Entity\Relationship;
use App\Entity\User;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Relations avec Camille : un cas par statut et par type.
 */
class TestRelationshipFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['test'];
    }

    public function getDependencies(): array
    {
        return [TestUserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $owner = $this->user(TestUserFixtures::OWNER);

        // Ami proche accepté (et abonné, comme sur Instagram on peut être les deux)
        $this->link($manager, $this->user(TestUserFixtures::CLOSE_FRIEND), $owner, RelationshipType::CloseFriend, RelationshipStatus::Accepted);
        $this->link($manager, $this->user(TestUserFixtures::CLOSE_FRIEND), $owner, RelationshipType::Follower, RelationshipStatus::Accepted);
        // Abonné accepté
        $this->link($manager, $this->user(TestUserFixtures::FOLLOWER), $owner, RelationshipType::Follower, RelationshipStatus::Accepted);
        // Demande en attente : aucun droit, traité comme un random
        $this->link($manager, $this->user(TestUserFixtures::PENDING), $owner, RelationshipType::CloseFriend, RelationshipStatus::Pending);
        // Bloqué : aucun droit
        $this->link($manager, $this->user(TestUserFixtures::BLOCKED), $owner, RelationshipType::Follower, RelationshipStatus::Blocked);
        // Relation réciproque : Camille est amie proche de Sophie
        $this->link($manager, $owner, $this->user(TestUserFixtures::CLOSE_FRIEND), RelationshipType::CloseFriend, RelationshipStatus::Accepted);

        $manager->flush();
    }

    private function user(string $reference): User
    {
        return $this->getReference($reference, User::class);
    }

    private function link(ObjectManager $manager, User $requester, User $addressee, RelationshipType $type, RelationshipStatus $status): void
    {
        $relationship = (new Relationship())->setType($type)->setStatus($status);
        $requester->addSentRelationship($relationship);
        $addressee->addReceivedRelationship($relationship);
        $manager->persist($relationship);
    }
}
