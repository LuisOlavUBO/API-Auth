<?php

namespace App\Services;

use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records the authentication events of the current request (change eventos-de-autenticacion).
 * Recording never makes the caller fail: an error goes to the log and the login goes on.
 */
class AuthenticationEvents
{
    /**
     * A person logged in, with "clave" or "microsoft".
     */
    public function loginSucceeded(User $user, string $method): void
    {
        $this->record('login_ok', $method, ['user_id' => $user->getKey()]);
    }

    /**
     * A login was rejected. The email is only given for the password login (D5).
     */
    public function loginFailed(string $method, string $reason, ?User $user = null, ?string $email = null): void
    {
        $this->record('login_fallido', $method, [
            'reason' => $reason,
            'user_id' => $user?->getKey(),
            'email' => $email === null ? null : Str::lower($email),
        ]);
    }

    /**
     * The rate limiter of the password login rejected an attempt.
     */
    public function loginBlocked(?string $email): void
    {
        $this->record('login_bloqueado', 'clave', ['email' => $email === null ? null : Str::lower($email)]);
    }

    /**
     * oauth/token rejected a client with invalid_client. Never with the secret it sent.
     */
    public function clientRejected(string $clientId, string $reason): void
    {
        $this->record('cliente_rechazado', 'client_credentials', ['client_id' => $clientId, 'reason' => $reason]);
    }

    /**
     * @param  array{user_id?: int|string|null, email?: string|null, client_id?: string|null, reason?: string|null}  $attributes
     */
    protected function record(string $type, string $method, array $attributes): void
    {
        try {
            $request = request();
            $clientId = $attributes['client_id'] ?? $this->authorizingClientId();

            AuthenticationEvent::create([
                ...$attributes,
                'occurred_at' => now(),
                'type' => $type,
                'method' => $method,
                'email' => isset($attributes['email']) ? Str::limit($attributes['email'], 255, '') : null,
                'client_id' => $clientId === null ? null : Str::limit(Str::lower($clientId), 100, ''),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 512, '') ?: null,
            ]);
        } catch (Throwable $exception) {
            Log::error('No se pudo registrar el evento de autenticación.', ['type' => $type, 'exception' => $exception]);
        }
    }

    /**
     * The client_id of the authorization request the person is logging in for, if any.
     */
    protected function authorizingClientId(): ?string
    {
        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        parse_str((string) parse_url((string) $request->session()->get('url.intended'), PHP_URL_QUERY), $query);

        return is_string($query['client_id'] ?? null) ? $query['client_id'] : null;
    }
}
