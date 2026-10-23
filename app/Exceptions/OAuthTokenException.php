<?php

namespace App\Exceptions;

use League\OAuth2\Server\Exception\OAuthServerException;
use RuntimeException;

/**
 * A login or refresh rejected by the OAuth2 server. ProblemRenderer answers it
 * as problem+json with the OAuth error code in "error".
 */
class OAuthTokenException extends RuntimeException
{
    public function __construct(
        public readonly string $errorType,
        string $message,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function fromServerException(OAuthServerException $e): static
    {
        // Bad credentials / expired refresh tokens come back as invalid_grant (400);
        // expose them as 401 so clients can treat them as authentication failures.
        $status = in_array($e->getErrorType(), ['invalid_grant', 'invalid_credentials'])
            ? 401
            : $e->getHttpStatusCode();

        // Client misconfiguration is a server problem, not the caller's.
        if ($e->getErrorType() === 'invalid_client') {
            $status = 500;
        }

        return new static($e->getErrorType(), $e->getHint() ?: $e->getMessage(), $status);
    }
}
