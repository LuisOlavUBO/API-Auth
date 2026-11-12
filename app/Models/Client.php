<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\Scope;

/**
 * An OAuth client of an application. Every client of IAM belongs to one of our own
 * applications, so none of them asks the user to approve the authorization.
 */
class Client extends PassportClient
{
    /**
     * Always in lowercase: SQL Server returns uniqueidentifier in uppercase, and Passport puts getKey() in
     * the token's "aud" and "sub" and in the introspection (change client-id-en-minusculas, D1).
     */
    protected function id(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value === null ? null : strtolower($value));
    }

    /**
     * The application this client belongs to.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @param  Scope[]  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return true;
    }
}
