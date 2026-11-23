<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

/**
 * Passport's repository, also keeping the "nonce" of the authorization for the id_token.
 *
 * oauth/authorize issues the code in the same request that brings the nonce (there is no
 * consent page), so it is read from the current request and cached under the code's id
 * until the code expires. ScopeRepository takes it when the code is exchanged.
 */
class AuthCodeRepository extends PassportAuthCodeRepository
{
    public const NONCE_KEY = 'oidc-nonce:';

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        parent::persistNewAuthCode($authCodeEntity);

        $nonce = request()->query('nonce');

        if (is_string($nonce) && $nonce !== '') {
            Cache::put(self::NONCE_KEY.$authCodeEntity->getIdentifier(), $nonce, $authCodeEntity->getExpiryDateTime());
        }
    }
}
