<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UAT phase-3 regressions: browser titles, Arabic login hints, GET logout.
 */
class UatPhase3FixTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('phone', '0500000000')->firstOrFail();
        $this->admin->forceFill(['must_change_password' => false])->save();
    }

    public function test_hr_and_archive_pages_set_arabic_document_title(): void
    {
        $paths = [
            '/attendance' => 'الحضور',
            '/leaves' => 'الإجازات',
            '/evaluations' => 'التقييم الربعي',
            '/meetings/archive' => 'أرشيف المحاضر',
        ];

        foreach ($paths as $path => $titlePart) {
            $this->actingAs($this->admin)
                ->get($path)
                ->assertOk()
                ->assertSee('<title>'.$titlePart.' — منصة حلل</title>', false);
        }
    }

    public function test_login_fields_expose_arabic_title_hints(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('title="يرجى إدخال رقم الجوال"', false)
            ->assertSee('title="يرجى إدخال كلمة المرور"', false);
    }

    public function test_get_logout_ends_session_and_redirects_to_login(): void
    {
        $this->actingAs($this->admin)
            ->get('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_expenses_default_tab_is_all_for_reviewer(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/expenses')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('لا توجد طلبات', $html);
    }
}
