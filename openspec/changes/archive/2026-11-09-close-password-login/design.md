# Design

## Context

`/v1/login` y `/v1/refresh` (`AuthController`) llaman a `OAuthTokenService`, que ejecuta en el mismo proceso el password grant y el refresh de Passport con un cliente interno (`config('oauth.password_client')`). Sus rechazos se convierten en `OAuthTokenException` (problem+json). El password grant está activo con `Passport::enablePasswordGrant()`, así que cualquier cliente de tipo password también puede usarlo en `oauth/token`. `ScopeRepository` deja pasar los scopes por defecto (`config('oauth.default_scopes')`) que `OAuthTokenService` envía cuando el login no trae `scope`. Muchos tests obtienen tokens de usuario por estos caminos. Ver proposal.md para el motivo.

## Goals / Non-Goals

**Goals:**
- Que no exista ninguna forma de obtener tokens de usuario enviando la contraseña a la API.
- Que los tests sigan cubriendo los scopes, los roles y el refresh por el único camino que queda: el código del login central.

**Non-Goals:**
- Cambiar `/v1/me`, `/v1/logout` y `/v1/logout-all`.
- Clientes públicos o SPA.

## Decisions

1. **Rutas y controlador:** se quitan `v1/login` y `v1/refresh` de `routes/api.php` (404 por la ruta inexistente), los métodos `login` y `refresh` de `AuthController` y `LoginRequest`. El limitador `auth` se queda: lo usa el login web.
2. **Password grant apagado:** se quita `Passport::enablePasswordGrant()`. Passport ya no registra el grant y `oauth/token` responde `unsupported_grant_type`. Es lo que cierra la puerta para cualquier cliente, no solo para el interno.
3. **`OAuthTokenService`:** se quitan `issueForCredentials`, `refresh` y `requestToken`, y se borra `OAuthTokenException`, que solo usaba `requestToken`. Se quedan `introspect`, `revoke` y `revokeAllFor`. Se ajusta el comentario de `bootstrap/app.php` que la menciona.
4. **Configuración:** se quitan `oauth.password_client` y `oauth.default_scopes`, y las variables `PASSPORT_PASSWORD_CLIENT_*` de `.env.example`. `ScopeRepository` deja de eximir los scopes por defecto: un scope pedido que los roles no conceden siempre se rechaza.
5. **`oauth:client`:** se quita el tipo `password` de la firma y de la validación. Un cliente password ya creado deja de servir en cuanto se apaga el grant; en cada ambiente se revoca a mano (ver Migration Plan).
6. **Tests:** los ayudantes del flujo con código de `CentralLoginTest` (`challenge`, `authorizeUrl`, `queryOf`, `exchange` y `tokensFor`) pasan a `Tests\TestCase`, que ya tiene `createClient`. `tokensFor` recibe el usuario, el cliente y un `scope` opcional. Los tests que usan `/v1/login` o el password grant (`AuthTest`, `ApplicationRolesTest`, `TokenIntrospectionTest`, `ProblemDetailsTest`) obtienen sus tokens así y refrescan en `oauth/token`. `ServiceConfigurationTest` prueba el proxy de confianza con el login web, que tiene el mismo limitador. Se borran los tests de lo que desaparece: login y refresh de la API, scopes por defecto, cliente interno rechazado y límite de `/v1/login`.
7. **Scope inválido en el login central:** las especificaciones solo exigen que no se emitan tokens y que el error sea `invalid_scope`, porque el rechazo puede llegar al satélite como redirección o en el canje del código. Los tests verifican ese resultado, no el camino.

## Risks / Trade-offs

- [Clientes que usan `/v1/login` o el password grant] Dejan de funcionar al desplegar. Antes de desplegar se revisa en `oauth_access_tokens` si algún cliente distinto del interno emitió tokens con el grant password en los últimos días; si hay alguno, se avisa a su dueño.
- [Pruebas manuales] Bruno y curl ya no obtienen tokens de usuario con email y contraseña. Bruno usa la solicitud **Login central** (OAuth2 con PKCE) que ya existe.
- [Rechazos en el log] Hoy no hay un test de que `oauth/token` no registre como error un refresh token reutilizado. Si el test nuevo muestra que sí lo registra, se pausa y se decide antes de cambiar el manejo de errores.

## Migration Plan

1. Desplegar la versión nueva.
2. En cada ambiente, revocar el cliente password (`UPDATE oauth_clients SET revoked = 1 WHERE id = <PASSPORT_PASSWORD_CLIENT_ID>`) y quitar `PASSPORT_PASSWORD_CLIENT_ID/SECRET` del `.env`. Hacerlo después del despliegue evita romper la versión anterior mientras sigue corriendo.
3. Rollback: volver a la imagen anterior y reactivar el cliente (`revoked = 0`) con sus variables.
