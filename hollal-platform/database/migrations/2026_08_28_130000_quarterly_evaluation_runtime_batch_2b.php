<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR Round 4 batch 2ب — approve/archive lifecycle + cumulative edit log after approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_cycles', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('opened_at');
        });

        Schema::table('employee_evaluations', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('total_score');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('approved_by');
        });

        Schema::create('employee_evaluation_edit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->json('before_scores')->nullable();
            $table->json('after_scores')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_evaluation_edit_logs');

        Schema::table('employee_evaluations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'archived_at']);
        });

        Schema::table('evaluation_cycles', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
