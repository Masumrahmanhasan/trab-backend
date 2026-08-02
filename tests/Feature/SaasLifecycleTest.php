<?php

use App\Enums\Roles;
use App\Enums\SubscriptionStatus;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Store;
use App\Models\User;
use App\Services\Contracts\AuthenticationServiceInterface;
use App\Services\Contracts\StoreOnboardingServiceInterface;
use App\Services\Contracts\SubscriptionServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

describe('seeded RBAC', function () {
    it('creates namespaced permissions for both contexts', function () {
        expect(Permission::where('context', 'platform')->count())->toBeGreaterThan(0)
            ->and(Permission::where('context', 'store')->count())->toBeGreaterThan(0)
            ->and(Permission::where('key', 'platform.users.view')->exists())->toBeTrue()
            ->and(Permission::where('key', 'store.products.manage')->exists())->toBeTrue();
    });

    it('grants the super admin the full platform role with a subscription', function () {
        $superAdmin = User::where('email', config('permissions.super_admin_email'))->first();

        expect($superAdmin)->not->toBeNull()
            ->and($superAdmin->hasRole(Roles::SUPER_ADMIN->value))->toBeTrue()
            ->and($superAdmin->hasPermissionTo('platform.users.view'))->toBeTrue()
            ->and($superAdmin->hasPermissionTo('platform.system.manage'))->toBeTrue()
            ->and($superAdmin->hasPermissionTo('store.products.view'))->toBeFalse()
            ->and($superAdmin->activeSubscription)->not->toBeNull()
            ->and($superAdmin->activeSubscription->status)->toBe(SubscriptionStatus::TRIALING->value);
    });

    it('assigns the default user role to a plain demo user', function () {
        $demo = User::where('email', config('permissions.demo_user_email'))->first();

        expect($demo)->not->toBeNull()
            ->and($demo->hasRole(Roles::USER->value))->toBeTrue()
            ->and($demo->activeSubscription)->not->toBeNull()
            ->and($demo->activeSubscription->isOnTrial())->toBeTrue();
    });
});

describe('registration and trials', function () {
    it('registers a new merchant on a default-plan trial with the user role', function () {
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'New Merchant',
            'email' => 'merchant@test.com',
            'password' => 'password123',
        ]);

        expect($user->roles->pluck('key'))->toContain(Roles::USER->value)
            ->and($user->activeSubscription)->not->toBeNull()
            ->and($user->activeSubscription->plan->isDefault())->toBeTrue()
            ->and($user->activeSubscription->isOnTrial())->toBeTrue()
            ->and($user->activeSubscription->trial_ends_at)->not->toBeNull();
    });

    it('prevents a second subscription while one is current', function () {
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'New Merchant',
            'email' => 'merchant@test.com',
            'password' => 'password123',
        ]);

        $plan = Plan::where('slug', 'starter')->first();

        expect(fn () => app(SubscriptionServiceInterface::class)->createSubscription($user, $plan))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('store onboarding', function () {
    it('creates a store with owner role and store-scoped plan permissions', function () {
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'New Merchant',
            'email' => 'merchant@test.com',
            'password' => 'password123',
        ]);

        $store = app(StoreOnboardingServiceInterface::class)->createStoreWithPlan(
            ['name' => 'My Shop', 'slug' => 'my-shop'],
            $user,
            Plan::default()->first()->id
        );

        expect($store->plan_id)->toBe(Plan::default()->first()->id)
            ->and($user->roles()->wherePivot('team_id', $store->id)->pluck('key'))->toContain(Roles::OWNER->value)
            ->and($user->hasPermissionTo('store.settings.manage', $store->id))->toBeTrue()
            ->and($user->hasPermissionTo('store.settings.manage'))->toBeFalse();
    });

    it('enforces the plan store limit', function () {
        $plan = Plan::where('slug', 'default')->first();
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'New Merchant',
            'email' => 'merchant@test.com',
            'password' => 'password123',
        ]);

        $onboard = app(StoreOnboardingServiceInterface::class);

        for ($i = 1; $i <= $plan->max_stores; $i++) {
            $onboard->createStoreWithPlan(['name' => "Shop $i", 'slug' => "shop-$i"], $user, $plan->id);
        }

        expect(fn () => $onboard->createStoreWithPlan(
            ['name' => 'Overflow', 'slug' => 'overflow'],
            $user,
            $plan->id
        ))->toThrow(InvalidArgumentException::class);
    });
});

describe('staff access', function () {
    it('scopes staff role and permissions to the store', function () {
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password123',
        ]);

        $store = app(StoreOnboardingServiceInterface::class)->createStoreWithPlan(
            ['name' => 'My Shop', 'slug' => 'my-shop'],
            $user,
            Plan::default()->first()->id
        );

        $staff = User::factory()->create(['email' => 'staff@test.com']);
        $staff->assignRole(Roles::STAFF->value, $store->id);
        $staff->assignPermissionTo('store.orders.update', $store->id);

        expect($staff->hasRole(Roles::STAFF->value, $store->id))->toBeTrue()
            ->and($staff->hasRole(Roles::STAFF->value))->toBeFalse()
            ->and($staff->hasPermissionTo('store.orders.update', $store->id))->toBeTrue()
            ->and($staff->hasPermissionTo('store.orders.update'))->toBeFalse()
            ->and($staff->hasPermissionTo('store.settings.manage', $store->id))->toBeFalse();
    });
});

describe('plan management', function () {
    it('mirrors a plan change to owned stores and expands owner grants', function () {
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password123',
        ]);

        $store = app(StoreOnboardingServiceInterface::class)->createStoreWithPlan(
            ['name' => 'My Shop', 'slug' => 'my-shop'],
            $user,
            Plan::default()->first()->id
        );

        $before = $user->permissions()->wherePivot('team_id', $store->id)->count();
        $pro = Plan::where('slug', 'pro')->first();

        app(SubscriptionServiceInterface::class)->changePlan($user->activeSubscription, $pro);

        expect($store->fresh()->plan_id)->toBe($pro->id)
            ->and($user->permissions()->wherePivot('team_id', $store->id)->count())->toBeGreaterThan($before)
            ->and($user->hasPermissionTo('store.products.manage', $store->id))->toBeTrue()
            ->and($pro->features->contains('key', 'multi-store'))->toBeTrue();
    });

    it('preserves manually granted staff permissions across a plan change', function () {
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password123',
        ]);

        $store = app(StoreOnboardingServiceInterface::class)->createStoreWithPlan(
            ['name' => 'My Shop', 'slug' => 'my-shop'],
            $user,
            Plan::default()->first()->id
        );

        $staff = User::factory()->create(['email' => 'staff@test.com']);
        $staff->assignRole(Roles::STAFF->value, $store->id);
        $staff->assignPermissionTo('store.orders.update', $store->id);

        $pro = Plan::where('slug', 'pro')->first();
        app(SubscriptionServiceInterface::class)->changePlan($user->activeSubscription, $pro);

        expect($staff->hasPermissionTo('store.orders.update', $store->id))->toBeTrue();
    });

    it('cancels a subscription and tears down plan-derived access', function () {
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password123',
        ]);

        $store = app(StoreOnboardingServiceInterface::class)->createStoreWithPlan(
            ['name' => 'My Shop', 'slug' => 'my-shop'],
            $user,
            Plan::default()->first()->id
        );

        $subscription = app(SubscriptionServiceInterface::class)->cancelSubscription($user->activeSubscription);

        expect($subscription->isCancelled())->toBeTrue()
            ->and($user->permissions()->wherePivot('team_id', $store->id)->count())->toBe(0)
            ->and($user->roles()->wherePivot('team_id', $store->id)->count())->toBe(0);
    });
});

describe('feature synchronization', function () {
    it('propagates newly added features to existing plans via features:sync', function () {
        $plan = Plan::where('slug', 'starter')->first();
        $pro = Plan::where('slug', 'pro')->first();

        expect($plan->features->contains('key', 'multi-store'))->toBeTrue()
            ->and($pro->features->contains('key', 'multi-store'))->toBeTrue();

        $this->artisan('features:sync')->assertExitCode(0);

        expect(Plan::where('slug', 'starter')->first()->features->contains('key', 'multi-store'))->toBeTrue();
    });
});

describe('http api', function () {
    it('registers a user over the API and starts a trial subscription', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'API Merchant',
            'email' => 'api-merchant@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['token']]);

        $user = User::where('email', 'api-merchant@test.com')->first();

        expect($user)->not->toBeNull()
            ->and($user->hasRole(Roles::USER->value))->toBeTrue()
            ->and($user->activeSubscription)->not->toBeNull()
            ->and($user->activeSubscription->isOnTrial())->toBeTrue();
    });

    it('allows the super admin to reach the SaaS-owner dashboard', function () {
        $superAdmin = User::where('email', config('permissions.super_admin_email'))->first();
        $token = $superAdmin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/super-admin/dashboard')
            ->assertOk();
    });

    it('forbids a regular user from the SaaS-owner dashboard', function () {
        $demo = User::where('email', config('permissions.demo_user_email'))->first();
        $token = $demo->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/super-admin/dashboard')
            ->assertForbidden();
    });

    it('creates a store over the API and grants the owner scoped access', function () {
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'API Owner',
            'email' => 'api-owner@test.com',
            'password' => 'password123',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $defaultPlan = Plan::default()->first();

        $this->withToken($token)
            ->postJson('/api/v1/stores', [
                'name' => 'API Shop',
                'slug' => 'api-shop',
                'plan_id' => $defaultPlan->id,
            ])
            ->assertCreated();

        $store = Store::where('slug', 'api-shop')->first();

        expect($store)->not->toBeNull()
            ->and($user->hasRole(Roles::OWNER->value, $store->id))->toBeTrue()
            ->and($user->hasPermissionTo('store.settings.manage', $store->id))->toBeTrue();
    });

    it('rejects creating a store on a plan that differs from the subscription', function () {
        $user = app(AuthenticationServiceInterface::class)->registerWithSubscription([
            'name' => 'API Owner',
            'email' => 'api-owner@test.com',
            'password' => 'password123',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $pro = Plan::where('slug', 'pro')->first();

        $this->withToken($token)
            ->postJson('/api/v1/stores', [
                'name' => 'Leak Shop',
                'slug' => 'leak-shop',
                'plan_id' => $pro->id,
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Store plan must match your active subscription plan');

        expect(Store::where('slug', 'leak-shop')->exists())->toBeFalse();
    });
});
