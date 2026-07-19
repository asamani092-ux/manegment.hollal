<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 06B-B1..06B-B5 — project execution.
 *
 * · generation bookkeeping on projects and tasks (06B-B1)
 * · entity team members alongside the حلل team (06B-B2)
 * · visits + reports + consultations with contract quotas (06B-B3)
 * · measurement forms/questions/responses + beneficiary groups (06B-B4)
 * · closure, final report, lesson learned, renewal (06B-B5)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('hollal_template_version_id')->nullable()->after('program_id');
            $table->unsignedBigInteger('entity_template_version_id')->nullable()->after('hollal_template_version_id');
            $table->unsignedBigInteger('generated_from_request_id')->nullable()->after('entity_template_version_id');
            $table->text('lesson_learned')->nullable();
            $table->string('final_report_path')->nullable();
            $table->timestamp('final_report_approved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('entity_visible')->default(false)->after('template_item_id');
            $table->string('role_label')->nullable()->after('entity_visible');

            // 06B-B1 — generated plan items may carry an entity role with no
            // platform account, and generated tasks have no human assigner.
            $table->unsignedBigInteger('assigned_by')->nullable()->change();
            $table->unsignedBigInteger('assigned_to')->nullable()->change();
        });

        Schema::create('project_entity_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role_label'); // مدير جهة|منسق الجهة|معلم...
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('project_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_on');
            $table->string('purpose')->nullable();
            $table->string('status')->default('مجدولة'); // مجدولة|منفذة|ملغاة
            $table->text('notes')->nullable();
            $table->text('positives')->nullable();
            $table->text('challenges')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('request')->nullable();
            $table->string('requested_via')->default('داخلي'); // داخلي|رابط الجهة
            $table->foreignId('specialist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response')->nullable();
            $table->string('status')->default('مفتوحة'); // مفتوحة|مسندة|مغلقة
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('measurement_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('kind')->default('اختبار'); // اختبار|رضا
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('measurement_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_form_id')->constrained()->cascadeOnDelete();
            $table->string('text');
            $table->unsignedInteger('max_score')->default(10);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('beneficiary_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('audience')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('measurement_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phase'); // قبلي|بعدي
            $table->json('answers'); // question_id => score
            $table->decimal('total_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->timestamps();

            $table->index(['project_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_responses');
        Schema::dropIfExists('beneficiary_groups');
        Schema::dropIfExists('measurement_questions');
        Schema::dropIfExists('measurement_forms');
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('project_visits');
        Schema::dropIfExists('project_entity_members');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['entity_visible', 'role_label']);
            $table->unsignedBigInteger('assigned_by')->nullable(false)->change();
            $table->unsignedBigInteger('assigned_to')->nullable(false)->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn([
                'hollal_template_version_id', 'entity_template_version_id', 'generated_from_request_id',
                'lesson_learned', 'final_report_path', 'final_report_approved_at', 'delivered_at', 'closed_at',
            ]);
        });
    }
};
