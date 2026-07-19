<?php

namespace Tests\Feature;

use App\Livewire\Programs\ProgramShow;
use App\Livewire\Programs\ProgramsIndex;
use App\Models\Organization;
use App\Models\Partnership;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\Project;
use App\Models\User;
use App\Services\ProgramService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 06A-B1 — full program card: prices, versions (history preserved), files,
 * platform link + steps, derived executing entities, «مشروع تطوير».
 */
class ProgramCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);
    }

    private function program(): Program
    {
        return Program::create(['name' => 'برنامج القراءة', 'stage' => Program::STAGE_ACTIVE]);
    }

    private function manager(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['projects.programs.view', 'projects.programs.manage', 'projects.view']);

        return $user;
    }

    public function test_versioning_keeps_history(): void
    {
        $program = $this->program();
        $service = app(ProgramService::class);
        $editor = $this->manager();

        $first = $service->createVersion($program, 'v1', $editor, 'الإصدار الأول');
        $second = $service->createVersion($program->fresh(), 'v2', $editor, 'تحديث المحتوى');

        $this->assertSame(2, $program->versions()->count());
        $this->assertFalse($first->fresh()->is_current);
        $this->assertTrue($second->fresh()->is_current);
        $this->assertSame($second->id, $program->fresh()->current_version_id);
        $this->assertSame('الإصدار الأول', $first->fresh()->change_reason);
        $this->assertSame($editor->id, $first->fresh()->changed_by);
    }

    public function test_setting_prices_records_a_new_version(): void
    {
        $program = $this->program();

        app(ProgramService::class)->setPrices($program, [
            ProgramPrice::SERVICE_TRAINING => 5000,
            ProgramPrice::SERVICE_VISIT => 800,
        ], $this->manager());

        $this->assertSame(2, $program->prices()->count());
        $this->assertSame('5000.00', (string) $program->prices()->where('service_type', 'تدريب')->first()->unit_price);
        $this->assertSame(1, $program->versions()->count());
        $this->assertSame('تحديث الأسعار', $program->versions()->first()->change_reason);
    }

    public function test_unknown_service_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ProgramService::class)->setPrices($this->program(), ['خدمة وهمية' => 100]);
    }

    public function test_unauthorized_user_cannot_edit_prices(): void
    {
        $program = $this->program();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('projects.programs.view');

        Livewire::actingAs($viewer)->test(ProgramShow::class, ['program' => $program])
            ->set('prices.تدريب', '9999')
            ->call('savePrices')
            ->assertForbidden();

        $this->assertSame(0, $program->prices()->count());
    }

    public function test_manager_can_edit_prices_from_the_card(): void
    {
        $program = $this->program();

        Livewire::actingAs($this->manager())->test(ProgramShow::class, ['program' => $program])
            ->set('prices.'.ProgramPrice::SERVICE_PACKAGE, '1200')
            ->call('savePrices')
            ->assertHasNoErrors();

        $this->assertSame('1200.00', (string) $program->prices()->firstOrFail()->unit_price);
    }

    public function test_development_project_button_creates_an_internal_project(): void
    {
        $program = $this->program();
        $manager = $this->manager();

        Livewire::actingAs($manager)->test(ProgramShow::class, ['program' => $program])
            ->call('createDevelopmentProject');

        $project = Project::where('program_id', $program->id)->firstOrFail();
        $this->assertSame('داخلي', $project->kind);
        $this->assertSame($manager->id, $project->manager_id);
        $this->assertStringContainsString('مشروع تطوير', $project->name);
    }

    public function test_executing_entities_are_derived_from_projects(): void
    {
        $program = $this->program();
        $organization = Organization::create(['name' => 'مدارس الرياض']);
        $project = Project::factory()->create(['program_id' => $program->id]);
        Partnership::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'entity_name' => 'مدارس الرياض',
        ]);

        $entities = $program->executingOrganizations();

        $this->assertCount(1, $entities);
        $this->assertSame($organization->id, $entities->first()->id);
    }

    public function test_program_files_live_on_the_private_disk(): void
    {
        $program = $this->program();
        $manager = $this->manager();

        Livewire::actingAs($manager)->test(ProgramShow::class, ['program' => $program])
            ->set('fileTitle', 'دليل المعلم')
            ->set('fileKind', 'دليل المعلم')
            ->set('upload', \Illuminate\Http\UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'))
            ->call('uploadFile')
            ->assertHasNoErrors();

        $file = $program->files()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->assertStringStartsWith('programs/'.$program->id, $file->path);
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->get('/programs')->assertForbidden();
    }

    public function test_index_lists_programs(): void
    {
        $this->program();

        Livewire::actingAs($this->manager())->test(ProgramsIndex::class)
            ->assertOk()
            ->assertSee('برنامج القراءة');
    }

    public function test_index_create_requires_manage_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('projects.programs.view');

        Livewire::actingAs($viewer)->test(ProgramsIndex::class)
            ->call('openCreate')
            ->assertForbidden();
    }
}
