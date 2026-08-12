<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round-1 HR: pay-scale link on profile, contract renewal history,
 * offboarding start stamp, and إسناد task ↔ employee link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees_profile', function (Blueprint $table) {
            $table->foreignId('pay_scale_id')->nullable()->after('employment_type')->constrained('pay_scales')->nullOnDelete();
            $table->string('grade_label')->nullable()->after('pay_scale_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->json('renewal_history')->nullable()->after('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('offboarding_started_at')->nullable()->after('employment_status');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('related_user_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->index('related_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('offboarding_started_at');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('renewal_history');
        });

        Schema::table('employees_profile', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pay_scale_id');
            $table->dropColumn('grade_label');
        });
    }
};
