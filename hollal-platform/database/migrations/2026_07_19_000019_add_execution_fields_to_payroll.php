<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 04-B2 — finance-side payroll execution. Finance approves, then executes each
 * row (transfer reference/date + proof on the private disk); amounts remain
 * read-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('finance_approved_by')->nullable()->after('submitted_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('finance_approved_at')->nullable()->after('finance_approved_by');
        });

        Schema::table('payroll_run_items', function (Blueprint $table) {
            $table->string('transfer_reference')->nullable()->after('net');
            $table->date('transfer_date')->nullable()->after('transfer_reference');
            $table->string('proof_file')->nullable()->after('transfer_date');
            $table->timestamp('executed_at')->nullable()->after('proof_file');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finance_approved_by');
            $table->dropColumn('finance_approved_at');
        });

        Schema::table('payroll_run_items', function (Blueprint $table) {
            $table->dropColumn(['transfer_reference', 'transfer_date', 'proof_file', 'executed_at']);
        });
    }
};
