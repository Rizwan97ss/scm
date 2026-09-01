<?php

namespace App\Support;

use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Renders a Code128 barcode as a base64 data URI, embeddable directly in a
 * dompdf Blade view's <img src="..."> — same reasoning as QrCodeGenerator:
 * dompdf can't fetch external image URLs reliably, and PNG (not SVG) is
 * what dompdf actually renders consistently. Used for ID cards, encoding
 * the same human-readable identifier already printed next to it (admission
 * number / employee ID) so a scan and a glance always agree.
 */
class BarcodeGenerator
{
    public static function dataUri(string $data, int $widthFactor = 2, int $height = 40): string
    {
        $generator = new BarcodeGeneratorPNG;
        $png = $generator->getBarcode($data, $generator::TYPE_CODE_128, $widthFactor, $height);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
