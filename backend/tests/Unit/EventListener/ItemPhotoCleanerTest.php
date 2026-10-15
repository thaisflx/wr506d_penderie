<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Item;
use App\EventListener\ItemPhotoCleaner;
use App\Service\ItemPhotoStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ItemPhotoCleanerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/penderie-cleaner-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->projectDir.'/public/uploads/items');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testDeletesPhotoFileWhenItemIsRemoved(): void
    {
        $file = $this->projectDir.'/public/uploads/items/3-abc.jpg';
        file_put_contents($file, 'jpeg');
        $item = (new Item())->setPhoto('/uploads/items/3-abc.jpg');

        (new ItemPhotoCleaner(new ItemPhotoStorage($this->projectDir)))->postRemove($item);

        self::assertFileDoesNotExist($file);
        self::assertNull($item->getPhoto());
    }
}
