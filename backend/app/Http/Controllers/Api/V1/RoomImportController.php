<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\RoomImportTemplateExport;
use App\Imports\RoomsImport;
use App\Models\Room;

class RoomImportController extends LookupImportController
{
    protected function modelClass(): string
    {
        return Room::class;
    }

    protected function importClass(): string
    {
        return RoomsImport::class;
    }

    protected function templateExport(): object
    {
        return new RoomImportTemplateExport;
    }

    protected function templateFilename(): string
    {
        return 'room-import-template.xlsx';
    }

    protected function entityLabel(): string
    {
        return 'room';
    }
}
