## ADDED Requirements

### Requirement: Identificador público del usuario
Todo usuario SHALL tener un `uuid` único, asignado al crearse, que no cambia nunca. Es el identificador con que los demás sistemas se refieren al usuario; el `id` numérico es interno de API-Auth y los satélites MUST NOT guardarlo.

#### Scenario: Usuario nuevo
- **WHEN** se registra un usuario
- **THEN** el usuario tiene un `uuid` distinto al de cualquier otro usuario

#### Scenario: El uuid no cambia
- **WHEN** un usuario actualiza su sesión, sus roles o su perfil
- **THEN** su `uuid` sigue siendo el mismo

## MODIFIED Requirements

### Requirement: Perfil del usuario autenticado
El sistema SHALL devolver el perfil del dueño del token en `GET /api/auth/v1/me` (`id`, `uuid`, `name`, `email`, `email_verified_at`, `created_at`), sin exponer la contraseña. El endpoint MUST exigir un access token de usuario con el scope `user:read`.

#### Scenario: Consulta del perfil con token válido
- **WHEN** se llama con un access token de usuario que incluye `user:read`
- **THEN** el sistema responde 200 con el perfil del usuario, incluido su `uuid`, y sin el campo `password`

#### Scenario: Consulta sin token
- **WHEN** se llama sin token
- **THEN** el sistema responde 401

#### Scenario: Token sin el scope requerido
- **WHEN** se llama con un access token de usuario que no incluye `user:read`
- **THEN** el sistema responde 403
