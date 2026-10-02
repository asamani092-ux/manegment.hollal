<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Managed reference lists with versioned items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_lists', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_ar');
            $table->text('description_ar')->nullable();
            $table->json('schema')->nullable();
            $table->timestamps();
        });

        Schema::create('reference_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reference_list_id')->constrained('reference_lists')->cascadeOnDelete();
            $table->string('code');
            $table->string('name_ar');
            $table->json('attributes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('reference_items')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['reference_list_id', 'code', 'version']);
            $table->index(['reference_list_id', 'status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_items');
        Schema::dropIfExists('reference_lists');
    }
};
