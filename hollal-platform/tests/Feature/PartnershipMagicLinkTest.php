<?php

namespace Tests\Feature;

use App\Models\Partnership;
use App\Services\PartnerPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PartnershipMagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_guest_route_is_removed(): void
    {
        $this->assertFalse(Route::has('partnership.guest'));
        $this->get('/partnership/guest/any-token')->assertNotFound();
    }

    public function test_valid_partner_portal_token_is_accessible(): void
    {
        $partnership = Partnership::query()->create([
            'entity_name' => 'شركة الاختبار',
            'status' => 'active',
        ]);
        $link = app(PartnerPortalService::class)->issue($partnership);

        $this->get(route('partner.portal', $link->token))
            ->assertOk()
            ->assertSee('شركة الاختبار');
    }

    public function test_expired_partner_portal_token_returns_not_found(): void
    {
        $partnership = Partnership::query()->create([
            'entity_name' => 'شركة منتهية',
            'status' => 'active',
        ]);
        $link = app(PartnerPortalService::class)->issue($partnership);
        $link->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get(route('partner.portal', $link->token))->assertNotFound();
    }
}
