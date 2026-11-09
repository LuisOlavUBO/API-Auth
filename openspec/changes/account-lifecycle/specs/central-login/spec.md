## ADDED Requirements

### Requirement: Usuarios dados de baja en el login web
Un usuario dado de baja MUST NOT iniciar sesión en IAM: con contraseña SHALL ver el mismo mensaje que con credenciales incorrectas, y con Microsoft SHALL ver "No tienes acceso, contacta a soporte". Si es dado de baja con la sesión abierta, la siguiente solicitud de autorización MUST cerrar esa sesión y mostrar "No tienes acceso a este sistema, contacta a soporte", sin entregar un código.

#### Scenario: Login con contraseña
- **WHEN** un usuario dado de baja envía su email y su contraseña correctos al login web
- **THEN** vuelve al formulario con el mensaje de credenciales incorrectas y no queda con sesión

#### Scenario: Login con Microsoft
- **WHEN** entra con Microsoft una persona cuyo usuario está dado de baja
- **THEN** vuelve al login con "No tienes acceso, contacta a soporte" y no queda con sesión

#### Scenario: Baja con la sesión abierta
- **WHEN** una persona con sesión en IAM es dada de baja y luego un satélite la envía a `oauth/authorize`
- **THEN** su sesión se cierra, ve "No tienes acceso a este sistema, contacta a soporte" y el satélite no recibe un código

### Requirement: Cambio obligatorio de la contraseña temporal
Quien inicia sesión en el login web con una contraseña temporal vigente SHALL ser enviado a `GET /api/auth/password` y MUST NOT recibir códigos de autorización hasta cambiarla. `POST /api/auth/password` con `password` y `password_confirmation` MUST guardar la nueva contraseña, que debe cumplir la política de contraseñas y ser distinta de la temporal; la contraseña deja de ser temporal y la persona sigue al destino que intentaba abrir.

#### Scenario: Primer acceso con contraseña temporal
- **WHEN** una persona abre una solicitud de autorización de un satélite y entra con su contraseña temporal vigente
- **THEN** es enviada a la página de cambio de contraseña en lugar de volver al satélite

#### Scenario: Autorización antes de cambiarla
- **WHEN** una persona que entró con una contraseña temporal abre `oauth/authorize` sin haberla cambiado
- **THEN** es enviada a la página de cambio de contraseña y no recibe un código

#### Scenario: Cambio exitoso
- **WHEN** la persona envía una contraseña nueva válida con su confirmación
- **THEN** su contraseña queda cambiada y ya no es temporal, y vuelve a la solicitud de autorización, que le entrega el código

#### Scenario: Nueva contraseña igual a la temporal
- **WHEN** la persona envía como nueva la misma contraseña temporal
- **THEN** vuelve a la página con un mensaje de error y la contraseña sigue siendo temporal

### Requirement: Contraseña temporal vencida en el login web
Una contraseña temporal vencida MUST rechazarse en el login web con el mensaje "Tu contraseña temporal venció, contacta a soporte", sin iniciar sesión.

#### Scenario: Contraseña temporal vencida
- **WHEN** una persona entra al login web con su contraseña temporal correcta después de que venció
- **THEN** vuelve al formulario con "Tu contraseña temporal venció, contacta a soporte" y no queda con sesión
