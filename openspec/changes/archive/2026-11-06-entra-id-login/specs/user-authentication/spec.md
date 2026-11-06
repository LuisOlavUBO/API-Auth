## MODIFIED Requirements

### Requirement: Login con email y contraseña
El sistema SHALL emitir un par de tokens (acceso y refresh) en `POST /api/auth/v1/login` a cambio de `email` y `password` válidos, con un `scope` opcional separado por espacios. Las credenciales incorrectas MUST responder 401 con el código `invalid_grant`. Un usuario sin contraseña (creado al entrar con Microsoft) MUST NOT poder iniciar sesión con email y contraseña, ni aquí ni en el login web. El secreto del cliente OAuth usado internamente MUST NOT exponerse a quien llama.

#### Scenario: Login exitoso
- **WHEN** un usuario existente envía su email y su contraseña correctos
- **THEN** el sistema responde 200 con `token_type`, `expires_in`, `access_token` y `refresh_token`

#### Scenario: Contraseña incorrecta
- **WHEN** un usuario envía una contraseña incorrecta
- **THEN** el sistema responde 401 con `error` igual a `invalid_grant`

#### Scenario: Usuario sin contraseña
- **WHEN** se envía el email de un usuario sin contraseña con cualquier valor de `password`
- **THEN** el sistema responde 401 con `error` igual a `invalid_grant`
