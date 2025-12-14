<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonInterval;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassportTokenLifeTime();
    }

    private function configurePassportTokenLifeTime(): void
    {
        Passport::enablePasswordGrant();
        Passport::tokensExpireIn(CarbonInterval::minutes(3));
        Passport::refreshTokensExpireIn(CarbonInterval::days());
        Passport::personalAccessTokensExpireIn(CarbonInterval::days(2));
    }
}
