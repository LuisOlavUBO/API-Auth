## ADDED Requirements

### Requirement: Sin acceso a una aplicación de baja
El login central MUST mostrar «Sin acceso» a quien entra a un cliente de una aplicación con `disabled_at`, o a quien solo tiene roles de baja en la aplicación del cliente, como a quien no tiene roles en ella. Los clientes de una aplicación con `disabled_at` MUST NOT obtener ni renovar tokens (`invalid_client`).

#### Scenario: Aplicación de baja
- **GIVEN** la aplicación `fuas` de baja y una persona con roles en ella
- **WHEN** la persona entra al login central por un cliente de `fuas`
- **THEN** ve «Sin acceso» y no se emite código

#### Scenario: Cliente de servicio de una aplicación de baja
- **WHEN** un cliente de `fuas` pide un token con client credentials
- **THEN** responde `invalid_client` y no emite tokens
