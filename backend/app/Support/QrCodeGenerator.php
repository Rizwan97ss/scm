<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Renders a QR code as a base64 data URI, embeddable directly in a dompdf
 * Blade view's <img src="..."> — dompdf can't fetch external image URLs
 * reliably, so this avoids a network round-trip during PDF generation
 * entirely. Currently used for ID cards, encoding the model's own UUID
 * (see docs/database.md's "UUIDs alongside auto-increment IDs" note).
 */
class QrCodeGenerator
{
    public static function dataUri(string $data, int $size = 180): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $data,
            size: $size,
            margin: 4,
        ))->build()->getDataUri();
    }
}
