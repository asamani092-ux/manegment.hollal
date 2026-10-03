<?php

namespace App\Services;

use App\Models\EmployeeOnboardingItem;
use App\Models\User;

/**
 * Checklist rows from the active onboarding list. Time: O(steps) | Space: O(steps).
 */
class OnboardingChecklistService
{
    public function generate(User $user): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('employee_onboarding_items')) {
            return;
        }
        $items = app(ReferenceListService::class)->activeItems('onboarding_steps');
        foreach ($items as $item) {
            EmployeeOnboardingItem::query()->firstOrCreate(
                ['user_id' => $user->id, 'reference_item_id' => $item->id],
                ['status' => 'open']
            );
        }
    }
}
