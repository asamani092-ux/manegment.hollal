<?php

namespace Tests\Feature;

use App\Livewire\Documents\DocumentTemplatesIndex;
use App\Livewire\Documents\DocumentsIndex;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Meeting;
use App\Models\MeetingAmendment;
use App\Models\User;
use App\Services\MeetingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Documents tab UAT round 1 — preview, templates visibility, amendment versions.
 */
class DocsTabRound1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_documents_index_has_preview_and_no_duplicate_nav_buttons(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['documents.view', 'documents.create']);

        Livewire::actingAs($user)
            ->test(DocumentsIndex::class)
            ->assertDontSee('مكتبة القوالب', false)
            ->assertDontSee('إدارة النسخ', false)
            ->assertDontSee('مركز التقارير', false);
    }

    public function test_template_upload_visibility_and_download(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo(['documents.view', 'documents.templates.manage']);

        Livewire::actingAs($admin)
            ->test(DocumentTemplatesIndex::class)
            ->assertDontSee('href="'.route('documents.index').'"', false)
            ->set('title', 'نموذج عقد')
            ->set('visibility', 'all')
            ->set('uploadFile', UploadedFile::fake()->create('tpl.pdf', 20, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $template = DocumentTemplate::query()->where('title', 'نموذج عقد')->first();
        $this->assertNotNull($template);
        $this->assertSame('all', $template->visibility);

        $response = $this->actingAs($admin)
            ->get(route('documents.templates.download', $template));
        $response->assertOk();
        $disposition = strtolower((string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString(strtolower(rawurlencode('نموذج عقد.pdf')), $disposition);
    }

    public function test_approve_amendment_creates_labeled_document_version(): void
    {
        $chair = User::factory()->create(['must_change_password' => false]);
        $chair->givePermissionTo(['meetings.view', 'meetings.update', 'documents.view']);

        $meeting = Meeting::factory()->create([
            'chair_id' => $chair->id,
            'approval_status' => Meeting::APPROVAL_APPROVED,
            'approved_by' => $chair->id,
            'approved_at' => now(),
            'version' => 1,
            'title' => 'اجتماع أرشفة',
        ]);

        $path = 'meetings/orig.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 orig');
        $document = Document::create([
            'title' => 'محضر اجتماع: اجتماع أرشفة',
            'category' => 'محاضر_الاجتماعات',
            'source_type' => 'meeting',
            'source_id' => $meeting->id,
            'is_auto_archived' => true,
            'confidentiality' => 'department',
            'uploader_id' => $chair->id,
            'path' => $path,
            'current_version' => 1,
        ]);
        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => 1,
            'path' => $path,
            'change_note' => 'النسخة الأصلية المعتمدة',
            'uploaded_by' => $chair->id,
        ]);
        $meeting->update(['archived_document_id' => $document->id]);

        $amendment = MeetingAmendment::create([
            'meeting_id' => $meeting->id,
            'version' => 2,
            'note' => 'تصحيح بند',
            'status' => MeetingAmendment::STATUS_PENDING,
            'requested_by' => $chair->id,
            'created_at' => now(),
        ]);

        app(MeetingService::class)->approveAmendment($amendment, $chair);

        $this->assertSame(2, (int) $meeting->fresh()->version);
        $this->assertSame(2, DocumentVersion::where('document_id', $document->id)->count());
        $v2 = DocumentVersion::where('document_id', $document->id)->where('version', 2)->first();
        $this->assertNotNull($v2);
        $this->assertStringContainsString('معدَّل بتاريخ', (string) $v2->change_note);
        $this->assertStringContainsString($chair->name, (string) $v2->change_note);
        $this->assertSame(2, (int) $document->fresh()->current_version);
        $this->assertSame($v2->path, $document->fresh()->path);
        $this->assertSame($path, DocumentVersion::where('document_id', $document->id)->where('version', 1)->value('path'));
    }
}
