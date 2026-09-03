<?php

namespace App\Support;

/**
 * Renders a simple ribbon-medal badge (circle + two ribbon tails, optionally
 * a 5-point star cut into the circle) as a transparent-background PNG data
 * URI. Same reasoning as CardGradientGenerator: dompdf's inline <svg>
 * support is unreliable (confirmed directly — a plain <svg> shape in a
 * certificate Blade view rendered as nothing, no warning), so any
 * decorative graphic dompdf needs to show has to be a real raster image,
 * not markup dompdf has to interpret itself.
 */
class MedalGenerator
{
    public static function dataUri(string $colorHex, bool $withStar = false, int $size = 100): string
    {
        [$r, $g, $b] = self::hexToRgb($colorHex);
        $width = $size;
        $height = (int) round($size * 1.3);

        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $color = imagecolorallocate($image, $r, $g, $b);
        $white = imagecolorallocate($image, 255, 255, 255);

        $circleD = (int) round($size * 0.86);
        $circleCx = (int) round($width / 2);
        $circleCy = (int) round($circleD / 2) + 2;

        // Ribbon tails, drawn first so the circle overlaps their top edge.
        $tailW = (int) round($size * 0.16);
        $tailTop = $circleCy;
        $tailBottom = $height - 2;
        foreach ([-1, 1] as $side) {
            $cx = $circleCx + $side * (int) round($size * 0.22);
            $points = [
                $cx - $tailW / 2, $tailTop,
                $cx + $tailW / 2, $tailTop,
                $cx + ($side * $tailW / 2), $tailBottom,
                $cx, $tailBottom - (int) round($size * 0.12),
                $cx - ($side * $tailW / 2), $tailBottom,
            ];
            imagefilledpolygon($image, $points, $color);
        }

        imagefilledellipse($image, $circleCx, $circleCy, $circleD, $circleD, $color);
        imageellipse($image, $circleCx, $circleCy, (int) round($circleD * 0.78), (int) round($circleD * 0.78), $white);

        if ($withStar) {
            imagefilledpolygon($image, self::starPoints($circleCx, $circleCy, (int) round($circleD * 0.32), (int) round($circleD * 0.14)), $white);
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /** @return array<int, float> flat [x1,y1,x2,y2,...] for a 5-point star. */
    private static function starPoints(int $cx, int $cy, int $outerR, int $innerR): array
    {
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $angle = M_PI / 5 * $i - M_PI / 2;
            $radius = $i % 2 === 0 ? $outerR : $innerR;
            $points[] = $cx + $radius * cos($angle);
            $points[] = $cy + $radius * sin($angle);
        }

        return $points;
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
