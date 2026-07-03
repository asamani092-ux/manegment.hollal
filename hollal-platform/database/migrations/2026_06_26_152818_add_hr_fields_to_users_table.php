<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR fields added AFTER users + departments tables exist (FK order safe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('department_id')->nullable()->after('password')
                ->constrained('departments')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->after('department_id')
                ->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('manager_id');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['phone', 'department_id', 'manager_id', 'is_active', 'deleted_at']);
        });
    }
};
