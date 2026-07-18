<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 00-B4 — organizations (الجهات). Arabic-valued enums are stored as strings and
 * validated at the application layer; `roles` is JSON to allow multiple roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable(); // جمعية_تحفيظ|مدرسة|شركة_تعليمية|وقف|حكومية|أخرى
            $table->string('city')->nullable();
            $table->json('roles')->nullable();  // [متعاقدة|تنفيذ|مانحة]
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
