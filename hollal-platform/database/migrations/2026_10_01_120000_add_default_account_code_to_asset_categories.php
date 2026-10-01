<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط فئة الأصل بحساب الأصول الثابتة الافتراضي (مثل 121 أثاث / 122 حاسب).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->string('default_account_code', 3)->default('121')->after('name_ar');
        });
    }

    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropColumn('default_account_code');
        });
    }
};
