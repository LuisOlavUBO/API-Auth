<?php

namespace App\Services;

/**
 * The API that the access token being issued is for.
 *
 * league/oauth2-server validates the scopes (ScopeRepository) and then creates
 * the token (AccessTokenRepository) without passing anything between them, so
 * ScopeRepository leaves the validated API here and AccessTokenRepository takes
 * it when it creates the token. Every issuance goes through ScopeRepository
 * first, which always sets it, and taking it clears it, so it never leaks into
 * the next token.
 */
class TokenAudience
{
    protected ?string $api = null;

    /**
     * Set the API of the next token (null: a token for no particular API).
     */
    public function issueFor(?string $api): void
    {
        $this->api = $api;
    }

    /**
     * Take the API of the token being created and clear it.
     */
    public function pull(): ?string
    {
        [$api, $this->api] = [$this->api, null];

        return $api;
    }
}
