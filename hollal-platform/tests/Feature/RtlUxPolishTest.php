<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RtlUxPolishTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->create(['must_change_password' => false]);
        $this->manager->assignRole('General Manager');
    }

    public function test_tasks_screen_renders_ds_page_rtl_and_cards(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('tasks.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('ds-page-rtl', $html);
        $this->assertStringContainsString('ds-task-card', $html);
        $this->assertStringContainsString('ds-filters-row', $html);
    }

    public function test_meetings_screen_renders_ds_page_rtl_and_cards(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('meetings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('ds-page-rtl', $html);
        $this->assertStringContainsString('ds-meeting-cards-grid', $html);
        $this->assertStringContainsString('ds-empty-state', $html);

        $css = (string) file_get_contents(public_path('css/components.css'));
        $this->assertStringContainsString('minmax(160px, 200px)', $css);
        $this->assertStringContainsString('max-width: 200px', $css);
    }

    public function test_expenses_screen_renders_ds_page_rtl_and_mobile_cards(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('expenses.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('ds-page-rtl', $html);
        $this->assertStringContainsString('ds-list-cards-mobile', $html);
        $this->assertStringContainsString('ds-page-header-bar', $html);
    }

    public function test_arabic_validation_messages_available(): void
    {
        app()->setLocale('ar');

        $this->assertSame('حقل العنوان مطلوب.', __('validation.required', ['attribute' => 'العنوان']));
        $this->assertFileExists(lang_path('ar/validation.php'));
    }
}
