<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide signature portal requests (tokenized external signing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('document_type', 64);
            $table->unsignedBigInteger('document_id');
            $table->string('status', 32)->default('معلق');
            $table->string('signer_name')->nullable();
            $table->string('signer_position')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->string('signature_hash', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
    }
};
