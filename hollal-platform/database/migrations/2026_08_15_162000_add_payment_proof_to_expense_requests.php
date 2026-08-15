<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_requests', 'payment_proof_path')) {
                $table->string('payment_proof_path')->nullable()->after('attachment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            if (Schema::hasColumn('expense_requests', 'payment_proof_path')) {
                $table->dropColumn('payment_proof_path');
            }
        });
    }
};
