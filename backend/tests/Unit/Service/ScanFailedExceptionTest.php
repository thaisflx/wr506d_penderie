<?php

namespace App\Tests\Unit\Service;

use App\Service\ScanFailedException;
use PHPUnit\Framework\TestCase;

final class ScanFailedExceptionTest extends TestCase
{
    public function testKeepsMessageAndErrorDetails(): void
    {
        $exception = new ScanFailedException('Modèles saturés', ['model-a : HTTP 429', 'model-b : HTTP 503']);

        self::assertSame('Modèles saturés', $exception->getMessage());
        self::assertSame(['model-a : HTTP 429', 'model-b : HTTP 503'], $exception->errors);
        // Message destiné à l'utilisateur : l'exception reste une RuntimeException classique
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testErrorDetailsAreOptional(): void
    {
        self::assertSame([], (new ScanFailedException('Échec'))->errors);
    }
}
