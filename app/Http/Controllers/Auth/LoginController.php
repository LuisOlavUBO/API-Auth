<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthenticationEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The login form of IAM: the only place where a person types their password.
 */
class LoginController extends Controller
{
    /**
     * Show the form, or the person's session when they already have one.
     */
    public function show(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return view('auth.login');
        }

        if ($request->session()->has('url.intended')) {
            return redirect()->intended();
        }

        return view('auth.session', ['email' => Auth::user()->email]);
    }

    /**
     * Start the session and go back to what the person was trying to open.
     */
    public function store(Request $request, AuthenticationEvents $events): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Ingresa tu correo.',
            'email.email' => 'Ingresa un correo válido.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        // A disabled user sees the same message as a wrong password: it does not reveal the account.
        $valid = Auth::validate($credentials);
        $user = Auth::getLastAttempted();

        if (! $valid || $user->isDisabled()) {
            $events->loginFailed('clave', $valid ? 'baja' : 'credenciales', $user, $credentials['email']);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Correo o contraseña incorrectos.']);
        }

        if ($user->temporaryPasswordExpired()) {
            $events->loginFailed('clave', 'clave_vencida', $user, $credentials['email']);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Tu contraseña temporal venció, contacta a soporte.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $events->loginSucceeded($user, 'clave');

        // The pending destination stays in the session: the password page goes on to it.
        if ($user->hasTemporaryPassword()) {
            return redirect()->route('password');
        }

        return redirect()->intended(route('login'));
    }
}
