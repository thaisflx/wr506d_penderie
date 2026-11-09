<?php

namespace App\Tests\Functional;

use App\Entity\Block;
use App\Entity\Relationship;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Blocage : /api/blocks (docs/decisions.md). Marc est abonné de Camille dans les fixtures.
 */
final class BlockApiTest extends ApiTestCase
{
    private const SENSITIVE_KEYS = ['email', 'password', 'roles', 'birthDate', 'parent', 'places'];

    // --- Bloquer ---

    public function testBlockRemovesTheRelationship(): void
    {
        $marc = $this->user('marc');
        $this->logIn('camille');

        $block = $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$marc->getId()]);

        $this->assertStatus(201);
        self::assertSame('marc', $block['blocked']['username']);
        self::assertArrayNotHasKey('blocker', $block);
        foreach (self::SENSITIVE_KEYS as $key) {
            self::assertArrayNotHasKey($key, $block['blocked']);
        }
        // Marc n'est plus abonné : Sophie et Théo restent
        self::assertSame(2, $this->request('GET', '/api/me')['followerCount']);
        self::assertSame(0, $this->countRelationshipsBetween('camille', 'marc'));
    }

    public function testBlockRemovesRelationshipsInBothDirectionsAndOfEveryType(): void
    {
        // Sophie suit Camille, en est amie proche, et Camille est amie proche de Sophie : 3 relations
        self::assertSame(3, $this->countRelationshipsBetween('camille', 'sophie'));
        $sophie = $this->user('sophie');
        $this->logIn('camille');

        $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$sophie->getId()]);

        $this->assertStatus(201);
        self::assertSame(0, $this->countRelationshipsBetween('camille', 'sophie'));
        // Les autres relations de Camille ne bougent pas
        self::assertSame(1, $this->countRelationshipsBetween('camille', 'theo'));
    }

    public function testBlockerIsAlwaysTheConnectedUser(): void
    {
        $marc = $this->user('marc');
        $this->logIn('zoe');

        // Zoé tente de poser le blocage au nom de Camille
        $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$marc->getId(), 'blocker' => '/api/users/'.$this->user('camille')->getId()]);

        $this->assertStatus(201);
        $block = $this->em()->getRepository(Block::class)->findOneBy(['blocked' => $marc]);
        self::assertSame('zoe', $block?->getBlocker()?->getUsername());
        // La relation entre Camille et Marc n'est pas touchée
        self::assertSame(1, $this->countRelationshipsBetween('camille', 'marc'));
    }

    public function testBlockingYourselfIsRefused(): void
    {
        $camille = $this->logIn('camille');

        $response = $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$camille->getId()]);

        $this->assertStatus(422);
        self::assertSame('Tu ne peux pas te bloquer toi-même.', $response['violations'][0]['message']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function familyProvider(): iterable
    {
        yield 'parent qui bloque son enfant +18' => ['camille', 'sacha'];
        yield 'parent qui bloque son enfant -18' => ['camille', 'lou'];
        yield 'enfant qui bloque son parent' => ['sacha', 'camille'];
    }

    #[DataProvider('familyProvider')]
    public function testParentAndChildCannotBlockEachOther(string $blocker, string $blocked): void
    {
        $id = $this->user($blocked)->getId();
        $this->logIn($blocker);

        $response = $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$id]);

        $this->assertStatus(422);
        self::assertSame('Tu ne peux pas bloquer ton compte parent ou ton compte enfant.', $response['detail']);
        self::assertSame(0, $this->em()->getRepository(Block::class)->count([]));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function notFoundProvider(): iterable
    {
        yield 'compte inexistant' => ['zoe', null];
        yield 'compte suspendu' => ['zoe', 'suspendu'];
        // Lou n'est visible que par son parent
        yield 'compte -18 d\'une autre famille' => ['zoe', 'lou'];
    }

    #[DataProvider('notFoundProvider')]
    public function testBlockingAnUnreachableAccountIsNotFound(string $blocker, ?string $blocked): void
    {
        $id = null === $blocked ? 999999 : $this->user($blocked)->getId();
        $this->logIn($blocker);

        $response = $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$id]);

        $this->assertStatus(404);
        self::assertSame('Ce compte est introuvable.', $response['detail']);
    }

    public function testBlockingTwiceOrBlockingBackIsNotFound(): void
    {
        $this->blockDirectly('camille', 'marc');

        // Déjà bloqué : Marc est devenu introuvable pour Camille
        $this->logIn('camille');
        $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$this->user('marc')->getId()]);
        $this->assertStatus(404);
        // Marc ne peut pas bloquer Camille en retour : elle est introuvable pour lui, rien ne révèle le blocage
        $this->logIn('marc');
        $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$this->user('camille')->getId()]);
        $this->assertStatus(404);
    }

    public function testBlockedIsRequired(): void
    {
        $this->logIn('camille');

        $response = $this->request('POST', '/api/blocks', []);

        $this->assertStatus(400);
        self::assertSame('Indique le compte à bloquer (blocked).', $response['detail']);
    }

    // --- Effets du blocage ---

    public function testBlockHidesBothPeopleEverywhere(): void
    {
        $camille = $this->user('camille');
        $marc = $this->user('marc');
        $this->logIn('camille');
        $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$marc->getId()]);
        $this->assertStatus(201);

        foreach ([['camille', $marc->getId(), 'marc'], ['marc', $camille->getId(), 'cam']] as [$viewer, $otherId, $search]) {
            $this->logIn($viewer);
            // Recherche
            self::assertSame([], $this->request('GET', '/api/users?username='.$search)['member']);
            // Profil
            $this->request('GET', '/api/users/'.$otherId);
            $this->assertStatus(404);
            // Nouvelle demande
            $this->request('POST', '/api/relationships', ['addressee' => '/api/users/'.$otherId, 'type' => 'follower']);
            $this->assertStatus(404);
        }
    }

    // --- Lister et débloquer ---

    public function testListContainsOnlyMyBlocks(): void
    {
        $this->blockDirectly('camille', 'marc');
        $this->blockDirectly('theo', 'zoe');

        $this->logIn('camille');
        self::assertSame(['marc'], array_column(array_column($this->request('GET', '/api/blocks')['member'], 'blocked'), 'username'));
        // La personne bloquée ne voit pas qu'elle l'est
        $this->logIn('marc');
        self::assertSame([], $this->request('GET', '/api/blocks')['member']);
    }

    public function testUnblockDoesNotRestoreRelationships(): void
    {
        $marc = $this->user('marc');
        $this->logIn('camille');
        $id = $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$marc->getId()])['id'];

        $this->request('DELETE', '/api/blocks/'.$id);

        $this->assertStatus(204);
        // Marc est de nouveau visible, mais n'est plus abonné
        self::assertSame(['marc'], array_column($this->request('GET', '/api/users?username=marc')['member'], 'username'));
        self::assertSame(0, $this->countRelationshipsBetween('camille', 'marc'));
        // Et il peut renvoyer une demande
        $this->logIn('marc');
        $this->request('POST', '/api/relationships', ['addressee' => '/api/users/'.$this->user('camille')->getId(), 'type' => 'follower']);
        $this->assertStatus(201);
    }

    public function testBlockedPersonCannotSeeOrRemoveTheBlock(): void
    {
        $block = $this->blockDirectly('camille', 'marc');
        $this->logIn('marc');

        $this->request('GET', '/api/blocks/'.$block->getId());
        $this->assertStatus(404);
        $this->request('DELETE', '/api/blocks/'.$block->getId());
        $this->assertStatus(404);
        self::assertNotNull($this->em()->find(Block::class, $block->getId()));
    }

    public function testBlocksRequireToken(): void
    {
        $this->request('GET', '/api/blocks');

        $this->assertStatus(401);
    }

    private function blockDirectly(string $blocker, string $blocked): Block
    {
        $block = (new Block())->setBlocker($this->user($blocker))->setBlocked($this->user($blocked));
        $this->em()->persist($block);
        $this->em()->flush();

        return $block;
    }

    private function countRelationshipsBetween(string $a, string $b): int
    {
        $userA = $this->user($a);
        $userB = $this->user($b);

        return (int) $this->em()->getRepository(Relationship::class)->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('(r.requester = :a AND r.addressee = :b) OR (r.requester = :b AND r.addressee = :a)')
            ->setParameter('a', $userA)
            ->setParameter('b', $userB)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
