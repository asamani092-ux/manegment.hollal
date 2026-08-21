<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ATT-1…4 — hybrid attendance fields, imports, cycles, absence tiers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('source', 16)->default('يدوي')->after('type'); // بصمة|باركود|عن_بعد|يدوي
            $table->string('device_id')->nullable()->after('source');
            $table->decimal('work_hours', 8, 2)->nullable()->after('device_id');
            $table->unsignedInteger('late_minutes')->default(0)->after('work_hours');
            $table->string('field_location')->nullable()->after('late_minutes');
            $table->string('field_proof_path')->nullable()->after('field_location');
            $table->string('approval_status', 16)->nullable()->after('field_proof_path'); // بانتظار|معتمد|مرفوض
        });

        Schema::table('employees_profile', function (Blueprint $table) {
            $table->string('fingerprint_id')->nullable()->after('national_id');
            $table->boolean('is_field_worker')->default(false)->after('fingerprint_id');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->date('cycle_from')->nullable()->after('month');
            $table->date('cycle_to')->nullable()->after('cycle_from');
        });

        Schema::create('attendance_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_path');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedInteger('rows_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attendance_cycle_approvals', function (Blueprint $table) {
            $table->id();
            $table->date('cycle_from');
            $table->date('cycle_to');
            $table->string('status', 16)->default('مسودة'); // مسودة|معتمد
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->unique(['cycle_from', 'cycle_to']);
        });

        Schema::create('absence_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('from_day');
            $table->unsignedInteger('to_day')->nullable();
            $table->decimal('multiplier', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_tiers');
        Schema::dropIfExists('attendance_cycle_approvals');
        Schema::dropIfExists('attendance_imports');
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['cycle_from', 'cycle_to']);
        });
        Schema::table('employees_profile', function (Blueprint $table) {
            $table->dropColumn(['fingerprint_id', 'is_field_worker']);
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['source', 'device_id', 'work_hours', 'late_minutes', 'field_location', 'field_proof_path', 'approval_status']);
        });
    }
};
