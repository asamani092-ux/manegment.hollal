<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قواعد سلسلة الاعتماد الديناميكية حسب نوع العملية والمبلغ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type');
            $table->decimal('min_amount', 14, 2)->default(0);
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->json('approval_steps');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['transaction_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
