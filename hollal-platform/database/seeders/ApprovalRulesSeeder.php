<?php

namespace Database\Seeders;

use App\Models\ApprovalRule;
use Illuminate\Database\Seeder;

/**
 * قواعد سلسلة الاعتماد الافتراضية (مصروف / عهدة / عقد).
 * Time: O(1) | Space: O(1)
 */
class ApprovalRulesSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            [
                'transaction_type' => ApprovalRule::TYPE_EXPENSE,
                'bands' => [
                    [0, 1000, [['role' => 'department_manager']]],
                    [1000.01, 10000, [['role' => 'department_manager'], ['role' => 'finance_manager']]],
                    [10000.01, null, [['role' => 'department_manager'], ['role' => 'finance_manager'], ['role' => 'executive_director']]],
                ],
            ],
            [
                'transaction_type' => ApprovalRule::TYPE_CUSTODY,
                'bands' => [
                    [0, 1000, [['role' => 'department_manager']]],
                    [1000.01, 10000, [['role' => 'department_manager'], ['role' => 'finance_manager']]],
                    [10000.01, null, [['role' => 'department_manager'], ['role' => 'finance_manager'], ['role' => 'executive_director']]],
                ],
            ],
            [
                'transaction_type' => ApprovalRule::TYPE_CONTRACT,
                'bands' => [
                    [0, 10000, [['role' => 'finance_manager']]],
                    [10000.01, null, [['role' => 'finance_manager'], ['role' => 'executive_director']]],
                ],
            ],
        ];

        foreach ($matrix as $group) {
            foreach ($group['bands'] as [$min, $max, $steps]) {
                ApprovalRule::query()->updateOrCreate(
                    [
                        'transaction_type' => $group['transaction_type'],
                        'min_amount' => $min,
                        'max_amount' => $max,
                    ],
                    [
                        'approval_steps' => $steps,
                        'is_active' => true,
                    ],
                );
            }
        }

        // catch-all — يغطي أي مبلغ لم تطابقه قاعدة أضيق
        $catchAllSteps = [
            ['role' => 'department_manager', 'stage' => 1],
            ['role' => 'executive', 'stage' => 2],
            ['role' => 'finance', 'stage' => 3],
        ];

        foreach ([
            ApprovalRule::TYPE_EXPENSE,
            ApprovalRule::TYPE_CUSTODY,
            ApprovalRule::TYPE_QUOTE,
            ApprovalRule::TYPE_CONTRACT,
            ApprovalRule::TYPE_LEAVE,
            ApprovalRule::TYPE_OVERTIME,
            ApprovalRule::TYPE_EXCUSE,
            ApprovalRule::TYPE_DELEGATION,
        ] as $type) {
            ApprovalRule::query()->firstOrCreate(
                [
                    'transaction_type' => $type,
                    'min_amount' => 0,
                    'max_amount' => 999999999,
                ],
                [
                    'approval_steps' => $catchAllSteps,
                    'is_active' => true,
                ],
            );
        }
    }
}
