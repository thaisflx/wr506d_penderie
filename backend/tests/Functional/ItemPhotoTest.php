<?php

namespace App\Tests\Functional;

use App\Entity\Item;
use App\Tests\Support\Images;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST et DELETE /api/items/{id}/photo. Les fichiers sont écrits dans public/uploads/items :
 * chaque test supprime ceux qu'il a créés (la base, elle, est remise à zéro par transaction).
 */
final class ItemPhotoTest extends ApiTestCase
{
    /** @var list<string> */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function testOwnerUploadsPhoto(): void
    {
        $tshirt = $this->item('T-shirt blanc');
        $this->logIn('camille');
        $jpeg = Images::jpeg(1024, 768);

        $response = $this->upload($tshirt, ['image' => Images::dataUrl($jpeg)]);

        $this->assertStatus(200);
        self::assertMatchesRegularExpression(\sprintf('#^/uploads/items/%d-[0-9a-f]{16}\.jpg$#', $tshirt->getId()), $response['photo']);
        self::assertSame($jpeg, file_get_contents($this->publicPath($response['photo'])));
        // L'URL est enregistrée en base
        self::assertSame($response['photo'], $this->reloadedItem($tshirt)->getPhoto());
    }

    public function testOwnerDeletesPhoto(): void
    {
        $tshirt = $this->item('T-shirt blanc');
        $this->logIn('camille');
        $photo = $this->upload($tshirt, ['image' => Images::dataUrl(Images::jpeg())])['photo'];

        $this->request('DELETE', \sprintf('/api/items/%d/photo', $tshirt->getId()));

        $this->assertStatus(204);
        self::assertFileDoesNotExist($this->publicPath($photo));
        self::assertNull($this->reloadedItem($tshirt)->getPhoto());
    }

    public function testDeletingWhenThereIsNoPhotoSucceeds(): void
    {
        $jeans = $this->item('Jean brut');
        $this->logIn('camille');

        $this->request('DELETE', \sprintf('/api/items/%d/photo', $jeans->getId()));

        $this->assertStatus(204);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function methodProvider(): iterable
    {
        yield 'envoi' => ['POST'];
        yield 'suppression' => ['DELETE'];
    }

    /**
     * Théo est abonné de Camille, mais seul le propriétaire peut changer la photo.
     */
    #[DataProvider('methodProvider')]
    public function testItemOfAnotherUserIsNotFound(string $method): void
    {
        $tshirt = $this->item('T-shirt blanc');
        $this->logIn('theo');

        $response = $this->request($method, \sprintf('/api/items/%d/photo', $tshirt->getId()), ['image' => Images::dataUrl(Images::jpeg())]);

        $this->assertStatus(404);
        self::assertSame('Cet objet est introuvable.', $response['message']);
        self::assertNull($this->reloadedItem($tshirt)->getPhoto());
    }

    #[DataProvider('methodProvider')]
    public function testUnknownItemIsNotFound(string $method): void
    {
        $this->logIn('camille');

        $this->request($method, '/api/items/999999/photo', ['image' => Images::dataUrl(Images::jpeg())]);

        $this->assertStatus(404);
    }

    /**
     * @return iterable<string, array{array<string, mixed>|string, string}>
     */
    public static function invalidImageProvider(): iterable
    {
        $expected = 'Envoie une image JPEG encodée en base64 (data:image/jpeg;base64,...).';
        // Refusés par le contrôleur
        yield 'image absente' => [[], $expected];
        yield 'image qui n\'est pas un texte' => [['image' => ['data']], $expected];
        yield 'corps non JSON' => ['pas du JSON', $expected];
        // Refusés par ItemPhotoStorage, qui vérifie le contenu réel
        yield 'PNG annoncé comme JPEG' => [['image' => Images::dataUrl(Images::png())], $expected];
        yield 'base64 invalide' => [['image' => 'data:image/jpeg;base64,@@@'], $expected];
        yield 'image trop grande' => [['image' => Images::dataUrl(Images::jpeg(4000, 3000))], 'Image trop grande : redimensionne-la à 1024 px avant l\'envoi.'];
    }

    /**
     * @param array<string, mixed>|string $body
     */
    #[DataProvider('invalidImageProvider')]
    public function testInvalidImageReturns400(array|string $body, string $expectedMessage): void
    {
        $tshirt = $this->item('T-shirt blanc');
        $this->logIn('camille');

        $response = $this->upload($tshirt, $body);

        $this->assertStatus(400);
        self::assertSame($expectedMessage, $response['message']);
        self::assertNull($this->reloadedItem($tshirt)->getPhoto());
    }

    public function testTooHeavyImageReturns413(): void
    {
        $tshirt = $this->item('T-shirt blanc');
        $this->logIn('camille');

        $response = $this->upload($tshirt, ['image' => 'data:image/jpeg;base64,'.str_repeat('A', 3_000_000)]);

        $this->assertStatus(413);
        self::assertStringContainsString('Image trop lourde', $response['message']);
    }

    /**
     * @param array<string, mixed>|string $body
     *
     * @return array<string, mixed>
     */
    private function upload(Item $item, array|string $body): array
    {
        $response = $this->request('POST', \sprintf('/api/items/%d/photo', $item->getId()), $body);
        if (isset($response['photo'])) {
            $this->createdFiles[] = $this->publicPath($response['photo']);
        }

        return $response;
    }

    private function publicPath(string $url): string
    {
        return static::getContainer()->getParameter('kernel.project_dir').'/public'.$url;
    }

    private function reloadedItem(Item $item): Item
    {
        $em = $this->em();
        $em->clear();
        $reloaded = $em->find(Item::class, $item->getId());
        self::assertInstanceOf(Item::class, $reloaded);

        return $reloaded;
    }
}
