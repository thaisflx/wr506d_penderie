<?php

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\CategoryCrudController;
use App\Controller\Admin\ItemCrudController;
use App\Controller\Admin\OotdCrudController;
use App\Controller\Admin\UserCrudController;
use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Ootd;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Entité gérée par chaque CRUD du back office. EasyAdmin ne lit getEntityFqcn() qu'en construisant le conteneur,
 * jamais pendant une requête : ce test est le seul à l'appeler.
 */
final class CrudEntitiesTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function crudProvider(): iterable
    {
        yield 'comptes' => [UserCrudController::class, User::class];
        yield 'looks' => [OotdCrudController::class, Ootd::class];
        yield 'objets' => [ItemCrudController::class, Item::class];
        yield 'catégories' => [CategoryCrudController::class, Category::class];
    }

    /**
     * @param class-string $controller
     * @param class-string $entity
     */
    #[DataProvider('crudProvider')]
    public function testEachCrudManagesItsEntity(string $controller, string $entity): void
    {
        self::assertSame($entity, $controller::getEntityFqcn());
    }
}
