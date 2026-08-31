<?php

namespace App\Imports\Concerns;

use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

/**
 * Bounds how many data rows a single import file may write to the
 * database. Relying on file-size validation alone doesn't cap this: .xlsx
 * is a zip-compressed XML format, so a small upload can still unzip into an
 * enormous row count. Without a cap, a crafted file runs the import fully
 * synchronously (no queue in this codebase's import path) until PHP's
 * memory_limit/max_execution_time kills the worker mid-file, with whatever
 * rows already succeeded left in place and no rollback — effectively a
 * single-request DoS vector, and repeatable since imports aren't otherwise
 * rate-limited tighter than the generic API throttle.
 *
 * Used from each Import class's onRow(): call overRowCap($row) first and
 * return immediately if it's true, before doing any DB work for that row.
 */
trait CapsImportRows
{
    private int $rowsSeen = 0;

    protected function overRowCap(Row $row): bool
    {
        $this->rowsSeen++;

        if ($this->rowsSeen === $this->maxImportRows() + 1) {
            $this->failures[] = new Failure(
                $row->getIndex(),
                'file',
                ["This file has more than {$this->maxImportRows()} data rows — split it into smaller files and import them separately."],
                [],
            );
        }

        return $this->rowsSeen > $this->maxImportRows();
    }

    protected function maxImportRows(): int
    {
        return 2000;
    }
}
