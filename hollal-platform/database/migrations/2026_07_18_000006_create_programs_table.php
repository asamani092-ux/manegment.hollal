<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 00-B4 — programs skeleton (برامج حلل). Fully populated in 06A-B1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('stage')->default('تطوير'); // تطوير|نشط|موقوف
            $table->string('target_audience')->nullable();
            $table->unsignedInteger('sessions_count')->nullable();
            $table->unsignedInteger('hours_count')->nullable();
            $table->text('execution_requirements')->nullable();
            $table->string('platform_url')->nullable();
            $table->text('platform_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
