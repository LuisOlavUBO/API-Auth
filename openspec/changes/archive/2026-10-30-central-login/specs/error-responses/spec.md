# Spec Delta

## MODIFIED Requirements

### Requirement: Errores en formato problem+json
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

#### Scenario: Límite de solicitudes excedido
- **WHEN** se supera el límite de intentos de login
- **THEN** responde 429 con `error` igual a `LIMITE_EXCEDIDO` y la cabecera `Retry-After`

#### Scenario: Páginas web
- **WHEN** una persona sin sesión abre `/api/auth/oauth/authorize` o envía datos inválidos al formulario de login
- **THEN** recibe una redirección o una página HTML con el error, no un problem+json

#### Scenario: Sin Accept
- **WHEN** se llama a `GET /api/auth/v1/me` sin token y sin la cabecera `Accept`
- **THEN** responde 401 en problem+json, sin redirigir al login
