<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The change of a temporary password, required before IAM gives out codes. Changing a
 * password that is not temporary is not offered.
 */
class PasswordController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user()->hasTemporaryPassword()) {
            return redirect()->route('login');
        }

        return view('auth.password');
    }

    /**
     * Save the new password and go on to what the person was trying to open.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTemporaryPassword()) {
            return redirect()->route('login');
        }

        $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
                function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    if (Hash::check((string) $value, $user->password)) {
                        $fail('La nueva contraseña debe ser distinta de la temporal.');
                    }
                },
            ],
        ], [
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user->forceFill([
            'password' => $request->input('password'),
            'temporary_password_expires_at' => null,
        ])->save();

        $request->session()->regenerate();

        return redirect()->intended(route('login'));
    }
}
