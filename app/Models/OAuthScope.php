<?php

namespace App\Models;

use Database\Factories\OAuthScopeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A permission that access tokens may carry (e.g. "geografia:leer"),
 * owned by the API that enforces it.
 */
#[Fillable(['api_id', 'name', 'description'])]
class OAuthScope extends Model
{
    /** @use HasFactory<OAuthScopeFactory> */
    use HasFactory;

    protected $table = 'oauth_scopes';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // pdo_sqlsrv returns integer columns as strings.
            'api_id' => 'integer',
        ];
    }

    /**
     * API that requires this scope on its endpoints.
     */
    public function api(): BelongsTo
    {
        return $this->belongsTo(Api::class);
    }

    /**
     * Roles allowed to request this scope.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'oauth_scope_role', 'oauth_scope_id', 'role_id');
    }

    /**
     * Scopes granted to an OAuth client through its roles.
     *
     * @return Builder<static>
     */
    public static function grantedToClient(string $clientId): Builder
    {
        return static::whereHas('roles.clients', fn (Builder $clients) => $clients->whereKey($clientId))
            ->orderBy('name');
    }

    /**
     * Names of the scopes granted to an OAuth client through its roles.
     *
     * @return string[]
     */
    public static function namesForClient(string $clientId): array
    {
        return static::grantedToClient($clientId)->pluck('name')->all();
    }

    /**
     * Names of the scopes granted to an OAuth client through its roles that belong to one API.
     *
     * @return string[]
     */
    public static function namesForClientInApi(string $clientId, string $apiName): array
    {
        return static::grantedToClient($clientId)
            ->whereHas('api', fn (Builder $api) => $api->where('name', $apiName))
            ->pluck('name')
            ->all();
    }
}
