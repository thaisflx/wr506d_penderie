<?php

namespace App\Tests\Integration\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use App\Doctrine\CurrentOwnerExtension;
use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Look;
use App\Entity\Place;
use App\Entity\Room;
use App\Entity\Storage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * L'extension est appliquée à de vraies requêtes sur la base de test, avec le vrai service Security :
 * chaque niveau de la hiérarchie ne renvoie que le contenu de l'utilisateur connecté.
 */
final class CurrentOwnerExtensionTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CurrentOwnerExtension $extension;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->extension = self::getContainer()->get(CurrentOwnerExtension::class);
    }

    /**
     * @return iterable<string, array{class-string, list<string>}>
     */
    public static function camilleProvider(): iterable
    {
        yield 'logements' => [Place::class, ['Garde-meuble', 'Maison']];
        yield 'pièces' => [Room::class, ['Box 14', 'Bureau', 'Collection', 'Dressing', 'Garage', 'Grenier']];
        yield 'rangements' => [Storage::class, ['Caisse à outils', 'Carton « Déco de Noël »', 'Carton « Vinyles de papa »', 'Cartons', 'Coffre à bijoux', 'Penderie', 'Vitrine', 'Étagère']];
        yield 'catégories' => [Category::class, ['Bijoux', 'Chaussures', 'Décorations', 'Hauts', 'Papiers', 'Pièces de valeur', 'Pop', 'Rock', 'Vêtements d\'hiver', 'Électroportatif']];
        yield 'objets' => [Item::class, ['Abbey Road', 'Bague de grand-mère', 'Baskets montantes', 'Carnet intime', 'Contrat de location', 'Guirlande lumineuse', 'Jean brut', 'Led Zeppelin IV', 'Manteau en laine', 'Montre de collection', 'Perceuse', 'Ponceuse', 'T-shirt blanc', 'Thriller']];
    }

    /**
     * Camille voit toute sa hiérarchie, y compris ce qui est perso ou privé,
     * mais pas le studio de son compte enfant Sacha.
     *
     * @param class-string $class
     * @param list<string> $expected
     */
    #[DataProvider('camilleProvider')]
    public function testOwnerSeesOnlyOwnContent(string $class, array $expected): void
    {
        $this->logIn('camille');

        self::assertSame($expected, $this->collectionNames($class));
    }

    /**
     * @return iterable<string, array{class-string, list<string>}>
     */
    public static function sachaProvider(): iterable
    {
        yield 'logements' => [Place::class, ['Studio']];
        yield 'pièces' => [Room::class, ['Pièce principale']];
        yield 'rangements' => [Storage::class, ['Étagère']];
        yield 'catégories' => [Category::class, ['Sweats']];
        yield 'objets' => [Item::class, ['Hoodie gris']];
    }

    /**
     * @param class-string $class
     * @param list<string> $expected
     */
    #[DataProvider('sachaProvider')]
    public function testChildAccountSeesOnlyOwnContent(string $class, array $expected): void
    {
        $this->logIn('sacha');

        self::assertSame($expected, $this->collectionNames($class));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function hierarchyProvider(): iterable
    {
        foreach ([Place::class, Room::class, Storage::class, Category::class, Item::class] as $class) {
            yield (new \ReflectionClass($class))->getShortName() => [$class];
        }
    }

    /**
     * Théo est abonné de Camille mais ne possède rien : pour l'instant il ne voit rien
     * (les Voters ouvriront l'accès aux abonnés plus tard).
     *
     * @param class-string $class
     */
    #[DataProvider('hierarchyProvider')]
    public function testFollowerWithoutContentSeesNothing(string $class): void
    {
        $this->logIn('theo');

        self::assertSame([], $this->collectionNames($class));
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('hierarchyProvider')]
    public function testAnonymousSeesNothing(string $class): void
    {
        self::assertSame([], $this->collectionNames($class));
    }

    public function testItemQueryReturnsOwnPlaceOnly(): void
    {
        $house = $this->em->getRepository(Place::class)->findOneBy(['name' => 'Maison']);
        self::assertInstanceOf(Place::class, $house);

        $this->logIn('camille');
        self::assertSame($house, $this->findOne(Place::class, (int) $house->getId()));

        $this->logIn('theo');
        self::assertNull($this->findOne(Place::class, (int) $house->getId()));
    }

    public function testItemQueryFollowsJoinsForNestedResource(): void
    {
        $tools = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Électroportatif']);
        self::assertInstanceOf(Category::class, $tools);

        $this->logIn('camille');
        self::assertSame($tools, $this->findOne(Category::class, (int) $tools->getId()));

        $this->logIn('sophie');
        self::assertNull($this->findOne(Category::class, (int) $tools->getId()));
    }

    public function testResourcesOutsideHierarchyAreNotFiltered(): void
    {
        // Théo ne possède aucun look, mais l'extension ne s'applique pas aux looks
        $this->logIn('theo');

        self::assertCount(2, $this->collectionNames(Look::class));
    }

    private function logIn(string $username): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user, \sprintf('Compte "%s" absent : lancer make test-db', $username));

        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    private function collectionNames(string $class): array
    {
        $qb = $this->em->getRepository($class)->createQueryBuilder('o')->select('o.name');
        $this->extension->applyToCollection($qb, new QueryNameGenerator(), $class);

        // Tri en PHP (octets) plutôt qu'en SQL : le résultat ne dépend pas de la collation PostgreSQL
        $names = array_column($qb->getQuery()->getArrayResult(), 'name');
        sort($names, \SORT_STRING);

        return $names;
    }

    /**
     * @param class-string $class
     */
    private function findOne(string $class, int $id): ?object
    {
        $qb = $this->em->getRepository($class)->createQueryBuilder('o')->andWhere('o.id = :id')->setParameter('id', $id);
        $this->extension->applyToItem($qb, new QueryNameGenerator(), $class, ['id' => $id]);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
