<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Room;
use App\Entity\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Isolation des données par l'API : en attendant les Voters, chacun ne voit que son contenu
 * et le contenu des autres répond 404 (on ne révèle pas qu'il existe).
 */
final class IsolationTest extends ApiTestCase
{
    public function testFollowerCannotReadOwnerPlace(): void
    {
        $house = $this->place('Maison');
        $this->logIn('theo');

        $this->request('GET', '/api/places/'.$house->getId());

        $this->assertStatus(404);
    }

    public function testOwnerReadsOwnPlace(): void
    {
        $house = $this->place('Maison');
        $this->logIn('camille');

        $place = $this->request('GET', '/api/places/'.$house->getId());

        $this->assertStatus(200);
        self::assertSame('Maison', $place['name']);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function collectionProvider(): iterable
    {
        yield 'Camille' => ['camille', 2];
        yield 'Sacha (compte enfant)' => ['sacha', 1];
        yield 'Théo (abonné)' => ['theo', 0];
    }

    #[DataProvider('collectionProvider')]
    public function testPlaceCollectionIsLimitedToOwner(string $username, int $expected): void
    {
        $this->logIn($username);

        $places = $this->request('GET', '/api/places');

        $this->assertStatus(200);
        self::assertSame($expected, $places['totalItems']);
    }

    /**
     * @return iterable<string, array{string, class-string, string}>
     */
    public static function nestedResourceProvider(): iterable
    {
        yield 'pièce' => ['/api/rooms/', Room::class, 'Garage'];
        yield 'rangement' => ['/api/storages/', Storage::class, 'Caisse à outils'];
        yield 'catégorie' => ['/api/categories/', Category::class, 'Électroportatif'];
    }

    /**
     * Même Sophie, amie proche, ne passe pas encore par l'API pour voir le garage de Camille.
     *
     * @param class-string $class
     */
    #[DataProvider('nestedResourceProvider')]
    public function testNestedResourcesOfOthersAreNotFound(string $uri, string $class, string $name): void
    {
        $resource = $this->em()->getRepository($class)->findOneBy(['name' => $name]);
        self::assertNotNull($resource);
        $this->logIn('sophie');

        $this->request('GET', $uri.$resource->getId());

        $this->assertStatus(404);
    }

    public function testItemsOfOthersAreNotFound(): void
    {
        $tshirt = $this->item('T-shirt blanc');
        $this->logIn('theo');

        $this->request('GET', '/api/items/'.$tshirt->getId());
        $this->assertStatus(404);

        self::assertSame(0, $this->request('GET', '/api/items')['totalItems']);
    }

    public function testCreatedPlaceBelongsToConnectedUser(): void
    {
        $this->logIn('theo');

        $created = $this->request('POST', '/api/places', ['name' => 'Appartement', 'city' => 'Reims', 'visibility' => 'private']);

        $this->assertStatus(201);
        self::assertSame('theo', $this->place('Appartement')->getOwner()?->getUsername());
        // Théo retrouve son logement, Camille ne le voit pas
        self::assertSame(1, $this->request('GET', '/api/places')['totalItems']);
        $this->logIn('camille');
        $this->request('GET', $created['@id']);
        $this->assertStatus(404);
    }
}
