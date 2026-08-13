<?php

namespace App\Services;

use App\Models\Partnership;
use App\Models\PartnerLink;
use App\Models\PartnerPortalActivity;
use App\Models\User;
use App\Notifications\PartnershipPortalInvite;
use App\Support\Setting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;

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

    public function portalUrl(string $token): string
    {
        return route('partner.portal', ['token' => $token]);
    }

    /**
     * Send the portal URL to the organization's contacts. The configured mail
     * driver records the message in local/UAT environments; no WhatsApp API is
     * called.
     */
    public function emailLink(PartnerLink $link, ?string $email = null): string
    {
        $link->loadMissing('partnership.organization.contacts');
        $url = $this->portalUrl($link->token);
        $emails = collect([$email])
            ->merge($link->partnership->organization?->contacts?->pluck('email') ?? [])
            ->filter(fn ($value) => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        foreach ($emails as $recipient) {
            Notification::route('mail', $recipient)
                ->notify(new PartnershipPortalInvite($link->partnership, $url));
        }

        $this->log($link, 'portal.invite_emailed', [
            'url' => $url,
            'recipients' => $emails->all(),
        ]);

        return $url;
    }

    public function whatsappText(PartnerLink $link): string
    {
        return 'مرحبًا، يمكنكم متابعة شراكتكم واختيار البرامج والكميات من خلال الرابط التالي: '
            .$this->portalUrl($link->token);
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
