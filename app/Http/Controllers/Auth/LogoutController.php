<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OAuthTokenService;
use App\Services\OpenIdConnect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Passport\Passport;

/**
 * Central logout: ends the IAM session and revokes every token of the person, so that
 * all the satellites lose access.
 */
class LogoutController extends Controller
{
    public function __construct(protected OAuthTokenService $tokens, protected OpenIdConnect $oidc) {}

    /**
     * Logout started by a satellite: go back to "return" when it belongs to the client.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $this->closeSession($request);

        $return = $this->returnUrlOf($request);

        return $return
            ? redirect()->away($return)
            : view('auth.message', ['title' => 'Sesión cerrada', 'message' => 'Cerraste tu sesión en todos los sistemas.']);
    }

    /**
     * Logout from IAM's own page.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->closeSession($request);

        return redirect()->route('login');
    }

    protected function closeSession(Request $request): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            $this->tokens->revokeAllFor($user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * The "return" URL, only when its origin is the one of a redirect URI of the client.
     *
     * OpenID Connect's logout names it "post_logout_redirect_uri" and may identify the client
     * with the id_token it received ("id_token_hint"), which must carry IAM's signature; its
     * "state" goes back with the redirection.
     */
    protected function returnUrlOf(Request $request): ?string
    {
        $isOpenIdLogout = $request->filled('post_logout_redirect_uri');
        $return = (string) ($isOpenIdLogout ? $request->query('post_logout_redirect_uri') : $request->query('return'));
        $clientId = $request->filled('id_token_hint')
            ? (string) $this->oidc->clientOfIdTokenHint((string) $request->query('id_token_hint'))
            : (string) $request->query('client_id');
        $client = Str::isUuid($clientId) ? Passport::client()->newQuery()->find($clientId) : null;

        if (! $client || ! $this->originOf($return)) {
            return null;
        }

        if (! collect($client->redirect_uris)->contains(fn (string $uri) => $this->originOf($uri) === $this->originOf($return))) {
            return null;
        }

        if ($isOpenIdLogout && $request->filled('state')) {
            $return .= (str_contains($return, '?') ? '&' : '?').http_build_query(['state' => (string) $request->query('state')]);
        }

        return $return;
    }

    protected function originOf(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        return $scheme.'://'.strtolower($parts['host']).':'.($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    }
}
