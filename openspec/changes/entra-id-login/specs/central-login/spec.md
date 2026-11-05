## ADDED Requirements

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
