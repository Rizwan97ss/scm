<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->string('layout')->default('classic')->after('type');
            // Free-text {name, title} pairs (0-2), not a users FK — a
            // certificate's printed signatory is often not a system user,
            // and schools want to type "Principal" once per template rather
            // than per issuance. See CertificateController::pdf().
            $table->json('signatories')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['layout', 'signatories']);
        });
    }
};
