<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\FeatureController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SuperAdminController;
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
        // Plan routes (public for authenticated users)
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');

        // Feature routes (user-specific features based on subscription and role)
        Route::prefix('features')->group(function () {
            Route::get('/', [FeatureController::class, 'index'])->name('features.index');
            Route::get('/permissions', [FeatureController::class, 'permissions'])->name('features.permissions');
            Route::get('/{feature}', [FeatureController::class, 'show'])->name('features.show');
        });

        // Subscription routes (manage subscriptions without requiring subscription)
        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::get('/active', [SubscriptionController::class, 'active'])->name('subscriptions.active');
            Route::post('/', [SubscriptionController::class, 'store'])->name('subscriptions.store');
            Route::get('/{id}', [SubscriptionController::class, 'show'])->name('subscriptions.show')->whereNumber('id');
            Route::post('/{id}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel')->whereNumber('id');
            Route::post('/{id}/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume')->whereNumber('id');
            Route::post('/{id}/change-plan', [SubscriptionController::class, 'changePlan'])->name('subscriptions.change-plan')->whereNumber('id');
            Route::post('/{id}/renew', [SubscriptionController::class, 'renew'])->name('subscriptions.renew')->whereNumber('id');
        });

        // Store routes (require subscription)
        Route::middleware('subscription')->group(function () {
            Route::apiResource('stores', StoreController::class)->names([
                'index' => 'stores.index',
                'store' => 'stores.store',
                'show' => 'stores.show',
                'update' => 'stores.update',
                'destroy' => 'stores.destroy',
            ]);

            // Store additional routes
            Route::prefix('stores/{store}')->group(function () {
                Route::get('/staff', [StoreController::class, 'staff'])->name('stores.staff');
                Route::post('/staff', [StoreController::class, 'assignStaff'])->name('stores.staff.assign');
                Route::delete('/staff/{staff}', [StoreController::class, 'removeStaff'])->name('stores.staff.remove');
                Route::put('/staff/{staff}/permissions', [StoreController::class, 'updateStaffPermissions'])->name('stores.staff.permissions.update');
                Route::get('/permissions', [StoreController::class, 'availablePermissions'])->name('stores.permissions');
                Route::get('/features', [FeatureController::class, 'storeFeatures'])->name('stores.features');
                Route::get('/permissions/features', [FeatureController::class, 'storePermissions'])->name('stores.permissions.features');
            });
        });

        // Superadmin routes (require super admin role)
        Route::middleware('superadmin')->prefix('superadmin')->group(function () {
            // Dashboard and statistics
            Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('superadmin.dashboard');
            Route::get('/system-health', [SuperAdminController::class, 'systemHealth'])->name('superadmin.system-health');
            Route::get('/activity-logs', [SuperAdminController::class, 'activityLogs'])->name('superadmin.activity-logs');

            // User management
            Route::get('/users', [SuperAdminController::class, 'users'])->name('superadmin.users');
            Route::get('/users/{user}', [SuperAdminController::class, 'showUser'])->name('superadmin.users.show');
            Route::put('/users/{user}', [SuperAdminController::class, 'updateUser'])->name('superadmin.users.update');
            Route::delete('/users/{user}', [SuperAdminController::class, 'deleteUser'])->name('superadmin.users.delete');
            Route::post('/users/{user}/roles', [SuperAdminController::class, 'assignRole'])->name('superadmin.users.assign-role');
            Route::delete('/users/{user}/roles/{role}', [SuperAdminController::class, 'removeRole'])->name('superadmin.users.remove-role');

            // Role and permission management
            Route::get('/roles', [SuperAdminController::class, 'roles'])->name('superadmin.roles');
            Route::get('/permissions', [SuperAdminController::class, 'permissions'])->name('superadmin.permissions');

            // Plan and feature management
            Route::get('/plans', [SuperAdminController::class, 'plans'])
                ->name('superadmin.plans');
            Route::get('/features', [SuperAdminController::class, 'features'])
                ->name('superadmin.features');

            // Subscription management
            Route::get('/subscriptions', [SuperAdminController::class, 'subscriptions'])
                ->name('superadmin.subscriptions');
            Route::post('/users/{user}/subscription/manage',
                [SuperAdminController::class, 'manageSubscription'])
                ->name('superadmin.subscriptions.manage');

            // Store management
            Route::get('/stores', [SuperAdminController::class, 'stores'])
                ->name('superadmin.stores');
            Route::get('/stores/{store}', [SuperAdminController::class, 'showStore'])
                ->name('superadmin.stores.show');
        });
    });
});
