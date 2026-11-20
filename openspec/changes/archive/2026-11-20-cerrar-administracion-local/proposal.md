# Proposal

## Why

API-IAM ya administra usuarios, catálogo y clientes desde su portal, con auditoría y reglas contra el escalamiento de privilegios. API-Auth todavía crea usuarios al entrar con Microsoft y ofrece los comandos `oauth:*`, que escriben las mismas tablas sin pasar por esas reglas. Es el segundo cambio que dejó pendiente `account-lifecycle`, y conviene hacerlo antes del primer paso a producción, cuando todavía no hay usuarios creados por Microsoft ni nadie usa los comandos.

## What Changes

- **BREAKING** El login con Microsoft deja de crear usuarios: solo entra quien ya tiene un usuario (por `oid` o, la primera vez, por email). Sin usuario, vuelve al login con "No pudimos asociar tu cuenta de Microsoft, contacta a soporte" y se registra `login_fallido` con motivo `sin_cuenta`.
- **BREAKING** Se eliminan los comandos `oauth:application`, `oauth:role`, `oauth:scope`, `oauth:assign-role`, `oauth:client`, `oauth:client-role`, `oauth:client-scopes`, `oauth:client-application` y `oauth:role-scopes`. Su reemplazo es el portal de API-IAM; `oauth:client-application` (mover un cliente de aplicación) no tiene equivalente y se pierde.
- `RoleScopeSeeder` se mantiene solo para desarrollo y tests. En producción el catálogo base lo crea `iam:install` de API-IAM.
- README: la administración del catálogo, los roles y los clientes pasa a "se hace en el portal de IAM", y la sección "Despliegue" agrega el orden de la primera instalación (API-Auth primero, después API-IAM con `iam:install`; nunca API-IAM primero en una base vacía).

Fuera de alcance: mover las migraciones del catálogo a API-IAM, los permisos por tabla en SQL Server y "olvidé mi contraseña". OpenAPI no cambia (no se toca ningún endpoint); en Bruno solo cambia el texto de las carpetas que mostraban los comandos.

## Capabilities

### New Capabilities
<!-- Ninguna -->

### Modified Capabilities
- `central-login`: el usuario de Microsoft ya no se crea; sin usuario no inicia sesión. El login web deja de mencionar usuarios "creados al entrar con Microsoft".
- `access-administration`: se eliminan todos sus requisitos (comandos de consola y catálogo base sembrado); la capacidad desaparece.

## Impact

- Código: `User::fromMicrosoft`, `MicrosoftLoginController` (motivo del evento), `app/Console/Commands/*` (se borran los 9 comandos y `Concerns`).
- Tests: `MicrosoftLoginTest` (persona nueva), `OAuthScopeCommandsTest` (se borra), `ClientScopesTest` (se reescribe sin comandos para conservar la cobertura del token de cliente).
- Documentación: README y el texto de dos carpetas de Bruno.
- Operación: sin API-IAM instalado no hay forma de crear usuarios ni clientes; los dos servicios se despliegan juntos.
