# Proposal

## Why

Al probar en QA un cliente de ejemplo con `openid-client` 6 (Node), el canje con `client_secret_basic` respondió `401 invalid_client`. Esa librería arma la cabecera como exige RFC 6749 §2.3.1: `Basic base64(urlencode(client_id):urlencode(client_secret))`, así que los guiones del `client_id` (un UUID) llegan como `%2D`. Passport (league/oauth2-server, `AbstractGrant::getBasicAuthCredentials`) no decodifica esos valores y no encuentra el cliente. Con `curl -u id:secreto` (sin codificar) o con `client_secret_post` funciona. Ucampus anuncia `client_secret_basic` y podría usar una librería que codifique igual, así que hay que corregirlo antes de producción.

## What Changes

- `POST /api/auth/oauth/token` decodifica (`urldecode`) el usuario y la contraseña de `Authorization: Basic` antes de autenticar al cliente, en todos los grants (`authorization_code`, `refresh_token`, `client_credentials`).
- Las credenciales sin caracteres codificados funcionan igual que hoy (los UUID y los secretos alfanuméricos de Passport no cambian al decodificarse).
- El límite de solicitudes por `client_id` usa el id ya decodificado.

Fuera de alcance: cambios en `client_secret_post`, en otros endpoints o en el formato de error de OAuth2.

## Capabilities

### Modified Capabilities

- `openid-connect`: la autenticación del cliente al canjear acepta credenciales codificadas en `Authorization: Basic`.

## Impact

- Un middleware nuevo en `app/Http/Middleware/` que reescribe la cabecera, aplicado solo a la ruta `oauth/token` en `routes/api.php` (antes del `throttle`).
- `tests/Feature/OpenIdConnectTest.php` y la descripción de `oauth/token` en `docs/openapi/openapi.yaml`. Sin cambios en Bruno.
