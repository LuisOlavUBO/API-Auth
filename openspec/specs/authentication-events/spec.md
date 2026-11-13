# authentication-events Specification

## Purpose
Define el historial de autenticación que guarda API-Auth: una fila por login exitoso, login fallido, bloqueo por límite de intentos y cliente rechazado en `oauth/token`, con su motivo, sin contraseñas, secretos ni tokens; registrarlo nunca hace fallar el login, y los eventos se purgan a los 180 días. API-IAM lo lee para sus indicadores de seguridad.

## Requirements

### Requirement: Registro de inicios de sesión
Cada inicio de sesión correcto en el login central, con clave o con Microsoft, SHALL registrar un evento `login_ok` con la fecha, el método, el `user_id`, el `client_id` del flujo de autorización si lo hay, la IP y el navegador.

#### Scenario: Login con clave
- **WHEN** una persona entra con su correo y su clave
- **THEN** hay un evento `login_ok` con método `clave`, su `user_id` y la IP de la petición

#### Scenario: Login con Microsoft
- **WHEN** una persona vuelve de Microsoft y entra
- **THEN** hay un evento `login_ok` con método `microsoft` y su `user_id`

### Requirement: Registro de intentos fallidos y bloqueos
Cada intento fallido SHALL registrar `login_fallido` con el motivo (`credenciales`, `baja`, `clave_vencida`, `sin_cuenta`, `cuenta_vinculada_a_otra`), el correo intentado en minúsculas si fue con clave, el `user_id` si la cuenta existe, la IP y el navegador. Cuando el límite de intentos bloquea a alguien SHALL registrarse `login_bloqueado`. Un evento MUST NOT contener la contraseña intentada.

#### Scenario: Clave equivocada
- **WHEN** alguien intenta entrar con un correo existente y una clave equivocada
- **THEN** hay un evento `login_fallido` con motivo `credenciales`, el `user_id` de esa cuenta y la IP, y ningún campo contiene la clave

#### Scenario: Demasiados intentos
- **WHEN** el límite de intentos rechaza el siguiente intento
- **THEN** hay un evento `login_bloqueado` con el correo y la IP

### Requirement: Registro de clientes rechazados
Cuando `POST /api/auth/oauth/token` rechaza a un cliente con `invalid_client` SHALL registrarse `cliente_rechazado` con el `client_id` enviado, el motivo (`secreto_invalido`, `cliente_revocado`, `cliente_inexistente`) y la IP. MUST NOT registrarse el secreto enviado.

#### Scenario: Secreto viejo
- **WHEN** un sistema pide un token con client credentials y el secreto anterior a una rotación
- **THEN** hay un evento `cliente_rechazado` con su `client_id` y motivo `secreto_invalido`

### Requirement: El registro no afecta el login
Si registrar un evento falla, el inicio de sesión o la respuesta de `oauth/token` MUST seguir como si nada, y la falla SHALL quedar en el log de errores.

#### Scenario: Tabla no disponible
- **WHEN** la inserción del evento falla durante un login correcto
- **THEN** la persona entra igual y el error queda en el log

### Requirement: Retención de 180 días
Los eventos con más de 180 días SHALL borrarse automáticamente una vez al día.

#### Scenario: Evento antiguo
- **GIVEN** un evento de hace 200 días y otro de hace 10 días
- **WHEN** corre la purga diaria
- **THEN** solo queda el de hace 10 días
