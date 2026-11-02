# Spec Delta

## MODIFIED Requirements

### Requirement: Endpoint OAuth2 de emisión de tokens
El sistema SHALL exponer el endpoint OAuth2 estándar de token en `POST /api/auth/oauth/token` y el de autorización en `GET /api/auth/oauth/authorize`, sin versión. Son los únicos endpoints OAuth2 publicados: la aprobación y el rechazo de autorizaciones, los dispositivos y demás rutas OAuth MUST NOT estar disponibles, ni fuera del prefijo `/api/auth`.

#### Scenario: Solo existe el endpoint de token con prefijo
- **WHEN** se inspeccionan las rutas publicadas
- **THEN** existen `api/auth/oauth/token` y `api/auth/oauth/authorize`, y no existe ninguna otra ruta que empiece por `oauth/` ni por `api/auth/oauth/`

## ADDED Requirements

### Requirement: Límite de solicitudes del endpoint de token
El endpoint de token SHALL limitar las solicitudes por minuto por `client_id` (o por IP cuando no viene), con un máximo configurable. Al superarlo MUST responder 429 con la cabecera `Retry-After`.

#### Scenario: Un cliente supera el límite
- **WHEN** un cliente envía más solicitudes por minuto al endpoint de token que el máximo configurado
- **THEN** responde 429 con la cabecera `Retry-After`

#### Scenario: Otro cliente no se ve afectado
- **WHEN** un cliente superó el límite y otro cliente pide un token en el mismo minuto, desde la misma IP
- **THEN** el segundo recibe su token con normalidad
