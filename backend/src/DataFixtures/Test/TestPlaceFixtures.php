<?php

namespace App\DataFixtures\Test;

use App\Entity\Category;
use App\Entity\Place;
use App\Entity\Room;
use App\Entity\Storage;
use App\Entity\User;
use App\Enum\CategoryType;
use App\Enum\Visibility;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Hiérarchie logement > pièce > rangement > catégorie.
 * Chaque cas de visibilité du tableau des droits est représenté au moins une fois.
 */
class TestPlaceFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    // Catégories utilisées par TestItemFixtures
    public const CAT_DRESSING_TOPS = 'cat-dressing-tops';       // Followers > Followers
    public const CAT_DRESSING_SHOES = 'cat-dressing-shoes';     // Followers > Followers
    public const CAT_JEWELRY = 'cat-jewelry';                   // rangement perso dans une pièce ouverte
    public const CAT_VINYLS_ROCK = 'cat-vinyls-rock';           // Grenier > carton des vinyles
    public const CAT_VINYLS_POP = 'cat-vinyls-pop';
    public const CAT_CHRISTMAS = 'cat-christmas';               // carton jamais ouvert
    public const CAT_TOOLS = 'cat-tools';                       // CloseFriends
    public const CAT_OFFICE = 'cat-office';                     // Private
    public const CAT_COLLECTION = 'cat-collection';             // pièce perso
    public const CAT_STORAGE_UNIT = 'cat-storage-unit';         // logement privé (garde-meuble)
    public const CAT_CHILD = 'cat-child';                       // logement du compte enfant

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function getDependencies(): array
    {
        return [TestUserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $owner = $this->getReference(TestUserFixtures::OWNER, User::class);
        $childAdult = $this->getReference(TestUserFixtures::CHILD_ADULT, User::class);

        // --- Maison de Camille, ouverte aux abonnés ---
        $house = $this->place($manager, $owner, 'Maison', 'Troyes', '12 rue des Lilas', Visibility::Followers);

        $dressing = $this->room($manager, $house, 'Dressing', Visibility::Followers);
        $wardrobe = $this->storage($manager, $dressing, 'Penderie', Visibility::Followers);
        $this->category($manager, $wardrobe, 'Hauts', CategoryType::Top, self::CAT_DRESSING_TOPS);
        $this->category($manager, $wardrobe, 'Chaussures', CategoryType::Shoes, self::CAT_DRESSING_SHOES);
        // Rangement perso dans une pièce ouverte : son contenu reste invisible
        $jewelryBox = $this->storage($manager, $dressing, 'Coffre à bijoux', Visibility::Followers, isPersonal: true);
        $this->category($manager, $jewelryBox, 'Bijoux', CategoryType::Other, self::CAT_JEWELRY);

        // Grenier > carton > vinyles (cas de la maquette)
        $attic = $this->room($manager, $house, 'Grenier', Visibility::Followers);
        $vinylBox = $this->storage($manager, $attic, 'Carton « Vinyles de papa »', Visibility::Followers);
        $this->category($manager, $vinylBox, 'Rock', CategoryType::Media, self::CAT_VINYLS_ROCK);
        $this->category($manager, $vinylBox, 'Pop', CategoryType::Media, self::CAT_VINYLS_POP);
        $christmasBox = $this->storage($manager, $attic, 'Carton « Déco de Noël »', Visibility::Private);
        $this->category($manager, $christmasBox, 'Décorations', CategoryType::Other, self::CAT_CHRISTMAS);

        // Garage réservé aux amis proches (prêt d'outils)
        $garage = $this->room($manager, $house, 'Garage', Visibility::CloseFriends);
        $toolbox = $this->storage($manager, $garage, 'Caisse à outils', Visibility::CloseFriends);
        $this->category($manager, $toolbox, 'Électroportatif', CategoryType::Other, self::CAT_TOOLS);

        // Pièce privée
        $office = $this->room($manager, $house, 'Bureau', Visibility::Private);
        $shelf = $this->storage($manager, $office, 'Étagère', Visibility::Private);
        $this->category($manager, $shelf, 'Papiers', CategoryType::Other, self::CAT_OFFICE);

        // Pièce perso (collection) : invisible pour tout le monde sauf Camille
        $collectionRoom = $this->room($manager, $house, 'Collection', Visibility::Followers, isPersonal: true);
        $showcase = $this->storage($manager, $collectionRoom, 'Vitrine', Visibility::Followers);
        $this->category($manager, $showcase, 'Pièces de valeur', CategoryType::Other, self::CAT_COLLECTION);

        // --- Garde-meuble privé ---
        $storageUnit = $this->place($manager, $owner, 'Garde-meuble', 'Troyes', null, Visibility::Private);
        $box = $this->storage($manager, $this->room($manager, $storageUnit, 'Box 14', Visibility::Private), 'Cartons', Visibility::Private);
        $this->category($manager, $box, 'Vêtements d\'hiver', CategoryType::Top, self::CAT_STORAGE_UNIT);

        // --- Studio du compte enfant +18 ---
        $studio = $this->place($manager, $childAdult, 'Studio', 'Reims', null, Visibility::Private);
        $childShelf = $this->storage($manager, $this->room($manager, $studio, 'Pièce principale', Visibility::Private), 'Étagère', Visibility::Private);
        $this->category($manager, $childShelf, 'Sweats', CategoryType::Top, self::CAT_CHILD);

        $manager->flush();
    }

    private function place(ObjectManager $manager, User $owner, string $name, string $city, ?string $address, Visibility $visibility): Place
    {
        $place = (new Place())->setName($name)->setCity($city)->setVisibility($visibility);
        if (null !== $address) {
            $place->setAddress($address);
        }
        $owner->addPlace($place);
        $manager->persist($place);

        return $place;
    }

    private function room(ObjectManager $manager, Place $place, string $name, Visibility $visibility, bool $isPersonal = false): Room
    {
        $room = (new Room())->setName($name)->setVisibility($visibility)->setIsPersonal($isPersonal);
        $place->addRoom($room);
        $manager->persist($room);

        return $room;
    }

    private function storage(ObjectManager $manager, Room $room, string $name, Visibility $visibility, bool $isPersonal = false): Storage
    {
        $storage = (new Storage())->setName($name)->setVisibility($visibility)->setIsPersonal($isPersonal);
        $room->addStorage($storage);
        $manager->persist($storage);

        return $storage;
    }

    private function category(ObjectManager $manager, Storage $storage, string $name, CategoryType $type, string $reference): void
    {
        $category = (new Category())->setName($name)->setType($type);
        $storage->addCategory($category);
        $manager->persist($category);
        $this->addReference($reference, $category);
    }
}
