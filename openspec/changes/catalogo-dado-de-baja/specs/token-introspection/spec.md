## ADDED Requirements

### Requirement: Tokens de clientes de una aplicación de baja
El access token de un cliente cuya aplicación tiene `disabled_at` SHALL reportarse como inactivo (exactamente `{"active": false}`), aunque su firma sea válida y no haya vencido.

#### Scenario: Token previo a la baja
- **WHEN** un cliente de `fuas` obtuvo un token, `fuas` se da de baja y un servicio introspecta ese token
- **THEN** responde exactamente `{"active": false}`
