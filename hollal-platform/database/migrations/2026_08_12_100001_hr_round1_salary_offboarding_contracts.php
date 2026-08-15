<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees_profile', function (Blueprint $table) {
            if (! Schema::hasColumn('employees_profile', 'pay_scale_id')) {
                $table->foreignId('pay_scale_id')->nullable()->after('employment_type')->constrained('pay_scales')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees_profile', 'grade_label')) {
                $table->string('grade_label')->nullable()->after('pay_scale_id');
            }
        });

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'renewal_history')) {
                $table->json('renewal_history')->nullable()->after('status');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'offboarding_started_at')) {
                $table->timestamp('offboarding_started_at')->nullable()->after('employment_status');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'related_user_id')) {
                $table->foreignId('related_user_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
                $table->index('related_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'related_user_id')) {
                $table->dropConstrainedForeignId('related_user_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'offboarding_started_at')) {
                $table->dropColumn('offboarding_started_at');
            }
        });

        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'renewal_history')) {
                $table->dropColumn('renewal_history');
            }
        });

        Schema::table('employees_profile', function (Blueprint $table) {
            if (Schema::hasColumn('employees_profile', 'pay_scale_id')) {
                $table->dropConstrainedForeignId('pay_scale_id');
            }
            if (Schema::hasColumn('employees_profile', 'grade_label')) {
                $table->dropColumn('grade_label');
            }
        });
    }
};
