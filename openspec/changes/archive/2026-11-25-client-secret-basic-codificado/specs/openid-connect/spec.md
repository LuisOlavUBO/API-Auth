# Spec Delta

## MODIFIED Requirements

### Requirement: Autenticación del cliente al canjear
El canje del código SHALL aceptar las credenciales del cliente en la cabecera `Authorization: Basic` (`client_secret_basic`) o en el cuerpo de la solicitud (`client_secret_post`). En la cabecera, el id y el secreto MUST decodificarse como `application/x-www-form-urlencoded` (RFC 6749 §2.3.1) antes de validar el cliente, en todos los grants de `oauth/token`.

#### Scenario: Credenciales en la cabecera
- **WHEN** Ucampus canjea un código con `Authorization: Basic` con su id y su secreto, sin credenciales en el cuerpo
- **THEN** recibe los tokens

#### Scenario: Credenciales codificadas en la cabecera
- **WHEN** Ucampus canjea un código con `Authorization: Basic` cuyo id y secreto van codificados (los guiones del id como `%2D`)
- **THEN** recibe los tokens

#### Scenario: Credenciales en el cuerpo
- **WHEN** Ucampus canjea un código con `client_id` y `client_secret` en el cuerpo
- **THEN** recibe los tokens
