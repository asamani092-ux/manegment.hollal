<?php

namespace Tests\Feature;

use App\Livewire\Hr\PayScalesIndex;
use App\Models\PayScale;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\SalaryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 01-B2 — salary components persist month to month, editing opens a new row,
 * and grade assignment auto-creates the base component.
 */
class SalaryComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_open_ended_component_persists_across_months(): void
    {
        $employee = User::factory()->create();
        SalaryComponent::create([
            'employee_id' => $employee->id,
            'type' => SalaryComponent::TYPE_BASE,
            'label_ar' => 'الراتب الأساسي',
            'amount' => 5000,
            'valid_from' => now()->subMonths(2)->startOfMonth(),
            'valid_to' => null,
            'is_active' => true,
        ]);

        $thisMonth = SalaryComponent::query()->effectiveOn(now())->count();
        $nextMonth = SalaryComponent::query()->effectiveOn(now()->addMonth())->count();

        $this->assertSame(1, $thisMonth);
        $this->assertSame(1, $nextMonth);
    }

    public function test_editing_closes_old_row_and_opens_new(): void
    {
        $employee = User::factory()->create();
        $original = SalaryComponent::create([
            'employee_id' => $employee->id,
            'type' => SalaryComponent::TYPE_ALLOWANCE,
            'label_ar' => 'بدل سكن',
            'amount' => 1000,
            'valid_from' => now()->subMonth()->startOfMonth(),
            'valid_to' => null,
            'is_active' => true,
        ]);

        $new = app(SalaryService::class)->edit($original, ['amount' => 1500]);

        $original->refresh();
        $this->assertFalse($original->is_active);
        $this->assertEquals(today()->subDay()->toDateString(), $original->valid_to->toDateString());

        $this->assertTrue($new->is_active);
        $this->assertEquals(today()->toDateString(), $new->valid_from->toDateString());
        $this->assertSame('1500.00', $new->amount);

        // Only the new row is effective today.
        $this->assertSame(1, SalaryComponent::query()->effectiveOn(today())->count());
    }

    public function test_grade_assignment_creates_base_component(): void
    {
        $employee = User::factory()->create();
        $scale = PayScale::create([
            'name_ar' => 'السلم الإداري',
            'grades' => [
                ['label' => 'الدرجة الأولى', 'base_amount' => 8000],
                ['label' => 'الدرجة الثانية', 'base_amount' => 10000],
            ],
        ]);

        $component = app(SalaryService::class)->assignGrade($scale, $employee, 'الدرجة الثانية');

        $this->assertSame(SalaryComponent::TYPE_BASE, $component->type);
        $this->assertSame('10000.00', $component->amount);
        $this->assertTrue($component->is_active);
    }

    public function test_grade_assignment_replaces_previous_base(): void
    {
        $employee = User::factory()->create();
        $scale = PayScale::create([
            'name_ar' => 'السلم',
            'grades' => [['label' => 'أ', 'base_amount' => 6000], ['label' => 'ب', 'base_amount' => 7000]],
        ]);
        $service = app(SalaryService::class);

        $service->assignGrade($scale, $employee, 'أ');
        $service->assignGrade($scale, $employee, 'ب');

        $activeBase = SalaryComponent::query()
            ->where('employee_id', $employee->id)
            ->where('type', SalaryComponent::TYPE_BASE)
            ->effectiveOn(today())
            ->get();

        $this->assertCount(1, $activeBase);
        $this->assertSame('7000.00', $activeBase->first()->amount);
    }

    public function test_pay_scale_editor_requires_permission(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(PayScalesIndex::class)
            ->assertForbidden();
    }

    public function test_pay_scale_editor_saves_scale(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('hr.salaries.manage');

        Livewire::actingAs($manager)
            ->test(PayScalesIndex::class)
            ->call('openCreate')
            ->set('name_ar', 'سلم فني')
            ->set('grades', [['label' => 'مبتدئ', 'base_amount' => '4500']])
            ->call('save')
            ->assertDispatched('toast', type: 'success');

        $this->assertDatabaseHas('pay_scales', ['name_ar' => 'سلم فني']);
    }
}
