<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\Department;
use App\Models\Document;
use App\Models\ExpenseRequest;
use App\Models\Meeting;
use App\Models\Partnership;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Policies\ContractPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\ExpenseRequestPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\PartnershipPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\RolePolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use App\Policies\WeeklyReportPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        Task::class => TaskPolicy::class,
        ExpenseRequest::class => ExpenseRequestPolicy::class,
        Project::class => ProjectPolicy::class,
        Partnership::class => PartnershipPolicy::class,
        Meeting::class => MeetingPolicy::class,
        Payroll::class => PayrollPolicy::class,
        Document::class => DocumentPolicy::class,
        Contract::class => ContractPolicy::class,
        WeeklyReport::class => WeeklyReportPolicy::class,
        User::class => UserPolicy::class,
        Department::class => DepartmentPolicy::class,
        Role::class => RolePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
