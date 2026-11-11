# token-introspection Specification

## Purpose

Permite que otros servicios comprueben si un token emitido por API-Auth sigue activo y qué contiene, ya sea preguntándole al servicio o validando la firma del JWT localmente con su clave pública.

## Requirements

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
Para un token activo, la respuesta SHALL incluir `active: true`, `token_type: "Bearer"`, `client_id`, `sub`, `uuid` (el de la claim `uuid` del token), `scope` (scopes separados por espacio), `aud`, `roles` (los de la claim `roles` del token, en la misma forma que el token) y `exp` (timestamp de vencimiento), y `user` con el perfil cuando el token es de un usuario.

#### Scenario: Token de usuario activo
- **WHEN** un servicio introspecta un access token vigente de un usuario
- **THEN** responde `active: true` con `client_id`, `sub`, `uuid`, `scope`, `roles`, `exp`, `user.email` y `user.uuid` del usuario

#### Scenario: Token dirigido a una API
- **WHEN** un servicio introspecta un token emitido con `api=api-maestros` y el scope `geografia:leer`
- **THEN** responde `active: true`, `aud` igual a `[<client_id>, "api-maestros"]` y `scope` igual a `geografia:leer`

#### Scenario: Roles de un token de usuario
- **WHEN** un servicio introspecta el token de un usuario con el rol `vendedor` de `portal`, emitido para un cliente de `portal`
- **THEN** la respuesta incluye `roles` igual a `["vendedor"]`

#### Scenario: Roles de un token de servicio
- **WHEN** un servicio introspecta un token de cliente con el rol `introspector`
- **THEN** la respuesta incluye `roles` igual a `["introspector"]`

### Requirement: Tokens de cliente sin usuario
Un token de cliente (client credentials) SHALL reportarse con `sub`, `uuid` y `user` en `null`, aunque internamente su sujeto sea el propio cliente.

#### Scenario: Introspección de un token de servicio
- **WHEN** un servicio introspecta un token de cliente con el scope `introspect`
- **THEN** responde `active: true`, `sub: null`, `uuid: null`, `user: null` y `scope` igual a `introspect`

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

### Requirement: Tokens de usuarios dados de baja
El access token de un usuario dado de baja SHALL reportarse como inactivo (exactamente `{"active": false}`), aunque su firma sea válida, no haya vencido y no esté revocado.

#### Scenario: Introspección después de la baja
- **WHEN** un usuario es dado de baja y un servicio introspecta un access token suyo que seguía vigente
- **THEN** responde exactamente `{"active": false}`

### Requirement: Tokens de clientes revocados
El access token emitido para un cliente revocado (`oauth_clients.revoked`) SHALL reportarse como inactivo (exactamente `{"active": false}`), aunque su firma sea válida, no haya vencido y el token no esté revocado. Aplica tanto a tokens de cliente (client credentials) como a tokens de usuario emitidos para ese cliente.

#### Scenario: Token de servicio después de revocar el cliente
- **WHEN** un cliente obtiene un token con client credentials, el cliente es revocado y un servicio introspecta ese token
- **THEN** responde exactamente `{"active": false}`

#### Scenario: Token de usuario de un cliente revocado
- **WHEN** una persona obtiene un token por el login central para un cliente, el cliente es revocado y un servicio introspecta ese token
- **THEN** responde exactamente `{"active": false}`

#### Scenario: Cliente reactivado
- **WHEN** un cliente revocado vuelve a estar activo y un servicio introspecta un token suyo que no ha vencido
- **THEN** responde `active: true`
