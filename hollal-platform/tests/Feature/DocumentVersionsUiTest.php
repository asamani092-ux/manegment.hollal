<?php

namespace Tests\Feature;

use App\Livewire\Documents\DocumentVersionsIndex;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentVersionsUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    public function test_versions_index_opens_and_new_version_keeps_old(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        $document = Document::create([
            'title' => 'مستند تجريبي',
            'category' => 'عام',
            'path' => 'documents/old.pdf',
            'current_version' => 1,
            'uploader_id' => $admin->id,
            'confidentiality' => 'team',
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => 1,
            'path' => 'documents/old.pdf',
            'change_note' => 'أولى',
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('documents.versions'))->assertOk()->assertSee('إدارة النسخ', false);

        Livewire::actingAs($admin)
            ->test(DocumentVersionsIndex::class)
            ->call('openUpload')
            ->set('document_id', $document->id)
            ->set('change_note', 'ثانية')
            ->set('uploadFile', UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'))
            ->call('saveVersion')
            ->assertHasNoErrors();

        $this->assertSame(2, DocumentVersion::where('document_id', $document->id)->count());
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version' => 1, 'change_note' => 'أولى']);
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version' => 2, 'change_note' => 'ثانية']);
        $this->assertSame(2, (int) $document->fresh()->current_version);
    }
}
