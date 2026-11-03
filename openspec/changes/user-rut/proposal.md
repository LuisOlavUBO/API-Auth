# Proposal

## Why

Varios sistemas de la UBO identifican a las personas por RUT (por ejemplo, honorarios con las boletas). Guardarlo en IAM permite que los satélites lo obtengan del mismo lugar donde obtienen al usuario, sin que cada uno lo pida y lo guarde por su cuenta.

## What Changes

- `users` tiene una columna `rut` opcional, única cuando existe y siempre guardada en el mismo formato: sin puntos, con guion y con el dígito verificador en mayúscula (`12345678-K`).
- `POST /api/auth/v1/register` acepta un `rut` opcional. Se valida el formato y el dígito verificador, se exige que no esté registrado y se normaliza antes de guardarlo. Acepta el RUT con o sin puntos.
- El perfil (`/v1/me` y `user` de introspect) incluye `rut` (`null` si el usuario no tiene).
- El RUT no viaja en el JWT, porque cualquiera que tenga el token puede leer su contenido y el RUT es un dato personal.

Fuera de alcance: claim `rut` en el token, editar el RUT después del registro, un comando para asignarlo, obtenerlo desde Entra ID y buscar usuarios por RUT.

## Capabilities

### New Capabilities
<!-- Ninguna -->

### Modified Capabilities
- `user-authentication`: el registro acepta `rut`, el usuario tiene un RUT opcional y el perfil lo incluye.

## Impact

- Base de datos: columna `users.rut` con índice único filtrado (solo filas con RUT).
- Código: `RegisterRequest`, una regla de validación nueva para el RUT, `User` (fillable) y `UserResource`.
- Documentación: `Usuario` y el body de registro en `docs/openapi/`, y las requests de Registrar y Perfil en `docs/bruno/`.
- Depende de `user-uuid`: el requisito del perfil se escribe con `uuid` ya incluido, así que `user-uuid` debe archivarse antes.
