<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('confidentiality', ['team', 'department', 'managers']);
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'category']);
            $table->index('confidentiality');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
