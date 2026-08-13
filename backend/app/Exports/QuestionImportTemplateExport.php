<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class QuestionImportTemplateExport implements FromArray, WithHeadings
{
    use Exportable;

    public function headings(): array
    {
        return ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'marks', 'negative_marks'];
    }

    public function array(): array
    {
        return [
            ['What is the capital of France?', 'Paris', 'London', 'Berlin', 'Madrid', 'A', 1, ''],
            ['Water boils at 100°C at sea level.', 'True', 'False', '', '', 'A', 1, 0.25],
        ];
    }
}
