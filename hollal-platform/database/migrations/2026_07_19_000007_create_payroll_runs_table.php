<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B3 — monthly payroll run header. Status flows
 * مسودة → مرفوع_للمالية → منفذ, with معاد_للتصحيح as the correction loop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('month')->unique(); // YYYY-MM
            $table->string('status')->default('مسودة'); // مسودة|مرفوع_للمالية|منفذ|معاد_للتصحيح
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
