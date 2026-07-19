<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B2 — pay scales (سلم الرواتب). grades is a JSON array of
 * {label, base_amount}. Assigning a grade auto-creates a base salary component.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->json('grades')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_scales');
    }
};
