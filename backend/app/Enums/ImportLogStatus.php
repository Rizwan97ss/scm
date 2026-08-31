<?php

namespace App\Enums;

/**
 * Every import prior to this enum ran synchronously and was only ever
 * logged once it had already finished — those rows, and every small/
 * dry-run import going forward, are created directly as Completed. Queued
 * (large-file) imports are the only path that actually passes through
 * Queued/Processing — see ProcessStudentImportJob.
 */
enum ImportLogStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
