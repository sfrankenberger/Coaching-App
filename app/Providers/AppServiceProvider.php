<?php

namespace App\Providers;

use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use SocialiteProviders\Apple\Provider as AppleProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
        $this->app->singleton(Branding::class);
    }

    public function boot(): void
    {
        // Apple kommt nicht mit Socialite selbst, sondern aus socialiteproviders/apple.
        $this->app->make(SocialiteFactory::class)->extend('apple', function ($app) {
            $config = $app['config']['services.apple'] ?? [];

            return $app->make(SocialiteFactory::class)->buildProvider(AppleProvider::class, $config);
        });
    }
}
