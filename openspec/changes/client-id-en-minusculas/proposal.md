# Proposal

## Why

SQL Server devuelve los `uniqueidentifier` en mayúsculas, y Passport arma el cliente de cada token con el id tal como lo lee de `oauth_clients`. Por eso el `client_id` de los tokens sale en mayúsculas (`01A1CC54-…`) en la claim `aud`, en `sub` de los tokens de cliente, en la introspección y en la respuesta de token, mientras API-IAM, el portal y los `.env` de los sistemas lo usan en minúsculas. UBO-Common compara sin distinguir mayúsculas, pero cualquier API que compare `aud` o `client_id` de forma exacta rechazaría tokens válidos. Las personas ya tienen esta regla: `User::uuid` se devuelve siempre en minúsculas.

## What Changes

- El id de un cliente OAuth se usa siempre en minúsculas: en `aud` (primer elemento), en `sub` de los tokens de cliente, en `client_id` de la introspección y de los tokens y en lo que se guarda al emitir.
- Los refresh tokens emitidos antes del despliegue llevan el id en mayúsculas y league los compara de forma exacta: se rechazan una vez y la persona vuelve a iniciar sesión (ver design).
- Sin cambios en la emisión de scopes, en las rutas ni en la base.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `service-tokens`: el `client_id` de los tokens va en minúsculas.
- `token-introspection`: el `client_id` y `aud` de la respuesta van en minúsculas.

## Impact

- `app/Models/Client.php` y tests de `ServiceTokensTest`/`TokenIntrospectionTest` (o los que cubran emisión e introspección).
- Sin migraciones ni dependencias. API-IAM y UBO-Common no cambian.
- Al desplegar, las sesiones con un refresh token anterior piden volver a iniciar sesión una vez; los access tokens vigentes siguen sirviendo hasta vencer (60 minutos).
