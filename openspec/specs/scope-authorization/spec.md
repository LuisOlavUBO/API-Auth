# scope-authorization Specification

## Purpose

Define qué permisos (scopes) puede llevar cada token: los scopes existen en un catálogo administrado, pertenecen a una API y se conceden mediante roles asignados a usuarios y a clientes OAuth.

## Requirements

### Requirement: Catálogo de scopes
Solo SHALL existir los scopes registrados en el catálogo del servicio. Cada scope MUST pertenecer a exactamente una aplicación (la que lo exige en sus endpoints o funciones). Un scope que no está en el catálogo, incluido el comodín `*`, MUST tratarse como inexistente.

#### Scenario: Se solicita un scope que no existe
- **WHEN** un cliente solicita un token con el scope `does-not-exist` o `*`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope`

### Requirement: Scopes concedidos por roles
Un rol SHALL pertenecer a una aplicación y agrupar scopes del catálogo, que pueden ser de cualquier aplicación; su nombre MUST ser único dentro de su aplicación. Un usuario MAY tener varios roles, en una o varias aplicaciones, y un cliente OAuth, que pertenece a una aplicación, roles de cualquier aplicación. Sin roles no hay scopes.

#### Scenario: Mismo nombre de rol en dos aplicaciones
- **WHEN** las aplicaciones `portal` y `api-maestros` tienen un rol llamado `lector`
- **THEN** ambos roles existen por separado, cada uno con sus propios scopes

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

### Requirement: Descarte de scopes revocados al renovar
Al renovar con refresh token, los scopes del nuevo token SHALL ser los del token anterior que los roles actuales del usuario en la aplicación del cliente aún conceden; la renovación MUST NOT agregar scopes. Los cambios de roles o de scopes de un rol MUST aplicarse en el siguiente login o refresh, no a tokens ya emitidos.

#### Scenario: El rol perdió un scope
- **WHEN** un usuario obtuvo un token con `user:read user:write`, luego se retira `user:write` de su rol y renueva la sesión
- **THEN** la renovación responde 200 y el nuevo token contiene solo `user:read`

#### Scenario: Se agrega un scope al rol
- **WHEN** un usuario obtuvo un token con `user:read`, luego se concede `user:write` a su rol y renueva la sesión
- **THEN** el nuevo token contiene solo `user:read` y el scope nuevo llega en su siguiente login

#### Scenario: Usuario sin scopes renueva
- **WHEN** un usuario obtuvo un token sin scopes, luego se le asigna un rol con scopes y renueva la sesión
- **THEN** el nuevo token sigue sin scopes

### Requirement: Scopes del token en el JWT
Todo access token SHALL llevar sus scopes en la claim `scopes` del JWT, de modo que las APIs consumidoras puedan autorizar sin consultar al servicio.

#### Scenario: Scopes visibles en el token
- **WHEN** se decodifica el payload de un access token emitido con `user:read`
- **THEN** la claim `scopes` es `["user:read"]`

### Requirement: Roles en el token
Todo access token de usuario SHALL llevar en la claim `roles` los nombres de los roles del usuario que pertenecen a la aplicación del cliente. Un token de cliente SHALL llevar los roles del cliente que conceden al menos uno de sus scopes. Al renovar, la claim MUST calcularse con los roles actuales del usuario, no copiarse del token anterior.

#### Scenario: Roles de un usuario en su aplicación
- **WHEN** un usuario con el rol `vendedor` de `portal` y el rol `user` de `api-auth` obtiene un token con un cliente de `portal`
- **THEN** la claim `roles` es `["vendedor"]`

#### Scenario: Roles de un token de cliente dirigido a una API
- **WHEN** un cliente con el rol `lector` de `api-maestros` y el rol `lector` de `api-matricula` pide un token con `api=api-maestros`
- **THEN** la claim `roles` es `["lector"]` y corresponde al rol de `api-maestros`

#### Scenario: Se retira un rol al renovar
- **WHEN** un usuario obtuvo un token con el rol `admin` y luego se le retira ese rol y renueva la sesión
- **THEN** la claim `roles` del nuevo token no incluye `admin`

#### Scenario: Se agrega un rol al renovar
- **WHEN** un usuario obtuvo un token con el rol `user` y luego se le asigna `admin` y renueva la sesión
- **THEN** la claim `roles` del nuevo token incluye `admin` y los scopes no cambian hasta su siguiente login

### Requirement: Identificador del usuario en el token
Todo access token de usuario SHALL llevar en la claim `uuid` el identificador público del usuario. La claim `sub` MUST seguir siendo el identificador interno. Un token de cliente MUST NOT llevar la claim `uuid`.

#### Scenario: Token de usuario
- **WHEN** se decodifica el payload de un access token de usuario
- **THEN** la claim `uuid` es el `uuid` del usuario y `sub` es su `id`

#### Scenario: Token renovado
- **WHEN** un usuario renueva su sesión con el refresh token
- **THEN** el nuevo token lleva la misma claim `uuid`

#### Scenario: Token de cliente
- **WHEN** se decodifica el payload de un token de client credentials
- **THEN** el token no tiene la claim `uuid`

### Requirement: Token de usuario sin scopes solicitados
Cuando un usuario obtiene un token de usuario sin solicitar scopes, el token MUST llevar todos los scopes que conceden sus roles en la aplicación del cliente. No hay otros scopes por defecto.

#### Scenario: Token de usuario sin scope
- **WHEN** un usuario con el rol `vendedor` de `portal` obtiene un token con un cliente de `portal`, sin `scope`
- **THEN** el token contiene todos los scopes que concede `vendedor`

### Requirement: Roles y aplicaciones de baja no conceden scopes
Un rol con `disabled_at` o de una aplicación con `disabled_at` MUST NOT conceder scopes en tokens de usuario ni de cliente, ni incluirse en la claim `roles`. Los scopes de una aplicación con `disabled_at` MUST NOT concederse en ningún token. Las asignaciones no cambian: al quitar la baja, todo vuelve a concederse en el siguiente token.

#### Scenario: Rol de baja
- **GIVEN** una persona con el rol `pago-honorarios/revisor`, que da `honorarios:leer`
- **WHEN** el rol se da de baja y la persona obtiene un token con un cliente de `pago-honorarios`
- **THEN** el token no trae `honorarios:leer` ni el rol `revisor`

#### Scenario: Rol reactivado
- **WHEN** el rol vuelve a estar activo y la persona obtiene otro token
- **THEN** el token trae `honorarios:leer` y el rol `revisor`
