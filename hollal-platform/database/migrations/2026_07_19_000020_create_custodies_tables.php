<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 04-B3 — custodies (العُهد) with a fixed chain: employee → executive → finance.
 * Status flow: طلب → معتمدة → صرف → تسوية → مغلقة. Settlement items carry
 * invoices on the private disk; finance reconciles disbursed = Σitems + returned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custodies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('disbursed_amount', 12, 2)->nullable();
            $table->decimal('returned_amount', 12, 2)->default(0);
            $table->string('purpose');
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('طلب'); // طلب|معتمدة|صرف|تسوية|مغلقة
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('custody_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custody_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('invoice_file')->nullable(); // private disk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_settlement_items');
        Schema::dropIfExists('custodies');
    }
};
