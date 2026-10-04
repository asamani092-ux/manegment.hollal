<?php

namespace App\Livewire\Users;

use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTransfer;
use App\Models\LeaveRequest;
use App\Models\OrgUnit;
use App\Models\PayScale;
use App\Models\PeriodicEvaluation;
use App\Models\ProfileAccessLog;
use App\Models\Responsibility;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\Task;
use App\Models\User;
use App\Services\EvaluationService;
use App\Services\SalaryService;
use App\Support\OrgJobCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * 01-B1 + HR-1/2/4/5 — employee job profile with tabs.
 * Salary lives under job (gated on hr.salaries.view); contracts+documents merged;
 * approved/archived evaluations roll into the cumulative log tab.
 * Legacy tab query keys redirect: contracts|documents→contracts_documents,
 * salary→job, evaluations→log.
 */
class EmployeeProfileShow extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    #[Locked]
    public int $userId;

    public string $activeTab = 'overview';

    public string $statementBody = '';

    public string $onboardingDue = '';

    public bool $ownUpload = false;

    public bool $attendanceEnabled = false;

    public string $weeklyHours = '';

    /** مقفل|مفتوح — قائمة منسدلة لفتح الساعات الإضافية */
    public string $overtimeGate = 'مقفل';

    public bool $showEdit = false;

    public string $editName = '';

    public string $editPhone = '';

    public string $editEmail = '';

    public ?int $editAdministrationId = null;

    public ?int $editUnitId = null;

    public ?int $editManagerId = null;

    public string $editJobTitle = '';

    public ?int $editJobOrgUnitId = null;

    public string $editEmploymentType = '';

    public string $editHireDate = '';

    public string $editNationalId = '';

    public string $editRoleName = '';

    public string $editPassword = '';

    public bool $editIsActive = true;

    /** Toggle editing panels on card UI (view vs edit). */
    public bool $editDataCard = false;

    public bool $editJobCard = false;

    public bool $editSalaryCard = false;

    public ?int $payScaleId = null;

    public string $gradeLabel = '';

    public string $newComponentType = SalaryComponent::TYPE_ALLOWANCE;

    public string $newComponentLabel = '';

    public string $newComponentAmount = '';

    public string $baseAmount = '';

    public ?int $editingComponentId = null;

    public string $editComponentAmount = '';

    public string $editComponentLabel = '';

    public string $overtimeHourValue = '';

    public string $employeeComment = '';

    public bool $showDocumentModal = false;

    public ?int $documentId = null;

    public string $docType = EmployeeDocument::TYPE_ID;

    public string $docNumber = '';

    public string $docIssueDate = '';

    public string $docExpiryDate = '';

    public string $docNotes = '';

    public $docFile = null;

    public function mount(User $user): void
    {
        $self = (int) auth()->id() === (int) $user->id;
        if (! $self) {
            $this->authorize('hr.employees.view');
        } else {
            abort_unless(
                auth()->user()->can('dashboard.view') || auth()->user()->can('hr.employees.view'),
                403
            );
        }
        $this->userId = $user->id;
        $this->attendanceEnabled = (bool) $user->attendance_enabled;
        $this->weeklyHours = (string) ($user->profile?->weekly_hours ?? '');
        $this->overtimeGate = $user->profile?->overtime_unlocked ? 'مفتوح' : 'مقفل';
        $this->payScaleId = $user->profile?->pay_scale_id;
        $this->gradeLabel = (string) ($user->profile?->grade_label ?? '');
        $this->overtimeHourValue = (string) ($user->profile?->overtime_hour_value ?? '0');

        $tab = request()->query('tab');
        if (is_string($tab) && $tab !== '') {
            $this->setTab($tab);
        }
    }

    /**
     * Load the editable base amount only when the salary card is shown.
     * Time: O(1) | Space: O(1)
     */
    private function loadSalaryFormDefaults(): void
    {
        if ($this->baseAmount !== '') {
            return;
        }

        $base = SalaryComponent::query()
            ->where('employee_id', $this->userId)
            ->where('type', SalaryComponent::TYPE_BASE)
            ->effectiveOn(today())
            ->value('amount');
        $this->baseAmount = $base !== null ? (string) $base : '0';
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'salary' && $this->canViewSalary()) {
            $this->logSalaryAccess();
        }

        $this->activeTab = $this->normalizeTab($tab);
        if (in_array($this->activeTab, ['job', 'pay'], true) && $this->canViewSalary()) {
            $this->loadSalaryFormDefaults();
        }
    }

    public function updatedActiveTab(string $value): void
    {
        $normalized = $this->normalizeTab($value);
        if ($normalized !== $value) {
            $this->activeTab = $normalized;
        }
    }

    /**
     * Map retired tab keys onto the Round-5 consolidated tabs.
     * Time: O(1) | Space: O(1)
     */
    public function normalizeTab(string $tab): string
    {
        return match ($tab) {
            'data' => 'personal',
            'contracts', 'contracts_documents' => 'job',
            'documents' => 'documents',
            'salary' => 'pay',
            'evaluations', 'tasks' => 'performance',
            default => $tab,
        };
    }

    public function canViewSalary(): bool
    {
        return auth()->user()->can('hr.salaries.view');
    }

    public function openEdit(): void
    {
        $this->authorize('hr.employees.update');
        $user = User::with('profile')->findOrFail($this->userId);
        $this->editName = $user->name;
        $this->editPhone = (string) ($user->phone ?? '');
        $this->editEmail = $user->email;
        $this->editManagerId = $user->effectiveManager()?->id;
        $this->editJobTitle = (string) ($user->profile?->job_title ?? '');
        $this->editJobOrgUnitId = $user->org_unit_id;
        $cascade = OrgJobCatalog::cascadeFromJob($user->org_unit_id);
        $this->editAdministrationId = $cascade['administration_id'];
        $this->editUnitId = $cascade['unit_id'];
        $this->editEmploymentType = (string) ($user->profile?->employment_type ?? '');
        $this->editHireDate = $user->profile?->hire_date?->format('Y-m-d') ?? '';
        $this->editNationalId = (string) ($user->profile?->national_id ?? '');
        $this->editRoleName = $user->roles->first()?->name ?? '';
        $this->editPassword = '';
        $this->editIsActive = (bool) $user->is_active;
        $this->showEdit = true;
    }

    /** Cascade step 1 → clears قسم + وظيفة. Time: O(1) | Space: O(1) */
    public function updatedEditAdministrationId($value): void
    {
        $this->editAdministrationId = $value !== null && $value !== '' ? (int) $value : null;
        $this->editUnitId = null;
        $this->editJobOrgUnitId = null;
        $this->editJobTitle = '';
    }

    /** Cascade step 2 → clears وظيفة. Time: O(1) | Space: O(1) */
    public function updatedEditUnitId($value): void
    {
        $this->editUnitId = $value !== null && $value !== '' ? (int) $value : null;
        $this->editJobOrgUnitId = null;
        $this->editJobTitle = '';
    }

    public function updatedEditJobOrgUnitId($value): void
    {
        $this->editJobOrgUnitId = $value !== null && $value !== '' ? (int) $value : null;
        $title = OrgJobCatalog::resolveTitle($this->editJobOrgUnitId);
        if ($title !== null) {
            $this->editJobTitle = $title;
        }
    }

    public function saveProfile(): void
    {
        $this->authorize('hr.employees.update');
        $user = User::findOrFail($this->userId);
        $this->authorize('update', $user);

        $this->validate([
            'editName' => 'required|string|max:255',
            'editPhone' => 'required|string|max:50|unique:users,phone,'.$this->userId,
            'editEmail' => 'required|email|unique:users,email,'.$this->userId,
            'editAdministrationId' => 'nullable|exists:org_units,id',
            'editUnitId' => 'nullable|exists:org_units,id',
            'editManagerId' => 'nullable|exists:users,id',
            'editJobOrgUnitId' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $ok = OrgUnit::query()
                        ->whereKey((int) $value)
                        ->where('level', OrgUnit::LEVEL_JOB)
                        ->when($this->editUnitId, fn ($q) => $q->where('parent_id', $this->editUnitId))
                        ->exists();
                    if (! $ok) {
                        $fail('المسمى الوظيفي غير مرتبط بالقسم المختار.');
                    }
                },
            ],
            'editJobTitle' => 'nullable|string|max:255',
            'editEmploymentType' => 'nullable|in:دوام_كامل,دوام_جزئي,متعاون,متطوع',
            'editHireDate' => 'nullable|date',
            'editNationalId' => 'nullable|string|max:50',
            'editRoleName' => 'nullable|string|exists:roles,name',
            'editPassword' => 'nullable|string|min:8',
            'editIsActive' => 'boolean',
        ], [], [
            'editPassword' => 'كلمة المرور',
            'editIsActive' => 'حالة الحساب',
            'editRoleName' => 'الدور',
            'editHireDate' => 'تاريخ المباشرة',
            'editNationalId' => 'الهوية',
            'editJobTitle' => 'المسمى الوظيفي',
            'editJobOrgUnitId' => 'المسمى الوظيفي',
            'editAdministrationId' => 'الإدارة',
            'editUnitId' => 'القسم',
        ]);

        if ($this->editJobOrgUnitId) {
            $resolved = OrgJobCatalog::resolveTitle($this->editJobOrgUnitId);
            if ($resolved !== null) {
                $this->editJobTitle = $resolved;
            }
        }

        $derived = app(\App\Services\OrgStructureService::class)->deriveManagerId($user);
        $selected = $this->editManagerId ? (int) $this->editManagerId : null;
        $override = ($selected !== null && $selected !== $derived) ? $selected : null;
        $payload = [
            'name' => $this->editName,
            'phone' => $this->editPhone,
            'email' => $this->editEmail,
            'manager_id' => $derived ?? $selected,
            'org_unit_id' => $this->editJobOrgUnitId,
            'is_active' => $this->editIsActive,
        ];

        if ($this->editPassword !== '') {
            $payload['password'] = Hash::make($this->editPassword);
            $payload['must_change_password'] = true;
        }

        $user->update($payload);
        $user->forceFill(['manager_override_id' => $override])->save();
        if ($this->editRoleName !== '') {
            $user->syncRoles([$this->editRoleName]);
        }

        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['job_title' => $user->name],
        );
        $profile->forceFill([
            'job_title' => $this->editJobTitle !== '' ? $this->editJobTitle : $profile->job_title,
            'employment_type' => $this->editEmploymentType !== '' ? $this->editEmploymentType : null,
            'hire_date' => $this->editHireDate !== '' ? $this->editHireDate : null,
            'national_id' => $this->editNationalId !== '' ? $this->editNationalId : null,
        ])->save();

        $this->showEdit = false;
        $this->dispatch('toast', type: 'success', message: 'تم تحديث الملف الوظيفي');
    }

    /**
     * Amendments HR — تفعيل الحضور + الساعات الأساسية.
     * Time: O(1) | Space: O(1)
     */
    public function saveAttendanceSettings(): void
    {
        $this->authorize('hr.employees.update');

        $this->validate([
            'attendanceEnabled' => 'boolean',
            'weeklyHours' => 'nullable|integer|min:1|max:80',
        ]);

        $user = User::findOrFail($this->userId);
        $user->forceFill(['attendance_enabled' => $this->attendanceEnabled])->save();

        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['job_title' => $user->name],
        );
        $profile->forceFill([
            'weekly_hours' => $this->weeklyHours !== '' ? (int) $this->weeklyHours : null,
        ])->save();

        $this->dispatch('ds-toast', message: 'حُفظت إعدادات الحضور');
    }

    /**
     * Amendments Q1 — فتح/قفل الساعات الإضافية من القائمة المنسدلة.
     * Time: O(1) | Space: O(1)
     */
    public function saveOvertimeGate(): void
    {
        $this->authorize('hr.salaries.manage');

        $this->validate([
            'overtimeGate' => 'required|in:مقفل,مفتوح',
            'overtimeHourValue' => 'nullable|numeric|min:0',
        ]);

        $user = User::findOrFail($this->userId);
        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['job_title' => $user->name],
        );
        $profile->setOvertimeUnlocked($this->overtimeGate === 'مفتوح');
        $profile->forceFill([
            'overtime_hour_value' => $this->overtimeHourValue !== '' ? (float) $this->overtimeHourValue : 0,
        ])->save();

        $this->dispatch('ds-toast', message: $this->overtimeGate === 'مفتوح'
            ? 'فُتحت الساعات الإضافية لهذا الموظف'
            : 'أُقفلت الساعات الإضافية لهذا الموظف');
    }

    public function saveBaseAmount(): void
    {
        $this->authorize('hr.salaries.manage');

        $this->validate([
            'baseAmount' => 'required|numeric|min:0',
        ]);

        app(SalaryService::class)->setBaseAmount(
            User::findOrFail($this->userId),
            (float) $this->baseAmount,
        );

        $this->dispatch('toast', type: 'success', message: 'حُدّث الراتب الأساسي — المسيّرات الجديدة تستخدم المبلغ الجديد');
    }

    public function openEditComponent(int $id): void
    {
        $this->authorize('hr.salaries.manage');
        $component = SalaryComponent::query()
            ->where('employee_id', $this->userId)
            ->findOrFail($id);
        $this->editingComponentId = $component->id;
        $this->editComponentAmount = (string) $component->amount;
        $this->editComponentLabel = (string) $component->label_ar;
    }

    public function saveEditComponent(): void
    {
        $this->authorize('hr.salaries.manage');

        $this->validate([
            'editComponentAmount' => 'required|numeric|min:0',
            'editComponentLabel' => 'required|string|max:255',
        ]);

        $component = SalaryComponent::query()
            ->where('employee_id', $this->userId)
            ->findOrFail($this->editingComponentId);

        $new = app(SalaryService::class)->edit($component, [
            'amount' => (float) $this->editComponentAmount,
            'label_ar' => $this->editComponentLabel,
        ]);

        if ($new->type === SalaryComponent::TYPE_BASE) {
            $this->baseAmount = (string) $new->amount;
        }

        $this->editingComponentId = null;
        $this->dispatch('toast', type: 'success', message: 'حُدّث المكوّن (أُغلق السابق وحُفظ السجل)');
    }

    public function closeSalaryComponent(int $id): void
    {
        $this->authorize('hr.salaries.manage');
        $component = SalaryComponent::query()
            ->where('employee_id', $this->userId)
            ->findOrFail($id);
        app(SalaryService::class)->closeComponent($component);
        if ($component->type === SalaryComponent::TYPE_BASE) {
            $this->baseAmount = '';
        }
        $this->dispatch('toast', type: 'success', message: 'أُوقف سريان المكوّن');
    }

    public function assignPayGrade(): void
    {
        $this->authorize('hr.salaries.manage');

        $this->validate([
            'payScaleId' => 'required|exists:pay_scales,id',
            'gradeLabel' => 'required|string|max:255',
        ]);

        $scale = PayScale::findOrFail($this->payScaleId);
        app(SalaryService::class)->assignGrade($scale, User::findOrFail($this->userId), $this->gradeLabel);
        $this->dispatch('toast', type: 'success', message: 'رُبط الموظف بالسلم والدرجة — الراتب الأساسي مشتق تلقائيًا');
    }

    public function addSalaryComponent(): void
    {
        $this->authorize('hr.salaries.manage');

        $this->validate([
            'newComponentType' => 'required|in:'.SalaryComponent::TYPE_ALLOWANCE.','.SalaryComponent::TYPE_DEDUCTION,
            'newComponentLabel' => 'required|string|max:255',
            'newComponentAmount' => 'required|numeric|min:0',
        ]);

        try {
            app(SalaryService::class)->addComponent(
                User::with('profile')->findOrFail($this->userId),
                $this->newComponentType,
                $this->newComponentLabel,
                (float) $this->newComponentAmount,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('newComponentType', $e->getMessage());

            return;
        }

        $this->reset(['newComponentLabel', 'newComponentAmount']);
        $this->dispatch('toast', type: 'success', message: 'أُضيف مكوّن الراتب');
    }

    public function saveEmployeeComment(int $evaluationId): void
    {
        $evaluation = PeriodicEvaluation::findOrFail($evaluationId);
        abort_unless($evaluation->employee_id === auth()->id(), 403);

        $this->validate([
            'employeeComment' => 'required|string|max:2000',
        ]);

        try {
            app(EvaluationService::class)->addEmployeeComment($evaluation, $this->employeeComment);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->employeeComment = '';
        $this->dispatch('toast', type: 'success', message: 'سُجّل تعليقك على التقييم');
    }

    public function openDocumentModal(?int $id = null): void
    {
        $this->authorize('hr.employees.update');
        $this->resetDocumentForm();

        if ($id) {
            $doc = EmployeeDocument::query()
                ->where('user_id', $this->userId)
                ->findOrFail($id);
            $this->documentId = $doc->id;
            $this->docType = $doc->type;
            $this->docNumber = (string) ($doc->document_number ?? '');
            $this->docIssueDate = $doc->issue_date?->format('Y-m-d') ?? '';
            $this->docExpiryDate = $doc->expiry_date?->format('Y-m-d') ?? '';
            $this->docNotes = (string) ($doc->notes ?? '');
        }

        $this->showDocumentModal = true;
    }

    public function openOwnUpload(): void
    {
        abort_unless((int) auth()->id() === (int) $this->userId, 403);
        $this->ownUpload = true;
        $this->resetDocumentForm();
        $this->showDocumentModal = true;
    }

    public function saveDocument(): void
    {
        $isSelf = (int) auth()->id() === (int) $this->userId;
        if (! $isSelf) {
            $this->authorize('hr.employees.update');
        }

        $this->validate([
            'docType' => 'required|in:'.implode(',', EmployeeDocument::TYPES),
            'docNumber' => 'nullable|string|max:100',
            'docIssueDate' => 'nullable|date',
            'docExpiryDate' => 'nullable|date|after_or_equal:docIssueDate',
            'docNotes' => 'nullable|string|max:500',
            'docFile' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ], [], [
            'docType' => 'نوع الوثيقة',
            'docNumber' => 'رقم الوثيقة',
            'docExpiryDate' => 'تاريخ الانتهاء',
            'docFile' => 'الملف',
        ]);

        $payload = [
            'user_id' => $this->userId,
            'type' => $this->docType,
            'document_number' => $this->docNumber !== '' ? $this->docNumber : null,
            'issue_date' => $this->docIssueDate !== '' ? $this->docIssueDate : null,
            'expiry_date' => $this->docExpiryDate !== '' ? $this->docExpiryDate : null,
            'notes' => $this->docNotes !== '' ? $this->docNotes : null,
            'uploaded_by' => auth()->id(),
        ];

        if ($this->docFile) {
            $payload['file_path'] = $this->docFile->store('employee-documents/'.$this->userId, 'local');
        }

        $selfPending = $isSelf && ! auth()->user()->can('hr.documents.review');
        if ($selfPending) {
            $payload['status'] = 'pending_review';
        } elseif (! $this->documentId) {
            $payload['status'] = 'approved';
        }

        if ($this->documentId) {
            $doc = EmployeeDocument::query()->where('user_id', $this->userId)->findOrFail($this->documentId);
            if (isset($payload['file_path']) && $doc->file_path) {
                Storage::disk('local')->delete($doc->file_path);
            }
            $doc->forceFill($payload)->save();
        } else {
            EmployeeDocument::create($payload);
        }

        $this->showDocumentModal = false;
        $this->resetDocumentForm();
        $this->dispatch('toast', type: 'success', message: 'حُفظت الوثيقة الرسمية');
    }

    public function deleteDocument(int $id): void
    {
        $this->authorize('hr.employees.update');
        $doc = EmployeeDocument::query()->where('user_id', $this->userId)->findOrFail($id);
        if ($doc->file_path) {
            Storage::disk('local')->delete($doc->file_path);
        }
        $doc->delete();
        $this->dispatch('toast', type: 'success', message: 'حُذفت الوثيقة');
    }

    protected function resetDocumentForm(): void
    {
        $this->documentId = null;
        $this->docType = EmployeeDocument::TYPE_ID;
        $this->docNumber = '';
        $this->docIssueDate = '';
        $this->docExpiryDate = '';
        $this->docNotes = '';
        $this->docFile = null;
        $this->resetValidation(['docType', 'docNumber', 'docIssueDate', 'docExpiryDate', 'docNotes', 'docFile']);
    }

    private function logSalaryAccess(): void
    {
        ProfileAccessLog::create([
            'user_id' => auth()->id(),
            'target_user_id' => $this->userId,
            'tab_accessed' => 'salary',
            'accessed_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, array{at:\Illuminate\Support\Carbon,actor:string,event:string,detail:string}>
     */
    private function profileLogEntries(): Collection
    {
        $entries = collect();

        foreach (ProfileAccessLog::query()
            ->where('target_user_id', $this->userId)
            ->with('actor:id,name')
            ->orderByDesc('accessed_at')
            ->get(['user_id', 'tab_accessed', 'accessed_at']) as $log) {
            $tabLabel = match ($log->tab_accessed) {
                'salary' => 'الراتب',
                default => (string) $log->tab_accessed,
            };
            $entries->push([
                'at' => $log->accessed_at,
                'actor' => $log->actor?->name ?? '—',
                'event' => 'وصول تبويب',
                'detail' => $tabLabel,
            ]);
        }

        foreach (EmployeeTransfer::query()
            ->where('user_id', $this->userId)
            ->with(['fromUnit:id,name', 'toUnit:id,name', 'mover:id,name'])
            ->orderByDesc('effective_on')
            ->get() as $transfer) {
            $from = $transfer->fromUnit?->name ?? '—';
            $to = $transfer->toUnit?->name ?? '—';
            $entries->push([
                'at' => $transfer->effective_on->startOfDay(),
                'actor' => $transfer->mover?->name ?? '—',
                'event' => 'نقل هيكلي',
                'detail' => "{$from} → {$to}".($transfer->reason ? " — {$transfer->reason}" : ''),
            ]);
        }

        foreach (AuditLog::query()
            ->where('target_type', User::class)
            ->where('target_id', $this->userId)
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get() as $audit) {
            $entries->push([
                'at' => $audit->created_at,
                'actor' => $audit->actor?->name ?? '—',
                'event' => $audit->actionLabel(),
                'detail' => is_array($audit->metadata) ? json_encode($audit->metadata, JSON_UNESCAPED_UNICODE) : '—',
            ]);
        }

        return $entries->sortByDesc(fn (array $row) => $row['at'])->values();
    }

    public function completeOnboarding(int $id): void
    {
        $item = \App\Models\EmployeeOnboardingItem::query()->where('user_id', $this->userId)->findOrFail($id);
        abort_unless(auth()->user()->can('hr.employees.update') || (int) auth()->id() === (int) $this->userId, 403);
        app(\App\Services\OnboardingChecklistService::class)->markDone($item, auth()->user());
    }

    public function closeOnboarding(int $id): void
    {
        $item = \App\Models\EmployeeOnboardingItem::query()->where('user_id', $this->userId)->findOrFail($id);
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        app(\App\Services\OnboardingChecklistService::class)->close($item, auth()->user());
    }

    public function convertOnboarding(int $id): void
    {
        $item = \App\Models\EmployeeOnboardingItem::query()->where('user_id', $this->userId)->findOrFail($id);
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $employee = User::query()->findOrFail($this->userId);
        app(\App\Services\OnboardingChecklistService::class)->convertToTask(
            $item,
            $employee,
            auth()->user(),
            $this->onboardingDue !== '' ? $this->onboardingDue : null,
        );
    }

    public function submitViolationStatement(int $id): void
    {
        abort_unless((int) auth()->id() === (int) $this->userId, 403);
        $violation = \App\Models\Violation::query()->where('employee_id', $this->userId)->findOrFail($id);
        app(\App\Services\ViolationService::class)->submitStatement($violation, auth()->user(), $this->statementBody);
        $this->statementBody = '';
    }

    public function render(): View
    {
        $tab = $this->activeTab;
        $with = ['profile'];
        if (in_array($tab, ['overview', 'personal'], true)) {
            $with[] = 'manager:id,name';
        }
        if (in_array($tab, ['overview', 'personal', 'job', 'pay', 'leaves'], true)) {
            $with[] = 'orgUnit:id,name,level,parent_id';
            $with[] = 'orgUnit.parent:id,name,level';
        }
        if ($tab === 'personal') {
            $with[] = 'roles:id,name';
        }
        if (in_array($tab, ['job', 'pay'], true)) {
            $with[] = 'profile.payScale:id,name_ar';
        }

        $user = User::with($with)->findOrFail($this->userId);
        $salaryTab = in_array($tab, ['job', 'pay'], true) && $this->canViewSalary();
        $salaryComponents = $salaryTab
            ? SalaryComponent::query()->where('employee_id', $this->userId)->effectiveOn(today())->orderBy('type')->get()
            : collect();
        $showCatalogs = $this->showEdit;

        return view('livewire.users.employee-profile-show', [
            'user' => $user,
            'canViewSalary' => $this->canViewSalary(),
            'canManageOvertime' => auth()->user()->can('hr.salaries.manage'),
            'canUpdate' => auth()->user()->can('hr.employees.update'),
            'administrations' => $showCatalogs ? OrgJobCatalog::administrations() : collect(),
            'managers' => $showCatalogs ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'roles' => $showCatalogs ? Role::orderBy('name')->get(['id', 'name']) : collect(),
            'unitOptions' => $showCatalogs ? OrgJobCatalog::optionsForUnits($this->editAdministrationId) : [],
            'jobOptions' => $showCatalogs ? OrgJobCatalog::optionsForUnit($this->editUnitId) : [],
            'payScales' => $salaryTab
                ? PayScale::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'grades'])
                : collect(),
            'salaryComponents' => $salaryComponents,
            'salaryTotals' => $salaryTab ? $this->totalsFromComponents($user, $salaryComponents) : null,
            'documentMatrix' => in_array($tab, ['overview', 'documents'], true)
                ? app(\App\Services\DocumentRequirementService::class)->matrix($user)
                : [],
            'performanceSummary' => $tab === 'performance'
                ? app(\App\Services\PerformanceService::class)->summary($user, now()->startOfYear(), now()->endOfYear())
                : null,
            'profileViolations' => $tab === 'violations'
                ? \App\Models\Violation::query()->where('employee_id', $this->userId)->latest('id')->limit(30)->get()
                : collect(),
            'payslips' => $tab === 'pay'
                ? \App\Models\PayrollRunItem::query()->where('employee_id', $this->userId)->with('run:id,month,status')->latest('id')->limit(12)->get()
                : collect(),
            'attendanceRows' => $tab === 'attendance'
                ? \App\Models\AttendanceRecord::query()->where('employee_id', $this->userId)->latest('date')->limit(31)->get()
                : collect(),
            'custodyRows' => $tab === 'custody'
                ? \App\Models\Custody::query()->where('employee_id', $this->userId)->latest('id')->limit(20)->get()
                : collect(),
            'assetRows' => $tab === 'custody'
                ? \App\Models\Asset::query()->where('current_holder_id', $this->userId)->latest('id')->limit(20)->get()
                : collect(),
            'contracts' => $tab === 'documents'
                ? Contract::query()->where('employee_id', $this->userId)->latest('end_date')->get()
                : collect(),
            'onboardingItems' => $tab === 'overview' && \Illuminate\Support\Facades\Schema::hasTable('employee_onboarding_items')
                ? \App\Models\EmployeeOnboardingItem::query()
                    ->with('referenceItem:id,name_ar,code')
                    ->where('user_id', $this->userId)
                    ->limit(20)
                    ->get()
                : collect(),
            'employeeDocuments' => $tab === 'documents'
                ? EmployeeDocument::query()
                    ->where('user_id', $this->userId)
                    ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('expiry_date')
                    ->get()
                : collect(),
            'responsibilities' => in_array($tab, ['job', 'pay'], true)
                ? Responsibility::query()->where('employee_id', $this->userId)->active()->orderBy('order')->get()
                : collect(),
            'quarterlyEvaluations' => in_array($tab, ['performance', 'log'], true)
                ? $this->quarterlyEvaluationsFor($user, $tab === 'log')
                : collect(),
            'evaluations' => $tab === 'log' ? $this->legacyEvaluations(false) : collect(),
            'archivedEvaluations' => $tab === 'log' ? $this->legacyEvaluations(true) : collect(),
            'leaves' => $tab === 'leaves'
                ? LeaveRequest::query()->where('employee_id', $this->userId)->latest()->limit(20)->get()
                : collect(),
            'tasks' => $tab === 'performance'
                ? Task::query()->where('assigned_to', $this->userId)->latest()->limit(20)->get(['id', 'title', 'status', 'due_date'])
                : collect(),
            'profileLogEntries' => $tab === 'log' ? $this->profileLogEntries() : collect(),
        ])->layout('layouts.app', ['title' => 'الملف الوظيفي — '.$user->name]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SalaryComponent>  $components
     * @return array{base: float, allowances: float, deductions: float, monthly: float}
     */
    private function totalsFromComponents(User $user, $components): array
    {
        $base = (float) $components->where('type', SalaryComponent::TYPE_BASE)->sum('amount');
        $allowances = (float) $components->where('type', SalaryComponent::TYPE_ALLOWANCE)->sum('amount');
        $deductions = app(SalaryService::class)->isRegularEmployee($user)
            ? (float) $components->where('type', SalaryComponent::TYPE_DEDUCTION)->sum('amount')
            : 0.0;

        return [
            'base' => $base,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'monthly' => $base + $allowances - $deductions,
        ];
    }

    private function quarterlyEvaluationsFor(User $user, bool $withScores)
    {
        $with = $withScores
            ? ['cycle.items', 'scores', 'evaluator:id,name']
            : ['cycle:id,name,quarter,year'];

        return EmployeeEvaluation::query()
            ->where('employee_id', $user->id)
            ->when(
                (int) auth()->id() === (int) $user->id && ! auth()->user()->can('hr.employees.update'),
                fn ($q) => $q->whereIn('status', [
                    EmployeeEvaluation::STATUS_APPROVED,
                    EmployeeEvaluation::STATUS_ARCHIVED,
                ])
            )
            ->with($with)
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->get();
    }

    private function legacyEvaluations(bool $archived)
    {
        return PeriodicEvaluation::query()
            ->where('employee_id', $this->userId)
            ->when(
                $archived,
                fn ($q) => $q->where('status', PeriodicEvaluation::STATUS_ARCHIVED),
                fn ($q) => $q->where('status', '!=', PeriodicEvaluation::STATUS_ARCHIVED)
                    ->when(
                        ! auth()->user()->can('hr.employees.update'),
                        fn ($inner) => $inner->where('status', PeriodicEvaluation::STATUS_PUBLISHED)
                    )
            )
            ->with(['scores.responsibility:id,body', 'evaluator:id,name'])
            ->latest()
            ->get();
    }
}
