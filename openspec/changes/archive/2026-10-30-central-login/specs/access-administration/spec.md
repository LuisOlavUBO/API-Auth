# Spec Delta

## MODIFIED Requirements

### Requirement: Creación de clientes con su aplicación
El comando `oauth:client {name} --application= --type= --redirect=*` SHALL crear un cliente OAuth de la aplicación indicada y mostrar su id y su secreto una sola vez. `--type` es `client-credentials`, `password` o `authorization-code`; este último es un cliente confidencial para el login central y exige al menos un `--redirect`, que MUST ser una URL absoluta. `--application` es obligatorio y MUST ser una aplicación existente; si falla, no se crea el cliente.

#### Scenario: Crear un cliente de servicio
- **WHEN** se ejecuta `oauth:client portal-backend --application=portal --type=client-credentials`
- **THEN** el cliente existe, pertenece a `portal` y la salida muestra su id y su secreto

#### Scenario: Aplicación ausente o desconocida
- **WHEN** se ejecuta `oauth:client` sin `--application` o con una aplicación que no existe
- **THEN** el comando falla y no se crea ningún cliente

#### Scenario: Crear un cliente de código de autorización
- **WHEN** se ejecuta `oauth:client portal-web --application=portal --type=authorization-code --redirect=https://portal.ubo.cl/callback`
- **THEN** el cliente existe, pertenece a `portal`, tiene esa redirección registrada y la salida muestra su id y su secreto

#### Scenario: Cliente de código sin redirección
- **WHEN** se ejecuta `oauth:client` con `--type=authorization-code` sin `--redirect`, o con una redirección que no es una URL absoluta
- **THEN** el comando falla y no se crea ningún cliente
