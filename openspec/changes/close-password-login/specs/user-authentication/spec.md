## ADDED Requirements

### Requirement: Sin login por la API
Las personas SHALL obtener tokens solo a través del login central. `POST /api/auth/v1/login` y `POST /api/auth/v1/refresh` SHALL responder 404, y `POST /api/auth/oauth/token` MUST rechazar `grant_type=password` con `error` igual a `unsupported_grant_type`, para cualquier cliente.

#### Scenario: Login por la API
- **WHEN** se envía `POST /api/auth/v1/login` con el email y la contraseña correctos de un usuario
- **THEN** el sistema responde 404 y no emite tokens

#### Scenario: Refresh por la API
- **WHEN** se envía `POST /api/auth/v1/refresh` con un refresh token vigente
- **THEN** el sistema responde 404

#### Scenario: Password grant en el endpoint de token
- **WHEN** un cliente envía a `oauth/token` `grant_type=password` con el email y la contraseña correctos de un usuario
- **THEN** responde en el formato de OAuth2 con `error` igual a `unsupported_grant_type` y no emite tokens

## MODIFIED Requirements

### Requirement: Renovación de sesión con refresh token
El sistema SHALL entregar un nuevo par de tokens en `POST /api/auth/oauth/token` con `grant_type=refresh_token`, un `refresh_token` vigente y las credenciales del cliente que lo obtuvo, con un `scope` opcional. Cada refresh token MUST ser de un solo uso: una vez usado, un nuevo intento con el mismo token MUST rechazarse con `error` igual a `invalid_grant`. Un refresh token MUST NOT servir con otro cliente.

#### Scenario: Refresh exitoso
- **WHEN** un satélite envía un refresh token vigente con las credenciales de su cliente
- **THEN** el sistema responde 200 con un access token distinto al anterior y un nuevo refresh token

#### Scenario: Refresh token reutilizado
- **WHEN** se envía por segunda vez un refresh token ya usado
- **THEN** el sistema responde con `error` igual a `invalid_grant`

#### Scenario: Refresh token de otro cliente
- **WHEN** un cliente envía un refresh token que obtuvo otro cliente
- **THEN** el sistema no emite tokens

### Requirement: Cierre de la sesión actual
El sistema SHALL revocar, en `POST /api/auth/v1/logout`, el access token usado en la llamada y el refresh token asociado. La llamada MUST requerir un access token de usuario.

#### Scenario: Logout revoca ambos tokens
- **WHEN** un usuario llama a logout con su access token
- **THEN** el sistema responde 200, el access token queda revocado y su refresh token ya no permite renovar la sesión

## REMOVED Requirements

### Requirement: Login con email y contraseña
**Reason**: Las personas entran solo por la página del SSO; `/v1/login` se cierra. El rechazo de usuarios sin contraseña queda en el login web (`central-login`).
**Migration**: Las aplicaciones usan el login central (`oauth/authorize` con PKCE) y canjean el código en `oauth/token`.

### Requirement: Límite de intentos en endpoints de credenciales
**Reason**: Ya no existen `/v1/login` ni `/v1/refresh`. El login web y `oauth/token` tienen sus propios límites (`central-login` y `service-tokens`).
**Migration**: Ninguna.
