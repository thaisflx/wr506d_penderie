<?php

namespace App\Tests\Functional\Admin;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Accès au back office (docs/decisions.md, « Back office ») : ROLE_ADMIN seulement, connexion par formulaire avec
 * jeton CSRF, comptes suspendus refusés, et séparation totale avec l'API (JWT).
 */
final class AdminAccessTest extends AdminTestCase
{
    public function testAnonymousIsSentToTheLoginForm(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    public function testLoginFormHasACsrfTokenAndTheLanguageOfTheBrowser(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'de');

        $crawler = $this->client->request('GET', '/admin/login');

        $this->assertStatus(200);
        self::assertSame('de', $crawler->filter('html')->attr('lang'));
        self::assertCount(1, $crawler->filter('input[name="_csrf_token"]'));
        self::assertSelectorTextContains('button[type="submit"]', 'Anmelden');
    }

    public function testAdminLogsInWithTheFormAndTheDateIsRecorded(): void
    {
        $this->submitLogin('admin@test.local', 'password');

        self::assertResponseRedirects('http://localhost/admin');
        $this->client->followRedirect();
        $this->assertStatus(200);
        self::assertSelectorTextContains('h1', 'Tableau de bord');
        $this->em()->clear();
        self::assertNotNull($this->user('admin')->getLastLoginAt());
    }

    public function testAUserWithoutTheAdminRoleIsRefused(): void
    {
        $this->submitLogin('camille@test.local', 'password');
        $this->client->request('GET', '/admin');

        $this->assertStatus(403);
    }

    public function testASuspendedAccountCannotLogIn(): void
    {
        $this->submitLogin('suspendu@test.local', 'password');
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Ce compte est suspendu.', $crawler->text());
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    public function testAWrongPasswordIsRefused(): void
    {
        $this->submitLogin('admin@test.local', 'mauvais');
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Email ou mot de passe incorrect.', $crawler->text());
    }

    public function testTheFormIsRefusedWithoutAValidCsrfToken(): void
    {
        $this->client->request('POST', '/admin/login', ['_username' => 'admin@test.local', '_password' => 'password', '_csrf_token' => 'faux']);
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    // Un jeton de l'API n'ouvre pas le back office…
    public function testAJwtTokenDoesNotOpenTheBackOffice(): void
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($this->user('admin'));

        $this->client->request('GET', '/admin', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    // … et une session du back office n'ouvre pas l'API. Connexion par le vrai formulaire : loginUser() de Symfony
    // placerait aussi le jeton dans la requête suivante, quel que soit son firewall
    public function testAnAdminSessionDoesNotOpenTheApi(): void
    {
        $this->submitLogin('admin@test.local', 'password');
        $this->client->request('GET', '/admin');
        $this->assertStatus(200);

        $this->client->request('GET', '/api/me');

        $this->assertStatus(401);
    }

    public function testLogoutClosesTheSession(): void
    {
        $this->logInAdmin();

        $this->client->request('GET', '/admin/logout');
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    public function testTheBackOfficeSpeaksTheLanguageOfTheAdminAccount(): void
    {
        $this->logInAdmin()->setLocale('de');
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/admin');

        self::assertSame('de', $crawler->filter('html')->attr('lang'));
        self::assertSelectorTextContains('h1', 'Übersicht');
    }

    private function submitLogin(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/admin/login');
        $form = $crawler->filter('form')->form(['_username' => $email, '_password' => $password]);
        $this->client->submit($form);
    }
}
