<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive approval engine: metric, requests, steps, delegations.
 * Converts existing rule steps without dropping columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('approval_rules', 'metric')) {
            Schema::table('approval_rules', function (Blueprint $table) {
                $table->string('metric', 20)->default('amount')->after('transaction_type');
            });
        }

        if (Schema::hasTable('approval_requests')) {
            return;
        }

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('rule_id')->nullable()->constrained('approval_rules')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('current_step')->default(0);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id']);
        });

        Schema::create('approval_request_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedInteger('step_index');
            $table->json('definition');
            $table->string('status', 20)->default('waiting');
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acted_on_behalf_of')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->nullableMorphs('source');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('approval_rules')) {
            foreach (DB::table('approval_rules')->get() as $rule) {
                $steps = json_decode($rule->approval_steps, true) ?: [];
                $converted = [];
                foreach ($steps as $step) {
                    if (isset($step['type'])) {
                        $converted[] = $step;
                        continue;
                    }
                    $role = (string) ($step['role'] ?? '');
                    $converted[] = match ($role) {
                        'department_manager' => ['type' => 'direct_manager', 'required' => true, 'mode' => 'any', 'label_ar' => 'المدير المباشر'],
                        'finance', 'finance_manager' => ['type' => 'role', 'role' => 'Finance', 'required' => true, 'mode' => 'any', 'label_ar' => 'المالية'],
                        'executive', 'executive_director' => ['type' => 'role', 'role' => 'Executive Manager', 'required' => true, 'mode' => 'any', 'label_ar' => 'المدير التنفيذي'],
                        default => ['type' => 'role', 'role' => $role, 'required' => true, 'mode' => 'any', 'label_ar' => $role],
                    };
                }
                DB::table('approval_rules')->where('id', $rule->id)->update([
                    'metric' => 'amount',
                    'approval_steps' => json_encode($converted, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        if (Schema::hasTable('expense_requests')) {
            $pending = DB::table('expense_requests')->where('status', 'pending')->get();
            foreach ($pending as $expense) {
                $requestId = DB::table('approval_requests')->insertGetId([
                    'approvable_type' => 'expense_request',
                    'approvable_id' => $expense->id,
                    'status' => 'pending',
                    'current_step' => 0,
                    'submitted_by' => $expense->requester_id ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $stages = json_decode($expense->approval_stages ?? '[]', true) ?: [];
                foreach (array_values($stages) as $index => $stage) {
                    DB::table('approval_request_steps')->insert([
                        'approval_request_id' => $requestId,
                        'step_index' => $index,
                        'definition' => json_encode(['legacy_stage' => $stage], JSON_UNESCAPED_UNICODE),
                        'status' => ($expense->current_approval_stage === $stage) ? 'pending' : 'waiting',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('delegations');
        Schema::table('approval_rules', function (Blueprint $table) {
            $table->dropColumn('metric');
        });
    }
};
