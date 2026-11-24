## ADDED Requirements

### Requirement: Scopes de OpenID Connect
`openid`, `profile` y `email` SHALL existir fuera del catálogo, solo en tokens de usuario: los concede la autorización con código a cualquier usuario y se conservan al renovar. No son permisos: MUST NOT exigir roles, agregar aplicaciones a `aud` ni cambiar qué scopes de permisos lleva el token. Un token de cliente que los pida MUST rechazarse con `invalid_scope`.

#### Scenario: openid con permisos
- **WHEN** un usuario con el rol `vendedor` de `portal` canjea un código pedido con `scope=openid portal:ventas.leer`
- **THEN** el token lleva `openid` y `portal:ventas.leer`, y nada más

#### Scenario: Token de cliente con openid
- **WHEN** un cliente pide un token con client credentials y `scope=openid`
- **THEN** responde 400 con `error` igual a `invalid_scope`

## MODIFIED Requirements

### Requirement: Catálogo de scopes
Solo SHALL existir los scopes registrados en el catálogo del servicio, además de los de OpenID Connect (`openid`, `profile` y `email`). Cada scope del catálogo MUST pertenecer a exactamente una aplicación (la que lo exige en sus endpoints o funciones). Un scope que no está en el catálogo ni es de OpenID Connect, incluido el comodín `*`, MUST tratarse como inexistente.

#### Scenario: Se solicita un scope que no existe
- **WHEN** un cliente solicita un token con el scope `does-not-exist` o `*`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope`

### Requirement: Token de usuario sin scopes solicitados
Cuando un usuario obtiene un token de usuario sin solicitar scopes, o solicitando solo scopes de OpenID Connect, el token MUST llevar todos los scopes que conceden sus roles en la aplicación del cliente, más los de OpenID Connect solicitados. No hay otros scopes por defecto.

#### Scenario: Token de usuario sin scope
- **WHEN** un usuario con el rol `vendedor` de `portal` obtiene un token con un cliente de `portal`, sin `scope`
- **THEN** el token contiene todos los scopes que concede `vendedor`

#### Scenario: Solo scopes de OpenID Connect
- **WHEN** un usuario con el rol `alumno` de `ucampus` canjea un código pedido con `scope=openid email`
- **THEN** el token contiene `openid`, `email` y todos los scopes que concede `alumno`
