<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 09-B1 — org tree (إدارة ← وحدة ← وظيفة), job cards, transfer history,
 * committees linked to meetings.
 * 10-B1 — exceptional per-user permission grants carrying a reason and a date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('level'); // إدارة|وحدة|وظيفة
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('job_purpose')->nullable();       // بطاقة الوظيفة
            $table->json('job_responsibilities')->nullable();
            $table->json('job_requirements')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'level']);
        });

        Schema::create('employee_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('to_org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('effective_on');
            $table->string('reason')->nullable();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('mandate')->nullable();
            $table->foreignId('chair_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('committee_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_label')->nullable();
            $table->timestamps();

            $table->unique(['committee_id', 'user_id']);
        });

        // meetings.committee_id was reserved in 03-B1; the committees table
        // that backs it is created here.

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
        });

        Schema::create('exceptional_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->string('reason');
            $table->date('granted_on');
            $table->date('expires_on')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exceptional_grants');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('org_unit_id');
        });

        Schema::dropIfExists('committee_user');
        Schema::dropIfExists('committees');
        Schema::dropIfExists('employee_transfers');
        Schema::dropIfExists('org_units');
    }
};
