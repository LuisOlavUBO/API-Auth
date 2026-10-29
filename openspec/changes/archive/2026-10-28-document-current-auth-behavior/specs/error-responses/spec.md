# Spec Delta

## Purpose

Fija cómo responde el servicio cuando algo falla, para que los clientes puedan reaccionar con un código estable, y qué errores se registran en el log y cuáles no.

## ADDED Requirements

### Requirement: Errores en formato problem+json
Las rutas bajo `/api/auth` (salvo el endpoint OAuth2 de token) SHALL responder los errores según RFC 9457, con `Content-Type: application/problem+json`, los campos `type` (`about:blank`), `title`, `status` y `detail`, y un código estable en `error`.

#### Scenario: Petición sin token
- **WHEN** se llama a `GET /api/auth/v1/me` sin token
- **THEN** responde 401 en problem+json con `error` igual a `NO_AUTENTICADO`

#### Scenario: Token sin el scope requerido
- **WHEN** se llama a `GET /api/auth/v1/me` con un token que solo tiene `user:write`
- **THEN** responde 403 con `error` igual a `SCOPE_INSUFICIENTE` y `detail` igual a `El token no tiene el scope requerido: user:read.`

#### Scenario: Ruta inexistente
- **WHEN** se llama a una ruta que no existe bajo `/api/auth/v1`
- **THEN** responde 404 con `error` igual a `RUTA_NO_ENCONTRADA`

#### Scenario: Límite de solicitudes excedido
- **WHEN** se supera el límite de intentos de login
- **THEN** responde 429 con `error` igual a `LIMITE_EXCEDIDO` y la cabecera `Retry-After`

### Requirement: Errores de validación por campo
Los datos de entrada inválidos SHALL responder 422 con `error` igual a `VALIDACION` y los mensajes agrupados por campo en `errores`.

#### Scenario: Login con datos inválidos
- **WHEN** se envía un login con un email mal formado y sin contraseña
- **THEN** responde 422 con `error` igual a `VALIDACION` y entradas para `email` y `password` en `errores`

### Requirement: Rechazos de login y refresh con código OAuth
Un login o refresh rechazado por el servidor OAuth SHALL responder en problem+json con el código OAuth en `error`. `invalid_grant` e `invalid_credentials` MUST responder 401 con el título `Credenciales inválidas`; `invalid_scope` usa el título `Scope inválido`.

#### Scenario: Credenciales incorrectas
- **WHEN** un login envía una contraseña incorrecta
- **THEN** responde 401 con `error` igual a `invalid_grant` y `title` igual a `Credenciales inválidas`

### Requirement: Falla de configuración del cliente interno
Si el servidor OAuth rechaza al cliente que el servicio usa internamente para login y refresh, la respuesta SHALL ser 500 con `error` igual a `invalid_client` y un `detail` genérico, sin el motivo real. El motivo MUST registrarse como error en el log.

#### Scenario: Secreto del cliente interno incorrecto
- **WHEN** el secreto configurado del cliente interno es incorrecto y alguien inicia sesión
- **THEN** responde 500 con `detail` igual a `El servicio de autenticación no está bien configurado.`, sin el mensaje original, y se registra un error que comienza con `The password grant client was rejected: `

### Requirement: Errores esperados fuera del log de errores
Los rechazos esperados (credenciales incorrectas, refresh tokens vencidos o reutilizados) MUST NOT registrarse como errores en el log.

#### Scenario: Login fallido
- **WHEN** un login falla por contraseña incorrecta
- **THEN** no se registra ningún error en el log

### Requirement: Errores inesperados sin detalles internos
Una excepción no controlada SHALL responder 500 con `error` igual a `ERROR_INTERNO`, sin exponer el mensaje de la excepción ni la traza.

#### Scenario: Excepción no controlada
- **WHEN** un endpoint lanza una excepción con un mensaje interno
- **THEN** responde 500 con `error` igual a `ERROR_INTERNO`, sin ese mensaje y sin el campo `trace`

### Requirement: Formato OAuth2 en el endpoint de token
`POST /api/auth/oauth/token` SHALL responder sus errores en el formato de OAuth2 (`application/json` con `error` y `error_description`), no en problem+json, porque los clientes OAuth2 los leen así.

#### Scenario: Secreto de cliente incorrecto en el endpoint de token
- **WHEN** un cliente pide un token con un `client_secret` incorrecto
- **THEN** responde 401 en `application/json` con `error` igual a `invalid_client`, `error_description` y sin el campo `type`
