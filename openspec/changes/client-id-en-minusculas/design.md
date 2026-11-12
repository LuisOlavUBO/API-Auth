## Context

Passport (`Bridge\ClientRepository::fromClientModel`) construye la entidad del cliente con `$model->getKey()`, y de ahí salen el primer elemento de `aud` y `sub` (`AccessToken::convertToJWT`), el `client_id` de la respuesta de token y de los payloads de refresh tokens y códigos de autorización, y el `oauth_client_id` que lee la introspección. Con SQL Server, `getKey()` devuelve el `uniqueidentifier` en mayúsculas. `App\Models\User` ya resuelve lo mismo para `uuid` con un accessor en minúsculas.

## Goals / Non-Goals

**Goals:**
- Que todo lo que API-Auth emite o responde lleve el id del cliente en minúsculas, como lo guarda y muestra API-IAM.

**Non-Goals:**
- Aceptar refresh tokens emitidos antes del cambio (ver D2).
- Cambiar cómo UBO-Common compara (ya es insensible a mayúsculas).

## Decisions

**D1. Accessor en `App\Models\Client`.** Un accessor `id` que devuelve el valor en minúsculas, igual que `User::uuid`. Como `getKey()` pasa por `getAttribute`, Passport recibe el id en minúsculas sin tocar su código ni el de league. Las consultas siguen funcionando: SQL Server compara `uniqueidentifier` sin distinguir mayúsculas, y los `update` usan el valor original del modelo.

**D2. Refresh tokens anteriores al despliegue.** league compara el `client_id` del payload del refresh token (y del código de autorización) con el de la entidad usando `!==`. Los emitidos antes del cambio llevan el id en mayúsculas y se rechazan con `invalid_grant`; el sistema pide iniciar sesión de nuevo. Se acepta: ocurre una sola vez por sesión, al desplegar, y evita sobrescribir el grant de league. Los códigos de autorización viven minutos.

## Risks / Trade-offs

- [Un inicio de sesión extra tras el despliegue] → Desplegar fuera del horario de uso; los access tokens vigentes siguen válidos hasta vencer.
- [Una API que hoy compare el id en mayúsculas] → Ninguna conocida: UBO-Common usa `strcasecmp` y API-IAM guarda el id en minúsculas.

## Migration Plan

Sin migraciones. Desplegar API-Auth; no requiere cambios en otros servicios.
