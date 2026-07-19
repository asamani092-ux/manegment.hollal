<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 04-B7 — tax invoicing Phase A. Invoices carry an unbroken sequence, totals
 * derived from their line items, and a TLV (ZATCA-oriented) QR payload.
 * Credit/debit notes always reference the original invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->unique(); // e.g. invoice|note
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence')->unique();
            $table->string('number')->unique();
            $table->string('invoice_type')->default('ضريبية'); // ضريبية|مبسطة
            $table->string('mode')->default('داخلي'); // داخلي|خارجي
            $table->string('seller_name');
            $table->string('seller_vat_number')->nullable();
            $table->string('buyer_name');
            $table->string('buyer_vat_number')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('source_type')->nullable(); // دفعة|يدوي
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 8)->default('SAR');
            $table->text('qr_payload')->nullable();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('tax_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_invoice_id')->constrained('tax_invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 4)->default(0.15);
            $table->decimal('line_subtotal', 12, 2)->default(0);
            $table->decimal('line_vat', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('tax_invoice_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_invoice_id')->constrained('tax_invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('sequence')->unique();
            $table->string('number')->unique();
            $table->string('note_type'); // دائن|مدين
            $table->string('reason');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('qr_payload')->nullable();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_invoice_notes');
        Schema::dropIfExists('tax_invoice_items');
        Schema::dropIfExists('tax_invoices');
        Schema::dropIfExists('tax_invoice_sequences');
    }
};
