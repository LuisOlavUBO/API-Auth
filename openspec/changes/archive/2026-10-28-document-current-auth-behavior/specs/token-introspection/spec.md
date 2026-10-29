# Spec Delta

## Purpose

Permite que otros servicios comprueben si un token emitido por API-Auth sigue activo y qué contiene, ya sea preguntándole al servicio o validando la firma del JWT localmente con su clave pública.

## ADDED Requirements

### Requirement: Introspección de tokens para servicios
El sistema SHALL ofrecer en `POST /api/auth/v1/introspect` la validación de un `token` (firma, vencimiento y revocación), al estilo de RFC 7662. Quien llama MUST autenticarse con un token de cliente (client credentials) que tenga el scope `introspect`.

#### Scenario: Llamada sin token de servicio
- **WHEN** se llama a introspect sin token
- **THEN** el sistema responde 401 con el código `NO_AUTENTICADO`

#### Scenario: Llamada con un token de usuario
- **WHEN** se llama a introspect con un access token de usuario
- **THEN** el sistema responde 401

#### Scenario: Token de servicio sin el scope introspect
- **WHEN** se llama con un token de cliente que no tiene `introspect`
- **THEN** el sistema responde 403

### Requirement: Respuesta para tokens activos
Para un token activo, la respuesta SHALL incluir `active: true`, `token_type: "Bearer"`, `client_id`, `sub`, `scope` (scopes separados por espacio), `aud` y `exp` (timestamp de vencimiento), y `user` con el perfil cuando el token es de un usuario.

#### Scenario: Token de usuario activo
- **WHEN** un servicio introspecta un access token vigente de un usuario
- **THEN** responde `active: true` con `client_id`, `sub`, `scope`, `exp` y `user.email` del usuario

#### Scenario: Token dirigido a una API
- **WHEN** un servicio introspecta un token emitido con `api=api-maestros` y el scope `geografia:leer`
- **THEN** responde `active: true`, `aud` igual a `[<client_id>, "api-maestros"]` y `scope` igual a `geografia:leer`

### Requirement: Tokens de cliente sin usuario
Un token de cliente (client credentials) SHALL reportarse con `sub` y `user` en `null`, aunque internamente su sujeto sea el propio cliente.

#### Scenario: Introspección de un token de servicio
- **WHEN** un servicio introspecta un token de cliente con el scope `introspect`
- **THEN** responde `active: true`, `sub: null`, `user: null` y `scope` igual a `introspect`

### Requirement: Respuesta para tokens inactivos
Un token revocado, vencido, con firma inválida o mal formado SHALL responder 200 con exactamente `{"active": false}`, sin más detalles.

#### Scenario: Token revocado por logout
- **WHEN** un servicio introspecta un access token de usuario que fue revocado con logout
- **THEN** responde exactamente `{"active": false}`

#### Scenario: Token sin formato válido
- **WHEN** un servicio introspecta el valor `garbage`
- **THEN** responde exactamente `{"active": false}`

### Requirement: Publicación de la clave pública
El sistema SHALL publicar sin autenticación, en `GET /api/auth/v1/public-key`, la clave pública en formato PEM con la que se firman los access tokens, para que otros servicios validen los JWT localmente.

#### Scenario: Descarga de la clave pública
- **WHEN** se llama a public-key sin token
- **THEN** el sistema responde 200 con una clave que contiene `BEGIN PUBLIC KEY`
