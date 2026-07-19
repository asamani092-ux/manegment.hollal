<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\ContractFileDownloadController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DutiesFileDownloadController;
use App\Http\Controllers\ExpenseFileDownloadController;
use App\Http\Controllers\TaskFileDownloadController;
use App\Livewire\Contracts\ContractsIndex;
use App\Livewire\Documents\DocumentsIndex;
use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\Finance\BudgetsBoard;
use App\Livewire\Finance\FinancialDocumentsIndex;
use App\Livewire\Finance\FinancialReportsIndex;
use App\Livewire\DashboardIndex;
use App\Livewire\Hr\PayrollRunsIndex;
use App\Livewire\Hr\PayScalesIndex;
use App\Livewire\Departments\DepartmentsIndex;
use App\Livewire\Meetings\MeetingMinutes;
use App\Livewire\Meetings\MeetingsIndex;
use App\Livewire\Meetings\OpenDecisionsIndex;
use App\Livewire\Partnerships\PartnershipGuestView;
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
use App\Livewire\Users\EmployeeProfileShow;
use App\Livewire\Users\UsersIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/partnership/guest/{token}', PartnershipGuestView::class)->name('partnership.guest');

// 05-B5 — the unique partner link portal: token-scoped, rate-limited, fully logged.
Route::middleware('throttle:portal')->group(function () {
    Route::get('/portal/{token}', \App\Livewire\Partnerships\PartnerPortal::class)->name('partner.portal');

    Route::get('/portal/{token}/contracts/{contract}/pdf', \App\Http\Controllers\PartnerPortalContractPdfController::class)
        ->name('partner.portal.contract.pdf');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/change-password', [ChangePasswordController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('/change-password', [ChangePasswordController::class, 'changePassword'])->name('password.change.update');
});

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::middleware('throttle:files')->group(function () {
        Route::get('/files/tasks/{task}/{type}', TaskFileDownloadController::class)
            ->whereIn('type', ['attachment', 'submitted'])
            ->name('tasks.files.download');

        Route::get('/files/contracts/{contract}', ContractFileDownloadController::class)
            ->name('contracts.files.download');

        Route::get('/files/expenses/{expenseRequest}', ExpenseFileDownloadController::class)
            ->name('expenses.files.download');

        Route::get('/files/documents/{document}', DocumentDownloadController::class)
            ->name('documents.files.download');
    });

    Route::get('/dashboard', DashboardIndex::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/projects', ProjectsIndex::class)
        ->middleware('permission:projects.view')
        ->name('projects.index');

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

    Route::get('/meetings', MeetingsIndex::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.index');

    Route::get('/meetings/open-decisions', OpenDecisionsIndex::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.open-decisions');

    Route::get('/meetings/{meeting}/minutes/pdf', \App\Http\Controllers\MeetingMinutesPdfController::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.minutes.pdf');

    Route::get('/meetings/{meeting}/minutes', MeetingMinutes::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.minutes');

    Route::get('/departments', DepartmentsIndex::class)
        ->middleware('permission:structure.departments.view')
        ->name('departments.index');

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
        ->middleware('permission:settings.manage')
        ->name('settings.index');

    Route::get('/users', UsersIndex::class)
        ->middleware('permission:hr.employees.view')
        ->name('users.index');

    Route::get('/users/{user}/profile', EmployeeProfileShow::class)
        ->middleware('permission:hr.employees.view')
        ->name('users.profile');

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
        ->middleware('permission:partnerships.pipeline.view')
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

    Route::get('/financial-reports/pdf', \App\Http\Controllers\FinancialReportPdfController::class)
        ->middleware('permission:finance.reports.view')
        ->name('financial-reports.pdf');

    Route::get('/financial-reports', FinancialReportsIndex::class)
        ->middleware('permission:finance.reports.view')
        ->name('financial-reports.index');

    Route::get('/duties/download', DutiesFileDownloadController::class)
        ->name('duties.download');

    Route::get('/contracts', ContractsIndex::class)
        ->middleware('permission:partnerships.contracts.view')
        ->name('contracts.index');

    Route::get('/reports', ReportsIndex::class)
        ->middleware('permission:reports.view')
        ->name('reports.index');
});
