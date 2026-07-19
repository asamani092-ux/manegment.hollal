<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 03-B1 — meeting types, entity links, and a minutes approval cycle
 * (مسودة → بانتظار_الاعتماد → معتمد) kept separate from the scheduling status.
 * Amendments create a new version while preserving the original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('type')->default('دوري')->after('title'); // دوري|مشروع|شراكة|لجنة|طارئ
            $table->foreignId('project_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->foreignId('partnership_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('committee_id')->nullable()->after('partnership_id'); // committees in 09-B1
            $table->string('approval_status')->default('مسودة')->after('status');
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('archived_document_id')->nullable()->after('approved_at');
            $table->unsignedInteger('version')->default(1)->after('archived_document_id');

            $table->index('committee_id');
        });

        Schema::table('meeting_items', function (Blueprint $table) {
            $table->string('item_kind')->default('نقاشي')->after('topic'); // نقاشي|قرار
            $table->foreignId('proposed_by')->nullable()->after('item_kind')->constrained('users')->nullOnDelete();
        });

        Schema::create('meeting_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('note');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_amendments');

        Schema::table('meeting_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposed_by');
            $table->dropColumn('item_kind');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('partnership_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'type', 'committee_id', 'approval_status', 'approved_at',
                'archived_document_id', 'version',
            ]);
        });
    }
};
