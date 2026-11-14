<?php

namespace App\Tests\Functional;

use App\Entity\Ootd;
use App\Tests\Support\Images;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST et DELETE /api/ootds/{id}/photo. Les fichiers sont écrits dans public/uploads/ootds :
 * chaque test supprime ceux qu'il a créés (la base, elle, est remise à zéro par transaction).
 */
final class OotdPhotoTest extends ApiTestCase
{
    private const DRAFT = 'Idée pour la soirée';
    private const PUBLISHED = 'Tenue de bricolage du samedi';
    private const DEMO_PHOTO = '/uploads/demo/t-shirt-blanc.jpg';

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

    public function testOwnerAddsAPhotoThenPublishes(): void
    {
        $draft = $this->ootd(self::DRAFT);
        $this->logIn('camille');

        $response = $this->upload($draft, ['image' => Images::dataUrl(Images::jpeg(1024, 1365))]);

        $this->assertStatus(200);
        self::assertMatchesRegularExpression(\sprintf('#^/uploads/ootds/%d-[0-9a-f]{16}\.jpg$#', $draft->getId()), $response['photo']);
        self::assertFileExists($this->publicPath($response['photo']));
        // La photo exigée est là : le look peut être publié
        $this->request('PATCH', '/api/ootds/'.$draft->getId(), ['published' => true]);
        $this->assertStatus(200);
    }

    public function testReplacingThePhotoOfAPublishedOotdKeepsTheDemoFile(): void
    {
        $published = $this->ootd(self::PUBLISHED);
        $this->logIn('camille');

        $response = $this->upload($published, ['image' => Images::dataUrl(Images::jpeg())]);

        $this->assertStatus(200);
        self::assertSame($response['photo'], $this->reloaded($published)->getPhoto());
        // L'ancienne photo est celle de démo, partagée par les fixtures : elle n'est jamais supprimée
        self::assertFileExists($this->publicPath(self::DEMO_PHOTO));
    }

    public function testOwnerRemovesThePhotoOfADraft(): void
    {
        $draft = $this->ootd(self::DRAFT);
        $this->logIn('camille');
        $photo = $this->upload($draft, ['image' => Images::dataUrl(Images::jpeg())])['photo'];

        $this->request('DELETE', \sprintf('/api/ootds/%d/photo', $draft->getId()));

        $this->assertStatus(204);
        self::assertFileDoesNotExist($this->publicPath($photo));
        self::assertNull($this->reloaded($draft)->getPhoto());
    }

    public function testAPublishedOotdKeepsItsPhoto(): void
    {
        $published = $this->ootd(self::PUBLISHED);
        $this->logIn('camille');

        $response = $this->request('DELETE', \sprintf('/api/ootds/%d/photo', $published->getId()));

        $this->assertStatus(422);
        self::assertSame('Un look publié garde sa photo : remplace-la ou supprime le look.', $response['message']);
        self::assertSame(self::DEMO_PHOTO, $this->reloaded($published)->getPhoto());
    }

    public function testDeletingTheOotdDeletesItsPhoto(): void
    {
        $draft = $this->ootd(self::DRAFT);
        $this->logIn('camille');
        $photo = $this->upload($draft, ['image' => Images::dataUrl(Images::jpeg())])['photo'];

        $this->request('DELETE', '/api/ootds/'.$draft->getId());

        $this->assertStatus(204);
        self::assertFileDoesNotExist($this->publicPath($photo));
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function othersProvider(): iterable
    {
        // [personne, look, méthode, code] : 404 si le look est invisible, 403 s'il est visible sans être à moi
        yield 'Théo voit le look publié : 403 à l\'envoi' => ['theo', self::PUBLISHED, 'POST', 403];
        yield 'Théo voit le look publié : 403 à la suppression' => ['theo', self::PUBLISHED, 'DELETE', 403];
        yield 'Théo ne voit pas le brouillon : 404' => ['theo', self::DRAFT, 'POST', 404];
        yield 'Zoé ne voit pas le look : 404 à l\'envoi' => ['zoe', self::PUBLISHED, 'POST', 404];
        yield 'Zoé ne voit pas le look : 404 à la suppression' => ['zoe', self::PUBLISHED, 'DELETE', 404];
    }

    #[DataProvider('othersProvider')]
    public function testOthersCannotChangeThePhoto(string $viewer, string $caption, string $method, int $expected): void
    {
        $ootd = $this->ootd($caption);
        $before = $ootd->getPhoto();
        $this->logIn($viewer);

        $this->request($method, \sprintf('/api/ootds/%d/photo', $ootd->getId()), ['image' => Images::dataUrl(Images::jpeg())]);

        $this->assertStatus($expected);
        self::assertSame($before, $this->reloaded($ootd)->getPhoto());
    }

    public function testUnknownOotdIsNotFound(): void
    {
        $this->logIn('camille');

        $response = $this->request('POST', '/api/ootds/999999/photo', ['image' => Images::dataUrl(Images::jpeg())]);

        $this->assertStatus(404);
        self::assertSame('Ce look est introuvable.', $response['message']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>|string, int}>
     */
    public static function invalidImageProvider(): iterable
    {
        yield 'image absente' => [[], 400];
        yield 'corps non JSON' => ['pas du JSON', 400];
        yield 'PNG annoncé comme JPEG' => [['image' => Images::dataUrl(Images::png())], 400];
        yield 'image trop grande' => [['image' => Images::dataUrl(Images::jpeg(4000, 3000))], 400];
        yield 'image trop lourde' => [['image' => 'data:image/jpeg;base64,'.str_repeat('A', 3_000_000)], 413];
    }

    /**
     * @param array<string, mixed>|string $body
     */
    #[DataProvider('invalidImageProvider')]
    public function testInvalidImageIsRefused(array|string $body, int $expected): void
    {
        $draft = $this->ootd(self::DRAFT);
        $this->logIn('camille');

        $this->upload($draft, $body);

        $this->assertStatus($expected);
        self::assertNull($this->reloaded($draft)->getPhoto());
    }

    /**
     * @param array<string, mixed>|string $body
     *
     * @return array<string, mixed>
     */
    private function upload(Ootd $ootd, array|string $body): array
    {
        $response = $this->request('POST', \sprintf('/api/ootds/%d/photo', $ootd->getId()), $body);
        if (isset($response['photo'])) {
            $this->createdFiles[] = $this->publicPath($response['photo']);
        }

        return $response;
    }

    private function publicPath(string $url): string
    {
        return static::getContainer()->getParameter('kernel.project_dir').'/public'.$url;
    }

    private function ootd(string $caption): Ootd
    {
        $ootd = $this->em()->getRepository(Ootd::class)->findOneBy(['caption' => $caption]);
        self::assertInstanceOf(Ootd::class, $ootd, \sprintf('OOTD « %s » absent : lancer make test-db', $caption));

        return $ootd;
    }

    private function reloaded(Ootd $ootd): Ootd
    {
        $em = $this->em();
        $em->clear();
        $reloaded = $em->find(Ootd::class, $ootd->getId());
        self::assertInstanceOf(Ootd::class, $reloaded);

        return $reloaded;
    }
}
