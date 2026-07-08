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

    public function test_minutes_page_renders_for_authorized_user(): void
    {
        $this->actingAs($this->authorized)
            ->get(route('meetings.minutes', $this->meeting))
            ->assertOk()
            ->assertSee('طباعة المحضر PDF', false);
    }
}
