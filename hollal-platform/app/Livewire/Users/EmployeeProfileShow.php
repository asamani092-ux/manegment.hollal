<?php

namespace App\Livewire\Users;

use App\Models\Contract;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\PayScale;
use App\Models\PeriodicEvaluation;
use App\Models\ProfileAccessLog;
use App\Models\Responsibility;
use App\Models\SalaryComponent;
use App\Models\Task;
use App\Models\User;
use App\Services\EvaluationService;
use App\Services\SalaryService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * 01-B1 + HR-1/2/4 — employee job profile with tabs. The salary tab is gated on
 * hr.salaries.view and every access is recorded in profile_access_logs.
 */
class EmployeeProfileShow extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public int $userId;

    public string $activeTab = 'data';

    public bool $attendanceEnabled = false;

    public string $weeklyHours = '';

    /** مقفل|مفتوح — قائمة منسدلة لفتح الساعات الإضافية */
    public string $overtimeGate = 'مقفل';

    public bool $showEdit = false;

    public string $editName = '';

    public string $editPhone = '';

    public string $editEmail = '';

    public ?int $editDepartmentId = null;

    public ?int $editManagerId = null;

    public string $editJobTitle = '';

    public string $editEmploymentType = '';

    public string $editPassword = '';

    public bool $editIsActive = true;

    public ?int $payScaleId = null;

    public string $gradeLabel = '';

    public string $newComponentType = SalaryComponent::TYPE_ALLOWANCE;

    public string $newComponentLabel = '';

    public string $newComponentAmount = '';

    public string $overtimeHourValue = '';

    public string $employeeComment = '';

    public function mount(User $user): void
    {
        $this->authorize('hr.employees.view');
        $this->userId = $user->id;
        $this->attendanceEnabled = (bool) $user->attendance_enabled;
        $this->weeklyHours = (string) ($user->profile?->weekly_hours ?? '');
        $this->overtimeGate = $user->profile?->overtime_unlocked ? 'مفتوح' : 'مقفل';
        $this->payScaleId = $user->profile?->pay_scale_id;
        $this->gradeLabel = (string) ($user->profile?->grade_label ?? '');
        $this->overtimeHourValue = (string) ($user->profile?->overtime_hour_value ?? '0');
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'salary') {
            $this->authorize('hr.salaries.view');
            $this->logSalaryAccess();
        }

        $this->activeTab = $tab;
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
        $this->editDepartmentId = $user->department_id;
        $this->editManagerId = $user->manager_id;
        $this->editJobTitle = (string) ($user->profile?->job_title ?? '');
        $this->editEmploymentType = (string) ($user->profile?->employment_type ?? '');
        $this->editPassword = '';
        $this->editIsActive = (bool) $user->is_active;
        $this->showEdit = true;
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
            'editDepartmentId' => 'nullable|exists:departments,id',
            'editManagerId' => 'nullable|exists:users,id',
            'editJobTitle' => 'nullable|string|max:255',
            'editEmploymentType' => 'nullable|in:دوام_كامل,دوام_جزئي,متعاون,متطوع',
            'editPassword' => 'nullable|string|min:8',
            'editIsActive' => 'boolean',
        ], [], [
            'editPassword' => 'كلمة المرور',
            'editIsActive' => 'حالة الحساب',
        ]);

        $payload = [
            'name' => $this->editName,
            'phone' => $this->editPhone,
            'email' => $this->editEmail,
            'department_id' => $this->editDepartmentId,
            'manager_id' => $this->editManagerId,
            'is_active' => $this->editIsActive,
        ];

        if ($this->editPassword !== '') {
            $payload['password'] = Hash::make($this->editPassword);
            $payload['must_change_password'] = true;
        }

        $user->update($payload);

        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['job_title' => $user->name],
        );
        $profile->forceFill([
            'job_title' => $this->editJobTitle !== '' ? $this->editJobTitle : $profile->job_title,
            'employment_type' => $this->editEmploymentType !== '' ? $this->editEmploymentType : null,
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

    private function logSalaryAccess(): void
    {
        ProfileAccessLog::create([
            'user_id' => auth()->id(),
            'target_user_id' => $this->userId,
            'tab_accessed' => 'salary',
            'accessed_at' => now(),
        ]);
    }

    public function render(): View
    {
        $user = User::with(['department:id,name', 'manager:id,name', 'profile.payScale', 'roles:id,name'])
            ->findOrFail($this->userId);

        $salaryTotals = $this->canViewSalary()
            ? app(SalaryService::class)->monthlyFromComponents($user)
            : null;

        return view('livewire.users.employee-profile-show', [
            'user' => $user,
            'canViewSalary' => $this->canViewSalary(),
            'canManageOvertime' => auth()->user()->can('hr.salaries.manage'),
            'canUpdate' => auth()->user()->can('hr.employees.update'),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'managers' => User::orderBy('name')->get(['id', 'name']),
            'payScales' => PayScale::query()->where('is_active', true)->orderBy('name_ar')->get(),
            'salaryComponents' => $this->canViewSalary()
                ? SalaryComponent::query()->where('employee_id', $this->userId)->effectiveOn(today())->orderBy('type')->get()
                : collect(),
            'salaryTotals' => $salaryTotals,
            'contracts' => Contract::query()->where('employee_id', $this->userId)->latest('end_date')->get(),
            'responsibilities' => Responsibility::query()->where('employee_id', $this->userId)->active()->orderBy('order')->get(),
            'evaluations' => PeriodicEvaluation::query()
                ->where('employee_id', $this->userId)
                ->with(['scores.responsibility', 'evaluator:id,name'])
                ->latest()
                ->get(),
            'leaves' => LeaveRequest::query()->where('employee_id', $this->userId)->latest()->limit(20)->get(),
            'tasks' => Task::query()->where('assigned_to', $this->userId)->latest()->limit(20)->get(['id', 'title', 'status', 'due_date']),
        ])->layout('layouts.app', ['title' => 'الملف الوظيفي — '.$user->name]);
    }
}
