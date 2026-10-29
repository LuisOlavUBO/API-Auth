# service-tokens Specification

## Purpose

Permite que otros sistemas (satélites y microservicios) obtengan tokens propios para llamar a APIs, ya sea con scopes de varias APIs o con un token dirigido a una sola API que la nombra en su audiencia.

## Requirements

### Requirement: Endpoint OAuth2 de emisión de tokens
El sistema SHALL exponer el endpoint OAuth2 estándar en `POST /api/auth/oauth/token`, sin versión, con límite de solicitudes. Es el único endpoint OAuth2 publicado: autorización, dispositivos y demás rutas OAuth MUST NOT estar disponibles, ni fuera del prefijo `/api/auth`.

#### Scenario: Solo existe el endpoint de token con prefijo
- **WHEN** se inspeccionan las rutas publicadas
- **THEN** existe `api/auth/oauth/token` y no existe ninguna ruta que empiece por `oauth/`

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
Una solicitud client credentials con el parámetro `api` SHALL emitir un token solo para esa API. Sin `scope`, el token MUST llevar todos los scopes que los roles del cliente conceden en esa API; con `scope`, solo los solicitados, que deben estar concedidos en esa API.

#### Scenario: Token para una API sin indicar scopes
- **WHEN** un cliente con roles sobre `api-maestros` y `api-matricula` pide un token con `api=api-maestros`
- **THEN** el token contiene únicamente los scopes del cliente en `api-maestros`

#### Scenario: Token para una API acotado a algunos scopes
- **WHEN** el cliente pide `api=api-maestros` con `scope=geografia:leer`
- **THEN** el token contiene solo `geografia:leer`

#### Scenario: Scope de otra API en un token dirigido
- **WHEN** el cliente pide `api=api-maestros` con `scope=matricula:leer`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope`

### Requirement: Rechazo de APIs desconocidas o sin scopes
Una solicitud con `api` MUST rechazarse en lugar de emitir un token vacío cuando la API no existe (400, `invalid_request`, `Unknown API [<api>].`) o cuando el cliente no tiene scopes en ella (400, `invalid_scope`, `The client has no scopes in API [<api>].`).

#### Scenario: API desconocida
- **WHEN** un cliente pide un token con `api=api-bancos`, que no está registrada
- **THEN** el sistema responde 400 con `error` igual a `invalid_request` y `error_description` igual a `Unknown API [api-bancos].`

#### Scenario: API sin scopes para el cliente
- **WHEN** un cliente sin scopes en `api-matricula` pide un token con `api=api-matricula` y sin `scope`
- **THEN** el sistema responde 400 con `error` igual a `invalid_scope` y `error_description` igual a `The client has no scopes in API [api-matricula].`

### Requirement: Audiencia de los tokens
La claim `aud` de todo access token SHALL comenzar con el `client_id` que lo obtuvo. Un token dirigido a una API MUST agregar el nombre de esa API después del `client_id`, para que cada API pueda rechazar tokens emitidos para otra.

#### Scenario: Token dirigido a una API
- **WHEN** un cliente obtiene un token con `api=api-maestros`
- **THEN** `aud` es `[<client_id>, "api-maestros"]`

#### Scenario: Token sin API
- **WHEN** el mismo cliente obtiene un token sin `api`
- **THEN** `aud` es `[<client_id>]`
