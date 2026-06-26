<?php

use App\Http\Controllers\HomeController;
use App\Modules\Authentication\Http\Controllers\AcceptInvitationController;
use App\Modules\Authentication\Http\Controllers\ProfileController;
use App\Modules\Business\Http\Controllers\BranchController;
use App\Modules\Business\Http\Controllers\BusinessSettingsController;
use App\Modules\Business\Http\Controllers\DashboardController;
use App\Modules\Business\Http\Controllers\EmployeeController;
use App\Modules\Business\Http\Controllers\WarehouseController;
use App\Modules\RBAC\Http\Controllers\RoleController;
use App\Modules\Subscription\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('signed')->group(function () {
    Route::get('/employee-invitations/{user}', [AcceptInvitationController::class, 'show'])
        ->name('employee-invitations.accept');
    Route::post('/employee-invitations/{user}', [AcceptInvitationController::class, 'store'])
        ->name('employee-invitations.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/settings/business', [BusinessSettingsController::class, 'edit'])->name('settings.business.edit');
    Route::patch('/settings/business', [BusinessSettingsController::class, 'update'])->name('settings.business.update');

    Route::get('/settings/subscription', [SubscriptionController::class, 'show'])->name('settings.subscription.show');

    Route::get('/settings/roles', [RoleController::class, 'index'])->name('settings.roles.index');
    Route::post('/settings/roles', [RoleController::class, 'store'])->name('settings.roles.store');
    Route::patch('/settings/roles/{role}', [RoleController::class, 'update'])->name('settings.roles.update');
    Route::delete('/settings/roles/{role}', [RoleController::class, 'destroy'])->name('settings.roles.destroy');

    Route::get('/settings/branches', [BranchController::class, 'index'])->name('settings.branches.index');
    Route::post('/settings/branches', [BranchController::class, 'store'])->name('settings.branches.store');
    Route::patch('/settings/branches/{branch}', [BranchController::class, 'update'])->name('settings.branches.update');
    Route::delete('/settings/branches/{branch}', [BranchController::class, 'destroy'])->name('settings.branches.destroy');

    Route::get('/settings/warehouses', [WarehouseController::class, 'index'])->name('settings.warehouses.index');
    Route::post('/settings/warehouses', [WarehouseController::class, 'store'])->name('settings.warehouses.store');
    Route::patch('/settings/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('settings.warehouses.update');
    Route::delete('/settings/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('settings.warehouses.destroy');

    Route::get('/settings/employees', [EmployeeController::class, 'index'])->name('settings.employees.index');
    Route::post('/settings/employees', [EmployeeController::class, 'store'])->name('settings.employees.store');
    Route::patch('/settings/employees/{employee}', [EmployeeController::class, 'update'])->name('settings.employees.update');
    Route::delete('/settings/employees/{employee}', [EmployeeController::class, 'destroy'])->name('settings.employees.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
