<?php

namespace App\Http\Requests\Auth;

use App\Rules\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'rut' => ['nullable', 'string', new Rut, 'unique:users,rut'],
        ];
    }

    /**
     * Store the RUT in one format ("12345678-K") so that uniqueness compares
     * like with like: dots and spaces removed, uppercase, hyphen before the
     * check digit.
     */
    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('rut'))) {
            return;
        }

        $rut = strtoupper(str_replace(['.', ' '], '', $this->input('rut')));

        if (! str_contains($rut, '-') && strlen($rut) > 1) {
            $rut = substr($rut, 0, -1).'-'.substr($rut, -1);
        }

        $this->merge(['rut' => $rut]);
    }
}
