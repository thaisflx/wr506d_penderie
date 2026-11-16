<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use PHPUnit\Framework\TestCase;

/**
 * Clé de traduction d'une catégorie proposée par l'application : gardée tant que la catégorie garde son nom,
 * effacée dès que l'utilisateur la renomme (docs/decisions.md, « Internationalisation »).
 */
final class CategoryTest extends TestCase
{
    public function testRenamingClearsTheLabelKey(): void
    {
        $category = (new Category())->setName('Hauts')->setLabelKey('category.tops');

        $category->setName('Hauts et pulls');

        self::assertNull($category->getLabelKey());
        self::assertSame('Hauts et pulls', $category->getName());
    }

    public function testTheSameNameKeepsTheLabelKey(): void
    {
        $category = (new Category())->setName('Hauts')->setLabelKey('category.tops');

        $category->setName('Hauts');

        self::assertSame('category.tops', $category->getLabelKey());
    }

    public function testANewCategoryHasNoLabelKey(): void
    {
        self::assertNull((new Category())->setName('Mes casquettes')->getLabelKey());
    }
}
