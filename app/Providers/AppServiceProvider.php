<?php

namespace App\Providers;

use App\Models\Store;
use App\Policies\StorePolicy;
use App\Services\AuthenticationService;
use App\Services\Contracts\AuthenticationServiceInterface;
use App\Services\Contracts\StoreOnboardingServiceInterface;
use App\Services\Contracts\SubscriptionServiceInterface;
use App\Services\Contracts\UserFeatureServiceInterface;
use App\Services\StoreOnboardingService;
use App\Services\SubscriptionService;
use App\Services\UserFeatureService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthenticationServiceInterface::class, AuthenticationService::class);
        $this->app->bind(StoreOnboardingServiceInterface::class, StoreOnboardingService::class);
        $this->app->bind(SubscriptionServiceInterface::class, SubscriptionService::class);
        $this->app->bind(UserFeatureServiceInterface::class, UserFeatureService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }

    protected function registerPolicies(): void
    {
        \Gate::policy(Store::class, StorePolicy::class);
    }
}
