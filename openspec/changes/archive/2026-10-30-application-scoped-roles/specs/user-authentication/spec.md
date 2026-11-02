# Spec Delta

## MODIFIED Requirements

### Requirement: Rol por defecto al registrarse
Todo usuario creado por registro SHALL recibir el rol configurado como rol por defecto (por omisión, `user`) de la aplicación `api-auth`.

#### Scenario: El usuario registrado tiene el rol por defecto
- **WHEN** una persona se registra correctamente
- **THEN** su cuenta queda asociada al rol `user` de `api-auth`
