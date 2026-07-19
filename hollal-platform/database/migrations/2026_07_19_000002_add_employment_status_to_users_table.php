<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B1 — employment status (نشط|مجمد|منتهية_علاقته). مجمد/منتهية block login
 * (kept in sync with the existing is_active login gate). منتهية is reachable
 * only through offboarding (enforced in 01-B5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employment_status')->default('نشط')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employment_status');
        });
    }
};
