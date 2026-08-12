<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-HR §7 — leave requests + annual balance on employee profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // سنوية | مرضية | استثنائية
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedSmallInteger('days_count');
            $table->text('reason')->nullable();
            $table->string('status')->default('مقدم'); // مقدم | معتمد | مرفوض
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['from_date', 'to_date']);
        });

        Schema::table('employees_profile', function (Blueprint $table) {
            $table->unsignedSmallInteger('annual_leave_balance')->default(21)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('employees_profile', function (Blueprint $table) {
            $table->dropColumn('annual_leave_balance');
        });

        Schema::dropIfExists('leave_requests');
    }
};
