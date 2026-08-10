<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('designation_id')->nullable()->after('school_id')->constrained('designations')->nullOnDelete();
            $table->string('employee_id')->nullable()->after('designation_id');
            $table->date('hire_date')->nullable()->after('employee_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['school_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'employee_id']);
            $table->dropConstrainedForeignId('designation_id');
            $table->dropColumn(['employee_id', 'hire_date']);
        });
    }
};
