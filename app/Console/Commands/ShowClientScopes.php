<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesOAuthClient;
use App\Models\OAuthScope;
use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('oauth:client-scopes {client : Client id or name}')]
#[Description('Show the roles of an OAuth client and the scopes it may request')]
class ShowClientScopes extends Command
{
    use ResolvesOAuthClient;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $client = $this->resolveClient($this->argument('client'));

        if (! $client) {
            return self::FAILURE;
        }

        $roles = Role::whereHas('clients', fn (Builder $clients) => $clients->whereKey($client->getKey()))
            ->orderBy('name')
            ->get();

        $this->line("Client: {$client->name} ({$client->getKey()})");

        $this->line('Roles: '.($roles->pluck('name')->implode(', ') ?: '(none)'));

        $scopesByApi = OAuthScope::grantedToClient($client->getKey())->with('api')->get()->groupBy('api.name')->sortKeys();

        $this->table(['API', 'Scopes'], $scopesByApi->map(fn ($scopes, string $api) => [
            $api,
            $scopes->pluck('name')->implode(', '),
        ])->values());

        $this->line('May request: '.(implode(' ', OAuthScope::namesForClient($client->getKey())) ?: '(nothing)'));

        return self::SUCCESS;
    }
}
