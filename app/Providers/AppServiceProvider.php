<?php

namespace App\Providers;

use App\Services\AccessToken;
use App\Services\ScopeRepository;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();

        // The shared SQL Server login defaults to Spanish (dmy), which breaks
        // Laravel's "Y-m-d H:i:s" timestamps; force ymd for this app only.
        $useYmd = function (Connection $connection) {
            if ($connection->getDriverName() === 'sqlsrv') {
                $connection->unprepared('SET DATEFORMAT ymd');
            }
        };

        Event::listen(fn (ConnectionEstablished $event) => $useYmd($event->connection));

        // Connections opened before this provider booted.
        foreach (DB::getConnections() as $connection) {
            $useYmd($connection);
        }

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

        // Tokens issued for one API name it in "aud".
        Passport::useAccessTokenEntity(AccessToken::class);

        Passport::defaultScopes(config('oauth.default_scopes'));

        Passport::tokensExpireIn(now()->addMinutes(config('oauth.access_token_ttl')));
        Passport::refreshTokensExpireIn(now()->addMinutes(config('oauth.refresh_token_ttl')));
        Passport::clientCredentialsTokensExpireIn(now()->addMinutes(config('oauth.client_token_ttl')));
    }
}
