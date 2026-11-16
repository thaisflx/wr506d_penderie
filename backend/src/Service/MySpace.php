<?php

namespace App\Service;

use App\Entity\Block;
use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Ootd;
use App\Entity\Place;
use App\Entity\Relationship;
use App\Entity\User;
use App\Enum\RelationshipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * « Mon espace » (docs/decisions.md, « Back office et Mon espace ») : chiffres sur mon dressing et export de toutes
 * mes données (RGPD, art. 15 et 20). Uniquement mes données ; des autres personnes, seulement leur pseudo.
 */
final class MySpace
{
    // Nombre d'objets dans « les plus portés »
    public const MOST_WORN = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{
     *     itemCount: int,
     *     itemsByCategory: list<array{id: ?int, name: ?string, location: ?string, count: int}>,
     *     itemsByPlace: list<array{id: ?int, name: ?string, count: int}>,
     *     neverWorn: list<array{id: ?int, name: ?string}>,
     *     mostWorn: list<array{id: ?int, name: ?string, count: int}>,
     *     publishedLooks: int,
     *     followers: int,
     *     following: int,
     * }
     */
    public function stats(User $user): array
    {
        $categories = [];
        $places = [];
        foreach ($user->getPlaces() as $place) {
            $placeCount = 0;
            foreach ($this->categoriesOf($place) as $category) {
                $count = $category->getItems()->count();
                $placeCount += $count;
                $categories[] = ['id' => $category->getId(), 'name' => $category->getName(), 'location' => $category->getLocation(), 'count' => $count];
            }
            $places[] = ['id' => $place->getId(), 'name' => $place->getName(), 'count' => $placeCount];
        }
        usort($categories, static fn (array $a, array $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        // Nombre de looks publiés où chaque objet est porté (les brouillons ne comptent pas)
        $published = $this->publishedLooks($user);
        $worn = [];
        foreach ($published as $ootd) {
            foreach ($ootd->getItems() as $item) {
                $worn[$item->getId()] = ($worn[$item->getId()] ?? 0) + 1;
            }
        }
        $items = $user->getItems()->toArray();
        usort($items, static fn (Item $a, Item $b) => strcmp((string) $a->getName(), (string) $b->getName()));
        $neverWorn = array_values(array_filter($items, static fn (Item $item) => !isset($worn[$item->getId()])));
        $mostWorn = array_values(array_filter($items, static fn (Item $item) => isset($worn[$item->getId()])));
        usort($mostWorn, static fn (Item $a, Item $b) => $worn[$b->getId()] <=> $worn[$a->getId()]);

        return [
            'itemCount' => \count($items),
            'itemsByCategory' => $categories,
            'itemsByPlace' => $places,
            'neverWorn' => array_map(static fn (Item $item) => ['id' => $item->getId(), 'name' => $item->getName()], $neverWorn),
            'mostWorn' => array_map(
                static fn (Item $item) => ['id' => $item->getId(), 'name' => $item->getName(), 'count' => $worn[$item->getId()]],
                \array_slice($mostWorn, 0, self::MOST_WORN),
            ),
            'publishedLooks' => \count($published),
            // Personnes différentes, quel que soit le type de relation (abonné ou ami proche)
            'followers' => $this->countPeople($user, 'addressee', 'requester'),
            'following' => $this->countPeople($user, 'requester', 'addressee'),
        ];
    }

    /**
     * Toutes mes données, pour le fichier à télécharger. Les photos sont des URL ; des autres personnes, seul le
     * pseudo apparaît.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $username = static fn (?User $other) => $other?->getUsername();

        return [
            'exportedAt' => $this->clock->now()->format(\DATE_ATOM),
            'profile' => [
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'birthDate' => $user->getBirthDate()?->format('Y-m-d'),
                'bio' => $user->getBio(),
                'avatar' => $user->getAvatar(),
                'locale' => $user->getLocale(),
                'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
                'lastLoginAt' => $user->getLastLoginAt()?->format(\DATE_ATOM),
                'children' => $user->getChildren()->map($username)->getValues(),
            ],
            'places' => $user->getPlaces()->map(fn (Place $place) => [
                'name' => $place->getName(),
                'city' => $place->getCity(),
                'address' => $place->getAddress(),
                'visibility' => $place->getVisibility()->value,
                'rooms' => $place->getRooms()->map(fn ($room) => [
                    'name' => $room->getName(),
                    'visibility' => $room->getVisibility()->value,
                    'personal' => $room->isPersonal(),
                    'storages' => $room->getStorages()->map(fn ($storage) => [
                        'name' => $storage->getName(),
                        'visibility' => $storage->getVisibility()->value,
                        'personal' => $storage->isPersonal(),
                        'categories' => $storage->getCategories()->map(fn (Category $category) => [
                            'name' => $category->getName(),
                            'type' => $category->getType()->value,
                            'items' => $category->getItems()->map(fn (Item $item) => [
                                'name' => $item->getName(),
                                'status' => $item->getStatus()->value,
                                'personal' => $item->isPersonal(),
                                'photo' => $item->getPhoto(),
                                'barcode' => $item->getBarcode(),
                                'attributes' => $item->getAttributes(),
                            ])->getValues(),
                        ])->getValues(),
                    ])->getValues(),
                ])->getValues(),
            ])->getValues(),
            'looks' => array_map(static fn (Ootd $ootd) => [
                'caption' => $ootd->getCaption(),
                'wornOn' => $ootd->getWornOn()->format('Y-m-d'),
                'publishedAt' => $ootd->getPublishedAt()?->format(\DATE_ATOM),
                'photo' => $ootd->getPhoto(),
                'items' => $ootd->getItems()->map(static fn (Item $item) => $item->getName())->getValues(),
            ], $this->entityManager->getRepository(Ootd::class)->findBy(['owner' => $user], ['createdAt' => 'ASC', 'id' => 'ASC'])),
            'relationships' => [
                'sent' => $user->getSentRelationships()->map(static fn (Relationship $r) => [
                    'to' => $username($r->getAddressee()),
                    'type' => $r->getType()->value,
                    'status' => $r->getStatus()->value,
                    'createdAt' => $r->getCreatedAt()->format(\DATE_ATOM),
                ])->getValues(),
                'received' => $user->getReceivedRelationships()->map(static fn (Relationship $r) => [
                    'from' => $username($r->getRequester()),
                    'type' => $r->getType()->value,
                    'status' => $r->getStatus()->value,
                    'createdAt' => $r->getCreatedAt()->format(\DATE_ATOM),
                ])->getValues(),
            ],
            // Seulement les comptes que j'ai bloqués : un blocage subi n'est jamais révélé
            'blocked' => array_map(
                static fn (Block $block) => ['username' => $username($block->getBlocked()), 'createdAt' => $block->getCreatedAt()->format(\DATE_ATOM)],
                $this->entityManager->getRepository(Block::class)->findBy(['blocker' => $user]),
            ),
            'comments' => $user->getComments()->map(static fn ($comment) => [
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()->format(\DATE_ATOM),
            ])->getValues(),
        ];
    }

    /**
     * @return list<Category>
     */
    private function categoriesOf(Place $place): array
    {
        $categories = [];
        foreach ($place->getRooms() as $room) {
            foreach ($room->getStorages() as $storage) {
                $categories = [...$categories, ...$storage->getCategories()->getValues()];
            }
        }

        return $categories;
    }

    /**
     * @return list<Ootd>
     */
    private function publishedLooks(User $user): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('ootd')
            ->from(Ootd::class, 'ootd')
            ->where('ootd.owner = :user AND ootd.publishedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    // Personnes différentes liées à moi par une relation acceptée ($me : mon côté, $other : le leur)
    private function countPeople(User $user, string $me, string $other): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select(\sprintf('COUNT(DISTINCT relationship.%s)', $other))
            ->from(Relationship::class, 'relationship')
            ->where(\sprintf('relationship.%s = :user AND relationship.status = :accepted', $me))
            ->setParameter('user', $user)
            ->setParameter('accepted', RelationshipStatus::Accepted)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
