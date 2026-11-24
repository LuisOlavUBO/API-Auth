# Design

## Context

Las rutas de documentación se registran sin condición en `routes/api.php` (`docs/swagger`, `docs/openapi.yaml`) y `routes/web.php` (redirecciones de `/` y `/docs/swagger`). En el contenedor de producción, `docker-entrypoint.prod.sh` ejecuta `php artisan optimize` al arrancar, con el `.env.prod` ya cargado, así que la configuración y las rutas se cachean en cada ambiente y no al construir la imagen.

## Goals / Non-Goals

**Goals:** apagar las cuatro rutas con una variable, sin tocar las demás.

**Non-Goals:** cambiar el contenido de Swagger o de la especificación OpenAPI.

## Decisions

- **Variable en `config/app.php`**: `'docs' => (bool) env('API_DOCS', true)`, y las rutas leen `config('app.docs')`. Así funciona con la caché de configuración (no se llama a `env()` fuera de `config/`). Alternativa descartada: un archivo `config/docs.php` propio, que sería un archivo más para una sola clave.
- **Registrar las rutas solo si está encendida** (`if (config('app.docs')) { ... }` en ambos archivos de rutas). Una ruta inexistente ya da 404, y bajo `/api/*` `ProblemRenderer` lo responde como Problem Details sin código nuevo. Funciona con `route:cache` porque la caché se genera al arrancar el contenedor, con la variable del ambiente. Alternativa descartada: un middleware que haga `abort(404)`, que agrega una clase para el mismo resultado.
- **404 fuera de `/api`**: `/` y `/docs/swagger` responden el 404 por omisión de Laravel (HTML); Problem Details solo aplica bajo `/api`, como hoy.
- **Tests**: las rutas se leen al crear la aplicación, así que el test de documentación apagada define `API_DOCS=false` en el entorno y llama a `refreshApplication()`, restaurando la variable al terminar.

## Risks / Trade-offs

- [Cambiar `API_DOCS` sin reiniciar el contenedor no tiene efecto, por la caché de rutas] → El README indica reiniciar (`docker compose ... up -d`) tras cambiar el `.env.prod`, igual que las demás variables.
