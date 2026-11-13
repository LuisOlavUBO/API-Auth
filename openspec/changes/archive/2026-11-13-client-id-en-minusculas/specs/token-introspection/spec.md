## ADDED Requirements

### Requirement: Id del cliente en minúsculas en la introspección
La respuesta de la introspección de un token activo SHALL llevar el id del cliente en minúsculas en `client_id` y en el primer elemento de `aud`.

#### Scenario: Introspección de un token de cliente
- **WHEN** un servicio introspecta un token obtenido con client credentials
- **THEN** `client_id` y `aud[0]` son el id del cliente en minúsculas
