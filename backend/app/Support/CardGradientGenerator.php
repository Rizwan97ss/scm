<?php

namespace App\Support;

/**
 * Renders a diagonal two-color gradient as a base64 PNG data URI, for the
 * student ID card's full-bleed background. dompdf's CSS `background:
 * linear-gradient(...)` silently renders nothing (confirmed directly against
 * this app's installed dompdf version — the page came back plain white, no
 * warning) — a real raster image is the only reliable way to get a gradient
 * into a dompdf-rendered PDF, the same reasoning QrCodeGenerator/
 * BarcodeGenerator already embed their own output as data URIs rather than
 * leaning on dompdf to draw anything dynamic itself.
 *
 * Colors come from the school's own branding.primary_color/secondary_color
 * settings (already used for the existing card's accent strip), not a
 * hardcoded pair, so every school's card reflects its own brand.
 */
class CardGradientGenerator
{
    public static function diagonalDataUri(string $colorFromHex, string $colorToHex, int $width, int $height): string
    {
        $from = self::hexToRgb($colorFromHex);
        $to = self::hexToRgb($colorToHex);

        $image = imagecreatetruecolor($width, $height);

        // A true top-left-to-bottom-right diagonal (not just a vertical
        // fade) — each pixel's position along that axis is the average of
        // its horizontal and vertical progress. A card-sized canvas (a few
        // hundred pixels square even at print resolution) makes the
        // per-pixel cost negligible; imagesetpixel in a tight loop over
        // this small a canvas runs well under a second.
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $t = (($x / $width) + ($y / $height)) / 2;
                $r = (int) round($from[0] + ($to[0] - $from[0]) * $t);
                $g = (int) round($from[1] + ($to[1] - $from[1]) * $t);
                $b = (int) round($from[2] + ($to[2] - $from[2]) * $t);
                imagesetpixel($image, $x, $y, imagecolorallocate($image, $r, $g, $b));
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
