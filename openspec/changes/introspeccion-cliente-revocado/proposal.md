# Proposal

## Why

API-IAM va a permitir revocar clientes OAuth desde el portal (cambio `clientes-revocar` de API-IAM), sobre todo como respuesta a un secreto filtrado. Con un cliente revocado, API-Auth ya no emite ni renueva tokens (Passport `findActive`) y sus propias rutas ya rechazan sus tokens (`TokenGuard`), pero la introspección sigue respondiendo `active: true` para los access tokens que el cliente obtuvo antes de la revocación, hasta que vencen (60 minutos). Las APIs que introspectan seguirían aceptando a quien tenga el secreto filtrado durante ese tiempo. Para las personas dadas de baja ya se resolvió igual: su token se reporta inactivo de inmediato.

## What Changes

- `POST /api/auth/v1/introspect` responde exactamente `{"active": false}` para un token cuyo cliente está revocado (`oauth_clients.revoked`), aunque su firma sea válida, no haya vencido y no esté revocado. Aplica a tokens de servicio (`client_credentials`) y a tokens de usuario emitidos para ese cliente.
- Sin cambios en la emisión ni en `TokenGuard`: ya rechazan clientes revocados.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `token-introspection`: se agrega que los tokens de un cliente revocado se reportan inactivos.

## Impact

- `app/Http/Controllers/Auth/TokenIntrospectionController.php` y `tests/Feature/TokenIntrospectionTest.php`.
- Sin migraciones: lee `oauth_clients.revoked`, que escribe API-IAM.
- Orden de despliegue: este cambio va **antes** que `clientes-revocar` de API-IAM para que la revocación corte de inmediato a las APIs que introspectan. Si API-IAM se despliega primero, todo funciona igual, salvo que los tokens vigentes duran hasta vencer.
- Las APIs que validan el JWT localmente sin introspectar siguen aceptando el token hasta que vence; es el mismo límite que ya tiene la baja de personas.
