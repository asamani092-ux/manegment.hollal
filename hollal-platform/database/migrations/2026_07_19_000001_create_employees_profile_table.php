<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B1 — extended HR profile for a user (1:1). Arabic-valued enums stored as
 * strings and validated at the application layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees_profile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('job_title')->nullable();
            $table->string('employment_type')->nullable(); // دوام_كامل|دوام_جزئي|متعاون|متطوع
            $table->date('hire_date')->nullable();
            $table->string('national_id')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees_profile');
    }
};
