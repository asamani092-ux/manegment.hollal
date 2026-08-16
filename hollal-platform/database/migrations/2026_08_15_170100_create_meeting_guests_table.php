<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2 wave C — external guests (no employee account) invited to a meeting via
 * a tokenized short link: view the meeting/minutes and confirm/sign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->timestamps();

            $table->index('meeting_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_guests');
    }
};
