<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Ootd;
use App\Entity\Relationship;
use App\Entity\User;
use App\Enum\CategoryType;
use App\Enum\RelationshipStatus;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Les 8 KPI du tableau de bord du back office (docs/decisions.md, « KPI du tableau de bord »). Uniquement des
 * chiffres globaux : aucun détail d'un dressing, qui reste privé. Horloge injectée : les tests fixent « maintenant ».
 */
final class AdminStats
{
    // Nombre de semaines des petits graphiques (inscriptions, looks publiés)
    public const WEEKS = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemRepository $items,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{
     *     signups: array{last7: int, last30: int, weeks: list<array{start: \DateTimeImmutable, count: int}>},
     *     active: array{last7: int, last30: int, total: int},
     *     looks: array{weeks: list<array{start: \DateTimeImmutable, count: int}>, total: int},
     *     itemsPerUser: array{median: float, average: float},
     *     shareable: array{shareable: int, total: int, percent: int},
     *     relationships: array{accepted: int, pending: int, percent: int},
     *     itemsByType: array<string, int>,
     *     suspended: int,
     * }
     */
    public function all(): array
    {
        return [
            'signups' => $this->signups(),
            'active' => $this->active(),
            'looks' => $this->looks(),
            'itemsPerUser' => $this->itemsPerUser(),
            'shareable' => $this->shareable(),
            'relationships' => $this->relationships(),
            'itemsByType' => $this->itemsByType(),
            'suspended' => $this->entityManager->getRepository(User::class)->count(['isSuspended' => true]),
        ];
    }

    /**
     * Nouveaux comptes : 7 et 30 derniers jours, et par semaine.
     *
     * @return array{last7: int, last30: int, weeks: list<array{start: \DateTimeImmutable, count: int}>}
     */
    public function signups(): array
    {
        $dates = $this->dates(User::class, 'createdAt');

        return [
            'last7' => $this->countSince($dates, '-7 days'),
            'last30' => $this->countSince($dates, '-30 days'),
            'weeks' => $this->perWeek($dates),
        ];
    }

    /**
     * Personnes venues (dernière connexion) : 7 et 30 derniers jours, sur le nombre total de comptes.
     *
     * @return array{last7: int, last30: int, total: int}
     */
    public function active(): array
    {
        $dates = $this->dates(User::class, 'lastLoginAt');

        return [
            'last7' => $this->countSince($dates, '-7 days'),
            'last30' => $this->countSince($dates, '-30 days'),
            'total' => $this->entityManager->getRepository(User::class)->count([]),
        ];
    }

    /**
     * Looks publiés par semaine (les brouillons ne comptent pas).
     *
     * @return array{weeks: list<array{start: \DateTimeImmutable, count: int}>, total: int}
     */
    public function looks(): array
    {
        $dates = $this->dates(Ootd::class, 'publishedAt');

        return ['weeks' => $this->perWeek($dates), 'total' => \count($dates)];
    }

    /**
     * Objets rangés par personne : médiane (pas faussée par quelques gros comptes) et moyenne, comptes sans objet compris.
     *
     * @return array{median: float, average: float}
     */
    public function itemsPerUser(): array
    {
        $counts = array_map('intval', $this->entityManager->createQueryBuilder()
            ->select('COUNT(item.id)')
            ->from(User::class, 'user')
            ->leftJoin('user.items', 'item')
            ->groupBy('user.id')
            ->getQuery()
            ->getSingleColumnResult());

        return ['median' => self::median($counts), 'average' => [] === $counts ? 0.0 : round(array_sum($counts) / \count($counts), 1)];
    }

    /**
     * Valeur du milieu (moyenne des deux du milieu pour un nombre pair de valeurs) ; 0 sans valeur.
     *
     * @param list<int> $values
     */
    public static function median(array $values): float
    {
        if ([] === $values) {
            return 0.0;
        }
        sort($values);
        $middle = intdiv(\count($values), 2);

        return (float) (0 === \count($values) % 2 ? ($values[$middle - 1] + $values[$middle]) / 2 : $values[$middle]);
    }

    /**
     * Part des objets que leurs propriétaires acceptent de montrer (règle « partageable »).
     *
     * @return array{shareable: int, total: int, percent: int}
     */
    public function shareable(): array
    {
        $total = $this->entityManager->getRepository(Item::class)->count([]);
        $shareable = (int) $this->items->restrictToShareable($this->items->createQueryBuilder('item')->select('COUNT(item.id)'), 'item')
            ->getQuery()
            ->getSingleScalarResult();

        return ['shareable' => $shareable, 'total' => $total, 'percent' => $this->percent($shareable, $total)];
    }

    /**
     * Demandes d'amis : acceptées, en attente, et taux d'acceptation (acceptées / acceptées + en attente).
     *
     * @return array{accepted: int, pending: int, percent: int}
     */
    public function relationships(): array
    {
        $repository = $this->entityManager->getRepository(Relationship::class);
        $accepted = $repository->count(['status' => RelationshipStatus::Accepted]);
        $pending = $repository->count(['status' => RelationshipStatus::Pending]);

        return ['accepted' => $accepted, 'pending' => $pending, 'percent' => $this->percent($accepted, $accepted + $pending)];
    }

    /**
     * Objets par type de catégorie (les noms de catégories sont libres, le type est fiable) ; tous les types, même vides.
     *
     * @return array<string, int> valeur du type (top, bottom…) => nombre d'objets
     */
    public function itemsByType(): array
    {
        $byType = array_fill_keys(array_map(static fn (CategoryType $type) => $type->value, CategoryType::cases()), 0);
        $rows = $this->entityManager->createQueryBuilder()
            ->select('category.type AS type, COUNT(item.id) AS total')
            ->from(Item::class, 'item')
            ->join('item.category', 'category')
            ->groupBy('category.type')
            ->getQuery()
            ->getArrayResult();
        foreach ($rows as $row) {
            $type = $row['type'] instanceof CategoryType ? $row['type']->value : (string) $row['type'];
            $byType[$type] = (int) $row['total'];
        }

        return $byType;
    }

    /**
     * @param class-string $entity
     *
     * @return list<\DateTimeImmutable> dates non vides de ce champ
     */
    private function dates(string $entity, string $field): array
    {
        $values = $this->entityManager->createQueryBuilder()
            ->select(\sprintf('e.%s', $field))
            ->from($entity, 'e')
            ->where(\sprintf('e.%s IS NOT NULL', $field))
            ->getQuery()
            ->getSingleColumnResult();

        // Une requête de colonne simple renvoie le texte de la base, pas un objet date
        return array_map(static fn (mixed $value) => new \DateTimeImmutable((string) $value), $values);
    }

    /**
     * @param list<\DateTimeImmutable> $dates
     */
    private function countSince(array $dates, string $period): int
    {
        $since = $this->clock->now()->modify($period);

        return \count(array_filter($dates, static fn (\DateTimeImmutable $date) => $date >= $since));
    }

    /**
     * Nombre de dates par semaine (du lundi au dimanche), sur les WEEKS dernières semaines, la plus ancienne d'abord.
     *
     * @param list<\DateTimeImmutable> $dates
     *
     * @return list<array{start: \DateTimeImmutable, count: int}>
     */
    private function perWeek(array $dates): array
    {
        $thisMonday = $this->clock->now()->modify('monday this week')->setTime(0, 0);
        $weeks = [];
        for ($i = self::WEEKS - 1; $i >= 0; --$i) {
            $start = $thisMonday->modify(\sprintf('-%d weeks', $i));
            $end = $start->modify('+1 week');
            $weeks[] = [
                'start' => $start,
                'count' => \count(array_filter($dates, static fn (\DateTimeImmutable $date) => $date >= $start && $date < $end)),
            ];
        }

        return $weeks;
    }

    private function percent(int $part, int $total): int
    {
        return 0 === $total ? 0 : (int) round(100 * $part / $total);
    }
}
