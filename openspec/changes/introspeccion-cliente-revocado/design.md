## Context

`TokenIntrospectionController::introspect` valida el token con el `ResourceServer` (firma, vencimiento y `oauth_access_tokens.revoked`), carga el registro del token y, si es de un usuario dado de baja, responde `{"active": false}`. No mira el cliente. Passport ya usa `ClientRepository::findActive` al emitir y renovar tokens y en `TokenGuard`, así que un cliente revocado no obtiene tokens nuevos ni pasa las rutas de API-Auth; solo la introspección le da por buenos los tokens que ya tenía. `oauth_clients` la escribe API-IAM.

## Goals / Non-Goals

**Goals:**
- Que la revocación de un cliente corte de inmediato sus tokens en las APIs que introspectan.

**Non-Goals:**
- Revocar en `oauth_access_tokens` los tokens del cliente (no hace falta: la introspección lo resuelve y la reactivación vuelve a dejarlos válidos).
- Las APIs que validan el JWT localmente.

## Decisions

**D1. Chequeo junto al de la persona dada de baja.** Después de cargar el token, si su cliente (`$claims['client_id']`) no está activo según `Passport::client()` / `ClientRepository::findActive`, responder `{"active": false}`. Es una consulta por introspección, la misma forma que el chequeo de `isDisabled()`.

**D2. Reactivable.** Como no se toca `oauth_access_tokens`, si API-IAM reactiva el cliente, sus tokens que no vencieron vuelven a reportarse activos. Es lo esperado de una reactivación (se usa para deshacer una revocación por error).

## Risks / Trade-offs

- [Una consulta más por introspección] → Busca por clave primaria en `oauth_clients`; despreciable frente al resto de la introspección.

## Migration Plan

Sin migraciones. Desplegar antes del cambio `clientes-revocar` de API-IAM.
