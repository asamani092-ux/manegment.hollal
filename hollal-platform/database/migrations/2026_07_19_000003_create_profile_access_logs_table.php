<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B1 — records every access to a sensitive employee profile tab (e.g. the
 * salary tab).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // actor
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tab_accessed');
            $table->timestamp('accessed_at')->useCurrent();

            $table->index(['target_user_id', 'tab_accessed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_access_logs');
    }
};
