<?php

namespace App\Tests\Functional\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Back office (EasyAdmin, firewall « admin » avec session) : client de test en français, comme un navigateur.
 */
abstract class AdminTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr');
    }

    // Session ouverte sur le firewall du back office (la connexion par le formulaire est testée dans AdminAccessTest)
    protected function logInAdmin(string $username = 'admin'): User
    {
        $user = $this->user($username);
        $this->client->loginUser($user, 'admin');

        return $user;
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function user(string $username): User
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user, \sprintf('Compte "%s" absent : lancer make test-db', $username));

        return $user;
    }

    protected function assertStatus(int $expected): void
    {
        self::assertSame($expected, $this->client->getResponse()->getStatusCode());
    }
}
