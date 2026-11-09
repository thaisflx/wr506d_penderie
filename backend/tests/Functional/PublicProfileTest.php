<?php

namespace App\Tests\Functional;

use App\Entity\Block;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * GET /api/users/{id} : profil public et lien de l'utilisateur connecté avec ce compte.
 */
final class PublicProfileTest extends ApiTestCase
{
    private const PUBLIC_KEYS = ['@context', '@id', '@type', 'id', 'username', 'avatar', 'bio', 'viewerRelationship'];
    private const SENSITIVE_KEYS = ['email', 'password', 'roles', 'birthDate', 'parent', 'places', 'isSuspended', 'children', 'items', 'isMinor'];

    public function testProfileExposesOnlyPublicFields(): void
    {
        $camille = $this->user('camille');
        $this->logIn('theo');

        $profile = $this->request('GET', '/api/users/'.$camille->getId());

        $this->assertStatus(200);
        self::assertSame($camille->getId(), $profile['id']);
        self::assertSame('camille', $profile['username']);
        self::assertSame('Seconde main, vinyles et outils de bricolage', $profile['bio']);
        self::assertSame([], array_diff(array_keys($profile), self::PUBLIC_KEYS));
        foreach (self::SENSITIVE_KEYS as $key) {
            self::assertArrayNotHasKey($key, $profile);
        }
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('camille@test.local', $content);
        self::assertStringNotContainsString('1992-03-14', $content);
        self::assertStringNotContainsString('Lilas', $content); // adresse de la Maison
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function relationshipProvider(): iterable
    {
        // [qui regarde le profil de Camille, abonnement, ami proche]
        yield 'Théo, abonné' => ['theo', 'accepted', 'none'];
        yield 'Sophie, amie proche et abonnée' => ['sophie', 'accepted', 'accepted'];
        yield 'Nina, demande d\'ami proche en attente' => ['nina', 'none', 'pending'];
        yield 'Zoé, inconnue' => ['zoe', 'none', 'none'];
    }

    #[DataProvider('relationshipProvider')]
    public function testShowsMyRelationshipWithTheProfile(string $viewer, string $follower, string $closeFriend): void
    {
        $camille = $this->user('camille');
        $this->logIn($viewer);

        $link = $this->request('GET', '/api/users/'.$camille->getId())['viewerRelationship'];

        self::assertFalse($link['isMe']);
        self::assertSame($follower, $link['follower']['status']);
        self::assertSame($closeFriend, $link['closeFriend']['status']);
        // L'identifiant de la demande sert à l'annuler ; aucun quand il n'y a pas de demande
        self::assertSame('none' === $follower, null === $link['follower']['id']);
        self::assertSame('none' === $closeFriend, null === $link['closeFriend']['id']);
    }

    public function testOwnProfile(): void
    {
        $camille = $this->logIn('camille');

        $link = $this->request('GET', '/api/users/'.$camille->getId())['viewerRelationship'];

        $this->assertStatus(200);
        self::assertTrue($link['isMe']);
        self::assertSame('none', $link['follower']['status']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notFoundProvider(): iterable
    {
        yield 'compte suspendu' => ['theo', 'suspendu'];
        yield 'compte -18 vu par un autre' => ['theo', 'lou'];
    }

    #[DataProvider('notFoundProvider')]
    public function testHiddenProfilesAreNotFound(string $viewer, string $profile): void
    {
        $id = $this->user($profile)->getId();
        $this->logIn($viewer);

        $this->request('GET', '/api/users/'.$id);

        $this->assertStatus(404);
    }

    public function testUnknownProfileIsNotFound(): void
    {
        $this->logIn('theo');

        $this->request('GET', '/api/users/999999');

        $this->assertStatus(404);
    }

    public function testMinorProfileIsVisibleToItsParentOnly(): void
    {
        $lou = $this->user('lou');

        $this->logIn('camille');
        $this->request('GET', '/api/users/'.$lou->getId());
        $this->assertStatus(200);

        $this->logIn('sacha');
        $this->request('GET', '/api/users/'.$lou->getId());
        $this->assertStatus(404);
    }

    public function testAdultChildProfileIsPublic(): void
    {
        $sacha = $this->user('sacha');
        $this->logIn('theo');

        $this->request('GET', '/api/users/'.$sacha->getId());

        $this->assertStatus(200);
    }

    public function testBlockReturnsNotFoundBothWays(): void
    {
        $camille = $this->user('camille');
        $marc = $this->user('marc');
        $this->em()->persist((new Block())->setBlocker($camille)->setBlocked($marc));
        $this->em()->flush();

        // Ni Marc ni Camille ne voient l'autre, et rien n'indique un blocage
        $this->logIn('marc');
        $this->request('GET', '/api/users/'.$camille->getId());
        $this->assertStatus(404);
        $this->logIn('camille');
        $this->request('GET', '/api/users/'.$marc->getId());
        $this->assertStatus(404);
        // Un tiers voit toujours Marc
        $this->logIn('zoe');
        $this->request('GET', '/api/users/'.$marc->getId());
        $this->assertStatus(200);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function writeMethodProvider(): iterable
    {
        // [méthode, sur un profil (sinon sur la collection)]
        yield 'POST sur la collection' => ['POST', false];
        yield 'PUT sur un profil' => ['PUT', true];
        yield 'PATCH sur un profil' => ['PATCH', true];
        yield 'DELETE sur un profil' => ['DELETE', true];
        yield 'DELETE sur la collection' => ['DELETE', false];
    }

    /**
     * La ressource User est en lecture seule : aucune écriture possible, même sur son propre compte.
     */
    #[DataProvider('writeMethodProvider')]
    public function testUserResourceIsReadOnly(string $method, bool $onItem): void
    {
        $camille = $this->logIn('camille');

        $this->request($method, $onItem ? '/api/users/'.$camille->getId() : '/api/users', ['username' => 'pirate', 'email' => 'pirate@test.local', 'roles' => ['ROLE_ADMIN']]);

        $this->assertStatus(405);
        $this->em()->clear();
        $reloaded = $this->user('camille');
        self::assertSame('camille@test.local', $reloaded->getEmail());
        self::assertSame(['ROLE_USER'], $reloaded->getRoles());
        self::assertNull($this->em()->getRepository(User::class)->findOneBy(['username' => 'pirate']));
    }

    public function testProfileRequiresToken(): void
    {
        $this->request('GET', '/api/users/'.$this->user('camille')->getId());

        $this->assertStatus(401);
    }
}
