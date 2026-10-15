<?php

namespace App\Tests\Functional;

use App\Entity\Item;
use App\Entity\Place;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels : vraies requêtes HTTP sur le kernel, base de test chargée
 * avec les fixtures du groupe test (chaque test est annulé en fin de transaction).
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Authentifie les requêtes suivantes avec un jeton JWT du compte, comme le ferait le front
     * après /api/login_check (testé à part dans LoginTest).
     */
    protected function logIn(string $username): User
    {
        $user = $this->user($username);
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        return $user;
    }

    /**
     * @param array<string, mixed>|string|null $body tableau encodé en JSON, ou contenu brut
     *
     * @return array<string, mixed>
     */
    protected function request(string $method, string $uri, array|string|null $body = null): array
    {
        $this->client->request($method, $uri, server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: \is_array($body) ? (string) json_encode($body) : $body);

        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : (array) json_decode($content, true);
    }

    protected function assertStatus(int $expected): void
    {
        self::assertSame($expected, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
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

    protected function place(string $name): Place
    {
        $place = $this->em()->getRepository(Place::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Place::class, $place);

        return $place;
    }

    protected function item(string $name): Item
    {
        $item = $this->em()->getRepository(Item::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Item::class, $item);

        return $item;
    }
}
