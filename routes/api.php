<?php

use App\Http\Controllers\Api\V1\AuthenticationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('api.v1.')->group(function (): void {
    Route::post('/auth/token', [AuthenticationController::class, 'issueToken'])
        ->middleware(['api', 'throttle:taskflow-token'])
        ->name('auth.token.issue');

    Route::middleware(['api', 'auth:sanctum', 'active-user', 'throttle:taskflow-api'])->group(function (): void {
        Route::get('/me', [AuthenticationController::class, 'me'])->name('me.show');
        Route::delete('/auth/token', [AuthenticationController::class, 'destroy'])->name('auth.token.destroy');
    });
});
