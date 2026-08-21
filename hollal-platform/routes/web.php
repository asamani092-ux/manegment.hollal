<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ContractFileDownloadController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DutiesFileDownloadController;
use App\Http\Controllers\ExpenseFileDownloadController;
use App\Http\Controllers\FinancialDocumentDownloadController;
use App\Http\Controllers\RevenueFileDownloadController;
use App\Http\Controllers\TaskFileDownloadController;
use App\Livewire\Contracts\ContractsIndex;
use App\Livewire\Documents\DocumentsIndex;
use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\Finance\AccountingCloseIndex;
use App\Livewire\Finance\AccountingReportsIndex;
use App\Livewire\Finance\BudgetsBoard;
use App\Livewire\Finance\ChartOfAccountsIndex;
use App\Livewire\Finance\FinancialDocumentsIndex;
use App\Livewire\Finance\JournalEntriesIndex;
use App\Livewire\Finance\FinancialReportsIndex;
use App\Livewire\DashboardIndex;
use App\Livewire\Hr\PayrollRunsIndex;
use App\Livewire\Hr\PayScalesIndex;
use App\Livewire\Departments\DepartmentsIndex;
use App\Livewire\Meetings\MeetingMinutes;
use App\Livewire\Meetings\MeetingsIndex;
use App\Livewire\Meetings\OpenDecisionsIndex;
use App\Livewire\Payroll\PayrollIndex;
use App\Livewire\Projects\ProjectShow;
use App\Livewire\Projects\ProjectsIndex;
use App\Livewire\Reports\ReportsIndex;
use App\Livewire\Settings\ExpenseSettingsIndex;
use App\Livewire\Settings\MailSettingsIndex;
use App\Livewire\Settings\RolesIndex;
use App\Livewire\Settings\SettingsIndex;
use App\Livewire\Tasks\RecurringTasksIndex;
use App\Livewire\Tasks\TasksCalendar;
use App\Livewire\Tasks\TasksIndex;
use App\Livewire\Tasks\TeamTasksIndex;
use App\Livewire\Tasks\WorkloadBoard;
use App\Livewire\Uat\ToolsChecklist;
use App\Livewire\Users\EmployeeProfileShow;
use App\Livewire\Users\UsersIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// 05-B5 — the unique partner link portal: token-scoped, rate-limited, fully logged.
Route::middleware('throttle:portal')->group(function () {
    Route::get('/portal/{token}', \App\Livewire\Partnerships\PartnerPortal::class)->name('partner.portal');

    Route::get('/portal/{token}/contracts/{contract}/pdf', \App\Http\Controllers\PartnerPortalContractPdfController::class)
        ->name('partner.portal.contract.pdf');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'update'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/change-password', [ChangePasswordController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('/change-password', [ChangePasswordController::class, 'changePassword'])->name('password.change.update');
});

Route::middleware(['auth', 'password.changed', 'maintenance'])->group(function () {
    Route::middleware('throttle:files')->group(function () {
        Route::get('/files/tasks/{task}/{type}', TaskFileDownloadController::class)
            ->whereIn('type', ['attachment', 'submitted'])
            ->name('tasks.files.download');

        Route::get('/files/contracts/{contract}', ContractFileDownloadController::class)
            ->name('contracts.files.download');

        Route::get('/files/expenses/{expenseRequest}', ExpenseFileDownloadController::class)
            ->name('expenses.files.download');

        Route::get('/files/revenues/{revenue}', RevenueFileDownloadController::class)
            ->middleware('permission:finance.revenues.view|finance.revenues.manage')
            ->name('revenues.files.download');

        Route::get('/files/financial-documents/{type}/{id}', FinancialDocumentDownloadController::class)
            ->middleware('permission:finance.revenues.view')
            ->whereIn('type', ['expense_invoice', 'revenue_document', 'custody_invoice', 'payroll_proof'])
            ->name('financial-documents.files.download');

        Route::get('/files/documents/{document}', DocumentDownloadController::class)
            ->name('documents.files.download');
    });

    Route::get('/dashboard', DashboardIndex::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/uat/tools', ToolsChecklist::class)
        ->middleware(['uat.enabled', 'permission:dashboard.view'])
        ->name('uat.tools');

    Route::get('/projects', ProjectsIndex::class)
        ->middleware('permission:projects.view')
        ->name('projects.index');

    Route::get('/projects/{project}/execution', \App\Livewire\Projects\ProjectExecution::class)
        ->middleware('permission:projects.view')
        ->name('projects.execution');

    Route::get('/projects/{project}', ProjectShow::class)
        ->middleware('permission:projects.view')
        ->name('projects.show');

    Route::get('/tasks', TasksIndex::class)
        ->middleware('permission:esnad.tasks.view')
        ->name('tasks.index');

    Route::get('/team-tasks', TeamTasksIndex::class)
        ->middleware('permission:esnad.tasks.view')
        ->name('team-tasks.index');

    Route::get('/tasks-calendar', TasksCalendar::class)
        ->middleware('permission:esnad.tasks.view')
        ->name('tasks-calendar.index');

    Route::get('/recurring-tasks', RecurringTasksIndex::class)
        ->middleware('permission:esnad.tasks.create')
        ->name('recurring-tasks.index');

    Route::get('/workload-board', WorkloadBoard::class)
        ->middleware('permission:esnad.tasks.team.view')
        ->name('workload-board.index');

    Route::get('/expenses', ExpensesIndex::class)
        ->middleware('permission:finance.expenses.view|finance.expenses.create|finance.expenses.approve|finance.expenses.pay')
        ->name('expenses.index');

    Route::get('/payroll', PayrollIndex::class)
        ->middleware('permission:hr.salaries.view')
        ->name('payroll.index');

    Route::get('/documents', DocumentsIndex::class)
        ->middleware('permission:documents.view')
        ->name('documents.index');

    Route::get('/documents/templates', \App\Livewire\Documents\DocumentTemplatesIndex::class)
        ->middleware('permission:documents.view|documents.templates.manage')
        ->name('documents.templates');

    Route::get('/documents/policies', \App\Livewire\Documents\DocumentPoliciesIndex::class)
        ->middleware('permission:documents.policies.manage')
        ->name('documents.policies');

    Route::get('/meetings', MeetingsIndex::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.index');

    Route::get('/meetings/open-decisions', OpenDecisionsIndex::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.open-decisions');

    Route::get('/meetings/archive', \App\Livewire\Meetings\MeetingsArchiveIndex::class)
        ->middleware('permission:meetings.view|documents.view')
        ->name('meetings.archive');

    Route::get('/meetings/{meeting}/minutes/pdf', \App\Http\Controllers\MeetingMinutesPdfController::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.minutes.pdf');

    Route::get('/meetings/{meeting}/minutes', MeetingMinutes::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.minutes');

    Route::get('/departments', DepartmentsIndex::class)
        ->middleware('permission:structure.departments.view')
        ->name('departments.index');

    Route::get('/settings/grants', \App\Livewire\Settings\GrantsIndex::class)
        ->middleware('permission:roles.view')
        ->name('settings.grants');

    Route::get('/structure/org-tree', \App\Livewire\Structure\OrgTreeIndex::class)
        ->middleware('permission:structure.view|structure.departments.view|structure.manage')
        ->name('structure.org-tree');

    Route::get('/settings/roles', RolesIndex::class)
        ->middleware('permission:roles.view')
        ->name('settings.roles');

    Route::get('/settings/expenses', ExpenseSettingsIndex::class)
        ->middleware('permission:settings.manage')
        ->name('settings.expenses');

    Route::get('/settings/notifications', MailSettingsIndex::class)
        ->middleware('permission:settings.notifications.manage')
        ->name('settings.notifications');

    Route::get('/settings', SettingsIndex::class)
        ->middleware('permission:settings.manage|settings.general.manage|settings.finance.manage|settings.backup.manage')
        ->name('settings.index');

    Route::get('/users', UsersIndex::class)
        ->middleware('permission:hr.employees.view')
        ->name('users.index');

    Route::get('/users/{user}/profile', EmployeeProfileShow::class)
        ->middleware('permission:hr.employees.view')
        ->name('users.profile');

    Route::get('/attendance', \App\Livewire\Hr\AttendanceIndex::class)
        ->middleware('permission:hr.employees.view')
        ->name('attendance.index');

    Route::get('/leaves', \App\Livewire\Hr\LeavesIndex::class)
        ->middleware('permission:hr.leaves.request|hr.leaves.approve|hr.leaves.view-all|hr.employees.view')
        ->name('leaves.index');

    Route::get('/evaluations', \App\Livewire\Hr\EvaluationsIndex::class)
        ->middleware('permission:hr.employees.view')
        ->name('evaluations.index');

    Route::get('/responsibilities', \App\Livewire\Hr\ResponsibilitiesIndex::class)
        ->middleware('permission:hr.employees.update')
        ->name('responsibilities.index');

    Route::get('/hr-lifecycle', \App\Livewire\Hr\HrLifecycleIndex::class)
        ->middleware('permission:hr.employees.update')
        ->name('hr-lifecycle.index');

    Route::get('/visits', \App\Livewire\Projects\VisitsIndex::class)
        ->middleware('permission:projects.visits.view')
        ->name('visits.index');

    Route::get('/measurement', \App\Livewire\Projects\MeasurementIndex::class)
        ->middleware('permission:projects.measurement.view')
        ->name('measurement.index');

    Route::get('/structure/jobs', \App\Livewire\Structure\JobsIndex::class)
        ->middleware('permission:structure.positions.manage|structure.view')
        ->name('structure.jobs');

    Route::get('/structure/committees', \App\Livewire\Structure\CommitteesIndex::class)
        ->middleware('permission:structure.committees.manage|structure.view')
        ->name('structure.committees');

    Route::get('/documents/versions', \App\Livewire\Documents\DocumentVersionsIndex::class)
        ->middleware('permission:documents.manage-versions|documents.view')
        ->name('documents.versions');

    Route::get('/pay-scales', PayScalesIndex::class)
        ->middleware('permission:hr.salaries.manage')
        ->name('pay-scales.index');

    Route::get('/payroll-runs', PayrollRunsIndex::class)
        ->middleware('permission:hr.salaries.view')
        ->name('payroll-runs.index');

    Route::get('/financial-documents', FinancialDocumentsIndex::class)
        ->middleware('permission:finance.revenues.view')
        ->name('financial-documents.index');

    Route::get('/organizations', \App\Livewire\Partnerships\OrganizationsIndex::class)
        ->middleware('permission:partnerships.organizations.view')
        ->name('organizations.index');

    Route::get('/organizations/{organization}', \App\Livewire\Partnerships\OrganizationShow::class)
        ->middleware('permission:partnerships.organizations.view')
        ->name('organizations.show');

    Route::get('/partnerships/pipeline', \App\Livewire\Partnerships\PartnershipsPipeline::class)
        ->middleware('permission:partnerships.pipeline.view')
        ->name('partnerships.pipeline');

    Route::get('/partnerships/{partnership}', \App\Livewire\Partnerships\PartnershipShow::class)
        ->middleware('permission:partnerships.pipeline.view|partnerships.contracts.confirm|projects.update')
        ->name('partnerships.show');

    Route::get('/quotes/{quote}/pdf', \App\Http\Controllers\QuotePdfController::class)
        ->middleware('permission:partnerships.quotes.view')
        ->name('quotes.pdf');

    Route::get('/programs', \App\Livewire\Programs\ProgramsIndex::class)
        ->middleware('permission:projects.programs.view')
        ->name('programs.index');

    Route::get('/plan-templates', \App\Livewire\Programs\PlanTemplateEditor::class)
        ->middleware('permission:projects.templates.manage')
        ->name('plan-templates.index');

    Route::get('/programs/{program}', \App\Livewire\Programs\ProgramShow::class)
        ->middleware('permission:projects.programs.view')
        ->name('programs.show');

    Route::get('/files/programs/{programFile}', \App\Http\Controllers\ProgramFileDownloadController::class)
        ->middleware(['permission:projects.programs.view', 'throttle:files'])
        ->name('programs.files.download');

    Route::get('/tax-invoices/{taxInvoice}/pdf', \App\Http\Controllers\TaxInvoicePdfController::class)
        ->middleware('permission:finance.tax_invoices.view')
        ->name('tax-invoices.pdf');

    Route::get('/tax-invoices', \App\Livewire\Finance\TaxInvoicesIndex::class)
        ->middleware('permission:finance.tax_invoices.view')
        ->name('tax-invoices.index');

    Route::get('/budgets', BudgetsBoard::class)
        ->middleware('permission:finance.budgets.view')
        ->name('budgets.index');

    Route::get('/chart-of-accounts', ChartOfAccountsIndex::class)
        ->middleware('permission:finance.accounting.manage')
        ->name('chart-of-accounts.index');

    Route::get('/journal-entries', JournalEntriesIndex::class)
        ->middleware('permission:finance.accounting.manage')
        ->name('journal-entries.index');

    Route::get('/accounting-reports', AccountingReportsIndex::class)
        ->middleware('permission:finance.accounting.manage')
        ->name('accounting-reports.index');

    Route::get('/financial-reports/pdf', \App\Http\Controllers\FinancialReportPdfController::class)
        ->middleware('permission:finance.reports.view')
        ->name('financial-reports.pdf');
    Route::get('/financial-reports/excel', \App\Http\Controllers\FinancialReportExcelController::class)
        ->middleware('permission:finance.reports.view')
        ->name('financial-reports.excel');

    Route::get('/financial-reports', FinancialReportsIndex::class)
        ->middleware('permission:finance.reports.view')
        ->name('financial-reports.index');

    Route::get('/custodies', \App\Livewire\Finance\CustodiesIndex::class)
        ->middleware('permission:finance.custodies.view|finance.custodies.approve|finance.custodies.disburse')
        ->name('custodies.index');

    Route::get('/assets', \App\Livewire\Finance\AssetsIndex::class)
        ->middleware('permission:finance.assets.view|finance.assets.manage')
        ->name('assets.index');
    Route::redirect('/finance/assets', '/assets');

    Route::get('/revenues', \App\Livewire\Finance\RevenuesIndex::class)
        ->middleware('permission:finance.revenues.view|finance.revenues.manage')
        ->name('revenues.index');

    Route::get('/duties/download', DutiesFileDownloadController::class)
        ->name('duties.download');

    Route::get('/contracts', ContractsIndex::class)
        ->middleware('permission:partnerships.contracts.view')
        ->name('contracts.index');

    Route::get('/reports/center', \App\Livewire\Reports\ReportsCenter::class)
        ->middleware('permission:reports.view|reports.monthly.view|reports.projects.view|reports.impact.view|reports.kpis.view')
        ->name('reports.center');

    Route::get('/reports/audit-log', \App\Livewire\Reports\AuditLogIndex::class)
        ->middleware('permission:reports.audit-log.view')
        ->name('reports.audit-log');

    Route::get('/reports', ReportsIndex::class)
        ->middleware('permission:reports.view')
        ->name('reports.index');
});
