## 1. Tabla y servicio

- [x] 1.1 Migración `authentication_events` (D1) y modelo con `MassPrunable` (D4)
- [x] 1.2 Servicio `AuthenticationEvents` que nunca hace fallar al que llama (D2)

## 2. Puntos de registro

- [x] 2.1 Login con clave: éxito, `credenciales`, `baja`, `clave_vencida`
- [x] 2.2 Login con Microsoft: éxito, `sin_cuenta`, `cuenta_vinculada_a_otra` (además `baja`; `sin_cuenta` no tiene punto de registro hoy: `User::fromMicrosoft` siempre crea al usuario que falta)
- [x] 2.3 Bloqueo por límite de intentos
- [x] 2.4 `invalid_client` en `oauth/token`: `cliente_inexistente`, `cliente_revocado`, `secreto_invalido`

## 3. Retención y documentación

- [x] 3.1 `model:prune` diario en el scheduler; README (tabla nueva, retención, scheduler o tarea diaria)

## 4. Tests

- [x] 4.1 Un test por escenario de la spec, incluido que ningún evento contiene la clave ni el secreto y que una falla al registrar no impide el login
- [x] 4.2 Suite completa, Pint y `openspec validate eventos-de-autenticacion --strict`
