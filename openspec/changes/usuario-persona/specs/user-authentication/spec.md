## ADDED Requirements

### Requirement: Persona del usuario
Un usuario SHALL poder tener un `id_persona`: el identificador institucional de la persona en API-Personas, opcional y único entre los usuarios que lo tienen. Un usuario sin persona (por ejemplo, un proveedor externo) MUST quedar con `id_persona` en `null`. API-Auth MUST NOT ofrecer endpoints para escribirlo.

#### Scenario: Usuarios sin persona
- **WHEN** existen dos usuarios sin `id_persona`
- **THEN** ambos quedan guardados con `id_persona` en `null`

#### Scenario: Persona repetida
- **WHEN** se intenta guardar en un usuario el `id_persona` que ya tiene otro usuario
- **THEN** la base de datos lo rechaza y el segundo usuario queda sin cambios

#### Scenario: id_persona en la introspección
- **WHEN** un servicio introspecta el access token de un usuario con `id_persona` 1029
- **THEN** la respuesta incluye `user.id_persona` igual a `1029`

## MODIFIED Requirements

### Requirement: Perfil del usuario autenticado
El sistema SHALL devolver el perfil del dueño del token en `GET /api/auth/v1/me` (`id`, `uuid`, `id_persona`, `rut`, `name`, `email`, `email_verified_at`, `created_at`), sin exponer la contraseña. `id_persona` y `rut` son `null` si el usuario no tiene. El endpoint MUST exigir un access token de usuario con el scope `user:read`.

#### Scenario: Consulta del perfil con token válido
- **WHEN** se llama con un access token de usuario que incluye `user:read`
- **THEN** el sistema responde 200 con el perfil del usuario, incluidos su `uuid`, su `id_persona` y su `rut`, y sin el campo `password`

#### Scenario: Consulta sin token
- **WHEN** se llama sin token
- **THEN** el sistema responde 401

#### Scenario: Token sin el scope requerido
- **WHEN** se llama con un access token de usuario que no incluye `user:read`
- **THEN** el sistema responde 403
