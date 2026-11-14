<?php

namespace App\Tests\Integration\Entity;

use App\DataFixtures\Test\TestOotdFixtures;
use App\Entity\Item;
use App\Entity\Ootd;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Entité Ootd (lot 2) : règles de validation et suppressions en cascade, avec la base de test.
 */
final class OotdTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testNewOotdIsADraftWornToday(): void
    {
        $ootd = new Ootd();

        self::assertFalse($ootd->isPublished());
        self::assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $ootd->getWornOn()->format('Y-m-d'));
    }

    public function testDraftWithoutPhotoIsValid(): void
    {
        self::assertCount(0, $this->validator->validate($this->ootd()));
    }

    public function testPublishingWithoutPhotoIsRefused(): void
    {
        $violations = $this->validator->validate($this->ootd()->setPublishedAt(new \DateTimeImmutable()));

        self::assertCount(1, $violations);
        self::assertSame('photo', $violations[0]->getPropertyPath());
        self::assertSame('Ajoute une photo de ta tenue avant de publier.', $violations[0]->getMessage());
    }

    public function testPublishingWithPhotoIsValid(): void
    {
        $ootd = $this->ootd()->setPhoto('/uploads/ootds/1-abc.jpg')->setPublishedAt(new \DateTimeImmutable());

        self::assertCount(0, $this->validator->validate($ootd));
    }

    public function testPastDateIsAccepted(): void
    {
        self::assertCount(0, $this->validator->validate($this->ootd()->setWornOn(new \DateTimeImmutable('-2 days'))));
    }

    public function testFutureDateIsRefused(): void
    {
        $violations = $this->validator->validate($this->ootd()->setWornOn(new \DateTimeImmutable('tomorrow')));

        self::assertCount(1, $violations);
        self::assertSame('wornOn', $violations[0]->getPropertyPath());
        self::assertSame('La date du look ne peut pas être dans le futur.', $violations[0]->getMessage());
    }

    public function testCaptionIsLimitedTo500Characters(): void
    {
        self::assertCount(0, $this->validator->validate($this->ootd()->setCaption(str_repeat('a', 500))));

        $violations = $this->validator->validate($this->ootd()->setCaption(str_repeat('a', 501)));
        self::assertCount(1, $violations);
        self::assertSame('caption', $violations[0]->getPropertyPath());
    }

    public function testAnItemCanOnlyBeAddedOnce(): void
    {
        $item = $this->item('T-shirt blanc');

        $ootd = $this->ootd()->addItem($item)->addItem($item);

        self::assertCount(1, $ootd->getItems());
        $ootd->removeItem($item);
        self::assertCount(0, $ootd->getItems());
    }

    public function testDeletingAnItemRemovesItFromTheOotd(): void
    {
        $id = $this->publishedId();
        $this->em->remove($this->item('Ponceuse'));
        $this->em->flush();
        $this->em->clear();

        $ootd = $this->em->find(Ootd::class, $id);
        self::assertInstanceOf(Ootd::class, $ootd);
        self::assertSame(['T-shirt blanc'], $ootd->getItems()->map(fn (Item $item) => $item->getName())->getValues());
    }

    public function testDeletingTheOwnerDeletesTheirOotds(): void
    {
        // Zoé n'a ni objet, ni prêt, ni relation : son compte peut être supprimé directement en SQL
        $zoe = $this->user('zoe');
        $this->em->persist((new Ootd())->setOwner($zoe)->setCaption('Look de Zoé'));
        $this->em->flush();

        $this->em->getConnection()->executeStatement('DELETE FROM app_user WHERE id = ?', [$zoe->getId()]);
        $this->em->clear();

        self::assertSame(0, $this->em->getRepository(Ootd::class)->count(['caption' => 'Look de Zoé']));
    }

    public function testFixturesMatchTheRightsMatrix(): void
    {
        $repository = $this->em->getRepository(Ootd::class);
        $camille = $this->user('camille');

        self::assertCount(3, $repository->findBy(['owner' => $camille]));
        self::assertCount(1, $repository->findBy(['owner' => $camille, 'publishedAt' => null]));
        $draft = $repository->findOneBy(['owner' => $camille, 'publishedAt' => null]);
        self::assertNull($draft?->getPhoto(), 'Le brouillon des fixtures n\'a pas de photo');
        self::assertSame(['T-shirt blanc', 'Ponceuse'], $this->em->find(Ootd::class, $this->publishedId())
            ?->getItems()->map(fn (Item $item) => $item->getName())->getValues());
    }

    private function ootd(): Ootd
    {
        return (new Ootd())->setOwner($this->user('camille'));
    }

    // OOTD publié hier par Camille (TestOotdFixtures::PUBLISHED)
    private function publishedId(): int
    {
        $ootd = $this->em->getRepository(Ootd::class)->findOneBy(['caption' => 'Tenue de bricolage du samedi']);
        self::assertInstanceOf(Ootd::class, $ootd, \sprintf('OOTD %s absent : lancer make test-db', TestOotdFixtures::PUBLISHED));

        return (int) $ootd->getId();
    }

    private function user(string $username): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user, \sprintf('Compte "%s" absent : lancer make test-db', $username));

        return $user;
    }

    private function item(string $name): Item
    {
        $item = $this->em->getRepository(Item::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Item::class, $item, \sprintf('Objet "%s" absent : lancer make test-db', $name));

        return $item;
    }
}
