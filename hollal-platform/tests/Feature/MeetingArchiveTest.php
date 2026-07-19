<?php

namespace Tests\Feature;

use App\Livewire\DashboardIndex;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\User;
use App\Services\MeetingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 03-B2 — auto-archive approved minutes (read-only) + stale decisions surfacing.
 */
class MeetingArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_approval_creates_read_only_archive(): void
    {
        $chair = User::factory()->create();
        $meeting = Meeting::factory()->create(['chair_id' => $chair->id, 'approval_status' => 'مسودة']);

        app(MeetingService::class)->approveMinutes($meeting, $chair);

        $document = Document::where('source_type', 'meeting')->where('source_id', $meeting->id)->first();
        $this->assertNotNull($document);
        $this->assertTrue($document->is_auto_archived);
        $this->assertSame('محاضر_الاجتماعات', $document->category);
        $this->assertSame($document->id, $meeting->fresh()->archived_document_id);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_archived_document_is_not_editable_or_deletable(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('documents.create');
        $document = Document::create([
            'title' => 'محضر',
            'category' => 'محاضر_الاجتماعات',
            'source_type' => 'meeting',
            'source_id' => 1,
            'is_auto_archived' => true,
            'confidentiality' => 'department',
            'uploader_id' => $admin->id,
            'path' => 'meetings/x.pdf',
        ]);

        $this->assertFalse(Gate::forUser($admin)->allows('update', $document));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $document));
    }

    public function test_stale_decision_surfaces_on_dashboard(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('dashboard.view');
        $meeting = Meeting::factory()->create();

        $item = MeetingItem::create([
            'meeting_id' => $meeting->id,
            'topic' => 'اعتماد الميزانية المتأخر',
            'item_kind' => 'قرار',
            'decision' => 'اعتماد الميزانية',
            'responsible_id' => $user->id,
            'status' => 'open',
        ]);
        $item->forceFill(['created_at' => now()->subDays(45)])->save();

        Livewire::actingAs($user)
            ->test(DashboardIndex::class)
            ->assertSee('اعتماد الميزانية المتأخر');
    }

    public function test_stale_scope_selects_old_open_decisions(): void
    {
        $meeting = Meeting::factory()->create();

        $stale = MeetingItem::create(['meeting_id' => $meeting->id, 'topic' => 'قديم', 'decision' => 'قرار', 'status' => 'open']);
        $stale->forceFill(['created_at' => now()->subDays(40)])->save();

        MeetingItem::create(['meeting_id' => $meeting->id, 'topic' => 'حديث', 'decision' => 'قرار', 'status' => 'open']);

        $ids = MeetingItem::query()->stale(30)->pluck('id');

        $this->assertTrue($ids->contains($stale->id));
        $this->assertCount(1, $ids);
    }
}
