# Proposal

## Why

Las personas deben entrar siempre por la página del SSO (`/api/auth/login`), con Microsoft o, como respaldo, con su email y contraseña en esa misma página. `POST /api/auth/v1/login` y el *password grant* de `POST /api/auth/oauth/token` permiten que una aplicación pida la contraseña y la envíe a la API, saltándose la página: son una puerta que el SSO no controla. Además, la contraseña temporal y la baja de `account-lifecycle` tendrían que resolverse dos veces. Ningún satélite los necesita: usan el código con PKCE y renuevan sus tokens en `oauth/token` con su propio cliente.

## What Changes

- **BREAKING** `POST /api/auth/v1/login` y `POST /api/auth/v1/refresh` dejan de existir (404).
- **BREAKING** `POST /api/auth/oauth/token` rechaza `grant_type=password` con `unsupported_grant_type`, para cualquier cliente.
- **BREAKING** `oauth:client` ya no acepta `--type=password`.
- Se quitan el cliente interno del password grant (`PASSPORT_PASSWORD_CLIENT_ID/SECRET`) y los scopes por defecto del login (`user:read`), que solo usaba `/v1/login`.
- Los tokens de usuario se obtienen solo canjeando un código del login central, y se renuevan en `oauth/token` con `grant_type=refresh_token` y el cliente que los obtuvo.
- `/v1/me`, `/v1/logout` y `/v1/logout-all` no cambian.

Fuera de alcance: cambiar `/v1/me`, `/v1/logout` y `/v1/logout-all`; clientes públicos o SPA; los cambios de `account-lifecycle`, salvo quitarle lo que dependía de `/v1/login` (contraseña temporal en el login de la API y baja en `/v1/login` y `/v1/refresh`).

## Capabilities

### New Capabilities
<!-- Ninguna -->

### Modified Capabilities
- `user-authentication`: sin login ni refresh por la API; la renovación pasa a `oauth/token`.
- `central-login`: el rechazo de usuarios sin contraseña queda en el login web.
- `scope-authorization`: sin scopes por defecto del login; los escenarios que usaban `/v1/login` pasan al código y a los tokens de cliente.
- `error-responses`: sin los errores propios del login y el refresh de la API.
- `access-administration`: `oauth:client` sin el tipo `password`.

## Impact

- Código: rutas `v1/login` y `v1/refresh`, `AuthController`, `LoginRequest`, la parte de `OAuthTokenService` que emite tokens, `OAuthTokenException`, `Passport::enablePasswordGrant()`, la excepción de scopes por defecto en `ScopeRepository`, `config/oauth.php` y `oauth:client`.
- Tests: los que obtienen tokens con `/v1/login` o con el password grant pasan a obtenerlos con el código del login central.
- Ambientes: revocar el cliente password existente y quitar sus variables del `.env`.
- Documentación: OpenAPI, Bruno, README y `.env.example`.
