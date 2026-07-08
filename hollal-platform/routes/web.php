<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\ContractFileDownloadController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ExpenseFileDownloadController;
use App\Http\Controllers\TaskFileDownloadController;
use App\Livewire\Contracts\ContractsIndex;
use App\Livewire\Documents\DocumentsIndex;
use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\DashboardIndex;
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
use App\Livewire\Settings\RolesIndex;
use App\Livewire\Tasks\TasksIndex;
use App\Livewire\Users\UsersIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/partnership/guest/{token}', PartnershipGuestView::class)->name('partnership.guest');

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
    Route::get('/files/tasks/{task}/{type}', TaskFileDownloadController::class)
        ->whereIn('type', ['attachment', 'submitted'])
        ->name('tasks.files.download');

    Route::get('/files/contracts/{contract}', ContractFileDownloadController::class)
        ->name('contracts.files.download');

    Route::get('/files/expenses/{expenseRequest}', ExpenseFileDownloadController::class)
        ->name('expenses.files.download');

    Route::get('/files/documents/{document}', DocumentDownloadController::class)
        ->name('documents.files.download');

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
        ->middleware('permission:tasks.view')
        ->name('tasks.index');

    Route::get('/expenses', ExpensesIndex::class)
        ->middleware('permission:expenses.view|expenses.create|expenses.approve|expenses.pay')
        ->name('expenses.index');

    Route::get('/payroll', PayrollIndex::class)
        ->middleware('permission:salaries.view')
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

    Route::get('/meetings/{meeting}/minutes', MeetingMinutes::class)
        ->middleware('permission:meetings.view')
        ->name('meetings.minutes');

    Route::get('/departments', DepartmentsIndex::class)
        ->middleware('permission:departments.view')
        ->name('departments.index');

    Route::get('/settings/roles', RolesIndex::class)
        ->middleware('permission:roles.view')
        ->name('settings.roles');

    Route::get('/settings/expenses', ExpenseSettingsIndex::class)
        ->middleware('permission:settings.manage')
        ->name('settings.expenses');

    Route::get('/users', UsersIndex::class)
        ->middleware('permission:users.view')
        ->name('users.index');

    Route::get('/contracts', ContractsIndex::class)
        ->middleware('permission:contracts.view')
        ->name('contracts.index');

    Route::get('/reports', ReportsIndex::class)
        ->middleware('permission:reports.view')
        ->name('reports.index');
});
