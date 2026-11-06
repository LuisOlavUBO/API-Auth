# Tasks

## 1. Quitar el registro

- [x] 1.1 Borrar la ruta `register` de `routes/api.php`, la acción `register` de `AuthController` (y sus imports sin uso) y `RegisterRequest`. Verificar con un test en `AuthTest` que `POST /api/auth/v1/register` con datos válidos responde 404 y no crea usuarios.
- [x] 1.2 Borrar de `config/oauth.php` las claves `default_role` y `application` y ajustar los comentarios que mencionan el registro; corregir la descripción del rol `user` en `RoleScopeSeeder`. Verificar con `grep` que nada más usa esas claves.
- [x] 1.3 Borrar de `AuthTest` los tests de registro, RUT por registro y rol por defecto, y el helper `register()`. Verificar que `AuthTest` pasa.

## 2. Documentación

- [x] 2.1 Quitar `/api/auth/v1/register` (y esquemas que queden sin uso) de `docs/openapi/openapi.yaml`, borrar `docs/bruno/Usuarios/Registrar.bru` y quitar las menciones al registro de `docs/bruno/collection.bru` y del README (incluida `OAUTH_DEFAULT_ROLE`). Verificar que `DocsTest` pasa.

## 3. Cierre

- [x] 3.1 Correr `vendor/bin/pint --dirty --format agent` y la suite completa con la base de tests en Docker, y verificar que todo pasa.
