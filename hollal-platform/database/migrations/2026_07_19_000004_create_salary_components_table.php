<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B2 — salary components that persist month to month. Editing closes the old
 * row (valid_to = yesterday) and opens a new one (valid_from = today), so
 * history is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // أساسي|بدل|خصم_ثابت
            $table->string('label_ar');
            $table->decimal('amount', 12, 2);
            $table->date('valid_from');
            $table->date('valid_to')->nullable(); // null = open-ended
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
