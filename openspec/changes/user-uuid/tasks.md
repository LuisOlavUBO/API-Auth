# Tasks

## 1. Columna uuid en users

- [x] 1.1 Crear con `php artisan make:migration` la migración que agrega `users.uuid` (nullable), rellena un uuid por usuario existente, la deja no nula y crea el índice único; `down` borra índice y columna. Verificar con `migrate` y `migrate:rollback` contra la base de tests en Docker (:14334).
- [x] 1.2 Agregar `HasUuids` a `User` con `uniqueIds()` = `['uuid']` y verificar en `AuthTest` que un usuario registrado tiene `uuid` y que su `id` sigue siendo numérico.
- [x] 1.3 Agregar `uuid` a `UserResource` y verificar en `AuthTest` que `/v1/me` responde el `uuid` del usuario.

## 2. Claim uuid en el token

- [x] 2.1 En `AccessTokenRepository` leer el `uuid` del usuario cuando hay `$userIdentifier` y pasarlo a `AccessToken`, que agrega la claim `uuid` solo si no es `null`. Verificar con tests (en `AuthTest` o `ClientScopesTest`, según dónde estén los de la claim `roles`) que el token de usuario lleva `uuid` y `sub` = `id`, que el token renovado lleva el mismo `uuid` y que un token de client credentials no lleva la claim.

## 3. Introspección

- [x] 3.1 Leer la claim `uuid` en `OAuthTokenService::introspect` y devolverla en `TokenIntrospectionController` (`null` en tokens de cliente). Verificar en `TokenIntrospectionTest` que un token de usuario responde `uuid` y `user.uuid`, y uno de cliente `uuid: null`.
- [x] 3.2 Actualizar `docs/openapi/openapi.yaml` (`uuid` en `Usuario` y `TokenActivo`, aclarando que los satélites guardan `uuid` y no `sub`) y verificar que `DocsTest` pasa.

## 4. Cierre

- [x] 4.1 Correr `vendor/bin/pint --dirty --format agent` y la suite completa con la base de tests en Docker, y verificar que todo pasa.

## Workflow follow-up

- Archivar el cambio con `/opsx:archive` una vez revisado y desplegado.
