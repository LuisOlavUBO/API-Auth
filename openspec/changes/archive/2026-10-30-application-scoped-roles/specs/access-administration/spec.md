# Spec Delta

## RENAMED Requirements

- FROM: `### Requirement: Administración de APIs`
- TO: `### Requirement: Administración de aplicaciones`

## MODIFIED Requirements

### Requirement: Administración de aplicaciones
El comando `oauth:application {name} {description?}` SHALL crear una aplicación (un satélite, una API o un monolito, dueña de permisos, roles y clientes) o actualizar la existente con ese nombre.

#### Scenario: Crear una API
- **WHEN** se ejecuta `oauth:application api-maestros "Datos maestros"`
- **THEN** el comando termina con éxito y existe la aplicación `api-maestros` con esa descripción

### Requirement: Administración de roles
El comando `oauth:role {name} {description?} --application=` SHALL crear un rol de la aplicación indicada o actualizar el existente con ese nombre en esa aplicación. `--application` es obligatorio y MUST ser una aplicación existente. En los comandos que reciben un rol, este se identifica como `<aplicación>/<rol>`.

#### Scenario: Crear un rol
- **WHEN** se ejecuta `oauth:role maestros-lector "Lectura de API-Maestros" --application=api-maestros`
- **THEN** el comando termina con éxito y existe el rol `api-maestros/maestros-lector` con esa descripción

#### Scenario: Mismo nombre en otra aplicación
- **WHEN** se ejecuta `oauth:role lector --application=portal` y ya existe `api-maestros/lector`
- **THEN** existen los dos roles, `portal/lector` y `api-maestros/lector`

#### Scenario: Aplicación ausente o desconocida
- **WHEN** se ejecuta `oauth:role` sin `--application` o con una aplicación que no existe
- **THEN** el comando falla y el rol no se crea

### Requirement: Administración de scopes
El comando `oauth:scope {name} {description} --application= --role=*` SHALL crear o actualizar un scope de la aplicación indicada y concederlo a los roles indicados (`<aplicación>/<rol>`, de cualquier aplicación), sin quitarlo de otros roles. `--application` es obligatorio y MUST ser una aplicación existente; todos los roles MUST existir. Si algo falla, no se guarda nada.

#### Scenario: Crear un scope y concederlo a un rol
- **WHEN** se ejecuta `oauth:scope geografia:leer "Leer geografía" --application=api-maestros --role=api-auth/admin`
- **THEN** el scope queda en `api-maestros`, concedido al rol `api-auth/admin` y no a `api-auth/user`

#### Scenario: Conceder a un rol de otra aplicación
- **WHEN** se ejecuta `oauth:scope geografia:leer "Leer geografía" --application=api-maestros --role=portal/vendedor`
- **THEN** el rol `portal/vendedor` concede el scope de `api-maestros`

#### Scenario: Mover un scope a otra API
- **WHEN** se ejecuta `oauth:scope` sobre un scope existente con otra `--application`
- **THEN** el scope pasa a pertenecer a la nueva aplicación

#### Scenario: API ausente o desconocida
- **WHEN** se ejecuta `oauth:scope` sin `--application` o con una aplicación que no existe
- **THEN** el comando falla y el scope no se crea

#### Scenario: Rol desconocido
- **WHEN** se ejecuta `oauth:scope` con un `--role` que no existe
- **THEN** el comando falla y el scope no se crea

### Requirement: Asignación de rol a usuarios
El comando `oauth:assign-role {email} {role} [--sync]` SHALL agregar al usuario el rol indicado (`<aplicación>/<rol>`) sin quitarle los demás. Con `--sync` MUST reemplazar solo los roles que el usuario tiene en la aplicación de ese rol. El usuario y el rol MUST existir. El cambio se aplica desde el siguiente login o refresh.

#### Scenario: Cambiar el rol de un usuario
- **WHEN** un usuario con el rol `api-auth/user` recibe `api-auth/admin` con `oauth:assign-role`
- **THEN** el usuario queda con los roles `api-auth/user` y `api-auth/admin`

#### Scenario: Reemplazar los roles de una aplicación
- **WHEN** un usuario con los roles `api-auth/user` y `portal/vendedor` recibe `api-auth/admin` con `--sync`
- **THEN** el usuario queda con `api-auth/admin` y `portal/vendedor`

#### Scenario: Usuario o rol desconocido
- **WHEN** se ejecuta el comando con un usuario o un rol que no existe
- **THEN** el comando falla

### Requirement: Asignación de roles a clientes OAuth
El comando `oauth:client-role {client} {roles*} [--sync]` SHALL agregar roles (`<aplicación>/<rol>`, de cualquier aplicación) a un cliente, identificado por id o por nombre único. Con `--sync` MUST reemplazar los roles anteriores. Un cliente o un rol inexistente, o un nombre ambiguo, MUST hacer fallar el comando.

#### Scenario: Reemplazar los roles de un cliente
- **WHEN** un cliente con el rol `api-maestros/maestros-lector` recibe `api-matricula/matricula-lector` con `--sync`
- **THEN** ya no puede solicitar `geografia:leer` y sí puede solicitar `matricula:leer`

#### Scenario: Cliente o rol desconocido
- **WHEN** se ejecuta el comando con un cliente `no-existe` o con un rol `ghost`
- **THEN** el comando falla

### Requirement: Consulta de los scopes de un cliente
El comando `oauth:client-scopes {client}` SHALL mostrar la aplicación del cliente, sus roles, sus scopes agrupados por aplicación y la lista completa de scopes que puede solicitar, en orden alfabético.

#### Scenario: Listar lo que un cliente puede solicitar
- **WHEN** un cliente tiene los roles `maestros-lector` y `matricula-lector` y se ejecuta `oauth:client-scopes` con su nombre
- **THEN** la salida incluye `May request: geografia:leer homologacion:leer matricula:leer sistemas:leer`

### Requirement: Catálogo base sembrado
La siembra inicial SHALL crear la aplicación `api-auth`, sus scopes `user:read`, `user:write` e `introspect`, y los roles de `api-auth` `user` y `admin` (con `user:read` y `user:write`) e `introspector` (con `introspect`). MUST poder ejecutarse varias veces sin duplicar datos ni quitar concesiones hechas después.

#### Scenario: Siembra repetida
- **WHEN** se ejecuta la siembra sobre una base donde ya se concedieron scopes adicionales a un rol
- **THEN** el catálogo base sigue completo y las concesiones adicionales se conservan

## ADDED Requirements

### Requirement: Creación de clientes con su aplicación
El comando `oauth:client {name} --application= --type=` SHALL crear un cliente OAuth de la aplicación indicada y mostrar su id y su secreto una sola vez. `--type` es `client-credentials` o `password`. `--application` es obligatorio y MUST ser una aplicación existente; si falla, no se crea el cliente.

#### Scenario: Crear un cliente de servicio
- **WHEN** se ejecuta `oauth:client portal-backend --application=portal --type=client-credentials`
- **THEN** el cliente existe, pertenece a `portal` y la salida muestra su id y su secreto

#### Scenario: Aplicación ausente o desconocida
- **WHEN** se ejecuta `oauth:client` sin `--application` o con una aplicación que no existe
- **THEN** el comando falla y no se crea ningún cliente

### Requirement: Cambio de la aplicación de un cliente
El comando `oauth:client-application {client} {application}` SHALL mover un cliente, identificado por id o por nombre único, a la aplicación indicada. El cliente y la aplicación MUST existir.

#### Scenario: Mover un cliente
- **WHEN** se ejecuta `oauth:client-application portal-backend api-maestros`
- **THEN** `oauth:client-scopes portal-backend` muestra `api-maestros` como su aplicación

### Requirement: Consulta de los scopes de los roles de una aplicación
El comando `oauth:role-scopes {application}` SHALL listar, por cada rol de la aplicación, los scopes que concede agrupados por aplicación dueña, para revisar que un rol solo concede lo que su aplicación usa.

#### Scenario: Listar los roles de una aplicación
- **WHEN** el rol `portal/vendedor` concede `portal:ventas.crear` y `geografia:leer` y se ejecuta `oauth:role-scopes portal`
- **THEN** la salida muestra `vendedor` con `portal:ventas.crear` bajo `portal` y `geografia:leer` bajo `api-maestros`
