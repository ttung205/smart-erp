<?php

namespace App\Providers;

use App\Auth\InternalServiceKey;
use App\Auth\JwtVerifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InternalServiceKey::class, fn () => new InternalServiceKey((string) config('services.internal.key')));
        $this->app->bind(JwtVerifier::class, fn () => new JwtVerifier((string) config('services.identity.jwt_secret')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
