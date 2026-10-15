<?php

namespace App\DataFixtures\Test;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\ItemStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Objets : un cas par statut, par niveau de visibilité et par cas « perso ».
 */
class TestItemFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const TSHIRT = 'item-tshirt';
    public const JEANS = 'item-jeans';
    public const SNEAKERS = 'item-sneakers';
    public const PERSONAL_DIARY = 'item-personal-diary';
    public const ABBEY_ROAD = 'item-abbey-road';
    public const THRILLER = 'item-thriller';
    public const DRILL = 'item-drill';
    public const SANDER = 'item-sander';
    public const COAT = 'item-coat';

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function getDependencies(): array
    {
        return [TestPlaceFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $owner = $this->getReference(TestUserFixtures::OWNER, User::class);
        $childAdult = $this->getReference(TestUserFixtures::CHILD_ADULT, User::class);

        // Visibles par les abonnés (pièce et rangement ouverts)
        $this->item($manager, $owner, self::TSHIRT, 'T-shirt blanc', TestPlaceFixtures::CAT_DRESSING_TOPS,
            barcode: '3614273046215', attributes: ['marque' => 'Petit Bateau', 'taille' => 'M', 'couleur' => 'blanc', 'matiere' => '100 % coton']);
        $this->item($manager, $owner, self::JEANS, 'Jean brut', TestPlaceFixtures::CAT_DRESSING_TOPS, ItemStatus::InLaundry);
        $this->item($manager, $owner, self::SNEAKERS, 'Baskets montantes', TestPlaceFixtures::CAT_DRESSING_SHOES, ItemStatus::Reserved);
        // Objet perso dans un rangement ouvert : invisible pour tous sauf Camille
        $this->item($manager, $owner, self::PERSONAL_DIARY, 'Carnet intime', TestPlaceFixtures::CAT_DRESSING_TOPS, isPersonal: true);

        // Grenier > carton des vinyles
        $this->item($manager, $owner, self::ABBEY_ROAD, 'Abbey Road', TestPlaceFixtures::CAT_VINYLS_ROCK,
            attributes: ['artiste' => 'The Beatles', 'annee' => 1969]);
        $this->item($manager, $owner, null, 'Led Zeppelin IV', TestPlaceFixtures::CAT_VINYLS_ROCK);
        $this->item($manager, $owner, self::THRILLER, 'Thriller', TestPlaceFixtures::CAT_VINYLS_POP, ItemStatus::ForSale);
        $this->item($manager, $owner, null, 'Guirlande lumineuse', TestPlaceFixtures::CAT_CHRISTMAS);

        // Rangement perso (coffre à bijoux) et pièce perso (collection)
        $this->item($manager, $owner, null, 'Bague de grand-mère', TestPlaceFixtures::CAT_JEWELRY);
        $this->item($manager, $owner, null, 'Montre de collection', TestPlaceFixtures::CAT_COLLECTION);

        // Amis proches uniquement (garage)
        $this->item($manager, $owner, self::DRILL, 'Perceuse', TestPlaceFixtures::CAT_TOOLS, ItemStatus::Lent);
        $this->item($manager, $owner, self::SANDER, 'Ponceuse', TestPlaceFixtures::CAT_TOOLS);

        // Privés (bureau, garde-meuble)
        $this->item($manager, $owner, null, 'Contrat de location', TestPlaceFixtures::CAT_OFFICE);
        $this->item($manager, $owner, self::COAT, 'Manteau en laine', TestPlaceFixtures::CAT_STORAGE_UNIT);

        // Objet du compte enfant +18
        $this->item($manager, $childAdult, null, 'Hoodie gris', TestPlaceFixtures::CAT_CHILD);

        $manager->flush();
    }

    /**
     * @param array<string, mixed>|null $attributes
     */
    private function item(
        ObjectManager $manager,
        User $owner,
        ?string $reference,
        string $name,
        string $categoryReference,
        ItemStatus $status = ItemStatus::Available,
        bool $isPersonal = false,
        ?string $barcode = null,
        ?array $attributes = null,
    ): void {
        $item = (new Item())
            ->setName($name)
            ->setCategory($this->getReference($categoryReference, Category::class))
            ->setStatus($status)
            ->setIsPersonal($isPersonal)
            ->setBarcode($barcode)
            ->setAttributes($attributes);
        $owner->addItem($item);
        $manager->persist($item);

        if (null !== $reference) {
            $this->addReference($reference, $item);
        }
    }
}
