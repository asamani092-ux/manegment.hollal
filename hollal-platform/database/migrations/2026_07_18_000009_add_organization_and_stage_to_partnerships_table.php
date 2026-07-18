<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 00-B4 — reverse the partnership↔project relation groundwork: attach an
 * organization and a journey stage to each partnership. The seven-stage
 * machinery is built on top of `stage` in 05-B2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('stage')->nullable()->after('status'); // 1..8 journey stage
        });
    }

    public function down(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('stage');
        });
    }
};
