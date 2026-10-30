# Design

## Context

Ver `proposal.md` para el motivo. Estado actual relevante:

- Todo está bajo `/api/auth` porque `ubo-nginx-proxy` enruta por el segmento posterior a `/api`; las rutas web de `routes/web.php` solo redirigen a la documentación.
- `AppServiceProvider` llama a `Passport::ignoreRoutes()` y `routes/api.php` registra solo `POST oauth/token`. Passport trae `GET authorize` con el middleware `web`, y la aprobación y el rechazo con `auth`.
- `AuthorizationController` de Passport 13 ya envía al login al invitado (lanza `AuthenticationException`), soporta `prompt=login`, y omite el consentimiento solo si `Client::skipsAuthorization()` es verdadero, que por defecto devuelve `false`.
- league/oauth2-server verifica el `code_challenge` al canjear el código, pero no lo exige a un cliente confidencial.
- El renderer de problem+json de UBO.Common responde todo lo que coincide con `api/*` (`UBO_PROBLEMS_ROUTES`), y `bootstrap/app.php` fuerza JSON en `api/*`; por eso un guest recibiría un 401 en vez de la redirección al login.
- La tabla `sessions` existe y `SESSION_DRIVER=database`; la cookie usa `path=/`. El refresh token dura 43200 minutos y el límite de `/oauth/token` es `throttle` (60 por minuto por IP). `bootstrap/app.php` no configura proxies de confianza.
- `OAuthTokenService` ya emite los tokens con los roles de la aplicación del `client_id` (cambio `application-scoped-roles`), también para el grant de código.

## Goals / Non-Goals

**Goals:** que un monolito pueda autenticar usuarios con Authorization Code + PKCE sin ver la contraseña; que el SSO funcione entre satélites con una sola sesión de IAM; que el logout saque al usuario de todos.

**Non-Goals:** Microsoft Entra ID, SPAs y CORS, cerrar el registro público, baja de usuarios, tope absoluto de sesión, front aparte para el login.

## Decisions

**1. Páginas web bajo el mismo prefijo, con el grupo `web`.** `routes/web.php` registra `login`, `logout` y `oauth/authorize` bajo `ApiPrefix::BASE`. Passport mantiene `ignoreRoutes()` y se registra a mano solo `GET oauth/authorize` apuntando a su `AuthorizationController`, así quedan fuera la aprobación, el rechazo y los dispositivos. La ruta `login` se llama `login`, que es la que usa Laravel para enviar invitados. Alternativa descartada: un proyecto o front aparte, porque la sesión tiene que vivir donde `/authorize` la lee.

**2. Las páginas web quedan fuera de problem+json.** `ApiPrefix` lista las tres rutas (`WEB_PAGES`). En `bootstrap/app.php`: (a) el render de problem+json se omite para ellas, envolviendo `ProblemRenderer` (sin tocar la librería); (b) `shouldRenderJsonWhen` ya no fuerza JSON para ellas; (c) `redirectGuestsTo` devuelve `route('login')` para ellas y `null` para el resto, que mantiene el 401 sin `Accept` de la corrección anterior. El resto de `/api/auth` no cambia, incluidas las rutas inexistentes. Alternativa descartada: cambiar `UBO_PROBLEMS_ROUTES` por una lista, que dejaría las rutas desconocidas fuera del formato.

**3. Clientes que omiten el consentimiento.** Un modelo `App\Models\Client` extiende el de Passport y `skipsAuthorization()` devuelve `true`; se registra con `Passport::useClientModel()`. Todos los clientes de IAM son de aplicaciones propias, así que no hace falta distinguirlos. De paso agrega la relación con su aplicación.

**4. Dos middleware en la ruta de autorización, solo cuando hay sesión.**
- *PKCE:* rechaza con 400 (página HTML) si falta `code_challenge` o el método no es `S256`. Va primero, antes de pedir credenciales.
- *Roles:* si la persona tiene sesión y no tiene roles en la aplicación del cliente (`User::rolesIn`), responde 403 con la página "No tienes acceso a este sistema, contacta a soporte". Un invitado pasa de largo y llega al controlador, que lo envía al login; al volver, la solicitud se repite y se evalúa de nuevo.
Con `prompt=login` el controlador de Passport cierra la sesión y pide el login; si la persona no tiene roles, igual se evalúa tras volver.

**5. Login web mínimo.** `LoginController`: `show` (formulario, o "ya iniciaste sesión" con botón de cerrar), y `store` con `Auth::attempt` sobre el guard `web`, regeneración de la sesión y `redirect()->intended()`. Reutiliza el limitador `auth` (10 por minuto por email e IP) con `throttle:auth`. Las vistas son Blade con estilos en línea y en español, sin build de frontend.

**6. Logout central.** `LogoutController` atiende `GET` (desde el satélite) y `POST` (botón de IAM). Revoca todos los tokens con un método nuevo `OAuthTokenService::revokeAllFor(User)`, extraído de `logoutAll` (que lo reutiliza), cierra e invalida la sesión y regenera el token CSRF. El `return` solo es válido si su origen (esquema, host y puerto) coincide con el de alguna `redirect_uri` registrada del cliente; si no, muestra la página de sesión cerrada. Se acepta `GET` con efecto porque es lo que usa el cierre de sesión iniciado por la aplicación en OIDC; el riesgo (cerrar la sesión de alguien) es bajo.

**7. Sesión y cookie.** `config/session.php` toma `path` de `ApiPrefix::BASE` por omisión, para que otras APIs del mismo host no reciban la cookie. `SESSION_LIFETIME` sigue en 120 minutos; `SESSION_SECURE_COOKIE=true` en `docker-compose.prod.yml`. `http_only` y `same_site=lax` ya son los valores por defecto.

**8. Tiempos.** `oauth.refresh_token_ttl` por omisión pasa a 120 minutos (y `.env.example`). El refresh ya se renueva con cada uso, así que funciona como inactividad por satélite. Afecta también a `/v1/login`.

**9. Proxies de confianza.** `bootstrap/app.php` llama a `trustProxies(at: ...)` con `TRUSTED_PROXIES`: vacío no confía en nadie, `*` en todos, o una lista separada por comas. Se documenta que `*` solo debe usarse si el servicio solo es alcanzable por el proxy.

**10. Límite del endpoint de token.** Un limitador `oauth-token` en `AppServiceProvider` por `client_id` (o IP si no viene), con `OAUTH_TOKEN_RATE_LIMIT` (por omisión 120 por minuto); la ruta usa `throttle:oauth-token` en lugar de `throttle`.

**11. Clientes de código.** `Application::createClient()` acepta `authorization-code` y las `redirect_uris`; el cliente es confidencial, con los grants `authorization_code` y `refresh_token` y proveedor `users`. `oauth:client` agrega `--redirect=*`, exige al menos una para ese tipo y valida que sean URLs absolutas.

**12. Documentación de rutas.** Como una prueba exige que toda ruta bajo `/api` esté en OpenAPI, se documentan `login` (GET y POST), `logout` (GET y POST) y `oauth/authorize`. En Bruno se agrega una solicitud OAuth2 de código con PKCE para probar el flujo completo.

## Risks / Trade-offs

- [`GET logout` cambia estado] → Aceptado y limitado a cerrar la sesión; la URL de retorno se valida contra las del cliente para no abrir una redirección abierta.
- [Bajar el refresh a 120 minutos cambia lo que ven los clientes de `/v1/login`] → Está en la propuesta y en la spec; se informa en el README.
- [`prompt=login` cierra la sesión de IAM completa, no solo la de ese satélite] → Los otros satélites mantienen sus tokens hasta que venzan; se documenta.
- [Cada visita al login crea una fila en `sessions`] → Laravel limpia las sesiones vencidas; las rutas de la API no usan sesión, así que el volumen es el de las personas que abren el login.
- [`TRUSTED_PROXIES=*` permite falsificar la IP si el servicio queda expuesto] → Se deja vacío por omisión y se documenta cuándo usarlo.
- [El 403 de "sin roles" revela que la cuenta existe pero no tiene acceso] → La persona ya se autenticó, así que no expone nada a un tercero.

## Migration Plan

1. Implementar y probar en local con la base de Docker; la base `IAM` del `.env` no necesita migraciones.
2. Registrar un cliente de prueba con `oauth:client --type=authorization-code` y recorrer el flujo con Bruno.
3. En despliegue: fijar `SESSION_SECURE_COOKIE`, `TRUSTED_PROXIES` y, si estaban definidos, `PASSPORT_REFRESH_TOKEN_TTL` y `SESSION_PATH`.
4. Reversa: volver a la imagen anterior; las sesiones guardadas no afectan a la API.
