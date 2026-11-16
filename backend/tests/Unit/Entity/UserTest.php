<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function birthDateProvider(): iterable
    {
        // Date de référence : 15/10/2026
        yield 'enfant de 12 ans' => ['2014-10-15', true];
        yield '18 ans demain' => ['2008-10-16', true];
        yield '18 ans aujourd\'hui' => ['2008-10-15', false];
        yield 'adulte' => ['1992-03-14', false];
        yield 'né un 29 février, 18 ans le 1er mars' => ['2008-02-29', false];
    }

    #[DataProvider('birthDateProvider')]
    public function testIsMinorAtGivenDate(string $birthDate, bool $expected): void
    {
        $user = (new User())->setBirthDate(new \DateTimeImmutable($birthDate));

        self::assertSame($expected, $user->isMinor(new \DateTimeImmutable('2026-10-15')));
    }

    public function testIsMinorDefaultsToToday(): void
    {
        self::assertTrue((new User())->setBirthDate(new \DateTimeImmutable('-17 years'))->isMinor());
        self::assertFalse((new User())->setBirthDate(new \DateTimeImmutable('-18 years'))->isMinor());
    }

    public function testUserWithoutBirthDateIsNotMinor(): void
    {
        self::assertFalse((new User())->isMinor());
    }

    // Case « administrateur » du back office : seul ROLE_ADMIN change, les autres rôles restent
    public function testSetAdminAddsOrRemovesOnlyTheAdminRole(): void
    {
        $user = (new User())->setRoles(['ROLE_BETA']);

        $user->setAdmin(true);
        self::assertTrue($user->isAdmin());
        self::assertSame(['ROLE_BETA', 'ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());

        $user->setAdmin(true);
        self::assertSame(['ROLE_BETA', 'ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());

        $user->setAdmin(false);
        self::assertFalse($user->isAdmin());
        self::assertSame(['ROLE_BETA', 'ROLE_USER'], $user->getRoles());
    }

    public function testRoleUserAloneIsNotAdmin(): void
    {
        self::assertFalse((new User())->setRoles(['ROLE_USER'])->isAdmin());
    }
}
