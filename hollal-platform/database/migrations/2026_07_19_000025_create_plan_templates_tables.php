<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 06A-B2 — plan template editor. Five-level item tree, versioned: a project
 * generated from version N keeps version N even after the template moves on.
 * `needs_review` blocks real generation until the review session with عبدالله
 * has signed the template off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind'); // خطة حلل|خطة الجهة|داخلي
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('needs_review')->default(true);
            $table->string('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_template_id')->constrained()->cascadeOnDelete();
            $table->string('version_label');
            $table->boolean('is_current')->default(false);
            $table->string('change_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('template_items')->cascadeOnDelete();
            $table->unsignedTinyInteger('level'); // 1..5
            $table->string('title');
            $table->string('role')->nullable(); // مدير مشروع حلل|مشرف علمي|مدير جهة|شارح|منسق...
            $table->integer('start_offset_days')->default(0);
            $table->unsignedInteger('duration_days')->default(1);
            $table->string('evidence_required')->nullable();
            $table->string('item_kind')->default('إلزامي'); // إلزامي|خدمة
            $table->string('service_type')->nullable(); // تدريب|زيارة|استشارة|قياس
            $table->text('guidance_note')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['template_version_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_items');
        Schema::dropIfExists('template_versions');
        Schema::dropIfExists('plan_templates');
    }
};
