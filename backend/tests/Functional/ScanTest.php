<?php

namespace App\Tests\Functional;

use App\Service\ClothingScanner;
use App\Tests\Support\Images;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * POST /api/scan. En test, OPENROUTER_API_KEY est vide (.env.test) : le scan n'est pas configuré.
 * Pour les autres cas, le service est remplacé par un vrai ClothingScanner branché sur
 * MockHttpClient et MockClock : aucun appel réseau, aucune attente.
 */
final class ScanTest extends ApiTestCase
{
    private MockHttpClient $openRouter;

    public function testNotConfiguredReturns503(): void
    {
        $this->logIn('camille');

        $response = $this->request('POST', '/api/scan', ['image' => Images::dataUrl(Images::jpeg())]);

        $this->assertStatus(503);
        self::assertStringContainsString('OPENROUTER_API_KEY', $response['message']);
    }

    public function testSuccessReturnsScannerResult(): void
    {
        $this->fakeScanner([$this->openRouterAnswer('{"name": "Pull bleu marine", "type": "pull", "category": "top", "color": "bleu", "description": "Pull en laine."}')]);
        $this->logIn('camille');

        $result = $this->request('POST', '/api/scan', ['image' => Images::dataUrl(Images::jpeg())]);

        $this->assertStatus(200);
        self::assertSame('Pull bleu marine', $result['name']);
        self::assertSame('top', $result['categoryType']);
        self::assertSame('bleu', $result['color']);
        self::assertSame('model-a', $result['model']);
        self::assertSame(1, $this->openRouter->getRequestsCount());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function modeProvider(): iterable
    {
        yield 'mode étiquette' => ['label', 'étiquette'];
        yield 'mode photo' => ['photo', 'identifie l\'objet principal'];
        yield 'mode absent' => [null, 'identifie l\'objet principal'];
        yield 'mode inconnu' => ['autre', 'identifie l\'objet principal'];
    }

    #[DataProvider('modeProvider')]
    public function testModeSelectsPrompt(mixed $mode, string $expectedPrompt): void
    {
        $answer = $this->openRouterAnswer('{"name": "T-shirt Petit Bateau", "brand": "Petit Bateau"}');
        $this->fakeScanner([$answer]);
        $this->logIn('camille');

        $this->request('POST', '/api/scan', ['image' => Images::dataUrl(Images::jpeg()), 'mode' => $mode]);

        $this->assertStatus(200);
        $prompt = json_decode($answer->getRequestOptions()['body'], true)['messages'][0]['content'][0]['text'];
        self::assertStringContainsString($expectedPrompt, $prompt);
    }

    public function testAllModelsFailingReturns502(): void
    {
        $busy = fn () => new MockResponse('{"error": {"message": "Rate limit"}}', ['http_code' => 429]);
        $this->fakeScanner([$busy(), $busy()]);
        $this->logIn('camille');

        $response = $this->request('POST', '/api/scan', ['image' => Images::dataUrl(Images::jpeg())]);

        $this->assertStatus(502);
        self::assertSame('Les modèles d\'IA gratuits sont saturés en ce moment : réessaie dans une minute.', $response['message']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>|string}>
     */
    public static function invalidImageProvider(): iterable
    {
        yield 'image absente' => [[]];
        yield 'image vide' => [['image' => '']];
        yield 'image qui n\'est pas un texte' => [['image' => 42]];
        yield 'image PNG' => [['image' => Images::dataUrl(Images::png(), 'image/png')]];
        yield 'corps non JSON' => ['pas du JSON'];
    }

    /**
     * @param array<string, mixed>|string $body
     */
    #[DataProvider('invalidImageProvider')]
    public function testInvalidImageReturns400(array|string $body): void
    {
        $this->fakeScanner([]);
        $this->logIn('camille');

        $response = $this->request('POST', '/api/scan', $body);

        $this->assertStatus(400);
        self::assertSame('Envoie une image JPEG encodée en base64 (data:image/jpeg;base64,...).', $response['message']);
        // Rien n'est envoyé au modèle
        self::assertSame(0, $this->openRouter->getRequestsCount());
    }

    public function testTooLargeImageReturns413(): void
    {
        $this->fakeScanner([]);
        $this->logIn('camille');

        $response = $this->request('POST', '/api/scan', ['image' => 'data:image/jpeg;base64,'.str_repeat('A', 3_000_000)]);

        $this->assertStatus(413);
        self::assertStringContainsString('Image trop lourde', $response['message']);
        self::assertSame(0, $this->openRouter->getRequestsCount());
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function fakeScanner(array $responses): void
    {
        $this->openRouter = new MockHttpClient($responses);
        static::getContainer()->set(ClothingScanner::class, new ClothingScanner(
            $this->openRouter, new NullLogger(), new MockClock(), 'sk-test', 'model-a',
        ));
    }

    private function openRouterAnswer(string $content): MockResponse
    {
        return new MockResponse((string) json_encode(['choices' => [['message' => ['content' => $content]]]]), ['http_code' => 200]);
    }
}
