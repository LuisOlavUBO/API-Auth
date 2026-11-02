# Proposal

## Why

Los satélites de la UBO deben iniciar sesión en un solo lugar: IAM. Hoy API-Auth solo emite tokens a quien le entrega la contraseña (`/v1/login`, password grant), así que cada satélite tendría que recibirla. Este cambio agrega el login central con el flujo estándar Authorization Code + PKCE, para que la contraseña solo se escriba en IAM y cada satélite reciba su propio token con los roles que el usuario tiene en él (ver el documento de diseño "IAM Central UBO"). Es la entrega 2a: login con email y contraseña para satélites monolito. Microsoft Entra ID (2b) y las SPA quedan para después.

## What Changes

- **Login web** en Blade dentro de API-Auth: `GET/POST /api/auth/login`, con sesión en base de datos, CSRF y el límite de intentos por email e IP.
- **Autorización con código y PKCE:** se publica `GET /api/auth/oauth/authorize` (hoy Passport lo tiene desactivado). Exige PKCE con `S256`, no muestra pantalla de consentimiento (los satélites son propios) y manda al login a quien no ha iniciado sesión. `prompt=login` vuelve a pedir la clave.
- **Usuario sin roles en la aplicación del cliente:** IAM muestra "No tienes acceso a este sistema, contacta a soporte" y no lo devuelve al satélite.
- **Logout central:** `GET /api/auth/logout?client_id=&return=` cierra la sesión de IAM, revoca todos los tokens del usuario y vuelve a `return`, validado contra las URLs registradas del cliente. `POST /api/auth/logout` hace lo mismo desde la propia página de IAM.
- **Clientes de código de autorización:** `oauth:client --type=authorization-code --redirect=*` crea clientes confidenciales con sus `redirect_uris`.
- **Tiempos:** el refresh token pasa de 30 días a 120 minutos de inactividad (`PASSPORT_REFRESH_TOKEN_TTL`); la sesión de IAM dura 120 minutos y su cookie queda acotada a `/api/auth`.
- **Detrás del proxy:** `TRUSTED_PROXIES` para que el límite de solicitudes vea la IP real, y el límite de `/oauth/token` pasa a ser por cliente.
- **Errores:** las páginas web (`login`, `logout`, `authorize`) responden HTML y redirecciones, no problem+json; el resto de `/api/auth` sigue igual, y quien no tiene token sigue recibiendo 401.
- **Efecto sobre `/v1/login`:** su refresh token también vence a los 120 minutos de inactividad.

Fuera de alcance: Microsoft Entra ID y la vinculación por `oid`, cerrar el registro público `/v1/register`, la baja de usuarios, CORS y clientes públicos para SPAs, el módulo de UBO.Common y cualquier cambio en el contenido de los tokens.

## Capabilities

### New Capabilities

- `central-login`: login web con sesión, autorización con código y PKCE sin consentimiento, rechazo de usuarios sin roles en la aplicación, `prompt=login` y logout central.

### Modified Capabilities

- `service-tokens`: el endpoint de autorización se publica junto al de token; el endpoint de token tiene un límite de solicitudes por cliente; el grant de código de autorización emite los tokens de siempre.
- `error-responses`: las páginas web de login, logout y autorización quedan fuera del formato problem+json.
- `access-administration`: `oauth:client` crea clientes de código de autorización con sus URLs de redirección.
- `user-authentication`: la vigencia por defecto del refresh token baja a 120 minutos.
- `service-operations`: la IP de quien llama se toma de los proxies de confianza configurados.

## Impact

- **Código:** `routes/web.php`, `bootstrap/app.php` (proxies, redirección de invitados, render de errores), `AppServiceProvider` (rutas de Passport, límites, modelo de cliente), un modelo `Client` propio, controladores y middleware de login, autorización y logout, vistas Blade, `Application::createClient`, el comando `oauth:client` y `OAuthTokenService` (revocar todos los tokens de un usuario).
- **Configuración:** `config/session.php` (cookie bajo `/api/auth`), `config/oauth.php`, `.env.example`, `docker-compose.prod.yml` y el README (variables `TRUSTED_PROXIES`, `SESSION_SECURE_COOKIE` y los tiempos).
- **Documentación:** `docs/openapi` (las rutas nuevas, porque se verifica que toda ruta bajo `/api` esté documentada) y `docs/bruno` (solicitud OAuth2 con PKCE).
- **Datos:** ninguna migración; la tabla `sessions` ya existe.
- **Dependencias:** ninguna nueva.
- **Despliegue:** hay que fijar `SESSION_SECURE_COOKIE=true` y `TRUSTED_PROXIES` en producción y bajar `PASSPORT_REFRESH_TOKEN_TTL` en los entornos que lo tengan definido.
