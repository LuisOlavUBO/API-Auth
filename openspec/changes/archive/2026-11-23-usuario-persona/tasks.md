# Tasks

Los tests se corren contra el SQL Server de Docker (`DB_HOST=127.0.0.1 DB_PORT=14334 DB_DATABASE=apiauth_test DB_USERNAME=sa`, contraseña en `DOCKER_MSSQL_SA_PASSWORD`).

## 1. Columna id_persona

- [x] 1.1 Migración: `users.id_persona` (`unsignedBigInteger`, nullable, después de `uuid`) con índice único filtrado `WHERE id_persona IS NOT NULL`, igual que `rut`; `id_persona` en `fillable` de `User` y estado `withPersona(int $idPersona)` en `UserFactory`; verificar con `migrate:fresh` contra la base de tests y tests de que dos usuarios sin `id_persona` conviven y dos con el mismo no.

## 2. Perfil

- [x] 2.1 Agregar `id_persona` a `UserResource`; verificar con tests de `/v1/me` (con y sin `id_persona`) y de introspección (`user.id_persona`).
- [x] 2.2 Documentar `id_persona` en el esquema `Usuario` de `docs/openapi/` y en el ejemplo de Perfil de `docs/bruno/` si muestra los campos; verificar revisando que coincide con la respuesta de `/v1/me`.
- [x] 2.3 Correr `vendor/bin/pint --dirty --format agent` y la suite completa; verificar que todo pasa.

## Workflow follow-up

- Avisar a API-IAM de la columna nueva para que la cargue al crear o editar usuarios.
- `/opsx:archive` al terminar.
