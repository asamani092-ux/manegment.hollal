<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('value', 12, 2)->nullable();
            $table->string('contract_file')->nullable();
            $table->enum('status', ['active', 'expired', 'terminated', 'pending'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['end_date', 'status']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
