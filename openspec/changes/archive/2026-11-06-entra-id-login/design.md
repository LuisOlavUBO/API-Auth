# Design

## Context

El login central (2a) ya funciona: `LoginController` inicia la sesión de IAM con email y contraseña y `redirect()->intended()` devuelve a la persona a `oauth/authorize`, donde `RequireRolesInClientApplication` decide si recibe el código. Esta entrega solo agrega otra forma de iniciar esa misma sesión: Microsoft Entra ID. Lo que pasa después (código, PKCE, roles, logout) no cambia. La aplicación de Entra ID aún no está registrada.

**Goals:** entrar a IAM con la cuenta UBO de Microsoft; identificar al usuario por `oid`; vincular a los usuarios existentes por email; crear a los nuevos sin roles.

**Non-Goals:** anular la clave al vincular, cerrar `/v1/register`, baja de usuarios, sincronizar nombre/email/RUT desde Entra, cerrar la sesión de Microsoft, reautenticación forzada en Microsoft, SPAs.

## Decisions

**1. Socialite con el provider `microsoft-azure` de SocialiteProviders.** Resuelve el redirect, el `state` en la sesión, el canje del código y la lectura del perfil desde Graph (`/me`). El `id` que entrega es el id del usuario en Graph, que es el mismo `oid` del tenant. Se registra en `AppServiceProvider::boot` con `Event::listen(SocialiteWasCalled::class, [AzureExtendSocialite::class, 'handle'])`. Alternativa descartada: OIDC a mano con `Http`, más código propio (incluida la validación del `id_token`).

**2. Solo el tenant de la UBO.** `config/services.php` define `azure` con `client_id`, `client_secret`, `redirect` y `tenant` = `ENTRA_TENANT_ID`. Con un tenant específico (no `common`), Microsoft solo autentica cuentas de ese directorio. Las cuentas invitadas del tenant también tienen un `oid` en él y podrían entrar; quedan sin roles, igual que cualquier persona nueva, así que no ven ningún satélite hasta que se les asignen.

**3. Configurado = `ENTRA_CLIENT_ID` no vacío.** El controlador responde 404 si falta y la vista oculta el botón. Así el código puede llegar a producción antes de registrar la aplicación en Entra, y los entornos de desarrollo no muestran un botón roto.

**4. Rutas y controlador.** `MicrosoftLoginController` con `redirect()` y `callback()`, rutas `GET login/microsoft` y `GET login/microsoft/callback` (nombres `login.microsoft` y `login.microsoft.callback`) dentro del grupo de `routes/web.php`. Se agrega `api/auth/login/*` a `ApiPrefix::WEB_PAGES` para que respondan HTML y no problem+json. La cookie de sesión (`Path=/api/auth`, `SameSite=Lax`) viaja en la vuelta desde Microsoft porque es una navegación GET de primer nivel; el `state` y `url.intended` se conservan.

**5. Callback.** Lee el usuario de Socialite dentro de un `try`: cualquier `InvalidStateException` o error del canje (incluida la cancelación, que vuelve con `error` y sin `code`) vuelve al login con "No pudimos iniciar sesión con Microsoft, intenta de nuevo." Con un `oid` resuelve el usuario (decisión 6), hace `Auth::login`, regenera la sesión y `redirect()->intended(route('login'))`, igual que el login con clave. El email se toma de `mail` y, si viene vacío, de `getEmail()`, que en este provider es el `userPrincipalName`; sin `oid` o sin email también es un error.

**6. Resolver el usuario, en un método de `User` (`fromMicrosoft(string $oid, string $email, string $name): ?User`).**
1. `where('entra_oid', $oid)` → ese usuario.
2. `where('email', $email)` → si su `entra_oid` es nulo, se guarda el `oid` y se devuelve; si tiene otro, `null` (el controlador muestra "No pudimos asociar tu cuenta de Microsoft, contacta a soporte").
3. Si no existe, se crea con `name`, `email`, `entra_oid` y `password` nulo, sin roles (no se usa el rol por defecto del registro).

La comparación de email depende de la intercalación de SQL Server, que no distingue mayúsculas; se guarda el email tal como lo entrega Microsoft. No se actualizan nombre ni email en logins posteriores.

**7. Base de datos.** Una migración agrega `entra_oid` (uuid nullable) con índice único filtrado `WHERE entra_oid IS NOT NULL`, igual que `rut`, y deja `password` nullable. `entra_oid` va a `#[Hidden]`: no forma parte del perfil. `fillable` lo incluye.

**8. Usuarios sin contraseña.** `Auth::attempt` y la validación del password grant de Passport usan `Hash::check`, que devuelve `false` contra un hash nulo, así que ya rechazan a esos usuarios; se cubre con tests sin cambiar código, salvo que un test muestre lo contrario.

## Risks / Trade-offs

- **Cuenta preregistrada con el email de otra persona** (el registro es público y no verifica email): al vincular, la clave puesta por quien la registró sigue sirviendo. Se aceptó mantener la clave; se mitiga cuando se cierre `/v1/register` (fuera de alcance).
- **`prompt=login` con Microsoft:** IAM cierra su sesión y muestra el login, pero si la persona elige Microsoft y su sesión de Microsoft sigue abierta, entra sin escribir la clave. Igual pasa tras el logout central, que no cierra la sesión de Microsoft (decisión del diseño general).
- **Sin registro de la aplicación en Entra**, el flujo real con Microsoft no se prueba en esta entrega: los tests simulan Socialite. Se documentan los pasos de registro y la prueba manual queda para cuando exista.

## Migration Plan

1. Desplegar con las variables `ENTRA_*` vacías: el botón no aparece.
2. Registrar la aplicación en Entra ID (README), cargar las variables en el `.env.prod` y reiniciar el contenedor.
3. Rollback: vaciar `ENTRA_CLIENT_ID`. La migración tiene `down`, pero `password` solo vuelve a ser obligatoria si no hay usuarios sin contraseña.
