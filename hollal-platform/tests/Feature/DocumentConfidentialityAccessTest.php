<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentConfidentialityAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Department $departmentA;

    protected Department $departmentB;

    protected User $uploader;

    protected User $sameDepartmentUser;

    protected User $otherDepartmentUser;

    protected Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');

        $this->departmentA = Department::create(['name' => 'قسم أ']);
        $this->departmentB = Department::create(['name' => 'قسم ب']);

        $this->uploader = User::factory()->create([
            'phone' => '0501111111',
            'department_id' => $this->departmentA->id,
        ]);

        $this->sameDepartmentUser = User::factory()->create([
            'phone' => '0502222222',
            'department_id' => $this->departmentA->id,
        ]);
        $this->sameDepartmentUser->givePermissionTo('documents.view');

        $this->otherDepartmentUser = User::factory()->create([
            'phone' => '0503333333',
            'department_id' => $this->departmentB->id,
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
