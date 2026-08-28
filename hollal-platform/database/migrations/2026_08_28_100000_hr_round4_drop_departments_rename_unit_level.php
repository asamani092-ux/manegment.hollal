<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR Round 4 batch 1 — drop departments feature; rename org level وحدة → قسم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
        });

        Schema::table('org_units', function (Blueprint $table) {
            if (Schema::hasColumn('org_units', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
        });

        Schema::table('employee_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('employee_transfers', 'from_department_id')) {
                $table->dropConstrainedForeignId('from_department_id');
            }
            if (Schema::hasColumn('employee_transfers', 'to_department_id')) {
                $table->dropConstrainedForeignId('to_department_id');
            }
        });

        if (Schema::hasTable('expense_requests') && Schema::hasColumn('expense_requests', 'department_id')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        Schema::dropIfExists('departments');

        DB::table('org_units')->where('level', 'وحدة')->update(['level' => 'قسم']);
    }

    public function down(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('password')
                ->constrained()->nullOnDelete();
        });

        Schema::table('org_units', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('employee_transfers', function (Blueprint $table) {
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
        });

        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('project_id')
                    ->constrained()->nullOnDelete();
            });
        }

        DB::table('org_units')->where('level', 'قسم')->update(['level' => 'وحدة']);
    }
};
