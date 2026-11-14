<?php

namespace App\Tests\Unit\Service;

use App\Entity\Ootd;
use App\EventListener\OotdPhotoCleaner;
use App\Service\OotdPhotoStorage;
use App\Tests\Support\Images;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Photo d'un look du jour : mêmes règles que les objets (PhotoStorage, testé en détail par
 * ItemPhotoStorageTest), dans son propre dossier public/uploads/ootds.
 */
final class OotdPhotoStorageTest extends TestCase
{
    private string $projectDir;
    private string $uploadDir;
    private OotdPhotoStorage $storage;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/penderie-ootd-photo-'.bin2hex(random_bytes(4));
        $this->uploadDir = $this->projectDir.'/public/uploads/ootds/';
        (new Filesystem())->mkdir([$this->uploadDir, $this->projectDir.'/public/uploads/items', $this->projectDir.'/public/uploads/demo']);
        $this->storage = new OotdPhotoStorage($this->projectDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testStoresInTheOotdFolderUnderRandomName(): void
    {
        $ootd = $this->ootd(12);
        $jpeg = Images::jpeg(1024, 1365);

        $url = $this->storage->store($ootd, Images::dataUrl($jpeg));

        self::assertMatchesRegularExpression('#^/uploads/ootds/12-[0-9a-f]{16}\.jpg$#', $url);
        self::assertSame($url, $ootd->getPhoto());
        self::assertSame($jpeg, file_get_contents($this->projectDir.'/public'.$url));
    }

    public function testRejectsContentThatIsNotJpeg(): void
    {
        $ootd = $this->ootd();

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->storage->store($ootd, Images::dataUrl(Images::png(), 'image/jpeg'));
        } finally {
            self::assertNull($ootd->getPhoto());
            self::assertSame([], $this->uploadedFiles());
        }
    }

    public function testReplacingPhotoDeletesPreviousFile(): void
    {
        $ootd = $this->ootd();
        $first = $this->storage->store($ootd, Images::dataUrl(Images::jpeg()));
        $second = $this->storage->store($ootd, Images::dataUrl(Images::jpeg()));

        self::assertFileDoesNotExist($this->projectDir.'/public'.$first);
        self::assertSame([basename($second)], $this->uploadedFiles());
    }

    public function testRemoveDeletesUploadedFile(): void
    {
        $ootd = $this->ootd();
        $url = $this->storage->store($ootd, Images::dataUrl(Images::jpeg()));

        $this->storage->remove($ootd);

        self::assertNull($ootd->getPhoto());
        self::assertFileDoesNotExist($this->projectDir.'/public'.$url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignPhotoProvider(): iterable
    {
        yield 'photo de démo des fixtures' => ['/uploads/demo/t-shirt.jpg'];
        yield 'photo d\'un objet, même nom de fichier' => ['/uploads/items/t-shirt.jpg'];
        yield 'chemin forgé avec ../' => ['/uploads/ootds/../demo/t-shirt.jpg'];
    }

    #[DataProvider('foreignPhotoProvider')]
    public function testRemoveNeverDeletesFilesOutsideTheOotdFolder(string $photo): void
    {
        file_put_contents($this->projectDir.'/public/uploads/demo/t-shirt.jpg', 'démo');
        file_put_contents($this->projectDir.'/public/uploads/items/t-shirt.jpg', 'objet');
        $ootd = $this->ootd()->setPhoto($photo);

        $this->storage->remove($ootd);

        self::assertNull($ootd->getPhoto());
        self::assertFileExists($this->projectDir.'/public/uploads/demo/t-shirt.jpg');
        self::assertFileExists($this->projectDir.'/public/uploads/items/t-shirt.jpg');
    }

    public function testCleanerDeletesThePhotoWhenTheOotdIsRemoved(): void
    {
        $ootd = $this->ootd();
        $url = $this->storage->store($ootd, Images::dataUrl(Images::jpeg()));

        (new OotdPhotoCleaner($this->storage))->postRemove($ootd);

        self::assertFileDoesNotExist($this->projectDir.'/public'.$url);
    }

    private function ootd(int $id = 1): Ootd
    {
        $ootd = new Ootd();
        (new \ReflectionProperty(Ootd::class, 'id'))->setValue($ootd, $id);

        return $ootd;
    }

    /**
     * @return list<string>
     */
    private function uploadedFiles(): array
    {
        return array_values(array_diff((array) scandir($this->uploadDir), ['.', '..']));
    }
}
