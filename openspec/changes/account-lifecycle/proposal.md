# Proposal

## Why

La administración de IAM se moverá a una API aparte (API-IAM), que será dueña de los usuarios y del catálogo, mientras API-Auth queda solo para autenticar y autorizar. Para eso API-Auth tiene que respetar tres decisiones que tomará API-IAM: dar de baja a un usuario, crearlo con una contraseña temporal que se cambia en el primer acceso, y cerrar sus sesiones cuando cambian sus accesos. Hoy no existe ninguna de las tres.

## What Changes

- **Usuarios dados de baja** (`users.disabled_at`): no pueden iniciar sesión con contraseña en el login web ni con Microsoft, ni canjear códigos o renovar tokens, y la introspección reporta sus tokens como inactivos.
- **Contraseña temporal** (`users.temporary_password_expires_at`): quien entra al login web con una contraseña temporal vigente debe cambiarla en una página nueva (`/api/auth/password`) antes de seguir a su sistema. Una contraseña temporal vencida no sirve.
- **Revocación por un servicio**: `POST /api/auth/v1/users/{uuid}/revoke`, con token de cliente y scope `sessions:revoke`, revoca todos los tokens del usuario y cierra su sesión de IAM.

Fuera de alcance: quién escribe `disabled_at` y la contraseña temporal (lo hará API-IAM; hasta entonces solo los tests), quitar el alta automática con Microsoft, eliminar los comandos `oauth:*`, mover las migraciones del catálogo a API-IAM, los permisos por tabla en SQL Server, y "olvidé mi contraseña". Todo eso va en un segundo cambio, cuando API-IAM ya administre. Este cambio se implementa después de `close-password-login`, que elimina `/v1/login` y `/v1/refresh`.

## Capabilities

### New Capabilities
<!-- Ninguna -->

### Modified Capabilities
- `user-authentication`: el canje de código y el refresh rechazan usuarios dados de baja; nueva revocación de sesiones por un servicio.
- `central-login`: el login web y el de Microsoft rechazan usuarios dados de baja; cambio obligatorio de la contraseña temporal.
- `token-introspection`: los tokens de un usuario dado de baja son inactivos.

## Impact

- Base de datos: dos columnas nullable en `users`.
- Código: `User` (estado de la cuenta), `ScopeRepository` (último punto común de todas las emisiones de tokens de usuario), `LoginController`, `MicrosoftLoginController`, `TokenIntrospectionController`, un controlador y una vista de cambio de contraseña, un middleware en `oauth/authorize` y un controlador de revocación.
- Datos: scope `sessions:revoke` de `api-auth` en `RoleScopeSeeder`.
- Documentación: OpenAPI, Bruno y README.
