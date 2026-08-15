<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round-1 PART+MEET: org type free-text, meeting decision close reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('type_other')->nullable()->after('type');
        });

        Schema::table('meeting_items', function (Blueprint $table) {
            $table->string('close_reason')->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('close_reason');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('type_other');
        });

        Schema::table('meeting_items', function (Blueprint $table) {
            $table->dropColumn(['close_reason', 'closed_at']);
        });
    }
};
