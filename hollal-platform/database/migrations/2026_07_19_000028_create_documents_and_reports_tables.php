<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 07-B1 + 08-B1/08-B2 — document versions, template library, policy review
 * dates, and immutable report snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_policy')->default(false)->after('category');
            $table->date('review_date')->nullable()->after('is_policy');
            $table->timestamp('review_alert_sent_at')->nullable()->after('review_date');
            $table->unsignedInteger('current_version')->default(1)->after('review_alert_sent_at');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('change_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'version']);
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable();
            $table->string('path');
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('kind'); // monthly|project_dashboard|impact|kpi
            $table->string('label');
            $table->string('period')->nullable(); // e.g. 2026-07
            $table->unsignedBigInteger('subject_id')->nullable(); // project/organization id
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_versions');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['is_policy', 'review_date', 'review_alert_sent_at', 'current_version']);
        });
    }
};
