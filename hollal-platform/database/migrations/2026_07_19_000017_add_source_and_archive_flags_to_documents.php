<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 03-B2 — source linking + auto-archive flag on documents. Auto-archived
 * documents (approved minutes, final reports) are read-only. 07-B1 builds the
 * full source-linking library on top of these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('category');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->boolean('is_auto_archived')->default(false)->after('source_id');

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id', 'is_auto_archived']);
        });
    }
};
