# Design

## Context

`User::fromMicrosoft` busca por `oid`, vincula por email y, si no hay usuario, lo crea. Devuelve `null` solo cuando el email ya está vinculado a otro `oid`, y `MicrosoftLoginController` registra entonces `login_fallido` con motivo `cuenta_vinculada_a_otra`. Los 9 comandos `oauth:*` viven en `app/Console/Commands` con un trait en `Concerns`. `ClientScopesTest` prueba el token de cliente, pero prepara los roles del cliente con `oauth:client-role`. Ver proposal.md para el motivo.

## Goals / Non-Goals

**Goals:**
- Que API-Auth no cree usuarios ni escriba el catálogo: solo lee lo que administra API-IAM.
- Conservar la cobertura del token de cliente (`client_credentials`) sin los comandos.

**Non-Goals:**
- Mover migraciones, permisos por tabla en SQL Server, "olvidé mi contraseña".
- Cambiar `RoleScopeSeeder` o `DemoDataSeeder`.

## Decisions

1. **`fromMicrosoft` sin alta:** en vez de `create`, devuelve `null`. Sigue devolviendo `null` también cuando el email tiene otro `oid`. Se mantiene un solo valor de retorno porque los dos casos muestran el mismo mensaje.
2. **Motivo del evento en el controlador:** `MicrosoftLoginController` ya busca el usuario del email para el evento; si existe, el motivo sigue siendo `cuenta_vinculada_a_otra`, y si no, `sin_cuenta` (motivo que ya está en `authentication-events`, sin `user_id`). No hace falta cambiar esa spec.
3. **Tests de cliente sin comandos:** `ClientScopesTest` asigna los roles con `$client->roles()->attach(...)` (o un insert en `oauth_client_role` si el modelo no tiene la relación), igual a lo que escribe API-IAM. Se borran los dos tests que prueban comandos (`client_scopes_command_...` y `client_role_command_rejects_...`) y el de `--sync`, que prueba el comando y no el token. Los demás conservan sus aserciones sobre `oauth/token`.
4. **Tests de Microsoft:** los que hoy dependen del alta automática (persona nueva, nombre principal sin `mail`, persona nueva sin acceso al satélite, eventos con Microsoft) crean antes el usuario con la factory, como lo haría API-IAM. "Persona nueva" pasa a probar el rechazo.
5. **README:** se quitan los ejemplos con `oauth:*` y se reemplazan por una frase que remite al portal de API-IAM. En "Despliegue" se agrega el orden: API-Auth arranca y migra, después API-IAM migra y se corre `iam:install`.

## Risks / Trade-offs

- [Sin API-IAM instalado nadie puede crear usuarios ni clientes] → Los dos servicios se despliegan juntos y el orden queda en el README.
- [Si API-IAM arranca primero en una base vacía crea las tablas y el `migrate` de API-Auth falla] → Documentado en "Despliegue"; mover las migraciones queda para otro cambio.
- [Se pierde mover un cliente de aplicación] → Se crea un cliente nuevo y se revoca el anterior.
