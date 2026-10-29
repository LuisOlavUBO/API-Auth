# scope-authorization Specification

## Purpose

Define qué permisos (scopes) puede llevar cada token: los scopes existen en un catálogo administrado, pertenecen a una API y se conceden mediante roles asignados a usuarios y a clientes OAuth.

## Requirements

### Requirement: Catálogo de scopes
Solo SHALL existir los scopes registrados en el catálogo del servicio. Cada scope MUST pertenecer a exactamente una API (servidor de recursos). Un scope que no está en el catálogo, incluido el comodín `*`, MUST tratarse como inexistente.

#### Scenario: Se solicita un scope que no existe
- **WHEN** un login solicita el scope `does-not-exist` o `*`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope`

### Requirement: Scopes concedidos por roles
Un rol SHALL agrupar un conjunto de scopes del catálogo. Cada usuario MUST tener como máximo un rol, global para todo el servicio; un cliente OAuth MAY tener varios roles y recibe la unión de sus scopes. Usuarios y clientes sin rol no tienen scopes concedidos.

#### Scenario: Cliente con varios roles
- **WHEN** un cliente tiene un rol que concede `geografia:leer` y otro que concede `matricula:leer`, y solicita ambos scopes
- **THEN** el token emitido contiene `geografia:leer` y `matricula:leer`

#### Scenario: Usuario sin rol
- **WHEN** un usuario sin rol inicia sesión
- **THEN** recibe un access token sin scopes, que es rechazado con 403 en `GET /api/auth/v1/me`

### Requirement: Scopes por defecto en el login
Cuando un login de usuario no envía `scope`, el sistema SHALL solicitar los scopes por defecto (`user:read`). Un scope por defecto que el rol del usuario no concede MUST descartarse sin rechazar el login.

#### Scenario: Login sin scope
- **WHEN** un usuario con el rol `user` inicia sesión sin enviar `scope`
- **THEN** el token contiene exactamente `user:read`

### Requirement: Rechazo de scopes no concedidos al emitir
En el login de usuarios y en la emisión de tokens para clientes, solicitar explícitamente un scope que el rol (o los roles del cliente) no concede MUST rechazarse con 400 e `invalid_scope`. Los scopes solicitados y concedidos SHALL incluirse en el token.

#### Scenario: Usuario solicita scopes de su rol
- **WHEN** un usuario con el rol `user` solicita `user:read user:write`
- **THEN** el token contiene `user:read` y `user:write`

#### Scenario: Usuario solicita un scope ajeno a su rol
- **WHEN** un usuario con el rol `user` solicita `introspect`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope`

### Requirement: Descarte de scopes revocados al renovar
Al renovar con refresh token, los scopes que el rol del usuario ya no concede SHALL descartarse del nuevo token en lugar de rechazar la renovación. Los cambios de rol o de scopes de un rol MUST aplicarse en el siguiente login o refresh, no a tokens ya emitidos.

#### Scenario: El rol perdió un scope
- **WHEN** un usuario obtuvo un token con `user:read user:write`, luego se retira `user:write` de su rol y renueva la sesión
- **THEN** la renovación responde 200 y el nuevo token contiene solo `user:read`

### Requirement: Scopes del token en el JWT
Todo access token SHALL llevar sus scopes en la claim `scopes` del JWT, de modo que las APIs consumidoras puedan autorizar sin consultar al servicio.

#### Scenario: Scopes visibles en el token
- **WHEN** se decodifica el payload de un access token emitido con `user:read`
- **THEN** la claim `scopes` es `["user:read"]`
