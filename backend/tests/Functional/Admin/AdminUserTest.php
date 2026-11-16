<?php

namespace App\Tests\Functional\Admin;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Comptes dans le back office (docs/decisions.md, « Back office ») : de quoi gérer un compte, rien du dressing ni
 * la date de naissance ; seulement le rôle admin et la suspension ; ni création ni suppression ; un admin ne peut
 * pas se bloquer lui-même.
 */
final class AdminUserTest extends AdminTestCase
{
    public function testTheListShowsWhatIsNeededToManageAccounts(): void
    {
        $this->logInAdmin();

        $crawler = $this->client->request('GET', '/admin/user');

        $this->assertStatus(200);
        foreach (['camille', 'camille@test.local', 'Administrateur', 'Suspendu', 'Mineur', 'Objets rangés', 'Inscription', 'Dernière connexion'] as $expected) {
            self::assertStringContainsString($expected, $crawler->text());
        }
    }

    public function testTheDetailNeverShowsTheBirthDateNorTheWardrobe(): void
    {
        $this->logInAdmin();
        $camille = $this->user('camille');

        $crawler = $this->client->request('GET', '/admin/user/'.$camille->getId());

        $this->assertStatus(200);
        $text = $crawler->text();
        self::assertStringContainsString('camille@test.local', $text);
        self::assertStringNotContainsString((string) $camille->getBirthDate()?->format('Y'), $text);
        // Ni logement, ni pièce, ni objet du dressing de Camille
        foreach (['Maison', 'Garde-meuble', 'Dressing', 'Penderie', 'Carnet intime'] as $private) {
            self::assertStringNotContainsString($private, $text);
        }
    }

    public function testOnlyTheAdminRoleAndTheSuspensionCanBeChanged(): void
    {
        $this->logInAdmin();

        $crawler = $this->client->request('GET', '/admin/user/'.$this->user('camille')->getId().'/edit');

        $fields = $crawler->filter('form[name="User"]')->form()->all();
        self::assertEqualsCanonicalizing(['User[admin]', 'User[isSuspended]', 'User[_token]'], array_keys($fields));
    }

    public function testTheAdminSuspendsAnAccountWhichCanNoLongerLogIn(): void
    {
        $this->logInAdmin();

        $this->submitUserForm('camille', admin: false, suspended: true);

        $this->em()->clear();
        self::assertTrue($this->user('camille')->isSuspended());
        $this->client->request('POST', '/api/login_check', server: ['CONTENT_TYPE' => 'application/json'], content: '{"email": "camille@test.local", "password": "password"}');
        $this->assertStatus(401);
    }

    public function testTheAdminGivesTheAdminRole(): void
    {
        $this->logInAdmin();

        $this->submitUserForm('camille', admin: true, suspended: false);

        $this->em()->clear();
        $camille = $this->user('camille');
        self::assertTrue($camille->isAdmin());
        self::assertContains('ROLE_ADMIN', $camille->getRoles());
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function lockYourselfProvider(): iterable
    {
        yield 'se retirer le rôle admin' => [false, false];
        yield 'se suspendre' => [true, true];
    }

    #[DataProvider('lockYourselfProvider')]
    public function testAnAdminCannotLockThemselfOut(bool $admin, bool $suspended): void
    {
        $this->logInAdmin();

        $crawler = $this->submitUserForm('admin', admin: $admin, suspended: $suspended);

        self::assertStringContainsString('Tu ne peux ni te suspendre ni te retirer le rôle administrateur.', $crawler->text());
        $this->em()->clear();
        $me = $this->user('admin');
        self::assertTrue($me->isAdmin());
        self::assertFalse($me->isSuspended());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function forbiddenActionProvider(): iterable
    {
        yield 'créer un compte' => ['GET', '/admin/user/new'];
        yield 'supprimer un compte' => ['POST', '/admin/user/{id}/delete'];
    }

    #[DataProvider('forbiddenActionProvider')]
    public function testNoAccountIsCreatedNorDeleted(string $method, string $uri): void
    {
        $this->logInAdmin();
        $camille = $this->user('camille');

        $this->client->request($method, str_replace('{id}', (string) $camille->getId(), $uri));

        $this->assertStatus(403);
        $this->em()->clear();
        self::assertInstanceOf(User::class, $this->em()->find(User::class, $camille->getId()));
    }

    // Envoie le formulaire de modification puis suit la redirection
    private function submitUserForm(string $username, bool $admin, bool $suspended): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/user/'.$this->user($username)->getId().'/edit');
        $form = $crawler->filter('form[name="User"]')->form();
        $admin ? $form['User[admin]']->tick() : $form['User[admin]']->untick();
        $suspended ? $form['User[isSuspended]']->tick() : $form['User[isSuspended]']->untick();
        // Le bouton « Enregistrer » d'EasyAdmin est hors du <form> (attribut form=) : sa valeur est ajoutée à la main
        $this->client->request('POST', $form->getUri(), [...$form->getPhpValues(), 'ea' => ['newForm' => ['btn' => 'saveAndReturn']]]);

        return $this->client->followRedirect();
    }
}
