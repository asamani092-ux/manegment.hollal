<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_request_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['approved', 'rejected']);
            $table->text('notes')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['expense_request_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_approval_logs');
    }
};
