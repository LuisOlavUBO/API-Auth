<?php

namespace Database\Seeders;

use App\Models\Api;
use App\Models\OAuthScope;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Base scope catalog and roles. Safe to run more than once: it only adds
 * what is missing and never removes scopes granted afterwards.
 */
class RoleScopeSeeder extends Seeder
{
    /**
     * Scopes owned by this application ("api-auth").
     *
     * @var array<string, string>
     */
    protected array $scopes = [
        'user:read' => 'Read the authenticated user profile',
        'user:write' => 'Update the authenticated user profile',
        'introspect' => 'Validate access tokens issued by this service',
    ];

    /**
     * @var array<string, array{description: string, scopes: string[]}>
     */
    protected array $roles = [
        'user' => [
            'description' => 'Default role for registered users',
            'scopes' => ['user:read', 'user:write'],
        ],
        'admin' => [
            'description' => 'Administrators',
            'scopes' => ['user:read', 'user:write'],
        ],
        'introspector' => [
            'description' => 'Services that validate user tokens through /introspect',
            'scopes' => ['introspect'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $api = Api::firstOrCreate(['name' => 'api-auth'], ['description' => 'Authentication service (this application)']);

        foreach ($this->scopes as $name => $description) {
            OAuthScope::firstOrCreate(['name' => $name], ['api_id' => $api->id, 'description' => $description]);
        }

        foreach ($this->roles as $name => $definition) {
            $role = Role::firstOrCreate(['name' => $name], ['description' => $definition['description']]);

            $role->oauthScopes()->syncWithoutDetaching(
                OAuthScope::whereIn('name', $definition['scopes'])->pluck('id')
            );
        }
    }
}
