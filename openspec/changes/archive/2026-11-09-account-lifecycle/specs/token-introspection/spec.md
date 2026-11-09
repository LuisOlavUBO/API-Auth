## ADDED Requirements

### Requirement: Tokens de usuarios dados de baja
El access token de un usuario dado de baja SHALL reportarse como inactivo (exactamente `{"active": false}`), aunque su firma sea válida, no haya vencido y no esté revocado.

#### Scenario: Introspección después de la baja
- **WHEN** un usuario es dado de baja y un servicio introspecta un access token suyo que seguía vigente
- **THEN** responde exactamente `{"active": false}`
