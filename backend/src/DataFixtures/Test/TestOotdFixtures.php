<?php

namespace App\DataFixtures\Test;

use App\Entity\Item;
use App\Entity\Ootd;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Looks du jour (OOTD) de la matrice des droits (docs/decisions.md, lot 2).
 *
 * Camille : deux OOTD publiés et un brouillon ; Sophie (son amie proche, que Camille suit) : un OOTD publié,
 * pour que le fil de Camille montre aussi quelqu'un d'autre.
 * La Ponceuse vient du Garage (amis proches) : l'OOTD la montre aussi aux abonnés, sans son emplacement.
 */
class TestOotdFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const PUBLISHED = 'ootd-published';          // Camille, publié hier : T-shirt blanc + Ponceuse
    public const PUBLISHED_OLD = 'ootd-published-old';  // Camille, publié il y a 3 jours : Jean brut
    public const DRAFT = 'ootd-draft';                  // Camille, brouillon sans photo : Baskets montantes
    public const SOPHIE = 'ootd-sophie';                // Sophie, publié aujourd'hui, sans objet

    // Photo de démo suivie par git, hors de uploads/ootds : supprimer un OOTD ne peut pas l'effacer
    private const DEMO_PHOTO = '/uploads/demo/t-shirt-blanc.jpg';

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function getDependencies(): array
    {
        return [TestItemFixtures::class, TestRelationshipFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $owner = $this->getReference(TestUserFixtures::OWNER, User::class);
        $friend = $this->getReference(TestUserFixtures::CLOSE_FRIEND, User::class);

        $this->ootd($manager, self::PUBLISHED, $owner, 'Tenue de bricolage du samedi', '-1 day', '-1 day',
            [TestItemFixtures::TSHIRT, TestItemFixtures::SANDER]);
        $this->ootd($manager, self::PUBLISHED_OLD, $owner, 'Jean brut et rien d\'autre', '-3 days', '-3 days',
            [TestItemFixtures::JEANS]);
        $this->ootd($manager, self::DRAFT, $owner, 'Idée pour la soirée', 'today', null,
            [TestItemFixtures::SNEAKERS], photo: false);
        $this->ootd($manager, self::SOPHIE, $friend, 'Prête pour le marché', 'today', '-2 hours', []);

        $manager->flush();
    }

    /**
     * @param list<string> $itemReferences
     */
    private function ootd(
        ObjectManager $manager,
        string $reference,
        User $owner,
        string $caption,
        string $wornOn,
        ?string $publishedAt,
        array $itemReferences,
        bool $photo = true,
    ): void {
        $ootd = (new Ootd())
            ->setOwner($owner)
            ->setCaption($caption)
            ->setWornOn(new \DateTimeImmutable($wornOn))
            ->setPhoto($photo ? self::DEMO_PHOTO : null)
            ->setPublishedAt(null !== $publishedAt ? new \DateTimeImmutable($publishedAt) : null);
        foreach ($itemReferences as $itemReference) {
            $ootd->addItem($this->getReference($itemReference, Item::class));
        }
        $manager->persist($ootd);
        $this->addReference($reference, $ootd);
    }
}
