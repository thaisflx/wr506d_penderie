<?php

namespace App\Tests\Integration\Service;

use App\Entity\Block;
use App\Entity\Relationship;
use App\Entity\User;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use App\Repository\BlockRepository;
use App\Repository\RelationshipRepository;
use App\Service\RelationshipChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Matrice des droits sur le contenu de Camille, d'après les fixtures du groupe test
 * (docs/campagne-test.md) et les décisions de docs/decisions.md.
 * Les situations absentes des fixtures (blocage, ami proche sans abonnement) sont créées
 * dans le test ; la transaction est annulée à la fin de chaque test.
 */
final class RelationshipCheckerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RelationshipChecker $checker;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        // Construit avec les vrais repositories : le service n'est pas encore injecté ailleurs, le conteneur l'a retiré
        $this->checker = new RelationshipChecker(
            self::getContainer()->get(RelationshipRepository::class),
            self::getContainer()->get(BlockRepository::class),
        );
    }

    /**
     * @return iterable<string, array{string, ?string, bool, bool, bool, bool, bool}>
     */
    public static function matrixProvider(): iterable
    {
        // [viewer, situation préparée, isFollower, isCloseFriend, isChildOf, canSeeOotdOf, canCommentOotdOf]
        yield 'Sacha, enfant +18' => ['sacha', null, false, false, true, true, true];
        yield 'Lou, enfant -18' => ['lou', null, false, false, true, true, false];
        yield 'Sophie, amie proche et abonnée' => ['sophie', null, true, true, false, true, true];
        yield 'Théo, abonné' => ['theo', null, true, false, false, true, false];
        yield 'Nina, demande en attente' => ['nina', null, false, false, false, false, false];
        yield 'Nina, amie proche acceptée sans abonnement' => ['nina', 'accept-nina', true, true, false, true, true];
        yield 'Marc, abonné' => ['marc', null, true, false, false, true, false];
        yield 'Marc, bloqué par Camille' => ['marc', 'camille-blocks-marc', true, false, false, false, false];
        yield 'Marc, qui a bloqué Camille' => ['marc', 'marc-blocks-camille', true, false, false, false, false];
        yield 'Sacha, enfant bloqué' => ['sacha', 'camille-blocks-sacha', false, false, true, false, false];
        yield 'Zoé, inconnue' => ['zoe', null, false, false, false, false, false];
    }

    #[DataProvider('matrixProvider')]
    public function testRightsOnCamilleContent(
        string $viewer,
        ?string $situation,
        bool $follower,
        bool $closeFriend,
        bool $child,
        bool $canSee,
        bool $canComment,
    ): void {
        $camille = $this->user('camille');
        $viewerUser = $this->user($viewer);
        match ($situation) {
            'accept-nina' => $this->relationship('nina', 'camille', RelationshipType::CloseFriend)->setStatus(RelationshipStatus::Accepted),
            'camille-blocks-marc' => $this->block('camille', 'marc'),
            'marc-blocks-camille' => $this->block('marc', 'camille'),
            // L'API refusera ce blocage (parent et enfant) : le contrôle doit quand même tenir
            'camille-blocks-sacha' => $this->block('camille', 'sacha'),
            null => null,
        };
        $this->em->flush();

        self::assertSame($follower, $this->checker->isFollower($viewerUser, $camille), 'isFollower');
        self::assertSame($closeFriend, $this->checker->isCloseFriend($viewerUser, $camille), 'isCloseFriend');
        self::assertSame($child, $this->checker->isChildOf($viewerUser, $camille), 'isChildOf');
        self::assertSame($canSee, $this->checker->canSeeOotdOf($viewerUser, $camille), 'canSeeOotdOf');
        self::assertSame($canComment, $this->checker->canCommentOotdOf($viewerUser, $camille), 'canCommentOotdOf');
    }

    public function testOwnerHasEveryRightOnOwnContent(): void
    {
        $camille = $this->user('camille');

        self::assertTrue($this->checker->canSeeOotdOf($camille, $camille));
        self::assertTrue($this->checker->canCommentOotdOf($camille, $camille));
        self::assertFalse($this->checker->isBlocked($camille, $camille));
        self::assertFalse($this->checker->isChildOf($camille, $camille));
    }

    public function testBlockWorksInBothDirections(): void
    {
        $this->block('camille', 'marc');
        $this->em->flush();

        self::assertTrue($this->checker->isBlocked($this->user('camille'), $this->user('marc')));
        self::assertTrue($this->checker->isBlocked($this->user('marc'), $this->user('camille')));
        self::assertFalse($this->checker->isBlocked($this->user('camille'), $this->user('theo')));
    }

    public function testRelationshipsAreDirectional(): void
    {
        // Théo suit Camille, mais Camille ne suit pas Théo ; Camille est amie proche de Sophie
        self::assertFalse($this->checker->isFollower($this->user('camille'), $this->user('theo')));
        self::assertFalse($this->checker->canSeeOotdOf($this->user('camille'), $this->user('theo')));
        self::assertTrue($this->checker->isCloseFriend($this->user('camille'), $this->user('sophie')));
        // Le parent n'est pas l'enfant de son enfant
        self::assertFalse($this->checker->isChildOf($this->user('camille'), $this->user('sacha')));
    }

    public function testOnlyDirectChildrenCount(): void
    {
        // Un compte enfant de Sacha est un petit-enfant de Camille : aucun droit chez Camille
        $grandchild = (new User())
            ->setUsername('petit-enfant')
            ->setBirthDate(new \DateTimeImmutable('-5 years'));
        $this->user('sacha')->addChild($grandchild);
        $this->em->persist($grandchild);
        $this->em->flush();

        self::assertTrue($this->checker->isChildOf($grandchild, $this->user('sacha')));
        self::assertFalse($this->checker->isChildOf($grandchild, $this->user('camille')));
        self::assertFalse($this->checker->canSeeOotdOf($grandchild, $this->user('camille')));
    }

    private function relationship(string $requester, string $addressee, RelationshipType $type): Relationship
    {
        $relationship = $this->em->getRepository(Relationship::class)->findOneBy([
            'requester' => $this->user($requester),
            'addressee' => $this->user($addressee),
            'type' => $type,
        ]);
        self::assertInstanceOf(Relationship::class, $relationship);

        return $relationship;
    }

    private function block(string $blocker, string $blocked): void
    {
        $this->em->persist((new Block())->setBlocker($this->user($blocker))->setBlocked($this->user($blocked)));
    }

    private function user(string $username): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user, \sprintf('Compte "%s" absent : lancer make test-db', $username));

        return $user;
    }
}
