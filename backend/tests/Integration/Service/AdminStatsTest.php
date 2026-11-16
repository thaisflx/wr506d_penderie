<?php

namespace App\Tests\Integration\Service;

use App\Entity\Item;
use App\Entity\Ootd;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Service\AdminStats;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Les 8 KPI du back office sur la base de test, à une date fixée (mercredi 18 novembre 2026, midi). Les dates
 * d'inscription, de connexion et de publication sont posées ici : les fixtures les calculent au chargement.
 */
final class AdminStatsTest extends KernelTestCase
{
    private const NOW = '2026-11-18 12:00:00';

    private EntityManagerInterface $em;
    private AdminStats $stats;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->stats = new AdminStats($this->em, self::getContainer()->get(ItemRepository::class), new MockClock(self::NOW));

        foreach ($this->em->getRepository(User::class)->findAll() as $user) {
            $user->setCreatedAt(new \DateTimeImmutable('2026-01-01'))->setLastLoginAt(null);
        }
        $this->user('camille')->setCreatedAt(new \DateTimeImmutable('2026-11-17 09:00'))->setLastLoginAt(new \DateTimeImmutable('2026-11-17 08:00'));
        $this->user('sophie')->setCreatedAt(new \DateTimeImmutable('2026-11-05 10:00'))->setLastLoginAt(new \DateTimeImmutable('2026-10-25 20:00'));
        // Théo, inscrit en octobre, est venu lundi : « personnes venues » ne compte pas les inscriptions
        $this->user('theo')->setCreatedAt(new \DateTimeImmutable('2026-10-01 10:00'))->setLastLoginAt(new \DateTimeImmutable('2026-11-16 20:00'));
        // Lundi à minuit pile : début de la semaine du 16, pas fin de la précédente
        $this->ootd('Tenue de bricolage du samedi')->setPublishedAt(new \DateTimeImmutable('2026-11-16 00:00:00'));
        $this->ootd('Prête pour le marché')->setPublishedAt(new \DateTimeImmutable('2026-11-18 09:00'));
        $this->ootd('Jean brut et rien d\'autre')->setPublishedAt(new \DateTimeImmutable('2026-11-10 10:00'));
        $this->em->flush();
    }

    public function testNewAccounts(): void
    {
        $signups = $this->stats->signups();

        // Camille (hier) ; Sophie (le 5) dans les 30 jours ; Théo (1er octobre) au-delà
        self::assertSame(1, $signups['last7']);
        self::assertSame(2, $signups['last30']);
        // Semaines du lundi : Théo dans celle du 28 septembre, Sophie du 2 novembre, Camille du 16 ; le reste en janvier
        self::assertSame($this->weeks([0 => 1, 5 => 1, 7 => 1]), $this->counts($signups['weeks']));
        self::assertEquals(new \DateTimeImmutable('2026-09-28'), $signups['weeks'][0]['start']);
        self::assertEquals(new \DateTimeImmutable('2026-11-16'), $signups['weeks'][7]['start']);
    }

    public function testPeopleWhoCame(): void
    {
        $active = $this->stats->active();

        // Camille (hier) et Théo (lundi) ; Sophie (25 octobre) dans les 30 jours
        self::assertSame(2, $active['last7']);
        self::assertSame(3, $active['last30']);
        self::assertSame($this->em->getRepository(User::class)->count([]), $active['total']);
    }

    public function testPublishedLooksPerWeekWithoutDrafts(): void
    {
        $looks = $this->stats->looks();

        self::assertSame(3, $looks['total']);
        self::assertSame($this->weeks([6 => 1, 7 => 2]), $this->counts($looks['weeks']));
    }

    public function testItemsPerPerson(): void
    {
        $counts = array_map(static fn (User $user) => $user->getItems()->count(), $this->em->getRepository(User::class)->findAll());

        $perUser = $this->stats->itemsPerUser();

        self::assertSame(AdminStats::median($counts), $perUser['median']);
        self::assertSame(round(array_sum($counts) / \count($counts), 1), $perUser['average']);
        self::assertGreaterThan($perUser['median'], $perUser['average'], 'Camille et ses 14 objets tirent la moyenne vers le haut, pas la médiane');
    }

    public function testShareShownMatchesTheRuleOfEachItem(): void
    {
        $items = $this->em->getRepository(Item::class)->findAll();
        $shareable = \count(array_filter($items, static fn (Item $item) => $item->isShareable()));

        $share = $this->stats->shareable();

        self::assertSame(['shareable' => $shareable, 'total' => \count($items), 'percent' => (int) round(100 * $shareable / \count($items))], $share);
        self::assertLessThan(100, $share['percent']);
    }

    public function testAcceptedFriendRequests(): void
    {
        // Acceptées : Sophie (abonnée et amie proche), Théo, Marc vers Camille ; Camille vers Sophie. En attente : Nina
        self::assertSame(['accepted' => 5, 'pending' => 1, 'percent' => 83], $this->stats->relationships());
    }

    public function testItemsByTypeCoversEveryTypeAndEveryItem(): void
    {
        $byType = $this->stats->itemsByType();

        self::assertSame(['top', 'bottom', 'shoes', 'media', 'other'], array_keys($byType));
        self::assertSame($this->em->getRepository(Item::class)->count([]), array_sum($byType));
        self::assertGreaterThan(0, $byType['media'], 'Vinyles du Grenier');
    }

    public function testSuspendedAccountsAndEverythingTogether(): void
    {
        $all = $this->stats->all();

        self::assertSame(1, $all['suspended']);
        self::assertSame(['signups', 'active', 'looks', 'itemsPerUser', 'shareable', 'relationships', 'itemsByType', 'suspended'], array_keys($all));
    }

    /**
     * @param array<int, int> $nonZero rang de la semaine (0 = la plus ancienne) => nombre
     *
     * @return list<int>
     */
    private function weeks(array $nonZero): array
    {
        return array_replace(array_fill(0, AdminStats::WEEKS, 0), $nonZero);
    }

    /**
     * @param list<array{start: \DateTimeImmutable, count: int}> $weeks
     *
     * @return list<int>
     */
    private function counts(array $weeks): array
    {
        return array_column($weeks, 'count');
    }

    private function user(string $username): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function ootd(string $caption): Ootd
    {
        $ootd = $this->em->getRepository(Ootd::class)->findOneBy(['caption' => $caption]);
        self::assertInstanceOf(Ootd::class, $ootd);

        return $ootd;
    }
}
