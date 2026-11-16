<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Ootd;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Looks, objets et catégories dans le back office (docs/decisions.md, « Back office ») : « le dressing doit être
 * privé » (brief). L'admin modère les looks publiés, voit les objets montrés dans ces looks sans leur emplacement,
 * et gère les catégories sans voir où elles sont rangées.
 */
final class AdminContentTest extends AdminTestCase
{
    // Lieux du dressing de Camille (fixtures test) : jamais affichés dans le back office
    private const LOCATIONS = ['Maison', 'Garde-meuble', 'Dressing', 'Penderie', 'Garage', 'Caisse à outils', 'Grenier'];

    public function testOnlyPublishedLooksAreListed(): void
    {
        $this->logInAdmin();

        $crawler = $this->client->request('GET', '/admin/ootd');

        $this->assertStatus(200);
        $text = $crawler->text();
        foreach (['Tenue de bricolage du samedi', 'Jean brut et rien d\'autre', 'Prête pour le marché', 'camille', 'sophie'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }
        self::assertStringNotContainsString('Idée pour la soirée', $text);
    }

    public function testADraftIsNotFoundEvenByItsAddress(): void
    {
        $this->logInAdmin();
        $draft = $this->ootd('Idée pour la soirée');

        $this->client->request('GET', '/admin/ootd/'.$draft->getId());
        $this->assertStatus(404);

        $this->client->request('POST', '/admin/ootd/'.$draft->getId().'/delete');
        $this->assertStatus(404);
        $this->em()->clear();
        self::assertNotNull($this->em()->find(Ootd::class, $draft->getId()));
    }

    public function testTheDetailOfALookShowsWhatIsWornWithoutTheLocation(): void
    {
        $this->logInAdmin();

        $crawler = $this->client->request('GET', '/admin/ootd/'.$this->ootd('Tenue de bricolage du samedi')->getId());

        $this->assertStatus(200);
        self::assertStringContainsString('T-shirt blanc, Ponceuse', $crawler->text());
        $this->assertNoLocation($crawler->text());
    }

    // Comme pour les autres personnes : un objet devenu perso après la publication disparaît du look
    public function testAnItemMadePersonalAfterPublicationIsHiddenFromTheLook(): void
    {
        $this->logInAdmin();
        $this->item('Ponceuse')->setIsPersonal(true);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/admin/ootd/'.$this->ootd('Tenue de bricolage du samedi')->getId());

        self::assertStringContainsString('T-shirt blanc', $crawler->text());
        self::assertStringNotContainsString('Ponceuse', $crawler->text());
    }

    public function testTheAdminDeletesAPublishedLook(): void
    {
        $this->logInAdmin();
        $look = $this->ootd('Prête pour le marché');

        $crawler = $this->client->request('GET', '/admin/ootd/'.$look->getId());
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $this->client->request('POST', '/admin/ootd/'.$look->getId().'/delete', ['token' => $token]);

        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertNull($this->em()->find(Ootd::class, $look->getId()));
    }

    public function testADeleteWithoutAValidTokenIsRefused(): void
    {
        $this->logInAdmin();
        $look = $this->ootd('Prête pour le marché');

        $this->client->request('POST', '/admin/ootd/'.$look->getId().'/delete', ['token' => 'faux']);

        $this->em()->clear();
        self::assertNotNull($this->em()->find(Ootd::class, $look->getId()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function forbiddenLookActionProvider(): iterable
    {
        yield 'créer un look' => ['GET', '/admin/ootd/new'];
        yield 'modifier un look' => ['GET', '/admin/ootd/{id}/edit'];
    }

    #[DataProvider('forbiddenLookActionProvider')]
    public function testLooksAreNeitherCreatedNorChanged(string $method, string $uri): void
    {
        $this->logInAdmin();

        $this->client->request($method, str_replace('{id}', (string) $this->ootd('Tenue de bricolage du samedi')->getId(), $uri));

        $this->assertStatus(403);
    }

    public function testOnlyItemsShownInAPublishedLookAreListed(): void
    {
        $this->logInAdmin();

        $crawler = $this->client->request('GET', '/admin/item');

        $this->assertStatus(200);
        $names = $crawler->filter('td[data-column="name"]')->each(static fn ($cell) => trim($cell->text()));
        sort($names);
        // Pas les Baskets montantes (brouillon), ni la Perceuse (jamais dans un look), ni le Carnet intime (perso)
        self::assertSame(['Jean brut', 'Ponceuse', 'T-shirt blanc'], $names);
        self::assertStringContainsString('Haut', $crawler->text());
        $this->assertNoLocation($crawler->text());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hiddenItemProvider(): iterable
    {
        yield 'seulement dans un brouillon' => ['Baskets montantes'];
        yield 'jamais dans un look' => ['Perceuse'];
        yield 'objet perso' => ['Carnet intime'];
    }

    #[DataProvider('hiddenItemProvider')]
    public function testAnItemOfThePrivateWardrobeIsNotFoundEvenByItsAddress(string $name): void
    {
        $this->logInAdmin();

        $this->client->request('GET', '/admin/item/'.$this->item($name)->getId());

        $this->assertStatus(404);
    }

    public function testTheDetailOfAnItemShowsNoLocation(): void
    {
        $this->logInAdmin();

        $crawler = $this->client->request('GET', '/admin/item/'.$this->item('Ponceuse')->getId());

        $this->assertStatus(200);
        self::assertStringContainsString('Ponceuse', $crawler->text());
        self::assertStringContainsString('camille', $crawler->text());
        $this->assertNoLocation($crawler->text());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenItemActionProvider(): iterable
    {
        yield 'créer' => ['/admin/item/new'];
        yield 'modifier' => ['/admin/item/{id}/edit'];
    }

    #[DataProvider('forbiddenItemActionProvider')]
    public function testItemsAreReadOnly(string $uri): void
    {
        $this->logInAdmin();

        $this->client->request('GET', str_replace('{id}', (string) $this->item('Ponceuse')->getId(), $uri));

        $this->assertStatus(403);
    }

    public function testCategoriesShowNameTypeAndCountWithoutTheirStorage(): void
    {
        $this->logInAdmin();

        $crawler = $this->client->request('GET', '/admin/category');

        $this->assertStatus(200);
        self::assertStringContainsString('Hauts', $crawler->text());
        self::assertStringContainsString('Haut', $crawler->text());
        $this->assertNoLocation($crawler->text());
    }

    public function testTheAdminRenamesACategory(): void
    {
        $this->logInAdmin();
        $category = $this->category('Hauts');

        $crawler = $this->client->request('GET', '/admin/category/'.$category->getId().'/edit');
        $form = $crawler->filter('form[name="Category"]')->form(['Category[name]' => 'Hauts et pulls']);
        $this->client->request('POST', $form->getUri(), [...$form->getPhpValues(), 'ea' => ['newForm' => ['btn' => 'saveAndReturn']]]);

        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertSame('Hauts et pulls', $this->em()->find(Category::class, $category->getId())?->getName());
    }

    public function testACategoryWithItemsIsNeverDeleted(): void
    {
        $this->logInAdmin();
        $category = $this->category('Hauts');
        self::assertFalse($category->getItems()->isEmpty());

        $this->deleteCategory($category);

        $this->em()->clear();
        self::assertNotNull($this->em()->find(Category::class, $category->getId()));
        self::assertStringContainsString('Cette catégorie contient des objets : elle ne peut pas être supprimée.', $this->client->followRedirect()->text());
    }

    public function testAnEmptyCategoryIsDeleted(): void
    {
        $this->logInAdmin();
        $category = $this->category('Hauts');
        foreach ($category->getItems() as $item) {
            $this->em()->remove($item);
        }
        $this->em()->flush();

        $this->deleteCategory($category);

        $this->em()->clear();
        self::assertNull($this->em()->find(Category::class, $category->getId()));
    }

    public function testCategoriesAreNotCreatedInSomeoneElsesStorage(): void
    {
        $this->logInAdmin();

        $this->client->request('GET', '/admin/category/new');

        $this->assertStatus(403);
    }

    private function deleteCategory(Category $category): void
    {
        $crawler = $this->client->request('GET', '/admin/category/'.$category->getId());
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $this->client->request('POST', '/admin/category/'.$category->getId().'/delete', ['token' => $token]);
    }

    private function assertNoLocation(string $text): void
    {
        foreach (self::LOCATIONS as $location) {
            self::assertStringNotContainsString($location, $text, \sprintf('Emplacement « %s » affiché', $location));
        }
    }

    private function ootd(string $caption): Ootd
    {
        $ootd = $this->em()->getRepository(Ootd::class)->findOneBy(['caption' => $caption]);
        self::assertInstanceOf(Ootd::class, $ootd);

        return $ootd;
    }

    private function item(string $name): Item
    {
        $item = $this->em()->getRepository(Item::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Item::class, $item);

        return $item;
    }

    private function category(string $name): Category
    {
        $category = $this->em()->getRepository(Category::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Category::class, $category);

        return $category;
    }
}
