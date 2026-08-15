<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Round 2 FIN Batch 2 — expense return status + payment proof, custody disbursement proof.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_requests', 'payment_proof_path')) {
                $table->string('payment_proof_path')->nullable()->after('attachment');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expense_requests MODIFY COLUMN status ENUM('draft','pending','approved','paid','rejected','returned') NOT NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE expense_approval_logs MODIFY COLUMN action ENUM('approved','rejected','returned') NOT NULL");
        }

        Schema::table('custodies', function (Blueprint $table) {
            if (! Schema::hasColumn('custodies', 'disbursement_proof_path')) {
                $table->string('disbursement_proof_path')->nullable()->after('disbursed_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('custodies', function (Blueprint $table) {
            $table->dropColumn('disbursement_proof_path');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('expense_requests')->where('status', 'returned')->update(['status' => 'rejected']);
            DB::table('expense_approval_logs')->where('action', 'returned')->update(['action' => 'rejected']);
            DB::statement("ALTER TABLE expense_requests MODIFY COLUMN status ENUM('draft','pending','approved','paid','rejected') NOT NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE expense_approval_logs MODIFY COLUMN action ENUM('approved','rejected') NOT NULL");
        }

        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropColumn('payment_proof_path');
        });
    }
};
