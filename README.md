# API-Auth — Microservicio de autenticación (Laravel 13 + Passport 13)

Servicio OAuth2 que emite y valida tokens JWT para el resto de microservicios.

- **Usuarios finales**: registro, login, refresh, perfil y logout (password grant, el secreto del cliente nunca sale del servidor).
- **Otros microservicios**: se autentican con *client credentials* y validan tokens de usuario vía `/introspect`, o localmente con la clave pública.
- **Documentación**: Swagger UI en `/docs/swagger` (la raíz `/` redirige ahí). El contrato está en [`docs/openapi/openapi.yaml`](docs/openapi/openapi.yaml) y se sirve en `/docs/openapi.yaml`; un test verifica que documente todas las rutas `/api`.
- **Estándar UBO**: errores RFC 9457, tracing con [`ubo/common`](https://github.com/LuisOlavUBO/UBO-Common) y tests contra SQL Server, como [API-Maestros](../API-Maestros).

## Instalación

Requiere PHP 8.4 con `pdo_sqlsrv` y `sqlsrv`.

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

Los tests corren contra SQL Server, en la base `apiauth_test` (ver [Tests](#tests)).

### Caché y rate limiter

`CACHE_STORE=database`: lo único que usa la caché es el rate limiter de `register`/`login`/`refresh`, que cuenta los intentos en la tabla `cache` de `apiauth`. No usa Redis a propósito: para este volumen la base alcanza, y un servicio más sería otro punto de falla para el login del que dependen todas las APIs. Las sesiones (`SESSION_DRIVER`) y la cola también están en la base de datos: la API no usa sesiones fuera de la página de Swagger.

En producción (o contenedores) puedes pasar las claves por entorno en vez de archivos: `PASSPORT_PRIVATE_KEY` y `PASSPORT_PUBLIC_KEY`.

```bash
php artisan serve
```

## Docker

Sigue el estándar de las APIs Laravel de la UBO (como API-Admisión y API-Maestros): un contenedor PHP-FPM (`php:8.4-fpm-bookworm`) y su propio nginx, que usa la imagen oficial con [nginx/default.conf](nginx/default.conf) montado.

### Desarrollo

```bash
docker compose up -d --build
```

- La API queda en `http://localhost:8000`. El puerto se cambia con `DOCKER_HTTP_PORT` en el `.env`.
- La base de datos es la del `.env`.
- El código llega por bind mount, y Laravel lee el `.env` del proyecto. `vendor/` vive en un volumen del contenedor: si cambian `composer.json` o `composer.lock`, `docker compose up -d --build -V`.
- Las llaves de Passport se montan en `/run/keys` y el entrypoint las pasa por contenido (`PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY`), porque Passport rechaza los permisos 777 de los bind mounts en Windows. `docker compose exec` no pasa por el entrypoint, así que los comandos que usan las llaves, como los tests, se corren con `run` (ver [Tests](#tests)).

### Despliegue

`docker-compose.prod.yml` levanta `app` (imagen de `Dockerfile.prod`) y `nginx`. El healthcheck de nginx llama a `/api/v1/salud`, que consulta la base, así que el estado `healthy` cubre nginx → PHP-FPM → SQL Server. La imagen no incluye dependencias de desarrollo, `.env` ni llaves; `ubo/common` se descarga de GitHub como cualquier otra dependencia, sin credenciales. Al arrancar, el entrypoint valida las variables críticas, carga las llaves y ejecuta `php artisan optimize`. Con `RUN_MIGRATIONS=true` también corre las migraciones.

Construir y publicar, en el PC:

```bash
IMAGE=ghcr.io/luisolavubo/api-auth:1.1.0 docker compose -f docker-compose.prod.yml build app
docker push ghcr.io/luisolavubo/api-auth:1.1.0
```

En el servidor, `~/projects/api-auth` contiene solo:

| Archivo | Contenido | En SRV-CONTENEDOR viene de |
|---|---|---|
| `docker-compose.prod.yml` | El compose | El repo |
| `nginx/default.conf` | Configuración de nginx | El repo |
| `.env` | Variables de Compose: `IMAGE` y `DOCKER_HTTP_PORT_PROD` | `.env.srv-contenedor` |
| `.env.prod` | Configuración de Laravel: `APP_KEY`, `APP_URL`, `DB_*`, `PASSPORT_*` | `.env.prod.srv-contenedor` |
| `storage/oauth-*.key` | Llaves de Passport | Las mismas de la instancia local |

Los `.env.*` y las llaves no se versionan. En el servidor:

```bash
chmod 600 .env .env.prod storage/oauth-*.key
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d --no-build
```

El compose fuerza `APP_ENV=production`, `APP_DEBUG=false` y `LOG_CHANNEL=stderr` (logs con `docker compose -f docker-compose.prod.yml logs app`). HTTPS se termina en el proxy inverso que esté delante del nginx.

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

Además Passport expone `POST /oauth/token` (estándar OAuth2), usado por los microservicios para el grant `client_credentials`, y `GET /api/v1/salud` responde `{"estado": "OK", "base_datos": "OK"}` o `503` si la base no responde (misma ruta y forma que en API-Maestros). `/up` solo indica que Laravel arrancó.

Los endpoints públicos de `register`/`login`/`refresh` tienen rate limit de 10 req/min por email+IP.

### Errores (RFC 9457)

Las rutas `/api/v1/*` responden los errores como `application/problem+json`, igual que las demás APIs de la UBO ([ProblemRenderer.php](app/Exceptions/ProblemRenderer.php)):

```json
{
  "type": "about:blank",
  "title": "Credenciales inválidas",
  "status": 401,
  "detail": "The user credentials were incorrect.",
  "error": "invalid_grant"
}
```

`error` es el código estable para programar contra él:

| `error` | Estado | Cuándo |
|---|---|---|
| `invalid_grant` | 401 | Login o refresh con credenciales o refresh token inválidos |
| `invalid_scope` / `invalid_request` | 400 | Login o refresh con un scope o una solicitud OAuth inválidos |
| `NO_AUTENTICADO` | 401 | Falta el token, o es inválido, vencido o revocado |
| `SCOPE_INSUFICIENTE` | 403 | El token no tiene el scope que exige la ruta |
| `VALIDACION` | 422 | Datos inválidos; los mensajes por campo van en `errores` |
| `LIMITE_EXCEDIDO` | 429 | Rate limit superado; `Retry-After` indica cuándo reintentar |
| `RUTA_NO_ENCONTRADA` / `METODO_NO_PERMITIDO` | 404 / 405 | La ruta o el método no existen |
| `ERROR_INTERNO` | 500 | Error inesperado; el detalle queda solo en el log |

Los fallos de login y refresh conservan su código OAuth en `error`, como antes. Lo que cambió para quien consume: el texto pasa de `message` a `detail`, y los errores de validación de `errors` a `errores`.

`POST /oauth/token` **no cambia**: lo atiende Passport con el formato OAuth2 (`{error, error_description, hint}`), que esperan los clientes OAuth y `HttpServiceTokenProvider` de UBO.Common. La introspección mantiene su `200` (`active` true/false) y sus estados de error: `IntrospectionValidator` de UBO.Common solo mira el estado (con `401` renueva su token y reintenta).

### Tracing (UBO.Common)

Esta API instala [`ubo/common`](https://github.com/LuisOlavUBO/UBO-Common) solo por el módulo **Tracing** ([config/ubo-common.php](config/ubo-common.php)): continúa el `traceparent` de quien llama (W3C Trace Context) o empieza una traza, agrega `trace_id`, `span_id` y `parent_span_id` a cada línea de log y devuelve el header `X-Trace-Id`, también en los errores. Así una llamada de API-Maestros a `/oauth/token` o `/introspect` queda con el mismo `trace_id` en los logs de ambas.

| Variable | Valor | Uso |
|---|---|---|
| `UBO_COMMON_AUTH` | `false` | Esta API emite los tokens y los valida con Passport: no debe validarse contra sí misma |
| `UBO_COMMON_API_CLIENT` | `false` | Esta API no llama a otras APIs de la UBO |
| `UBO_TRACING_RESPONSE_HEADER` | `X-Trace-Id` | Header de respuesta con el `trace_id` (vacío: no se envía) |

Ambos módulos quedan apagados aunque falten las variables. Para traer una versión nueva: `composer update ubo/common`.

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

El token nombra la API en su `aud`, después del client id: `"aud": ["<client id>", "api-maestros"]`. La introspección devuelve el mismo `aud`. Una API que usa UBO.Common con `UBO_AUTH_API=<su nombre>` rechaza los tokens de sistema emitidos para otra API, así que un token filtrado solo sirve en la API que lo recibió, sin depender de cómo se nombren los scopes. UBO.Common pide los tokens de esta forma y guarda uno en caché por API.

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

- **Base de datos:** corren contra **SQL Server**, en la base `apiauth_test` ([phpunit.xml](phpunit.xml)), nunca SQLite: SQL Server devuelve, por ejemplo, los `uniqueidentifier` de los clientes en mayúsculas y los enteros como texto, y SQLite no lo detecta. No corras dos suites al mismo tiempo contra la misma base: `RefreshDatabase` hace que se bloqueen entre sí.
- **SQL Server en Docker (recomendado):** el login `apiauth_app` del servidor de desarrollo no tiene acceso a otra base. Define `DOCKER_MSSQL_SA_PASSWORD` en el `.env` (mínimo 8 caracteres con mayúsculas, minúsculas, números y símbolos) y levanta solo `sqlserver` y `sqlserver-init`, que crea `apiauth_test` con la collation de `apiauth` (`SQL_Latin1_General_CP1_CI_AS`):
  ```bash
  docker compose up -d sqlserver sqlserver-init
  ```
  Están en el perfil `tests`, así que `docker compose up -d` no los levanta. El puerto publicado es `14334` (se cambia con `DOCKER_MSSQL_PORT`), para no chocar con el `14333` de API-Maestros. Espera a que `sqlserver-init` termine (`docker compose ps -a sqlserver-init` muestra `exited (0)`) y corre los tests desde el host con el usuario `sa`. Las variables de entorno tienen prioridad sobre el `.env`, que no se modifica.

  En PowerShell:
  ```powershell
  $env:DB_HOST='127.0.0.1'; $env:DB_PORT='14334'; $env:DB_USERNAME='sa'
  $env:DB_PASSWORD=(Select-String '^DOCKER_MSSQL_SA_PASSWORD=' .env).Line.Split('=', 2)[1]
  php artisan test
  ```
  En bash:
  ```bash
  DB_HOST=127.0.0.1 DB_PORT=14334 DB_USERNAME=sa \
    DB_PASSWORD="$(grep '^DOCKER_MSSQL_SA_PASSWORD=' .env | cut -d= -f2-)" php artisan test
  ```
  Desde el contenedor `app` (sin PHP en el host), apuntando al servicio `sqlserver`:
  ```bash
  docker compose run --rm --no-deps -e DB_HOST=sqlserver -e DB_PORT=1433 -e DB_USERNAME=sa \
    -e DB_PASSWORD="$(grep '^DOCKER_MSSQL_SA_PASSWORD=' .env | cut -d= -f2-)" app php artisan test
  ```
  Las variables de PowerShell duran hasta cerrar la terminal. Para detener el contenedor: `docker compose stop sqlserver` (los datos quedan en el volumen `sqlserver-data`).
- **Caché:** los tests usan el store `array`.
- **Contrato:** `DocsTest` verifica que `docs/openapi/openapi.yaml` documente todas las rutas `/api`. Lint:
  ```bash
  npx @redocly/cli lint docs/openapi/openapi.yaml
  ```

## Estructura

- [app/Services/OAuthTokenService.php](app/Services/OAuthTokenService.php) — emite/valida tokens usando el servidor OAuth2 de Passport en proceso.
- [app/Http/Controllers/Auth/AuthController.php](app/Http/Controllers/Auth/AuthController.php) — registro, login, refresh, me, logout.
- [app/Http/Controllers/Auth/TokenIntrospectionController.php](app/Http/Controllers/Auth/TokenIntrospectionController.php) — introspección y clave pública.
- [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) — grants, TTL, rate limiting y binding del ScopeRepository.
- [app/Services/ScopeRepository.php](app/Services/ScopeRepository.php) — catálogo de scopes en BD y filtrado por rol.
- [app/Services/AccessToken.php](app/Services/AccessToken.php), [AccessTokenRepository.php](app/Services/AccessTokenRepository.php) y [TokenAudience.php](app/Services/TokenAudience.php) — el `aud` de los tokens por API: `ScopeRepository` valida `api` y lo deja en `TokenAudience`, y el repositorio crea el token con esa API.
- [app/Exceptions/ProblemRenderer.php](app/Exceptions/ProblemRenderer.php) — errores RFC 9457 de `/api/*`.
- [app/Http/Controllers/HealthController.php](app/Http/Controllers/HealthController.php) — `/api/v1/salud`.
- [routes/api.php](routes/api.php) — rutas.
