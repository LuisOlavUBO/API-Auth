<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Ingresa tu correo.',
            'email.email' => 'Ingresa un correo válido.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Correo o contraseña incorrectos.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('login'));
    }
}
