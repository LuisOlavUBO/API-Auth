# Spec Delta

## MODIFIED Requirements

### Requirement: Documentación OpenAPI publicada
Con la variable `API_DOCS` encendida (por omisión), el sistema SHALL servir sin autenticación Swagger UI en `/api/auth/docs/swagger` y la especificación OpenAPI 3.1 en `/api/auth/docs/openapi.yaml` (`application/yaml`), y la raíz `/` y `/docs/swagger` MUST redirigir a `/api/auth/docs/swagger`. Con `API_DOCS=false`, esas cuatro rutas MUST responder 404; las de `/api/auth/docs/` en formato Problem Details. El descubrimiento OpenID Connect y `v1/public-key` MUST NOT depender de esta variable.

#### Scenario: Redirección a la documentación
- **WHEN** `API_DOCS` no está definida y se accede a `/` o a `/docs/swagger`
- **THEN** se redirige a `/api/auth/docs/swagger`

#### Scenario: Especificación servida como YAML
- **WHEN** `API_DOCS` no está definida y se descarga `/api/auth/docs/openapi.yaml`
- **THEN** la respuesta es `application/yaml` y declara `openapi: 3.1.0`

#### Scenario: Documentación apagada
- **WHEN** `API_DOCS=false` y se pide `/api/auth/docs/swagger` o `/api/auth/docs/openapi.yaml`
- **THEN** la respuesta es 404 en formato Problem Details (`application/problem+json`)

#### Scenario: Sin redirección con la documentación apagada
- **WHEN** `API_DOCS=false` y se accede a `/` o a `/docs/swagger`
- **THEN** la respuesta es 404 y no redirige a Swagger

#### Scenario: Descubrimiento disponible con la documentación apagada
- **WHEN** `API_DOCS=false` y se piden `/api/auth/.well-known/openid-configuration`, `/api/auth/.well-known/jwks.json` y `/api/auth/v1/public-key`
- **THEN** las tres responden 200
