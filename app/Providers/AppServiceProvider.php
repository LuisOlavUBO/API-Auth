<?php

namespace App\Providers;

use App\Models\Client;
use App\Services\AccessTokenRepository;
use App\Services\ClientRepository;
use App\Services\ScopeRepository;
use App\Services\TokenAudience;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use Laravel\Passport\ClientRepository as PassportClientRepository;
use Laravel\Passport\Passport;
use SocialiteProviders\Azure\AzureExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scopes live in the database and are filtered by the user's role.
        $this->app->bind(PassportScopeRepository::class, ScopeRepository::class);

        // Clients of a disabled application are not active.
        $this->app->singleton(PassportClientRepository::class, ClientRepository::class);

        // Tokens issued for one API name it in "aud": ScopeRepository validates
        // the API and AccessTokenRepository creates the token with it.
        $this->app->singleton(TokenAudience::class);
        $this->app->bind(PassportAccessTokenRepository::class, AccessTokenRepository::class);

        // Only the token and authorize endpoints are used: routes/api.php and routes/web.php register
        // them under the API prefix. The rest (approve, deny, device, token/refresh) is not published.
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();

        // Socialite's "azure" driver, for the login with Microsoft Entra ID.
        Event::listen(SocialiteWasCalled::class, [AzureExtendSocialite::class, 'handle']);

        // Registered when the limiter is first used: resolving it here would open
        // the cache store (and its connection) on every artisan command.
        $this->callAfterResolving(RateLimiter::class, function (RateLimiter $limiter) {
            $limiter->for('auth', function (Request $request) {
                return Limit::perMinute(10)->by($request->input('email').'|'.$request->ip());
            });

            // One satellite must not use up the token requests of the others behind the same IP.
            // The client may authenticate with Basic instead of sending client_id.
            $limiter->for('oauth-token', function (Request $request) {
                return Limit::perMinute(config('oauth.token_rate_limit'))
                    ->by($request->input('client_id') ?: $request->getUser() ?: $request->ip());
            });
        });
    }

    /**
     * Configure grants, scopes and token lifetimes for Passport. The password grant stays off:
     * people only get tokens through the central login. Passport has no default scopes: a
     * request without "scope" reaches ScopeRepository as an empty list.
     */
    protected function configurePassport(): void
    {
        Passport::useClientModel(Client::class);

        // Clients never ask for consent. This view only answers prompt=consent, which would
        // otherwise show an approval page whose routes are not published.
        Passport::authorizationView(fn () => response()->view('auth.message', [
            'title' => 'Solicitud no válida',
            'message' => 'Esta aplicación no requiere consentimiento: quita prompt=consent de la solicitud.',
        ], 400));

        Passport::tokensExpireIn(now()->addMinutes(config('oauth.access_token_ttl')));
        Passport::refreshTokensExpireIn(now()->addMinutes(config('oauth.refresh_token_ttl')));
        Passport::clientCredentialsTokensExpireIn(now()->addMinutes(config('oauth.client_token_ttl')));
    }
}
