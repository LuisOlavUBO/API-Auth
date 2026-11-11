## 1. Introspección

- [x] 1.1 En `TokenIntrospectionController::introspect`, responder `{"active": false}` si el cliente del token no está activo (D1)
- [x] 1.2 Tests en `TokenIntrospectionTest`: token de servicio y token de usuario de un cliente revocado → `{"active": false}`; tras reactivar el cliente → `active: true`

## 2. Verificación

- [x] 2.1 Suite completa, Pint y `openspec validate introspeccion-cliente-revocado --strict`
