<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected User $uploader;

    protected User $outsider;

    protected Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');

        $this->uploader = User::factory()->create(['phone' => '0501111111', 'must_change_password' => false]);
        $this->uploader->givePermissionTo(['documents.view', 'documents.create']);

        $this->outsider = User::factory()->create(['phone' => '0502222222', 'must_change_password' => false]);
        $this->outsider->givePermissionTo(['documents.view']);

        Storage::disk('local')->put('documents/sample.pdf', 'document-content');

        $project = Project::factory()->create(['manager_id' => $this->uploader->id]);

        $this->document = Document::factory()->create([
            'uploader_id' => $this->uploader->id,
            'project_id' => $project->id,
            'path' => 'documents/sample.pdf',
            'confidentiality' => 'team',
        ]);
    }

    public function test_guest_is_redirected_when_downloading_document(): void
    {
        $this->get(route('documents.files.download', $this->document))
            ->assertRedirect(route('login'));
    }

    public function test_unrelated_user_receives_forbidden_when_downloading_document(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('documents.files.download', $this->document))
            ->assertForbidden();
    }

    public function test_uploader_can_download_document(): void
    {
        $this->actingAs($this->uploader)
            ->get(route('documents.files.download', $this->document))
            ->assertOk()
            ->assertDownload('sample.pdf');
    }
}
