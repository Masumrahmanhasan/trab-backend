<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\FeatureController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\UserProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/auth/login', [LoginController::class, 'login'])->name('auth.login');
    Route::post('/auth/register', [RegistrationController::class, 'register'])->name('auth.register');
    Route::post('/auth/email/verification-notification', [LoginController::class, 'sendVerificationEmail'])->name('auth.verification.send');
    Route::get('/auth/email/verify/{id}/{hash}', [LoginController::class, 'verifyEmail'])->name('auth.verification.verify');

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/profile', UserProfileController::class)->name('profile');

        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');

        // Feature routes (user-specific features based on subscription and role)
        Route::prefix('features')->group(function () {
            Route::get('/', [FeatureController::class, 'index'])->name('features.index');
            Route::get('/permissions', [FeatureController::class, 'permissions'])->name('features.permissions');
            Route::get('/{feature}', [FeatureController::class, 'show'])->name('features.show');
        });
    });
});
