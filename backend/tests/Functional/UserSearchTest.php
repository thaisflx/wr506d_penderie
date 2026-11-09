<?php

namespace App\Tests\Functional;

use App\Entity\Block;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * GET /api/users?username=… : recherche par pseudo (docs/decisions.md).
 */
final class UserSearchTest extends ApiTestCase
{
    // Seules clés autorisées pour un autre utilisateur (groupe user:public) ; avatar est absent quand il est vide
    private const PUBLIC_KEYS = ['@id', '@type', 'id', 'username', 'avatar', 'bio'];
    private const SENSITIVE_KEYS = ['email', 'password', 'roles', 'birthDate', 'parent', 'places', 'isSuspended', 'children', 'items'];

    public function testFindsByPartOfUsername(): void
    {
        $this->logIn('theo');

        $result = $this->request('GET', '/api/users?username=so');

        $this->assertStatus(200);
        self::assertSame(['sophie'], $this->usernames($result));
    }

    public function testSearchIgnoresCaseAndSurroundingSpaces(): void
    {
        $this->logIn('theo');

        self::assertSame(['sophie'], $this->usernames($this->request('GET', '/api/users?username=%20SOPH%20')));
    }

    public function testResultsExposeOnlyPublicFields(): void
    {
        $this->logIn('theo');

        $result = $this->request('GET', '/api/users?username=cam');

        $camille = $result['member'][0];
        self::assertSame('camille', $camille['username']);
        self::assertSame('Seconde main, vinyles et outils de bricolage', $camille['bio']);
        self::assertSame([], array_diff(array_keys($camille), self::PUBLIC_KEYS));
        foreach (self::SENSITIVE_KEYS as $key) {
            self::assertArrayNotHasKey($key, $camille);
        }
        // Pas de lien calculé dans la recherche : il n'est donné que sur le profil
        self::assertArrayNotHasKey('viewerRelationship', $camille);
        self::assertStringNotContainsString('@test.local', (string) $this->client->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tooShortProvider(): iterable
    {
        yield 'paramètre absent' => [''];
        yield 'un caractère' => ['?username=s'];
        yield 'un caractère entouré d\'espaces' => ['?username=%20s%20'];
    }

    #[DataProvider('tooShortProvider')]
    public function testSearchNeedsAtLeastTwoCharacters(string $query): void
    {
        $this->logIn('theo');

        $response = $this->request('GET', '/api/users'.$query);

        $this->assertStatus(400);
        self::assertSame('Indique au moins 2 caractères du pseudo.', $response['detail']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function excludedProvider(): iterable
    {
        yield 'moi-même' => ['camille', 'cam'];
        yield 'compte suspendu' => ['camille', 'susp'];
        // Même pour son parent : un mineur n'apparaît jamais dans la recherche
        yield 'compte -18' => ['camille', 'lou'];
    }

    #[DataProvider('excludedProvider')]
    public function testAccountsThatAreNeverFound(string $viewer, string $search): void
    {
        $this->logIn($viewer);

        self::assertSame([], $this->usernames($this->request('GET', '/api/users?username='.$search)));
    }

    public function testAdultChildAccountIsFound(): void
    {
        $this->logIn('theo');

        self::assertSame(['sacha'], $this->usernames($this->request('GET', '/api/users?username=sach')));
    }

    public function testBlockHidesBothPeopleFromEachOther(): void
    {
        $this->block('camille', 'marc');

        $this->logIn('camille');
        self::assertSame([], $this->usernames($this->request('GET', '/api/users?username=marc')));
        $this->logIn('marc');
        self::assertSame([], $this->usernames($this->request('GET', '/api/users?username=cam')));
        // Les autres continuent de trouver Marc
        $this->logIn('zoe');
        self::assertSame(['marc'], $this->usernames($this->request('GET', '/api/users?username=marc')));
    }

    public function testWildcardsAreSearchedLiterally(): void
    {
        $this->logIn('theo');

        // Sans échappement, "__" (deux caractères quelconques) trouverait tout le monde
        self::assertSame([], $this->usernames($this->request('GET', '/api/users?username=__')));
        self::assertSame([], $this->usernames($this->request('GET', '/api/users?username=%25%25')));
    }

    public function testResultsArePaginatedByTwenty(): void
    {
        for ($i = 1; $i <= 25; ++$i) {
            $this->em()->persist((new User())->setUsername(\sprintf('testeur%02d', $i))->setBirthDate(new \DateTimeImmutable('1990-01-01')));
        }
        $this->em()->flush();
        $this->logIn('theo');

        $result = $this->request('GET', '/api/users?username=testeur');

        self::assertSame(25, $result['totalItems']);
        self::assertCount(20, $result['member']);
        self::assertSame('testeur01', $result['member'][0]['username']);
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return list<string>
     */
    private function usernames(array $result): array
    {
        $this->assertStatus(200);

        return array_column($result['member'], 'username');
    }

    private function block(string $blocker, string $blocked): void
    {
        $this->em()->persist((new Block())->setBlocker($this->user($blocker))->setBlocked($this->user($blocked)));
        $this->em()->flush();
    }
}
