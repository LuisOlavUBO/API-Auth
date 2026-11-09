# Tasks

## 1. Tests sobre el login central

- [x] 1.1 Mover a `Tests\TestCase` los ayudantes del flujo con código de `CentralLoginTest` (`challenge`, `authorizeUrl`, `queryOf`, `exchange` y `tokensFor(User, Client, ?string $scope)`) y usarlos desde `CentralLoginTest`. Verificar que `CentralLoginTest` pasa.
- [x] 1.2 Cambiar `AuthTest`, `ApplicationRolesTest`, `TokenIntrospectionTest` y `ProblemDetailsTest` para que obtengan tokens de usuario con `tokensFor` y refresquen en `oauth/token` con `grant_type=refresh_token`. Borrar los tests de login y refresh de la API, scopes por defecto, cliente interno rechazado y límite de `/v1/login`. Cambiar el test del 422 a `introspect` sin `token`, agregar el de refresh token de otro cliente y el de refresh token reutilizado que no se registra en el log. En `ServiceConfigurationTest`, probar el proxy con el login web. Verificar que pasan con el código actual.

## 2. Cierre

- [x] 2.1 Quitar las rutas `v1/login` y `v1/refresh`, los métodos `login` y `refresh` de `AuthController`, `LoginRequest`, `Passport::enablePasswordGrant()`, `issueForCredentials`, `refresh` y `requestToken` de `OAuthTokenService`, y `OAuthTokenException`. Ajustar el comentario de `bootstrap/app.php`. Agregar en `AuthTest` los escenarios de "Sin login por la API" (404 en ambas rutas y `unsupported_grant_type` en `oauth/token`). Verificar que pasan.
- [x] 2.2 Quitar `oauth.password_client` y `oauth.default_scopes` de `config/oauth.php` y la excepción de scopes por defecto de `ScopeRepository`, y las variables `PASSPORT_PASSWORD_CLIENT_*` de `.env.example`. Verificar con `ApplicationRolesTest` y `AuthTest`.
- [x] 2.3 Quitar el tipo `password` de `oauth:client`. Verificar en `OAuthScopeCommandsTest` que `--type=password` falla sin crear el cliente.

## 3. Documentación y cierre

- [x] 3.1 Quitar login y refresh de `docs/openapi/openapi.yaml`, borrar `docs/bruno/Usuarios/Iniciar sesion.bru` y `Renovar tokens.bru` y ajustar las solicitudes que usaban `accessToken` de esa colección, y actualizar el README: endpoints, cliente password, rate limit, errores, ejemplo con curl y Bruno, y los pasos para revocar el cliente password en cada ambiente. Verificar que `DocsTest` pasa.
- [x] 3.2 Correr `vendor/bin/pint --dirty --format agent` y la suite completa con la base de tests en Docker, y verificar que todo pasa.
