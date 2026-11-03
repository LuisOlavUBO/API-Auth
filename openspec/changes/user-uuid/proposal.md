# Proposal

## Why

Los satélites (el primero, el sistema de pago de honorarios) necesitan guardar a qué usuario de IAM pertenece cada registro. Hoy el único identificador es el `id` autonumérico, que solo tiene sentido dentro de la base de IAM: cambia si la base se recrea o migra, no coincide entre ambientes y se puede adivinar. Conviene definir un identificador estable antes de que algún satélite empiece a guardar el `id`.

## What Changes

- Cada usuario tiene un `uuid` único e inmutable, asignado al crearse. Los usuarios existentes reciben uno en la migración. El `id` autonumérico sigue siendo la clave interna.
- Los access tokens de usuario llevan una claim adicional `uuid` con el identificador del usuario. Los tokens de cliente no la llevan. `sub` no cambia: sigue siendo el `id` interno.
- La introspección devuelve `uuid` (`null` en tokens de cliente), y el perfil (`/v1/me` y `user` de introspect) incluye `uuid`.
- Regla para los satélites: guardan el `uuid` para referirse a un usuario, nunca el `id` ni `sub`.

Fuera de alcance: cambiar `sub` para que sea el `uuid`, cambios en los satélites (honorarios), quitar Spatie de los satélites y el RUT.

## Capabilities

### New Capabilities
<!-- Ninguna -->

### Modified Capabilities
- `user-authentication`: el usuario tiene un `uuid` y el perfil lo incluye.
- `scope-authorization`: los tokens de usuario llevan la claim `uuid`.
- `token-introspection`: la respuesta incluye `uuid`.

## Impact

- Base de datos: columna `uuid` en `users` (migración con relleno de los usuarios existentes).
- Código: `User`, `UserFactory`, `AccessToken`, `AccessTokenRepository`, `OAuthTokenService`, `TokenIntrospectionController`, `UserResource`.
- Documentación: esquemas `Usuario` y `TokenActivo` en `docs/openapi/` y la colección de Bruno si muestra esos campos.
- Satélites: pueden leer `uuid` del token o de introspect. Los que no lo usan no se ven afectados (solo se agregan campos).
