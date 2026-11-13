## 1. Cliente

- [x] 1.1 Accessor `id` en minúsculas en `App\Models\Client` (D1)
- [x] 1.2 Tests: token de cliente pedido con el id en mayúsculas (`aud[0]`, `sub`), token dirigido a una API, renovación con refresh token e introspección (`client_id`, `aud[0]`), todo con el id leído de SQL Server

## 2. Verificación

- [x] 2.1 Suite completa, Pint y `openspec validate client-id-en-minusculas --strict`
