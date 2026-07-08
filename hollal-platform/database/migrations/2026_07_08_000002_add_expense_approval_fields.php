<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->after('reason');
            $table->string('current_approval_stage')->nullable()->after('status');
            $table->json('approval_stages')->nullable()->after('current_approval_stage');
            $table->timestamp('paid_ready_at')->nullable()->after('approved_at');
        });

        DB::table('expense_requests')->where('payment_method', 'bank_transfer')->update(['payment_method' => 'transfer']);
        DB::table('expense_requests')->where('payment_method', 'card')->update(['payment_method' => 'pos']);
        DB::table('expense_requests')->where('payment_method', 'cash')->update(['payment_method' => 'other']);
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropColumn(['priority', 'current_approval_stage', 'approval_stages', 'paid_ready_at']);
        });
    }
};
