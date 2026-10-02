<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reference_item_id')->constrained('reference_items')->cascadeOnDelete();
            $table->unsignedTinyInteger('occurrence_index')->default(1);
            $table->string('source', 20)->default('manual');
            $table->date('occurred_on');
            $table->date('discovered_on');
            $table->text('facts')->nullable();
            $table->string('status', 40)->default('suggested');
            $table->json('decided_penalty')->nullable();
            $table->text('decision_reason')->nullable();
            $table->date('statement_deadline_on')->nullable();
            $table->timestamp('statement_submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('month', 7);
            $table->foreignId('reference_item_id')->nullable()->constrained('reference_items')->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('computed_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('proposed');
            $table->string('reason')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('violations');
    }
};
