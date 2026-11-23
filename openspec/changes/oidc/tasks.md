# Tasks

Requiere `usuario-persona` archivado. Los tests se corren contra el SQL Server de Docker (`DB_HOST=127.0.0.1 DB_PORT=14334 DB_DATABASE=apiauth_test DB_USERNAME=sa`, contraseña en `DOCKER_MSSQL_SA_PASSWORD`).

## 1. Llaves y descubrimiento

- [x] 1.1 Crear la clase que arma el JWK y el `kid` (RFC 7638) desde la llave pública de Passport, y la ruta `GET .well-known/jwks.json`; verificar con tests de que responde sin token, que `kid` es la huella esperada y que la llave es la misma de `v1/public-key`.
- [x] 1.2 Agregar `GET .well-known/openid-configuration` con el `issuer` desde `APP_URL` y las URLs y valores soportados del diseño; verificar con tests de `issuer`, `jwks_uri`, `end_session_endpoint`, `scopes_supported` y `token_endpoint_auth_methods_supported`.

## 2. Scopes de OIDC

- [x] 2.1 Reconocer `openid`, `profile` y `email` en `ScopeRepository` (fuera del catálogo, solo en tokens de usuario, sin cambiar los permisos ni `aud`); verificar con tests de: `openid` con un permiso, solo scopes de OIDC (todos los permisos de los roles), renovación que los conserva, y client credentials con `openid` (`invalid_scope`); la suite de scopes existente sigue pasando.

## 3. id_token y nonce

- [x] 3.1 Crear `IdTokenResponse` (`getExtraParams`) con las claims del diseño y `kid`, registrarla con `Passport::useAuthorizationServerResponseType`, y armar los datos del usuario en un método que también usará `userinfo`; verificar con tests de canje con `openid` (firma con la llave del JWKS, `iss`, `sub` = `uuid`, `aud`, `exp`), sin `openid` (sin `id_token`), client credentials, usuario con y sin `id_persona` (texto, sin RUT), `profile` y `email`, y renovación (nuevo `id_token` sin `nonce`).
- [x] 3.2 Crear el `AuthCodeRepository` que guarda el `nonce` en la caché y sacarlo en `ScopeRepository::finalizeScopes` hacia `TokenAudience`; verificar con tests de autorización con `nonce` → `id_token` con ese `nonce`, sin `nonce` → sin claim, y que un segundo canje no lo reutiliza.
- [x] 3.3 Verificar con un test el canje con `Authorization: Basic` y con credenciales en el cuerpo; cambiar código solo si falla.

## 4. Userinfo y logout

- [x] 4.1 Agregar `GET oauth/userinfo` con `auth:api` y `CheckToken::using('openid')`; verificar con tests de token con `openid email` (sin `name` ni RUT), sin token y token de cliente (401), y token sin `openid` (403).
- [x] 4.2 Extender `LogoutController` con `post_logout_redirect_uri`, `id_token_hint` (firma propia, aunque vencido) y `state`; verificar con tests de logout con `id_token_hint` válido y `state`, `id_token_hint` con otra firma, origen no registrado, y que el logout actual con `return` sigue igual.

## 5. Documentación

- [x] 5.1 `docs/openapi/`: rutas `.well-known/*` y `oauth/userinfo`, `id_token` en la respuesta de `oauth/token` y los parámetros nuevos del logout; `docs/bruno/`: descubrimiento, llaves y `userinfo`; README: cómo integrar un cliente OIDC (URL de descubrimiento, scopes, PKCE S256, claim `id_persona`, `APP_URL` pública); verificar que las rutas documentadas coinciden con `php artisan route:list --path=api/auth`.
- [x] 5.2 Correr `vendor/bin/pint --dirty --format agent` y la suite completa; verificar que todo pasa.

## Workflow follow-up

- En API-IAM: aplicación `ucampus`, sus roles y el cliente de Ucampus con sus `redirect_uri`.
- Pedir a Ucampus sus `redirect_uri` y confirmar que usan PKCE S256 y la claim `id_persona`.
- Probar el login real de Ucampus en el ambiente de pruebas.
- `/opsx:archive` al terminar.
