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
    }
}
