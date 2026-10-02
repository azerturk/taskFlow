<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})
    ->middleware(['auth', 'active-user'])
    ->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:taskflow-login')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth', 'active-user'])
    ->name('logout');

Route::middleware(['auth', 'active-user'])->group(function (): void {
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::middleware(['auth', 'active-user', 'can:manageUsers'])->prefix('admin/users')->as('admin.users.')->group(function (): void {
    Route::get('/', [AdminUserController::class, 'index'])->name('index');
    Route::get('/create', [AdminUserController::class, 'create'])->name('create');
    Route::post('/', [AdminUserController::class, 'store'])->name('store');
    Route::get('/{user}/edit', [AdminUserController::class, 'edit'])->name('edit');
    Route::match(['put', 'patch'], '/{user}', [AdminUserController::class, 'update'])->name('update');
    Route::put('/{user}/password', [AdminUserController::class, 'resetPassword'])->name('password.reset');
    Route::put('/{user}/suspend', [AdminUserController::class, 'suspend'])->name('suspend');
    Route::put('/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('reactivate');
});
