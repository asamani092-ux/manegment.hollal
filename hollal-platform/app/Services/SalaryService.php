<?php

namespace App\Services;

use App\Models\PayScale;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 01-B2 — salary component lifecycle. Editing never overwrites: the current row
 * is closed (valid_to = yesterday) and a new row opens today, preserving
 * history. Assigning a pay-scale grade auto-creates the base component.
 */
class SalaryService
{
    public const REGULAR_TYPE = 'دوام_كامل';

    public function edit(SalaryComponent $component, array $attributes): SalaryComponent
    {
        return DB::transaction(function () use ($component, $attributes) {
            $component->update([
                'valid_to' => today()->subDay(),
                'is_active' => false,
            ]);

            return SalaryComponent::create(array_merge([
                'employee_id' => $component->employee_id,
                'type' => $component->type,
                'label_ar' => $component->label_ar,
                'valid_from' => today(),
                'valid_to' => null,
                'is_active' => true,
            ], $attributes));
        });
    }

    public function assignGrade(PayScale $scale, User $employee, string $gradeLabel): SalaryComponent
    {
        $grade = $scale->grade($gradeLabel);

        if ($grade === null) {
            throw new \InvalidArgumentException('الدرجة غير موجودة في سلم الرواتب.');
        }

        return DB::transaction(function () use ($employee, $grade, $gradeLabel, $scale) {
            SalaryComponent::query()
                ->where('employee_id', $employee->id)
                ->where('type', SalaryComponent::TYPE_BASE)
                ->effectiveOn(today())
                ->update([
                    'valid_to' => today()->subDay(),
                    'is_active' => false,
                ]);

            $profile = $employee->profile()->firstOrCreate(
                ['user_id' => $employee->id],
                ['job_title' => $employee->name],
            );
            $profile->forceFill([
                'pay_scale_id' => $scale->id,
                'grade_label' => $gradeLabel,
            ])->save();

            return SalaryComponent::create([
                'employee_id' => $employee->id,
                'type' => SalaryComponent::TYPE_BASE,
                'label_ar' => 'الراتب الأساسي — '.$gradeLabel,
                'amount' => $grade['base_amount'],
                'valid_from' => today(),
                'is_active' => true,
            ]);
        });
    }

    /**
     * Set or replace base salary (cumulative: close old, open new).
     * Time: O(1) | Space: O(1)
     */
    public function setBaseAmount(User $employee, float $amount, ?string $labelAr = null): SalaryComponent
    {
        return DB::transaction(function () use ($employee, $amount, $labelAr) {
            SalaryComponent::query()
                ->where('employee_id', $employee->id)
                ->where('type', SalaryComponent::TYPE_BASE)
                ->effectiveOn(today())
                ->update([
                    'valid_to' => today()->subDay(),
                    'is_active' => false,
                ]);

            return SalaryComponent::create([
                'employee_id' => $employee->id,
                'type' => SalaryComponent::TYPE_BASE,
                'label_ar' => $labelAr ?: 'الراتب الأساسي',
                'amount' => $amount,
                'valid_from' => today(),
                'is_active' => true,
            ]);
        });
    }

    /**
     * Close an effective component without replacement.
     * Time: O(1) | Space: O(1)
     */
    public function closeComponent(SalaryComponent $component): void
    {
        $component->update([
            'valid_to' => today()->subDay(),
            'is_active' => false,
        ]);
    }

    /**
     * Time: O(1) | Space: O(1)
     */
    public function addComponent(User $employee, string $type, string $labelAr, float $amount): SalaryComponent
    {
        if ($type === SalaryComponent::TYPE_DEDUCTION && ! $this->isRegularEmployee($employee)) {
            throw new \InvalidArgumentException('الخصم الثابت متاح للموظف النظامي (دوام كامل) فقط.');
        }

        return SalaryComponent::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'label_ar' => $labelAr,
            'amount' => $amount,
            'valid_from' => today(),
            'is_active' => true,
        ]);
    }

    /**
     * Monthly salary derived from effective components.
     * Time: O(c) components | Space: O(1)
     *
     * @return array{base: float, allowances: float, deductions: float, monthly: float}
     */
    public function monthlyFromComponents(User $employee): array
    {
        $components = SalaryComponent::query()
            ->where('employee_id', $employee->id)
            ->effectiveOn(today())
            ->get(['type', 'amount']);

        $base = (float) $components->where('type', SalaryComponent::TYPE_BASE)->sum('amount');
        $allowances = (float) $components->where('type', SalaryComponent::TYPE_ALLOWANCE)->sum('amount');
        $deductions = $this->isRegularEmployee($employee)
            ? (float) $components->where('type', SalaryComponent::TYPE_DEDUCTION)->sum('amount')
            : 0.0;

        return [
            'base' => $base,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'monthly' => $base + $allowances - $deductions,
        ];
    }

    public function isRegularEmployee(User $employee): bool
    {
        $type = $employee->profile?->employment_type;

        return $type === null || $type === self::REGULAR_TYPE;
    }
}
