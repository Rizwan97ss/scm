<?php

namespace App\Support;

/**
 * Reads a bundled TTF from resources/fonts/certificates and returns it as a
 * base64 data: URI for a Blade view's own @font-face block — same reasoning
 * as QrCodeGenerator/BarcodeGenerator: dompdf can't fetch external font
 * files reliably at render time, so everything it needs is embedded inline.
 * Fonts are OFL-licensed Google Fonts (Playfair Display, Poppins, Great
 * Vibes), bundled at build time rather than fetched per-request.
 */
class FontEmbedder
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function dataUri(string $filename): string
    {
        return self::$cache[$filename] ??= 'data:font/ttf;base64,'.base64_encode(
            file_get_contents(resource_path("fonts/certificates/{$filename}"))
        );
    }
}
