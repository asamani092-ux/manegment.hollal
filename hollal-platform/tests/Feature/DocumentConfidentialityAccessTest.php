<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\OrgUnit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
}
