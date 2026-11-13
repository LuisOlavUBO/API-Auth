# service-tokens Specification

## Purpose

Permite que otros sistemas (satélites y microservicios) obtengan tokens propios para llamar a APIs, ya sea con scopes de varias APIs o con un token dirigido a una sola API que la nombra en su audiencia.

## Requirements

### Requirement: Endpoint OAuth2 de emisión de tokens
El sistema SHALL exponer el endpoint OAuth2 estándar de token en `POST /api/auth/oauth/token` y el de autorización en `GET /api/auth/oauth/authorize`, sin versión. Son los únicos endpoints OAuth2 publicados: la aprobación y el rechazo de autorizaciones, los dispositivos y demás rutas OAuth MUST NOT estar disponibles, ni fuera del prefijo `/api/auth`.

#### Scenario: Solo existe el endpoint de token con prefijo
- **WHEN** se inspeccionan las rutas publicadas
- **THEN** existen `api/auth/oauth/token` y `api/auth/oauth/authorize`, y no existe ninguna otra ruta que empiece por `oauth/` ni por `api/auth/oauth/`

### Requirement: Tokens de cliente con client credentials
Un cliente OAuth SHALL obtener un access token con `grant_type=client_credentials`, su `client_id` y su `client_secret`, y un `scope` opcional. Los scopes se limitan a los que conceden sus roles. Un cliente que no solicita scopes recibe un token sin scopes. Los tokens de cliente vencen por defecto a los 60 minutos (configurable).

#### Scenario: Cliente sin roles no solicita scopes
- **WHEN** un cliente sin roles pide un token sin `scope`
- **THEN** el sistema responde 200 con un token cuya claim `scopes` está vacía

#### Scenario: Cliente sin roles solicita un scope
- **WHEN** un cliente sin roles pide un token con `scope=introspect`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope`

#### Scenario: Cliente solicita un scope fuera de sus roles
- **WHEN** un cliente cuyo rol solo concede scopes de `api-maestros` solicita `matricula:leer`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope`

### Requirement: Token dirigido a una API
Una solicitud client credentials con el parámetro `api`, que nombra una aplicación registrada, SHALL emitir un token solo para esa aplicación. Sin `scope`, el token MUST llevar todos los scopes de esa aplicación que conceden los roles del cliente, sean de la aplicación que sean los roles; con `scope`, solo los solicitados, que deben estar concedidos en esa aplicación.

#### Scenario: Token para una API sin indicar scopes
- **WHEN** un cliente con roles sobre `api-maestros` y `api-matricula` pide un token con `api=api-maestros`
- **THEN** el token contiene únicamente los scopes del cliente en `api-maestros`

#### Scenario: Token para una API acotado a algunos scopes
- **WHEN** el cliente pide `api=api-maestros` con `scope=geografia:leer`
- **THEN** el token contiene solo `geografia:leer`

#### Scenario: Scope de otra API en un token dirigido
- **WHEN** el cliente pide `api=api-maestros` con `scope=matricula:leer`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope`

#### Scenario: Rol de otra aplicación en un token dirigido
- **WHEN** un cliente de `portal` con el rol `lector` de `api-maestros`, que concede `geografia:leer`, pide un token con `api=api-maestros`
- **THEN** el token contiene `geografia:leer`

### Requirement: Rechazo de APIs desconocidas o sin scopes
Una solicitud con `api` MUST rechazarse en lugar de emitir un token vacío cuando la API no existe (400, `invalid_request`, `Unknown API [<api>].`) o cuando el cliente no tiene scopes en ella (400, `invalid_scope`, `The client has no scopes in API [<api>].`).

#### Scenario: API desconocida
- **WHEN** un cliente pide un token con `api=api-bancos`, que no está registrada
- **THEN** el sistema responde 400 con `error` igual a `invalid_request` y `error_description` igual a `Unknown API [api-bancos].`

#### Scenario: API sin scopes para el cliente
- **WHEN** un cliente sin scopes en `api-matricula` pide un token con `api=api-matricula` y sin `scope`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope` y `error_description` igual a `The client has no scopes in API [api-matricula].`

### Requirement: Audiencia de los tokens
La claim `aud` de todo access token SHALL comenzar con el `client_id` que lo obtuvo. Un token dirigido a una API MUST agregar el nombre de esa API después del `client_id`. Un token de usuario SHALL agregar, en orden alfabético y sin repetir, el nombre de cada aplicación dueña de alguno de sus scopes. Así cada API puede rechazar tokens emitidos para otra.

#### Scenario: Token dirigido a una API
- **WHEN** un cliente obtiene un token con `api=api-maestros`
- **THEN** `aud` es `[<client_id>, "api-maestros"]`

#### Scenario: Token sin API
- **WHEN** el mismo cliente obtiene un token sin `api`
- **THEN** `aud` es `[<client_id>]`

#### Scenario: Token de usuario con scopes de varias aplicaciones
- **WHEN** un usuario cuyo rol concede `portal:ventas.crear` y `geografia:leer` obtiene un token con un cliente de `portal`
- **THEN** `aud` es `[<client_id>, "api-maestros", "portal"]`

#### Scenario: Token de usuario sin scopes
- **WHEN** un usuario sin roles en la aplicación del cliente obtiene un token
- **THEN** `aud` es `[<client_id>]`

### Requirement: Token de cliente sin api ni scope
Una solicitud client credentials sin `api` y sin `scope` SHALL emitir un token sin scopes, aunque los roles del cliente concedan alguno. Un token de cliente con scopes se obtiene indicando `api` o `scope`.

#### Scenario: Cliente con roles sin api ni scope
- **WHEN** un cliente cuyo rol concede `user:read` y `geografia:leer` pide un token sin `api` y sin `scope`
- **THEN** el token se emite con la claim `scopes` vacía

### Requirement: Roles de un cliente de cualquier aplicación
Un cliente OAuth SHALL poder recibir roles de cualquier aplicación, no solo de la suya. Lo normal es asignarle los roles que ofrece cada API que consume, y el token para una API solo lleva los scopes de esa API.

#### Scenario: Cliente con roles de varias aplicaciones
- **WHEN** un cliente de `portal` tiene el rol `lector` de `api-maestros` y el rol `lector` de `api-matricula`
- **THEN** puede pedir un token con `api=api-maestros` y otro con `api=api-matricula`, cada uno con los scopes de su API

### Requirement: Límite de solicitudes del endpoint de token
El endpoint de token SHALL limitar las solicitudes por minuto por `client_id` (o por IP cuando no viene), con un máximo configurable. Al superarlo MUST responder 429 con la cabecera `Retry-After`.

#### Scenario: Un cliente supera el límite
- **WHEN** un cliente envía más solicitudes por minuto al endpoint de token que el máximo configurado
- **THEN** responde 429 con la cabecera `Retry-After`

#### Scenario: Otro cliente no se ve afectado
- **WHEN** un cliente superó el límite y otro cliente pide un token en el mismo minuto, desde la misma IP
- **THEN** el segundo recibe su token con normalidad

### Requirement: Id del cliente en minúsculas
El id de un cliente OAuth SHALL aparecer en minúsculas en todo token que emita API-Auth: el primer elemento de `aud`, la claim `sub` de los tokens de cliente y el `client_id` asociado al token, aunque SQL Server lo devuelva en mayúsculas y aunque el cliente lo envíe en mayúsculas al pedir el token.

#### Scenario: Token de cliente
- **WHEN** un cliente pide un token con client credentials, enviando su `client_id` en mayúsculas
- **THEN** `aud[0]` y `sub` del token son el id del cliente en minúsculas

#### Scenario: Token dirigido a una API
- **WHEN** un cliente obtiene un token con `api=api-maestros`
- **THEN** `aud` es `[<client_id en minúsculas>, "api-maestros"]`

#### Scenario: Renovación con refresh token
- **WHEN** una persona renueva su token con un refresh token emitido después de este cambio
- **THEN** el token nuevo se emite y `aud[0]` es el id del cliente en minúsculas
