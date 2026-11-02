<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- No se usa Laravel Cloud. Se despliega en un servidor on-premise con Docker.
- Flujo:
  1. En el PC, construir la imagen de producción (`Dockerfile.prod`) con su versión: `IMAGE=ghcr.io/luisolavubo/api-auth:<versión> docker compose -f docker-compose.prod.yml build app`.
  2. Publicarla en GitHub Packages: `docker push ghcr.io/luisolavubo/api-auth:<versión>`.
  3. En el servidor, con `IMAGE` en su `.env`: `docker compose -f docker-compose.prod.yml pull` y `docker compose -f docker-compose.prod.yml up -d --no-build`.
- El detalle (archivos del servidor, llaves de Passport, proxy inverso) está en la sección "Despliegue" del README.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

# API-Auth (contexto del proyecto)

## Qué es
- Servicio de autenticación con Laravel Passport (tokens JWT) y el paquete `ubo/common` (Problems, Health, SqlServer).
- Objetivo a futuro: servicio de autenticación central para los satélites (SSO con Entra ID, login con PKCE, roles por sistema). Aún no implementado.

## Principio KISS (Keep It Simple, Stupid)
- Resolver lo que se pide hoy con la solución más simple que funcione. Nada de capas, abstracciones, interfaces, patrones o configuraciones "por si acaso".
- No anticipar requisitos futuros: si una fase posterior lo necesita, se diseña en esa fase.
- Preferir las herramientas que ya traen Laravel, Passport y `ubo/common` antes que código propio o dependencias nuevas.
- Un cambio toca solo lo necesario: sin refactors, renombres ni "mejoras" que nadie pidió.
- Si hay dos opciones, elegir la más fácil de leer y de borrar. Ante la duda, preguntar antes de agregar complejidad.
- Aplica también a los artefactos de OpenSpec: propuestas, diseños y tareas cortos y acotados al cambio.

## Metodología de desarrollo con OpenSpec
- El comportamiento vigente está especificado en `openspec/specs/` (una carpeta por capacidad). Es la fuente de verdad: leer la spec afectada antes de proponer o implementar.
- Todo cambio de comportamiento pasa por un cambio de OpenSpec en `openspec/changes/<nombre>/`. Los arreglos menores que no cambian comportamiento (copy, estilo, formato) no lo necesitan.
- Flujo:
  1. `/opsx:explore`: pensar el problema y aclarar requisitos, sin escribir código.
  2. `/opsx:propose`: crear el cambio con `proposal.md`, specs delta y, si hace falta, `design.md` y `tasks.md`. Indicar qué queda fuera de alcance.
  3. Esperar la aprobación del usuario antes de implementar.
  4. `/opsx:apply`: implementar las tareas en orden, con sus tests, marcándolas al terminar.
  5. `/opsx:update`: si las decisiones cambian a mitad de camino, actualizar los artefactos antes de seguir con el código.
  6. `/opsx:archive`: al terminar y verificar, archivar el cambio para que sus specs delta pasen a `openspec/specs/`.
- Los artefactos se escriben en español, con requisitos en formato `SHALL`/`MUST` y escenarios `#### Scenario:`, igual que las specs existentes.
- El código, los tests y la spec deben coincidir: si la implementación se aparta de la spec, se corrige una de las dos antes de archivar.

## Rutas
- Todas las rutas se publican bajo `/api/auth` (`App\Http\ApiPrefix`) y se versionan con `v1`.
- `/api/auth/oauth/token` es la única ruta de Passport en uso; no se versiona y mantiene el formato de error de OAuth2.
- Errores en formato Problem Details (`ProblemRenderer` de `ubo/common`).
- Endpoints entre servicios (client credentials): `introspect` (scope `introspect`) y `public-key`.

## Base de datos
- SQL Server.
- Tests: SQL Server en Docker (`DB_HOST=127.0.0.1`, `DB_PORT=14334`, base `apiauth_test`, usuario `sa`, contraseña en `DOCKER_MSSQL_SA_PASSWORD` del `.env`). Sobrescribir las variables `DB_*` en cada comando de test o migración.
- El `.env` apunta al SQL Server remoto de desarrollo (10.16.2.138). No ejecutar `migrate` contra él salvo que se pida explícitamente.

## Documentación
- OpenAPI en `docs/openapi/` y colección de Bruno en `docs/bruno/`. Actualizarlas cuando cambie un endpoint.

## Dependencias nuevas
- Requieren aprobación previa (p. ej. Socialite para Entra ID).
