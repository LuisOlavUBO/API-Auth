<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Passport\Passport;

#[Fillable(['name', 'description'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * Scopes that users and clients with this role may request in their tokens.
     */
    public function oauthScopes(): BelongsToMany
    {
        return $this->belongsToMany(OAuthScope::class, 'oauth_scope_role', 'role_id', 'oauth_scope_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * OAuth clients (other systems) that hold this role.
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Passport::clientModel(), 'oauth_client_role', 'role_id', 'client_id');
    }
}
