# user-authentication Specification

## Purpose

Permite que las personas creen una cuenta, inicien sesión con email y contraseña, renueven su sesión y la cierren, obteniendo tokens de acceso firmados que otras APIs pueden validar.

## Requirements

### Requirement: Registro de usuarios
El sistema SHALL permitir crear una cuenta en `POST /api/auth/v1/register` con `name`, `email` y `password` confirmada (`password_confirmation`). El email MUST ser único. La respuesta exitosa SHALL ser 201 con el usuario creado y su primer par de tokens (`token_type`, `expires_in`, `access_token`, `refresh_token`).

#### Scenario: Registro exitoso
- **WHEN** se envía un nombre, un email no registrado y una contraseña válida con su confirmación
- **THEN** el sistema responde 201 con `user` (incluye `id` y `email`) y un par de tokens, y el usuario queda guardado

#### Scenario: Datos de registro inválidos
- **WHEN** se envía un registro sin nombre, con un email mal formado o sin contraseña
- **THEN** el sistema responde 422 indicando los errores de `name`, `email` y `password`

### Requirement: Rol por defecto al registrarse
Todo usuario creado por registro SHALL recibir el rol configurado como rol por defecto (por omisión, `user`).

#### Scenario: El usuario registrado tiene el rol por defecto
- **WHEN** una persona se registra correctamente
- **THEN** su cuenta queda asociada al rol `user`

### Requirement: Login con email y contraseña
El sistema SHALL emitir un par de tokens (acceso y refresh) en `POST /api/auth/v1/login` a cambio de `email` y `password` válidos, con un `scope` opcional separado por espacios. Las credenciales incorrectas MUST responder 401 con el código `invalid_grant`. El secreto del cliente OAuth usado internamente MUST NOT exponerse a quien llama.

#### Scenario: Login exitoso
- **WHEN** un usuario existente envía su email y su contraseña correctos
- **THEN** el sistema responde 200 con `token_type`, `expires_in`, `access_token` y `refresh_token`

#### Scenario: Contraseña incorrecta
- **WHEN** un usuario envía una contraseña incorrecta
- **THEN** el sistema responde 401 con `error` igual a `invalid_grant`

### Requirement: Renovación de sesión con refresh token
El sistema SHALL entregar un nuevo par de tokens en `POST /api/auth/v1/refresh` a cambio de un `refresh_token` vigente, con un `scope` opcional. Cada refresh token MUST ser de un solo uso: una vez usado, un nuevo intento con el mismo token MUST responder 401.

#### Scenario: Refresh exitoso
- **WHEN** se envía un refresh token vigente
- **THEN** el sistema responde 200 con un access token distinto al anterior y un nuevo refresh token

#### Scenario: Refresh token reutilizado
- **WHEN** se envía por segunda vez un refresh token ya usado
- **THEN** el sistema responde 401

### Requirement: Perfil del usuario autenticado
El sistema SHALL devolver el perfil del dueño del token en `GET /api/auth/v1/me` (`id`, `name`, `email`, `email_verified_at`, `created_at`), sin exponer la contraseña. El endpoint MUST exigir un access token de usuario con el scope `user:read`.

#### Scenario: Consulta del perfil con token válido
- **WHEN** se llama con un access token de usuario que incluye `user:read`
- **THEN** el sistema responde 200 con el perfil del usuario y sin el campo `password`

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
- **THEN** el sistema responde 200, el access token queda revocado y su refresh token ya no permite renovar la sesión (401)

### Requirement: Cierre de todas las sesiones
El sistema SHALL revocar, en `POST /api/auth/v1/logout-all`, todos los access tokens vigentes del usuario y sus refresh tokens, en todos los dispositivos. La llamada MUST requerir un access token de usuario.

#### Scenario: Logout de todas las sesiones
- **WHEN** un usuario con dos sesiones abiertas llama a logout-all con el token de una de ellas
- **THEN** el sistema responde 200 y ninguno de sus access tokens queda vigente

### Requirement: Límite de intentos en endpoints de credenciales
Los endpoints de registro, login y refresh SHALL aceptar como máximo 10 solicitudes por minuto por combinación de email e IP. Al superarlo MUST responder 429 con la cabecera `Retry-After`.

#### Scenario: Se supera el límite de intentos
- **WHEN** se hacen 11 intentos de login en un minuto con el mismo email desde la misma IP
- **THEN** el undécimo responde 429 con la cabecera `Retry-After`

### Requirement: Vigencia de los tokens
Los access tokens de usuario SHALL vencer por defecto a los 60 minutos y los refresh tokens a los 30 días. Ambos plazos MUST poder configurarse por entorno. Los access tokens SHALL ser JWT firmados con la clave privada del servicio.

#### Scenario: Vigencia por defecto
- **WHEN** se emite un par de tokens sin configuración de vigencia propia
- **THEN** `expires_in` corresponde a 60 minutos y el refresh token es válido durante 30 días
