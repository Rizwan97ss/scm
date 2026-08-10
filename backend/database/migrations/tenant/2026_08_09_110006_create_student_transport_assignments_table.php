<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_transport_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('route_stop_id')->constrained('route_stops')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            // Explicit short name: the auto-generated one (66 chars) is over
            // MySQL's 64-char identifier limit — SQLite has no such limit,
            // so this only surfaces once a real MySQL database is migrated
            // against.
            $table->index(['student_id', 'is_active'], 'student_transport_assignments_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transport_assignments');
    }
};
