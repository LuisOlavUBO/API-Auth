# Design

## Context

`users.id` es autonumérico y Passport lo usa como identificador del usuario: va en `sub` y en `oauth_access_tokens.user_id`. `AccessToken` ya arma el JWT a mano para agregar `aud` y `roles`, y `OAuthTokenService` lee las claims propias del JWT para introspect. Ver proposal.md para el motivo.

## Goals / Non-Goals

**Goals:**
- Columna `uuid` en `users`, llena para todos los usuarios, única y no nula.
- Claim `uuid` en los tokens de usuario, sin tocar cómo Passport identifica al usuario.

**Non-Goals:**
- Buscar usuarios por `uuid` en algún endpoint.
- Impedir por código que se edite el `uuid`: basta con que ningún flujo lo modifique y que no sea asignable en masa.

## Decisions

- **`id` sigue siendo la clave primaria; `uuid` es una columna aparte.** En SQL Server un `uniqueidentifier` como clave clustered fragmenta los inserts, y cambiar la clave obligaría a tocar todas las FK (`role_user`, tokens de Passport, `sessions`). Alternativa descartada: `uuid` como clave primaria.
- **`sub` no cambia; el `uuid` va en una claim propia.** Poner el `uuid` en `sub` exige cambiar cómo Passport identifica al usuario (UserRepository, `user_id` de los tokens y `$request->user()`). Alternativa descartada por costo y riesgo, sin beneficio hoy.
- **Se genera en Laravel con `HasUuids` y `uniqueIds()` = `['uuid']`.** Así el modelo lo tiene al crearse y la clave primaria sigue siendo autonumérica (el trait solo deja de incrementar si la clave está en `uniqueIds()`). Alternativa descartada: `DEFAULT NEWID()` en la base, porque el modelo no lo vería sin volver a consultarlo.
- **Tipo de columna: `$table->uuid()`** (`uniqueidentifier` en SQL Server), con índice único. SQL Server lo devuelve en mayúsculas y Laravel lo genera en minúsculas, así que `User` lo expone siempre en minúsculas (accessor) para que el token, `/v1/me` e introspect den el mismo texto.
- **La claim se agrega en `AccessTokenRepository`:** cuando hay `$userIdentifier`, lee el `uuid` del usuario y se lo pasa a `AccessToken`, que agrega `uuid` solo si no es `null`. Cubre login, refresh y Authorization Code, porque todos pasan por `getNewToken`. Alternativa descartada: calcularlo en `TokenAudience` junto con los roles, que mezcla dos responsabilidades.
- **Introspect lee `uuid` del JWT**, igual que `roles` (`OAuthTokenService`), y `UserResource` agrega `uuid`, con lo que `/v1/me` y `user` de introspect lo incluyen.

## Risks / Trade-offs

- [Tokens emitidos antes del despliegue no tienen la claim `uuid`] → introspect responde `uuid: null` para ellos; el `user.uuid` sí viene. Desaparecen solos al vencer (60 min, o al renovar).
- [Relleno de usuarios existentes en SQL Server] → la migración agrega la columna nullable, asigna un uuid por usuario y después la deja no nula con índice único, todo en la misma migración.

## Migration Plan

1. Desplegar con la migración (ver `deployments` en CLAUDE.md). La migración rellena los usuarios existentes.
2. Rollback: la migración `down` borra el índice y la columna. Los satélites aún no guardan el `uuid`, así que no hay datos que perder fuera de IAM.
