<?php

namespace App\Tests\Functional;

use App\Entity\Block;
use App\Entity\Ootd;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Looks du jour : /api/ootds, /api/me/ootds, /api/users/{id}/ootds (docs/decisions.md, lot 2).
 * Lou n'a pas d'email et ne peut pas se connecter : ses droits sont testés dans OotdRightsTest.
 */
final class OotdApiTest extends ApiTestCase
{
    private const PUBLISHED = 'Tenue de bricolage du samedi';
    private const PUBLISHED_OLD = 'Jean brut et rien d\'autre';
    private const DRAFT = 'Idée pour la soirée';
    private const SOPHIE = 'Prête pour le marché';

    // Ce qu'un look ne doit jamais révéler d'un objet : son emplacement
    private const LOCATION_KEYS = ['category', 'storage', 'room', 'place', 'attributes', 'status', 'isPersonal', 'barcode'];

    /**
     * @return iterable<string, array{string, bool, list<string>, int, int, int}>
     */
    public static function matrixProvider(): iterable
    {
        // [personne, Marc bloqué, fil, GET publié, GET brouillon, PATCH publié]
        yield 'Camille, propriétaire' => ['camille', false, [self::SOPHIE, self::PUBLISHED, self::PUBLISHED_OLD], 200, 200, 200];
        yield 'Sacha, enfant +18' => ['sacha', false, [self::PUBLISHED, self::PUBLISHED_OLD], 200, 404, 403];
        yield 'Sophie, amie proche' => ['sophie', false, [self::SOPHIE, self::PUBLISHED, self::PUBLISHED_OLD], 200, 404, 403];
        yield 'Théo, abonné' => ['theo', false, [self::PUBLISHED, self::PUBLISHED_OLD], 200, 404, 403];
        yield 'Nina, demande en attente' => ['nina', false, [], 404, 404, 404];
        yield 'Marc, abonné' => ['marc', false, [self::PUBLISHED, self::PUBLISHED_OLD], 200, 404, 403];
        yield 'Marc, bloqué par Camille' => ['marc', true, [], 404, 404, 404];
        yield 'Zoé, inconnue' => ['zoe', false, [], 404, 404, 404];
    }

    /**
     * @param list<string> $feed
     */
    #[DataProvider('matrixProvider')]
    public function testRightsMatrix(string $viewer, bool $marcBlocked, array $feed, int $getPublished, int $getDraft, int $patchPublished): void
    {
        if ($marcBlocked) {
            $this->blockMarc();
        }
        $published = $this->ootdId(self::PUBLISHED);
        $draft = $this->ootdId(self::DRAFT);
        $this->logIn($viewer);

        // Fil : du plus récent au plus ancien
        self::assertSame($feed, array_column($this->request('GET', '/api/ootds')['member'], 'caption'));
        $this->request('GET', '/api/ootds/'.$published);
        $this->assertStatus($getPublished);
        $this->request('GET', '/api/ootds/'.$draft);
        $this->assertStatus($getDraft);
        $this->request('PATCH', '/api/ootds/'.$published, ['caption' => 'Modifié']);
        $this->assertStatus($patchPublished);
    }

    /**
     * Looks d'un profil public : même règle que le fil.
     *
     * @return iterable<string, array{string, bool, list<string>}>
     */
    public static function profileProvider(): iterable
    {
        yield 'Théo voit les looks de Camille' => ['theo', false, [self::PUBLISHED, self::PUBLISHED_OLD]];
        yield 'Sacha aussi (compte enfant)' => ['sacha', false, [self::PUBLISHED, self::PUBLISHED_OLD]];
        yield 'Camille voit ses publiés, pas son brouillon' => ['camille', false, [self::PUBLISHED, self::PUBLISHED_OLD]];
        yield 'Nina non' => ['nina', false, []];
        yield 'Zoé non' => ['zoe', false, []];
        yield 'Marc bloqué non' => ['marc', true, []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('profileProvider')]
    public function testOotdsOfAProfile(string $viewer, bool $marcBlocked, array $expected): void
    {
        if ($marcBlocked) {
            $this->blockMarc();
        }
        $camille = $this->user('camille');
        $this->logIn($viewer);

        $list = $this->request('GET', '/api/users/'.$camille->getId().'/ootds');

        $this->assertStatus(200);
        self::assertSame($expected, array_column($list['member'], 'caption'));
    }

    public function testMyOotdsListDraftsAndPublishedSeparately(): void
    {
        $this->logIn('camille');

        self::assertSame([self::DRAFT], array_column($this->request('GET', '/api/me/ootds?published=false')['member'], 'caption'));
        self::assertEqualsCanonicalizing([self::PUBLISHED, self::PUBLISHED_OLD], array_column($this->request('GET', '/api/me/ootds?published=true')['member'], 'caption'));
        self::assertCount(3, $this->request('GET', '/api/me/ootds')['member']);
    }

    public function testAnonymousIsRejected(): void
    {
        foreach (['/api/ootds', '/api/me/ootds', '/api/ootds/'.$this->ootdId(self::PUBLISHED)] as $uri) {
            $this->request('GET', $uri);
            $this->assertStatus(401);
        }
    }

    // --- Ce qu'un look expose ---

    public function testAnOotdShowsItsItemsWithoutTheirLocation(): void
    {
        $this->logIn('theo');

        $ootd = $this->request('GET', '/api/ootds/'.$this->ootdId(self::PUBLISHED));

        self::assertSame('camille', $ootd['owner']['username']);
        self::assertArrayNotHasKey('email', $ootd['owner']);
        self::assertTrue($ootd['published']);
        self::assertSame((new \DateTimeImmutable('-1 day'))->format('Y-m-d'), $ootd['wornOn']);
        // La Ponceuse vient du Garage (amis proches) : Théo la voit dans le look, pas l'endroit où elle est rangée
        self::assertSame(['T-shirt blanc', 'Ponceuse'], array_column($ootd['wornItems'], 'name'));
        self::assertSame('Petit Bateau', $ootd['wornItems'][0]['brand']);
        self::assertSame('Hauts', $ootd['wornItems'][0]['categoryName']);
        foreach ($ootd['wornItems'] as $item) {
            foreach (self::LOCATION_KEYS as $key) {
                self::assertArrayNotHasKey($key, $item);
            }
        }
        self::assertArrayNotHasKey('items', $ootd);
    }

    public function testAnItemMadePersonalAfterPublicationIsHiddenFromOthers(): void
    {
        $tshirt = $this->item('T-shirt blanc')->setIsPersonal(true);
        $this->em()->flush();
        $id = $this->ootdId(self::PUBLISHED);

        $this->logIn('theo');
        self::assertSame(['Ponceuse'], array_column($this->request('GET', '/api/ootds/'.$id)['wornItems'], 'name'));
        // Le propriétaire voit toujours tout
        $this->logIn('camille');
        self::assertSame(['T-shirt blanc', 'Ponceuse'], array_column($this->request('GET', '/api/ootds/'.$id)['wornItems'], 'name'));
        self::assertTrue($tshirt->isPersonal());
    }

    // --- Créer, publier, supprimer ---

    public function testCreateADraftWithShareableItems(): void
    {
        $this->logIn('camille');

        $ootd = $this->request('POST', '/api/ootds', [
            'caption' => 'Pull et ponceuse',
            'wornOn' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d'),
            'items' => [$this->iri('T-shirt blanc'), $this->iri('Ponceuse')],
        ]);

        $this->assertStatus(201);
        self::assertSame('camille', $ootd['owner']['username']);
        self::assertFalse($ootd['published']);
        self::assertNull($ootd['publishedAt']);
        self::assertSame(['T-shirt blanc', 'Ponceuse'], array_column($ootd['wornItems'], 'name'));
    }

    public function testWornOnDefaultsToToday(): void
    {
        $this->logIn('camille');

        $ootd = $this->request('POST', '/api/ootds', ['caption' => 'Sans date']);

        $this->assertStatus(201);
        self::assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $ootd['wornOn']);
    }

    public function testOwnerIsAlwaysTheConnectedUser(): void
    {
        $this->logIn('zoe');

        $ootd = $this->request('POST', '/api/ootds', ['caption' => 'Usurpation', 'owner' => '/api/users/'.$this->user('camille')->getId()]);

        $this->assertStatus(201);
        self::assertSame('zoe', $ootd['owner']['username']);
    }

    /**
     * Objets privés ou perso (eux-mêmes ou leur rangement, pièce, logement) : refusés.
     *
     * @return iterable<string, array{string}>
     */
    public static function hiddenItemProvider(): iterable
    {
        yield 'objet perso' => ['Carnet intime'];
        yield 'pièce privée (Bureau)' => ['Contrat de location'];
        yield 'rangement privé (carton de Noël)' => ['Guirlande lumineuse'];
        yield 'rangement perso (coffre à bijoux)' => ['Bague de grand-mère'];
        yield 'pièce perso (Collection)' => ['Montre de collection'];
        yield 'logement privé (garde-meuble)' => ['Manteau en laine'];
    }

    #[DataProvider('hiddenItemProvider')]
    public function testHiddenItemsAreRefused(string $name): void
    {
        $this->logIn('camille');

        $response = $this->request('POST', '/api/ootds', ['items' => [$this->iri('T-shirt blanc'), $this->iri($name)]]);

        $this->assertStatus(422);
        self::assertSame('items', $response['violations'][0]['propertyPath']);
        self::assertSame(\sprintf('« %s » est privé ou perso : il ne peut pas apparaître dans un look.', $name), $response['violations'][0]['message']);
    }

    public function testItemsOfSomeoneElseAreNotFound(): void
    {
        // Le hoodie de Sacha : CurrentOwnerExtension le rend introuvable pour Camille, sans révéler qu'il existe
        $this->logIn('camille');

        $this->request('POST', '/api/ootds', ['items' => [$this->iri('Hoodie gris')]]);

        $this->assertStatus(400);
        self::assertSame(0, $this->em()->getRepository(Ootd::class)->count(['owner' => $this->user('camille'), 'caption' => null]));
    }

    public function testAForeignItemAndAnUnknownItemGiveTheSameAnswer(): void
    {
        // Rien ne doit distinguer « l'objet existe mais n'est pas à moi » de « l'objet n'existe pas »
        $this->logIn('camille');
        $foreign = $this->iri('Hoodie gris');
        $unknown = '/api/items/999999';

        $foreignResponse = $this->comparable($this->request('POST', '/api/ootds', ['items' => [$foreign]]), $foreign);
        $foreignStatus = $this->client->getResponse()->getStatusCode();
        $unknownResponse = $this->comparable($this->request('POST', '/api/ootds', ['items' => [$unknown]]), $unknown);

        self::assertSame(400, $foreignStatus);
        $this->assertStatus(400);
        self::assertSame($unknownResponse, $foreignResponse);
        self::assertSame('Item not found for "{iri}".', $foreignResponse['detail']);
    }

    public function testFutureDateIsRefused(): void
    {
        $this->logIn('camille');

        $response = $this->request('POST', '/api/ootds', ['wornOn' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d')]);

        $this->assertStatus(422);
        self::assertSame('wornOn', $response['violations'][0]['propertyPath']);
    }

    public function testPublishingRequiresAPhoto(): void
    {
        $draft = $this->ootdId(self::DRAFT);
        $this->logIn('camille');

        $response = $this->request('PATCH', '/api/ootds/'.$draft, ['published' => true]);

        $this->assertStatus(422);
        self::assertSame('Ajoute une photo de ta tenue avant de publier.', $response['violations'][0]['message']);
        $this->em()->clear();
        self::assertFalse($this->em()->find(Ootd::class, $draft)?->isPublished());
    }

    public function testPublishingADraftWithAPhoto(): void
    {
        $draft = $this->ootdId(self::DRAFT);
        $this->em()->find(Ootd::class, $draft)?->setPhoto('/uploads/demo/t-shirt-blanc.jpg');
        $this->em()->flush();
        $this->logIn('camille');

        $ootd = $this->request('PATCH', '/api/ootds/'.$draft, ['published' => true]);

        $this->assertStatus(200);
        self::assertTrue($ootd['published']);
        self::assertNotNull($ootd['publishedAt']);
        // Il arrive en tête du fil de Théo
        $this->logIn('theo');
        self::assertSame(self::DRAFT, $this->request('GET', '/api/ootds')['member'][0]['caption']);
    }

    public function testPublishingTwiceKeepsTheFirstDate(): void
    {
        $id = $this->ootdId(self::PUBLISHED_OLD);
        $this->logIn('camille');
        $before = $this->request('GET', '/api/ootds/'.$id)['publishedAt'];

        $after = $this->request('PATCH', '/api/ootds/'.$id, ['published' => true]);

        $this->assertStatus(200);
        self::assertSame($before, $after['publishedAt']);
    }

    public function testAPublishedOotdCannotGoBackToDraft(): void
    {
        $this->logIn('camille');

        $response = $this->request('PATCH', '/api/ootds/'.$this->ootdId(self::PUBLISHED), ['published' => false]);

        $this->assertStatus(422);
        self::assertSame('published', $response['violations'][0]['propertyPath']);
        self::assertSame('Un look publié ne peut pas redevenir un brouillon : supprime-le.', $response['violations'][0]['message']);
    }

    public function testPatchValidatesItemsToo(): void
    {
        $this->logIn('camille');

        $this->request('PATCH', '/api/ootds/'.$this->ootdId(self::PUBLISHED), ['items' => [$this->iri('Carnet intime')]]);

        $this->assertStatus(422);
    }

    public function testOwnerDeletesAnOotd(): void
    {
        $id = $this->ootdId(self::PUBLISHED);
        $this->logIn('camille');

        $this->request('DELETE', '/api/ootds/'.$id);

        $this->assertStatus(204);
        $this->em()->clear();
        self::assertNull($this->em()->find(Ootd::class, $id));
        // Les objets portés existent toujours
        self::assertSame('Ponceuse', $this->item('Ponceuse')->getName());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function deleteProvider(): iterable
    {
        yield 'Sophie peut voir, pas supprimer' => ['sophie', 403];
        yield 'Zoé ne voit même pas le look' => ['zoe', 404];
    }

    #[DataProvider('deleteProvider')]
    public function testOthersCannotDelete(string $viewer, int $expected): void
    {
        $id = $this->ootdId(self::PUBLISHED);
        $this->logIn($viewer);

        $this->request('DELETE', '/api/ootds/'.$id);

        $this->assertStatus($expected);
        $this->em()->clear();
        self::assertNotNull($this->em()->find(Ootd::class, $id));
    }

    // --- Pagination du fil ---

    public function testFeedShowsTenOotdsPerPage(): void
    {
        $camille = $this->user('camille');
        for ($i = 1; $i <= 11; ++$i) {
            $this->em()->persist((new Ootd())->setOwner($camille)->setCaption('Look '.$i)->setPhoto('/uploads/demo/t-shirt-blanc.jpg')->setPublishedAt(new \DateTimeImmutable(\sprintf('+%d minutes', $i))));
        }
        $this->em()->flush();
        $this->logIn('theo');

        $first = $this->request('GET', '/api/ootds');
        $second = $this->request('GET', '/api/ootds?page=2');

        self::assertCount(10, $first['member']);
        self::assertSame('Look 11', $first['member'][0]['caption']);
        self::assertSame(13, $first['totalItems']);
        self::assertArrayHasKey('next', $first['view']);
        self::assertSame(['Look 1', self::PUBLISHED, self::PUBLISHED_OLD], array_column($second['member'], 'caption'));
    }

    /**
     * Réponse d'erreur sans ce qui dépend de la requête : l'IRI envoyée (remplacée par {iri})
     * et la trace, affichée seulement en mode debug.
     *
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function comparable(array $response, string $iri): array
    {
        unset($response['trace']);

        return json_decode(str_replace(addcslashes($iri, '/'), '{iri}', (string) json_encode($response)), true);
    }

    private function blockMarc(): void
    {
        $this->em()->persist((new Block())->setBlocker($this->user('camille'))->setBlocked($this->user('marc')));
        $this->em()->flush();
    }

    private function ootdId(string $caption): int
    {
        $ootd = $this->em()->getRepository(Ootd::class)->findOneBy(['caption' => $caption]);
        self::assertInstanceOf(Ootd::class, $ootd, \sprintf('OOTD « %s » absent : lancer make test-db', $caption));

        return (int) $ootd->getId();
    }

    private function iri(string $itemName): string
    {
        return '/api/items/'.$this->item($itemName)->getId();
    }
}
