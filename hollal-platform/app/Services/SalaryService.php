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

        return DB::transaction(function () use ($employee, $grade, $gradeLabel) {
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
                'label_ar' => 'الراتب الأساسي — '.$gradeLabel,
                'amount' => $grade['base_amount'],
                'valid_from' => today(),
                'is_active' => true,
            ]);
        });
    }
}
