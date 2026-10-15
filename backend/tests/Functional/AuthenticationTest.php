<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Connexion JWT (/api/login_check) et protection du reste de l'API.
 */
final class AuthenticationTest extends ApiTestCase
{
    public function testLoginReturnsUsableToken(): void
    {
        $response = $this->request('POST', '/api/login_check', ['email' => 'camille@test.local', 'password' => 'password']);

        $this->assertStatus(200);
        self::assertIsString($response['token'] ?? null);

        // Le jeton obtenu ouvre l'API au nom de Camille
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$response['token']);
        $me = $this->request('GET', '/api/me');
        $this->assertStatus(200);
        self::assertSame('camille', $me['username']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badCredentialsProvider(): iterable
    {
        yield 'mauvais mot de passe' => ['camille@test.local', 'mauvais'];
        yield 'compte inconnu' => ['inconnu@test.local', 'password'];
    }

    #[DataProvider('badCredentialsProvider')]
    public function testBadCredentialsAreRejected(string $email, string $password): void
    {
        $response = $this->request('POST', '/api/login_check', ['email' => $email, 'password' => $password]);

        $this->assertStatus(401);
        self::assertArrayNotHasKey('token', $response);
        // Même message dans les deux cas : on ne révèle pas si le compte existe
        self::assertSame('Invalid credentials.', $response['message']);
    }

    public function testSuspendedAccountCannotLogIn(): void
    {
        $response = $this->request('POST', '/api/login_check', ['email' => 'suspendu@test.local', 'password' => 'password']);

        $this->assertStatus(401);
        self::assertArrayNotHasKey('token', $response);
        self::assertSame('Ce compte est suspendu.', $response['message']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function protectedRouteProvider(): iterable
    {
        yield 'profil' => ['GET', '/api/me'];
        yield 'logements' => ['GET', '/api/places'];
        yield 'objets' => ['GET', '/api/items'];
        yield 'création de logement' => ['POST', '/api/places'];
        yield 'scan' => ['POST', '/api/scan'];
        yield 'photo d\'objet' => ['POST', '/api/items/1/photo'];
    }

    #[DataProvider('protectedRouteProvider')]
    public function testApiRequiresToken(string $method, string $uri): void
    {
        $response = $this->request($method, $uri, []);

        $this->assertStatus(401);
        self::assertSame('JWT Token not found', $response['message']);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer pas.un.jeton');
        $this->request('GET', '/api/me');

        $this->assertStatus(401);
    }

    public function testApiEntryPointAndDocsArePublic(): void
    {
        $this->client->request('GET', '/api', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertStatus(200);
    }
}
