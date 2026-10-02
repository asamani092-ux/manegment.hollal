<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive leave balances, pay impacts, and substitute fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_item_id')->nullable()->after('type');
            $table->foreignId('substitute_id')->nullable()->after('reference_item_id')->constrained('users')->nullOnDelete();
            $table->string('substitute_status', 20)->nullable()->after('substitute_id');
            $table->unsignedBigInteger('parent_leave_id')->nullable()->after('substitute_status');
            $table->date('cut_on')->nullable()->after('to_date');
            $table->string('attachment_path')->nullable()->after('reason');
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reference_item_id')->constrained('reference_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->decimal('entitled', 8, 2)->default(0);
            $table->decimal('used', 8, 2)->default(0);
            $table->decimal('reserved', 8, 2)->default(0);
            $table->decimal('adjusted', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'reference_item_id', 'period_year']);
        });

        Schema::create('leave_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reference_item_id')->constrained('reference_items')->cascadeOnDelete();
            $table->decimal('days', 8, 2);
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('leave_pay_impacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->string('month', 7);
            $table->decimal('unpaid_days', 8, 2)->default(0);
            $table->decimal('partial_days', 8, 2)->default(0);
            $table->decimal('partial_pct', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['leave_request_id', 'month', 'partial_pct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_pay_impacts');
        Schema::dropIfExists('leave_balance_adjustments');
        Schema::dropIfExists('leave_balances');
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('substitute_id');
            $table->dropColumn(['reference_item_id', 'substitute_status', 'parent_leave_id', 'cut_on', 'attachment_path']);
        });
    }
};
