# user-authentication Specification

## Purpose

Define los tokens de usuario que emite el login central: cómo se renuevan, el perfil del dueño, el cierre de sesión y los datos del usuario. Las personas no crean su cuenta ni envían su contraseña a la API.

## Requirements

### Requirement: Sin registro público
El sistema MUST NOT permitir que una persona cree su propia cuenta. `POST /api/auth/v1/register` SHALL responder 404. Los usuarios se crean al entrar por primera vez con Microsoft Entra ID, sin roles.

#### Scenario: Intento de registro
- **WHEN** se envía `POST /api/auth/v1/register` con `name`, `email`, `password` y `password_confirmation` válidos
- **THEN** el sistema responde 404 y no se crea ningún usuario

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

### Requirement: Cierre de la sesión actual
El sistema SHALL revocar, en `POST /api/auth/v1/logout`, el access token usado en la llamada y el refresh token asociado. La llamada MUST requerir un access token de usuario.

#### Scenario: Logout revoca ambos tokens
- **WHEN** un usuario llama a logout con su access token
- **THEN** el sistema responde 200, el access token queda revocado y su refresh token ya no permite renovar la sesión

### Requirement: Cierre de todas las sesiones
El sistema SHALL revocar, en `POST /api/auth/v1/logout-all`, todos los access tokens vigentes del usuario y sus refresh tokens, en todos los dispositivos. La llamada MUST requerir un access token de usuario.

#### Scenario: Logout de todas las sesiones
- **WHEN** un usuario con dos sesiones abiertas llama a logout-all con el token de una de ellas
- **THEN** el sistema responde 200 y ninguno de sus access tokens queda vigente

### Requirement: Vigencia de los tokens
Los access tokens de usuario SHALL vencer por defecto a los 60 minutos y los refresh tokens a los 120 minutos, que se renuevan cada vez que se usan y por eso equivalen a un plazo de inactividad. Ambos plazos MUST poder configurarse por entorno. Los access tokens SHALL ser JWT firmados con la clave privada del servicio.

#### Scenario: Vigencia por defecto
- **WHEN** se emite un par de tokens sin configuración de vigencia propia
- **THEN** `expires_in` corresponde a 60 minutos y el refresh token es válido durante 120 minutos

#### Scenario: El refresh se renueva con el uso
- **WHEN** se renueva la sesión con un refresh token vigente
- **THEN** el refresh token nuevo vuelve a ser válido durante 120 minutos

### Requirement: Identificador público del usuario
Todo usuario SHALL tener un `uuid` único, asignado al crearse, que no cambia nunca. Es el identificador con que los demás sistemas se refieren al usuario; el `id` numérico es interno de API-Auth y los satélites MUST NOT guardarlo.

#### Scenario: Usuario nuevo
- **WHEN** se crea un usuario
- **THEN** el usuario tiene un `uuid` distinto al de cualquier otro usuario

#### Scenario: El uuid no cambia
- **WHEN** un usuario actualiza su sesión, sus roles o su perfil
- **THEN** su `uuid` sigue siendo el mismo

### Requirement: RUT del usuario
Un usuario SHALL poder tener un `rut`, opcional y único entre los usuarios que lo tienen. Se MUST guardar sin puntos, con guion y con el dígito verificador en mayúscula (`12345678-K`). El RUT MUST NOT incluirse en los access tokens.

#### Scenario: Usuario sin RUT
- **WHEN** se crean dos usuarios sin `rut`
- **THEN** ambos quedan guardados con `rut` en `null`

#### Scenario: El RUT no viaja en el token
- **WHEN** se decodifica el payload del access token de un usuario con RUT
- **THEN** el token no contiene el RUT

#### Scenario: RUT en la introspección
- **WHEN** un servicio introspecta el access token de un usuario con RUT
- **THEN** la respuesta incluye `user.rut` con el RUT del usuario

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
