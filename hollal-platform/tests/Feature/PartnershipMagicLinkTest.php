<?php

namespace Tests\Feature;

use App\Models\Partnership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnershipMagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_partnership_guest_token_is_accessible(): void
    {
        Partnership::query()->create([
            'entity_name' => 'شركة الاختبار',
            'magic_link_token' => 'valid-guest-token',
            'token_expires_at' => now()->addDay(),
            'status' => 'active',
        ]);

        $this->get(route('partnership.guest', 'valid-guest-token'))
            ->assertOk()
            ->assertSee('شركة الاختبار');
    }

    public function test_expired_partnership_guest_token_returns_not_found(): void
    {
        Partnership::query()->create([
            'entity_name' => 'شركة منتهية',
            'magic_link_token' => 'expired-guest-token',
            'token_expires_at' => now()->subMinute(),
            'status' => 'active',
        ]);

        $this->get(route('partnership.guest', 'expired-guest-token'))
            ->assertNotFound();
    }
}
