# Proposal

## Why

Hoy un usuario tiene un solo rol global (`users.role_id`), así que sus permisos son los mismos sin importar a qué sistema entre. API-Auth va a ser el servicio de autenticación central de los satélites (ver el documento de diseño "IAM Central UBO"), y para eso cada sistema debe definir sus propios roles y cada persona debe poder tener roles distintos en cada uno. Este cambio construye ese modelo sin tocar aún el login web, PKCE ni Entra ID, y se puede probar con el `/v1/login` y el M2M que ya existen.

## What Changes

- Las **APIs** pasan a ser **aplicaciones**: la tabla `apis` se renombra a `applications` y `oauth_scopes.api_id` a `application_id`, conservando las filas y los nombres actuales (`api-auth`, `api-maestros`). Una aplicación puede tener permisos, roles y clientes OAuth.
- Cada **rol pertenece a una aplicación** (`roles.application_id`) y puede conceder permisos de varias. El nombre del rol es único dentro de su aplicación, ya no global.
- Cada **cliente OAuth pertenece a una aplicación** (`oauth_clients.application_id`, obligatorio).
- Un usuario puede tener **varios roles** (`role_user`, reemplaza `users.role_id`).
- **Tokens de usuario:** los permisos salen de los roles del usuario que pertenecen a la aplicación del `client_id`. Llevan la claim `roles` y un `aud` con el `client_id` primero y las aplicaciones de sus permisos después. Sin `scope` reciben todos los permisos de esos roles; con `scope`, solo lo pedido.
- **Refresh:** `scopes` = los del token anterior que los roles actuales aún conceden (quita, nunca agrega); `roles` se recalculan desde los roles actuales.
- **Tokens M2M:** sin cambios de comportamiento, salvo que los roles de un cliente pueden ser de cualquier aplicación y la claim `roles` lleva solo los que conceden permisos en la API pedida.
- La **introspección** devuelve `roles`.
- **Comandos de administración:** los comandos `oauth:*` reciben la aplicación; nuevos `oauth:client` (crear clientes con su aplicación, porque `passport:client` no la conoce), `oauth:client-application` (mover un cliente a otra aplicación) y `oauth:role-scopes` (listar los permisos de los roles de una aplicación).
- Los comandos cambian: `oauth:api` pasa a `oauth:application`; `oauth:scope --api` pasa a `--application`; `oauth:role` y `oauth:assign-role` exigen la aplicación; `oauth:assign-role` agrega un rol (con `--sync` reemplaza los del usuario en esa aplicación). `/v1/login` no cambia de comportamiento.
- Sin `scope`, `/v1/login` sigue entregando `user:read`; los scopes por defecto pasan de ser una configuración global de Passport a ser del login de IAM.

Fuera de alcance: login web, `/oauth/authorize` y PKCE, Microsoft Entra ID, logout central, cambio de los tiempos de sesión, el módulo de UBO.Common y la baja de usuarios.

## Capabilities

### New Capabilities

Ninguna. El modelo de aplicaciones vive dentro de las capacidades de permisos, tokens y administración que ya existen.

### Modified Capabilities

- `scope-authorization`: los scopes pertenecen a una aplicación; los roles pertenecen a una aplicación y un usuario tiene varios; los permisos de un token de usuario salen de los roles de la aplicación del cliente; sin `scope` se entregan todos; el refresh recalcula roles y solo quita scopes.
- `service-tokens`: el parámetro `api` nombra una aplicación; los roles de un cliente pueden ser de cualquier aplicación; los tokens llevan la claim `roles`; los tokens de usuario pueden tener varias aplicaciones en `aud`.
- `token-introspection`: la respuesta de un token activo incluye `roles`.
- `access-administration`: comandos de aplicaciones, roles, permisos, clientes y asignación de roles con su aplicación; consulta de los permisos de los roles de una aplicación; catálogo base sembrado con `api-auth` como aplicación.
- `user-authentication`: el rol por defecto al registrarse es un rol de `api-auth` asignado con `role_user`.

## Impact

- **Base de datos:** migración que renombra `apis` y `oauth_scopes.api_id`, agrega `roles.application_id` (índice único con `name`), `oauth_clients.application_id` (obligatorio) y `role_user`, y elimina `users.role_id` y el único global de `roles.name`. Migra los datos actuales: los roles existentes y todos los clientes existentes pasan a `api-auth` (para un cliente M2M su aplicación no cambia lo que recibe; después se mueven con `oauth:client-application`). Sin cascadas hacia `applications`, por las restricciones de SQL Server.
- **Código:** `Api` → `Application`, `Role`, `User`, `OAuthScope`, `ScopeRepository`, `TokenAudience`, `AccessTokenRepository`, `AccessToken`, `OAuthTokenService`, `TokenIntrospectionController`, `AppServiceProvider` (scopes por defecto), `AuthController::register`, `RoleScopeSeeder`, factories y los comandos `oauth:*`.
- **Documentación:** `docs/openapi`, `docs/bruno` y el README.
- **Tests:** los tests que crean clientes con `ClientRepository` necesitan indicar su aplicación; se agregan tests de los casos nuevos.
- **Despliegue:** hay que migrar el SQL Server de producción. Los consumidores (UBO.Common) no cambian: siguen validando `aud` y `scopes`, y el `client_id` sigue en `aud[0]`.
- **Dependencias:** ninguna nueva.
