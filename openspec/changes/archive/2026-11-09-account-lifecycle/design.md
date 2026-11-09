# Design

## Context

Después de `close-password-login`, los tokens de usuario se emiten solo por dos grants de `oauth/token`: el canje del código del login central y el refresh. Los dos pasan por `ScopeRepository::finalizeScopes` con el id del usuario. La contraseña solo se escribe en el login web, que usa `Auth::attempt` en `LoginController` y el de Microsoft `User::fromMicrosoft`. `oauth/authorize` ya tiene los middleware `RequirePkce` y `RequireRolesInClientApplication`. Las sesiones de IAM están en la tabla `sessions`, que tiene `user_id`. Ver proposal.md para el motivo.

## Goals / Non-Goals

**Goals:**
- Que una baja o una contraseña temporal se respeten en todos los caminos que entregan tokens.
- Que API-IAM pueda cerrar todas las sesiones de un usuario sin escribir en las tablas de API-Auth.

**Non-Goals:**
- Una forma de dar de baja o de poner contraseñas temporales en API-Auth (ni comando ni endpoint): eso es de API-IAM.
- Recuperar la contraseña olvidada, o cambiarla sin que sea temporal.

## Decisions

1. **Dos columnas nullable en `users`:** `disabled_at` y `temporary_password_expires_at`, con métodos en `User` (`isDisabled()`, `hasTemporaryPassword()`, `temporaryPasswordExpired()`). Si `temporary_password_expires_at` no es nulo, la contraseña es temporal y vence en esa fecha. Una sola columna alcanza para "debe cambiarla" y "vence"; no hace falta un booleano aparte.
2. **Baja en `ScopeRepository::finalizeUserScopes`:** si el usuario está dado de baja, lanza `OAuthServerException::invalidGrant()`. Es el único punto que recorren los dos grants, así que una sola verificación cubre el canje de código y el refresh. `oauth/token` la responde con el formato OAuth2.
3. **Login web:** `LoginController::store` valida con `Auth::validate` y toma el usuario con `Auth::getLastAttempted()`. Dado de baja → el mismo error que credenciales incorrectas, para no revelar que la cuenta existe. Contraseña temporal vencida → "Tu contraseña temporal venció, contacta a soporte". En otro caso, `Auth::login`. Si la contraseña es temporal, redirige a `password` en lugar de a `intended`; el destino pendiente se conserva en la sesión.
4. **Login con Microsoft:** si `fromMicrosoft` devuelve un usuario dado de baja, `failed('No tienes acceso, contacta a soporte.')`.
5. **Middleware `RequireActiveAccount` en `oauth/authorize`**, entre `RequirePkce` y `RequireRolesInClientApplication`. Con una persona con sesión: si está dada de baja, cierra la sesión y responde la vista de "Sin acceso" con 403; si su contraseña es temporal, `redirect()->guest(route('password'))`, que guarda la solicitud como destino. Un invitado pasa, igual que hoy. El cambio se exige aunque la persona haya entrado con Microsoft: una contraseña temporal es un secreto que conoce el administrador, y no debe seguir sirviendo.
6. **Página de cambio:** `PasswordController` con `GET/POST /api/auth/password` (middleware `auth`, ruta en `ApiPrefix::WEB_PAGES`), vista `auth/password`. Valida `password` como `required`, `confirmed`, `Password::defaults()` y distinta de la actual (`! Hash::check`). Guarda la contraseña, pone `temporary_password_expires_at` en `null`, regenera la sesión y hace `redirect()->intended(route('login'))`. Sin contraseña temporal, la página redirige al login: cambiar una contraseña normal queda fuera de alcance.
7. **Revocación:** `POST /api/auth/v1/users/{uuid}/revoke` con `EnsureClientIsResourceOwner::using('sessions:revoke')`, igual que `introspect`. Busca por `uuid` (`firstOrFail`, 404 en Problem Details), llama a `OAuthTokenService::revokeAllFor` y borra las filas de `sessions` con su `user_id` en la conexión y tabla de `config/session.php`. Responde 204. Es idempotente: revocar dos veces da 204.
8. **Introspección:** si el token es de un usuario dado de baja, responde `{"active": false}`. Es lo único que hace falta para que las APIs que validan con introspect corten el acceso de inmediato. Las que validan solo la firma lo cortan cuando el access token vence (60 minutos), salvo que API-IAM también llame a la revocación.
9. **Datos:** `RoleScopeSeeder` agrega el scope `sessions:revoke` de `api-auth` y un rol `session-revoker` que lo concede, igual que `introspect`/`introspector`.

## Risks / Trade-offs

- [Columnas que nadie escribe todavía] Hasta que exista API-IAM, `disabled_at` y la contraseña temporal solo se usan en tests. Se acepta: el contrato queda listo y probado antes de que API-IAM lo use.
- [Validación de firma local] Una API que no introspecta sigue aceptando el token de un usuario dado de baja hasta que vence. Por eso API-IAM debe revocar al dar de baja; la revocación tampoco lo corta en esa API (el JWT sigue siendo válido), así que el límite real es la vigencia de 60 minutos.
- [Migraciones de `users` en API-Auth] Por ahora la migración vive aquí. Se mueve a API-IAM en el segundo cambio, junto con las del catálogo.

## Migration Plan

1. Desplegar con la migración: agrega dos columnas nullable sin tocar filas, así que ningún usuario queda dado de baja ni con contraseña temporal.
2. Crear el scope en cada ambiente con `php artisan oauth:scope sessions:revoke "..." --application=api-auth --role=api-auth/<rol>`.
3. Rollback: `down` borra las dos columnas.
