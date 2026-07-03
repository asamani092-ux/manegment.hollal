<?php

namespace App\Livewire\Partnerships;

use App\Models\Partnership;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Public guest view for partners via magic_link_token (no auth).
 */
class PartnershipGuestView extends Component
{
    public Partnership $partnership;

    public function mount(string $token): void
    {
        $this->partnership = Partnership::query()
            ->where('magic_link_token', $token)
            ->where('token_expires_at', '>', now())
            ->with(['project:id,name'])
            ->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.partnerships.partnership-guest-view')
            ->layout('layouts.guest', ['title' => 'تفاصيل الشراكة']);
    }
}
