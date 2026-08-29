<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingMinutesPdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $authorized;

    protected User $outsider;

    protected Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->authorized = User::factory()->create([
            'phone' => '0501111111',
            'must_change_password' => false,
        ]);
        $this->authorized->givePermissionTo(['meetings.view', 'meetings.update']);

        $this->outsider = User::factory()->create([
            'phone' => '0502222222',
            'must_change_password' => false,
        ]);

        $this->meeting = Meeting::factory()->create([
            'chair_id' => $this->authorized->id,
            'title' => 'اجتماع اختبار',
            'agenda' => 'جدول أعمال تجريبي',
        ]);
    }

    public function test_authorized_user_can_download_minutes_pdf(): void
    {
        $response = $this->actingAs($this->authorized)
            ->get(route('meetings.minutes.pdf', $this->meeting));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_unauthorized_user_receives_forbidden_on_pdf(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('meetings.minutes.pdf', $this->meeting))
            ->assertForbidden();
    }

    public function test_minutes_pdf_uses_pdf_arabic_pipeline(): void
    {
        $html = app(\App\Services\MeetingMinutesPdfService::class)->buildHtml($this->meeting);

        $this->assertStringContainsString('direction: rtl', $html);
        $this->assertStringContainsString('font-family: ibmplex', $html);
        $this->assertStringContainsString('pdf-meta', $html);
        $this->assertStringContainsString('جدول الأعمال', $html);

        $bytes = app(\App\Services\MeetingMinutesPdfService::class)->output($this->meeting);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame('ibmplex', \App\Support\PdfArabic::defaultFont());
    }

    public function test_minutes_page_renders_for_authorized_user(): void
    {
        $this->actingAs($this->authorized)
            ->get(route('meetings.minutes', $this->meeting))
            ->assertOk()
            ->assertSee('بنود المحضر', false);
    }
}
