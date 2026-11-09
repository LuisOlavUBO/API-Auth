## ADDED Requirements

### Requirement: Usuarios dados de baja
Un usuario con fecha de baja (`disabled_at`) MUST NOT obtener tokens: el canje de un código del login central y la renovación con refresh token en `POST /api/auth/oauth/token` SHALL rechazarse con `error` igual a `invalid_grant`, en el formato de error de OAuth2, aunque el código o el refresh token sean válidos.

#### Scenario: Canje del código después de la baja
- **WHEN** un usuario recibió un código del login central, es dado de baja y el satélite canjea el código
- **THEN** el sistema responde con `error` igual a `invalid_grant` y no emite tokens

#### Scenario: Refresh después de la baja
- **WHEN** un usuario es dado de baja y después un satélite envía un refresh token suyo que seguía vigente
- **THEN** el sistema responde con `error` igual a `invalid_grant` y no emite tokens

### Requirement: Revocación de sesiones por un servicio
El sistema SHALL ofrecer `POST /api/auth/v1/users/{uuid}/revoke` para que un servicio cierre todas las sesiones de un usuario: revoca todos sus access y refresh tokens y cierra su sesión de IAM. Quien llama MUST autenticarse con un token de cliente que tenga el scope `sessions:revoke`. La respuesta exitosa SHALL ser 204, también cuando el usuario no tenía sesiones; un `uuid` que no corresponde a ningún usuario MUST responder 404.

#### Scenario: Revocación de un usuario con sesiones
- **WHEN** un servicio con `sessions:revoke` revoca a un usuario con tokens vigentes y sesión de IAM
- **THEN** responde 204, sus tokens quedan revocados, su refresh token ya no permite renovar y su sesión de IAM se cierra

#### Scenario: Usuario inexistente
- **WHEN** un servicio con `sessions:revoke` revoca un `uuid` que no es de ningún usuario
- **THEN** responde 404

#### Scenario: Token sin el scope
- **WHEN** se llama con un token de cliente sin `sessions:revoke`
- **THEN** responde 403

#### Scenario: Token de usuario
- **WHEN** se llama con un access token de usuario
- **THEN** responde 401
