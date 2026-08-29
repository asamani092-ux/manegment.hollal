<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR Round 5 batch ب — track who entered each score (HR proxy for manager section).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_evaluation_scores', function (Blueprint $table) {
            $table->foreignId('scored_by')->nullable()->after('note')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_evaluation_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scored_by');
        });
    }
};
