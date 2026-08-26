<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // School is stancl/tenancy's tenant model, using "predefined
            // columns" mode -- its own real columns (name, slug, etc.), not
            // the package's default JSON data blob. This nullable data
            // column still needs to exist purely for the package's own
            // internal bookkeeping (DatabaseConfig::makeCredentials() writes
            // the generated tenant database name here via setInternal(),
            // through the HasDataColumn/VirtualColumn trait) -- the app
            // itself never reads or writes it directly.
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }
};
