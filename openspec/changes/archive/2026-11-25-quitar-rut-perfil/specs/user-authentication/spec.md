# Spec Delta

## MODIFIED Requirements

### Requirement: Perfil del usuario autenticado
El sistema SHALL devolver el perfil del dueño del token en `GET /api/auth/v1/me` (`id`, `uuid`, `id_persona`, `name`, `email`, `email_verified_at`, `created_at`), sin exponer la contraseña ni el RUT. `id_persona` es `null` si el usuario no tiene. El endpoint MUST exigir un access token de usuario con el scope `user:read`.

#### Scenario: Consulta del perfil con token válido
- **WHEN** se llama con un access token de usuario que incluye `user:read`
- **THEN** el sistema responde 200 con el perfil del usuario, incluidos su `uuid` y su `id_persona`, y sin los campos `password` ni `rut`

#### Scenario: Consulta sin token
- **WHEN** se llama sin token
- **THEN** el sistema responde 401

#### Scenario: Token sin el scope requerido
- **WHEN** se llama con un access token de usuario que no incluye `user:read`
- **THEN** el sistema responde 403

### Requirement: RUT del usuario
Un usuario SHALL poder tener un `rut`, opcional y único entre los usuarios que lo tienen. Se MUST guardar sin puntos, con guion y con el dígito verificador en mayúscula (`12345678-K`). El RUT MUST NOT incluirse en los access tokens ni en las respuestas de la API.

#### Scenario: Usuario sin RUT
- **WHEN** se crean dos usuarios sin `rut`
- **THEN** ambos quedan guardados con `rut` en `null`

#### Scenario: El RUT no viaja en el token
- **WHEN** se decodifica el payload del access token de un usuario con RUT
- **THEN** el token no contiene el RUT

#### Scenario: RUT en la introspección
- **WHEN** un servicio introspecta el access token de un usuario con RUT
- **THEN** la respuesta no incluye `user.rut`
