<?php

namespace Tests\Feature;

use App\Livewire\Meetings\OpenDecisionsIndex;
use App\Livewire\Partnerships\OrganizationsIndex;
use App\Livewire\Partnerships\PartnershipShow;
use App\Models\MeetingItem;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\Partnership;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PartnershipStageChanged;
use App\Services\PartnershipPipelineService;
use App\Services\TaskLifecycleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 1 — PART-1..5 + MEET-1.
 */
class ReportRound1PartMeetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_other_organization_type_requires_free_text(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['partnerships.organizations.view', 'partnerships.organizations.manage']);

        Livewire::actingAs($user)
            ->test(OrganizationsIndex::class)
            ->call('openCreate')
            ->set('name', 'جهة مستقلة')
            ->set('type', 'أخرى')
            ->call('save')
            ->assertHasErrors(['typeOther']);

        Livewire::actingAs($user)
            ->test(OrganizationsIndex::class)
            ->call('openCreate')
            ->set('name', 'جهة مستقلة')
            ->set('type', 'أخرى')
            ->set('typeOther', 'مركز تدريب أهلي')
            ->call('save')
            ->assertHasNoErrors();

        $org = Organization::query()->where('name', 'جهة مستقلة')->first();
        $this->assertSame('أخرى', $org?->type);
        $this->assertSame('مركز تدريب أهلي', $org?->type_other);
        $this->assertSame('مركز تدريب أهلي', $org?->typeLabel());
    }

    public function test_organization_project_status_is_arabic(): void
    {
        $this->assertSame('نشط', (new Project(['status' => 'active']))->statusLabel());
        $this->assertSame('مكتمل', (new Project(['status' => 'completed']))->statusLabel());
        $this->assertSame('متوقف', (new Project(['status' => 'on_hold']))->statusLabel());
    }

    public function test_partnership_contract_section_is_visible(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['partnerships.pipeline.view']);
        $partnership = Partnership::create([
            'organization_id' => Organization::create(['name' => 'جمعية النور'])->id,
            'entity_name' => 'جمعية النور',
            'stage' => Partnership::STAGE_OPPORTUNITY,
            'stage_entered_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(PartnershipShow::class, ['partnership' => $partnership])
            ->assertSee('عقد الشراكة')
            ->assertSee('دورة حياة الرحلة')
            ->assertSee('فرصة')
            ->assertSee('تنفيذ');
    }

    public function test_stage_move_mails_organization_contacts(): void
    {
        Notification::fake();

        $organization = Organization::create(['name' => 'جمعية النور']);
        OrganizationContact::create([
            'organization_id' => $organization->id,
            'name' => 'مسؤول',
            'email' => 'partner@example.com',
            'is_primary' => true,
        ]);
        $partnership = Partnership::create([
            'organization_id' => $organization->id,
            'entity_name' => 'جمعية النور',
            'stage' => Partnership::STAGE_OPPORTUNITY,
            'stage_entered_at' => now(),
        ]);

        app(PartnershipPipelineService::class)->moveTo($partnership, Partnership::STAGE_CONTACT, null, 'أول تواصل');

        Notification::assertSentOnDemand(PartnershipStageChanged::class);
    }

    public function test_legacy_guest_route_removed(): void
    {
        $this->assertFalse(Route::has('partnership.guest'));
    }

    public function test_open_decision_closes_with_reason(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo(['meetings.view', 'meetings.update']);
        $item = MeetingItem::factory()->create([
            'decision' => 'اعتماد الميزانية',
            'status' => 'open',
        ]);

        Livewire::actingAs($admin)
            ->test(OpenDecisionsIndex::class)
            ->call('selectMeeting', $item->meeting_id)
            ->call('openClose', $item->id)
            ->set('closeReason', 'نُفّذ في الاجتماع التالي')
            ->call('closeDecision')
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame('done', $item->status);
        $this->assertSame('نُفّذ في الاجتماع التالي', $item->close_reason);
        $this->assertNotNull($item->closed_at);
    }

    public function test_completing_linked_task_closes_decision(): void
    {
        $assigner = User::factory()->create();
        $assignee = User::factory()->create();
        $task = Task::factory()->create([
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'status' => 'pending_review',
        ]);
        $item = MeetingItem::factory()->create([
            'decision' => 'قرار مربوط',
            'status' => 'in_progress',
            'task_id' => $task->id,
        ]);

        app(TaskLifecycleService::class)->recordFinalRating($task, $assigner, 'متميز');

        $item->refresh();
        $this->assertSame('done', $item->status);
        $this->assertSame('أُغلقت بإكمال المهمة', $item->close_reason);
    }
}
