<?php

namespace Tests\Feature;

use App\Livewire\Documents\DocumentsIndex;
use App\Models\Document;
use App\Models\OrgUnit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentConfidentialityAccessTest extends TestCase
{
    use RefreshDatabase;

    protected OrgUnit $unitA;

    protected OrgUnit $unitB;

    protected User $uploader;

    protected User $sameDepartmentUser;

    protected User $otherDepartmentUser;

    protected Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');

        $adminA = OrgUnit::create(['name' => 'إدارة أ', 'level' => OrgUnit::LEVEL_ADMINISTRATION]);
        $adminB = OrgUnit::create(['name' => 'إدارة ب', 'level' => OrgUnit::LEVEL_ADMINISTRATION]);
        $this->unitA = OrgUnit::create([
            'name' => 'قسم أ',
            'level' => OrgUnit::LEVEL_UNIT,
            'parent_id' => $adminA->id,
        ]);
        $this->unitB = OrgUnit::create([
            'name' => 'قسم ب',
            'level' => OrgUnit::LEVEL_UNIT,
            'parent_id' => $adminB->id,
        ]);

        $this->uploader = User::factory()->create([
            'phone' => '0501111111',
            'org_unit_id' => $this->unitA->id,
            'must_change_password' => false,
        ]);

        $this->sameDepartmentUser = User::factory()->create([
            'phone' => '0502222222',
            'org_unit_id' => $this->unitA->id,
            'must_change_password' => false,
        ]);
        $this->sameDepartmentUser->givePermissionTo('documents.view');

        $this->otherDepartmentUser = User::factory()->create([
            'phone' => '0503333333',
            'org_unit_id' => $this->unitB->id,
            'must_change_password' => false,
        ]);
        $this->otherDepartmentUser->givePermissionTo('documents.view');

        Storage::disk('local')->put('documents/secret.pdf', 'secret-content');

        $this->document = Document::factory()->departmentConfidential()->create([
            'uploader_id' => $this->uploader->id,
            'path' => 'documents/secret.pdf',
        ]);
    }

    public function test_user_from_another_department_cannot_download_department_confidential_document(): void
    {
        $response = $this->actingAs($this->otherDepartmentUser)->get(
            route('documents.files.download', $this->document)
        );

        $response->assertForbidden();
    }

    public function test_user_from_same_department_can_download_department_confidential_document(): void
    {
        $response = $this->actingAs($this->sameDepartmentUser)->get(
            route('documents.files.download', $this->document)
        );

        $response->assertOk();
    }

    public function test_index_shows_preview_when_uploader_eager_load_includes_org_unit(): void
    {
        $this->sameDepartmentUser->givePermissionTo('documents.create');

        Livewire::actingAs($this->sameDepartmentUser)
            ->test(DocumentsIndex::class)
            ->assertSee($this->document->title)
            ->assertSeeHtml('aria-label="معاينة المستند"')
            ->assertSeeHtml('aria-label="تحميل المستند"');

        $listed = Document::query()
            ->with(['uploader:id,name,org_unit_id'])
            ->findOrFail($this->document->id);

        $this->assertTrue(Gate::forUser($this->sameDepartmentUser)->allows('download', $listed));
        $this->assertNotNull($listed->uploader?->org_unit_id);
    }

    public function test_delete_denied_without_view_access_even_with_create_permission(): void
    {
        $this->otherDepartmentUser->givePermissionTo('documents.create');

        $this->assertFalse(Gate::forUser($this->otherDepartmentUser)->allows('view', $this->document));
        $this->assertFalse(Gate::forUser($this->otherDepartmentUser)->allows('delete', $this->document));
    }
}
