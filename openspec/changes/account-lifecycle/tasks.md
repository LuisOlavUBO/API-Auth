# Tasks

## 1. Estado de la cuenta

- [x] 1.1 Crear con `php artisan make:migration` la migración que agrega `users.disabled_at` y `users.temporary_password_expires_at` (datetime, nullable); `down` las borra. Agregar sus casts, los métodos `isDisabled()`, `hasTemporaryPassword()` y `temporaryPasswordExpired()` en `User`, y estados `disabled()` y `withTemporaryPassword()` en `UserFactory`. Verificar con `migrate` y `migrate:rollback` contra la base de tests en Docker (:14334).

## 2. Tokens

- [x] 2.1 En `ScopeRepository::finalizeUserScopes`, rechazar con `invalidGrant` a un usuario dado de baja. Verificar en `CentralLoginTest` que el canje de código y el refresh de un usuario dado de baja responden `invalid_grant`.
- [x] 2.2 En `TokenIntrospectionController::introspect`, responder `{"active": false}` si el usuario del token está dado de baja. Verificar en `TokenIntrospectionTest`.

## 3. Login web

- [x] 3.1 En `LoginController::store`, validar con `Auth::validate`, rechazar al usuario dado de baja con el error de credenciales incorrectas y la contraseña temporal vencida con "Tu contraseña temporal venció, contacta a soporte", y enviar a `password` a quien entra con una contraseña temporal vigente. Verificar los tres casos en `CentralLoginTest`.
- [x] 3.2 En `MicrosoftLoginController::callback`, rechazar al usuario dado de baja con "No tienes acceso, contacta a soporte.". Verificar en `MicrosoftLoginTest`.
- [x] 3.3 Crear el middleware `RequireActiveAccount` y ponerlo en `oauth/authorize` entre `RequirePkce` y `RequireRolesInClientApplication`. Verificar en `CentralLoginTest` la baja con la sesión abierta (sesión cerrada, 403, sin código) y la autorización con contraseña temporal sin cambiar (redirige a `password`).
- [x] 3.4 Crear `PasswordController` (`GET/POST /api/auth/password`, con `auth`), la vista `auth/password` y agregar la ruta a `ApiPrefix::WEB_PAGES`. Verificar en `CentralLoginTest` el cambio exitoso que vuelve a la autorización y entrega el código, el rechazo de una contraseña igual a la temporal y la redirección al login sin contraseña temporal.

## 4. Revocación

- [x] 4.1 Agregar `POST /api/auth/v1/users/{uuid}/revoke` con `EnsureClientIsResourceOwner::using('sessions:revoke')` y su controlador, que revoca los tokens y borra las sesiones de IAM del usuario y responde 204. Agregar `sessions:revoke` y el rol `session-revoker` a `RoleScopeSeeder`. Verificar en un test nuevo (`make:test --phpunit SessionRevocationTest`) los escenarios de la spec: revocación con sesiones, usuario inexistente (404), token sin scope (403) y token de usuario (401).

## 5. Documentación y cierre

- [x] 5.1 Documentar la revocación y la página de cambio de contraseña en `docs/openapi/openapi.yaml`, agregar la solicitud a `docs/bruno/Servicios/` y actualizar el README (scope `sessions:revoke` y qué tablas lee y escribe API-Auth). Verificar que `DocsTest` pasa.
- [x] 5.2 Correr `vendor/bin/pint --dirty --format agent` y la suite completa con la base de tests en Docker, y verificar que todo pasa.
