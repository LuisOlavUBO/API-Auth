# Proposal

## Why

Ucampus, el sistema de gestión académica, se integra con su proveedor de identidad por OpenID Connect (antes lo hizo con Entra ID) y necesita que las personas de la UBO, sobre todo los alumnos, que usan Google, entren con el login central. Passport es solo OAuth2: no entrega `id_token`, discovery ni llaves en formato JWK. Este cambio agrega un OIDC mínimo sobre el login central para que Ucampus, y cualquier cliente OIDC estándar, identifique a la persona por su `id_persona`.

## What Changes

- **Scopes `openid`, `profile` y `email`:** los acepta la autorización con código. No son permisos: no salen del catálogo ni de los roles, y no cambian los scopes de permisos del token.
- **`id_token`** en la respuesta de `oauth/token` cuando se concedió `openid` (canje del código y renovación): JWT RS256 firmado con la llave de Passport, con `iss`, `sub` (el `uuid`), `aud` (el cliente), `exp`, `iat`, `nonce` (si se envió) e `id_persona` (si el usuario tiene), más `name` con `profile`, y `email` y `email_verified` con `email`. El RUT no va.
- **`nonce`:** el que llega a `oauth/authorize` vuelve en el `id_token` del canje de ese código.
- **Discovery** en `GET /api/auth/.well-known/openid-configuration` y **llaves** en `GET /api/auth/.well-known/jwks.json` (la misma llave pública de `v1/public-key`, como JWK).
- **`GET /api/auth/oauth/userinfo`** con un access token de usuario con `openid`: `sub`, `id_persona` y los datos de los scopes concedidos.
- **Logout de OIDC:** `GET /api/auth/logout` acepta además `id_token_hint`, `post_logout_redirect_uri` y `state`, con la misma regla de origen que el `return` actual, y se anuncia como `end_session_endpoint`.
- PKCE sigue obligatorio. La autenticación del cliente en el canje es `client_secret_basic` o `client_secret_post`.

Fuera de alcance: flujos implicit e hybrid, registro dinámico de clientes, `private_key_jwt`, el parámetro `claims`, `max_age`, `acr` y `auth_time`, logout por front-channel o back-channel, `sub` distinto por cliente, cifrado del `id_token`, `iss` en los access tokens, registro de las `post_logout_redirect_uri` exactas, el cliente de Ucampus en IAM (lo crea API-IAM con sus `redirect_uri`) y la certificación OpenID.

## Capabilities

### New Capabilities
- `openid-connect`: discovery, llaves JWK, `id_token`, `nonce`, `userinfo` y logout de OIDC sobre el login central.

### Modified Capabilities
- `scope-authorization`: `openid`, `profile` y `email` existen fuera del catálogo, solo en tokens de usuario, y no cuentan como scopes de permisos.

## Impact

- Código: un `ResponseType` propio que agrega el `id_token` (registrado con `Passport::useAuthorizationServerResponseType`), un `AuthCodeRepository` propio que guarda el `nonce`, `ScopeRepository` (scopes de OIDC), `TokenAudience` (lleva el `nonce`), controladores de discovery, llaves y `userinfo`, y `LogoutController`.
- Rutas: `.well-known/openid-configuration`, `.well-known/jwks.json` y `oauth/userinfo` bajo `/api/auth`.
- Configuración: el `issuer` sale de `APP_URL`, que debe ser la URL pública.
- Documentación: `docs/openapi/` (las tres rutas, el `id_token` en `oauth/token` y los parámetros del logout), `docs/bruno/` (discovery, llaves y `userinfo`) y README (cómo integrar un cliente OIDC).
- Depende de `usuario-persona` (`users.id_persona`), que debe archivarse antes.
