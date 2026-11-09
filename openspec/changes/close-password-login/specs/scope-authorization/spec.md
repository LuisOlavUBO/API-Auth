## ADDED Requirements

### Requirement: Token de usuario sin scopes solicitados
Cuando un usuario obtiene un token de usuario sin solicitar scopes, el token MUST llevar todos los scopes que conceden sus roles en la aplicación del cliente. No hay otros scopes por defecto.

#### Scenario: Token de usuario sin scope
- **WHEN** un usuario con el rol `vendedor` de `portal` obtiene un token con un cliente de `portal`, sin `scope`
- **THEN** el token contiene todos los scopes que concede `vendedor`

## MODIFIED Requirements

### Requirement: Catálogo de scopes
Solo SHALL existir los scopes registrados en el catálogo del servicio. Cada scope MUST pertenecer a exactamente una aplicación (la que lo exige en sus endpoints o funciones). Un scope que no está en el catálogo, incluido el comodín `*`, MUST tratarse como inexistente.

#### Scenario: Se solicita un scope que no existe
- **WHEN** un cliente solicita un token con el scope `does-not-exist` o `*`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope`

### Requirement: Scopes que puede llevar un token
Un token de usuario SHALL poder llevar la unión de los scopes de los roles del usuario que pertenecen a la aplicación del cliente que lo obtiene. Un token de cliente SHALL poder llevar la unión de los scopes de los roles del cliente.

#### Scenario: Cliente con varios roles
- **WHEN** un cliente tiene un rol que concede `geografia:leer` y otro que concede `matricula:leer`, y solicita ambos scopes
- **THEN** el token emitido contiene `geografia:leer` y `matricula:leer`

#### Scenario: Usuario sin rol
- **WHEN** un usuario que no tiene roles en la aplicación del cliente entra por el login central
- **THEN** no recibe un código ni tokens

#### Scenario: Roles sin scopes
- **WHEN** un usuario cuyo rol en la aplicación del cliente no concede scopes obtiene un token
- **THEN** recibe un access token sin scopes, que es rechazado con 403 en `GET /api/auth/v1/me`

#### Scenario: Usuario con roles en dos aplicaciones
- **WHEN** un usuario con el rol `user` de `api-auth` y el rol `vendedor` de `portal` obtiene un token con un cliente de `api-auth`
- **THEN** el token contiene los scopes de `user` y ninguno de los de `vendedor`

#### Scenario: Rol con scopes de varias aplicaciones
- **WHEN** el rol `vendedor` de `portal` concede `portal:ventas.crear` y `geografia:leer`, y un usuario con ese rol obtiene un token con un cliente de `portal`
- **THEN** el token contiene `portal:ventas.crear` y `geografia:leer`

### Requirement: Rechazo de scopes no concedidos al emitir
Al emitir tokens de usuario y tokens de cliente, solicitar explícitamente un scope que los roles del usuario en la aplicación del cliente (o los roles del cliente) no conceden MUST rechazarse con `invalid_scope`, sin emitir tokens. Los scopes solicitados y concedidos SHALL incluirse en el token.

#### Scenario: Usuario solicita scopes de su rol
- **WHEN** un usuario con el rol `user` obtiene un token solicitando `user:read user:write`
- **THEN** el token contiene `user:read` y `user:write`

#### Scenario: Usuario solicita un scope ajeno a su rol
- **WHEN** un usuario con el rol `user` solicita `introspect`
- **THEN** no recibe tokens y el error es `invalid_scope`

#### Scenario: Usuario solicita un scope de un rol de otra aplicación
- **WHEN** un usuario con el rol `vendedor` de `portal` solicita `portal:ventas.crear` con un cliente de `api-auth`
- **THEN** no recibe tokens y el error es `invalid_scope`

## REMOVED Requirements

### Requirement: Scopes por defecto en el login
**Reason**: Los scopes por defecto (`user:read`) solo los pedía `/v1/login`, que se elimina. La regla que queda pasa a "Token de usuario sin scopes solicitados".
**Migration**: Ninguna: los tokens del login central ya llevan todos los scopes de los roles cuando no se pide `scope`.
