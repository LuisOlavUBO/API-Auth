# Spec Delta

## Purpose

Cubre lo necesario para operar el servicio detrás del proxy compartido: dónde se publican sus rutas, cómo se informa su salud, dónde está su documentación y cómo se continúan las trazas de quien llama.

## ADDED Requirements

### Requirement: Prefijo público de rutas
Todas las rutas de la API SHALL publicarse bajo `/api/auth`, con los endpoints funcionales en `/api/auth/v1`, de modo que la URL interna y la pública coincidan detrás del proxy compartido, que enruta por el segmento posterior a `/api`.

#### Scenario: Endpoints versionados bajo el prefijo
- **WHEN** se consulta la lista de rutas publicadas
- **THEN** los endpoints funcionales están bajo `/api/auth/v1` y el endpoint OAuth2 está en `/api/auth/oauth/token`

### Requirement: Endpoint de salud
`GET /api/auth/v1/salud` SHALL comprobar la base de datos y responder 200 con exactamente `{"estado": "OK", "base_datos": "OK"}`. Si la base de datos no está disponible, MUST responder 503 con exactamente `{"estado": "ERROR", "base_datos": "NO_DISPONIBLE"}`.

#### Scenario: Servicio sano
- **WHEN** se llama a salud con la base de datos disponible
- **THEN** responde 200 con `{"estado": "OK", "base_datos": "OK"}`

#### Scenario: Base de datos caída
- **WHEN** se llama a salud con la base de datos no disponible
- **THEN** responde 503 con `{"estado": "ERROR", "base_datos": "NO_DISPONIBLE"}`

### Requirement: Documentación OpenAPI publicada
El sistema SHALL servir sin autenticación Swagger UI en `/api/auth/docs/swagger` y la especificación OpenAPI 3.1 en `/api/auth/docs/openapi.yaml` (`application/yaml`). La raíz `/` y `/docs/swagger` MUST redirigir a `/api/auth/docs/swagger`.

#### Scenario: Redirección a la documentación
- **WHEN** se accede a `/` o a `/docs/swagger`
- **THEN** se redirige a `/api/auth/docs/swagger`

#### Scenario: Especificación servida como YAML
- **WHEN** se descarga `/api/auth/docs/openapi.yaml`
- **THEN** la respuesta es `application/yaml` y declara `openapi: 3.1.0`

### Requirement: Especificación sincronizada con las rutas
La especificación OpenAPI MUST documentar cada ruta publicada bajo `/api` (excepto las de documentación), y cada ruta documentada MUST existir.

#### Scenario: Ruta nueva sin documentar
- **WHEN** se agrega una ruta bajo `/api` que no figura en la especificación OpenAPI
- **THEN** la verificación de sincronía entre rutas y especificación falla

### Requirement: Propagación de trazas
Cuando la petición trae una cabecera W3C `traceparent`, la respuesta SHALL continuar esa traza e incluir su identificador en la cabecera `X-Trace-Id`, también en respuestas de error.

#### Scenario: Respuesta exitosa con traza
- **WHEN** se llama a public-key con un `traceparent` cuyo trace id es `4bf92f3577b34da6a3ce929d0e0e4736`
- **THEN** la respuesta incluye `X-Trace-Id: 4bf92f3577b34da6a3ce929d0e0e4736`

#### Scenario: Respuesta de error con traza
- **WHEN** se llama a `GET /api/auth/v1/me` sin token y con un `traceparent`
- **THEN** la respuesta 401 incluye `X-Trace-Id` con el trace id recibido

### Requirement: Validación propia de tokens
El servicio SHALL validar sus propios tokens localmente y MUST NOT delegar la autenticación de sus endpoints en un servicio de autenticación externo ni en otro cliente de API.

#### Scenario: Sin autenticación delegada
- **WHEN** se inspecciona la configuración del servicio
- **THEN** la autenticación externa y el cliente de API de la librería común están desactivados y solo está activo su módulo de trazas
