<?php

namespace App\DataFixtures;

use App\Entity\Block;
use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Place;
use App\Entity\Item;
use App\Entity\Look;
use App\Entity\LookItem;
use App\Entity\Ootd;
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
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de test couvrant le tableau des droits.
 * Tous les comptes avec email ont le mot de passe "password".
 */
class AppFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function load(ObjectManager $manager): void
    {
        // --- Comptes ---
        $admin = $this->createUser($manager, 'admin', 'admin@penderie.test', '1985-01-01', ['ROLE_ADMIN']);
        $alice = $this->createUser($manager, 'alice', 'alice@penderie.test', '1980-04-12');
        $alice->setBio('Fan de vinyles et de vide-greniers');

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

        // --- Dates d'inscription et de dernière connexion (tableau de bord du back office) ---
        // Relatives au jour du chargement, pour que les KPI restent parlants : inscriptions étalées sur 8 semaines,
        // connexions récentes ou anciennes, des comptes jamais revenus
        $this->setDates($admin, '-60 days', '-1 hour');
        $this->setDates($alice, '-52 days', '-2 hours');
        $this->setDates($leo, '-45 days', '-12 days');
        $this->setDates($mia, '-45 days', null);
        $this->setDates($noa, '-20 days', null);
        $this->setDates($bob, '-38 days', '-1 day');
        $this->setDates($carla, '-30 days', '-3 days');
        $this->setDates($dan, '-9 days', '-25 days');
        $this->setDates($eve, '-4 days', '-4 days');
        // Comptes de démo sans contenu : ils remplissent les graphiques par semaine ; malo est suspendu (modération)
        foreach ([
            ['fanny', '-55 days', '-40 days'], ['gabin', '-33 days', '-6 days'], ['hugo', '-24 days', '-20 days'],
            ['ines', '-17 days', '-2 days'], ['jules', '-11 days', '-11 days'], ['lina', '-6 days', '-1 day'],
            ['malo', '-5 days', '-5 days'], ['nora', '-2 days', '-1 hour'],
        ] as [$username, $signedUp, $lastLogin]) {
            $member = $this->createUser($manager, $username, $username.'@penderie.test', '1998-05-10');
            $this->setDates($member, $signedUp, $lastLogin);
            $member->setIsSuspended('malo' === $username);
        }

        // --- Relations avec alice ---
        $this->createRelationship($manager, $bob, $alice, RelationshipType::CloseFriend, RelationshipStatus::Accepted);
        $this->createRelationship($manager, $bob, $alice, RelationshipType::Follower, RelationshipStatus::Accepted);
        $this->createRelationship($manager, $carla, $alice, RelationshipType::Follower, RelationshipStatus::Accepted);
        // En attente : dan est traité comme un random
        $this->createRelationship($manager, $dan, $alice, RelationshipType::Follower, RelationshipStatus::Pending);
        $this->createRelationship($manager, $alice, $bob, RelationshipType::CloseFriend, RelationshipStatus::Accepted);
        // alice suit carla : ses looks du jour arrivent dans le fil d'alice
        $this->createRelationship($manager, $alice, $carla, RelationshipType::Follower, RelationshipStatus::Accepted);
        // alice a bloqué eve : aucune relation entre elles, dans un sens comme dans l'autre
        $manager->persist((new Block())->setBlocker($alice)->setBlocked($eve));

        // --- Logement, pièces et storages d'alice ---
        $place = (new Place())->setName('Appartement Lyon')->setCity('Lyon')->setVisibility(Visibility::Followers);
        $alice->addPlace($place);
        $manager->persist($place);

        $bedroom = $this->createRoom($manager, $place, 'Chambre', Visibility::Followers);
        $living = $this->createRoom($manager, $place, 'Salon', Visibility::Followers);
        $bathroom = $this->createRoom($manager, $place, 'Salle de bain', Visibility::Private, true);
        $cellar = $this->createRoom($manager, $place, 'Cave', Visibility::CloseFriends);

        $wardrobe = $this->createStorage($manager, $bedroom, 'Armoire', Visibility::Followers);
        $drawer = $this->createStorage($manager, $bedroom, 'Tiroir de nuit', Visibility::Followers, true);
        $vinylBox = $this->createStorage($manager, $living, 'Bac à vinyles', Visibility::Followers);
        $cabinet = $this->createStorage($manager, $bathroom, 'Meuble lavabo', Visibility::Private);
        $toolbox = $this->createStorage($manager, $cellar, 'Caisse à outils', Visibility::CloseFriends);

        // Logement de leo (privé par défaut)
        $studio = (new Place())->setName('Studio étudiant')->setCity('Grenoble');
        $leo->addPlace($studio);
        $manager->persist($studio);
        $leoShelf = $this->createStorage($manager, $this->createRoom($manager, $studio, 'Pièce principale', Visibility::Private), 'Étagère', Visibility::Private);

        // --- Catégories (dans chaque storage) et objets ---
        $wardrobeTops = $this->createCategory($manager, $wardrobe, 'T-shirts', CategoryType::Top);
        $wardrobeBottoms = $this->createCategory($manager, $wardrobe, 'Pantalons', CategoryType::Bottom);
        $wardrobeShoes = $this->createCategory($manager, $wardrobe, 'Baskets', CategoryType::Shoes);
        $wardrobeMisc = $this->createCategory($manager, $wardrobe, 'Divers', CategoryType::Other);
        $drawerMisc = $this->createCategory($manager, $drawer, 'Bijoux', CategoryType::Other);
        $vinyls = $this->createCategory($manager, $vinylBox, 'Rock', CategoryType::Media);
        $vinylsPop = $this->createCategory($manager, $vinylBox, 'Pop', CategoryType::Media);
        $toiletries = $this->createCategory($manager, $cabinet, 'Toilette', CategoryType::Other);
        $tools = $this->createCategory($manager, $toolbox, 'Électroportatif', CategoryType::Other);
        $leoTops = $this->createCategory($manager, $leoShelf, 'Sweats', CategoryType::Top);

        $tshirt = $this->createItem($manager, $alice, 'T-shirt blanc', $wardrobeTops);
        $jeans = $this->createItem($manager, $alice, 'Jean brut', $wardrobeBottoms);
        // Démo de la fiche objet : attributs comme après un scan, et une photo d'exemple.
        // Photo générée pour le projet (illustration dessinée par script, sans image source), hors de
        // /uploads/items/ : supprimer la photo depuis l'application ne peut donc pas effacer le fichier.
        $tshirt
            ->setPhoto('/uploads/demo/t-shirt-blanc.jpg')
            ->setAttributes([
                'type' => 'T-shirt',
                'brand' => 'Atelier Coton',
                'color' => 'Blanc cassé',
                'size' => 'M',
                'composition' => '100 % coton bio',
                'description' => 'T-shirt col rond en jersey épais, coupe droite.',
            ]);
        $jeans->setAttributes([
            'type' => 'Jean',
            'brand' => 'Maison Indigo',
            'color' => 'Bleu brut',
            'size' => '38',
            'composition' => '98 % coton, 2 % élasthanne',
            'description' => 'Jean droit taille haute, toile brute non délavée.',
        ]);
        $sneakers = $this->createItem($manager, $alice, 'Baskets montantes', $wardrobeShoes);
        $diary = $this->createItem($manager, $alice, 'Carnet intime', $wardrobeMisc, isPersonal: true);
        $this->createItem($manager, $alice, 'Bijoux de famille', $drawerMisc);
        $this->createItem($manager, $alice, 'Trousse de toilette', $toiletries);
        $drill = $this->createItem($manager, $alice, 'Perceuse', $tools, ItemStatus::Lent);
        $abbeyRoad = $this->createItem($manager, $alice, 'Abbey Road', $vinyls);
        $thriller = $this->createItem($manager, $alice, 'Thriller', $vinylsPop, ItemStatus::ForSale);
        $this->createItem($manager, $leo, 'Hoodie gris', $leoTops);

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

        // --- Looks du jour (OOTD, lot 2) ---
        // Photos de tenues dessinées par script pour le projet (aucune image source), dans uploads/demo :
        // supprimer un look ou sa photo depuis l'application ne touche qu'à uploads/ootds, jamais à ces fichiers.
        // Seuls des objets partageables y figurent : ni perso, ni dans un rangement, une pièce ou un logement privé.

        // Vêtements de bob (logement ouvert à ses abonnés)
        $bobWardrobe = $this->createCategory($manager, $this->createStorage($manager, $this->createRoom($manager, $this->createPlace($manager, $bob, 'Maison Annecy', 'Annecy'), 'Chambre', Visibility::Followers), 'Penderie', Visibility::Followers), 'Vêtements', CategoryType::Top);
        $linenShirt = $this->createDemoItem($manager, $bob, 'Chemise en lin', $bobWardrobe, ['type' => 'Chemise', 'brand' => 'Atelier Lin', 'color' => 'Beige', 'size' => 'L', 'composition' => '100 % lin']);
        $navyTrousers = $this->createDemoItem($manager, $bob, 'Pantalon marine', $bobWardrobe, ['type' => 'Pantalon', 'brand' => 'Maison Indigo', 'color' => 'Marine', 'size' => '42', 'composition' => '97 % coton, 3 % élasthanne']);
        $loafers = $this->createDemoItem($manager, $bob, 'Mocassins cognac', $bobWardrobe, ['type' => 'Mocassins', 'brand' => 'Cordonnerie Savoie', 'color' => 'Cognac', 'size' => '43', 'composition' => 'Cuir']);
        $greenTee = $this->createDemoItem($manager, $bob, 'T-shirt kaki', $bobWardrobe, ['type' => 'T-shirt', 'brand' => 'Atelier Coton', 'color' => 'Kaki', 'size' => 'L', 'composition' => '100 % coton bio']);
        $chinos = $this->createDemoItem($manager, $bob, 'Chino sable', $bobWardrobe, ['type' => 'Pantalon', 'brand' => 'Maison Indigo', 'color' => 'Sable', 'size' => '42', 'composition' => '100 % coton']);

        // Vêtements de carla (logement ouvert à ses abonnés)
        $carlaRack = $this->createCategory($manager, $this->createStorage($manager, $this->createRoom($manager, $this->createPlace($manager, $carla, 'Appartement Paris', 'Paris'), 'Dressing', Visibility::Followers), 'Portant', Visibility::Followers), 'Vêtements', CategoryType::Top);
        $floralDress = $this->createDemoItem($manager, $carla, 'Robe fleurie', $carlaRack, ['type' => 'Robe', 'brand' => 'Jardin d\'Été', 'color' => 'Rose poudré', 'size' => 'S', 'composition' => '100 % viscose']);
        $denimJacket = $this->createDemoItem($manager, $carla, 'Veste en jean', $carlaRack, ['type' => 'Veste', 'brand' => 'Maison Indigo', 'color' => 'Bleu délavé', 'size' => 'S', 'composition' => '100 % coton']);
        $blackSkirt = $this->createDemoItem($manager, $carla, 'Jupe noire', $carlaRack, ['type' => 'Jupe', 'brand' => 'Atelier Lin', 'color' => 'Noir', 'size' => '36', 'composition' => '55 % lin, 45 % viscose']);

        $this->createOotd($manager, $bob, 'Chemise en lin pour le marché du samedi', 'bob-chemise-lin', 'today', '-1 hour', [$linenShirt, $navyTrousers, $loafers]);
        $this->createOotd($manager, $carla, 'Robe fleurie, premier jour de soleil', 'carla-robe-fleurie', '-1 day', '-1 day', [$floralDress]);
        $this->createOotd($manager, $alice, 'Le combo de toujours : t-shirt blanc et jean brut', 'alice-tshirt-jean', '-2 days', '-2 days', [$tshirt, $jeans, $sneakers]);
        $this->createOotd($manager, $carla, 'Veste en jean et jupe noire pour le bureau', 'carla-veste-jean', '-3 days', '-3 days', [$denimJacket, $blackSkirt]);
        $this->createOotd($manager, $bob, 'Dimanche tranquille', 'bob-sweat-chino', '-5 days', '-5 days', [$greenTee, $chinos]);
        $this->createOotd($manager, $alice, 'Marinière du week-end', 'alice-mariniere', '-6 days', '-6 days', [$jeans, $sneakers]);
        // Brouillon d'alice : visible par elle seule, dans l'onglet « Brouillons » de son profil
        $this->createOotd($manager, $alice, 'Idée pour samedi soir', null, 'today', null, [$jeans]);

        $manager->flush();
    }

    private function createPlace(ObjectManager $manager, User $owner, string $name, string $city): Place
    {
        $place = (new Place())->setName($name)->setCity($city)->setVisibility(Visibility::Followers);
        $owner->addPlace($place);
        $manager->persist($place);

        return $place;
    }

    /**
     * Objet avec des attributs comme après un scan (brand, size, color, composition).
     *
     * @param array<string, string> $attributes
     */
    private function createDemoItem(ObjectManager $manager, User $owner, string $name, Category $category, array $attributes): Item
    {
        return $this->createItem($manager, $owner, $name, $category)->setAttributes($attributes);
    }

    /**
     * @param list<Item> $items
     */
    private function createOotd(ObjectManager $manager, User $owner, string $caption, ?string $photo, string $wornOn, ?string $publishedAt, array $items): void
    {
        $ootd = (new Ootd())
            ->setOwner($owner)
            ->setCaption($caption)
            ->setPhoto(null !== $photo ? '/uploads/demo/'.$photo.'.jpg' : null)
            ->setWornOn(new \DateTimeImmutable($wornOn))
            ->setPublishedAt(null !== $publishedAt ? new \DateTimeImmutable($publishedAt) : null);
        foreach ($items as $item) {
            $ootd->addItem($item);
        }
        $manager->persist($ootd);
    }

    // Date d'inscription et de dernière connexion (null : jamais connecté), relatives au jour du chargement
    private function setDates(User $user, string $signedUp, ?string $lastLogin): void
    {
        $user->setCreatedAt(new \DateTimeImmutable($signedUp))
            ->setLastLoginAt(null === $lastLogin ? null : new \DateTimeImmutable($lastLogin));
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(ObjectManager $manager, string $username, ?string $email, string $birthDate, array $roles = []): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setEmail($email)
            ->setBirthDate(new \DateTimeImmutable($birthDate))
            ->setRoles($roles);
        // Seuls les comptes avec email peuvent se connecter
        if (null !== $email) {
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        }
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

    private function createCategory(ObjectManager $manager, Storage $storage, string $name, CategoryType $type): Category
    {
        $category = (new Category())->setName($name)->setType($type)->setLabelKey(CategoryLabels::keyFor($name));
        $storage->addCategory($category);
        $manager->persist($category);

        return $category;
    }

    private function createRoom(ObjectManager $manager, Place $place, string $name, Visibility $visibility, bool $isPersonal = false): Room
    {
        $room = (new Room())->setName($name)->setVisibility($visibility)->setIsPersonal($isPersonal);
        $place->addRoom($room);
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

    private function createItem(ObjectManager $manager, User $owner, string $name, Category $category, ItemStatus $status = ItemStatus::Available, bool $isPersonal = false): Item
    {
        $item = (new Item())
            ->setName($name)
            ->setCategory($category)
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
