# Spec Delta

## ADDED Requirements

### Requirement: IP de quien llama detrás del proxy
El servicio SHALL tomar la IP de quien llama de la cabecera `X-Forwarded-For` únicamente cuando la solicitud llega de un proxy listado en `TRUSTED_PROXIES` (direcciones separadas por comas, o `*`). Sin esa variable MUST NOT confiar en ninguna cabecera, y los límites de solicitudes por IP usan la de la conexión.

#### Scenario: Proxy de confianza
- **WHEN** `TRUSTED_PROXIES` incluye al proxy y este reenvía una solicitud con `X-Forwarded-For: 10.0.0.5`
- **THEN** los límites de solicitudes consideran la IP `10.0.0.5`

#### Scenario: Cabecera de un origen desconocido
- **WHEN** `TRUSTED_PROXIES` no está definida y llega una solicitud con `X-Forwarded-For: 10.0.0.5`
- **THEN** los límites de solicitudes consideran la IP de la conexión y no `10.0.0.5`
