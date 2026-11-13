# Proposal

## Why

API-Auth no registra los inicios de sesión ni los intentos fallidos: el límite de intentos vive solo en la caché y no hay historial. Sin eso no se puede responder lo básico de una revisión de seguridad: quién falló al entrar y cuántas veces, desde qué IPs, si alguien prueba muchas cuentas, si una persona entró desde una IP nueva, o si un cliente revocado o con un secreto viejo sigue intentando obtener tokens. El portal de IAM va a mostrar esos indicadores (después de `indicadores-portal` de API-IAM) y necesita esta fuente.

## What Changes

- Tabla propia de API-Auth `authentication_events`, una fila por evento:
  - **Tipos**: `login_ok`, `login_fallido`, `login_bloqueado` (límite de intentos) y `cliente_rechazado` (client credentials con secreto inválido, cliente revocado o de una aplicación de baja).
  - **Datos**: fecha, tipo, método (`clave`, `microsoft`, `client_credentials`), `user_id` si se sabe, correo intentado (en minúsculas, solo en fallos con clave), `client_id` si lo hay, IP, navegador y motivo del fallo (`credenciales`, `baja`, `clave_vencida`, `sin_cuenta`, `cuenta_vinculada_a_otra`, `secreto_invalido`, `cliente_revocado`).
  - **Nunca** guarda contraseñas, secretos ni tokens.
- Se registra en el login con clave, en la vuelta de Microsoft, al activarse el límite de intentos y al rechazar un cliente en `oauth/token`. Registrar nunca hace fallar el login: si la inserción falla, se loguea y el login sigue.
- Retención de 180 días: los eventos más antiguos se borran con `model:prune`, programado una vez al día.
- API-IAM solo lee esta tabla, para sus indicadores; queda en la lista de tablas de API-Auth.

## Capabilities

### New Capabilities

- `authentication-events`: el registro de eventos de autenticación y su retención.

### Modified Capabilities

(ninguna)

## Impact

- Migración de API-Auth (`authentication_events`, con índices por fecha, `user_id`, IP y `client_id`), modelo con `MassPrunable`, registro en `LoginController`, `MicrosoftLoginController`, el limitador de intentos y la emisión de tokens de cliente; tarea programada de purga; tests.
- Sin cambios de comportamiento para quien inicia sesión. La tabla la escribe solo API-Auth.
- Después de desplegar, API-IAM podrá agregar los indicadores de seguridad (cambio posterior a `indicadores-portal`).
