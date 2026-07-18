<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 00-B4 — link projects to partnerships and programs, and classify by kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('partnership_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('partnership_id')->constrained()->nullOnDelete();
            $table->string('kind')->default('داخلي')->after('program_id'); // شراكة|داخلي
            $table->date('launch_date')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partnership_id');
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn(['kind', 'launch_date']);
        });
    }
};
