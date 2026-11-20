## MODIFIED Requirements

### Requirement: Login web
`GET /api/auth/login` SHALL mostrar el formulario de login en HTML. `POST /api/auth/login` con `email` y `password` MUST iniciar la sesión de IAM y redirigir al destino original, o a la página de login si no hay uno. Con credenciales incorrectas MUST volver al formulario con un mensaje, sin iniciar sesión. Un usuario sin contraseña (por ejemplo, uno dado de alta en API-IAM para entrar con Microsoft) MUST NOT iniciar sesión con el formulario.

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

### Requirement: Usuario de una cuenta de Microsoft
El usuario de una cuenta de Microsoft SHALL identificarse por su `oid` de Entra ID. Sin usuario con ese `oid`, MUST vincularse el del mismo email (conserva su contraseña). API-Auth MUST NOT crear usuarios: si no hay usuario con ese `oid` ni con ese email, o si el usuario de ese email tiene otro `oid`, MUST NOT iniciar sesión y SHALL mostrar "No pudimos asociar tu cuenta de Microsoft, contacta a soporte".

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
- **THEN** vuelve al login con "No pudimos asociar tu cuenta de Microsoft, contacta a soporte", no queda con sesión, no se crea ningún usuario y se registra `login_fallido` con motivo `sin_cuenta`
