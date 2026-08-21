<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** FIN-ACC-5 — cost centers, bank reconciliation, year close markers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name_ar');
            $table->string('source_type', 32)->nullable(); // department | project
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->after('account_id')
                ->constrained('cost_centers')->nullOnDelete();
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('statement_balance', 14, 2);
            $table->decimal('book_balance', 14, 2)->default(0);
            $table->decimal('difference', 14, 2)->default(0);
            $table->string('status', 16)->default('مسودة'); // مسودة | مكتمل
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fiscal_year_closes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->foreignId('closing_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_closes');
        Schema::dropIfExists('bank_reconciliations');
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
        });
        Schema::dropIfExists('cost_centers');
    }
};
