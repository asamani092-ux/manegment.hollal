<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B4 — base weekly hours, overtime unlock gate (locked by default), and the
 * scheduler-applied monthly overtime days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees_profile', function (Blueprint $table) {
            $table->unsignedInteger('weekly_hours')->nullable()->after('overtime_hour_value');
            $table->boolean('overtime_unlocked')->default(false)->after('weekly_hours');
            $table->unsignedInteger('overtime_days_this_month')->default(0)->after('overtime_unlocked');
        });
    }

    public function down(): void
    {
        Schema::table('employees_profile', function (Blueprint $table) {
            $table->dropColumn(['weekly_hours', 'overtime_unlocked', 'overtime_days_this_month']);
        });
    }
};
