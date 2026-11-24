# Tasks

## 1. Variable y rutas

- [x] 1.1 Agregar `'docs' => (bool) env('API_DOCS', true)` a `config/app.php` y la línea comentada `# API_DOCS=true` (con una nota: `false` apaga Swagger) a `.env.example`; verificar con `php artisan config:show app.docs`.
- [x] 1.2 Registrar `docs/swagger` y `docs/openapi.yaml` de `routes/api.php`, y las redirecciones de `/` y `/docs/swagger` de `routes/web.php`, solo si `config('app.docs')`; verificar que `php artisan route:list --path=docs` las lista con la variable sin definir.
- [x] 1.3 En `tests/Feature/DocsTest.php`, agregar tests con `API_DOCS=false` (variable de entorno + `refreshApplication()`, restaurada al terminar): las dos rutas de `/api/auth/docs/` responden 404 `application/problem+json`, `/` y `/docs/swagger` responden 404, y `/.well-known/openid-configuration`, `/.well-known/jwks.json` y `v1/public-key` responden 200; verificar que `php artisan test --compact tests/Feature/DocsTest.php` pasa (también los tests existentes, con la variable encendida).

## 2. Documentación

- [x] 2.1 En el README: en la línea de **Documentación**, indicar que `API_DOCS=false` la apaga; en **Variables de despliegue**, agregar `API_DOCS` (encendida por omisión; `false` en QA, reiniciar el contenedor tras cambiarla). Verificar leyendo ambas secciones.
