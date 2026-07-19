<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 04-B4 — revenues (الإيرادات). Partnership payments create one revenue each on
 * confirmation (idempotent, wired in 05-B6); manual revenues are entered
 * directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenues', function (Blueprint $table) {
            $table->id();
            $table->string('source_type')->default('يدوي'); // شراكة|يدوي
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('revenue_categories')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('received_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('tax_invoice_id')->nullable(); // 04-B7
            $table->string('external_document_path')->nullable();
            $table->string('status')->default('مسجل'); // مسجل|مؤكد
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenues');
    }
};
