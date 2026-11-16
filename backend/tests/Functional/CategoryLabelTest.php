<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Storage;

/**
 * Catégories proposées par l'application : leur clé de traduction est envoyée au front (catégorie et objets), jamais
 * écrite par un client, et effacée quand l'utilisateur renomme la catégorie (docs/decisions.md, i18n).
 */
final class CategoryLabelTest extends ApiTestCase
{
    public function testTheKeyIsSentWithTheCategoryAndItsItems(): void
    {
        $this->logIn('camille');

        $categories = array_column($this->request('GET', '/api/categories')['member'], 'labelKey', 'name');
        $items = array_column($this->request('GET', '/api/items')['member'], 'categoryLabelKey', 'name');

        self::assertSame('category.tops', $categories['Hauts']);
        self::assertSame('category.power_tools', $categories['Électroportatif']);
        self::assertSame('category.tops', $items['T-shirt blanc']);
    }

    public function testRenamingACategoryClearsItsKey(): void
    {
        $this->logIn('camille');

        $category = $this->request('PATCH', '/api/categories/'.$this->category('Hauts')->getId(), ['name' => 'Hauts et pulls']);

        $this->assertStatus(200);
        self::assertNull($category['labelKey']);
    }

    public function testAClientCannotSetAKey(): void
    {
        $this->logIn('camille');
        $hauts = $this->category('Hauts');

        $patched = $this->request('PATCH', '/api/categories/'.$hauts->getId(), ['labelKey' => 'category.jewellery']);
        $created = $this->request('POST', '/api/categories', [
            'name' => 'Casquettes',
            'type' => 'other',
            'storage' => '/api/storages/'.$hauts->getStorage()?->getId(),
            'labelKey' => 'category.tops',
        ]);

        self::assertSame('category.tops', $patched['labelKey']);
        $this->assertStatus(201);
        self::assertNull($created['labelKey']);
    }

    private function category(string $name): Category
    {
        $category = $this->em()->getRepository(Category::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Category::class, $category);
        self::assertInstanceOf(Storage::class, $category->getStorage());

        return $category;
    }
}
