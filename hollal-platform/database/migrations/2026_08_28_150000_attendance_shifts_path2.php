<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Path-2 attendance: work shifts + assign to employees; day types حضور|عن بعد|ميداني.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('start_time', 5); // HH:MM
            $table->string('end_time', 5);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->json('weekdays'); // Carbon dayOfWeek 0=Sun … 6=Sat
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('employees_profile', function (Blueprint $table) {
            $table->foreignId('work_shift_id')
                ->nullable()
                ->after('is_field_worker')
                ->constrained('work_shifts')
                ->nullOnDelete();
        });

        // Migrate legacy day types → path-2 vocabulary.
        if (Schema::hasTable('attendance_records')) {
            DB::table('attendance_records')
                ->where('type', 'تكليف خارجي')
                ->update(['type' => 'ميداني']);
        }
    }

    public function down(): void
    {
        Schema::table('employees_profile', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_shift_id');
        });
        Schema::dropIfExists('work_shifts');
    }
};
