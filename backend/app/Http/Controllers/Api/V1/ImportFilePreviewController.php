<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Generic, entity-agnostic "read this file's header row and data rows back
 * as plain strings" — the first step of the frontend's "upload your own
 * file" import flow: arbitrary headers get mapped client-side to whichever
 * entity's canonical columns, reviewed/edited in the same PasteGrid the
 * manual paste flow already uses, and only then committed through the
 * normal per-entity import endpoint. Nothing here writes to the database.
 *
 * Deliberately not gated by a specific import permission — it only ever
 * echoes back the content of a file the caller themselves just uploaded, so
 * there's no other user's data or write capability to protect. The
 * file-type/size validation plus the same row cap every real import already
 * enforces (CapsImportRows) are the controls that actually matter here:
 * without them, this would still be a synchronous, uncapped
 * spreadsheet-parse triggered by an authenticated request — the same
 * single-request DoS shape CapsImportRows' own docblock describes.
 */
class ImportFilePreviewController extends Controller
{
    private const MAX_ROWS = 2000;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $sheets = Excel::toArray(new class {}, $request->file('file'));
        $rows = $sheets[0] ?? [];

        $headers = array_map(fn ($cell) => trim((string) ($cell ?? '')), $rows[0] ?? []);
        $dataRows = array_slice($rows, 1, self::MAX_ROWS);

        return ApiResponse::success([
            'headers' => $headers,
            'rows' => array_map(
                fn ($row) => array_map(fn ($cell) => trim((string) ($cell ?? '')), $row),
                $dataRows
            ),
            'truncated' => count($rows) - 1 > self::MAX_ROWS,
        ]);
    }
}
