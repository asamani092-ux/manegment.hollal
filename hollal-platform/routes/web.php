<?php

use App\Http\Controllers\Auth\AuthController;
use App\Livewire\Departments\DepartmentsIndex;
use App\Livewire\Partnerships\PartnershipGuestView;
use App\Livewire\Projects\ProjectsIndex;
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

Route::middleware(['auth'])->group(function () {
    Route::view('/dashboard', 'dashboard.index')
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/projects', ProjectsIndex::class)
        ->middleware('permission:projects.view')
        ->name('projects.index');

    Route::get('/tasks', TasksIndex::class)
        ->middleware('permission:tasks.view')
        ->name('tasks.index');

    Route::get('/departments', DepartmentsIndex::class)
        ->middleware('permission:departments.view')
        ->name('departments.index');

    Route::get('/settings/roles', RolesIndex::class)
        ->middleware('permission:roles.view')
        ->name('settings.roles');

    Route::get('/users', UsersIndex::class)
        ->middleware('permission:users.view')
        ->name('users.index');
});
