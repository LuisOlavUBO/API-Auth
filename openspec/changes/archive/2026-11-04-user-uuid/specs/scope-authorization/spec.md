## ADDED Requirements

### Requirement: Identificador del usuario en el token
Todo access token de usuario SHALL llevar en la claim `uuid` el identificador público del usuario. La claim `sub` MUST seguir siendo el identificador interno. Un token de cliente MUST NOT llevar la claim `uuid`.

#### Scenario: Token de usuario
- **WHEN** se decodifica el payload de un access token de usuario
- **THEN** la claim `uuid` es el `uuid` del usuario y `sub` es su `id`

#### Scenario: Token renovado
- **WHEN** un usuario renueva su sesión con el refresh token
- **THEN** el nuevo token lleva la misma claim `uuid`

#### Scenario: Token de cliente
- **WHEN** se decodifica el payload de un token de client credentials
- **THEN** el token no tiene la claim `uuid`
