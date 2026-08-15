<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-2 Wave D-deep — assets become an independent register with a
 * derivable book value: عمر محاسبي (accounting useful life in years) is
 * captured at registration and never edited; depreciation is always
 * computed live from purchase_amount, purchase_date and this value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedInteger('useful_life_years')->nullable()->after('purchase_amount');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('useful_life_years');
        });
    }
};
