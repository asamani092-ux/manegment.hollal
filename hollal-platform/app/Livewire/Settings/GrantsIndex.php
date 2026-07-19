<?php

namespace App\Livewire\Settings;

use App\Models\ExceptionalGrant;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionGrantService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 10-B1 — the grant screen: an «الكل» switch per role, section and action
 * toggles, exceptional per-user grants (reason + date, highlighted), and the
 * «من يملك ماذا» review matrix with its export.
 */
class GrantsIndex extends Component
{
    use AuthorizesRequests;

    public string $tab = 'roles'; // roles|exceptions|matrix

    public ?int $roleId = null;

    /** @var list<string> */
    public array $selected = [];

    // exceptional grant form
    public ?int $grantUserId = null;

    public ?string $grantPermission = null;

    public string $grantReason = '';

    public ?string $grantExpiresOn = null;

    public function mount(): void
    {
        $this->authorize('roles.view');
        $this->roleId = Role::orderBy('id')->value('id');
        $this->loadRole();
    }

    public function selectRole(int $roleId): void
    {
        $this->roleId = $roleId;
        $this->loadRole();
    }

    /** «الكل» — every permission on or off in one switch. */
    public function toggleAll(bool $on): void
    {
        $this->selected = $on ? PermissionSeeder::PERMISSIONS : [];
    }

    /** Toggle a whole section (e.g. all of finance.*). */
    public function toggleSection(string $section, bool $on): void
    {
        $inSection = collect(PermissionSeeder::PERMISSIONS)
            ->filter(fn (string $p) => str_starts_with($p, $section.'.'))
            ->values();

        $this->selected = $on
            ? collect($this->selected)->merge($inSection)->unique()->values()->all()
            : collect($this->selected)->reject(fn (string $p) => $inSection->contains($p))->values()->all();
    }

    public function saveRole(): void
    {
        $this->authorize('roles.update');

        app(PermissionGrantService::class)->syncRolePermissions(
            Role::findOrFail($this->roleId),
            array_values(array_intersect($this->selected, PermissionSeeder::PERMISSIONS)),
            auth()->user(),
        );

        $this->dispatch('ds-toast', message: 'تم حفظ صلاحيات الدور');
    }

    public function grantException(): void
    {
        $this->authorize('roles.update');

        $this->validate([
            'grantUserId' => 'required|exists:users,id',
            'grantPermission' => 'required|string',
            'grantReason' => 'required|string|min:3|max:255',
            'grantExpiresOn' => 'nullable|date',
        ], [
            'grantReason.required' => 'الاستثناء يتطلب سببًا مكتوبًا',
        ], ['grantUserId' => 'الموظف', 'grantPermission' => 'الصلاحية']);

        try {
            app(PermissionGrantService::class)->grantException(
                User::findOrFail($this->grantUserId),
                $this->grantPermission,
                $this->grantReason,
                auth()->user(),
                $this->grantExpiresOn,
            );

            $this->grantReason = '';
            $this->dispatch('ds-toast', message: 'تم منح الاستثناء');
        } catch (\InvalidArgumentException $e) {
            $this->addError('grantPermission', $e->getMessage());
        }
    }

    public function revokeException(int $grantId): void
    {
        $this->authorize('roles.update');

        app(PermissionGrantService::class)->revokeException(ExceptionalGrant::findOrFail($grantId), auth()->user());

        $this->dispatch('ds-toast', message: 'تم سحب الاستثناء');
    }

    /** Export the «من يملك ماذا» matrix. */
    public function exportMatrix()
    {
        $this->authorize('roles.view');

        $csv = app(PermissionGrantService::class)->matrixCsv();

        return response()->streamDownload(
            fn () => print ($csv),
            'permissions-matrix-'.now()->format('Ymd-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function render(): View
    {
        return view('livewire.settings.grants-index', [
            'roles' => Role::orderBy('id')->get(),
            'permissions' => collect(PermissionSeeder::PERMISSIONS)
                ->groupBy(fn (string $p) => explode('.', $p, 2)[0]),
            'labels' => config('permission_labels.labels'),
            'groups' => config('permission_labels.groups'),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'exceptions' => ExceptionalGrant::with('user')->orderByDesc('id')->get(),
            'matrix' => $this->tab === 'matrix' ? app(PermissionGrantService::class)->matrix() : collect(),
        ])->layout('layouts.app', ['title' => 'منح الصلاحيات']);
    }

    private function loadRole(): void
    {
        $this->selected = $this->roleId
            ? Role::findOrFail($this->roleId)->permissions()->pluck('name')->all()
            : [];
    }
}
