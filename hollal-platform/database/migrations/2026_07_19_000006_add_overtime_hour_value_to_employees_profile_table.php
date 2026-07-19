<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B2 — per-employee overtime hour value; multiplied by approved overtime
 * hours in the payroll run (01-B3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees_profile', function (Blueprint $table) {
            $table->decimal('overtime_hour_value', 8, 2)->default(0)->after('employment_type');
        });
    }

    public function down(): void
    {
        Schema::table('employees_profile', function (Blueprint $table) {
            $table->dropColumn('overtime_hour_value');
        });
    }
};
