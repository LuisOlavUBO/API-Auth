# Tasks

Los tests se corren contra el SQL Server de Docker (`DB_HOST=127.0.0.1 DB_PORT=14334 DB_DATABASE=apiauth_test DB_USERNAME=sa`, contraseña en `DOCKER_MSSQL_SA_PASSWORD`). No se migra `10.16.2.138` salvo que el usuario lo pida.

## 1. Modelo de datos y migración

- [x] 1.1 Crear la migración (renombrar `apis` y `oauth_scopes.api_id`; `roles.application_id` con único `(application_id, name)`; `oauth_clients.application_id` obligatorio; `role_user`; quitar `users.role_id`), con el orden y las reglas de `design.md`; verificar con `php artisan migrate:fresh` en la base de Docker.
- [x] 1.2 Probar la migración con datos previos: migrar hasta la migración anterior, crear roles, scopes, un cliente y usuarios con rol, migrar y comprobar que todo quedó en `api-auth`, que `role_user` tiene los roles de cada usuario y que `down()` vuelve a la estructura anterior; verificar en la base de Docker.
- [x] 1.3 Renombrar el modelo `Api` a `Application` (clase, `ApplicationFactory`, relación en `OAuthScope`) y agregar `roles()` y `createClient()` a `Application`; verificar que no quedan referencias a `Api` con `grep`.
- [x] 1.4 Cambiar `Role` (pertenece a una aplicación, `users()` por `role_user`), `User` (`roles()` y `allowedScopeNames($applicationId)`, sin `role()`) y `RoleFactory`; verificar con un test de modelos: mismo nombre de rol en dos aplicaciones y usuario con roles en dos aplicaciones.
- [x] 1.5 Adaptar `RoleScopeSeeder` a aplicaciones y roles de `api-auth`; verificar que ejecutarlo dos veces no duplica datos (test existente de siembra repetida).
- [x] 1.6 Adaptar los tests existentes a la aplicación (`ClientScopesTest`, `OAuthScopeCommandsTest`, `TokenIntrospectionTest`, `AuthTest`: clientes creados con `Application::createClient()`, roles con aplicación); verificar que pasan con el comportamiento de hoy.

## 2. Emisión de tokens de usuario

- [x] 2.1 Fijar `Passport::defaultScopes([])` y hacer que `OAuthTokenService` envíe `config('oauth.default_scopes')` cuando `/v1/login` no trae `scope`; verificar con los tests de login existentes (sin scope = `user:read`, `admin` sin scope = `user:read`).
- [x] 2.2 Cambiar `ScopeRepository` para usuarios: roles en la aplicación del cliente, lista vacía → todos los scopes, scope pedido fuera de los roles → `invalid_scope`, y la exención de los scopes por defecto leyendo la configuración; verificar con tests de los escenarios "Usuario con roles en dos aplicaciones", "Rol con scopes de varias aplicaciones", "Token de usuario sin scope por el endpoint OAuth2" y "Usuario solicita un scope de un rol de otra aplicación".
- [x] 2.3 Implementar el refresh según `design.md` (scopes del token anterior ∩ permitidos, sin agregar; lista vacía no se expande); verificar con tests de "Se agrega un scope al rol", "Usuario sin scopes renueva" y el escenario existente "El rol perdió un scope".
- [x] 2.4 Extender `TokenAudience`, `AccessTokenRepository` y `AccessToken` con la audiencia y la claim `roles`, con el `client_id` primero; verificar con tests de `aud` y `roles` de un token de usuario y de un token sin scopes, y con los tests de `aud` M2M existentes.
- [x] 2.5 Asignar el rol por defecto de `api-auth` en `AuthController::register` (config `oauth.application`, por omisión `api-auth`); verificar con el test existente de rol por defecto, ajustado a la aplicación.

## 3. Tokens de servicio e introspección

- [x] 3.1 Calcular la claim `roles` de los tokens M2M (roles del cliente que conceden algún scope del token) y aceptar roles de cualquier aplicación en `api=`; verificar con tests de "Rol de otra aplicación en un token dirigido", de "Roles de un token de cliente dirigido a una API" y de "Cliente con roles sin api ni scope" (token sin scopes).
- [x] 3.2 Devolver `roles` en la introspección leyéndolos del JWT; verificar con los tests "Roles de un token de usuario" y "Roles de un token de servicio".

## 4. Comandos de administración

- [x] 4.1 Agregar el trait `ResolvesRoles` (formato `<aplicación>/<rol>`, error por rol o aplicación desconocida) y renombrar `oauth:api` a `oauth:application`; verificar con un test de crear y actualizar una aplicación.
- [x] 4.2 Adaptar `oauth:role` (`--application` obligatorio), `oauth:scope` (`--application`, roles de cualquier aplicación) y `oauth:assign-role` (agrega; `--sync` reemplaza solo los de esa aplicación); verificar con tests de cada escenario de la spec, incluida la atomicidad de `oauth:scope`.
- [x] 4.3 Adaptar `oauth:client-role` y `oauth:client-scopes` (roles `<aplicación>/<rol>`, muestra la aplicación del cliente); verificar con los tests existentes ajustados.
- [x] 4.4 Crear `oauth:client` (tipos `client-credentials` y `password`; muestra id y secreto) y `oauth:client-application`; verificar con tests de crear, falta de `--application`, aplicación desconocida y mover un cliente.
- [x] 4.5 Crear `oauth:role-scopes {application}`; verificar con un test que lista un rol con scopes de dos aplicaciones.

## 5. Documentación y cierre

- [x] 5.1 Actualizar `README.md` (alta del cliente con `oauth:client`, ejemplos de `oauth:*` con aplicaciones y roles `<aplicación>/<rol>`, despliegue y `oauth:client-application` para los clientes existentes); verificar que cada comando documentado se ejecuta tal como está escrito en la base de Docker.
- [x] 5.2 Actualizar `docs/openapi/openapi.yaml` (claim `roles`, `roles` en la respuesta de `introspect`, `aud` de los tokens de usuario) y `docs/bruno` (pruebas de `Iniciar sesion`, `Introspeccion` y `Token de cliente por API`); verificar con el test de documentación existente y leyendo el YAML.
- [x] 5.3 Correr toda la suite de tests en Docker y `vendor/bin/pint --dirty --format agent`; verificar que pasan y que `openspec validate application-scoped-roles --strict` es válido.

## Workflow follow-up

- Migrar el SQL Server de desarrollo (`10.16.2.138`) solo cuando el usuario lo pida, y reasignar los clientes con `oauth:client-application`.
- Archivar el cambio (`/opsx:archive`) tras la revisión, para que los specs delta pasen a `openspec/specs/`.
