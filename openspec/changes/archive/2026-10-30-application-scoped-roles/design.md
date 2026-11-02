# Design

## Context

Ver `proposal.md` para el motivo. Estado actual relevante:

- `apis` ← `oauth_scopes.api_id` (con llave foránea); `roles.name` único global; `users.role_id` (un rol por usuario); `oauth_client_role` une clientes y roles. `oauth_clients` es de Passport y no tiene relación con nada.
- league/oauth2-server llama a `ScopeRepository::finalizeScopes()` y después a `AccessTokenRepository::getNewToken()` sin pasar nada entre ambos; `TokenAudience` es el canal que ya usa el token M2M por API para llevar la API al JWT.
- Passport sustituye "no se pidió `scope`" por `Passport::defaultScopes()` (hoy `['user:read']`) antes de que `finalizeScopes` lo vea. En el refresh, league valida los scopes pedidos contra los del refresh token anterior antes de llamar a `finalizeScopes`.
- `ClientRepository::create()` de Passport es protegido e inserta sin `application_id`.
- SQL Server: no se puede alterar una columna con una restricción que dependa de ella (ya ocurre en la migración de `api_id`), y rechaza llaves con cascada por varias rutas.

## Goals / Non-Goals

**Goals:** que los permisos de un token de usuario dependan de la aplicación del cliente; migrar los datos actuales sin cambiar lo que reciben hoy los usuarios y clientes existentes; que `/v1/login` y el M2M se comporten igual que ahora.

**Non-Goals:** login web, PKCE, Entra ID, baja de usuarios, tope absoluto de sesión, un modelo `Client` propio (no hace falta hasta el consentimiento de la entrega 2).

## Decisions

**1. Una tabla `applications` en vez de `systems` + `apis`.** Se renombra `apis`; las filas y los nombres (`api-auth`, `api-maestros`) se conservan para no romper el `aud` ni `UBO_AUTH_API` de lo desplegado. La clase `Api` pasa a `Application`. Alternativa descartada: crear `systems` aparte, que dejaba cada aplicación que valida y consume registrada dos veces.

**2. El cliente determina la aplicación.** `oauth_clients.application_id` obligatorio. `ScopeRepository` lo lee con una consulta por el id del cliente (la entidad de league solo trae el id), sin reemplazar el modelo de cliente de Passport. Alternativa descartada: parámetro `system=` en la solicitud, falsificable.

**3. Detectar "sin scope" con la lista vacía.** Se deja `Passport::defaultScopes([])`, de modo que una solicitud sin `scope` llega a `finalizeScopes` como lista vacía, también en el canje de un código de autorización, donde la solicitud original ya no existe. Los scopes por defecto pasan a ser solo de `/v1/login`: `OAuthTokenService` los envía explícitamente cuando el cliente no manda `scope`, y la exención "un scope por defecto que los roles no conceden se descarta" lee `config('oauth.default_scopes')`. Esto reemplaza la idea inicial de mirar la solicitud HTTP, que no sirve para el canje del código. Consecuencia decidida: un cliente M2M que no manda `api` ni `scope` recibe un token sin scopes. Antes recibía `user:read` si sus roles lo concedían, solo por el valor por defecto; la spec ya decía que un cliente sin scopes pedidos recibe un token sin scopes.

**4. Cálculo de scopes y roles en `finalizeScopes`, por grant.**

| Grant | Scopes | Roles |
|---|---|---|
| password / authorization_code (usuario) | roles del usuario en la aplicación del cliente: lista vacía → todos los que conceden; con lista → solo lo pedido, fuera de ellos `invalid_scope` | todos los roles del usuario en esa aplicación |
| refresh_token | los del token anterior ∩ los permitidos hoy (nunca la lista vacía → todos) | recalculados igual que arriba |
| client_credentials | como hoy, con o sin `api` | roles del cliente que conceden al menos un scope del token |

Después de decidir los scopes se calcula la audiencia (aplicaciones dueñas de los scopes en un token de usuario; la API pedida en un token M2M con `api`; nada en un token M2M sin `api`) y se deja con los roles en `TokenAudience`, que pasa de guardar una API a guardar la audiencia y los roles. `AccessToken` los vuelca con `permittedFor(client_id, ...)` y la claim `roles`. El `client_id` sigue primero.

**5. Roles del usuario por `role_user`.** `User::roles()` es `belongsToMany`; `User::allowedScopeNames($applicationId)` reemplaza a la versión con rol único. Los roles de una aplicación con más de un usuario no tienen cascada hacia `applications`: borrar una aplicación con roles o clientes falla, que es lo que se quiere.

**6. Referencia a roles en la consola: `<aplicación>/<rol>`.** Como el nombre ya no es único, los comandos reciben siempre ese formato, resuelto por un trait compartido (`ResolvesRoles`) junto a `ResolvesOAuthClient`. Alternativa descartada: aceptar el nombre solo cuando es único, que cambiaría el significado de un comando al crear un rol homónimo.

**7. Crear clientes con aplicación.** Un método `Application::createClient()` inserta el cliente con `forceCreate` y los mismos atributos que `ClientRepository` (secreto aleatorio, `grant_types`, `redirect_uris`, `revoked`), reutilizando el modelo de Passport para el hash y `plainSecret`. Lo usan `oauth:client` y los tests. `passport:client` queda inutilizable: falla por la columna obligatoria.

**8. Introspección.** `OAuthTokenService::introspect()` lee `roles` del JWT, como ya hace con `aud`, así no consulta la base y dice lo mismo que el token.

**9. Migración (una sola, sin cascada hacia `applications`).** En este orden:
1. Quitar la llave foránea de `oauth_scopes.api_id`, renombrar la columna a `application_id` y la tabla `apis` a `applications`, y recrear la llave.
2. Si existen roles o clientes, crear `api-auth` cuando falte (los tests parten de una base vacía y la siembra la crea).
3. `roles`: agregar `application_id` (nulo), asignar `api-auth` a todos, hacerla obligatoria, crear la llave y el índice único `(application_id, name)` y quitar el único de `name`.
4. `oauth_clients`: agregar `application_id`, asignar `api-auth` a todos los clientes, obligatoria, llave. Un cliente M2M no cambia lo que recibe por esto; se mueve después con `oauth:client-application`.
5. Crear `role_user`, copiar `users.role_id` y quitar su llave y la columna.

Cada paso separa "agregar nulo", "completar" y "hacer obligatoria" porque SQL Server no deja alterar una columna con restricciones. `down()` revierte la estructura; un usuario con varios roles conserva solo el primero.

## Risks / Trade-offs

- [Renombrar tabla y columna con llave en SQL Server deja nombres de restricciones antiguos o falla] → Probar `up()` y `down()` en el SQL Server de Docker con los seeders y con datos creados antes de migrar; nunca contra `10.16.2.138` primero.
- [El `aud` de un token de usuario ahora incluye `api-auth` cuando tiene `user:read`, y antes solo el `client_id`] → Es compatible con UBO.Common (comprueba que `aud` contenga su API y lee el `client_id` de `aud[0]`); queda en la spec y en un test.
- [Agregar un rol hace aparecer su nombre en `roles` antes que sus scopes] → Es lo acordado: las APIs deciden por `scopes`, así que falla de forma segura; los scopes nuevos llegan en el siguiente login.
- [Los comandos `oauth:*` cambian de nombre y de formato] → Es la primera versión y nadie usa los anteriores; se actualizan README, OpenAPI y la colección de Bruno.
- [Desplegar en producción] → La migración se aplica con la versión nueva de la imagen y los clientes existentes quedan en `api-auth`, igual que hoy en la práctica.

## Migration Plan

1. Migrar y probar en Docker (`DB_PORT=14334`) con datos previos.
2. Subir la versión nueva de la imagen y migrar el servidor de desarrollo cuando el usuario lo pida.
3. Reasignar con `oauth:client-application` los clientes que no sean de `api-auth` (p. ej. el de API-Maestros).
4. Reversa: `migrate:rollback` del último lote y volver a la imagen anterior.
