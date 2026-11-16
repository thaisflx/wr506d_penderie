<?php

namespace App\Tests\Functional;

/**
 * User.lastLoginAt de bout en bout : la connexion par /api/login_check l'enregistre, les requêtes suivantes avec le
 * jeton ne le changent pas, une connexion refusée non plus (docs/decisions.md, « Back office »).
 */
final class LastLoginTest extends ApiTestCase
{
    public function testLoginRecordsTheDateOfTheConnection(): void
    {
        $before = new \DateTimeImmutable('-1 second');

        $this->request('POST', '/api/login_check', ['email' => 'camille@test.local', 'password' => 'password']);

        $this->assertStatus(200);
        $this->em()->clear();
        $lastLogin = $this->user('camille')->getLastLoginAt();
        self::assertNotNull($lastLogin);
        self::assertGreaterThanOrEqual($before, $lastLogin);
    }

    public function testRequestsWithTheTokenDoNotChangeIt(): void
    {
        $camille = $this->logIn('camille');
        $camille->setLastLoginAt(new \DateTimeImmutable('2026-11-01 10:00:00'));
        $this->em()->flush();

        $this->request('GET', '/api/me');
        $this->request('GET', '/api/ootds');

        $this->assertStatus(200);
        $this->em()->clear();
        self::assertEquals(new \DateTimeImmutable('2026-11-01 10:00:00'), $this->user('camille')->getLastLoginAt());
    }

    public function testRefusedLoginDoesNotChangeIt(): void
    {
        // Point de départ fixé ici : la base de test peut garder une connexion d'un test Cypress précédent
        $this->user('camille')->setLastLoginAt(null);
        $this->em()->flush();

        $this->request('POST', '/api/login_check', ['email' => 'camille@test.local', 'password' => 'mauvais']);

        $this->assertStatus(401);
        $this->em()->clear();
        self::assertNull($this->user('camille')->getLastLoginAt());
    }
}
