<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 02-B1 — triple-evaluation lifecycle fields + evidence requirement + task
 * hierarchy link. Ratings (متميز|متوسط|مقبول|متأخر) are stored as strings.
 * The "تحتاج تعديلًا" action returns status to in_progress and is captured in
 * task_status_logs, so no new enum value is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('required_evidence')->nullable()->after('description');
            $table->string('self_rating')->nullable()->after('submitted_file');
            $table->string('pm_rating')->nullable()->after('self_rating');
            $table->string('final_rating')->nullable()->after('pm_rating');
            $table->text('final_notes')->nullable()->after('final_rating');
            $table->timestamp('completed_at')->nullable()->after('final_notes');
            $table->unsignedBigInteger('template_item_id')->nullable()->after('completed_at');
            $table->foreignId('parent_task_id')->nullable()->after('template_item_id')
                ->constrained('tasks')->nullOnDelete();

            $table->index('template_item_id');
        });

        Schema::create('task_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_logs');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_task_id');
            $table->dropColumn([
                'required_evidence', 'self_rating', 'pm_rating', 'final_rating',
                'final_notes', 'completed_at', 'template_item_id',
            ]);
        });
    }
};
