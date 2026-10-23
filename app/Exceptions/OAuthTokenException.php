<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Log;
use League\OAuth2\Server\Exception\OAuthServerException;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * A login or refresh rejected by the OAuth2 server. UBO.Common's ProblemRenderer
 * answers it as problem+json with the OAuth error code in "error" and the
 * exception message as "detail".
 */
class OAuthTokenException extends DomainProblem
{
    /**
     * @param  string|null  $reason  why the server rejected it, when it must go to the log instead of "detail"
     */
    public function __construct(
        public readonly string $errorType,
        string $message,
        protected readonly int $httpStatus,
        protected readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }

    public static function fromServerException(OAuthServerException $e): static
    {
        $reason = $e->getHint() ?: $e->getMessage();

        // Client misconfiguration is a server problem, not the caller's: the
        // caller gets a generic detail and the reason goes to the log.
        if ($e->getErrorType() === 'invalid_client') {
            return new static('invalid_client', 'El servicio de autenticación no está bien configurado.', 500, $reason);
        }

        // Bad credentials / expired refresh tokens come back as invalid_grant (400);
        // expose them as 401 so clients can treat them as authentication failures.
        $status = in_array($e->getErrorType(), ['invalid_grant', 'invalid_credentials'])
            ? 401
            : $e->getHttpStatusCode();

        return new static($e->getErrorType(), $reason, $status);
    }

    /**
     * Whether the password grant client itself was rejected.
     */
    public function isServerMisconfiguration(): bool
    {
        return $this->errorType === 'invalid_client';
    }

    public function code(): string
    {
        return $this->errorType;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function title(): string
    {
        if ($this->isServerMisconfiguration()) {
            return 'Error interno';
        }

        return match ($this->errorType) {
            'invalid_grant', 'invalid_credentials' => 'Credenciales inválidas',
            'invalid_scope' => 'Scope inválido',
            default => 'Solicitud OAuth inválida',
        };
    }

    /**
     * Wrong passwords and expired refresh tokens are expected, so they are not
     * logged. Only a rejected password grant client is: the service is broken.
     */
    public function report(): bool
    {
        if ($this->isServerMisconfiguration()) {
            Log::error('The password grant client was rejected: '.$this->reason);
        }

        return true;
    }
}
