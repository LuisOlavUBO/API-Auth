# error-responses Specification

## Purpose

Fija cómo responde el servicio cuando algo falla, para que los clientes puedan reaccionar con un código estable, y qué errores se registran en el log y cuáles no.

## Requirements

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

### Requirement: Formato problem+json de los errores
Las rutas bajo `/api/auth` (salvo el endpoint OAuth2 de token y las páginas web de login, logout y autorización) SHALL responder los errores según RFC 9457, con `Content-Type: application/problem+json`, los campos `type` (`about:blank`), `title`, `status` y `detail`, y un código estable en `error`.

#### Scenario: Petición sin token
- **WHEN** se llama a `GET /api/auth/v1/me` sin token
- **THEN** responde 401 en problem+json con `error` igual a `NO_AUTENTICADO`

#### Scenario: Token sin el scope requerido
- **WHEN** se llama a `GET /api/auth/v1/me` con un token que solo tiene `user:write`
- **THEN** responde 403 con `error` igual a `SCOPE_INSUFICIENTE` y `detail` igual a `El token no tiene el scope requerido: user:read.`

#### Scenario: Ruta inexistente
- **WHEN** se llama a una ruta que no existe bajo `/api/auth/v1`
- **THEN** responde 404 con `error` igual a `RUTA_NO_ENCONTRADA`

#### Scenario: Páginas web
- **WHEN** una persona sin sesión abre `/api/auth/oauth/authorize` o envía datos inválidos al formulario de login
- **THEN** recibe una redirección o una página HTML con el error, no un problem+json

#### Scenario: Sin Accept
- **WHEN** se llama a `GET /api/auth/v1/me` sin token y sin la cabecera `Accept`
- **THEN** responde 401 en problem+json, sin redirigir al login

### Requirement: Validación de datos por campo
Los datos de entrada inválidos SHALL responder 422 con `error` igual a `VALIDACION` y los mensajes agrupados por campo en `errores`.

#### Scenario: Introspección sin token a revisar
- **WHEN** un servicio con el scope `introspect` llama a `POST /api/auth/v1/introspect` sin `token`
- **THEN** responde 422 con `error` igual a `VALIDACION` y una entrada para `token` en `errores`

### Requirement: Rechazos esperados fuera del log
Los rechazos esperados del endpoint de token (credenciales de cliente incorrectas, códigos o refresh tokens vencidos o reutilizados) MUST NOT registrarse como errores en el log.

#### Scenario: Refresh token reutilizado
- **WHEN** un satélite envía a `oauth/token` un refresh token ya usado
- **THEN** no se registra ningún error en el log
