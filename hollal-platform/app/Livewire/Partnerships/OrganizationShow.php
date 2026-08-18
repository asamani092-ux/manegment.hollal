<?php

namespace App\Livewire\Partnerships;

use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\Partnership;
use App\Models\Program;
use App\Models\User;
use App\Services\PartnershipPipelineService;
use App\Services\PartnershipQuickCreateService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 05-B1 — the organization page: data, journeys, projects, cumulative impact
 * record, documents and the communication timeline.
 */
class OrganizationShow extends Component
{
    use AuthorizesRequests;

    public Organization $organization;

    public bool $showContactModal = false;

    public ?int $contactId = null;

    public string $contactName = '';

    public ?string $contactPosition = null;

    public ?string $contactPhone = null;

    public ?string $contactEmail = null;

    public bool $contactPrimary = false;

    public bool $showQuickPartnershipModal = false;

    public ?int $quickOwnerId = null;

    /** @var list<int|string> */
    public array $quickProgramIds = [];

    public function mount(Organization $organization): void
    {
        $this->authorize('partnerships.organizations.view');
        $this->organization = $organization;
    }

    public function openContactCreate(): void
    {
        $this->authorize('partnerships.organizations.manage');
        $this->resetContactForm();
        $this->showContactModal = true;
    }

    public function editContact(int $id): void
    {
        $this->authorize('partnerships.organizations.manage');
        $contact = $this->organization->contacts()->findOrFail($id);
        $this->contactId = $contact->id;
        $this->contactName = $contact->name;
        $this->contactPosition = $contact->position;
        $this->contactPhone = $contact->phone;
        $this->contactEmail = $contact->email;
        $this->contactPrimary = $contact->is_primary;
        $this->showContactModal = true;
    }

    public function saveContact(): void
    {
        $this->authorize('partnerships.organizations.manage');
        $data = $this->validate([
            'contactName' => 'required|string|max:255',
            'contactPosition' => 'nullable|string|max:255',
            'contactPhone' => 'nullable|string|max:50',
            'contactEmail' => 'nullable|email|max:255',
            'contactPrimary' => 'boolean',
        ], [], ['contactName' => 'اسم المسؤول', 'contactEmail' => 'البريد']);

        $contact = $this->contactId
            ? $this->organization->contacts()->findOrFail($this->contactId)
            : new OrganizationContact(['organization_id' => $this->organization->id]);
        $contact->fill([
            'name' => $data['contactName'],
            'position' => $data['contactPosition'] ?? null,
            'phone' => $data['contactPhone'] ?? null,
            'email' => $data['contactEmail'] ?? null,
            'is_primary' => (bool) ($data['contactPrimary'] ?? false),
        ])->save();

        if ($contact->is_primary) {
            $this->organization->contacts()->whereKeyNot($contact->id)->update(['is_primary' => false]);
        }

        $this->showContactModal = false;
        $this->resetContactForm();
        $this->dispatch('ds-toast', message: 'تم حفظ مسؤول التواصل');
    }

    public function archiveContact(int $id): void
    {
        $this->authorize('partnerships.organizations.manage');
        $this->organization->contacts()->findOrFail($id)->delete();
        $this->dispatch('ds-toast', message: 'تمت أرشفة مسؤول التواصل');
    }

    public function openQuickPartnership(): void
    {
        $this->authorize('partnerships.organizations.manage');
        $this->quickOwnerId = auth()->id();
        $this->quickProgramIds = [];
        $this->showQuickPartnershipModal = true;
    }

    public function createQuickPartnership(): void
    {
        $this->authorize('partnerships.organizations.manage');
        $this->validate([
            'quickOwnerId' => 'required|exists:users,id',
            'quickProgramIds' => 'required|array|min:1',
            'quickProgramIds.*' => 'integer|exists:programs,id',
        ], [], ['quickProgramIds' => 'البرامج المسموحة', 'quickOwnerId' => 'المتابع']);

        try {
            $partnership = app(PartnershipQuickCreateService::class)->create(
                $this->organization,
                User::findOrFail($this->quickOwnerId),
                $this->quickProgramIds,
            );
        } catch (\RuntimeException $exception) {
            $this->addError('quickProgramIds', $exception->getMessage());

            return;
        }

        $this->showQuickPartnershipModal = false;
        $this->dispatch('ds-toast', message: 'تم فتح رحلة شراكة في مرحلة فرصة');
        $this->redirect(route('partnerships.show', ['partnership' => $partnership->id]).'?from=organization');
    }

    public function renewPartnership(int $partnershipId): void
    {
        $this->authorize('partnerships.organizations.manage');
        $partnership = $this->organization->partnerships()->with('project')->findOrFail($partnershipId);

        if (! in_array($partnership->stage, [Partnership::STAGE_STALLED, Partnership::STAGE_CLOSED], true)) {
            $this->addError('renewal', 'تجديد الرحلة من هنا لرحلة متعثرة أو مغلقة فقط. مشروع منتهٍ/متوقف يُجدَّد من جدول المشاريع.');

            return;
        }

        $this->openRenewalAndGo($partnership, 'تجديد تراكمي لرحلة متعثرة أو مغلقة');
    }

    public function renewFromProject(int $projectId): void
    {
        $this->authorize('partnerships.organizations.manage');
        $project = $this->organization->projects()->firstWhere('id', $projectId);

        if ($project === null || ! Partnership::projectStatusAllowsRenewal($project->status)) {
            $this->addError('renewal', 'التجديد متاح لمشروع منتهٍ أو متوقف فقط');

            return;
        }

        $partnership = $project->partnership
            ?? $this->organization->partnerships()->where('project_id', $project->id)->first();

        if ($partnership === null) {
            $this->addError('renewal', 'لا توجد رحلة مربوطة بهذا المشروع');

            return;
        }

        $this->openRenewalAndGo($partnership, 'تجديد بعد مشروع منتهٍ أو متوقف');
    }

    private function openRenewalAndGo(Partnership $partnership, string $note): void
    {
        $renewal = app(PartnershipPipelineService::class)->openRenewal(
            $partnership,
            auth()->user(),
            $note,
        );

        $this->dispatch('ds-toast', message: 'فُتحت رحلة تجديد جديدة دون استبدال القديمة');
        $this->redirect(route('partnerships.show', ['partnership' => $renewal->id]).'?from=organization');
    }

    public function render(): View
    {
        return view('livewire.partnerships.organization-show', [
            'organization' => $this->organization->load(['contacts', 'partnerships.project', 'partnerships.renewedFrom']),
            'projects' => $this->organization->projects(),
            'impact' => $this->organization->cumulativeImpact(),
            'timeline' => $this->organization->timeline(),
            'owners' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'programs' => Program::query()
                ->where('stage', Program::STAGE_ACTIVE)
                ->whereHas('prices', fn ($query) => $query->where('is_active', true))
                ->withCount('prices')
                ->orderBy('name')
                ->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => $this->organization->name]);
    }

    private function resetContactForm(): void
    {
        $this->contactId = null;
        $this->contactName = '';
        $this->contactPosition = null;
        $this->contactPhone = null;
        $this->contactEmail = null;
        $this->contactPrimary = false;
    }
}
