# Tasks

## 1. Decodificar Basic

- [x] 1.1 Crear un middleware que, si la solicitud trae `Authorization: Basic`, reescriba la cabecera con `urldecode` del usuario y de la contraseña, y aplicarlo a `oauth/token` en `routes/api.php` antes del `throttle`.
- [x] 1.2 En `tests/Feature/OpenIdConnectTest.php`, agregar un test: un canje con `Authorization: Basic` cuyo id y secreto van codificados (`%2D` en el id) recibe los tokens; verificar que `php artisan test --compact tests/Feature/OpenIdConnectTest.php` pasa (también el test existente sin codificar).

## 2. Documentación

- [x] 2.1 En `docs/openapi/openapi.yaml`, indicar en `oauth/token` que con `client_secret_basic` el id y el secreto pueden ir codificados (RFC 6749 §2.3.1).
