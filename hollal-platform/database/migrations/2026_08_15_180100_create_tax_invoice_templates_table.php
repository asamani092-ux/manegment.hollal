<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-2 Wave D-deep — one uploadable letterhead/background per invoice
 * type (ضريبية/full · مبسطة/simplified). Company data itself still comes
 * from CompanyProfile; the template only carries the visual letterhead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // ضريبية|مبسطة
            $table->string('letterhead_path')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_invoice_templates');
    }
};
