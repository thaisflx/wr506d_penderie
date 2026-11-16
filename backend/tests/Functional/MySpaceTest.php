<?php

namespace App\Tests\Functional;

use App\Entity\Block;
use App\Entity\Item;
use App\Entity\Ootd;
use App\Tests\Support\Images;

/**
 * « Mon espace » : mes chiffres (GET /api/me/stats) et l'export de mes données (GET /api/me/export). Uniquement mes
 * données ; des autres personnes, seulement leur pseudo (docs/decisions.md, « Back office et Mon espace »).
 */
final class MySpaceTest extends ApiTestCase
{
    public function testMyStats(): void
    {
        $this->logIn('camille');

        $stats = $this->request('GET', '/api/me/stats');

        $this->assertStatus(200);
        self::assertSame(14, $stats['itemCount']);
        self::assertSame(['name' => 'Hauts', 'labelKey' => 'category.tops', 'location' => 'Dressing › Penderie', 'count' => 3], array_diff_key($stats['itemsByCategory'][0], ['id' => 0]));
        self::assertSame(14, array_sum(array_column($stats['itemsByCategory'], 'count')));
        self::assertSame(['Maison' => 13, 'Garde-meuble' => 1], array_column($stats['itemsByPlace'], 'count', 'name'));
        // Looks publiés de Camille : T-shirt blanc et Ponceuse, puis Jean brut ; les Baskets ne sont que dans un brouillon
        self::assertSame(2, $stats['publishedLooks']);
        self::assertEqualsCanonicalizing(['Jean brut', 'Ponceuse', 'T-shirt blanc'], array_column($stats['mostWorn'], 'name'));
        self::assertSame([1, 1, 1], array_column($stats['mostWorn'], 'count'));
        self::assertCount(11, $stats['neverWorn']);
        self::assertContains('Baskets montantes', array_column($stats['neverWorn'], 'name'));
        self::assertNotContains('Ponceuse', array_column($stats['neverWorn'], 'name'));
        // Abonnés : Sophie, Théo, Marc (Sophie compte une fois, abonnée et amie proche) ; abonnements : Sophie
        self::assertSame(3, $stats['followers']);
        self::assertSame(1, $stats['following']);
    }

    public function testTheMostWornComeFirst(): void
    {
        $this->logIn('camille');
        // Un deuxième look publié avec la Ponceuse
        $this->request('POST', '/api/ootds', ['caption' => 'Encore la ponceuse', 'items' => ['/api/items/'.$this->itemId('Ponceuse')]]);
        $this->assertStatus(201);
        $this->request('POST', '/api/ootds/'.$this->lastOotdId().'/photo', ['image' => Images::dataUrl(Images::jpeg())]);
        $this->request('PATCH', '/api/ootds/'.$this->lastOotdId(), ['published' => true]);

        $stats = $this->request('GET', '/api/me/stats');

        self::assertSame(['name' => 'Ponceuse', 'count' => 2], array_diff_key($stats['mostWorn'][0], ['id' => 0]));
        self::assertSame(3, $stats['publishedLooks']);
        $this->request('DELETE', '/api/ootds/'.$this->lastOotdId());
    }

    public function testStatsOfSomeoneWithNothing(): void
    {
        $this->logIn('theo');

        $stats = $this->request('GET', '/api/me/stats');

        self::assertSame(['itemCount' => 0, 'itemsByCategory' => [], 'itemsByPlace' => [], 'neverWorn' => [], 'mostWorn' => [], 'publishedLooks' => 0, 'followers' => 0, 'following' => 1], $stats);
    }

    public function testMyExportContainsAllMyDataAsAFile(): void
    {
        $this->logIn('camille');

        $export = $this->request('GET', '/api/me/export');

        $this->assertStatus(200);
        self::assertMatchesRegularExpression('/^attachment; filename=lendly-mes-donnees-\d{4}-\d{2}-\d{2}\.json$/', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertSame('camille@test.local', $export['profile']['email']);
        self::assertSame(['sacha', 'lou'], $export['profile']['children']);
        self::assertSame(['Maison', 'Garde-meuble'], array_column($export['places'], 'name'));
        $penderie = $export['places'][0]['rooms'][0]['storages'][0];
        self::assertSame('Penderie', $penderie['name']);
        self::assertContains('T-shirt blanc', array_column($penderie['categories'][0]['items'], 'name'));
        // Brouillon compris
        self::assertContains('Idée pour la soirée', array_column($export['looks'], 'caption'));
        self::assertCount(3, $export['looks']);
        self::assertContains(['to' => 'sophie', 'type' => 'close_friend', 'status' => 'accepted'], array_map(static fn (array $r) => array_diff_key($r, ['createdAt' => 0]), $export['relationships']['sent']));
        self::assertContains('nina', array_column($export['relationships']['received'], 'from'));
    }

    public function testTheExportRevealsNothingAboutOtherPeople(): void
    {
        $camille = $this->logIn('camille');
        // Marc bloque Camille : un blocage subi n'est jamais révélé
        $this->em()->persist((new Block())->setBlocker($this->user('marc'))->setBlocked($camille));
        $this->em()->persist((new Block())->setBlocker($camille)->setBlocked($this->user('zoe')));
        $this->em()->flush();

        $this->request('GET', '/api/me/export');
        $raw = (string) $this->client->getResponse()->getContent();
        $export = json_decode($raw, true);

        // Seulement le blocage posé par Camille ; celui de Marc n'apparaît pas (Marc reste visible comme abonné : le
        // blocage est créé ici directement en base, sans supprimer les relations comme le ferait l'API)
        self::assertSame(['zoe'], array_column($export['blocked'], 'username'));
        self::assertArrayNotHasKey('blockedBy', $export);
        // Ni l'email ni le contenu des autres, ni mot de passe
        foreach (['sophie@test.local', 'theo@test.local', 'nina@test.local', 'Hoodie gris', 'password', '$2y$'] as $secret) {
            self::assertStringNotContainsString($secret, $raw);
        }
    }

    public function testAnonymousGetsNothing(): void
    {
        foreach (['/api/me/stats', '/api/me/export'] as $uri) {
            $this->request('GET', $uri);
            $this->assertStatus(401);
        }
    }

    private function itemId(string $name): int
    {
        return (int) $this->em()->getRepository(Item::class)->findOneBy(['name' => $name])?->getId();
    }

    private function lastOotdId(): int
    {
        return (int) $this->em()->getRepository(Ootd::class)->findOneBy(['caption' => 'Encore la ponceuse'])?->getId();
    }
}
