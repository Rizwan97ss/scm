<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('credit_note_number');
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->date('issued_at');
            $table->timestamps();
            $table->unique(['credit_note_number']);
            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
