<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\FeatureController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SuperAdminController;
use App\Http\Controllers\Api\V1\UserProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public routes handle registration/login/verification. Everything else sits
| behind `auth:sanctum`. The super-admin group is additionally guarded by the
| `superadmin` middleware, and store routes resolve the active tenant through
| the `set.team.context` middleware (route store -> X-Team-Id header -> team_id
| query parameter).
|
*/

Route::prefix('v1')->group(function () {
    // Public: authentication
    Route::post('/auth/login', [LoginController::class, 'login'])->name('auth.login');
    Route::post('/auth/register', [RegistrationController::class, 'register'])->name('auth.register');
    Route::post('/auth/email/verification-notification', [LoginController::class, 'sendVerificationEmail'])->name('auth.verification.send');
    Route::get('/auth/email/verify/{id}/{hash}', [LoginController::class, 'verifyEmail'])->name('auth.verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', UserProfileController::class)->name('profile');

        // Plans are public catalog data, but list/show stay authenticated so
        // the response can reflect the caller's plan state.
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');

        // Features available to the authenticated user (based on subscription/roles)
        Route::prefix('features')->group(function () {
            Route::get('/', [FeatureController::class, 'index'])->name('features.index');
            Route::get('/permissions', [FeatureController::class, 'permissions'])->name('features.permissions');
            Route::get('/{feature}', [FeatureController::class, 'show'])->name('features.show');
        });

        // Subscriptions (self-service)
        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::post('/', [SubscriptionController::class, 'store'])->name('subscriptions.store');
            Route::get('/active', [SubscriptionController::class, 'active'])->name('subscriptions.active');
            Route::get('/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
            Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
            Route::post('/{subscription}/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');
            Route::patch('/{subscription}/plan', [SubscriptionController::class, 'changePlan'])->name('subscriptions.change-plan');
            Route::post('/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('subscriptions.renew');
        });

        // Stores (tenant workspaces)
        Route::middleware('set.team.context')->prefix('stores')->group(function () {
            Route::get('/', [StoreController::class, 'index'])->name('stores.index');
            Route::post('/', [StoreController::class, 'store'])->name('stores.store');
            Route::get('/{store}', [StoreController::class, 'show'])->name('stores.show');
            Route::patch('/{store}', [StoreController::class, 'update'])->name('stores.update');
            Route::delete('/{store}', [StoreController::class, 'destroy'])->name('stores.destroy');
            Route::get('/{store}/staff', [StoreController::class, 'staff'])->name('stores.staff');
            Route::get('/{store}/permissions', [StoreController::class, 'availablePermissions'])->name('stores.permissions');
            Route::post('/{store}/staff', [StoreController::class, 'assignStaff'])->name('stores.staff.assign');
            Route::delete('/{store}/staff/{staff}', [StoreController::class, 'removeStaff'])->name('stores.staff.remove');
            Route::put('/{store}/staff/{staff}/permissions', [StoreController::class, 'updateStaffPermissions'])->name('stores.staff.permissions');
        });

        // SaaS-owner panel (super admin)
        Route::middleware('superadmin')->prefix('super-admin')->group(function () {
            Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('super-admin.dashboard');

            Route::get('/users', [SuperAdminController::class, 'users'])->name('super-admin.users');
            Route::get('/users/{user}', [SuperAdminController::class, 'showUser'])->name('super-admin.users.show');
            Route::patch('/users/{user}', [SuperAdminController::class, 'updateUser'])->name('super-admin.users.update');
            Route::delete('/users/{user}', [SuperAdminController::class, 'deleteUser'])->name('super-admin.users.delete');
            Route::post('/users/{user}/roles', [SuperAdminController::class, 'assignRole'])->name('super-admin.users.roles.assign');
            Route::delete('/users/{user}/roles/{roleKey}', [SuperAdminController::class, 'removeRole'])->name('super-admin.users.roles.remove');
            Route::patch('/users/{user}/subscription', [SuperAdminController::class, 'manageSubscription'])->name('super-admin.users.subscription');

            Route::get('/roles', [SuperAdminController::class, 'roles'])->name('super-admin.roles');
            Route::get('/permissions', [SuperAdminController::class, 'permissions'])->name('super-admin.permissions');
            Route::get('/plans', [SuperAdminController::class, 'plans'])->name('super-admin.plans');
            Route::get('/features', [SuperAdminController::class, 'features'])->name('super-admin.features');
            Route::get('/subscriptions', [SuperAdminController::class, 'subscriptions'])->name('super-admin.subscriptions');

            Route::get('/stores', [SuperAdminController::class, 'stores'])->name('super-admin.stores');
            Route::get('/stores/{store}', [SuperAdminController::class, 'showStore'])->name('super-admin.stores.show');

            Route::get('/activity-logs', [SuperAdminController::class, 'activityLogs'])->name('super-admin.activity-logs');
            Route::get('/health', [SuperAdminController::class, 'systemHealth'])->name('super-admin.health');
        });
    });
});
