<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_statements') && Schema::hasTable('violations')) {
            Schema::create('employee_statements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('violation_id')->constrained('violations')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->json('attachments')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('violations') && ! Schema::hasColumn('violations', 'source_ref')) {
            Schema::table('violations', function (Blueprint $table) {
                $table->string('source_ref')->nullable();
                $table->json('attachments')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('exclusion_reason_item_id')->nullable()->constrained('reference_items')->nullOnDelete();
                $table->boolean('cap_flagged')->default(false);
            });
        }

        if (Schema::hasTable('payroll_adjustments') && ! Schema::hasColumn('payroll_adjustments', 'decided_by')) {
            Schema::table('payroll_adjustments', function (Blueprint $table) {
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('payroll_run_item_id')->nullable()->constrained('payroll_run_items')->nullOnDelete();
                $table->string('deferred_to', 7)->nullable();
            });
        }

        if (Schema::hasTable('employee_documents') && ! Schema::hasColumn('employee_documents', 'reference_item_id')) {
            Schema::table('employee_documents', function (Blueprint $table) {
                $table->foreignId('reference_item_id')->nullable()->constrained('reference_items')->nullOnDelete();
            });
        }

        if (Schema::hasTable('employee_onboarding_items') && ! Schema::hasColumn('employee_onboarding_items', 'acted_at')) {
            Schema::table('employee_onboarding_items', function (Blueprint $table) {
                $table->timestamp('acted_at')->nullable();
            });
        }

        $this->ensureCycleColumns();

        foreach (DB::table('evaluation_cycles')->whereNull('name')->get() as $row) {
            DB::table('evaluation_cycles')->where('id', $row->id)->update([
                'name' => 'الربع '.$row->quarter.' / '.$row->year,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('evaluation_cycles')->whereNull('year')->update(['year' => 0]);
        DB::table('evaluation_cycles')->whereNull('quarter')->update(['quarter' => 0]);

        Schema::table('evaluation_cycles', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable(false)->change();
            $table->unsignedTinyInteger('quarter')->nullable(false)->change();
            $table->dropColumn(['name', 'scope', 'linked_project_ids']);
        });
        Schema::table('evaluation_cycles', function (Blueprint $table) {
            $table->unique(['year', 'quarter']);
        });

        Schema::table('employee_onboarding_items', function (Blueprint $table) {
            $table->dropColumn('acted_at');
        });
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reference_item_id');
        });
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropConstrainedForeignId('payroll_run_item_id');
            $table->dropColumn('deferred_to');
        });
        Schema::table('violations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropConstrainedForeignId('exclusion_reason_item_id');
            $table->dropColumn(['source_ref', 'attachments', 'cap_flagged']);
        });
        Schema::dropIfExists('employee_statements');
    }

    private function ensureCycleColumns(): void
    {
        if (! Schema::hasTable('evaluation_cycles')) {
            return;
        }

        try {
            $names = array_column(Schema::getIndexes('evaluation_cycles'), 'name');
            if (in_array('evaluation_cycles_year_quarter_unique', $names, true)) {
                Schema::table('evaluation_cycles', function (Blueprint $table) {
                    $table->dropUnique(['year', 'quarter']);
                });
            }
        } catch (\Throwable) {
        }

        if (! Schema::hasColumn('evaluation_cycles', 'name')) {
            Schema::table('evaluation_cycles', function (Blueprint $table) {
                $table->string('name')->nullable();
                $table->json('scope')->nullable();
                $table->json('linked_project_ids')->nullable();
            });
        }

        try {
            Schema::table('evaluation_cycles', function (Blueprint $table) {
                $table->unsignedSmallInteger('year')->nullable()->change();
                $table->unsignedTinyInteger('quarter')->nullable()->change();
            });
        } catch (\Throwable) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE evaluation_cycles MODIFY year SMALLINT UNSIGNED NULL');
                DB::statement('ALTER TABLE evaluation_cycles MODIFY quarter TINYINT UNSIGNED NULL');
            }
        }
    }
};
