## ADDED Requirements

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
