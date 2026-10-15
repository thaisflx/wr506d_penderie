<?php

namespace App\Tests\Unit\Entity;

use App\Entity\ShareLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShareLinkTest extends TestCase
{
    private const NOW = '2026-10-15 12:00:00';

    /**
     * @return iterable<string, array{?string, ?string, bool}>
     */
    public static function linkProvider(): iterable
    {
        // [expiresAt, revokedAt, actif ?]
        yield 'sans date limite' => [null, null, true];
        yield 'expire dans 30 jours' => ['2026-11-14 12:00:00', null, true];
        yield 'expiré hier' => ['2026-10-14 12:00:00', null, false];
        yield 'expire à l\'instant même' => [self::NOW, null, false];
        yield 'révoqué' => [null, '2026-10-13 12:00:00', false];
        yield 'révoqué avant sa date limite' => ['2026-11-14 12:00:00', '2026-10-13 12:00:00', false];
    }

    #[DataProvider('linkProvider')]
    public function testIsActiveAtGivenDate(?string $expiresAt, ?string $revokedAt, bool $expected): void
    {
        $link = (new ShareLink())
            ->setExpiresAt(null !== $expiresAt ? new \DateTimeImmutable($expiresAt) : null)
            ->setRevokedAt(null !== $revokedAt ? new \DateTimeImmutable($revokedAt) : null);

        self::assertSame($expected, $link->isActive(new \DateTimeImmutable(self::NOW)));
    }

    public function testIsActiveDefaultsToNow(): void
    {
        self::assertTrue((new ShareLink())->setExpiresAt(new \DateTimeImmutable('+1 hour'))->isActive());
        self::assertFalse((new ShareLink())->setExpiresAt(new \DateTimeImmutable('-1 hour'))->isActive());
    }
}
