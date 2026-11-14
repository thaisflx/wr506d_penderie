<?php

namespace App\Tests\Unit\Service;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Place;
use App\Entity\Room;
use App\Entity\Storage;
use App\Enum\Visibility;
use App\Service\ItemExposure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Objet montrable dans un look du jour : ni lui, ni son rangement, sa pièce ou son logement privé ou perso.
 */
final class ItemExposureTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function itemProvider(): iterable
    {
        yield 'tout ouvert aux abonnés' => [[], true];
        yield 'pièce et rangement amis proches' => [['room' => Visibility::CloseFriends, 'storage' => Visibility::CloseFriends], true];
        yield 'objet perso' => [['itemPersonal' => true], false];
        yield 'rangement perso' => [['storagePersonal' => true], false];
        yield 'rangement privé' => [['storage' => Visibility::Private], false];
        yield 'pièce perso' => [['roomPersonal' => true], false];
        yield 'pièce privée' => [['room' => Visibility::Private], false];
        yield 'logement privé' => [['place' => Visibility::Private], false];
    }

    /**
     * @param array<string, mixed> $setup
     */
    #[DataProvider('itemProvider')]
    public function testIsShareable(array $setup, bool $expected): void
    {
        self::assertSame($expected, (new ItemExposure())->isShareable($this->item($setup)));
    }

    public function testItemWithoutLocationIsNotShareable(): void
    {
        self::assertFalse((new ItemExposure())->isShareable(new Item()));
    }

    /**
     * @param array<string, mixed> $setup
     */
    private function item(array $setup): Item
    {
        $place = (new Place())->setVisibility($setup['place'] ?? Visibility::Followers);
        $room = (new Room())->setVisibility($setup['room'] ?? Visibility::Followers)->setIsPersonal($setup['roomPersonal'] ?? false)->setPlace($place);
        $storage = (new Storage())->setVisibility($setup['storage'] ?? Visibility::Followers)->setIsPersonal($setup['storagePersonal'] ?? false)->setRoom($room);

        return (new Item())->setCategory((new Category())->setStorage($storage))->setIsPersonal($setup['itemPersonal'] ?? false);
    }
}
