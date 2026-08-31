<?php

namespace App\Exports;

use App\Exports\Support\ReferenceSheet;
use App\Models\GradeLevel;
use App\Models\Room;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SectionImportTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        $gradeLevels = GradeLevel::query()->orderBy('sequence')->get(['code', 'name']);
        $rooms = Room::query()->orderBy('name')->get(['code', 'name']);

        return [
            new ReferenceSheet('Sections', ['grade_level_code', 'name', 'capacity', 'room_code'], [
                ['G1', 'A', '30', 'R101'],
            ]),
            new ReferenceSheet(
                'Valid Grade Level Codes',
                ['code', 'name'],
                $gradeLevels->map(fn ($g) => [$g->code, $g->name])->all(),
            ),
            new ReferenceSheet(
                'Valid Room Codes',
                ['code', 'name'],
                $rooms->map(fn ($r) => [$r->code, $r->name])->all(),
            ),
        ];
    }
}
