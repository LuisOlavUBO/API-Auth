## MODIFIED Requirements

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
