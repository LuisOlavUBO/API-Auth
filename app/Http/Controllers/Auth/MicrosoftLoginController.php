<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * The login with Microsoft Entra ID: another way to start the same IAM session as the
 * password form. Only the UBO tenant (config services.azure.tenant).
 */
class MicrosoftLoginController extends Controller
{
    /**
     * Whether the login with Microsoft is configured.
     */
    public static function isEnabled(): bool
    {
        return filled(config('services.azure.client_id'));
    }

    /**
     * Send the person to Microsoft's sign-in page.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless(self::isEnabled(), 404);

        return Socialite::driver('azure')->redirect();
    }

    /**
     * Start the session with the person's user and go back to what they were trying to open.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::isEnabled(), 404);

        try {
            $account = Socialite::driver('azure')->user();
        } catch (Exception) {
            return $this->failed('No pudimos iniciar sesión con Microsoft, intenta de nuevo.');
        }

        // "mail" is the address people use; this provider's getEmail() is the userPrincipalName.
        $email = ($account->user['mail'] ?? null) ?: $account->getEmail();

        if (blank($account->getId()) || blank($email)) {
            return $this->failed('No pudimos iniciar sesión con Microsoft, intenta de nuevo.');
        }

        $user = User::fromMicrosoft($account->getId(), $email, $account->getName() ?: $email);

        if ($user === null) {
            return $this->failed('No pudimos asociar tu cuenta de Microsoft, contacta a soporte.');
        }

        if ($user->isDisabled()) {
            return $this->failed('No tienes acceso, contacta a soporte.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('login'));
    }

    /**
     * Back to the login form with the reason, without a session.
     */
    protected function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['microsoft' => $message]);
    }
}
