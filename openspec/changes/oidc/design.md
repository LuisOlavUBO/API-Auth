# Design

## Context

El login central ya hace el flujo de código con PKCE, y los access tokens son JWT RS256 firmados con la llave de Passport. `AccessToken` y `AccessTokenRepository` ya extienden league/oauth2-server, y `ScopeRepository` deja datos en `TokenAudience` para que `AccessTokenRepository` los tome, porque la librería no los pasa entre sus piezas. Este cambio agrega OIDC con esas mismas técnicas, sin dependencias nuevas (lcobucci/jwt ya viene con Passport). Depende de `usuario-persona` (`users.id_persona`).

**Goals:** que un cliente OIDC estándar configure IAM solo con la URL de descubrimiento y reciba un `id_token` válido con `id_persona`.

**Non-Goals:** ver "Fuera de alcance" en `proposal.md`. Tampoco cambia el access token: sigue igual, con `sub` interno y sin `iss`.

## Decisions

**1. `id_token` con un `ResponseType` propio.** `IdTokenResponse` extiende `BearerTokenResponse` y sobrescribe `getExtraParams()`, el punto de extensión que league/oauth2-server ofrece para agregar campos a la respuesta del token. Se registra con `Passport::useAuthorizationServerResponseType()` en `AppServiceProvider`. Agrega `id_token` solo si el token es de usuario y lleva `openid`, así que cubre el canje del código y la renovación sin distinguir el grant. Firma con `$this->privateKey`, que la librería ya le entrega, usando lcobucci/jwt como `AccessToken`. Alternativa descartada: un paquete OIDC para Passport, que es una dependencia nueva para pocas clases.

**2. Claims.** `iss`, `sub` = `uuid`, `aud` = id del cliente en minúsculas (como en la introspección), `iat` y `exp` = `iat` más la vigencia del access token, `nonce` si hay, `id_persona` como texto si el usuario tiene (el mismo formato de texto que usa API-UCampus), `name` con `profile`, y `email` y `email_verified` con `email`. El RUT nunca. `/userinfo` arma los mismos datos de usuario con el mismo método, para que no difieran.

**3. El `nonce` viaja con el código, en caché.** `oauth/authorize` emite el código en la misma solicitud que trae `nonce` (sin consentimiento). Un `AuthCodeRepository` propio extiende el de Passport y, al guardar el código, deja `request()->query('nonce')` en la caché con la clave `oidc-nonce:<id del código>` y la vigencia del código. Al canjearlo, league/oauth2-server pasa el id del código a `ScopeRepository::finalizeScopes()` (`$authCodeId`), que saca el `nonce` de la caché (`Cache::pull`) y lo deja en `TokenAudience`; `IdTokenResponse` lo toma de ahí y lo limpia. Alternativa descartada: una columna `nonce` en `oauth_auth_codes`, que es una tabla de Passport y obliga a una migración sobre ella.

**4. Scopes de OIDC en `ScopeRepository`.** Una constante con `openid`, `profile` y `email`. `getScopeEntityByIdentifier()` los reconoce aunque no estén en `oauth_scopes`. `finalizeUserScopes()` los separa de los pedidos, aplica las reglas actuales de permisos solo al resto (si el resto está vacío y no es renovación, van todos los permisos de los roles) y los vuelve a sumar. En tokens de cliente no se tocan: no están en los permisos del cliente, así que las reglas actuales ya responden `invalid_scope`. No aparecen en `oauth_scopes`, así que `applicationsOwning()` no los agrega a `aud`.

**5. `kid` y JWKS desde la llave pública.** Una clase pequeña lee `oauth-public.key` (o `PASSPORT_PUBLIC_KEY`, igual que `public-key`) con `openssl_pkey_get_details`, arma el JWK (`n`, `e` en base64url) y calcula el `kid` como la huella RFC 7638 (SHA-256 de `{"e","kty","n"}`). La usan el endpoint de llaves y `IdTokenResponse` (cabecera `kid`). Si se rotan las llaves, el JWKS y el `kid` cambian solos.

**6. Issuer y descubrimiento.** `issuer` = `APP_URL` + `/api/auth` (`ApiPrefix::BASE`). Todas las URLs del descubrimiento se arman desde el `issuer`, no desde la solicitud, para que coincidan aunque el contenedor esté detrás del proxy. El JSON es fijo salvo esas URLs.

**7. Rutas.** En `routes/api.php`, bajo `/api/auth` y sin versión (son rutas del estándar, como `oauth/token`): `GET .well-known/openid-configuration` y `GET .well-known/jwks.json` sin autenticación, y `GET oauth/userinfo` con `auth:api` y `CheckToken::using('openid')`. Los errores de `userinfo` (401 y 403) siguen en Problem Details, como `/v1/me`.

**8. Logout.** `LogoutController::returnUrlOf()` acepta `post_logout_redirect_uri` como alternativa a `return`. El cliente sale de `client_id` o, si no viene, del `aud` de `id_token_hint`, que se valida con la llave pública ignorando el vencimiento. La regla de origen es la actual (mismo origen que alguna `redirect_uri` del cliente). Si hay `state`, se agrega a la URL de retorno. El cierre de sesión y la revocación no cambian.

## Risks / Trade-offs

- [Una versión mayor de league/oauth2-server cambia `BearerTokenResponse` o el paso de `$authCodeId`] → los tests del `id_token` y del `nonce` fallan en CI; se ajustan dos clases.
- [La caché se limpia entre la autorización y el canje] → el `id_token` sale sin `nonce`, Ucampus lo rechaza y la persona vuelve a entrar. Es una ventana de minutos.
- [`APP_URL` distinta de la URL pública] → el `issuer` no coincide y Ucampus rechaza los tokens. Se documenta en el README y se verifica en el descubrimiento al desplegar.
- [Regla de origen en vez de coincidencia exacta para `post_logout_redirect_uri`] → el estándar pide registrarlas exactas; se aceptó la regla actual para no agregar datos al cliente. Se cambia si un cliente la exige.
- [El logout por GET sin confirmación] → igual que hoy: un enlace puede cerrar la sesión de alguien, pero no da acceso.

## Migration Plan

1. Archivar `usuario-persona` y desplegar este cambio. Sin datos que migrar.
2. Verificar en el servidor que `/api/auth/.well-known/openid-configuration` muestre el `issuer` público.
3. En API-IAM: crear la aplicación `ucampus` con sus roles, el cliente de Ucampus con sus `redirect_uri` y asignar los roles a las personas.
4. Entregar a Ucampus la URL de descubrimiento, el `client_id`, el secreto y los scopes (`openid`, más `profile` y `email` si los usan); recordarles PKCE S256 y la claim `id_persona`.
5. Rollback: revertir el despliegue. Los clientes sin `openid` no se ven afectados.
