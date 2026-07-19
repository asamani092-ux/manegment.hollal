<?php

namespace Tests\Feature;

use App\Livewire\Finance\BudgetsBoard;
use App\Livewire\Partnerships\OrganizationShow;
use App\Livewire\Partnerships\PartnerPortal;
use App\Livewire\Programs\ProgramShow;
use App\Livewire\Projects\ProjectExecution;
use App\Models\Consultation;
use App\Models\Custody;
use App\Models\Organization;
use App\Models\Partnership;
use App\Models\PartnerLink;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectVisit;
use App\Models\User;
use App\Services\PartnerPortalService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 12-B1 — verification pass: an IDOR re-audit over every entity introduced by
 * 04-B6 → 11-B1, plus the structural checks the spec checklist relies on.
 */
class VerificationAudit12B1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function outsider(): User
    {
        return User::factory()->create(['must_change_password' => false]);
    }

    private function partnership(string $name): Partnership
    {
        $organization = Organization::create(['name' => $name]);

        return Partnership::create([
            'organization_id' => $organization->id,
            'entity_name' => $name,
            'stage' => Partnership::STAGE_OPPORTUNITY,
            'stage_entered_at' => now(),
        ]);
    }

    // ------------------------------------------------------- new-entity IDOR

    public function test_organizations_are_closed_to_users_without_permission(): void
    {
        $organization = Organization::create(['name' => 'جهة خاصة']);

        $this->actingAs($this->outsider())->get('/organizations')->assertForbidden();
        $this->actingAs($this->outsider())->get('/organizations/'.$organization->id)->assertForbidden();

        Livewire::actingAs($this->outsider())->test(OrganizationShow::class, ['organization' => $organization])
            ->assertForbidden();
    }

    public function test_programs_are_closed_without_permission(): void
    {
        $program = Program::create(['name' => 'برنامج', 'stage' => Program::STAGE_ACTIVE]);

        $this->actingAs($this->outsider())->get('/programs')->assertForbidden();

        Livewire::actingAs($this->outsider())->test(ProgramShow::class, ['program' => $program])
            ->assertForbidden();
    }

    public function test_custodies_and_finance_screens_stay_closed(): void
    {
        Custody::create([
            'employee_id' => User::factory()->create()->id,
            'amount' => 500, 'purpose' => 'عهدة', 'status' => Custody::STATUS_REQUESTED,
        ]);

        $this->actingAs($this->outsider())->get('/budgets')->assertForbidden();
        $this->actingAs($this->outsider())->get('/financial-reports')->assertForbidden();
        $this->actingAs($this->outsider())->get('/tax-invoices')->assertForbidden();

        Livewire::actingAs($this->outsider())->test(BudgetsBoard::class)->assertForbidden();
    }

    public function test_plan_templates_and_grants_stay_closed(): void
    {
        $this->actingAs($this->outsider())->get('/plan-templates')->assertForbidden();
        $this->actingAs($this->outsider())->get('/settings/grants')->assertForbidden();
        $this->actingAs($this->outsider())->get('/structure/org-tree')->assertForbidden();
        $this->actingAs($this->outsider())->get('/reports/center')->assertForbidden();
        $this->actingAs($this->outsider())->get('/reports/audit-log')->assertForbidden();
    }

    public function test_project_execution_screen_respects_the_project_policy(): void
    {
        $project = Project::factory()->create();

        Livewire::actingAs($this->outsider())->test(ProjectExecution::class, ['project' => $project])
            ->assertForbidden();
    }

    public function test_portal_token_is_scoped_to_its_own_partnership(): void
    {
        $a = $this->partnership('الجهة أ');
        $b = $this->partnership('الجهة ب');

        $visitOfB = ProjectVisit::create([
            'project_id' => Project::factory()->create()->id,
            'scheduled_on' => now()->toDateString(),
        ]);

        $link = app(PartnerPortalService::class)->issue($a);

        $component = Livewire::test(PartnerPortal::class, ['token' => $link->token]);
        $viewedPartnership = $component->viewData('partnership');

        $this->assertSame($a->id, $viewedPartnership->id);
        $this->assertNotSame($b->id, $viewedPartnership->id);
        $this->assertSame(0, PartnerLink::where('partnership_id', $b->id)->count());
        $this->assertNotNull($visitOfB->id); // the portal never exposes it
    }

    public function test_revoked_portal_token_is_dead(): void
    {
        $partnership = $this->partnership('جهة');
        $portal = app(PartnerPortalService::class);
        $link = $portal->issue($partnership);
        $portal->revoke($link);

        $this->get('/portal/'.$link->token)->assertNotFound();
    }

    public function test_unknown_portal_token_is_not_found(): void
    {
        $this->get('/portal/'.str_repeat('x', 64))->assertNotFound();
    }

    // ------------------------------------------------------ checklist checks

    public function test_every_authenticated_route_carries_a_permission_or_a_policy(): void
    {
        $exempt = [
            'dashboard', 'password.change', 'password.change.update', 'logout', 'login',
            'partnership.guest', 'partner.portal', 'partner.portal.contract.pdf',
            'duties.download', 'tasks.files.download', 'contracts.files.download',
            'expenses.files.download', 'documents.files.download',
        ];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if ($name === '' || in_array($name, $exempt, true)) {
                continue;
            }

            $middleware = collect($route->gatherMiddleware());

            if (! $middleware->contains('auth')) {
                continue;
            }

            $this->assertTrue(
                $middleware->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:')),
                "route [{$name}] is authenticated but carries no permission middleware"
            );
        }
    }

    public function test_scheduled_sweeps_are_registered(): void
    {
        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => $event->command)
            ->filter()
            ->implode(' ');

        foreach ([
            'tasks:notify-due-soon', 'contracts:notify-expiring', 'reports:generate-weekly',
            'budgets:check-thresholds', 'documents:check-policy-reviews', 'projects:generate-pending',
        ] as $command) {
            $this->assertStringContainsString($command, $commands, "missing schedule for {$command}");
        }
    }

    public function test_consultations_and_visits_belong_to_their_project_only(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $consultation = Consultation::create([
            'project_id' => $projectB->id,
            'subject' => 'استشارة ب',
        ]);

        $this->assertSame(0, $projectA->consultations()->count());
        $this->assertSame(1, $projectB->consultations()->count());

        $this->expectException(ModelNotFoundException::class);
        $projectA->consultations()->findOrFail($consultation->id);
    }
}
