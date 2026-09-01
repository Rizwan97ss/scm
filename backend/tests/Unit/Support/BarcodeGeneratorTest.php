<?php

namespace Tests\Unit\Support;

use App\Support\BarcodeGenerator;
use Tests\TestCase;

class BarcodeGeneratorTest extends TestCase
{
    public function test_it_returns_a_valid_png_data_uri(): void
    {
        $uri = BarcodeGenerator::dataUri('2026-0042');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $binary = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        $this->assertNotFalse($binary);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($binary, 0, 8));
    }

    public function test_different_input_produces_different_output(): void
    {
        $this->assertNotSame(BarcodeGenerator::dataUri('EMP-0001'), BarcodeGenerator::dataUri('EMP-0002'));
    }
}
