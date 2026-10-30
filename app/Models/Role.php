<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Passport\Passport;

/**
 * A set of scopes, which may belong to any application, owned by one application:
 * the one where its users and clients use it.
 */
#[Fillable(['application_id', 'name', 'description'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * Application that defines this role.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Scopes that users and clients with this role may request in their tokens.
     */
    public function oauthScopes(): BelongsToMany
    {
        return $this->belongsToMany(OAuthScope::class, 'oauth_scope_role', 'role_id', 'oauth_scope_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id');
    }

    /**
     * OAuth clients that hold this role.
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Passport::clientModel(), 'oauth_client_role', 'role_id', 'client_id');
    }
}
