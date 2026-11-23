<?php

namespace App\Services;

use App\Http\ApiPrefix;
use App\Models\User;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Throwable;

/**
 * The minimal OpenID Connect layer over the central login: the issuer, the signing key as
 * a JWK, the claims about the user shared by the id_token and userinfo, and the check of
 * the id_token_hint of the logout.
 */
class OpenIdConnect
{
    /**
     * Scopes of OpenID Connect: they exist outside the catalog and are not permissions.
     */
    public const SCOPES = ['openid', 'profile', 'email'];

    /**
     * The issuer: the public URL of /api/auth. APP_URL must be the public URL.
     */
    public function issuer(): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ApiPrefix::BASE;
    }

    /**
     * The public key that signs the tokens, in PEM.
     */
    public function publicKey(): string
    {
        return config('passport.public_key') ?: file_get_contents(Passport::keyPath('oauth-public.key'));
    }

    /**
     * The public key as a JWK, with its "kid".
     *
     * @return array{kty: string, use: string, alg: string, kid: string, n: string, e: string}
     */
    public function jwk(): array
    {
        $rsa = openssl_pkey_get_details(openssl_pkey_get_public($this->publicKey()))['rsa'];
        $components = ['e' => $this->base64Url($rsa['e']), 'kty' => 'RSA', 'n' => $this->base64Url($rsa['n'])];

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->base64Url(hash('sha256', json_encode($components), true)),
            'n' => $components['n'],
            'e' => $components['e'],
        ];
    }

    /**
     * The "kid" of the signing key: its RFC 7638 thumbprint, so it changes when the keys rotate.
     */
    public function keyId(): string
    {
        return $this->jwk()['kid'];
    }

    /**
     * Claims about the user for the given scopes, shared by the id_token and userinfo.
     * "id_persona" goes as text, as Ucampus receives it from API-UCampus. Never the RUT.
     *
     * @param  string[]  $scopes
     * @return array<string, mixed>
     */
    public function userClaims(User $user, array $scopes): array
    {
        $claims = ['sub' => $user->uuid];

        if ($user->id_persona !== null) {
            $claims['id_persona'] = (string) $user->id_persona;
        }

        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->name;
        }

        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        return $claims;
    }

    /**
     * The client an id_token_hint was issued for, when IAM signed it (even if it expired).
     */
    public function clientOfIdTokenHint(string $idToken): ?string
    {
        try {
            $token = (new Parser(new JoseEncoder))->parse($idToken);
        } catch (Throwable) {
            return null;
        }

        $signedByIam = new SignedWith(new Sha256, InMemory::plainText($this->publicKey()));

        if (! $token instanceof UnencryptedToken || ! (new Validator)->validate($token, $signedByIam)) {
            return null;
        }

        $audiences = $token->claims()->get(RegisteredClaims::AUDIENCE, []);

        return is_string($audiences[0] ?? null) ? $audiences[0] : null;
    }

    protected function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
