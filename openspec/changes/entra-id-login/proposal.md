# Proposal

## Why

Las personas de la UBO ya tienen su cuenta institucional en Microsoft Entra ID. Con el login central (entrega 2a) solo pueden entrar a IAM con email y una contraseña propia de IAM. Esta entrega (2b) agrega el botón "Ingresar con Microsoft" al login de IAM, para que entren con su cuenta UBO sin crear ni recordar otra clave. El resto del flujo (código, PKCE, roles, logout) no cambia.

## What Changes

- **Botón "Ingresar con Microsoft"** en `/api/auth/login`, junto al formulario de email y contraseña, que se mantiene. Solo aparece cuando Entra ID está configurado.
- **Rutas nuevas:** `GET /api/auth/login/microsoft` manda a Microsoft y `GET /api/auth/login/microsoft/callback` recibe la respuesta, inicia la sesión de IAM y vuelve al destino original (por ejemplo, la autorización del satélite).
- **Solo el tenant de la UBO:** el inicio de sesión se hace contra el tenant configurado, así que cuentas personales o de otras organizaciones no pueden entrar.
- **Identificación por `oid`:** el usuario se busca por el identificador de objeto de Entra (`users.entra_oid`, nuevo, único cuando existe).
  - Si no hay usuario con ese `oid` pero sí con el mismo email, se vincula guardando el `oid`; su contraseña local sigue funcionando.
  - Si el email ya está vinculado a otro `oid`, no inicia sesión y muestra "contacta a soporte".
  - Si no existe, se crea con nombre y email de Entra, sin contraseña y sin roles: el satélite le mostrará "No tienes acceso a este sistema, contacta a soporte" hasta que se le asignen.
- **`users.password` pasa a ser opcional**, para los usuarios creados desde Entra. Un usuario sin contraseña no puede entrar con email y clave (ni en el login web ni en `/v1/login`).
- **Errores de Microsoft** (cancelación, `state` inválido, respuesta sin `oid`) vuelven al login con un mensaje, sin sesión.
- **Dependencias nuevas (aprobadas):** `laravel/socialite` y `socialiteproviders/microsoft-azure`.

Fuera de alcance: anular la contraseña local al vincular, cerrar el registro público `/v1/register`, la baja de usuarios, actualizar nombre o email desde Entra en logins posteriores, obtener el RUT desde Entra, cerrar la sesión de Microsoft en el logout, forzar la reautenticación en Microsoft con `prompt=login`, y SPAs. El registro de la aplicación en Entra ID aún no existe: se documentan los pasos en el README y los tests simulan a Microsoft.

## Capabilities

### New Capabilities
<!-- Ninguna -->

### Modified Capabilities
- `central-login`: el login web ofrece entrar con Microsoft Entra ID, con identificación por `oid`, vinculación por email y alta sin roles.
- `user-authentication`: la contraseña del usuario es opcional; sin contraseña no se puede iniciar sesión con email y clave.

## Impact

- Base de datos: columna `users.entra_oid` (uuid, índice único filtrado) y `users.password` nullable.
- Código: controlador nuevo para el login con Microsoft, rutas en `routes/web.php`, registro del provider en `AppServiceProvider`, `config/services.php`, `User` y la vista `auth/login.blade.php`.
- Configuración: `ENTRA_TENANT_ID`, `ENTRA_CLIENT_ID`, `ENTRA_CLIENT_SECRET` y `ENTRA_REDIRECT_URI` en `.env.example` y `docker-compose.prod.yml`.
- Dependencias: `laravel/socialite` y `socialiteproviders/microsoft-azure`.
- Documentación: README (registro de la aplicación en Entra y variables) y las páginas web del login en `docs/openapi/`. Bruno no cambia: el flujo es de navegador.
