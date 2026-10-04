<?php

namespace Tests\Feature;

use App\Livewire\Users\EmployeeProfileShow;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Queries per employee-file tab on the seeded trial dataset.
 * Time: O(tabs) | Space: O(1)
 */
class EmployeeFileQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_profile_tab_stays_within_fifteen_queries(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('phone', '0500000000')->firstOrFail();
        $admin->forceFill(['must_change_password' => false])->save();
        $employee = User::query()->whereKeyNot($admin->id)->whereHas('profile')->firstOrFail();

        $tabs = [
            'overview', 'personal', 'job', 'pay', 'attendance', 'documents',
            'performance', 'leaves', 'violations', 'custody', 'log',
        ];

        $component = Livewire::actingAs($admin)->test(EmployeeProfileShow::class, ['user' => $employee]);

        foreach ($tabs as $tab) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $component->call('setTab', $tab)->assertSet('activeTab', $tab);
            $count = count(DB::getQueryLog());
            $this->assertLessThanOrEqual(15, $count, "تبويب {$tab} نفّذ {$count} استعلامًا");
        }
    }
}
