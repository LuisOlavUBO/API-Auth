<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesOAuthClient;
use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('oauth:client-scopes {client : Client id or name}')]
#[Description('Show the application and roles of an OAuth client and the scopes it may request')]
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
            ->with('application')
            ->orderBy('name')
            ->get();

        $this->line("Client: {$client->name} ({$client->getKey()})");
        $this->line('Application: '.Application::find($client->application_id)?->name);
        $this->line('Roles: '.($roles->map(fn (Role $role) => $role->application->name.'/'.$role->name)->implode(', ') ?: '(none)'));

        $scopesByApplication = OAuthScope::grantedToClient($client->getKey())->with('application')->get()->groupBy('application.name')->sortKeys();

        $this->table(['Application', 'Scopes'], $scopesByApplication->map(fn ($scopes, string $application) => [
            $application,
            $scopes->pluck('name')->implode(', '),
        ])->values());

        $this->line('May request: '.(implode(' ', OAuthScope::namesForClient($client->getKey())) ?: '(nothing)'));

        return self::SUCCESS;
    }
}
