<?php

namespace App\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

/**
 * A satellite, API or monolith. It owns permissions (scopes) that it enforces,
 * roles that group permissions of any application, and OAuth clients.
 */
#[Fillable(['name', 'description'])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    /**
     * Scopes this application requires on its endpoints or functions.
     */
    public function oauthScopes(): HasMany
    {
        return $this->hasMany(OAuthScope::class);
    }

    /**
     * Roles defined by this application, assigned to its users and to clients.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Create an OAuth client of this application.
     *
     * Passport's ClientRepository cannot be used: it inserts without application_id,
     * which is required. The attributes are the same ones it sets.
     *
     * @param  'client-credentials'|'password'  $type
     */
    public function createClient(string $name, string $type = 'client-credentials'): Client
    {
        $grantTypes = $type === 'password' ? ['password', 'refresh_token'] : ['client_credentials'];

        return Passport::client()->newQuery()->forceCreate([
            'application_id' => $this->id,
            'name' => $name,
            'secret' => Str::random(40),
            'provider' => $type === 'password' ? 'users' : null,
            'redirect_uris' => [],
            'grant_types' => $grantTypes,
            'revoked' => false,
        ]);
    }
}
