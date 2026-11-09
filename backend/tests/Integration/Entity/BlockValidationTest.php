<?php

namespace App\Tests\Integration\Entity;

use App\Entity\Block;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Règles de validation de l'entité Block, avec le vrai validateur et la base de test
 * (UniqueEntity interroge la base).
 */
final class BlockValidationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testBlockingSomeoneElseIsValid(): void
    {
        $block = (new Block())->setBlocker($this->user('camille'))->setBlocked($this->user('marc'));

        self::assertCount(0, $this->validator->validate($block));
    }

    public function testBlockingYourselfIsRefused(): void
    {
        $camille = $this->user('camille');

        $violations = $this->validator->validate((new Block())->setBlocker($camille)->setBlocked($camille));

        self::assertCount(1, $violations);
        self::assertSame('blocked', $violations[0]->getPropertyPath());
        self::assertSame('Tu ne peux pas te bloquer toi-même.', $violations[0]->getMessage());
    }

    public function testBlockingTwiceIsRefused(): void
    {
        $this->em->persist((new Block())->setBlocker($this->user('camille'))->setBlocked($this->user('marc')));
        $this->em->flush();

        $violations = $this->validator->validate((new Block())->setBlocker($this->user('camille'))->setBlocked($this->user('marc')));

        self::assertCount(1, $violations);
        self::assertSame('Cette personne est déjà bloquée.', $violations[0]->getMessage());
    }

    private function user(string $username): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user, \sprintf('Compte "%s" absent : lancer make test-db', $username));

        return $user;
    }
}
