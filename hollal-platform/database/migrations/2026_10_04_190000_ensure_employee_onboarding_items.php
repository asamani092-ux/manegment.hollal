<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the onboarding checklist table when an earlier migration stopped first.
 * Time: O(1) | Space: O(1)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_onboarding_items')) {
            Schema::create('employee_onboarding_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reference_item_id')->constrained('reference_items')->cascadeOnDelete();
                $table->string('status', 30)->default('open');
                $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
                $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('acted_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'reference_item_id']);
            });
        }

        if (Schema::hasTable('employee_onboarding_items') && ! Schema::hasColumn('employee_onboarding_items', 'task_id')) {
            Schema::table('employee_onboarding_items', function (Blueprint $table) {
                $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            });
        }
        if (Schema::hasTable('employee_onboarding_items') && ! Schema::hasColumn('employee_onboarding_items', 'acted_by')) {
            Schema::table('employee_onboarding_items', function (Blueprint $table) {
                $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
        if (Schema::hasTable('employee_onboarding_items') && ! Schema::hasColumn('employee_onboarding_items', 'acted_at')) {
            Schema::table('employee_onboarding_items', function (Blueprint $table) {
                $table->timestamp('acted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
    }
};
