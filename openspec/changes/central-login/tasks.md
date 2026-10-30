# Tasks

Los tests se corren contra el SQL Server de Docker (`DB_HOST=127.0.0.1 DB_PORT=14334 DB_DATABASE=apiauth_test DB_USERNAME=sa`, contraseña en `DOCKER_MSSQL_SA_PASSWORD`). El desarrollo manual usa la base `IAM` del `.env`.

## 1. Configuración base

- [x] 1.1 Leer `TRUSTED_PROXIES` (vacío, `*` o lista separada por comas) en `bootstrap/app.php` mediante una clase pequeña que interprete el valor, y llamar a `trustProxies`; verificar con un test unitario de los tres formatos y con un test de la IP que ve el límite de intentos cuando el proxy es de confianza y cuando no.
- [x] 1.2 Fijar `session.path` en `/api/auth` por omisión, el refresh token en 120 minutos por omisión, y actualizar `.env.example` y `docker-compose.prod.yml` (`SESSION_SECURE_COOKIE=true`); verificar con tests de los valores de configuración y de que el refresh que emite `/v1/login` vence a los 120 minutos.
- [x] 1.3 Agregar el limitador `oauth-token` por `client_id` (o IP) con `OAUTH_TOKEN_RATE_LIMIT` y usarlo en la ruta de token; verificar con un test en que un cliente supera el límite y recibe 429 con `Retry-After` mientras otro cliente sigue recibiendo su token.

## 2. Clientes de código de autorización

- [x] 2.1 Crear el modelo `Client` propio con `skipsAuthorization()` verdadero y registrarlo con `Passport::useClientModel`; hacer que `Application::createClient()` acepte `authorization-code` con sus `redirect_uris`; verificar con un test del cliente creado (confidencial, grants, redirecciones) y de que el resto de los tests de clientes sigue pasando.
- [x] 2.2 Agregar `--redirect=*` a `oauth:client` y el tipo `authorization-code` (exige al menos una URL absoluta); verificar con tests de crear, sin redirección y con una redirección inválida.

## 3. Login web

- [x] 3.1 Agregar `ApiPrefix::WEB_PAGES` y adaptar `bootstrap/app.php`: omitir problem+json, no forzar JSON y redirigir invitados a `route('login')` solo en esas rutas; verificar con un test de que un invitado en `oauth/authorize` es redirigido al login, y con los tests existentes de problem+json y de `v1/me` sin `Accept` (siguen en 401).
- [x] 3.2 Crear `LoginController`, las vistas Blade en español y las rutas `GET/POST login` con `throttle:auth`; verificar con tests de formulario, login correcto con destino original, credenciales incorrectas, persona con sesión y 429 al superar los 10 intentos.

## 4. Autorización con código

- [x] 4.1 Registrar `GET oauth/authorize` apuntando al controlador de Passport, con el middleware de PKCE (400 sin `code_challenge` o con `plain`); verificar con tests de ambos rechazos y de que el 400 llega antes de pedir credenciales.
- [x] 4.2 Agregar el middleware de roles y la página "No tienes acceso a este sistema, contacta a soporte" (403); verificar con un test de una persona con roles solo en otra aplicación (sin código y sin redirección al satélite) y otro con roles en la aplicación del cliente.
- [x] 4.3 Cubrir el flujo completo con tests: invitado → login → código con `state`, sin pantalla de consentimiento, `redirect_uri` no registrada, `prompt=login`, canje correcto con roles y scopes de la aplicación, `code_verifier` incorrecto y código reutilizado; verificar que todos pasan.

## 5. Logout central

- [x] 5.1 Extraer `OAuthTokenService::revokeAllFor(User)` de `logoutAll` y reutilizarlo; verificar con los tests existentes de `logout-all`.
- [x] 5.2 Crear `LogoutController` con `GET` (retorno validado por origen contra las redirecciones del cliente) y `POST`, y las rutas; verificar con tests de retorno válido, retorno de otro origen, sin parámetros, refresh rechazado tras el logout y logout desde la página de IAM.

## 6. Documentación y cierre

- [x] 6.1 Documentar en `docs/openapi/openapi.yaml` `login` (GET y POST), `logout` (GET y POST) y `oauth/authorize`; verificar con el test que compara rutas y OpenAPI.
- [x] 6.2 Agregar a `docs/bruno` una solicitud OAuth2 de código con PKCE y actualizar el README (login central, `oauth:client --type=authorization-code`, `prompt=login`, `TRUSTED_PROXIES`, `SESSION_SECURE_COOKIE`, tiempos de sesión y de refresh); verificar que cada comando del README se ejecuta tal como está escrito en la base de Docker.
- [x] 6.3 Correr la suite completa, `vendor/bin/pint --dirty --format agent` y `openspec validate central-login --strict`, y recorrer el flujo en local con un cliente de prueba (login, código, canje, logout); verificar que todo pasa y que la sesión y los tokens se comportan según las specs.

## Workflow follow-up

- Archivar el cambio (`/opsx:archive`) tras la revisión, para que los specs delta pasen a `openspec/specs/`.
