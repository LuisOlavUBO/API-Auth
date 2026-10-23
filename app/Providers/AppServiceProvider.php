<?php

namespace App\Providers;

use App\Services\AccessTokenRepository;
use App\Services\ScopeRepository;
use App\Services\TokenAudience;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scopes live in the database and are filtered by the user's role.
        $this->app->bind(PassportScopeRepository::class, ScopeRepository::class);

        // Tokens issued for one API name it in "aud": ScopeRepository validates
        // the API and AccessTokenRepository creates the token with it.
        $this->app->singleton(TokenAudience::class);
        $this->app->bind(PassportAccessTokenRepository::class, AccessTokenRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();

        // Registered when the limiter is first used: resolving it here would open
        // the cache store (and its connection) on every artisan command.
        $this->callAfterResolving(RateLimiter::class, function (RateLimiter $limiter) {
            $limiter->for('auth', function (Request $request) {
                return Limit::perMinute(10)->by($request->input('email').'|'.$request->ip());
            });
        });
    }

    /**
     * Configure grants, scopes and token lifetimes for Passport.
     */
    protected function configurePassport(): void
    {
        Passport::enablePasswordGrant();

        Passport::defaultScopes(config('oauth.default_scopes'));

        Passport::tokensExpireIn(now()->addMinutes(config('oauth.access_token_ttl')));
        Passport::refreshTokensExpireIn(now()->addMinutes(config('oauth.refresh_token_ttl')));
        Passport::clientCredentialsTokensExpireIn(now()->addMinutes(config('oauth.client_token_ttl')));
    }
}
