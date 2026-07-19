<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 04-B1 — mandatory expense category, department/project attribution, and a
 * separate official document (الفاتورة/المستند الرسمي). category_id is nullable
 * at the DB level for migration safety on existing rows but is required by the
 * form; one of department_id / project_id must be set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('type')
                ->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('project_id')
                ->constrained('departments')->nullOnDelete();
            $table->string('official_document_path')->nullable()->after('attachment');
        });
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('official_document_path');
        });
    }
};
