## ADDED Requirements

### Requirement: Id del cliente en minúsculas
El id de un cliente OAuth SHALL aparecer en minúsculas en todo token que emita API-Auth: el primer elemento de `aud`, la claim `sub` de los tokens de cliente y el `client_id` asociado al token, aunque SQL Server lo devuelva en mayúsculas y aunque el cliente lo envíe en mayúsculas al pedir el token.

#### Scenario: Token de cliente
- **WHEN** un cliente pide un token con client credentials, enviando su `client_id` en mayúsculas
- **THEN** `aud[0]` y `sub` del token son el id del cliente en minúsculas

#### Scenario: Token dirigido a una API
- **WHEN** un cliente obtiene un token con `api=api-maestros`
- **THEN** `aud` es `[<client_id en minúsculas>, "api-maestros"]`

#### Scenario: Renovación con refresh token
- **WHEN** una persona renueva su token con un refresh token emitido después de este cambio
- **THEN** el token nuevo se emite y `aud[0]` es el id del cliente en minúsculas
