<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR Round 4 batch 2أ — quarterly evaluation engine (templates + cycles + snapshots).
 * Legacy periodic_evaluations / evaluation_scores remain isolated for the old screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('evaluation_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_template_id')->constrained()->cascadeOnDelete();
            $table->string('section'); // مدير|موارد
            $table->string('question_text');
            $table->unsignedTinyInteger('weight');
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('evaluation_cycles', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter'); // 1–4
            $table->string('status')->default('مسودة'); // مسودة|مفتوحة|مغلقة
            $table->foreignId('evaluation_template_id')->constrained()->restrictOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'quarter']);
        });

        Schema::create('evaluation_cycle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->string('question_text');
            $table->unsignedTinyInteger('weight');
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('employee_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('مسودة'); // مسودة|قيد_التقييم|مكتمل
            $table->decimal('total_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['evaluation_cycle_id', 'employee_id']);
        });

        Schema::create('employee_evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_cycle_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable(); // 1–5
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['employee_evaluation_id', 'evaluation_cycle_item_id'], 'emp_eval_score_unique');
        });

        // Isolate legacy demo rows so they do not collide with the new engine naming.
        if (Schema::hasTable('periodic_evaluations')) {
            DB::table('evaluation_scores')->delete();
            DB::table('periodic_evaluations')->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_evaluation_scores');
        Schema::dropIfExists('employee_evaluations');
        Schema::dropIfExists('evaluation_cycle_items');
        Schema::dropIfExists('evaluation_cycles');
        Schema::dropIfExists('evaluation_template_items');
        Schema::dropIfExists('evaluation_templates');
    }
};
