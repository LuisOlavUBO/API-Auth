## MODIFIED Requirements

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
