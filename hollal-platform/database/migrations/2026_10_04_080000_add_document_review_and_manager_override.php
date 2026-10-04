<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->string('status', 30)->default('approved')->after('notes');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
            $table->unsignedBigInteger('previous_document_id')->nullable()->after('rejection_reason');
        });

        Schema::table('employee_onboarding_items', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('status')->constrained('tasks')->nullOnDelete();
            $table->foreignId('acted_by')->nullable()->after('task_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('manager_override_id')->nullable()->after('manager_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_override_id');
        });
        Schema::table('employee_onboarding_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
            $table->dropConstrainedForeignId('acted_by');
        });
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'reviewed_at', 'rejection_reason', 'previous_document_id']);
        });
    }
};
