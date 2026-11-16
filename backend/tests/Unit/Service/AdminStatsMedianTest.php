<?php

namespace App\Tests\Unit\Service;

use App\Service\AdminStats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Médiane des objets rangés par personne (KPI du back office).
 */
final class AdminStatsMedianTest extends TestCase
{
    /**
     * @return iterable<string, array{list<int>, float}>
     */
    public static function medianProvider(): iterable
    {
        yield 'aucun compte' => [[], 0.0];
        yield 'un seul compte' => [[7], 7.0];
        yield 'nombre impair, dans le désordre' => [[14, 0, 3], 3.0];
        yield 'nombre pair : moyenne des deux du milieu' => [[0, 14, 1, 2], 1.5];
        yield 'un gros compte ne fausse pas la médiane' => [[1, 2, 2, 3, 500], 2.0];
    }

    /**
     * @param list<int> $values
     */
    #[DataProvider('medianProvider')]
    public function testMedian(array $values, float $expected): void
    {
        self::assertSame($expected, AdminStats::median($values));
    }
}
