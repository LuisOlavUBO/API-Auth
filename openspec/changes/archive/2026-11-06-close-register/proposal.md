# Proposal

## Why

`POST /api/auth/v1/register` permite que cualquiera cree una cuenta sin verificar el email. Como el login con Microsoft vincula por email y conserva la contraseña, alguien puede registrar el email de otra persona y seguir entrando con su clave cuando esa persona reciba roles. Además, el alta de usuarios en IAM debe hacerse desde el portal de administración, no por autoservicio.

## What Changes

- **BREAKING**: se elimina `POST /api/auth/v1/register`. Pasa a responder 404 como cualquier ruta inexistente.
- Se elimina el rol por defecto que recibía el usuario registrado, junto con su configuración (`OAUTH_DEFAULT_ROLE` y `OAUTH_APPLICATION`), que solo usaba el registro.
- Mientras no exista el portal, los usuarios se crean al entrar por primera vez con Microsoft Entra ID (sin roles) y reciben roles con `oauth:assign-role`. Esto ya funciona así y no cambia.
- El límite de intentos de credenciales queda solo para login y refresh.

Fuera de alcance: el portal de administración, un comando de alta de usuarios, anular la contraseña de los usuarios existentes al vincularlos con Microsoft y revisar las cuentas que ya se crearon por registro.

## Capabilities

### New Capabilities
<!-- Ninguna -->

### Modified Capabilities
- `user-authentication`: se quitan el registro y el rol por defecto, y los requisitos que hablaban de "registrarse" pasan a hablar de "crear" un usuario.

## Impact

- Código: ruta y acción `register` de `AuthController`, `RegisterRequest`, claves `default_role` y `application` de `config/oauth.php`.
- Tests: los de registro en `AuthTest` se reemplazan por uno que comprueba que la ruta ya no existe.
- Documentación: `/v1/register` en `docs/openapi/`, `Usuarios/Registrar.bru` y menciones en `docs/bruno/collection.bru`, y el README.
- Clientes: ningún satélite usa el registro (los satélites entran por el login central); quien lo use recibirá 404.
