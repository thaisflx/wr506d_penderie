<?php

namespace App\Tests\Functional;

/**
 * Profil de l'utilisateur connecté et compteurs de relations (voir docs/campagne-test.md).
 */
final class MeTest extends ApiTestCase
{
    public function testOwnerProfileWithCounters(): void
    {
        $camille = $this->logIn('camille');

        $me = $this->request('GET', '/api/me');

        $this->assertStatus(200);
        self::assertSame($camille->getId(), $me['id']);
        self::assertSame('camille', $me['username']);
        self::assertSame('camille@test.local', $me['email']);
        self::assertSame('Seconde main, vinyles et outils de bricolage', $me['bio']);
        self::assertSame(['ROLE_USER'], $me['roles']);
        self::assertFalse($me['isMinor']);
        self::assertSame(14, $me['itemCount']);
        // Abonnés acceptés : Sophie et Théo (Marc est bloqué, il ne compte pas)
        self::assertSame(2, $me['followerCount']);
        // Amie proche acceptée : Sophie (la demande de Nina est en attente)
        self::assertSame(1, $me['closeFriendCount']);
        // Demande en attente : Nina
        self::assertSame(1, $me['pendingRequestCount']);
    }

    public function testOwnerProfileListsChildAccounts(): void
    {
        $this->logIn('camille');

        $children = $this->request('GET', '/api/me')['children'];

        self::assertSame(['sacha', 'lou'], array_column($children, 'username'));
        // Sacha a plus de 18 ans, Lou est mineur
        self::assertSame([false, true], array_column($children, 'isMinor'));
        self::assertSame([$this->user('sacha')->getId(), $this->user('lou')->getId()], array_column($children, 'id'));
    }

    public function testFollowerProfileHasEmptyCounters(): void
    {
        $this->logIn('theo');

        $me = $this->request('GET', '/api/me');

        $this->assertStatus(200);
        self::assertSame('theo', $me['username']);
        self::assertSame(0, $me['itemCount']);
        self::assertSame(0, $me['followerCount']);
        self::assertSame(0, $me['closeFriendCount']);
        self::assertSame(0, $me['pendingRequestCount']);
        self::assertSame([], $me['children']);
    }

    public function testCloseFriendCountsReciprocalRelationship(): void
    {
        // Camille a ajouté Sophie en amie proche
        $this->logIn('sophie');

        self::assertSame(1, $this->request('GET', '/api/me')['closeFriendCount']);
    }

    public function testAdminHasAdminRole(): void
    {
        $this->logIn('admin');

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $this->request('GET', '/api/me')['roles']);
    }
}
