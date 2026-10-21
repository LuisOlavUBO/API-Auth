# API-Auth — Microservicio de autenticación (Laravel 13 + Passport 13)

Servicio OAuth2 que emite y valida tokens JWT para el resto de microservicios.

- **Usuarios finales**: registro, login, refresh, perfil y logout (password grant, el secreto del cliente nunca sale del servidor).
- **Otros microservicios**: se autentican con *client credentials* y validan tokens de usuario vía `/introspect`, o localmente con la clave pública.

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=RoleScopeSeeder
php artisan passport:keys
php artisan passport:client --password --name="Auth Service Password Client" --provider=users
```

Copia el `Client ID` y `Client Secret` del último comando a `.env`:

```
PASSPORT_PASSWORD_CLIENT_ID=...
PASSPORT_PASSWORD_CLIENT_SECRET=...
```

El secreto se guarda hasheado en la base de datos y solo se muestra una vez.

### Base de datos

Usa SQL Server 2016 (base **`apiauth`**), dedicada solo a autenticación: usuarios, tokens y clientes OAuth. La API se conecta con el login propio **`apiauth_app`**, que no es sysadmin. Solo tiene acceso a `apiauth`, con los roles `db_datareader`, `db_datawriter` y `db_ddladmin`; este último sirve para correr las migraciones. Su idioma por defecto es `us_english` y su base por defecto es `apiauth`. Completa `DB_HOST`, `DB_USERNAME` y `DB_PASSWORD` en `.env`. Requiere las extensiones `sqlsrv`/`pdo_sqlsrv` y el ODBC Driver 18.

El login del servidor tiene idioma español (formato de fecha `dmy`). Por eso [AppServiceProvider.php](app/Providers/AppServiceProvider.php) ejecuta `SET DATEFORMAT ymd` en cada conexión. Sin ese ajuste, Laravel no puede guardar las fechas.

Los tests usan SQLite en memoria y no tocan el servidor.

En producción (o contenedores) puedes pasar las claves por entorno en vez de archivos: `PASSPORT_PRIVATE_KEY` y `PASSPORT_PUBLIC_KEY`.

```bash
php artisan serve
```

## Endpoints (`/api/v1/auth`)

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| POST | `/register` | — | `name`, `email`, `password`, `password_confirmation` → usuario + tokens |
| POST | `/login` | — | `email`, `password`, `scope?` → `access_token`, `refresh_token`, `expires_in` |
| POST | `/refresh` | — | `refresh_token` → nuevo par (el refresh anterior queda revocado) |
| GET | `/me` | Bearer (usuario, scope `user:read`) | Perfil del usuario del token |
| POST | `/logout` | Bearer (usuario) | Revoca el access token actual y su refresh token |
| POST | `/logout-all` | Bearer (usuario) | Revoca todas las sesiones del usuario |
| POST | `/introspect` | Bearer (cliente, scope `introspect`) | `token` → `{ active, sub, client_id, scope, exp, user }` |
| GET | `/public-key` | — | Clave pública PEM para verificar JWT localmente |

Además Passport expone `POST /oauth/token` (estándar OAuth2), usado por los microservicios para el grant `client_credentials`.

Los endpoints públicos de `register`/`login`/`refresh` tienen rate limit de 10 req/min por email+IP.

### Ejemplo

```bash
curl -X POST http://localhost:8000/api/v1/auth/login -H "Accept: application/json" -d "email=ana@example.com&password=secret-password"
```

```bash
curl http://localhost:8000/api/v1/auth/me -H "Accept: application/json" -H "Authorization: Bearer <access_token>"
```

## Integrar otro microservicio

1. Crear un cliente para el servicio y darle roles (sin roles no puede pedir ningún scope):

   ```bash
   php artisan passport:client --client --name="orders-service"
   ```

   ```bash
   php artisan oauth:client-role orders-service introspector
   ```

2. El servicio obtiene su token:

   ```bash
   curl -X POST http://localhost:8000/oauth/token -d "grant_type=client_credentials&client_id=<id>&client_secret=<secret>&scope=introspect"
   ```

   Para llamar a otra API, lo recomendado es pedir un token **por API** con el parámetro `api` (ver [Tokens por API](#tokens-por-api)):

   ```bash
   curl -X POST http://localhost:8000/oauth/token -d "grant_type=client_credentials&client_id=<id>&client_secret=<secret>&api=api-maestros"
   ```

3. Para validar el token de un usuario que le llega, tiene dos opciones:
   - **Introspección** (detecta revocaciones/logout): `POST /api/v1/auth/introspect` con `token=<token del usuario>` y su propio token de cliente como Bearer.
   - **Verificación local** (sin llamada de red, no detecta revocación hasta que expira): verificar la firma RS256 del JWT con la clave de `/api/v1/auth/public-key` y revisar `exp`.

## Scopes y roles

Los scopes viven en base de datos y se asignan **por rol**:

- `apis`: las APIs o backends que exponen endpoints protegidos (`api-auth`, `api-maestros`, ...). Un sistema que solo consume no va aquí.
- `oauth_scopes`: catálogo de scopes que existen (`user:read`, `geografia:leer`, ...). Cada scope pertenece a una API (`api_id`, obligatorio). El nombre sigue la convención `dominio:permiso` y es único en toda la organización.
- `roles` + `oauth_scope_role`: qué scopes puede pedir cada rol.
- `users.role_id`: rol del usuario (uno). Los usuarios nuevos reciben `OAUTH_DEFAULT_ROLE` (por defecto `user`).
- `oauth_client_role`: roles de cada cliente `client_credentials` (uno o varios, p. ej. uno por API que consume).

Carga inicial (API `api-auth`; roles `user`, `admin` e `introspector`; scopes `user:read`, `user:write`, `introspect`):

```bash
php artisan db:seed --class=RoleScopeSeeder
```

Al hacer login, [app/Services/ScopeRepository.php](app/Services/ScopeRepository.php) filtra los scopes contra el rol del usuario:

- Si no se manda `scope`, se pide el scope por defecto (`user:read`).
- Pedir un scope que el rol no tiene (o `*`, o uno inexistente) responde `400 invalid_scope`.
- En `/refresh` se quitan los scopes que el rol ya no tenga, así los cambios de rol se aplican sin forzar un nuevo login.
- Un usuario sin rol obtiene un token sin scopes.

Los tokens de cliente (`client_credentials`) siguen las mismas reglas con la unión de los scopes de todos los roles del cliente. **Un cliente sin roles no puede pedir ningún scope.**

### Tokens por API

Un cliente `client_credentials` puede mandar `api=<nombre de la API>` para obtener un token que solo sirve en esa API:

- Sin `scope`: el token trae **todos** los scopes que los roles del cliente le dan en esa API. El sistema no necesita conocer los scopes.
- Con `scope`: el token trae solo esos, y todos deben pertenecer a esa API y estar en los roles del cliente; si no, `400 invalid_scope`.
- Si la API no existe: `400 invalid_request` (`Unknown API [...]`).
- Si el cliente no tiene scopes en esa API: `400 invalid_scope` (`The client has no scopes in API [...]`), en vez de emitir un token vacío.

Así, un token filtrado solo sirve en la API que lo recibió. UBO.Common pide los tokens de esta forma y guarda uno en caché por API.

### Registrar un sistema que consume varias APIs

Crea un rol por perfil de acceso de cada API y asigna al sistema los que necesite:

```bash
php artisan oauth:role maestros-lector "Lectura de API-Maestros"
```

```bash
php artisan oauth:scope geografia:leer "Leer geografía" --api=api-maestros --role=maestros-lector
```

```bash
php artisan passport:client --client --name="sistema-x"
```

```bash
php artisan oauth:client-role sistema-x maestros-lector matricula-lector
```

```bash
php artisan oauth:client-scopes sistema-x
```

`oauth:scope` exige que la API (`--api`) y los roles (`--role`) existan. Volver a ejecutarlo con otra `--api` mueve el scope sin cambiar su nombre. `oauth:client-role` agrega roles; con `--sync` reemplaza los anteriores. `oauth:client-scopes` muestra los scopes agrupados por API.

### Agregar una API nueva

No hace falta tocar código ni desplegar este servicio:

```bash
php artisan oauth:api api-matricula "Matrícula de alumnos"
```

```bash
php artisan oauth:scope matricula:leer "Leer matrículas" --api=api-matricula --role=admin
```

```bash
php artisan oauth:assign-role ana@example.com admin
```

En la API nueva, exige el scope en la ruta:

```php
use Laravel\Passport\Http\Middleware\CheckToken;

Route::get('/orders', ...)->middleware(['auth:api', CheckToken::using('orders:read')]);
```

Si la API valida el JWT localmente, debe revisar que el claim `scopes` contenga el scope requerido.

## Configuración de expiración (minutos)

`PASSPORT_ACCESS_TOKEN_TTL` (60), `PASSPORT_REFRESH_TOKEN_TTL` (43200 = 30 días), `PASSPORT_CLIENT_TOKEN_TTL` (60).

Limpiar tokens expirados/revocados periódicamente:

```bash
php artisan passport:purge
```

## Tests

```bash
php artisan test
```

## Estructura

- [app/Services/OAuthTokenService.php](app/Services/OAuthTokenService.php) — emite/valida tokens usando el servidor OAuth2 de Passport en proceso.
- [app/Http/Controllers/Auth/AuthController.php](app/Http/Controllers/Auth/AuthController.php) — registro, login, refresh, me, logout.
- [app/Http/Controllers/Auth/TokenIntrospectionController.php](app/Http/Controllers/Auth/TokenIntrospectionController.php) — introspección y clave pública.
- [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) — grants, TTL, rate limiting y binding del ScopeRepository.
- [app/Services/ScopeRepository.php](app/Services/ScopeRepository.php) — catálogo de scopes en BD y filtrado por rol.
- [routes/api.php](routes/api.php) — rutas.
