# Proposal

## Why

Hoy no hay relación entre los usuarios de IAM y las personas de API-Personas, que maneja la información de las personas con un identificador institucional (`id`) compartido entre sistemas. Ucampus identifica a las personas con ese identificador (`id_persona`), y otros satélites lo van a necesitar para cruzar al usuario con sus datos sin usar el RUT. Guardar el vínculo en el usuario es la base del login de Ucampus con OIDC (cambio `oidc`).

## What Changes

- `users` tiene una columna `id_persona` opcional: el `id` de la persona en API-Personas, único entre los usuarios que lo tienen. Queda vacía en usuarios que no son personas (por ejemplo, proveedores externos) o cuya persona aún no existe.
- La carga API-IAM, que administra los usuarios. API-Auth solo la lee: no tiene endpoint ni comando para escribirla.
- El perfil (`/v1/me` y `user` de introspect) incluye `id_persona` (`null` si el usuario no tiene).

Fuera de alcance: cargar o vincular `id_persona` (API-IAM), el alta de usuarios desde API-Personas, quitar el `rut` de `users`, consultar API-Personas desde API-Auth y llevar `id_persona` en los access tokens o en el `id_token` (lo hace el cambio `oidc`).

## Capabilities

### New Capabilities
<!-- Ninguna -->

### Modified Capabilities
- `user-authentication`: el usuario tiene un `id_persona` opcional y el perfil lo incluye.

## Impact

- Base de datos: columna `users.id_persona` (entero, nullable, índice único filtrado como `rut` y `entra_oid`).
- Código: `User` (`fillable`), `UserFactory` (estado con persona) y `UserResource`.
- Documentación: esquema `Usuario` en `docs/openapi/` y el ejemplo de Perfil en `docs/bruno/` si muestra los campos.
- API-IAM: debe poder escribir la columna nueva (tabla compartida).
