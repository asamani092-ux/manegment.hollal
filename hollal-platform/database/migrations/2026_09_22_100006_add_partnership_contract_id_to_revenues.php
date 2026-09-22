<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الإيراد بعقد الشراكة لدعم تقارير لاحقة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenues', function (Blueprint $table) {
            if (! Schema::hasColumn('revenues', 'partnership_contract_id')) {
                $table->foreignId('partnership_contract_id')->nullable()->after('source_id')
                    ->constrained('partnership_contracts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('revenues', function (Blueprint $table) {
            if (Schema::hasColumn('revenues', 'partnership_contract_id')) {
                $table->dropConstrainedForeignId('partnership_contract_id');
            }
        });
    }
};
