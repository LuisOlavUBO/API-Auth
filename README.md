# API-Auth — Microservicio de autenticación (Laravel 13 + Passport 13)

Servicio OAuth2 que emite y valida tokens JWT para el resto de microservicios.

- **Usuarios finales**: registro, login, refresh, perfil y logout (password grant, el secreto del cliente nunca sale del servidor).
- **Otros microservicios**: se autentican con *client credentials* y validan tokens de usuario vía `/introspect`, o localmente con la clave pública.
- **Rutas**: todas bajo `/api/auth` ([ApiPrefix.php](app/Http/ApiPrefix.php)): `/api/auth/v1/...` y `POST /api/auth/oauth/token`.
- **Documentación**: Swagger UI en `/api/auth/docs/swagger` (`/` y `/docs/swagger` redirigen ahí). El contrato está en [`docs/openapi/openapi.yaml`](docs/openapi/openapi.yaml) y se sirve en `/api/auth/docs/openapi.yaml`; un test verifica que documente todas las rutas `/api`.
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
php artisan oauth:client "Auth Service Password Client" --application=api-auth --type=password
```

Copia el `Client ID` y `Client Secret` del último comando a `.env`:

```
PASSPORT_PASSWORD_CLIENT_ID=...
PASSPORT_PASSWORD_CLIENT_SECRET=...
```

El secreto se guarda hasheado en la base de datos y solo se muestra una vez.

### Base de datos

Usa SQL Server 2016 (base **`apiauth`**), dedicada solo a autenticación: usuarios, tokens y clientes OAuth. La API se conecta con el login propio **`apiauth_app`**, que no es sysadmin. Solo tiene acceso a `apiauth`, con los roles `db_datareader`, `db_datawriter` y `db_ddladmin`; este último sirve para correr las migraciones. Su idioma por defecto es `us_english` y su base por defecto es `apiauth`. Completa `DB_HOST`, `DB_USERNAME` y `DB_PASSWORD` en `.env`. Requiere las extensiones `sqlsrv`/`pdo_sqlsrv` y el ODBC Driver 18.

El login del servidor tiene idioma español (formato de fecha `dmy`). Por eso el módulo **SqlServer** de UBO.Common ([config/ubo-common.php](config/ubo-common.php)) ejecuta `SET DATEFORMAT ymd` en cada conexión. Sin ese ajuste, Laravel no puede guardar las fechas.

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

`docker-compose.prod.yml` levanta `app` (imagen de `Dockerfile.prod`) y `nginx`. El healthcheck de nginx llama a `/api/auth/v1/salud`, que consulta la base, así que el estado `healthy` cubre nginx → PHP-FPM → SQL Server. La imagen no incluye dependencias de desarrollo, `.env` ni llaves; `ubo/common` se descarga de GitHub como cualquier otra dependencia, sin credenciales. Al arrancar, el entrypoint valida las variables críticas, carga las llaves y ejecuta `php artisan optimize`. Con `RUN_MIGRATIONS=true` también corre las migraciones.

Construir y publicar, en el PC:

```bash
IMAGE=ghcr.io/luisolavubo/api-auth:3.0.0 docker compose -f docker-compose.prod.yml build app
docker push ghcr.io/luisolavubo/api-auth:3.0.0
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

**Proxy inverso:** `apidev.ubo.cl` es compartido por varias APIs. `ubo-nginx-proxy` elige el contenedor por el segmento que sigue a `/api` (`/api/auth/...` → `api-auth-nginx-prod`, por la red externa `proxy`) y pasa la ruta sin reescribirla, así que la URL interna y la externa son iguales: `http://192.168.51.220:4203/api/auth/v1/salud` y `https://apidev.ubo.cl/api/auth/v1/salud`. La imagen 3.0.0 cambió todas las URLs de la 2.x (`/api/v1/auth/*` → `/api/auth/v1/*`, `/oauth/token` → `/api/auth/oauth/token`) y no mantiene las antiguas: los consumidores necesitan una versión de UBO.Common con las rutas nuevas.

## Endpoints (`/api/auth/v1`)

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

Además `POST /api/auth/oauth/token` es el endpoint estándar OAuth2 de Passport, usado por los microservicios para el grant `client_credentials`. Junto con `GET /api/auth/oauth/authorize` del [login central](#login-central-sso-de-los-satélites-monolito) son las únicas rutas de Passport registradas: las demás (aprobar o rechazar, `/oauth/device`, `/oauth/token/refresh`) están apagadas con `Passport::ignoreRoutes()`. El login central agrega las páginas web `GET/POST /api/auth/login` y `GET/POST /api/auth/logout`, que responden HTML y no problem+json. `GET /api/auth/v1/salud` responde `{"estado": "OK", "base_datos": "OK"}` o `503` si la base no responde (misma forma que en API-Maestros). `/up` solo indica que Laravel arrancó.

Los endpoints públicos de `register`/`login`/`refresh` tienen rate limit de 10 req/min por email+IP.

### Errores (RFC 9457)

Las rutas `/api/auth/*` responden los errores como `application/problem+json`, igual que las demás APIs de la UBO. Los presenta el `ProblemRenderer` del módulo **Problems** de UBO.Common, registrado en [bootstrap/app.php](bootstrap/app.php). Los fallos de login y refresh son una `OAuthTokenException` ([OAuthTokenException.php](app/Exceptions/OAuthTokenException.php)), un error de negocio (`DomainProblem`) de esta API que el renderizador presenta con su código OAuth:

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

Los fallos de login y refresh conservan su código OAuth en `error`, como antes. Si Passport rechaza el cliente password (`invalid_client`), es un error de configuración del servidor: responde `500` y el motivo queda solo en el log. Los demás fallos (contraseña incorrecta, refresh token vencido o revocado, scope inválido) son esperables y no se registran en el log. Lo que cambió para quien consume: el texto pasa de `message` a `detail`, y los errores de validación de `errors` a `errores`.

`POST /api/auth/oauth/token` mantiene el formato OAuth2 (`{error, error_description, hint}`), que esperan los clientes OAuth y `HttpServiceTokenProvider` de UBO.Common: Passport presenta sus propias excepciones antes que el `ProblemRenderer`. Solo los errores que no son de OAuth (ruta o método inexistente, rate limit) salen en RFC 9457. La introspección mantiene su `200` (`active` true/false) y sus estados de error: `IntrospectionValidator` de UBO.Common solo mira el estado (con `401` renueva su token y reintenta).

### Tracing (UBO.Common)

Esta API usa de [`ubo/common`](https://github.com/LuisOlavUBO/UBO-Common) los módulos **Tracing**, **Problems** (errores de `/api/*`), **Health** (`/api/auth/v1/salud`) y **SqlServer** (`SET DATEFORMAT ymd`), encendidos en [config/ubo-common.php](config/ubo-common.php). Tracing continúa el `traceparent` de quien llama (W3C Trace Context) o empieza una traza, agrega `trace_id`, `span_id` y `parent_span_id` a cada línea de log y devuelve el header `X-Trace-Id`, también en los errores. Así una llamada de API-Maestros a `/api/auth/oauth/token` o `/introspect` queda con el mismo `trace_id` en los logs de ambas.

| Variable | Valor | Uso |
|---|---|---|
| `UBO_COMMON_AUTH` | `false` | Esta API emite los tokens y los valida con Passport: no debe validarse contra sí misma |
| `UBO_COMMON_API_CLIENT` | `false` | Esta API no llama a otras APIs de la UBO |
| `UBO_TRACING_RESPONSE_HEADER` | `X-Trace-Id` | Header de respuesta con el `trace_id` (vacío: no se envía) |

Ambos módulos quedan apagados aunque falten las variables. Para traer una versión nueva: `composer update ubo/common`.

### Ejemplo

```bash
curl -X POST http://localhost:8000/api/auth/v1/login -H "Accept: application/json" -d "email=ana@example.com&password=secret-password"
```

```bash
curl http://localhost:8000/api/auth/v1/me -H "Accept: application/json" -H "Authorization: Bearer <access_token>"
```

### Colección Bruno

[docs/bruno](docs/bruno) es una colección de [Bruno](https://www.usebruno.com/) con todos los endpoints, documentados y con tests. Se abre con *Open Collection* sobre esa carpeta; se elige el entorno `Local` o `Servidor` y se completan `email`, `password`, `client_id` y `client_secret` (las secretas quedan solo en el equipo); para la solicitud OAuth2 de **Login central**, también `callback_url`, `code_client_id` y `code_client_secret`. **Iniciar sesión** guarda `accessToken`/`refreshToken` y **Token de cliente (introspect)** guarda `clientToken` para las demás solicitudes.

## Login central (SSO de los satélites monolito)

La contraseña solo se escribe en IAM. Un satélite monolito manda el navegador de la persona a `GET /api/auth/oauth/authorize` (Authorization Code con PKCE `S256`, sin pantalla de consentimiento); IAM muestra su login en `/api/auth/login` (Blade, dentro de este servicio), guarda la sesión y devuelve un `code` a la `redirect_uri` del satélite, que lo canjea en `POST /api/auth/oauth/token` con su `code_verifier` y las credenciales de su cliente. El token trae los roles y scopes que la persona tiene en la aplicación del cliente. Las SPA, CORS y los clientes públicos quedan para otra entrega.

1. Crear el cliente de código de autorización del satélite (confidencial, con al menos una `--redirect` absoluta; se repite para varias). La aplicación y los roles de la persona se crean como se explica en [Scopes y roles](#scopes-y-roles):

   ```bash
   php artisan oauth:application portal "Portal de la UBO"
   ```

   ```bash
   php artisan oauth:client portal-web --application=portal --type=authorization-code --redirect=https://portal.ubo.cl/callback
   ```

2. El satélite genera un `code_verifier` y un `state`, y manda a la persona a:

   ```
   GET /api/auth/oauth/authorize?response_type=code&client_id=<id>&redirect_uri=https://portal.ubo.cl/callback&state=<state>&code_challenge=<base64url(sha256(code_verifier))>&code_challenge_method=S256
   ```

   - Sin `code_challenge` o con `plain` responde `400` antes de pedir la clave. Una `redirect_uri` que el cliente no tiene registrada responde un error y no redirige.
   - Una persona sin ningún rol en la aplicación del cliente ve "No tienes acceso a este sistema, contacta a soporte" (403): no recibe código ni vuelve al satélite.
   - `prompt=login` cierra la sesión de IAM y vuelve a pedir la clave, aunque hubiera sesión. Afecta a la sesión de IAM completa: los tokens de los otros satélites siguen hasta que venzan.

3. Con el `code` recibido, el satélite pide sus tokens:

   ```bash
   curl -X POST http://localhost:8000/api/auth/oauth/token -d "grant_type=authorization_code&client_id=<id>&client_secret=<secret>&redirect_uri=https://portal.ubo.cl/callback&code_verifier=<code_verifier>&code=<code>"
   ```

4. Logout central: el satélite manda a `GET /api/auth/logout?client_id=<id>&return=https://portal.ubo.cl/` (el `return` debe tener el mismo origen que una redirección registrada del cliente). Cierra la sesión de IAM, revoca todos los tokens de la persona en todos los satélites y vuelve a `return`; si no coincide, muestra "Sesión cerrada". La página de IAM tiene su propio botón (`POST /api/auth/logout`).

**Tiempos:** la sesión de IAM dura 120 minutos sin uso (`SESSION_LIFETIME`) y su cookie va acotada a `/api/auth`, `HttpOnly` y `SameSite=Lax`. El refresh token vence a los 120 minutos de inactividad (`PASSPORT_REFRESH_TOKEN_TTL`, se renueva con cada uso); esto también vale para los tokens de `/api/auth/v1/login`.

**Variables de despliegue** (en el `.env.prod`):

- `SESSION_SECURE_COOKIE=true` (el compose de producción ya lo fija): la cookie de sesión solo viaja por HTTPS.
- `TRUSTED_PROXIES`: proxies cuyas cabeceras `X-Forwarded-*` se aceptan, para que los límites de solicitudes vean la IP real. Vacío no confía en nadie; `*` confía en todos (solo si el servicio únicamente es alcanzable por el proxy; si no, cualquiera puede falsificar su IP); o una lista de IP o redes separada por comas, p. ej. `172.18.0.0/16`.
- `OAUTH_TOKEN_RATE_LIMIT`: solicitudes por minuto a `/api/auth/oauth/token` por `client_id` (120 por omisión).
- Si estaban definidas, quitar `SESSION_PATH` y bajar `PASSPORT_REFRESH_TOKEN_TTL` a 120.

## Integrar otro microservicio

1. Registrar la aplicación del servicio, crear su cliente y darle roles (sin roles no puede pedir ningún scope). Todo cliente pertenece a una aplicación, por eso se crea con `oauth:client` y no con `passport:client`:

   ```bash
   php artisan oauth:application orders-service "Servicio de órdenes"
   ```

   ```bash
   php artisan oauth:client orders-service --application=orders-service --type=client-credentials
   ```

   ```bash
   php artisan oauth:client-role orders-service api-auth/introspector
   ```

2. El servicio obtiene su token:

   ```bash
   curl -X POST http://localhost:8000/api/auth/oauth/token -d "grant_type=client_credentials&client_id=<id>&client_secret=<secret>&scope=introspect"
   ```

   Para llamar a otra API, lo recomendado es pedir un token **por API** con el parámetro `api` (ver [Tokens por API](#tokens-por-api)):

   ```bash
   curl -X POST http://localhost:8000/api/auth/oauth/token -d "grant_type=client_credentials&client_id=<id>&client_secret=<secret>&api=api-maestros"
   ```

3. Para validar el token de un usuario que le llega, tiene dos opciones:
   - **Introspección** (detecta revocaciones/logout): `POST /api/auth/v1/introspect` con `token=<token del usuario>` y su propio token de cliente como Bearer.
   - **Verificación local** (sin llamada de red, no detecta revocación hasta que expira): verificar la firma RS256 del JWT con la clave de `/api/auth/v1/public-key` y revisar `exp`.

## Scopes y roles

Los scopes viven en base de datos y se asignan **por rol**:

Una **aplicación** es un satélite, una API o un monolito. Los scopes, los roles y los clientes viven en base de datos y pertenecen a una aplicación:

- `applications`: las aplicaciones (`api-auth`, `api-maestros`, `portal`, ...). Cada una usa solo lo que necesita: scopes si valida accesos, roles si tiene usuarios o los ofrece a quien la consume, clientes si pide tokens.
- `oauth_scopes`: catálogo de scopes que existen (`user:read`, `geografia:leer`, ...). Cada scope pertenece a una aplicación (`application_id`, obligatorio). El nombre sigue la convención `dominio:permiso` y es único en toda la organización.
- `roles` + `oauth_scope_role`: qué scopes concede cada rol, de cualquier aplicación. Un rol pertenece a una aplicación y su nombre es único dentro de ella; en los comandos se escribe `<aplicación>/<rol>`.
- `role_user`: roles de cada usuario (varios, en una o varias aplicaciones). Los usuarios nuevos reciben el rol `OAUTH_DEFAULT_ROLE` (por defecto `user`) de la aplicación `OAUTH_APPLICATION` (por defecto `api-auth`).
- `oauth_clients.application_id`: la aplicación de cada cliente (obligatorio). Un cliente de `client_credentials` puede tener roles de cualquier aplicación (`oauth_client_role`), normalmente los que ofrece cada API que consume.

Carga inicial (aplicación `api-auth`; roles `user`, `admin` e `introspector`; scopes `user:read`, `user:write`, `introspect`):

```bash
php artisan db:seed --class=RoleScopeSeeder
```

Al emitir un token de usuario, [app/Services/ScopeRepository.php](app/Services/ScopeRepository.php) usa solo los roles del usuario que pertenecen a la aplicación del cliente que lo obtiene:

- Sin `scope`, el token trae todos los scopes de esos roles. `POST /api/auth/v1/login` es la excepción: sin `scope` pide el scope por defecto (`user:read`).
- Pedir un scope que esos roles no conceden (o `*`, o uno inexistente) responde `400 invalid_scope`.
- Un usuario sin roles en la aplicación obtiene un token sin scopes.
- En `/refresh` se quitan los scopes que los roles ya no conceden y nunca se agregan; los scopes nuevos llegan en el siguiente login. La claim `roles` se recalcula.
- La claim `roles` lleva los nombres de esos roles y `aud` lleva el client id y, en orden alfabético, las aplicaciones dueñas de los scopes.

Los tokens de cliente (`client_credentials`) usan la unión de los scopes de todos los roles del cliente y solo los que se piden. **Sin `scope` y sin `api`, el token no trae scopes.** Su claim `roles` lleva los roles del cliente que conceden alguno de los scopes del token.

### Tokens por API

Un cliente `client_credentials` puede mandar `api=<nombre de la API>` para obtener un token que solo sirve en esa API:

- Sin `scope`: el token trae **todos** los scopes que los roles del cliente le dan en esa API. El sistema no necesita conocer los scopes.
- Con `scope`: el token trae solo esos, y todos deben pertenecer a esa API y estar en los roles del cliente; si no, `400 invalid_scope`.
- Si la API no existe: `400 invalid_request` (`Unknown API [...]`).
- Si el cliente no tiene scopes en esa API: `400 invalid_scope` (`The client has no scopes in API [...]`), en vez de emitir un token vacío.

El token nombra la API en su `aud`, después del client id: `"aud": ["<client id>", "api-maestros"]`. La introspección devuelve el mismo `aud`. Una API que usa UBO.Common con `UBO_AUTH_API=<su nombre>` rechaza los tokens de sistema emitidos para otra API, así que un token filtrado solo sirve en la API que lo recibió, sin depender de cómo se nombren los scopes. UBO.Common pide los tokens de esta forma y guarda uno en caché por API.

### Registrar un sistema que consume varias APIs

Cada API ofrece un rol por perfil de acceso y se asignan al cliente del sistema los que necesite:

```bash
php artisan oauth:application api-maestros "Datos maestros"
```

```bash
php artisan oauth:role maestros-lector "Lectura de API-Maestros" --application=api-maestros
```

```bash
php artisan oauth:scope geografia:leer "Leer geografía" --application=api-maestros --role=api-maestros/maestros-lector
```

```bash
php artisan oauth:application sistema-x "Sistema X"
```

```bash
php artisan oauth:client sistema-x --application=sistema-x --type=client-credentials
```

```bash
php artisan oauth:client-role sistema-x api-maestros/maestros-lector
```

```bash
php artisan oauth:client-scopes sistema-x
```

`oauth:scope` exige que la aplicación (`--application`) y los roles (`--role`, como `<aplicación>/<rol>`) existan. Volver a ejecutarlo con otra `--application` mueve el scope sin cambiar su nombre. `oauth:client-role` agrega roles de cualquier aplicación; con `--sync` reemplaza los anteriores. `oauth:client-scopes` muestra la aplicación del cliente y sus scopes agrupados por aplicación. `oauth:client-application` mueve un cliente a otra aplicación y `oauth:role-scopes <aplicación>` lista los scopes que concede cada rol de una aplicación, para revisar que solo concede lo que su aplicación usa.

### Agregar una API nueva

No hace falta tocar código ni desplegar este servicio:

```bash
php artisan oauth:application api-matricula "Matrícula de alumnos"
```

```bash
php artisan oauth:scope matricula:leer "Leer matrículas" --application=api-matricula --role=api-auth/admin
```

```bash
php artisan oauth:assign-role ana@example.com api-auth/admin
```

`oauth:assign-role` agrega el rol sin quitar los demás del usuario; con `--sync` reemplaza solo los que tiene en la aplicación de ese rol.

En la API nueva, exige el scope en la ruta:

```php
use Laravel\Passport\Http\Middleware\CheckToken;

Route::get('/orders', ...)->middleware(['auth:api', CheckToken::using('orders:read')]);
```

Si la API valida el JWT localmente, debe revisar que el claim `scopes` contenga el scope requerido.

## Configuración de expiración (minutos)

`PASSPORT_ACCESS_TOKEN_TTL` (60), `PASSPORT_REFRESH_TOKEN_TTL` (120: es el plazo de inactividad, porque el refresh se renueva con cada uso), `PASSPORT_CLIENT_TOKEN_TTL` (60). La sesión web de IAM usa `SESSION_LIFETIME` (120).

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
- [bootstrap/app.php](bootstrap/app.php) — errores RFC 9457 de `/api/*` con el `ProblemRenderer` de UBO.Common.
- [app/Exceptions/OAuthTokenException.php](app/Exceptions/OAuthTokenException.php) — fallos de login y refresh: código OAuth, título y qué se registra en el log.
- [config/ubo-common.php](config/ubo-common.php) — módulos de UBO.Common: Tracing, Problems, Health (`/api/auth/v1/salud`) y SqlServer.
- [routes/api.php](routes/api.php) — rutas.
- [routes/web.php](routes/web.php) — login, logout y autorización del [login central](#login-central-sso-de-los-satélites-monolito); [LoginController](app/Http/Controllers/Auth/LoginController.php), [LogoutController](app/Http/Controllers/Auth/LogoutController.php), los middleware [RequirePkce](app/Http/Middleware/RequirePkce.php) y [RequireRolesInClientApplication](app/Http/Middleware/RequireRolesInClientApplication.php) y las vistas en [resources/views/auth](resources/views/auth).
