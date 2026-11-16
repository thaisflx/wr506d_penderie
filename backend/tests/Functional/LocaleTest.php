<?php

namespace App\Tests\Functional;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Langue des réponses de l'API (docs/decisions.md, « Internationalisation ») : celle du compte pour une personne
 * connectée, sinon celle de l'en-tête Accept-Language (fr, en, de), sinon le français.
 */
final class LocaleTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptLanguageProvider(): iterable
    {
        yield 'français' => ['fr', 'Email ou mot de passe incorrect.'];
        yield 'anglais' => ['en', 'Incorrect email or password.'];
        yield 'allemand' => ['de', 'E-Mail-Adresse oder Passwort falsch.'];
        yield 'variante régionale : de-CH donne de' => ['de-CH', 'E-Mail-Adresse oder Passwort falsch.'];
        yield 'préférences pondérées : en avant de' => ['de;q=0.5, en;q=0.9', 'Incorrect email or password.'];
        yield 'langue non gérée : français' => ['es', 'Email ou mot de passe incorrect.'];
        yield 'en-tête vide : français' => ['', 'Email ou mot de passe incorrect.'];
    }

    // Visiteur anonyme : la connexion refusée répond dans la langue de son navigateur
    #[DataProvider('acceptLanguageProvider')]
    public function testAnonymousVisitorGetsTheLanguageOfTheHeader(string $acceptLanguage, string $expected): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', $acceptLanguage);

        $response = $this->request('POST', '/api/login_check', ['email' => 'camille@test.local', 'password' => 'mauvais']);

        $this->assertStatus(401);
        self::assertSame($expected, $response['message']);
    }

    public function testSuspendedAccountMessageIsTranslated(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'de');

        $response = $this->request('POST', '/api/login_check', ['email' => 'suspendu@test.local', 'password' => 'password']);

        $this->assertStatus(401);
        self::assertSame('Dieses Konto ist gesperrt.', $response['message']);
    }

    // Personne connectée : la langue de son compte l'emporte sur celle de son navigateur
    public function testTheLanguageOfTheAccountWinsOverTheHeader(): void
    {
        $camille = $this->logIn('camille');
        $camille->setLocale('de');
        $this->em()->flush();
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'en');

        $response = $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$camille->getId()]);

        $this->assertStatus(422);
        self::assertSame('Du kannst dich nicht selbst blockieren.', $response['violations'][0]['message']);
    }

    public function testAnAccountInFrenchStaysInFrenchWithAnEnglishBrowser(): void
    {
        $camille = $this->logIn('camille');
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'en');

        $response = $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$camille->getId()]);

        self::assertSame('Tu ne peux pas te bloquer toi-même.', $response['violations'][0]['message']);
    }

    public function testErrorsOfControllersAndProcessorsAreTranslated(): void
    {
        $camille = $this->logIn('camille');
        $camille->setLocale('en');
        $this->em()->flush();

        $photo = $this->request('POST', '/api/ootds/999999/photo', ['image' => 'x']);
        self::assertSame('This look can\'t be found.', $photo['message']);

        $search = $this->request('GET', '/api/users?username=c');
        self::assertSame('Type at least 2 characters of the username.', $search['detail']);
    }

    public function testMeShowsTheLanguageOfTheAccount(): void
    {
        $this->logIn('camille');

        self::assertSame('fr', $this->request('GET', '/api/me')['locale']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedLocaleProvider(): iterable
    {
        yield 'anglais' => ['en'];
        yield 'allemand' => ['de'];
        yield 'retour au français' => ['fr'];
    }

    #[DataProvider('supportedLocaleProvider')]
    public function testIChangeTheLanguageOfMyAccount(string $locale): void
    {
        $this->logIn('camille')->setLocale('de');
        $this->em()->flush();

        $me = $this->request('PATCH', '/api/me', ['locale' => $locale]);

        $this->assertStatus(200);
        self::assertSame($locale, $me['locale']);
        self::assertSame('camille', $me['username']);
        $this->em()->clear();
        self::assertSame($locale, $this->user('camille')->getLocale());
    }

    public function testTheNextRequestsUseTheNewLanguage(): void
    {
        $camille = $this->logIn('camille');

        $this->request('PATCH', '/api/me', ['locale' => 'de']);
        $response = $this->request('POST', '/api/blocks', ['blocked' => '/api/users/'.$camille->getId()]);

        self::assertSame('Du kannst dich nicht selbst blockieren.', $response['violations'][0]['message']);
    }

    public function testAnUnsupportedLanguageIsRefused(): void
    {
        $this->logIn('camille');

        $response = $this->request('PATCH', '/api/me', ['locale' => 'es']);

        $this->assertStatus(422);
        self::assertSame('locale', $response['violations'][0]['propertyPath']);
        self::assertSame('Choisis une langue proposée : fr, en ou de.', $response['violations'][0]['message']);
        $this->em()->clear();
        self::assertSame('fr', $this->user('camille')->getLocale());
    }

    /**
     * @return iterable<string, array{array<string, mixed>|string}>
     */
    public static function invalidBodyProvider(): iterable
    {
        yield 'un autre champ en plus de la langue' => [['locale' => 'en', 'email' => 'pirate@test.local']];
        yield 'un autre champ seul' => [['roles' => ['ROLE_ADMIN']]];
        yield 'langue qui n\'est pas un texte' => [['locale' => ['en']]];
        yield 'corps vide' => [[]];
        yield 'corps non JSON' => ['locale=en'];
    }

    // La route ne sert qu'à la langue : ni l'email, ni les rôles, ni rien d'autre ne peuvent passer par elle
    #[DataProvider('invalidBodyProvider')]
    public function testOnlyTheLanguageCanBeChanged(array|string $body): void
    {
        $this->logIn('camille');

        $response = $this->request('PATCH', '/api/me', $body);

        $this->assertStatus(400);
        self::assertSame('Seule la langue (locale) peut être modifiée ici.', $response['detail']);
        $this->em()->clear();
        $camille = $this->user('camille');
        self::assertSame('fr', $camille->getLocale());
        self::assertSame('camille@test.local', $camille->getEmail());
        self::assertNotContains('ROLE_ADMIN', $camille->getRoles());
    }

    public function testAnonymousCannotChangeALanguage(): void
    {
        $this->request('PATCH', '/api/me', ['locale' => 'en']);

        $this->assertStatus(401);
        self::assertSame(User::DEFAULT_LOCALE, $this->user('camille')->getLocale());
    }
}
