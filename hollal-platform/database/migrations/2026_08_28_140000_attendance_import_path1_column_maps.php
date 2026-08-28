<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round 4 batch 3-path1 — interactive column maps, staged imports,
 * manual late/absence indicators, post-approval correction fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_column_maps', function (Blueprint $table) {
            $table->id();
            $table->string('source_label', 120);
            $table->json('headers')->nullable();
            $table->json('mapping');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('source_label');
        });

        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->string('source_label', 120)->nullable()->after('file_path');
            $table->string('import_month', 7)->nullable()->after('source_label'); // Y-m
            $table->string('status', 24)->default('مكتمل')->after('import_month'); // مسودة|بانتظار_مطابقة|مكتمل
            $table->json('column_mapping')->nullable()->after('status');
            $table->json('staged_rows')->nullable()->after('column_mapping');
            $table->json('unmatched_rows')->nullable()->after('staged_rows');
            $table->boolean('replaced')->default(false)->after('unmatched_rows');
        });

        Schema::create('attendance_manual_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('cycle_from');
            $table->date('cycle_to');
            $table->decimal('late_hours', 8, 2)->default(0);
            $table->unsignedInteger('absence_days')->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'cycle_from', 'cycle_to'], 'att_manual_emp_cycle_uq');
        });

        Schema::table('attendance_cycle_approvals', function (Blueprint $table) {
            $table->string('correction_reason')->nullable()->after('snapshot');
            $table->foreignId('correction_requested_by')->nullable()->after('correction_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('correction_requested_at')->nullable()->after('correction_requested_by');
            $table->foreignId('correction_approved_by')->nullable()->after('correction_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('correction_approved_at')->nullable()->after('correction_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_cycle_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('correction_requested_by');
            $table->dropConstrainedForeignId('correction_approved_by');
            $table->dropColumn([
                'correction_reason',
                'correction_requested_at',
                'correction_approved_at',
            ]);
        });

        Schema::dropIfExists('attendance_manual_indicators');

        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->dropColumn([
                'source_label',
                'import_month',
                'status',
                'column_mapping',
                'staged_rows',
                'unmatched_rows',
                'replaced',
            ]);
        });

        Schema::dropIfExists('attendance_column_maps');
    }
};
