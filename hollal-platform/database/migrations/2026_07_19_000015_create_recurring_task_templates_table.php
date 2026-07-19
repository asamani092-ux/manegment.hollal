<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 02-B3 — recurring task templates. Each generated instance is an independent
 * task with its own lifecycle. tasks.recurring_template_id links an instance
 * back to its template so completing one can trigger the next.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('required_evidence')->nullable();
            $table->foreignId('assigned_to_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('priority')->default('medium');
            $table->string('pattern'); // أسبوعي|شهري
            $table->unsignedTinyInteger('day_of_week')->nullable();  // 0..6
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1..31
            $table->boolean('is_active')->default(true);
            $table->date('last_generated_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('recurring_template_id')->nullable()->after('parent_task_id')
                ->constrained('recurring_task_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_template_id');
        });

        Schema::dropIfExists('recurring_task_templates');
    }
};
