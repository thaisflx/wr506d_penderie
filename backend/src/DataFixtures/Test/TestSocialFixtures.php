<?php

namespace App\DataFixtures\Test;

use App\Entity\Comment;
use App\Entity\Item;
use App\Entity\Look;
use App\Entity\LookItem;
use App\Entity\Reservation;
use App\Entity\Sale;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Enum\ReservationStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Looks, commentaires, prêts, ventes et liens de partage.
 */
class TestSocialFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
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
        $owner = $this->user(TestUserFixtures::OWNER);
        $friend = $this->user(TestUserFixtures::CLOSE_FRIEND);
        $childAdult = $this->user(TestUserFixtures::CHILD_ADULT);

        // --- Looks ---
        // Publié, avec un objet perso qui doit rester masqué dans le look
        $published = $this->look($manager, $owner, 'Look du dimanche', true,
            [TestItemFixtures::TSHIRT, TestItemFixtures::JEANS, TestItemFixtures::SNEAKERS, TestItemFixtures::PERSONAL_DIARY]);
        // Brouillon : visible par Camille seulement
        $this->look($manager, $owner, 'Brouillon soirée', false, [TestItemFixtures::JEANS]);

        // --- Commentaires : amie proche et compte enfant +18 ---
        $this->comment($manager, $friend, 'Trop bien ce look !', look: $published);
        $this->comment($manager, $childAdult, 'Tu me prêtes les baskets ?', item: $this->item(TestItemFixtures::SNEAKERS));

        // --- Prêts : en cours, en retard, terminé ---
        $this->loan($manager, $friend, TestItemFixtures::DRILL, '-3 days', '+4 days', ReservationStatus::Ongoing, 'Pour monter une étagère');
        $this->loan($manager, $childAdult, TestItemFixtures::SANDER, '-20 days', '-5 days', ReservationStatus::Late);
        $this->loan($manager, $friend, TestItemFixtures::ABBEY_ROAD, '-60 days', '-45 days', ReservationStatus::Completed);

        // --- Ventes : en cours et déjà vendue à une amie ---
        $manager->persist((new Sale())->setItem($this->item(TestItemFixtures::THRILLER))->setPrice('25.00'));
        $sold = (new Sale())->setItem($this->item(TestItemFixtures::COAT))->setPrice('40.00')->setSoldAt(new \DateTimeImmutable('-10 days'));
        $friend->addPurchase($sold);
        $manager->persist($sold);

        // --- Liens de partage : actif, expiré, révoqué ---
        $lookLink = (new ShareLink())->setLook($published);
        $owner->addShareLink($lookLink);
        $manager->persist($lookLink);

        $selection = (new ShareLink())
            ->addItem($this->item(TestItemFixtures::ABBEY_ROAD))
            ->addItem($this->item(TestItemFixtures::THRILLER))
            ->setExpiresAt(new \DateTimeImmutable('+30 days'));
        $owner->addShareLink($selection);
        $manager->persist($selection);

        $expired = (new ShareLink())->addItem($this->item(TestItemFixtures::TSHIRT))->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $owner->addShareLink($expired);
        $manager->persist($expired);

        $revoked = (new ShareLink())->addItem($this->item(TestItemFixtures::SNEAKERS))->setRevokedAt(new \DateTimeImmutable('-2 days'));
        $owner->addShareLink($revoked);
        $manager->persist($revoked);

        $manager->flush();
    }

    private function user(string $reference): User
    {
        return $this->getReference($reference, User::class);
    }

    private function item(string $reference): Item
    {
        return $this->getReference($reference, Item::class);
    }

    /**
     * @param list<string> $itemReferences
     */
    private function look(ObjectManager $manager, User $owner, string $name, bool $published, array $itemReferences): Look
    {
        $look = (new Look())->setName($name)->setIsPublished($published);
        $owner->addLook($look);
        $manager->persist($look);
        foreach ($itemReferences as $reference) {
            $manager->persist((new LookItem())->setLook($look)->setItem($this->item($reference)));
        }

        return $look;
    }

    private function comment(ObjectManager $manager, User $author, string $content, ?Item $item = null, ?Look $look = null): void
    {
        $comment = (new Comment())->setContent($content)->setItem($item)->setLook($look);
        $author->addComment($comment);
        $manager->persist($comment);
    }

    private function loan(ObjectManager $manager, User $borrower, string $itemReference, string $start, string $expectedEnd, ReservationStatus $status, ?string $note = null): void
    {
        $loan = (new Reservation())
            ->setItem($this->item($itemReference))
            ->setStartDate(new \DateTime($start))
            ->setExpectedEndDate(new \DateTime($expectedEnd))
            ->setStatus($status)
            ->setNote($note);
        $borrower->addReceivedReservation($loan);
        $manager->persist($loan);
    }
}
