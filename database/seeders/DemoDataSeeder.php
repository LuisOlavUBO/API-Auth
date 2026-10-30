<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\OAuthScope;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Laravel\Passport\Passport;

/**
 * Test data for development: a few applications with scopes and roles, users with roles
 * in different applications and the clients to try the flows. Never run it in production.
 *
 * Safe to run more than once. The secrets of the clients it creates are written to
 * storage/app/demo-clients.txt (ignored by git) because they are only known at creation.
 * Every demo user has the password "password".
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Scopes by application.
     *
     * @var array<string, array<string, string>>
     */
    protected array $scopes = [
        'api-maestros' => [
            'geografia:leer' => 'Leer geografía',
            'sistemas:leer' => 'Leer sistemas',
            'homologacion:leer' => 'Leer homologaciones',
        ],
        'api-matricula' => [
            'matricula:leer' => 'Leer matrículas',
            'matricula:escribir' => 'Matricular alumnos',
        ],
        'portal' => [
            'portal:inicio.ver' => 'Ver el inicio del portal',
            'portal:reportes.ver' => 'Ver los reportes del portal',
        ],
    ];

    /**
     * Roles as "application/role": description and the scopes they grant, of any application.
     *
     * @var array<string, array{description: string, scopes: string[]}>
     */
    protected array $roles = [
        'api-maestros/lector' => [
            'description' => 'Lectura de API-Maestros',
            'scopes' => ['geografia:leer', 'sistemas:leer', 'homologacion:leer'],
        ],
        'api-matricula/lector' => [
            'description' => 'Lectura de API-Matrícula',
            'scopes' => ['matricula:leer'],
        ],
        'portal/estudiante' => [
            'description' => 'Estudiante en el portal',
            'scopes' => ['portal:inicio.ver', 'matricula:leer', 'geografia:leer'],
        ],
        'portal/coordinador' => [
            'description' => 'Coordinador en el portal',
            'scopes' => ['portal:inicio.ver', 'portal:reportes.ver', 'matricula:leer', 'matricula:escribir', 'geografia:leer'],
        ],
    ];

    /**
     * @var array<string, array{name: string, roles: string[]}>
     */
    protected array $users = [
        'ana@example.com' => ['name' => 'Ana Coordinadora', 'roles' => ['api-auth/user', 'portal/coordinador']],
        'beto@example.com' => ['name' => 'Beto Estudiante', 'roles' => ['api-auth/user', 'portal/estudiante']],
        'admin@example.com' => ['name' => 'Admin IAM', 'roles' => ['api-auth/admin']],
        'sinrol@example.com' => ['name' => 'Sin Roles en el Portal', 'roles' => ['api-auth/user']],
    ];

    /**
     * @var array<string, array{application: string, type: 'client-credentials'|'password', roles: string[]}>
     */
    protected array $clients = [
        'IAM Login (demo)' => ['application' => 'api-auth', 'type' => 'password', 'roles' => []],
        'portal-web' => ['application' => 'portal', 'type' => 'password', 'roles' => []],
        'portal-backend' => ['application' => 'portal', 'type' => 'client-credentials', 'roles' => ['api-maestros/lector', 'api-matricula/lector']],
        'maestros-introspector' => ['application' => 'api-maestros', 'type' => 'client-credentials', 'roles' => ['api-auth/introspector']],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RoleScopeSeeder::class);

        foreach (['api-maestros' => 'Datos maestros', 'api-matricula' => 'Matrícula de alumnos', 'portal' => 'Portal académico (satélite)'] as $name => $description) {
            Application::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        foreach ($this->scopes as $application => $scopes) {
            $applicationId = Application::firstWhere('name', $application)->id;

            foreach ($scopes as $name => $description) {
                OAuthScope::firstOrCreate(['name' => $name], ['application_id' => $applicationId, 'description' => $description]);
            }
        }

        foreach ($this->roles as $reference => $definition) {
            $role = $this->role($reference, $definition['description']);
            $role->oauthScopes()->syncWithoutDetaching(OAuthScope::whereIn('name', $definition['scopes'])->pluck('id'));
        }

        foreach ($this->users as $email => $definition) {
            $user = User::updateOrCreate(['email' => $email], ['name' => $definition['name'], 'password' => 'password']);
            $user->roles()->syncWithoutDetaching(collect($definition['roles'])->map(fn (string $reference) => $this->role($reference)->id));
        }

        $this->createClients();
    }

    /**
     * The role of a "application/role" reference, created when a description is given.
     */
    protected function role(string $reference, ?string $description = null): Role
    {
        [$application, $name] = explode('/', $reference, 2);
        $applicationId = Application::firstWhere('name', $application)->id;

        return $description === null
            ? Role::where(['application_id' => $applicationId, 'name' => $name])->firstOrFail()
            : Role::firstOrCreate(['application_id' => $applicationId, 'name' => $name], ['description' => $description]);
    }

    /**
     * Create the clients that do not exist yet and write their credentials to a local file.
     */
    protected function createClients(): void
    {
        $lines = [];

        foreach ($this->clients as $name => $definition) {
            if (Passport::client()->newQuery()->where('name', $name)->exists()) {
                continue;
            }

            $client = Application::firstWhere('name', $definition['application'])->createClient($name, $definition['type']);

            foreach ($definition['roles'] as $reference) {
                $this->role($reference)->clients()->syncWithoutDetaching([$client->getKey()]);
            }

            $lines[] = "{$name} ({$definition['application']}, {$definition['type']}): client_id={$client->getKey()} client_secret={$client->plainSecret}";
        }

        if ($lines !== []) {
            file_put_contents(storage_path('app/demo-clients.txt'), implode("\n", $lines)."\n", FILE_APPEND);
            $this->command?->info(count($lines).' client(s) created; credentials in storage/app/demo-clients.txt');
        }
    }
}
