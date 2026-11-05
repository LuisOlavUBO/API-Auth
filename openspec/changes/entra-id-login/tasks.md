# Tasks

Los tests se corren contra el SQL Server de Docker (`DB_HOST=127.0.0.1 DB_PORT=14334 DB_DATABASE=apiauth_test DB_USERNAME=sa`, contraseña en `DOCKER_MSSQL_SA_PASSWORD`). Microsoft se simula con `Socialite::shouldReceive`.

## 1. Base

- [x] 1.1 Instalar `laravel/socialite` y `socialiteproviders/microsoft-azure`, registrar el provider en `AppServiceProvider`, agregar `azure` a `config/services.php` y las variables `ENTRA_*` a `.env.example` (en producción van en el `.env.prod`, que `docker-compose.prod.yml` ya carga); verificar con `composer show` y con que la suite existente sigue pasando.
- [x] 1.2 Migración: `users.entra_oid` (uuid nullable, índice único filtrado) y `users.password` nullable; `entra_oid` en `fillable` y `#[Hidden]`; verificar con `migrate:fresh` contra la base de tests y un test de que dos usuarios sin `entra_oid` conviven y dos con el mismo no.

## 2. Usuarios de Microsoft

- [x] 2.1 Agregar `User::fromMicrosoft($oid, $email, $name)`: por `oid`, vinculación por email si no tiene `oid`, `null` si el email tiene otro `oid`, y alta sin contraseña ni roles; verificar con tests de los cuatro casos.
- [x] 2.2 Verificar con tests que un usuario sin contraseña recibe 401 `invalid_grant` en `/v1/login` y no inicia sesión en el login web; cambiar código solo si fallan.

## 3. Login con Microsoft

- [x] 3.1 Crear `MicrosoftLoginController` (`redirect` y `callback`), las rutas `login/microsoft` y `login/microsoft/callback`, y agregar `api/auth/login/*` a `ApiPrefix::WEB_PAGES`; verificar con tests de 404 sin configuración, redirección a Microsoft, callback correcto que vuelve al destino original, error o `state` inválido que vuelve al login sin sesión, y email vinculado a otro `oid` con el mensaje de soporte.
- [x] 3.2 Mostrar el botón "Ingresar con Microsoft" en `auth/login.blade.php` solo con Entra configurado; verificar con tests de la página con y sin configuración.
- [x] 3.3 Cubrir el flujo completo con un test: invitado en `oauth/authorize` → login con Microsoft → código con `state` (persona con roles) y 403 "No tienes acceso" (persona nueva).

## 4. Documentación

- [x] 4.1 README: registro de la aplicación en Entra ID (tenant único, redirect URI `https://<host>/api/auth/login/microsoft/callback`, secreto, permiso `User.Read`), variables `ENTRA_*`, reglas de vinculación y la limitación de la sesión de Microsoft; `docs/openapi/`: las rutas `login/microsoft` y su callback como páginas web; verificar revisando que las rutas coinciden con `php artisan route:list --path=api/auth/login`.
- [x] 4.2 Correr `vendor/bin/pint --dirty --format agent` y la suite completa; verificar que todo pasa.

## Workflow follow-up

- Registrar la aplicación en Entra ID, cargar las variables en el servidor y probar el login real.
- `/opsx:archive` al terminar.
