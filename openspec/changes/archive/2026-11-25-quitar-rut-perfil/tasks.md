# Tasks

## 1. Quitar el RUT de las respuestas

- [x] 1.1 Quitar `rut` de `UserResource` y ajustar `AuthTest::test_user_can_fetch_the_profile` y `TokenIntrospectionTest` para comprobar que `rut` y `user.rut` no vienen; verificar con `php artisan test --compact` sobre ambos archivos
- [x] 1.2 Quitar `rut` del esquema `Usuario` en `docs/openapi/openapi.yaml` y de los ejemplos de `docs/bruno/Usuarios/Perfil.bru` y `docs/bruno/Servicios/Introspeccion.bru`; verificar con el test de sincronía de OpenAPI

## Workflow follow-up

- Archivar el cambio con `/opsx:archive` tras verificar.
