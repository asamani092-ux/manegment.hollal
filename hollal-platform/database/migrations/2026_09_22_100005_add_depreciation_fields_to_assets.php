<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول الإهلاك بالقسط الثابت على الأصول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'useful_life_months')) {
                $table->unsignedInteger('useful_life_months')->nullable()->after('useful_life_years');
            }
            if (! Schema::hasColumn('assets', 'salvage_value')) {
                $table->decimal('salvage_value', 12, 2)->default(0)->after('useful_life_months');
            }
            if (! Schema::hasColumn('assets', 'depreciation_start')) {
                $table->date('depreciation_start')->nullable()->after('salvage_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $cols = array_filter(['useful_life_months', 'salvage_value', 'depreciation_start'], fn ($c) => Schema::hasColumn('assets', $c));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
