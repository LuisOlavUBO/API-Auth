# openid-connect Specification

## Purpose

Permite que los clientes de OpenID Connect, como Ucampus, identifiquen a la persona que entró por el login central con un `id_token` estándar, y descubran solos los endpoints y las llaves para validarlo.

## Requirements

### Requirement: Descubrimiento
El sistema SHALL publicar sin autenticación, en `GET /api/auth/.well-known/openid-configuration`, un JSON con `issuer`, `authorization_endpoint`, `token_endpoint`, `userinfo_endpoint`, `jwks_uri`, `end_session_endpoint` y los valores soportados: `response_types_supported` (`code`), `scopes_supported`, `id_token_signing_alg_values_supported` (`RS256`), `token_endpoint_auth_methods_supported`, `code_challenge_methods_supported` (`S256`) y `subject_types_supported` (`public`).

#### Scenario: Consulta del descubrimiento
- **WHEN** se llama a `/api/auth/.well-known/openid-configuration` sin token
- **THEN** responde 200 con `issuer` igual a la URL pública de `/api/auth` y `jwks_uri` igual a `<issuer>/.well-known/jwks.json`

#### Scenario: Valores soportados
- **WHEN** se consulta el descubrimiento
- **THEN** `scopes_supported` incluye `openid`, `profile` y `email`, y `token_endpoint_auth_methods_supported` es `client_secret_basic` y `client_secret_post`

### Requirement: Llaves públicas en formato JWK
El sistema SHALL publicar sin autenticación, en `GET /api/auth/.well-known/jwks.json`, la llave pública con la que firma los tokens como un conjunto JWK (`keys`), con `kty` `RSA`, `use` `sig`, `alg` `RS256` y un `kid` derivado de la llave.

#### Scenario: Consulta de las llaves
- **WHEN** se llama a `/api/auth/.well-known/jwks.json` sin token
- **THEN** responde 200 con una llave RSA cuyo `kid` es el mismo de la cabecera de los `id_token`

#### Scenario: Misma llave que public-key
- **WHEN** se compara la llave del JWKS con la de `/api/auth/v1/public-key`
- **THEN** ambas son la misma llave pública

### Requirement: id_token al canjear el código
Cuando el canje de un código concede `openid`, la respuesta de `oauth/token` SHALL incluir `id_token`: un JWT RS256 con `kid` en la cabecera y las claims `iss`, `sub` (el `uuid` del usuario), `aud` (el id del cliente), `iat` y `exp` (la misma vigencia del access token). Sin `openid` la respuesta MUST NOT incluir `id_token`.

#### Scenario: Canje con openid
- **WHEN** Ucampus canjea el código de una solicitud con `scope=openid`
- **THEN** la respuesta incluye `id_token`, firmado con la llave del JWKS, con `sub` igual al `uuid` del usuario y `aud` igual al id del cliente de Ucampus

#### Scenario: Canje sin openid
- **WHEN** un satélite canjea el código de una solicitud sin `openid`
- **THEN** la respuesta no incluye `id_token`

#### Scenario: Token de cliente
- **WHEN** un cliente pide un token con client credentials
- **THEN** la respuesta no incluye `id_token`

### Requirement: Persona en el id_token
El `id_token` SHALL incluir `id_persona`, como texto, cuando el usuario tiene una persona, y MUST omitirla cuando no. Con `profile` SHALL incluir `name`, y con `email`, `email` y `email_verified`. El `id_token` MUST NOT incluir el RUT.

#### Scenario: Usuario con persona
- **WHEN** se emite el `id_token` de un usuario con `id_persona` 1029
- **THEN** el `id_token` incluye `id_persona` igual a `"1029"` y no incluye el RUT

#### Scenario: Usuario sin persona
- **WHEN** se emite el `id_token` de un usuario sin `id_persona`
- **THEN** el `id_token` no incluye `id_persona`

#### Scenario: Scopes profile y email
- **WHEN** se canjea el código de una solicitud con `scope=openid profile email`
- **THEN** el `id_token` incluye `name`, `email` y `email_verified` del usuario

### Requirement: Nonce
El `nonce` enviado a `oauth/authorize` SHALL incluirse sin cambios en el `id_token` del canje del código emitido en esa solicitud. Sin `nonce` en la autorización, el `id_token` MUST NOT llevarlo.

#### Scenario: Nonce enviado
- **WHEN** Ucampus abre la autorización con `nonce=n-0S6_WzA2Mj` y canjea el código recibido
- **THEN** el `id_token` incluye `nonce` igual a `n-0S6_WzA2Mj`

#### Scenario: Sin nonce
- **WHEN** se canjea un código de una autorización sin `nonce`
- **THEN** el `id_token` no incluye `nonce`

### Requirement: id_token al renovar
Al renovar con refresh token un token que tiene `openid`, la respuesta SHALL incluir un `id_token` nuevo con los datos actuales del usuario y sin `nonce`.

#### Scenario: Renovación con openid
- **WHEN** Ucampus renueva con el refresh token de un token con `openid`
- **THEN** la respuesta incluye un `id_token` con el mismo `sub` y sin `nonce`

### Requirement: Datos del usuario (userinfo)
`GET /api/auth/oauth/userinfo` SHALL responder, a un access token de usuario con `openid`, un JSON con `sub` (el `uuid`), `id_persona` como texto si el usuario tiene, `name` con `profile` y `email` y `email_verified` con `email`, sin el RUT. Sin token, o con un token de cliente, MUST responder 401; con un token de usuario sin `openid`, MUST responder 403.

#### Scenario: Token con openid
- **WHEN** Ucampus llama con el access token de un usuario con `id_persona` 1029, obtenido con `scope=openid email`
- **THEN** responde 200 con `sub` igual al `uuid`, `id_persona` igual a `"1029"`, `email` y `email_verified`, sin `name` ni el RUT

#### Scenario: Sin token
- **WHEN** se llama a `userinfo` sin token
- **THEN** responde 401

#### Scenario: Token sin openid
- **WHEN** se llama con un access token de usuario obtenido sin `openid`
- **THEN** responde 403

### Requirement: Autenticación del cliente al canjear
El canje del código SHALL aceptar las credenciales del cliente en la cabecera `Authorization: Basic` (`client_secret_basic`) o en el cuerpo de la solicitud (`client_secret_post`). En la cabecera, el id y el secreto MUST decodificarse como `application/x-www-form-urlencoded` (RFC 6749 §2.3.1) antes de validar el cliente, en todos los grants de `oauth/token`.

#### Scenario: Credenciales en la cabecera
- **WHEN** Ucampus canjea un código con `Authorization: Basic` con su id y su secreto, sin credenciales en el cuerpo
- **THEN** recibe los tokens

#### Scenario: Credenciales codificadas en la cabecera
- **WHEN** Ucampus canjea un código con `Authorization: Basic` cuyo id y secreto van codificados (los guiones del id como `%2D`)
- **THEN** recibe los tokens

#### Scenario: Credenciales en el cuerpo
- **WHEN** Ucampus canjea un código con `client_id` y `client_secret` en el cuerpo
- **THEN** recibe los tokens

### Requirement: Logout de OpenID Connect
`GET /api/auth/logout` SHALL aceptar `post_logout_redirect_uri` con `client_id` o `id_token_hint`, además de `return` y `client_id`: cierra la sesión y revoca los tokens como el logout central, y redirige a `post_logout_redirect_uri` con el `state` recibido solo si su origen coincide con el de alguna redirección registrada del cliente. Un `id_token_hint` MUST tener nuestra firma, aunque esté vencido.

#### Scenario: Logout desde Ucampus
- **WHEN** una persona con sesión abre el logout con un `id_token_hint` emitido para Ucampus, una `post_logout_redirect_uri` del origen de Ucampus y `state=abc`
- **THEN** su sesión de IAM se cierra, sus tokens quedan revocados y es redirigida a `post_logout_redirect_uri` con `state=abc`

#### Scenario: id_token_hint con otra firma
- **WHEN** el logout trae un `id_token_hint` que no firmó IAM
- **THEN** cierra la sesión y revoca los tokens, pero muestra la página de sesión cerrada y no redirige

#### Scenario: Retorno de otro origen
- **WHEN** el logout trae una `post_logout_redirect_uri` de un origen que el cliente no tiene registrado
- **THEN** cierra la sesión y revoca los tokens, pero muestra la página de sesión cerrada y no redirige
