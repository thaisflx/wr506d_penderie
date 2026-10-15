<?php

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use App\Repository\RelationshipRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Compteurs calculés sur la base de test (fixtures du groupe test, voir docs/campagne-test.md).
 * Demandes reçues par Camille : Sophie (amie proche + abonnée, acceptées), Théo (abonné, accepté),
 * Nina (amie proche, en attente), Marc (abonné, bloqué).
 */
final class RelationshipRepositoryTest extends KernelTestCase
{
    private RelationshipRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(RelationshipRepository::class);
    }

    /**
     * @return iterable<string, array{string, RelationshipStatus, ?RelationshipType, int}>
     */
    public static function countProvider(): iterable
    {
        yield 'Camille : acceptées, tous types' => ['camille', RelationshipStatus::Accepted, null, 3];
        yield 'Camille : abonnés acceptés' => ['camille', RelationshipStatus::Accepted, RelationshipType::Follower, 2];
        yield 'Camille : amis proches acceptés' => ['camille', RelationshipStatus::Accepted, RelationshipType::CloseFriend, 1];
        yield 'Camille : demandes en attente' => ['camille', RelationshipStatus::Pending, null, 1];
        yield 'Camille : demande d\'ami proche en attente' => ['camille', RelationshipStatus::Pending, RelationshipType::CloseFriend, 1];
        yield 'Camille : aucun abonnement en attente' => ['camille', RelationshipStatus::Pending, RelationshipType::Follower, 0];
        yield 'Camille : bloqués' => ['camille', RelationshipStatus::Blocked, null, 1];
        // Relation réciproque : Camille est amie proche de Sophie
        yield 'Sophie : amie proche de Camille' => ['sophie', RelationshipStatus::Accepted, RelationshipType::CloseFriend, 1];
        // Les demandes envoyées ne comptent pas : Théo a demandé, il n'a rien reçu
        yield 'Théo : rien reçu' => ['theo', RelationshipStatus::Accepted, null, 0];
        yield 'Zoé : aucune relation' => ['zoe', RelationshipStatus::Pending, null, 0];
    }

    #[DataProvider('countProvider')]
    public function testCountReceived(string $username, RelationshipStatus $status, ?RelationshipType $type, int $expected): void
    {
        self::assertSame($expected, $this->repository->countReceived($this->user($username), $status, $type));
    }

    private function user(string $username): User
    {
        $user = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user, \sprintf('Compte "%s" absent : lancer make test-db', $username));

        return $user;
    }
}
