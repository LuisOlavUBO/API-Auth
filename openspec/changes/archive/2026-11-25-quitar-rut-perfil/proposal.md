# Proposal

## Why

El RUT es un dato personal sensible y ningún consumidor de `GET /api/auth/v1/me` ni de la introspección lo necesita. Hoy ambos lo devuelven porque comparten el mismo perfil de usuario.

## What Changes

- **BREAKING**: `GET /api/auth/v1/me` deja de devolver `rut`.
- **BREAKING**: `POST /api/auth/v1/introspect` deja de devolver `user.rut`.
- El RUT sigue guardado en `users` (opcional, único y normalizado), y sigue sin viajar en los tokens, el `id_token` ni `userinfo`.

Fuera de alcance: eliminar la columna `rut`, cambiar su formato o su validación, y cambiar los claims de OpenID Connect.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `user-authentication`: el perfil de `/me` ya no incluye `rut`, y la introspección ya no expone `user.rut`.

## Impact

- Código: `UserResource`, que usan `/me` e `introspect`.
- Tests: `AuthTest` y `TokenIntrospectionTest`.
- Docs: esquema `Usuario` en `docs/openapi/openapi.yaml` y ejemplos de Bruno (`Usuarios/Perfil.bru`, `Servicios/Introspeccion.bru`).
- Satélites que lean `rut` o `user.rut`: dejan de recibirlo.
