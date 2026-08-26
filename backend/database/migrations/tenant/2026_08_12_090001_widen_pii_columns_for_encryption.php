<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens columns about to hold Laravel's `encrypted` cast (see the User,
 * Student, Guardian, Payment models) — the encrypted envelope runs ~3-4x
 * longer than plaintext, so a 255-char `string` column can overflow on a
 * value that fit comfortably before. Structural only; the cast itself is
 * added to the models in the same change, and existing plaintext rows are
 * rewritten in place by `php artisan security:encrypt-pii` (run once,
 * after this migration and before the encrypted-cast code is deployed —
 * see docs/deployment.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('phone')->nullable()->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->text('emergency_contact_phone')->nullable()->change();
            $table->text('address_line1')->nullable()->change();
            $table->text('address_line2')->nullable()->change();
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->text('national_id')->nullable()->change();
            $table->text('address_line1')->nullable()->change();
            $table->text('address_line2')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->text('reference_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('emergency_contact_phone')->nullable()->change();
            $table->string('address_line1')->nullable()->change();
            $table->string('address_line2')->nullable()->change();
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('national_id')->nullable()->change();
            $table->string('address_line1')->nullable()->change();
            $table->string('address_line2')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->change();
        });
    }
};
