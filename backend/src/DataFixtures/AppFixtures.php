<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Home;
use App\Entity\Item;
use App\Entity\Look;
use App\Entity\LookItem;
use App\Entity\Relationship;
use App\Entity\Reservation;
use App\Entity\Room;
use App\Entity\Sale;
use App\Entity\ShareLink;
use App\Entity\Storage;
use App\Entity\User;
use App\Enum\CategoryType;
use App\Enum\ItemStatus;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use App\Enum\ReservationStatus;
use App\Enum\Visibility;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de test couvrant le tableau des droits.
 * Tous les comptes avec email ont le mot de passe "password".
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // --- Comptes ---
        $admin = $this->createUser($manager, 'admin', 'admin@penderie.test', '1985-01-01', ['ROLE_ADMIN']);
        $alice = $this->createUser($manager, 'alice', 'alice@penderie.test', '1980-04-12');

        // Enfant +18 avec son propre compte
        $leo = $this->createUser($manager, 'leo', 'leo@penderie.test', '2004-09-03');
        $alice->addChild($leo);

        // Enfant -18 créé et géré par le parent : pas d'email ni de mot de passe
        $mia = $this->createUser($manager, 'mia', null, (new \DateTimeImmutable('-12 years'))->format('Y-m-d'));
        $alice->addChild($mia);

        // Un compte enfant peut lui-même avoir des comptes enfants
        $noa = $this->createUser($manager, 'noa', null, (new \DateTimeImmutable('-2 years'))->format('Y-m-d'));
        $leo->addChild($noa);

        $bob = $this->createUser($manager, 'bob', 'bob@penderie.test', '1982-06-20');
        $carla = $this->createUser($manager, 'carla', 'carla@penderie.test', '1995-11-02');
        $dan = $this->createUser($manager, 'dan', 'dan@penderie.test', '1990-02-14');
        $eve = $this->createUser($manager, 'eve', 'eve@penderie.test', '1993-07-30');

        // --- Relations avec alice ---
        $this->createRelationship($manager, $bob, $alice, RelationshipType::CloseFriend, RelationshipStatus::Accepted);
        $this->createRelationship($manager, $bob, $alice, RelationshipType::Follower, RelationshipStatus::Accepted);
        $this->createRelationship($manager, $carla, $alice, RelationshipType::Follower, RelationshipStatus::Accepted);
        // En attente : dan est traité comme un random
        $this->createRelationship($manager, $dan, $alice, RelationshipType::Follower, RelationshipStatus::Pending);
        $this->createRelationship($manager, $eve, $alice, RelationshipType::Follower, RelationshipStatus::Blocked);
        $this->createRelationship($manager, $alice, $bob, RelationshipType::CloseFriend, RelationshipStatus::Accepted);

        // --- Catégories ---
        $top = $this->createCategory($manager, 'T-shirts', CategoryType::Top);
        $bottom = $this->createCategory($manager, 'Pantalons', CategoryType::Bottom);
        $shoes = $this->createCategory($manager, 'Baskets', CategoryType::Shoes);
        $vinyl = $this->createCategory($manager, 'Vinyles', CategoryType::Media);
        $tools = $this->createCategory($manager, 'Outils', CategoryType::Other);

        // --- Logement, pièces et storages d'alice ---
        $flat = (new Home())->setName('Appartement Lyon')->setCity('Lyon')->setVisibility(Visibility::Followers);
        $alice->addHome($flat);
        $manager->persist($flat);

        $bedroom = $this->createRoom($manager, $flat, 'Chambre', Visibility::Followers);
        $living = $this->createRoom($manager, $flat, 'Salon', Visibility::Followers);
        $bathroom = $this->createRoom($manager, $flat, 'Salle de bain', Visibility::Private, true);
        $cellar = $this->createRoom($manager, $flat, 'Cave', Visibility::CloseFriends);

        $wardrobe = $this->createStorage($manager, $bedroom, 'Armoire', Visibility::Followers);
        $drawer = $this->createStorage($manager, $bedroom, 'Tiroir de nuit', Visibility::Followers, true);
        $vinylBox = $this->createStorage($manager, $living, 'Bac à vinyles', Visibility::Followers);
        $cabinet = $this->createStorage($manager, $bathroom, 'Meuble lavabo', Visibility::Private);
        $toolbox = $this->createStorage($manager, $cellar, 'Caisse à outils', Visibility::CloseFriends);

        // Logement de leo (privé par défaut)
        $studio = (new Home())->setName('Studio étudiant')->setCity('Grenoble');
        $leo->addHome($studio);
        $manager->persist($studio);
        $leoShelf = $this->createStorage($manager, $this->createRoom($manager, $studio, 'Pièce principale', Visibility::Private), 'Étagère', Visibility::Private);

        // --- Objets ---
        $tshirt = $this->createItem($manager, $alice, 'T-shirt blanc', $top, $wardrobe);
        $jeans = $this->createItem($manager, $alice, 'Jean brut', $bottom, $wardrobe);
        $sneakers = $this->createItem($manager, $alice, 'Baskets montantes', $shoes, $wardrobe);
        $diary = $this->createItem($manager, $alice, 'Carnet intime', $tools, $wardrobe, isPersonal: true);
        $this->createItem($manager, $alice, 'Bijoux de famille', $tools, $drawer);
        $this->createItem($manager, $alice, 'Trousse de toilette', $tools, $cabinet);
        $drill = $this->createItem($manager, $alice, 'Perceuse', $tools, $toolbox, ItemStatus::Lent);
        $abbeyRoad = $this->createItem($manager, $alice, 'Abbey Road', $vinyl, $vinylBox);
        $thriller = $this->createItem($manager, $alice, 'Thriller', $vinyl, $vinylBox, ItemStatus::ForSale);
        $this->createItem($manager, $leo, 'Hoodie gris', $top, $leoShelf);

        // --- Look publié (contient un objet perso qui restera masqué) ---
        $look = (new Look())->setName('Look du dimanche')->setDescription('Simple et confortable')->setIsPublished(true);
        $alice->addLook($look);
        $manager->persist($look);
        foreach ([$tshirt, $jeans, $sneakers, $diary] as $item) {
            $manager->persist((new LookItem())->setLook($look)->setItem($item));
        }

        $draft = (new Look())->setName('Brouillon soirée');
        $alice->addLook($draft);
        $manager->persist($draft);
        $manager->persist((new LookItem())->setLook($draft)->setItem($jeans));

        // --- Commentaires (amis proches et enfants +18) ---
        $this->createComment($manager, $bob, 'Super look !', look: $look);
        $this->createComment($manager, $leo, 'Je te l\'emprunte quand ?', item: $sneakers);

        // --- Prêt (ami proche) et vente ---
        $loan = (new Reservation())
            ->setItem($drill)
            ->setStartDate(new \DateTime('-3 days'))
            ->setExpectedEndDate(new \DateTime('+4 days'))
            ->setStatus(ReservationStatus::Ongoing)
            ->setNote('Pour monter une étagère');
        $bob->addReceivedReservation($loan);
        $manager->persist($loan);

        $manager->persist((new Sale())->setItem($thriller)->setPrice('25.00'));

        // --- Liens de partage (look ou sélection d'objets, jamais une pièce) ---
        $lookLink = (new ShareLink())->setLook($look);
        $alice->addShareLink($lookLink);
        $manager->persist($lookLink);

        $selectionLink = (new ShareLink())
            ->addItem($abbeyRoad)
            ->addItem($thriller)
            ->setExpiresAt(new \DateTimeImmutable('+30 days'));
        $alice->addShareLink($selectionLink);
        $manager->persist($selectionLink);

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(ObjectManager $manager, string $username, ?string $email, string $birthDate, array $roles = []): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setEmail($email)
            // Hash bcrypt compatible avec le hasher "auto" de Symfony (security-bundle pas encore installé)
            ->setPassword(null !== $email ? password_hash('password', PASSWORD_BCRYPT) : null)
            ->setBirthDate(new \DateTimeImmutable($birthDate))
            ->setRoles($roles);
        $manager->persist($user);

        return $user;
    }

    private function createRelationship(ObjectManager $manager, User $requester, User $addressee, RelationshipType $type, RelationshipStatus $status): void
    {
        $relationship = (new Relationship())->setType($type)->setStatus($status);
        $requester->addSentRelationship($relationship);
        $addressee->addReceivedRelationship($relationship);
        $manager->persist($relationship);
    }

    private function createCategory(ObjectManager $manager, string $name, CategoryType $type): Category
    {
        $category = (new Category())->setName($name)->setType($type);
        $manager->persist($category);

        return $category;
    }

    private function createRoom(ObjectManager $manager, Home $home, string $name, Visibility $visibility, bool $isPersonal = false): Room
    {
        $room = (new Room())->setName($name)->setVisibility($visibility)->setIsPersonal($isPersonal);
        $home->addRoom($room);
        $manager->persist($room);

        return $room;
    }

    private function createStorage(ObjectManager $manager, Room $room, string $name, Visibility $visibility, bool $isPersonal = false): Storage
    {
        $storage = (new Storage())->setName($name)->setVisibility($visibility)->setIsPersonal($isPersonal);
        $room->addStorage($storage);
        $manager->persist($storage);

        return $storage;
    }

    private function createItem(ObjectManager $manager, User $owner, string $name, Category $category, Storage $storage, ItemStatus $status = ItemStatus::Available, bool $isPersonal = false): Item
    {
        $item = (new Item())
            ->setName($name)
            ->setCategory($category)
            ->setStorage($storage)
            ->setStatus($status)
            ->setIsPersonal($isPersonal);
        $owner->addItem($item);
        $manager->persist($item);

        return $item;
    }

    private function createComment(ObjectManager $manager, User $author, string $content, ?Item $item = null, ?Look $look = null): void
    {
        $comment = (new Comment())->setContent($content)->setItem($item)->setLook($look);
        $author->addComment($comment);
        $manager->persist($comment);
    }
}
