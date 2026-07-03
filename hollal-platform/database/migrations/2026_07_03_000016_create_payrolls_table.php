<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('month');
            $table->decimal('base', 12, 2)->default(0);
            $table->decimal('additions', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->string('transfer_status')->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'month']);
            $table->index('month');
            $table->index('transfer_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
