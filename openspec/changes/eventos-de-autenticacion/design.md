## Context

El login central está en `LoginController` (clave: `store`, que valida con `Auth::validate` y rechaza a los dados de baja), `MicrosoftLoginController` (`callback`, con `User::fromMicrosoft` y sus fallos) y el limitador de intentos configurado en `AppServiceProvider`. Los tokens de cliente los emite Passport en `oauth/token`, que rechaza con `invalid_client` a un cliente inexistente, revocado o con secreto inválido. No hay registro de nada de esto. API-IAM leerá la tabla para sus indicadores (solo lectura).

## Goals / Non-Goals

**Goals:**
- Un historial de autenticación consultable por fecha, persona, IP y cliente.

**Non-Goals:**
- Alertas, geolocalización de IPs y detección automática (las arma API-IAM sobre los datos).
- Registrar cada emisión o renovación de token (ya está en `oauth_access_tokens`).

## Decisions

**D1. Tabla `authentication_events`**: `id`, `occurred_at` (datetime2), `type`, `method`, `user_id` nulo, `email` nulo, `client_id` nulo, `ip_address` (45), `user_agent` (512, truncado), `reason` nulo. Índices: `occurred_at`; `(user_id, occurred_at)`; `(ip_address, occurred_at)`; `(client_id, occurred_at)`. Sin FK: el evento sobrevive a la persona o al cliente.

**D2. Un servicio `AuthenticationEvents`** con un método por tipo, llamado desde cada punto. Envuelve la inserción en `try/catch`: un fallo se loguea y no se propaga (requisito «El registro no afecta el login»).

**D3. Puntos de registro**: `LoginController::store` (éxito, credenciales, baja, clave vencida), `MicrosoftLoginController::callback` (éxito, `sin_cuenta` si el login de Microsoft no está habilitado para crear, `cuenta_vinculada_a_otra`), el callback del limitador (bloqueo), y un listener o decorador del repositorio de clientes de Passport para `invalid_client` (distinguiendo inexistente, revocado y secreto inválido).

**D4. Retención** con `MassPrunable` (`occurred_at < now - 180 días`) y `model:prune` en el scheduler diario. El despliegue debe tener el scheduler activo; si no lo tiene, el README indica correr `php artisan model:prune` a diario.

**D5. Privacidad.** Nunca se guardan contraseñas, secretos ni tokens. El correo se guarda solo en fallos con clave (para ver ataques a cuentas inexistentes). Retención acotada a 180 días.

## Risks / Trade-offs

- [Volumen] → Una fila por login y por fallo; con índices por fecha y purga a 180 días.
- [Datos personales (IP, navegador, correo)] → Retención acotada y acceso solo por API-IAM con `iam:usuarios.leer`.

## Migration Plan

Migración aditiva de API-Auth (tabla nueva). Desplegar API-Auth; API-IAM consume los datos en un cambio posterior.
