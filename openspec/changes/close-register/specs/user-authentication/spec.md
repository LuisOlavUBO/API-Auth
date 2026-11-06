## ADDED Requirements

### Requirement: Sin registro público
El sistema MUST NOT permitir que una persona cree su propia cuenta. `POST /api/auth/v1/register` SHALL responder 404. Los usuarios se crean al entrar por primera vez con Microsoft Entra ID, sin roles.

#### Scenario: Intento de registro
- **WHEN** se envía `POST /api/auth/v1/register` con `name`, `email`, `password` y `password_confirmation` válidos
- **THEN** el sistema responde 404 y no se crea ningún usuario

## MODIFIED Requirements

### Requirement: Límite de intentos en endpoints de credenciales
Los endpoints de login y refresh SHALL aceptar como máximo 10 solicitudes por minuto por combinación de email e IP. Al superarlo MUST responder 429 con la cabecera `Retry-After`.

#### Scenario: Se supera el límite de intentos
- **WHEN** se hacen 11 intentos de login en un minuto con el mismo email desde la misma IP
- **THEN** el undécimo responde 429 con la cabecera `Retry-After`

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

## REMOVED Requirements

### Requirement: Registro de usuarios
**Reason**: El alta de usuarios se hará desde el portal de administración de IAM; el autoservicio permitía preregistrar el email de otra persona.
**Migration**: Mientras no exista el portal, la persona entra con Microsoft Entra ID (se crea su usuario sin roles) y se le asignan roles con `php artisan oauth:assign-role`.

### Requirement: Rol por defecto al registrarse
**Reason**: Solo aplicaba a los usuarios creados por registro, que deja de existir.
**Migration**: Asignar los roles explícitamente con `php artisan oauth:assign-role`.
