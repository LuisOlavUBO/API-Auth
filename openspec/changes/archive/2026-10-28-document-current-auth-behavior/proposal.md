# Proposal

## Why

API-Auth va a convertirse en el servicio de autenticación central de los satélites (roles por aplicación, auditoría, login central con PKCE, SSO con Entra ID). Hoy su comportamiento solo está descrito en el código y en los tests, así que no hay un punto de partida contra el cual expresar esos cambios. Este cambio fija la línea base: describe el comportamiento actual tal como funciona, sin modificarlo, para que cada fase posterior se escriba como un delta sobre specs existentes.

## What Changes

- Se documenta en specs el comportamiento vigente del servicio, agrupado en siete capacidades.
- No cambia ningún comportamiento, endpoint, modelo de datos ni dependencia: no hay código que implementar.
- Las specs reflejan el estado actual aunque una fase futura lo vaya a reemplazar (por ejemplo, el rol global único por usuario en `users.role_id`); esos reemplazos se harán en cambios posteriores como requisitos modificados o eliminados.

## Capabilities

### New Capabilities

- `user-authentication`: registro, login con email y contraseña, renovación con refresh token, perfil del usuario autenticado, cierre de sesión (uno o todos) y límite de intentos.
- `scope-authorization`: catálogo de scopes en base de datos, scopes concedidos por roles a usuarios y clientes, scopes por defecto y reglas para aceptar, rechazar o descartar scopes al emitir y renovar tokens.
- `service-tokens`: emisión de tokens para servicios (client credentials) en el endpoint OAuth2 y tokens dirigidos a una API concreta, con esa API en la audiencia (`aud`).
- `token-introspection`: validación de tokens para otros servicios (al estilo RFC 7662) y publicación de la clave pública para validar JWT localmente.
- `access-administration`: comandos de consola para administrar APIs, roles, scopes y la asignación de roles a usuarios y clientes, y el catálogo base sembrado.
- `error-responses`: formato de errores RFC 9457 (problem+json) con código estable, excepción del endpoint OAuth2 y reglas de registro de errores.
- `service-operations`: prefijo público de rutas, endpoint de salud, documentación OpenAPI/Swagger y propagación de trazas.

### Modified Capabilities

(ninguna: no existen specs previas)

## Impact

- Solo se agregan archivos bajo `openspec/`. No se modifican código, rutas, migraciones, configuración ni dependencias.
- Fuente de verdad usada para redactar los requisitos: `routes/api.php`, `routes/web.php`, `app/Http/Controllers/Auth/*`, `app/Services/*`, `app/Exceptions/OAuthTokenException.php`, `app/Console/Commands/*`, `database/seeders/RoleScopeSeeder.php`, `config/oauth.php` y los tests de `tests/Feature/*`.
- Al archivar este cambio quedan creadas las specs en `openspec/specs/`, que servirán de base para el cambio de la fase 1 (roles y permisos por aplicación).
