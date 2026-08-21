<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-ACC-1 — chart of accounts + bridge to existing expense/revenue categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32);
            $table->string('name_ar');
            $table->string('type', 32); // أصول، خصوم، حقوق_ملكية، إيرادات، مصروفات
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('nature', 16); // مدين، دائن
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
            $table->index(['type', 'is_active']);
            $table->index('parent_id');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('parent_id')
                ->constrained('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('revenue_categories', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('parent_id')
                ->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('revenue_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
        Schema::dropIfExists('chart_of_accounts');
    }
};
