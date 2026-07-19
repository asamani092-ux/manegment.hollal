<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Partnership;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 00-B4 — data migration: entity extraction, reversed partnership↔project
 * relation, status→stage mapping, orphan projects marked داخلي.
 */
class HollalEntitiesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_mutate_the_database(): void
    {
        $project = Project::factory()->create();
        $partnership = Partnership::create([
            'entity_name' => 'جمعية النور',
            'status' => 'active',
            'project_id' => $project->id,
        ]);

        $this->artisan('migrate:hollal-entities --dry-run')->assertSuccessful();

        $this->assertSame(0, Organization::count());
        $this->assertNull($partnership->fresh()->organization_id);
        $this->assertNull($partnership->fresh()->stage);
        $this->assertNull($project->fresh()->partnership_id);
        // Legacy link untouched by a dry run.
        $this->assertSame($project->id, $partnership->fresh()->project_id);
    }

    public function test_extracts_organizations_and_reverses_relation(): void
    {
        $project = Project::factory()->create();
        $partnership = Partnership::create([
            'entity_name' => 'مدرسة الفلاح',
            'status' => 'active',
            'project_id' => $project->id,
        ]);

        $this->artisan('migrate:hollal-entities')->assertSuccessful();

        $org = Organization::where('name', 'مدرسة الفلاح')->first();
        $this->assertNotNull($org);
        $this->assertSame($org->id, $partnership->fresh()->organization_id);

        // Relation reversed onto the project; legacy column nulled.
        $this->assertSame($partnership->id, $project->fresh()->partnership_id);
        $this->assertSame('شراكة', $project->fresh()->kind);
        $this->assertNull($partnership->fresh()->project_id);

        // Status mapped to a journey stage.
        $this->assertSame(6, $partnership->fresh()->stage);
    }

    public function test_duplicate_entity_names_map_to_one_organization(): void
    {
        Partnership::create(['entity_name' => 'وقف الخير', 'status' => 'pending_form']);
        Partnership::create(['entity_name' => 'وقف الخير', 'status' => 'negotiation']);

        $this->artisan('migrate:hollal-entities')->assertSuccessful();

        $this->assertSame(1, Organization::where('name', 'وقف الخير')->count());
    }

    public function test_orphan_projects_are_marked_internal(): void
    {
        $orphan = Project::factory()->create(['kind' => 'شراكة']);

        $this->artisan('migrate:hollal-entities')->assertSuccessful();

        $this->assertSame('داخلي', $orphan->fresh()->kind);
        $this->assertNull($orphan->fresh()->partnership_id);
    }

    public function test_migration_is_idempotent(): void
    {
        $project = Project::factory()->create();
        Partnership::create([
            'entity_name' => 'شركة إتقان',
            'status' => 'completed',
            'project_id' => $project->id,
        ]);

        $this->artisan('migrate:hollal-entities')->assertSuccessful();
        $this->artisan('migrate:hollal-entities')->assertSuccessful();

        $this->assertSame(1, Organization::where('name', 'شركة إتقان')->count());
        $this->assertSame(8, Partnership::first()->stage);
    }
}
