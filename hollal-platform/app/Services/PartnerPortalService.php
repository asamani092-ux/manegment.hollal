<?php

namespace App\Services;

use App\Models\Partnership;
use App\Models\PartnerLink;
use App\Models\PartnerPortalActivity;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Support\Str;

/**
 * 05-B5 — the unique partner link.
 *
 * A token resolves to exactly one partnership; nothing else is reachable
 * through it. Every portal action is written to the portal activity log,
 * attributed to the partner organization.
 */
class PartnerPortalService
{
    public function issue(Partnership $partnership, ?User $actor = null, ?int $days = null): PartnerLink
    {
        $days ??= (int) Setting::get('links.default_expiry_days', 7);

        return PartnerLink::create([
            'partnership_id' => $partnership->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays($days),
            'is_revoked' => false,
            'created_by' => $actor?->id,
        ]);
    }

    public function revoke(PartnerLink $link): PartnerLink
    {
        $link->forceFill(['is_revoked' => true])->save();

        return $link;
    }

    public function renew(PartnerLink $link, ?int $days = null): PartnerLink
    {
        $days ??= (int) Setting::get('links.default_expiry_days', 7);

        $link->forceFill(['is_revoked' => false, 'expires_at' => now()->addDays($days)])->save();

        return $link;
    }

    /** Resolve a token to its link, or null when expired/revoked/unknown. */
    public function resolve(string $token): ?PartnerLink
    {
        $link = PartnerLink::query()->where('token', $token)->first();

        if (! $link || ! $link->isUsable()) {
            return null;
        }

        $link->forceFill(['last_used_at' => now()])->save();

        return $link;
    }

    /** @param array<string, mixed> $metadata */
    public function log(PartnerLink $link, string $action, array $metadata = [], ?string $ip = null): PartnerPortalActivity
    {
        return PartnerPortalActivity::create([
            'partner_link_id' => $link->id,
            'partnership_id' => $link->partnership_id,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => $ip,
        ]);
    }
}
