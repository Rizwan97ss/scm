<?php

namespace Tests\Unit\Support;

use App\Support\FormulaInjectionSafeValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormulaInjectionSafeValueBinderTest extends TestCase
{
    private function bind(string $value): string
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $cell = $sheet->getCell('A1');
        (new FormulaInjectionSafeValueBinder())->bindValue($cell, $value);

        return (string) $cell->getValue();
    }

    #[DataProvider('dangerousValues')]
    public function test_a_value_starting_with_a_dangerous_prefix_is_neutralized(string $value): void
    {
        $bound = $this->bind($value);

        $this->assertStringStartsWith("'", $bound);
        $this->assertStringContainsString(ltrim($value), $bound);
    }

    public static function dangerousValues(): array
    {
        return [
            ['=cmd|\'/c calc\'!A1'],
            ['+1+1'],
            ['-2+3'],
            ['@SUM(A1:A2)'],
            ["\ttab-prefixed"],
        ];
    }

    public function test_an_ordinary_value_is_left_untouched(): void
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $cell = $sheet->getCell('A1');
        (new FormulaInjectionSafeValueBinder())->bindValue($cell, 'Ordinary Student Name');

        $this->assertSame('Ordinary Student Name', $cell->getValue());
        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
    }
}
