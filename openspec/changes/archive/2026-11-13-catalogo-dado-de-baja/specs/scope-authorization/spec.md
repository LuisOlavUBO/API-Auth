## ADDED Requirements

### Requirement: Roles y aplicaciones de baja no conceden scopes
Un rol con `disabled_at` o de una aplicación con `disabled_at` MUST NOT conceder scopes en tokens de usuario ni de cliente, ni incluirse en la claim `roles`. Los scopes de una aplicación con `disabled_at` MUST NOT concederse en ningún token. Las asignaciones no cambian: al quitar la baja, todo vuelve a concederse en el siguiente token.

#### Scenario: Rol de baja
- **GIVEN** una persona con el rol `pago-honorarios/revisor`, que da `honorarios:leer`
- **WHEN** el rol se da de baja y la persona obtiene un token con un cliente de `pago-honorarios`
- **THEN** el token no trae `honorarios:leer` ni el rol `revisor`

#### Scenario: Rol reactivado
- **WHEN** el rol vuelve a estar activo y la persona obtiene otro token
- **THEN** el token trae `honorarios:leer` y el rol `revisor`
