<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ضريبة وبيانات فاتورة لبنود تسوية العهدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custody_settlement_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 4)->default(0.15)->after('amount');
            $table->decimal('vat_amount', 12, 2)->default(0)->after('vat_rate');
            $table->decimal('total_amount', 12, 2)->default(0)->after('vat_amount');
            $table->string('invoice_number')->nullable()->after('total_amount');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->string('vendor_name')->nullable()->after('invoice_file');
        });
    }

    public function down(): void
    {
        Schema::table('custody_settlement_items', function (Blueprint $table) {
            $table->dropColumn([
                'vat_rate', 'vat_amount', 'total_amount',
                'invoice_number', 'invoice_date', 'vendor_name',
            ]);
        });
    }
};
