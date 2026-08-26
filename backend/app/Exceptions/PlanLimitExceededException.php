<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by PlanLimitService when adding a student/staff user would exceed
 * the school's current plan. Rendered as HTTP 402 (bootstrap/app.php) —
 * deliberately not 422 (that's field validation) or 403 (that's a
 * permission the actor lacks) — so the frontend can reliably show an
 * "Upgrade your plan" CTA rather than a generic error.
 */
class PlanLimitExceededException extends Exception
{
    //
}
