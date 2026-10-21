<?php

namespace App\Models;

use Database\Factories\ApiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A resource server (API or backend) that defines and enforces scopes.
 */
#[Fillable(['name', 'description'])]
class Api extends Model
{
    /** @use HasFactory<ApiFactory> */
    use HasFactory;

    /**
     * Scopes this API requires on its endpoints.
     */
    public function oauthScopes(): HasMany
    {
        return $this->hasMany(OAuthScope::class);
    }
}
