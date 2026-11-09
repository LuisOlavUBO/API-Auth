# central-login Specification

## Purpose
Permite que los satélites autentiquen a sus usuarios en un solo lugar: IAM muestra el login, mantiene la sesión y entrega un código que el satélite canjea por tokens, sin ver nunca la contraseña.

## Requirements

### Requirement: Login web
`GET /api/auth/login` SHALL mostrar el formulario de login en HTML. `POST /api/auth/login` con `email` y `password` MUST iniciar la sesión de IAM y redirigir al destino original, o a la página de login si no hay uno. Con credenciales incorrectas MUST volver al formulario con un mensaje, sin iniciar sesión. Un usuario sin contraseña (creado al entrar con Microsoft) MUST NOT iniciar sesión con el formulario.

#### Scenario: Formulario de login
- **WHEN** una persona sin sesión abre `/api/auth/login`
- **THEN** responde 200 con HTML que incluye los campos de email y contraseña

#### Scenario: Login correcto
- **WHEN** una persona envía su email y su contraseña correctos
- **THEN** queda con sesión en IAM y es redirigida al destino que intentaba abrir

#### Scenario: Credenciales incorrectas
- **WHEN** una persona envía una contraseña incorrecta
- **THEN** vuelve al formulario con un mensaje de error y no queda con sesión

#### Scenario: Usuario sin contraseña
- **WHEN** se envía el email de un usuario sin contraseña con cualquier valor de `password`
- **THEN** vuelve al formulario con el mensaje de credenciales incorrectas y no queda con sesión

#### Scenario: Persona con sesión abre el login
- **WHEN** una persona con sesión abre `/api/auth/login` sin destino pendiente
- **THEN** ve que ya inició sesión, con su email y un botón para cerrarla

### Requirement: Límite de intentos del login web
El envío del formulario de login SHALL aceptar como máximo 10 solicitudes por minuto por combinación de email e IP. Al superarlo MUST responder 429 con la cabecera `Retry-After`.

#### Scenario: Se supera el límite
- **WHEN** se hacen 11 intentos de login web en un minuto con el mismo email desde la misma IP
- **THEN** el undécimo responde 429 con la cabecera `Retry-After`

### Requirement: Sesión de IAM
La sesión de IAM SHALL guardarse en la base de datos y vencer a los 120 minutos sin uso (configurable). Su cookie MUST limitarse a `/api/auth`, no ser legible por JavaScript, usar `SameSite=Lax` y poder marcarse como solo HTTPS por entorno.

#### Scenario: Cookie de sesión
- **WHEN** una persona inicia sesión
- **THEN** la respuesta fija una cookie con `Path=/api/auth`, `HttpOnly` y `SameSite=Lax`

#### Scenario: Sesión vencida
- **WHEN** una persona vuelve a `/api/auth/oauth/authorize` después de 120 minutos sin uso
- **THEN** es enviada al login en lugar de recibir un código

### Requirement: Autorización con código
`GET /api/auth/oauth/authorize` con `response_type=code`, `client_id`, `redirect_uri`, `state`, `code_challenge` y `code_challenge_method=S256` SHALL redirigir a `redirect_uri` con un `code` de un solo uso y el mismo `state` cuando la persona tiene sesión. Si no la tiene, MUST enviarla al login y devolverla a la misma solicitud al terminar. Un `redirect_uri` que no esté registrado en el cliente MUST rechazarse sin redirigir.

#### Scenario: Persona con sesión
- **WHEN** una persona con sesión y roles en la aplicación del cliente abre una solicitud de autorización válida
- **THEN** es redirigida a `redirect_uri` con `code` y `state`

#### Scenario: Persona sin sesión
- **WHEN** una persona sin sesión abre una solicitud de autorización válida, inicia sesión y termina el formulario
- **THEN** vuelve a la solicitud de autorización y es redirigida a `redirect_uri` con `code` y `state`

#### Scenario: Redirección no registrada
- **WHEN** la solicitud trae un `redirect_uri` que no está entre los del cliente
- **THEN** responde con un error y no redirige a esa dirección

### Requirement: PKCE obligatorio
La autorización MUST exigir `code_challenge` con `code_challenge_method=S256`. Una solicitud sin `code_challenge`, o con el método `plain`, SHALL rechazarse con 400 antes de pedir credenciales.

#### Scenario: Solicitud sin PKCE
- **WHEN** se abre `/api/auth/oauth/authorize` sin `code_challenge`
- **THEN** responde 400 y no pide credenciales ni emite un código

#### Scenario: Método plain
- **WHEN** se abre la autorización con `code_challenge_method=plain`
- **THEN** responde 400

### Requirement: Sin consentimiento
La autorización de un cliente de IAM MUST NOT mostrar una pantalla de consentimiento: con sesión y roles, el código se emite directamente.

#### Scenario: Sin pantalla de aprobación
- **WHEN** una persona con sesión y roles abre una solicitud de autorización válida
- **THEN** no ve ninguna pantalla de aprobación y recibe el código

### Requirement: Personas sin roles en la aplicación
Si la persona no tiene ningún rol en la aplicación del cliente, la autorización SHALL responder 403 con una página que diga "No tienes acceso a este sistema, contacta a soporte" y MUST NOT redirigir al satélite ni emitir un código.

#### Scenario: Sin roles en la aplicación
- **WHEN** una persona con sesión, que solo tiene roles de otras aplicaciones, abre la autorización de un cliente de `portal`
- **THEN** responde 403 con ese mensaje y no recibe código

### Requirement: Volver a pedir las credenciales
La autorización con `prompt=login` SHALL cerrar la sesión de IAM y mostrar el login, aunque la persona ya tuviera sesión, y al terminar continuar con la solicitud.

#### Scenario: prompt=login con sesión
- **WHEN** una persona con sesión abre la autorización con `prompt=login`
- **THEN** es enviada al login y, tras iniciar sesión, recibe el código

### Requirement: Canje del código por tokens
`POST /api/auth/oauth/token` con `grant_type=authorization_code`, el `code`, la `redirect_uri`, el `code_verifier` y las credenciales del cliente SHALL emitir el access y el refresh token con los roles y scopes que la persona tiene en la aplicación del cliente. Un `code_verifier` que no corresponde al `code_challenge`, o un código ya usado, MUST rechazarse.

#### Scenario: Canje correcto
- **WHEN** el satélite canjea el código de una persona con el rol `coordinador` de `portal`, con su `code_verifier` y las credenciales de su cliente
- **THEN** recibe un access token con `roles` igual a `["coordinador"]` y los scopes de ese rol, y un refresh token

#### Scenario: Verifier incorrecto
- **WHEN** el satélite canjea el código con otro `code_verifier`
- **THEN** responde 400 con `error` igual a `invalid_grant`

#### Scenario: Código reutilizado
- **WHEN** el satélite canjea por segunda vez el mismo código
- **THEN** responde 400 con `error` igual a `invalid_grant`

### Requirement: Logout central
`GET /api/auth/logout` con `client_id` y `return` SHALL cerrar la sesión de IAM, revocar todos los tokens de acceso y de refresh de la persona y redirigir a `return`, solo si su origen coincide con el de alguna redirección registrada del cliente. En otro caso, o sin esos parámetros, MUST mostrar una página que confirme que la sesión se cerró.

#### Scenario: Logout con retorno válido
- **WHEN** una persona con sesión abre el logout con el `client_id` de `portal` y un `return` del mismo origen que su redirección registrada
- **THEN** su sesión de IAM se cierra, sus tokens quedan revocados y es redirigida a `return`

#### Scenario: Retorno de otro origen
- **WHEN** el logout trae un `return` de un origen que el cliente no tiene registrado
- **THEN** cierra la sesión y revoca los tokens, pero muestra la página de sesión cerrada y no redirige

#### Scenario: Refresh tras el logout
- **WHEN** un satélite intenta renovar con un refresh token emitido antes del logout
- **THEN** responde 400 con `error` igual a `invalid_grant`

### Requirement: Logout desde la página de IAM
`POST /api/auth/logout` SHALL cerrar la sesión de IAM y revocar todos los tokens de la persona, y mostrar el login.

#### Scenario: Botón de cerrar sesión
- **WHEN** una persona con sesión envía el cierre de sesión desde la página de IAM
- **THEN** su sesión se cierra, sus tokens quedan revocados y vuelve al formulario de login

### Requirement: Login con Microsoft Entra ID
Con Entra ID configurado, el login web SHALL ofrecer "Ingresar con Microsoft": `GET /api/auth/login/microsoft` envía al login de Microsoft del tenant de la UBO y `GET /api/auth/login/microsoft/callback` MUST iniciar la sesión de IAM y redirigir al destino original. Sin configuración, el botón MUST NOT mostrarse y ambas rutas SHALL responder 404.

#### Scenario: Botón visible
- **WHEN** una persona sin sesión abre `/api/auth/login` y Entra ID está configurado
- **THEN** ve el formulario de email y contraseña y el botón "Ingresar con Microsoft"

#### Scenario: Entra ID sin configurar
- **WHEN** Entra ID no está configurado y se abre `/api/auth/login/microsoft`
- **THEN** responde 404, y la página de login no muestra el botón

#### Scenario: Ir a Microsoft
- **WHEN** una persona abre `/api/auth/login/microsoft`
- **THEN** es redirigida al inicio de sesión de Microsoft del tenant configurado

#### Scenario: Vuelta al satélite
- **WHEN** una persona sin sesión abre una solicitud de autorización válida, elige "Ingresar con Microsoft" y Microsoft la devuelve con su cuenta
- **THEN** queda con sesión en IAM, vuelve a la solicitud de autorización y, si tiene roles en la aplicación del cliente, es redirigida a `redirect_uri` con `code` y `state`

#### Scenario: Microsoft no autentica
- **WHEN** la vuelta de Microsoft trae un error (por ejemplo, la persona canceló) o un `state` que no corresponde a la sesión
- **THEN** vuelve al login con un mensaje de error y no queda con sesión

### Requirement: Usuario de una cuenta de Microsoft
El usuario de una cuenta de Microsoft SHALL identificarse por su `oid` de Entra ID. Sin usuario con ese `oid`, MUST vincularse el del mismo email (conserva su contraseña); si ese usuario tiene otro `oid`, MUST NOT iniciar sesión y SHALL mostrar "No pudimos asociar tu cuenta de Microsoft, contacta a soporte". Sin usuario con ese email, MUST crearse uno sin contraseña y sin roles.

#### Scenario: Usuario ya vinculado
- **WHEN** entra con Microsoft una persona cuyo `oid` ya tiene un usuario, aunque su email en Microsoft haya cambiado
- **THEN** inicia sesión con ese usuario

#### Scenario: Primera vez con un email existente
- **WHEN** entra con Microsoft por primera vez una persona cuyo email ya tiene un usuario sin `oid`
- **THEN** inicia sesión con ese usuario, que queda vinculado a su `oid` y puede seguir entrando con su contraseña

#### Scenario: Email vinculado a otra cuenta
- **WHEN** entra con Microsoft una persona cuyo `oid` no tiene usuario y cuyo email pertenece a un usuario vinculado a otro `oid`
- **THEN** ve el mensaje de contactar a soporte y no queda con sesión

#### Scenario: Persona nueva
- **WHEN** entra con Microsoft una persona cuyo `oid` y email no tienen usuario
- **THEN** se crea su usuario sin contraseña y sin roles, inicia sesión y, al volver a la autorización de un satélite, ve "No tienes acceso a este sistema, contacta a soporte"
