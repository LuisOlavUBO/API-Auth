## ADDED Requirements

### Requirement: RUT del usuario
Un usuario SHALL poder tener un `rut`, opcional y único entre los usuarios que lo tienen. Se MUST guardar sin puntos, con guion y con el dígito verificador en mayúscula (`12345678-K`). El RUT MUST NOT incluirse en los access tokens.

#### Scenario: Usuario sin RUT
- **WHEN** se registran dos usuarios sin `rut`
- **THEN** ambos quedan guardados con `rut` en `null`

#### Scenario: El RUT no viaja en el token
- **WHEN** se decodifica el payload del access token de un usuario con RUT
- **THEN** el token no contiene el RUT

#### Scenario: RUT en la introspección
- **WHEN** un servicio introspecta el access token de un usuario con RUT
- **THEN** la respuesta incluye `user.rut` con el RUT del usuario

## MODIFIED Requirements

### Requirement: Registro de usuarios
El sistema SHALL permitir crear una cuenta en `POST /api/auth/v1/register` con `name`, `email` y `password` confirmada (`password_confirmation`), y opcionalmente `rut`. El email MUST ser único. El `rut`, si se envía, MUST tener un dígito verificador válido y no estar registrado, y se acepta con o sin puntos. La respuesta exitosa SHALL ser 201 con el usuario creado y su primer par de tokens (`token_type`, `expires_in`, `access_token`, `refresh_token`).

#### Scenario: Registro exitoso
- **WHEN** se envía un nombre, un email no registrado y una contraseña válida con su confirmación
- **THEN** el sistema responde 201 con `user` (incluye `id` y `email`) y un par de tokens, y el usuario queda guardado

#### Scenario: Datos de registro inválidos
- **WHEN** se envía un registro sin nombre, con un email mal formado o sin contraseña
- **THEN** el sistema responde 422 indicando los errores de `name`, `email` y `password`

#### Scenario: Registro con RUT
- **WHEN** se registra un usuario con `rut` igual a `12.345.678-5`
- **THEN** el sistema responde 201 y `user.rut` es `12345678-5`

#### Scenario: RUT con dígito verificador inválido
- **WHEN** se registra un usuario con `rut` igual a `12345678-9`
- **THEN** el sistema responde 422 indicando el error de `rut`

#### Scenario: RUT ya registrado
- **WHEN** se registra un usuario con un `rut` que ya tiene otro usuario, aunque se escriba con puntos
- **THEN** el sistema responde 422 indicando el error de `rut`

### Requirement: Perfil del usuario autenticado
El sistema SHALL devolver el perfil del dueño del token en `GET /api/auth/v1/me` (`id`, `uuid`, `rut`, `name`, `email`, `email_verified_at`, `created_at`), sin exponer la contraseña. `rut` es `null` si el usuario no tiene. El endpoint MUST exigir un access token de usuario con el scope `user:read`.

#### Scenario: Consulta del perfil con token válido
- **WHEN** se llama con un access token de usuario que incluye `user:read`
- **THEN** el sistema responde 200 con el perfil del usuario, incluidos su `uuid` y su `rut`, y sin el campo `password`

#### Scenario: Consulta sin token
- **WHEN** se llama sin token
- **THEN** el sistema responde 401

#### Scenario: Token sin el scope requerido
- **WHEN** se llama con un access token de usuario que no incluye `user:read`
- **THEN** el sistema responde 403
