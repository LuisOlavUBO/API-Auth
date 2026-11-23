<?php

namespace App\Services;

/**
 * The applications and roles of the access token being issued.
 *
 * league/oauth2-server validates the scopes (ScopeRepository) and then creates
 * the token (AccessTokenRepository) without passing anything between them, so
 * ScopeRepository leaves here the applications to name in "aud" and the roles to
 * name in "roles", and AccessTokenRepository takes them when it creates the
 * token. Every issuance goes through ScopeRepository first, which always sets
 * them, and taking them clears them, so they never leak into the next token.
 */
class TokenAudience
{
    /** @var string[] */
    protected array $applications = [];

    /** @var string[] */
    protected array $roles = [];

    /**
     * The nonce of the authorization whose code is being exchanged, for the id_token
     * (IdTokenResponse). ScopeRepository sets it on every issuance, null when there is none.
     */
    protected ?string $nonce = null;

    /**
     * Set the applications and roles of the next token (none: a token for no particular application).
     *
     * @param  string[]  $applications
     * @param  string[]  $roles
     */
    public function issueFor(array $applications = [], array $roles = []): void
    {
        $this->applications = $applications;
        $this->roles = $roles;
    }

    /**
     * Take the applications and roles of the token being created and clear them.
     *
     * @return array{0: string[], 1: string[]}
     */
    public function pull(): array
    {
        $taken = [$this->applications, $this->roles];
        $this->applications = [];
        $this->roles = [];

        return $taken;
    }

    /**
     * Set the nonce of the token being issued (null: none).
     */
    public function useNonce(?string $nonce): void
    {
        $this->nonce = $nonce;
    }

    /**
     * Take the nonce of the token being issued and clear it.
     */
    public function pullNonce(): ?string
    {
        $nonce = $this->nonce;
        $this->nonce = null;

        return $nonce;
    }
}
