## REMOVED Requirements

### Requirement: Administración de aplicaciones
**Reason**: La administración del catálogo pasa al portal de API-IAM, que la audita y aplica sus reglas.
**Migration**: Crear y editar aplicaciones en el portal de API-IAM.

### Requirement: Administración de roles
**Reason**: La administración del catálogo pasa al portal de API-IAM.
**Migration**: Crear y editar roles en el portal de API-IAM.

### Requirement: Administración de scopes
**Reason**: La administración del catálogo pasa al portal de API-IAM.
**Migration**: Crear permisos y concederlos a roles en el portal de API-IAM.

### Requirement: Asignación de rol a usuarios
**Reason**: Los roles de las personas los asigna API-IAM, con auditoría y reglas contra el escalamiento de privilegios.
**Migration**: Asignar y quitar roles en el portal de API-IAM; `iam:grant-admin` de API-IAM queda para el acceso de emergencia.

### Requirement: Asignación de roles a clientes OAuth
**Reason**: Los roles de los clientes de servicio los asigna API-IAM.
**Migration**: Asignar y quitar roles de clientes en el portal de API-IAM.

### Requirement: Consulta de los scopes de un cliente
**Reason**: La consulta de clientes está en API-IAM.
**Migration**: Ver el detalle del cliente y sus roles en el portal de API-IAM.

### Requirement: Catálogo base sembrado
**Reason**: En producción el catálogo base de `api-auth` lo crea `iam:install` de API-IAM. `RoleScopeSeeder` queda solo como apoyo de desarrollo y tests.
**Migration**: Correr `iam:install` de API-IAM una vez por ambiente, después de las migraciones de API-Auth.

### Requirement: Creación de clientes con su aplicación
**Reason**: Los clientes se crean en API-IAM, que guarda el secreto con bcrypt y lo muestra una sola vez.
**Migration**: Crear clientes en el portal de API-IAM.

### Requirement: Cambio de la aplicación de un cliente
**Reason**: Se elimina con el resto de los comandos; API-IAM no lo ofrece.
**Migration**: Crear un cliente nuevo en la aplicación correcta y revocar el anterior.

### Requirement: Consulta de los scopes de los roles de una aplicación
**Reason**: La consulta del catálogo está en API-IAM.
**Migration**: Ver los roles de una aplicación y sus permisos en el portal de API-IAM.
