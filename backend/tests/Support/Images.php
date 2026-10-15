<?php

namespace App\Tests\Support;

/**
 * Images minimales pour les tests : seul l'en-tête compte pour getimagesizefromstring(),
 * ce qui évite de dépendre de GD dans le conteneur ou de versionner des fichiers binaires.
 */
final class Images
{
    public static function jpeg(int $width = 16, int $height = 16): string
    {
        return "\xFF\xD8"                                           // SOI
            ."\xFF\xE0".pack('n', 16)."JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00" // APP0
            ."\xFF\xC0".pack('n', 11)."\x08".pack('nn', $height, $width)."\x01\x01\x11\x00" // SOF0 : dimensions
            ."\xFF\xD9";                                            // EOI
    }

    public static function png(int $width = 16, int $height = 16): string
    {
        $ihdr = 'IHDR'.pack('NN', $width, $height)."\x08\x02\x00\x00\x00";

        return "\x89PNG\r\n\x1A\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
    }

    public static function dataUrl(string $binary, string $mime = 'image/jpeg'): string
    {
        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }
}
