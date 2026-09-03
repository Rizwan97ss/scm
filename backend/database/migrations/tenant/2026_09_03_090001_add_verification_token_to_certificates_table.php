<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * certificate_number is sequential/guessable (CertificateService's own
 * per-year counter), so it can't be what a QR code encodes for public
 * verification — anyone could enumerate every certificate a school has
 * issued. Same reasoning as User::uuid (see IdCardController's QR/barcode
 * usage): a second, non-sequential identifier meant to be shared publicly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->uuid('verification_token')->nullable()->unique()->after('certificate_number');
        });

        DB::table('certificates')->whereNull('verification_token')->orderBy('id')->each(
            fn ($row) => DB::table('certificates')->where('id', $row->id)->update(['verification_token' => (string) Str::uuid()])
        );
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('verification_token');
        });
    }
};
