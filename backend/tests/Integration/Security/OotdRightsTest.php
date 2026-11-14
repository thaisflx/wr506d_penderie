<?php

namespace App\Tests\Integration\Security;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\OotdVisibilityExtension;
use App\Entity\Block;
use App\Entity\Ootd;
use App\Entity\User;
use App\Repository\BlockRepository;
use App\Repository\RelationshipRepository;
use App\Security\Voter\OotdVoter;
use App\Service\RelationshipChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Droits sur les looks du jour (lot 2), sans passer par HTTP : c'est le seul moyen de tester Lou,
 * qui n'a pas d'email et ne peut donc pas se connecter à l'API.
 *
 * Test croisé : OotdVisibilityExtension (DQL) et RelationshipChecker::canSeeOotdOf (PHP) écrivent
 * la même règle deux fois ; pour chaque personne de la matrice, elles doivent donner le même résultat.
 */
final class OotdRightsTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RelationshipChecker $checker;
    private OotdVisibilityExtension $extension;
    private OotdVoter $voter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->checker = new RelationshipChecker(
            self::getContainer()->get(RelationshipRepository::class),
            self::getContainer()->get(BlockRepository::class),
        );
        $this->extension = self::getContainer()->get(OotdVisibilityExtension::class);
        $this->voter = new OotdVoter($this->checker);
    }

    /**
     * @return iterable<string, array{string, bool, list<string>}>
     */
    public static function matrixProvider(): iterable
    {
        $camille = ['Tenue de bricolage du samedi', 'Jean brut et rien d\'autre'];
        // [personne, Marc bloqué par Camille, looks publiés visibles dans le fil]
        yield 'Camille, propriétaire' => ['camille', false, [...$camille, 'Prête pour le marché']];
        yield 'Sacha, enfant +18' => ['sacha', false, $camille];
        yield 'Lou, enfant -18' => ['lou', false, $camille];
        yield 'Sophie, amie proche (voit aussi le sien)' => ['sophie', false, [...$camille, 'Prête pour le marché']];
        yield 'Théo, abonné' => ['theo', false, $camille];
        yield 'Nina, demande en attente' => ['nina', false, []];
        yield 'Marc, abonné' => ['marc', false, $camille];
        yield 'Marc, bloqué par Camille' => ['marc', true, []];
        yield 'Zoé, inconnue' => ['zoe', false, []];
    }

    /**
     * Les mêmes personnes, sans le résultat attendu (pour les tests qui le calculent eux-mêmes).
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function viewerProvider(): iterable
    {
        foreach (self::matrixProvider() as $name => [$viewer, $marcBlocked]) {
            yield $name => [$viewer, $marcBlocked];
        }
    }

    /**
     * @param list<string> $expectedCaptions
     */
    #[DataProvider('matrixProvider')]
    public function testFeedMatchesTheMatrix(string $viewer, bool $marcBlocked, array $expectedCaptions): void
    {
        if ($marcBlocked) {
            $this->blockMarc();
        }

        $captions = array_map(fn (Ootd $ootd) => $ootd->getCaption(), $this->feed($this->user($viewer)));

        self::assertEqualsCanonicalizing($expectedCaptions, $captions);
    }

    #[DataProvider('viewerProvider')]
    public function testExtensionAndCheckerAgreeForEveryPublishedOotd(string $viewer, bool $marcBlocked): void
    {
        if ($marcBlocked) {
            $this->blockMarc();
        }
        $user = $this->user($viewer);
        $inFeed = array_map(fn (Ootd $ootd) => $ootd->getId(), $this->feed($user));

        foreach ($this->em->getRepository(Ootd::class)->findAll() as $ootd) {
            if (!$ootd->isPublished()) {
                continue;
            }
            $owner = $ootd->getOwner();
            self::assertNotNull($owner);
            $expected = $this->checker->canSeeOotdOf($user, $owner);
            self::assertSame($expected, \in_array($ootd->getId(), $inFeed, true), \sprintf('Extension et canSeeOotdOf divergent pour %s sur « %s »', $viewer, $ootd->getCaption()));
            self::assertSame($expected ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $this->vote($user, $ootd, OotdVoter::VIEW));
            self::assertSame($expected, null !== $this->findOne($user, $ootd), 'Lecture du détail');
        }
    }

    #[DataProvider('viewerProvider')]
    public function testOnlyTheOwnerSeesAndEditsTheDraft(string $viewer, bool $marcBlocked): void
    {
        if ($marcBlocked) {
            $this->blockMarc();
        }
        $user = $this->user($viewer);
        $draft = $this->ootd('Idée pour la soirée');
        $published = $this->ootd('Tenue de bricolage du samedi');
        $isOwner = 'camille' === $viewer;

        self::assertSame($isOwner, null !== $this->findOne($user, $draft));
        self::assertSame($isOwner ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $this->vote($user, $draft, OotdVoter::VIEW));
        foreach ([$draft, $published] as $ootd) {
            self::assertSame($isOwner ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $this->vote($user, $ootd, OotdVoter::EDIT));
        }
    }

    public function testMyOotdsContainDraftsAndCanBeFiltered(): void
    {
        $camille = $this->user('camille');

        self::assertCount(3, $this->collection($camille, OotdVisibilityExtension::MINE));
        self::assertSame(['Idée pour la soirée'], array_map(fn (Ootd $o) => $o->getCaption(), $this->collection($camille, OotdVisibilityExtension::MINE, ['published' => 'false'])));
        self::assertCount(2, $this->collection($camille, OotdVisibilityExtension::MINE, ['published' => 'true']));
        // Les looks des autres n'y sont jamais
        self::assertCount(1, $this->collection($this->user('sophie'), OotdVisibilityExtension::MINE));
    }

    public function testAnonymousSeesNothing(): void
    {
        self::getContainer()->get(TokenStorageInterface::class)->setToken(null);
        $collection = $this->em->getRepository(Ootd::class)->createQueryBuilder('o');
        $this->extension->applyToCollection($collection, new QueryNameGenerator(), Ootd::class, new GetCollection(name: OotdVisibilityExtension::FEED));
        $item = $this->em->getRepository(Ootd::class)->createQueryBuilder('o');
        $this->extension->applyToItem($item, new QueryNameGenerator(), Ootd::class, []);

        self::assertSame([], $collection->getQuery()->getResult());
        self::assertSame([], $item->getQuery()->getResult());
        $token = new UsernamePasswordToken($this->user('camille'), 'main');
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, new \stdClass(), [OotdVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, $this->ootd('Idée pour la soirée'), ['ITEM_EDIT']));
        // Look sans propriétaire (jamais enregistré) : refusé à tout le monde
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, (new Ootd())->setPublishedAt(new \DateTimeImmutable()), [OotdVoter::VIEW]));
    }

    public function testOtherResourcesAreNotFiltered(): void
    {
        $this->logIn($this->user('zoe'));
        $queryBuilder = $this->em->getRepository(User::class)->createQueryBuilder('u');
        $before = (string) $queryBuilder->getDQL();

        $this->extension->applyToCollection($queryBuilder, new QueryNameGenerator(), User::class);
        $this->extension->applyToItem($queryBuilder, new QueryNameGenerator(), User::class, []);

        self::assertSame($before, $queryBuilder->getDQL());
    }

    /**
     * @return list<Ootd>
     */
    private function feed(User $viewer): array
    {
        return $this->collection($viewer, OotdVisibilityExtension::FEED);
    }

    /**
     * @param array<string, string> $filters
     *
     * @return list<Ootd>
     */
    private function collection(User $viewer, string $operation, array $filters = []): array
    {
        $this->logIn($viewer);
        $queryBuilder = $this->em->getRepository(Ootd::class)->createQueryBuilder('o');
        $this->extension->applyToCollection($queryBuilder, new QueryNameGenerator(), Ootd::class, new GetCollection(name: $operation), ['filters' => $filters]);

        return $queryBuilder->getQuery()->getResult();
    }

    private function findOne(User $viewer, Ootd $ootd): ?Ootd
    {
        $this->logIn($viewer);
        $queryBuilder = $this->em->getRepository(Ootd::class)->createQueryBuilder('o')->andWhere('o.id = :id')->setParameter('id', $ootd->getId());
        $this->extension->applyToItem($queryBuilder, new QueryNameGenerator(), Ootd::class, ['id' => $ootd->getId()]);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    private function vote(User $viewer, Ootd $ootd, string $attribute): int
    {
        return $this->voter->vote(new UsernamePasswordToken($viewer, 'main', $viewer->getRoles()), $ootd, [$attribute]);
    }

    private function logIn(User $user): void
    {
        self::getContainer()->get(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function blockMarc(): void
    {
        $this->em->persist((new Block())->setBlocker($this->user('camille'))->setBlocked($this->user('marc')));
        $this->em->flush();
    }

    private function ootd(string $caption): Ootd
    {
        $ootd = $this->em->getRepository(Ootd::class)->findOneBy(['caption' => $caption]);
        self::assertInstanceOf(Ootd::class, $ootd, \sprintf('OOTD « %s » absent : lancer make test-db', $caption));

        return $ootd;
    }

    private function user(string $username): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user, \sprintf('Compte "%s" absent : lancer make test-db', $username));

        return $user;
    }
}
