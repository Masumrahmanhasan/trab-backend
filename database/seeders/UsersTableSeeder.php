<?php

namespace Database\Seeders;

use App\Enums\Roles;
use App\Models\Plan;
use App\Models\User;
use App\Services\Contracts\SubscriptionServiceInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Seed the initial accounts:
     *
     * 1. Super Admin — full platform role + an active subscription, so the
     *    SaaS-owner panel is usable straight away.
     * 2. A demo regular user — the "default user" experience: assigned the
     *    basic `user` role and put on a trial of the default plan, which is
     *    exactly what a fresh sign-up receives.
     *
     * Both are idempotent (matched on email).
     */
    public function run(): void
    {
        $this->createSuperAdmin();
        $this->createDemoUser();
    }

    protected function createSuperAdmin(): void
    {
        $superAdmin = User::query()->updateOrCreate(
            ['email' => config('permissions.super_admin_email', 'superadmin@gmail.com')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(config('permissions.super_admin_password', 'password')),
                'email_verified_at' => now(),
            ]
        );

        $superAdmin->assignRole(Roles::SUPER_ADMIN->value);
        $superAdmin->assignRole(Roles::USER->value);

        $this->ensureSubscription($superAdmin);
    }

    protected function createDemoUser(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => config('permissions.demo_user_email', 'user@example.com')],
            [
                'name' => 'Demo User',
                'password' => Hash::make(config('permissions.demo_user_password', 'password')),
                'email_verified_at' => now(),
            ]
        );

        // The `user` role is auto-assigned in User::booted() on creation;
        // re-seeding only needs it on the very first run.
        if (! $user->hasRole(Roles::USER->value)) {
            $user->assignRole(Roles::USER->value);
        }

        $this->ensureSubscription($user);
    }

    /**
     * Make sure the account has a current subscription on the default plan.
     */
    protected function ensureSubscription(User $user): void
    {
        if ($user->activeSubscription()->exists()) {
            return;
        }

        $defaultPlan = Plan::query()->default()->active()->first();

        if (! $defaultPlan) {
            return;
        }

        app(SubscriptionServiceInterface::class)
            ->createSubscription($user, $defaultPlan, null, ['starts_at' => now()]);
    }
}
