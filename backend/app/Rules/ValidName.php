<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * For human/entity names, titles, and labels (subject names, room names,
 * student/guardian/staff names, book titles, ...) — NOT for fields that
 * legitimately need their own special characters (email, password, phone,
 * URL/slug, notes/descriptions), which keep their existing rules untouched.
 *
 * Allows letters (any script — Unicode-aware, not just ASCII), digits
 * (needed for "Grade 1", "Room 101", "Section 2-A"), spaces, and the
 * punctuation real names/titles actually use: apostrophe (O'Brien),
 * hyphen (Smith-Jones), period (St. Mary's), comma, ampersand (R&D Lab),
 * parentheses, and forward slash (A/V Room). Rejects everything else —
 * this app had junk like "@@#@" and "(*&*^%*(" sitting in a subjects
 * table with no character-class check to stop it.
 */
class ValidName implements ValidationRule
{
    private const PATTERN = "/^[\p{L}\p{N} .,''\-&()\/]+$/u";

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('The :attribute may only contain letters, numbers, spaces, and the punctuation marks \' - & ( ) . , /');
        }
    }
}
