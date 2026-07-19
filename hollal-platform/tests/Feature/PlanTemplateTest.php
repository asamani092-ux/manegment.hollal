<?php

namespace Tests\Feature;

use App\Livewire\Programs\PlanTemplateEditor;
use App\Models\PlanTemplate;
use App\Models\TemplateItem;
use App\Models\User;
use App\Services\PlanTemplateService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 06A-B2 — five-level template editor, seeded plans (61 + 135), versioning,
 * and the review flag that blocks generation.
 */
class PlanTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function editor(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('projects.templates.manage');

        return $user;
    }

    public function test_seeded_plans_match_the_documented_structure_counts(): void
    {
        $this->seed(PlanTemplateSeeder::class);

        $hollal = PlanTemplate::where('kind', PlanTemplate::KIND_HOLLAL)->firstOrFail();
        $entity = PlanTemplate::where('kind', PlanTemplate::KIND_ENTITY)->firstOrFail();

        $this->assertSame(61, $hollal->currentVersion->items()->count());
        $this->assertSame(135, $entity->currentVersion->items()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PlanTemplateSeeder::class);
        $this->seed(PlanTemplateSeeder::class);

        $this->assertSame(2, PlanTemplate::count());
        $this->assertSame(61, PlanTemplate::where('kind', PlanTemplate::KIND_HOLLAL)->firstOrFail()
            ->currentVersion->items()->count());
    }

    public function test_seeded_templates_use_the_five_level_tree_and_both_item_kinds(): void
    {
        $this->seed(PlanTemplateSeeder::class);
        $version = PlanTemplate::where('kind', PlanTemplate::KIND_HOLLAL)->firstOrFail()->currentVersion;

        $this->assertSame([1, 2, 3, 4, 5], $version->items()->distinct()->orderBy('level')->pluck('level')->all());
        $this->assertTrue($version->items()->where('item_kind', TemplateItem::KIND_SERVICE)->exists());
        $this->assertTrue($version->items()->where('item_kind', TemplateItem::KIND_MANDATORY)->exists());
    }

    public function test_generation_is_blocked_while_the_review_flag_is_set(): void
    {
        $this->seed(PlanTemplateSeeder::class);
        $template = PlanTemplate::where('kind', PlanTemplate::KIND_HOLLAL)->firstOrFail();
        $service = app(PlanTemplateService::class);

        $this->assertTrue($template->needs_review);

        try {
            $service->assertGeneratable($template);
            $this->fail('generation should be blocked while the template needs review');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('جلسة المراجعة', $e->getMessage());
        }

        $service->markReviewed($template, $this->editor(), 'روجعت الإزاحات والأدوار');

        $service->assertGeneratable($template->fresh());
        $this->assertFalse($template->fresh()->needs_review);
    }

    public function test_a_sixth_level_is_rejected(): void
    {
        $service = app(PlanTemplateService::class);
        $template = $service->create('قالب اختبار', PlanTemplate::KIND_INTERNAL);
        $version = $template->currentVersion;

        $parent = null;
        for ($level = 1; $level <= 5; $level++) {
            $parent = $service->addItem($version, ['title' => 'مستوى '.$level], $parent);
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->addItem($version, ['title' => 'مستوى 6'], $parent);
    }

    public function test_a_new_version_copies_items_and_leaves_the_old_version_untouched(): void
    {
        $service = app(PlanTemplateService::class);
        $template = $service->create('قالب', PlanTemplate::KIND_INTERNAL);
        $first = $template->currentVersion;
        $service->addItem($first, ['title' => 'مرحلة أولى']);

        $second = $service->newVersion($template->fresh(), null, 'تعديل');
        $service->addItem($second, ['title' => 'مرحلة ثانية']);

        $this->assertSame(1, $first->items()->count());
        $this->assertSame(2, $second->items()->count());
        $this->assertFalse($first->fresh()->is_current);
        $this->assertTrue($second->fresh()->is_current);
    }

    public function test_service_items_are_only_generated_when_the_service_was_sold(): void
    {
        $service = app(PlanTemplateService::class);
        $template = $service->create('قالب', PlanTemplate::KIND_INTERNAL);
        $version = $template->currentVersion;

        $service->addItem($version, ['title' => 'بند إلزامي']);
        $service->addItem($version, [
            'title' => 'بند تدريب',
            'item_kind' => TemplateItem::KIND_SERVICE,
            'service_type' => 'تدريب',
        ]);
        $service->addItem($version, [
            'title' => 'بند زيارة',
            'item_kind' => TemplateItem::KIND_SERVICE,
            'service_type' => 'زيارة',
        ]);

        $selected = $service->itemsForServices($version, ['تدريب']);

        $this->assertCount(2, $selected);
        $this->assertSame(['بند إلزامي', 'بند تدريب'], $selected->pluck('title')->all());
    }

    public function test_unknown_item_kind_is_rejected(): void
    {
        $service = app(PlanTemplateService::class);
        $version = $service->create('قالب', PlanTemplate::KIND_INTERNAL)->currentVersion;

        $this->expectException(\InvalidArgumentException::class);
        $service->addItem($version, ['title' => 'بند', 'item_kind' => 'نوع مجهول']);
    }

    public function test_editor_requires_the_templates_permission(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->get('/plan-templates')->assertForbidden();
    }

    public function test_editor_adds_an_item_as_a_new_version(): void
    {
        $this->seed(PlanTemplateSeeder::class);
        $template = PlanTemplate::where('kind', PlanTemplate::KIND_HOLLAL)->firstOrFail();

        Livewire::actingAs($this->editor())->test(PlanTemplateEditor::class)
            ->call('selectTemplate', $template->id)
            ->call('openItemModal')
            ->set('title', 'مرحلة إضافية')
            ->call('addItem')
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertSame(2, $template->versions()->count());
        $this->assertSame(62, $template->currentVersion->items()->count());
    }

    public function test_editor_clears_the_review_flag(): void
    {
        $this->seed(PlanTemplateSeeder::class);
        $template = PlanTemplate::where('kind', PlanTemplate::KIND_ENTITY)->firstOrFail();

        Livewire::actingAs($this->editor())->test(PlanTemplateEditor::class)
            ->call('selectTemplate', $template->id)
            ->set('reviewNote', 'جلسة مراجعة مع عبدالله')
            ->call('markReviewed');

        $this->assertFalse($template->fresh()->needs_review);
        $this->assertSame('جلسة مراجعة مع عبدالله', $template->fresh()->review_note);
    }
}
