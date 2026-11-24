# Proposal

## Why

En QA (`authqa.ubo.cl`) Swagger es público y expone el mapa completo de la API (introspect, revoke, scopes) a quien no lo necesita: Ucampus se integra solo con el descubrimiento OIDC. No se puede condicionar por `APP_ENV`, porque `docker-compose.prod.yml` fija `APP_ENV=production` en todos los ambientes, incluido desarrollo (`authdev.ubo.cl`), donde Swagger debe seguir visible.

## What Changes

- Nueva variable de entorno `API_DOCS` (encendida por omisión). En `false`:
  - `GET /api/auth/docs/swagger` y `GET /api/auth/docs/openapi.yaml` responden 404 en formato Problem Details.
  - `/` y `/docs/swagger` dejan de redirigir a Swagger y responden 404.
- El descubrimiento OIDC (`/.well-known/openid-configuration`, `/.well-known/jwks.json`) y `v1/public-key` no cambian: siguen siempre públicos.
- Sin la variable (o en `true`) el comportamiento es el actual; no hay cambios incompatibles.

Fuera de alcance: proteger Swagger con login o por IP, y cambios en otras APIs.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `service-operations`: la documentación OpenAPI publicada pasa a poder apagarse con `API_DOCS`.

## Impact

- `routes/api.php`, `routes/web.php` y `config/app.php` (la variable).
- `.env.example`, `tests/Feature/DocsTest.php` y README (Despliegue: apagarla en QA con `API_DOCS=false` en el `.env.prod`).
- `docker-compose.prod.yml` no cambia: el valor se define por servidor en su `.env.prod`.
- Sin cambios en OpenAPI ni Bruno (las rutas de documentación no se documentan a sí mismas). Se publicará como imagen 5.1.2.
