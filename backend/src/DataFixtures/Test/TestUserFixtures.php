<?php

namespace App\DataFixtures\Test;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Campagne de test : un compte par colonne du tableau des droits.
 * Mot de passe de tous les comptes qui se connectent : "password".
 */
class TestUserFixtures extends Fixture implements FixtureGroupInterface
{
    public const OWNER = 'user-owner';           // Camille : propriétaire
    public const CHILD_ADULT = 'user-child-adult'; // Sacha : compte enfant +18
    public const CHILD_MINOR = 'user-child-minor'; // Lou : compte enfant -18 (sans connexion)
    public const CLOSE_FRIEND = 'user-close-friend'; // Sophie : amie proche acceptée
    public const FOLLOWER = 'user-follower';     // Théo : abonné accepté
    public const PENDING = 'user-pending';       // Nina : demande en attente
    public const BLOCKED = 'user-blocked';       // Marc : bloqué
    public const RANDOM = 'user-random';         // Zoé : aucune relation
    public const SUSPENDED = 'user-suspended';   // Compte suspendu par la modération
    public const ADMIN = 'user-admin';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function load(ObjectManager $manager): void
    {
        $owner = $this->createUser($manager, self::OWNER, 'camille', 'camille@test.local', '1992-03-14');
        $owner->setBio('Seconde main, vinyles et outils de bricolage');

        $childAdult = $this->createUser($manager, self::CHILD_ADULT, 'sacha', 'sacha@test.local', '2005-05-20');
        $owner->addChild($childAdult);

        // Mineur : pas d'email ni de mot de passe, le compte est géré par le parent
        $childMinor = $this->createUser($manager, self::CHILD_MINOR, 'lou', null, (new \DateTimeImmutable('-12 years'))->format('Y-m-d'));
        $owner->addChild($childMinor);

        $this->createUser($manager, self::CLOSE_FRIEND, 'sophie', 'sophie@test.local', '1991-08-02');
        $this->createUser($manager, self::FOLLOWER, 'theo', 'theo@test.local', '1998-12-11');
        $this->createUser($manager, self::PENDING, 'nina', 'nina@test.local', '1995-01-27');
        $this->createUser($manager, self::BLOCKED, 'marc', 'marc@test.local', '1988-06-09');
        $this->createUser($manager, self::RANDOM, 'zoe', 'zoe@test.local', '2000-10-05');

        $suspended = $this->createUser($manager, self::SUSPENDED, 'suspendu', 'suspendu@test.local', '1990-01-01');
        $suspended->setIsSuspended(true);

        $this->createUser($manager, self::ADMIN, 'admin', 'admin@test.local', '1985-01-01', ['ROLE_ADMIN']);

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(ObjectManager $manager, string $reference, string $username, ?string $email, string $birthDate, array $roles = []): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setEmail($email)
            ->setBirthDate(new \DateTimeImmutable($birthDate))
            ->setRoles($roles);
        if (null !== $email) {
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        }
        $manager->persist($user);
        $this->addReference($reference, $user);

        return $user;
    }
}
