<?php

namespace Tests\Unit\Support;

use App\Support\CardGradientGenerator;
use Tests\TestCase;

class CardGradientGeneratorTest extends TestCase
{
    public function test_it_returns_a_valid_png_data_uri_of_the_requested_size(): void
    {
        $uri = CardGradientGenerator::diagonalDataUri('#0d9488', '#1e3a8a', 486, 306);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $binary = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        $image = imagecreatefromstring($binary);
        $this->assertNotFalse($image);
        $this->assertSame(486, imagesx($image));
        $this->assertSame(306, imagesy($image));
    }

    /** The whole point is a diagonal fade, not a solid fill -- the top-left corner and bottom-right corner must actually differ. */
    public function test_the_gradient_actually_transitions_between_the_two_colors(): void
    {
        $uri = CardGradientGenerator::diagonalDataUri('#000000', '#ffffff', 100, 100);
        $binary = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        $image = imagecreatefromstring($binary);

        $topLeft = imagecolorat($image, 0, 0);
        $bottomRight = imagecolorat($image, 99, 99);

        $this->assertNotSame($topLeft, $bottomRight);
        // top-left should be close to black, bottom-right close to white.
        $this->assertLessThan(30, $topLeft & 0xff);
        $this->assertGreaterThan(225, $bottomRight & 0xff);
    }

    public function test_it_accepts_a_3_digit_hex_color(): void
    {
        $uri = CardGradientGenerator::diagonalDataUri('#000', '#fff', 10, 10);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }
}
