<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B5 — versioned official duties file (ملف المهام الرسمي). The latest version
 * is surfaced on the dashboard; previous versions are archived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_duties_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version');
            $table->string('file_path'); // private disk
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_duties_documents');
    }
};
