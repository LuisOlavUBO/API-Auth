# Spec Delta

## Purpose

Permite a quien opera el servicio administrar, desde la consola, las APIs, los roles, los scopes y la asignación de roles a usuarios y clientes OAuth, y deja sembrado un catálogo base para que el servicio funcione desde su instalación.

## ADDED Requirements

### Requirement: Administración de APIs
El comando `oauth:api {name} {description?}` SHALL crear una API (servidor de recursos dueño de scopes) o actualizar la existente con ese nombre.

#### Scenario: Crear una API
- **WHEN** se ejecuta `oauth:api api-maestros "Datos maestros"`
- **THEN** el comando termina con éxito y existe la API `api-maestros` con esa descripción

### Requirement: Administración de roles
El comando `oauth:role {name} {description?}` SHALL crear un rol o actualizar el existente con ese nombre.

#### Scenario: Crear un rol
- **WHEN** se ejecuta `oauth:role maestros-lector "Lectura de API-Maestros"`
- **THEN** el comando termina con éxito y existe el rol con esa descripción

### Requirement: Administración de scopes
El comando `oauth:scope {name} {description} --api= --role=*` SHALL crear o actualizar un scope de la API indicada y concederlo a los roles indicados, sin quitarlo de otros roles. `--api` es obligatorio y MUST ser una API existente; todos los roles MUST existir. Si algo falla, no se guarda nada.

#### Scenario: Crear un scope y concederlo a un rol
- **WHEN** se ejecuta `oauth:scope geografia:leer "Leer geografía" --api=api-maestros --role=admin`
- **THEN** el scope queda en `api-maestros`, concedido al rol `admin` y no al rol `user`

#### Scenario: Mover un scope a otra API
- **WHEN** se ejecuta `oauth:scope` sobre un scope existente con otra `--api`
- **THEN** el scope pasa a pertenecer a la nueva API

#### Scenario: API ausente o desconocida
- **WHEN** se ejecuta `oauth:scope` sin `--api` o con una API que no existe
- **THEN** el comando falla y el scope no se crea

#### Scenario: Rol desconocido
- **WHEN** se ejecuta `oauth:scope` con un `--role` que no existe
- **THEN** el comando falla y el scope no se crea

### Requirement: Asignación de rol a usuarios
El comando `oauth:assign-role {email} {role}` SHALL reemplazar el rol del usuario por el indicado. El usuario y el rol MUST existir. El cambio se aplica desde el siguiente login o refresh.

#### Scenario: Cambiar el rol de un usuario
- **WHEN** se ejecuta `oauth:assign-role ana@example.com admin`
- **THEN** el comando termina con éxito y el usuario queda con el rol `admin`

### Requirement: Asignación de roles a clientes OAuth
El comando `oauth:client-role {client} {roles*} [--sync]` SHALL agregar roles a un cliente, identificado por id o por nombre único. Con `--sync` MUST reemplazar los roles anteriores. Un cliente o un rol inexistente, o un nombre ambiguo, MUST hacer fallar el comando.

#### Scenario: Reemplazar los roles de un cliente
- **WHEN** un cliente con el rol `maestros-lector` recibe `matricula-lector` con `--sync`
- **THEN** ya no puede solicitar `geografia:leer` y sí puede solicitar `matricula:leer`

#### Scenario: Cliente o rol desconocido
- **WHEN** se ejecuta el comando con un cliente `no-existe` o con un rol `ghost`
- **THEN** el comando falla

### Requirement: Consulta de los scopes de un cliente
El comando `oauth:client-scopes {client}` SHALL mostrar los roles del cliente, sus scopes agrupados por API y la lista completa de scopes que puede solicitar, en orden alfabético.

#### Scenario: Listar lo que un cliente puede solicitar
- **WHEN** un cliente tiene los roles `maestros-lector` y `matricula-lector` y se ejecuta `oauth:client-scopes` con su nombre
- **THEN** la salida incluye `May request: geografia:leer homologacion:leer matricula:leer sistemas:leer`

### Requirement: Catálogo base sembrado
La siembra inicial SHALL crear la API `api-auth`, sus scopes `user:read`, `user:write` e `introspect`, y los roles `user` y `admin` (con `user:read` y `user:write`) e `introspector` (con `introspect`). MUST poder ejecutarse varias veces sin duplicar datos ni quitar concesiones hechas después.

#### Scenario: Siembra repetida
- **WHEN** se ejecuta la siembra sobre una base donde ya se concedieron scopes adicionales a un rol
- **THEN** el catálogo base sigue completo y las concesiones adicionales se conservan
