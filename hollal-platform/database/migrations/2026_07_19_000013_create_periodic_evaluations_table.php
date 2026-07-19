<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B5 — periodic evaluations scored against the employee's responsibilities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodic_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('period'); // YYYY-QN
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('مسودة'); // مسودة|منشور
            $table->text('employee_comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'period']);
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodic_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsibility_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 1..5
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
        Schema::dropIfExists('periodic_evaluations');
    }
};
