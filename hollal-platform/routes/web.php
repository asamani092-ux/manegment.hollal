<?php

use App\Http\Controllers\AssetExcelController;
use App\Http\Controllers\AssetPdfController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ContractFileDownloadController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DutiesFileDownloadController;
use App\Http\Controllers\EmployeeDocumentDownloadController;
use App\Http\Controllers\ExpenseFileDownloadController;
use App\Http\Controllers\FinancialDocumentDownloadController;
use App\Http\Controllers\FinancialReportExcelController;
use App\Http\Controllers\FinancialReportPdfController;
use App\Http\Controllers\MeetingMinutesPdfController;
use App\Http\Controllers\MeetingSignedMinutesController;
use App\Http\Controllers\PartnerPortalActionController;
use App\Http\Controllers\PartnerPortalContractPdfController;
use App\Http\Controllers\ProgramFileDownloadController;
use App\Http\Controllers\QuotePdfController;
use App\Http\Controllers\RevenueFileDownloadController;
use App\Http\Controllers\TaskFileDownloadController;
use App\Http\Controllers\TaxInvoicePdfController;
use App\Livewire\Contracts\ContractsIndex;
use App\Livewire\DashboardIndex;
use App\Livewire\Documents\DocumentPoliciesIndex;
use App\Livewire\Documents\DocumentsIndex;
use App\Livewire\Documents\DocumentTemplatesIndex;
use App\Livewire\Documents\DocumentVersionsIndex;
use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\Finance\AccountingCloseIndex;
use App\Livewire\Finance\AccountingReportsIndex;
use App\Livewire\Finance\AssetsIndex;
use App\Livewire\Finance\BudgetsBoard;
use App\Livewire\Finance\ChartOfAccountsIndex;
use App\Livewire\Finance\CustodiesIndex;
use App\Livewire\Finance\FinancialDocumentsIndex;
use App\Livewire\Finance\JournalEntriesIndex;
use App\Livewire\Finance\FinancialReportsIndex;
use App\Livewire\Finance\RevenuesIndex;
use App\Livewire\Finance\TaxInvoicesIndex;
use App\Livewire\Hr\AttendanceIndex;
use App\Livewire\Hr\EvaluationsIndex;
use App\Livewire\Hr\HrLifecycleIndex;
use App\Livewire\Hr\LeavesIndex;
use App\Livewire\Hr\PayrollRunsIndex;
use App\Livewire\Hr\PayScalesIndex;
use App\Livewire\Hr\ResponsibilitiesIndex;
use App\Livewire\Meetings\MeetingGuestPortal;
use App\Livewire\Meetings\MeetingMinutes;
use App\Livewire\Meetings\MeetingsArchiveIndex;
use App\Livewire\Meetings\MeetingsIndex;
use App\Livewire\Meetings\OpenDecisionsIndex;
use App\Livewire\Partnerships\OrganizationShow;
use App\Livewire\Partnerships\DiagnosisQuestionsIndex;
use App\Livewire\Partnerships\OrganizationsIndex;
use App\Livewire\Partnerships\PartnerPortal;
use App\Livewire\Partnerships\PartnershipShow;
use App\Livewire\Partnerships\PartnershipsPipeline;
use App\Livewire\Payroll\PayrollIndex;
use App\Livewire\Programs\PlanTemplateEditor;
use App\Livewire\Programs\ProgramShow;
use App\Livewire\Programs\ProgramsIndex;
use App\Livewire\Projects\MeasurementIndex;
use App\Livewire\Projects\ProjectExecution;
use App\Livewire\Projects\ProjectShow;
use App\Livewire\Projects\ProjectsIndex;
use App\Livewire\Projects\VisitsIndex;
use App\Livewire\Reports\AuditLogIndex;
use App\Livewire\Reports\ReportsCenter;
use App\Livewire\Reports\ReportsIndex;
use App\Livewire\Settings\ExpenseSettingsIndex;
use App\Livewire\Settings\GrantsIndex;
use App\Livewire\Settings\MailSettingsIndex;
use App\Livewire\Settings\SettingsIndex;
use App\Livewire\Structure\CommitteesIndex;
use App\Livewire\Structure\JobsIndex;
use App\Livewire\Structure\OrgTreeIndex;
use App\Livewire\Tasks\TasksCalendar;
use App\Livewire\Tasks\TasksIndex;
use App\Livewire\Tasks\TeamTasksIndex;
use App\Livewire\Uat\ToolsChecklist;
use App\Livewire\Users\EmployeeProfileShow;
use App\Livewire\Users\UsersIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// 05-B5 — the unique partner link portal: token-scoped, rate-limited, fully logged.
Route::middleware('throttle:portal')->group(function () {
    Route::get('/portal/{token}', PartnerPortal::class)->name('partner.portal');

    Route::get('/portal/{token}/contracts/{contract}/pdf', PartnerPortalContractPdfController::class)
        ->name('partner.portal.contract.pdf');

    Route::get('/portal/{token}/{page}', PartnerPortal::class)
        ->whereIn('page', ['programs', 'diagnosis', 'quotes', 'payments', 'contract'])
        ->name('partner.portal.page');

    Route::post('/portal/{token}/actions/programs', [PartnerPortalActionController::class, 'confirmPrograms'])
        ->name('partner.portal.programs.save');
    Route::post('/portal/{token}/actions/diagnosis', [PartnerPortalActionController::class, 'submitDiagnosis'])
        ->name('partner.portal.diagnosis.save');
    Route::post('/portal/{token}/actions/quotes/{quote}', [PartnerPortalActionController::class, 'acceptQuote'])
        ->name('partner.portal.quotes.accept');

    // P2 wave C — external meeting guest (no employee account): view + confirm/sign.
    Route::get('/meetings/guest/{token}', MeetingGuestPortal::class)
        ->name('meetings.guest.portal');
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

        Route::get('/files/employee-documents/{employeeDocument}', EmployeeDocumentDownloadController::class)
            ->name('employee-documents.files.download');

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

    Route::get('/my-space', \App\Livewire\Hr\EmployeeHub::class)
        ->middleware('permission:dashboard.view')
        ->name('employee-hub.index');

    Route::get('/uat/tools', ToolsChecklist::class)
        ->middleware(['uat.enabled', 'permission:dashboard.view'])
        ->name('uat.tools');

    Route::get('/projects', ProjectsIndex::class)
        ->middleware('permission:projects.view')
        ->name('projects.index');

    Route::get('/projects/{project}/execution', ProjectExecution::class)
        ->middleware('permission:projects.view')
        ->name('projects.execution');

    Route::get('/projects/{project}', ProjectShow::class)
        ->middleware('permission:projects.view')
        ->name('projects.show');

    Route::get('/tasks', TasksIndex::class)
        ->middleware('permission:esnad.tasks.view')
        ->name('tasks.index');

    Route::get('/team-tasks', TeamTasksIndex::class)
        ->middleware('permission:esnad.tasks.team.view')
        ->name('team-tasks.index');

    Route::get('/tasks-calendar', TasksCalendar::class)
        ->middleware('permission:esnad.tasks.view')
        ->name('tasks-calendar.index');

    Route::get('/recurring-tasks', fn () => redirect()->route('team-tasks.index', ['tab' => 'recurring']))
        ->middleware('permission:esnad.tasks.team.view')
        ->name('recurring-tasks.index');

    Route::get('/workload-board', fn () => redirect()->route(
        'team-tasks.index',
        ['tab' => in_array(request()->query('tab'), ['loads', 'recurring', 'reminders'], true)
            ? request()->query('tab')
            : 'loads']
    ))
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

    Route::get('/documents/templates', DocumentTemplatesIndex::class)
        ->middleware('permission:documents.view|documents.templates.manage')
        ->name('documents.templates');

    Route::get('/documents/policies', DocumentPoliciesIndex::class)
        ->middleware('permission:documents.policies.manage')
        ->name('documents.policies');

    Route::get('/meetings', MeetingsIndex::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.index');

    Route::get('/meetings/open-decisions', OpenDecisionsIndex::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.open-decisions');

    Route::get('/meetings/archive', MeetingsArchiveIndex::class)
        ->middleware('permission:meetings.view|documents.view')
        ->name('meetings.archive');

    // P2 wave C — minutes/PDF access is Policy-gated (attendees included), not
    // limited to the global meetings.view permission. See MeetingPolicy::view.
    Route::get('/meetings/{meeting}/minutes/pdf', MeetingMinutesPdfController::class)
        ->name('meetings.minutes.pdf');

    Route::get('/meetings/{meeting}/minutes/signed', MeetingSignedMinutesController::class)
        ->name('meetings.minutes.signed');

    Route::get('/meetings/{meeting}/minutes', MeetingMinutes::class)
        ->name('meetings.minutes');

    Route::get('/settings/grants', GrantsIndex::class)
        ->middleware('permission:roles.view')
        ->name('settings.grants');

    Route::get('/settings/roles', function () {
        return redirect()->route('settings.grants', ['tab' => 'entities']);
    })->middleware('permission:roles.view')->name('settings.roles');

    Route::get('/structure/org-tree', OrgTreeIndex::class)
        ->middleware('permission:structure.view|structure.manage')
        ->name('structure.org-tree');

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

    Route::get('/attendance', AttendanceIndex::class)
        ->middleware('permission:hr.employees.view')
        ->name('attendance.index');

    Route::get('/attendance/cycle', \App\Livewire\Hr\AttendanceCycleIndex::class)
        ->middleware('permission:hr.employees.update')
        ->name('attendance.cycle');

    Route::get('/leaves', LeavesIndex::class)
        ->middleware('permission:hr.leaves.request|hr.leaves.approve|hr.leaves.view-all|hr.employees.view')
        ->name('leaves.index');

    Route::get('/evaluations', EvaluationsIndex::class)
        ->name('evaluations.index');

    // Round 5ب — legacy eval screens redirect into the unified wizard.
    Route::get('/evaluation-templates', fn () => redirect()->route('evaluations.index', ['step' => 'template']))
        ->middleware('permission:hr.employees.update')
        ->name('evaluation-templates.index');

    Route::get('/evaluation-cycles', fn () => redirect()->route('evaluations.index', ['step' => 'cycle']))
        ->middleware('permission:hr.employees.update')
        ->name('evaluation-cycles.index');

    Route::get('/my-evaluations', function () {
        return redirect()->to(route('users.profile', auth()->id()).'?tab=log');
    })->name('employee-evaluations.mine');

    Route::get('/team-evaluations', fn () => redirect()->route('evaluations.index', ['step' => 'score']))
        ->name('employee-evaluations.team');

    Route::get('/responsibilities', ResponsibilitiesIndex::class)
        ->middleware('permission:hr.employees.update')
        ->name('responsibilities.index');

    Route::get('/hr-lifecycle', HrLifecycleIndex::class)
        ->middleware('permission:hr.employees.update')
        ->name('hr-lifecycle.index');

    Route::get('/visits', VisitsIndex::class)
        ->middleware('permission:projects.visits.view')
        ->name('visits.index');

    Route::get('/measurement', MeasurementIndex::class)
        ->middleware('permission:projects.measurement.view')
        ->name('measurement.index');

    Route::get('/structure/jobs', JobsIndex::class)
        ->middleware('permission:structure.positions.manage|structure.view')
        ->name('structure.jobs');

    Route::get('/structure/committees', CommitteesIndex::class)
        ->middleware('permission:structure.committees.manage|structure.view')
        ->name('structure.committees');

    Route::get('/documents/versions', DocumentVersionsIndex::class)
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

    Route::get('/organizations', OrganizationsIndex::class)
        ->middleware('permission:partnerships.organizations.view')
        ->name('organizations.index');

    Route::get('/organizations/{organization}', OrganizationShow::class)
        ->middleware('permission:partnerships.organizations.view')
        ->name('organizations.show');

    Route::get('/partnerships/diagnosis-questions', DiagnosisQuestionsIndex::class)
        ->middleware('permission:partnerships.organizations.view')
        ->name('partnerships.diagnosis-questions.index');

    Route::get('/partnerships/pipeline', PartnershipsPipeline::class)
        ->middleware('permission:partnerships.pipeline.view')
        ->name('partnerships.pipeline');

    Route::get('/partnerships/{partnership}', PartnershipShow::class)
        ->middleware('permission:partnerships.pipeline.view|partnerships.contracts.confirm|projects.update')
        ->name('partnerships.show');

    Route::get('/quotes/{quote}/pdf', QuotePdfController::class)
        ->middleware('permission:partnerships.quotes.view')
        ->name('quotes.pdf');

    Route::get('/programs', ProgramsIndex::class)
        ->middleware('permission:projects.programs.view')
        ->name('programs.index');

    Route::get('/plan-templates', PlanTemplateEditor::class)
        ->middleware('permission:projects.templates.manage')
        ->name('plan-templates.index');

    Route::get('/programs/{program}', ProgramShow::class)
        ->middleware('permission:projects.programs.view')
        ->name('programs.show');

    Route::get('/files/programs/{programFile}', ProgramFileDownloadController::class)
        ->middleware(['permission:projects.programs.view', 'throttle:files'])
        ->name('programs.files.download');

    Route::get('/tax-invoices/{taxInvoice}/pdf', TaxInvoicePdfController::class)
        ->middleware('permission:finance.tax_invoices.view')
        ->name('tax-invoices.pdf');

    Route::get('/tax-invoices', TaxInvoicesIndex::class)
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

    Route::get('/accounting-close', AccountingCloseIndex::class)
        ->middleware('permission:finance.accounting.manage')
        ->name('accounting-close.index');

    Route::get('/financial-reports/pdf', FinancialReportPdfController::class)
        ->middleware('permission:finance.reports.view')
        ->name('financial-reports.pdf');
    Route::get('/financial-reports/excel', FinancialReportExcelController::class)
        ->middleware('permission:finance.reports.view')
        ->name('financial-reports.excel');

    Route::get('/financial-reports', FinancialReportsIndex::class)
        ->middleware('permission:finance.reports.view')
        ->name('financial-reports.index');

    Route::get('/custodies', CustodiesIndex::class)
        ->middleware('permission:finance.custodies.view|finance.custodies.approve|finance.custodies.disburse')
        ->name('custodies.index');

    Route::get('/assets/excel', AssetExcelController::class)
        ->middleware('permission:finance.assets.view|finance.assets.manage')
        ->name('assets.excel');
    Route::get('/assets/pdf', AssetPdfController::class)
        ->middleware('permission:finance.assets.view|finance.assets.manage')
        ->name('assets.pdf');
    Route::get('/assets', AssetsIndex::class)
        ->middleware('permission:finance.assets.view|finance.assets.manage')
        ->name('assets.index');
    Route::redirect('/finance/assets', '/assets');

    Route::get('/revenues', RevenuesIndex::class)
        ->middleware('permission:finance.revenues.view|finance.revenues.manage')
        ->name('revenues.index');

    Route::get('/revenues/{revenue}/files', RevenueFileDownloadController::class)
        ->middleware('permission:finance.revenues.view|finance.revenues.manage')
        ->name('revenues.files.download');

    Route::get('/duties/download', DutiesFileDownloadController::class)
        ->name('duties.download');

    Route::get('/contracts', ContractsIndex::class)
        ->middleware('permission:partnerships.contracts.view')
        ->name('contracts.index');

    Route::get('/reports/center', ReportsCenter::class)
        ->middleware('permission:reports.view|reports.monthly.view|reports.projects.view|reports.impact.view|reports.kpis.view')
        ->name('reports.center');

    Route::get('/reports/audit-log', AuditLogIndex::class)
        ->middleware('permission:reports.audit-log.view')
        ->name('reports.audit-log');

    Route::get('/reports', ReportsIndex::class)
        ->middleware('permission:reports.view')
        ->name('reports.index');
});
