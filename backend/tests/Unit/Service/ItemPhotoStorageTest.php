<?php

namespace App\Tests\Unit\Service;

use App\Entity\Item;
use App\Service\ItemPhotoStorage;
use App\Tests\Support\Images;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Le stockage écrit dans un dossier temporaire qui joue le rôle du projet (public/uploads/items).
 */
final class ItemPhotoStorageTest extends TestCase
{
    private string $projectDir;
    private string $uploadDir;
    private ItemPhotoStorage $storage;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/penderie-photo-'.bin2hex(random_bytes(4));
        $this->uploadDir = $this->projectDir.'/public/uploads/items/';
        (new Filesystem())->mkdir($this->uploadDir);
        $this->storage = new ItemPhotoStorage($this->projectDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testStoresValidJpegUnderRandomName(): void
    {
        $item = $this->item(42);
        $jpeg = Images::jpeg(1024, 768);

        $url = $this->storage->store($item, Images::dataUrl($jpeg));

        self::assertMatchesRegularExpression('#^/uploads/items/42-[0-9a-f]{16}\.jpg$#', $url);
        self::assertSame($url, $item->getPhoto());
        self::assertSame($jpeg, file_get_contents($this->projectDir.'/public'.$url));
    }

    public function testAcceptsImageAtMaximumDimension(): void
    {
        $url = $this->storage->store($this->item(), Images::dataUrl(Images::jpeg(2048, 2048)));

        self::assertFileExists($this->projectDir.'/public'.$url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidContentProvider(): iterable
    {
        yield 'PNG annoncé comme JPEG' => [Images::dataUrl(Images::png(), 'image/jpeg')];
        yield 'type annoncé autre que JPEG' => [Images::dataUrl(Images::jpeg(), 'image/png')];
        yield 'base64 invalide' => ['data:image/jpeg;base64,@@pas du base64@@'];
        yield 'base64 valide mais pas une image' => [Images::dataUrl('bonjour')];
        yield 'pas une data URL' => [base64_encode(Images::jpeg())];
    }

    #[DataProvider('invalidContentProvider')]
    public function testRejectsContentThatIsNotJpeg(string $dataUrl): void
    {
        $item = $this->item();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Envoie une image JPEG encodée en base64');
        try {
            $this->storage->store($item, $dataUrl);
        } finally {
            self::assertNull($item->getPhoto());
            self::assertSame([], $this->uploadedFiles());
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function tooLargeProvider(): iterable
    {
        yield 'trop large' => [2049, 100];
        yield 'trop haute' => [100, 2049];
    }

    #[DataProvider('tooLargeProvider')]
    public function testRejectsTooLargeImage(int $width, int $height): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image trop grande');

        try {
            $this->storage->store($this->item(), Images::dataUrl(Images::jpeg($width, $height)));
        } finally {
            self::assertSame([], $this->uploadedFiles());
        }
    }

    public function testReplacingPhotoDeletesPreviousFile(): void
    {
        $item = $this->item(7);
        $first = $this->storage->store($item, Images::dataUrl(Images::jpeg()));
        $second = $this->storage->store($item, Images::dataUrl(Images::jpeg()));

        self::assertNotSame($first, $second);
        self::assertFileDoesNotExist($this->projectDir.'/public'.$first);
        self::assertSame([basename($second)], $this->uploadedFiles());
    }

    public function testRemoveDeletesUploadedFile(): void
    {
        $item = $this->item();
        $url = $this->storage->store($item, Images::dataUrl(Images::jpeg()));

        $this->storage->remove($item);

        self::assertNull($item->getPhoto());
        self::assertFileDoesNotExist($this->projectDir.'/public'.$url);
    }

    public function testRemoveKeepsExternalPhoto(): void
    {
        // Même nom de fichier qu'une photo envoyée : seule l'URL externe doit être oubliée
        file_put_contents($this->uploadDir.'pull.jpg', 'contenu');
        $item = $this->item()->setPhoto('https://cdn.example.com/pull.jpg');

        $this->storage->remove($item);

        self::assertNull($item->getPhoto());
        self::assertFileExists($this->uploadDir.'pull.jpg');
    }

    public function testRemoveCannotLeaveUploadDirectory(): void
    {
        // Un chemin forgé avec ../ ne doit viser que le dossier des photos
        file_put_contents($this->projectDir.'/public/index.php', '<?php');
        $item = $this->item()->setPhoto('/uploads/items/../../index.php');

        $this->storage->remove($item);

        self::assertFileExists($this->projectDir.'/public/index.php');
    }

    public function testRemoveWithoutPhotoDoesNothing(): void
    {
        $item = $this->item();

        $this->storage->remove($item);

        self::assertNull($item->getPhoto());
    }

    private function item(int $id = 1): Item
    {
        $item = new Item();
        (new \ReflectionProperty(Item::class, 'id'))->setValue($item, $id);

        return $item;
    }

    /**
     * @return list<string>
     */
    private function uploadedFiles(): array
    {
        return array_values(array_diff((array) scandir($this->uploadDir), ['.', '..']));
    }
}
