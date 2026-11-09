<?php

namespace App\Tests\Functional;

use App\Entity\Block;
use App\Entity\Relationship;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Demandes d'abonnement et d'ami proche : /api/relationships (docs/decisions.md).
 * Fixtures : Sophie (amie proche + abonnée), Théo (abonné), Marc (abonné) et Nina (ami proche en attente)
 * ont envoyé une demande à Camille ; Camille est amie proche de Sophie.
 */
final class RelationshipApiTest extends ApiTestCase
{
    private const SENSITIVE_KEYS = ['email', 'password', 'roles', 'birthDate', 'parent', 'places'];

    // --- Envoyer une demande ---

    public function testSendFollowRequest(): void
    {
        $camille = $this->user('camille');
        $zoe = $this->logIn('zoe');

        $created = $this->request('POST', '/api/relationships', ['addressee' => $this->iri($camille->getId()), 'type' => 'follower']);

        $this->assertStatus(201);
        self::assertSame('pending', $created['status']);
        self::assertSame('follower', $created['type']);
        self::assertSame('zoe', $created['requester']['username']);
        self::assertSame('camille', $created['addressee']['username']);
        foreach (self::SENSITIVE_KEYS as $key) {
            self::assertArrayNotHasKey($key, $created['requester']);
            self::assertArrayNotHasKey($key, $created['addressee']);
        }

        // Camille la voit dans ses demandes reçues
        $this->logIn('camille');
        $received = $this->request('GET', '/api/relationships?direction=received&status=pending');
        self::assertContains('zoe', array_column(array_column($received['member'], 'requester'), 'username'));
        self::assertSame(2, $this->request('GET', '/api/me')['pendingRequestCount']);
        self::assertSame($zoe->getId(), $this->relationship('zoe', 'camille', RelationshipType::Follower)->getRequester()?->getId());
    }

    public function testRequesterAndStatusAreAlwaysSetByTheServer(): void
    {
        $camille = $this->user('camille');
        $this->logIn('zoe');

        // Zoé tente d'envoyer la demande au nom de Théo, déjà acceptée
        $created = $this->request('POST', '/api/relationships', [
            'addressee' => $this->iri($camille->getId()),
            'type' => 'close_friend',
            'requester' => $this->iri($this->user('theo')->getId()),
            'status' => 'accepted',
        ]);

        $this->assertStatus(201);
        self::assertSame('zoe', $created['requester']['username']);
        self::assertSame('pending', $created['status']);
    }

    public function testRequestToYourselfIsRefused(): void
    {
        $camille = $this->logIn('camille');

        $response = $this->request('POST', '/api/relationships', ['addressee' => $this->iri($camille->getId()), 'type' => 'follower']);

        $this->assertStatus(422);
        self::assertSame('addressee', $response['violations'][0]['propertyPath']);
        self::assertSame('Tu ne peux pas t\'envoyer une demande à toi-même.', $response['violations'][0]['message']);
    }

    public function testDuplicateRequestIsRefused(): void
    {
        // Théo suit déjà Camille
        $camille = $this->user('camille');
        $this->logIn('theo');

        $response = $this->request('POST', '/api/relationships', ['addressee' => $this->iri($camille->getId()), 'type' => 'follower']);

        $this->assertStatus(422);
        self::assertSame('Tu as déjà envoyé cette demande.', $response['violations'][0]['message']);
    }

    public function testCloseFriendRequestIsSeparateFromFollow(): void
    {
        // Théo suit Camille : il peut aussi demander à devenir ami proche
        $camille = $this->user('camille');
        $this->logIn('theo');

        $this->request('POST', '/api/relationships', ['addressee' => $this->iri($camille->getId()), 'type' => 'close_friend']);

        $this->assertStatus(201);
    }

    public function testTypeIsRequired(): void
    {
        $camille = $this->user('camille');
        $this->logIn('zoe');

        $response = $this->request('POST', '/api/relationships', ['addressee' => $this->iri($camille->getId())]);

        $this->assertStatus(422);
        self::assertSame('type', $response['violations'][0]['propertyPath']);
    }

    public function testAddresseeIsRequired(): void
    {
        $this->logIn('zoe');

        $response = $this->request('POST', '/api/relationships', ['type' => 'follower']);

        $this->assertStatus(400);
        self::assertSame('Indique le compte à qui envoyer la demande (addressee).', $response['detail']);
    }

    /**
     * Comptes à qui l'on ne peut pas envoyer de demande : tous répondent 404, comme un compte inexistant.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function unreachableProvider(): iterable
    {
        yield 'compte inexistant' => ['zoe', 'inconnu'];
        yield 'compte suspendu' => ['zoe', 'suspendu'];
        yield 'compte -18' => ['zoe', 'lou'];
        // Même son parent ne peut pas envoyer de demande à un compte -18
        yield 'compte -18, par son parent' => ['camille', 'lou'];
    }

    #[DataProvider('unreachableProvider')]
    public function testRequestToUnreachableAccountIsNotFound(string $requester, string $addressee): void
    {
        $id = 'inconnu' === $addressee ? 999999 : $this->user($addressee)->getId();
        $this->logIn($requester);

        $response = $this->request('POST', '/api/relationships', ['addressee' => $this->iri($id), 'type' => 'follower']);

        $this->assertStatus(404);
        self::assertSame('Ce compte est introuvable.', $response['detail']);
    }

    public function testRequestIsNotFoundWhenABlockExistsEitherWay(): void
    {
        $camille = $this->user('camille');
        $zoe = $this->user('zoe');
        $this->em()->persist((new Block())->setBlocker($camille)->setBlocked($zoe));
        $this->em()->flush();

        $this->logIn('zoe');
        $this->request('POST', '/api/relationships', ['addressee' => $this->iri($camille->getId()), 'type' => 'follower']);
        $this->assertStatus(404);

        $this->logIn('camille');
        $this->request('POST', '/api/relationships', ['addressee' => $this->iri($zoe->getId()), 'type' => 'follower']);
        $this->assertStatus(404);
    }

    // --- Accepter ---

    public function testAddresseeAcceptsRequest(): void
    {
        $nina = $this->relationship('nina', 'camille', RelationshipType::CloseFriend);
        $this->logIn('camille');

        $accepted = $this->request('PATCH', '/api/relationships/'.$nina->getId(), ['status' => 'accepted']);

        $this->assertStatus(200);
        self::assertSame('accepted', $accepted['status']);
        $me = $this->request('GET', '/api/me');
        self::assertSame(2, $me['closeFriendCount']);
        self::assertSame(0, $me['pendingRequestCount']);
    }

    public function testRequesterCannotAcceptOwnRequest(): void
    {
        $nina = $this->relationship('nina', 'camille', RelationshipType::CloseFriend);
        $this->logIn('nina');

        $this->request('PATCH', '/api/relationships/'.$nina->getId(), ['status' => 'accepted']);

        $this->assertStatus(403);
    }

    public function testOthersCannotSeeOrAcceptARequest(): void
    {
        $nina = $this->relationship('nina', 'camille', RelationshipType::CloseFriend);
        $this->logIn('theo');

        $this->request('PATCH', '/api/relationships/'.$nina->getId(), ['status' => 'accepted']);
        $this->assertStatus(404);
        $this->request('GET', '/api/relationships/'.$nina->getId());
        $this->assertStatus(404);
    }

    public function testOnlyPendingToAcceptedIsAllowed(): void
    {
        $nina = $this->relationship('nina', 'camille', RelationshipType::CloseFriend);
        $theo = $this->relationship('theo', 'camille', RelationshipType::Follower);
        $this->logIn('camille');

        // Revenir en attente
        $response = $this->request('PATCH', '/api/relationships/'.$nina->getId(), ['status' => 'pending']);
        $this->assertStatus(422);
        self::assertSame('Seule une demande en attente peut être acceptée.', $response['detail']);
        // Accepter une demande déjà acceptée
        $this->request('PATCH', '/api/relationships/'.$theo->getId(), ['status' => 'accepted']);
        $this->assertStatus(422);
        // Statut inconnu, dont l'ancien statut "blocked"
        $this->request('PATCH', '/api/relationships/'.$nina->getId(), ['status' => 'blocked']);
        $this->assertStatus(400);
    }

    public function testOnlyTheStatusCanBeChanged(): void
    {
        $nina = $this->relationship('nina', 'camille', RelationshipType::CloseFriend);
        $this->logIn('camille');

        $this->request('PATCH', '/api/relationships/'.$nina->getId(), [
            'status' => 'accepted',
            'type' => 'follower',
            'addressee' => $this->iri($this->user('zoe')->getId()),
        ]);

        $this->assertStatus(200);
        $this->em()->clear();
        $reloaded = $this->em()->find(Relationship::class, $nina->getId());
        self::assertSame(RelationshipType::CloseFriend, $reloaded?->getType());
        self::assertSame('camille', $reloaded?->getAddressee()?->getUsername());
    }

    // --- Refuser, annuler, se désabonner ---

    /**
     * @return iterable<string, array{string, string, RelationshipType}>
     */
    public static function deleteProvider(): iterable
    {
        // [qui supprime, demandeur de la relation, type]
        yield 'Camille refuse la demande de Nina' => ['camille', 'nina', RelationshipType::CloseFriend];
        yield 'Nina annule sa demande' => ['nina', 'nina', RelationshipType::CloseFriend];
        yield 'Théo se désabonne' => ['theo', 'theo', RelationshipType::Follower];
        yield 'Camille retire Théo de ses abonnés' => ['camille', 'theo', RelationshipType::Follower];
    }

    #[DataProvider('deleteProvider')]
    public function testRequesterOrAddresseeCanDelete(string $actor, string $requester, RelationshipType $type): void
    {
        $relationship = $this->relationship($requester, 'camille', $type);
        $id = $relationship->getId();
        $this->logIn($actor);

        $this->request('DELETE', '/api/relationships/'.$id);

        $this->assertStatus(204);
        $this->em()->clear();
        self::assertNull($this->em()->find(Relationship::class, $id));
    }

    public function testOthersCannotDeleteARelationship(): void
    {
        $theo = $this->relationship('theo', 'camille', RelationshipType::Follower);
        $this->logIn('sophie');

        $this->request('DELETE', '/api/relationships/'.$theo->getId());

        $this->assertStatus(404);
    }

    // --- Lister ---

    public function testListContainsOnlyMyRelationships(): void
    {
        $this->logIn('zoe');

        self::assertSame([], $this->request('GET', '/api/relationships')['member']);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function filterProvider(): iterable
    {
        // [filtres, paires demandeur>destinataire attendues, triées]
        yield 'toutes' => ['', ['camille>sophie', 'marc>camille', 'nina>camille', 'sophie>camille', 'sophie>camille', 'theo>camille']];
        yield 'reçues' => ['?direction=received', ['marc>camille', 'nina>camille', 'sophie>camille', 'sophie>camille', 'theo>camille']];
        yield 'envoyées' => ['?direction=sent', ['camille>sophie']];
        yield 'reçues en attente' => ['?direction=received&status=pending', ['nina>camille']];
        yield 'abonnés acceptés' => ['?direction=received&status=accepted&type=follower', ['marc>camille', 'sophie>camille', 'theo>camille']];
    }

    #[DataProvider('filterProvider')]
    public function testFilters(string $query, array $expected): void
    {
        $this->logIn('camille');

        $list = $this->request('GET', '/api/relationships'.$query);

        $this->assertStatus(200);
        $pairs = array_map(fn (array $r) => $r['requester']['username'].'>'.$r['addressee']['username'], $list['member']);
        sort($pairs);
        self::assertSame($expected, $pairs);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badFilterProvider(): iterable
    {
        yield 'direction' => ['?direction=both'];
        yield 'statut' => ['?status=blocked'];
        yield 'type' => ['?type=friend'];
    }

    #[DataProvider('badFilterProvider')]
    public function testUnknownFilterValuesAreRejected(string $query): void
    {
        $this->logIn('camille');

        $this->request('GET', '/api/relationships'.$query);

        $this->assertStatus(400);
    }

    public function testRelationshipsRequireToken(): void
    {
        $this->request('GET', '/api/relationships');

        $this->assertStatus(401);
    }

    private function iri(?int $userId): string
    {
        return '/api/users/'.$userId;
    }

    private function relationship(string $requester, string $addressee, RelationshipType $type): Relationship
    {
        $relationship = $this->em()->getRepository(Relationship::class)->findOneBy([
            'requester' => $this->user($requester),
            'addressee' => $this->user($addressee),
            'type' => $type,
        ]);
        self::assertInstanceOf(Relationship::class, $relationship);

        return $relationship;
    }
}
