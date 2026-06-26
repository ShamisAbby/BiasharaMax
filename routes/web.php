<?php

use App\Modules\Authentication\Http\Controllers\ProfileController;
use App\Modules\Business\Http\Controllers\BusinessSettingsController;
use App\Modules\Business\Http\Controllers\DashboardController;
use App\Modules\RBAC\Http\Controllers\RoleController;
use App\Modules\Subscription\Http\Controllers\SubscriptionController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
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
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
