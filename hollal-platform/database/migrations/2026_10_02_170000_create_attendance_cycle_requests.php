<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_cycles')) {
            return;
        }

        Schema::create('attendance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7);
            $table->string('status', 20)->default('open');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('reopened_reason')->nullable();
            $table->timestamps();
            $table->unique('month');
        });

        Schema::create('attendance_cycle_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_cycle_id')->constrained('attendance_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 40)->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('chargeable_late_minutes')->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->text('correction_reason')->nullable();
            $table->boolean('downstream_decided')->default(false);
            $table->timestamps();
            $table->unique(['attendance_cycle_id', 'employee_id', 'date']);
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hours', 8, 2);
            $table->string('reason');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('excuse_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->time('from_time');
            $table->time('to_time');
            $table->string('reason');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('minutes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excuse_requests');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendance_cycle_days');
        Schema::dropIfExists('attendance_cycles');
    }
};
