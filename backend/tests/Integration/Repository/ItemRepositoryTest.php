<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Item;
use App\Enum\Visibility;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * La règle « partageable » écrite en requête (back office, KPI) dit la même chose qu'Item::isShareable() pour chaque
 * objet de la base de test ; les objets montrés sont ceux d'un look publié (docs/decisions.md, « Back office »).
 */
final class ItemRepositoryTest extends KernelTestCase
{
    private ItemRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(ItemRepository::class);
    }

    public function testQueryAndMethodAgreeForEveryItem(): void
    {
        $items = $this->repository->findAll();
        $fromQuery = $this->names($this->repository->restrictToShareable($this->repository->createQueryBuilder('item'), 'item')->getQuery()->getResult());

        $fromMethod = $this->names(array_values(array_filter($items, static fn (Item $item) => $item->isShareable())));

        self::assertGreaterThan(count($fromMethod), count($items), 'Les fixtures doivent contenir des objets non partageables');
        self::assertNotEmpty($fromMethod);
        self::assertSame($fromMethod, $fromQuery);
    }

    // Seul le logement devient privé (pièces et rangements restent ouverts) : ses objets ne sont plus partageables,
    // ni pour la méthode ni pour la requête
    public function testAPrivatePlaceHidesItsItemsEvenWithOpenRoomsAndStorages(): void
    {
        $tshirt = $this->item('T-shirt blanc');
        self::assertTrue($tshirt->isShareable());
        $tshirt->getStorage()?->getRoom()?->getPlace()?->setVisibility(Visibility::Private);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $fromQuery = $this->names($this->repository->restrictToShareable($this->repository->createQueryBuilder('item'), 'item')->getQuery()->getResult());

        self::assertFalse($tshirt->isShareable());
        self::assertNotContains('T-shirt blanc', $fromQuery);
        self::assertSame($this->names(array_values(array_filter($this->repository->findAll(), static fn (Item $item) => $item->isShareable()))), $fromQuery);
    }

    public function testShownItemsAreTheShareableOnesOfPublishedLooks(): void
    {
        $shown = $this->repository->restrictToShownInPublishedLooks($this->repository->createQueryBuilder('item'), 'item')->getQuery()->getResult();

        // Brouillon de Camille : Baskets montantes ; jamais dans un look : Perceuse ; perso : Carnet intime
        self::assertSame(['Jean brut', 'Ponceuse', 'T-shirt blanc'], $this->names($shown));
        foreach ($shown as $item) {
            self::assertTrue($this->repository->isShownInPublishedLooks($item));
        }
    }

    public function testCountsThePublishedLooksOfAnItem(): void
    {
        self::assertSame(1, $this->repository->countPublishedLooks($this->item('Ponceuse')));
        // Seulement dans le brouillon de Camille
        self::assertSame(0, $this->repository->countPublishedLooks($this->item('Baskets montantes')));
        self::assertFalse($this->repository->isShownInPublishedLooks($this->item('Baskets montantes')));
    }

    public function testAnItemMadePersonalIsNoLongerShown(): void
    {
        $tshirt = $this->item('T-shirt blanc');
        $tshirt->setIsPersonal(true);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertFalse($this->repository->isShownInPublishedLooks($tshirt));
        $shown = $this->repository->restrictToShownInPublishedLooks($this->repository->createQueryBuilder('item'), 'item')->getQuery()->getResult();
        self::assertNotContains('T-shirt blanc', $this->names($shown));
    }

    private function item(string $name): Item
    {
        $item = $this->repository->findOneBy(['name' => $name]);
        self::assertInstanceOf(Item::class, $item, \sprintf('Objet "%s" absent : lancer make test-db', $name));

        return $item;
    }

    /**
     * @param list<Item> $items
     *
     * @return list<string>
     */
    private function names(array $items): array
    {
        $names = array_map(static fn (Item $item) => (string) $item->getName(), $items);
        sort($names);

        return $names;
    }
}
