## 1. Login con Microsoft sin alta

- [x] 1.1 `User::fromMicrosoft` devuelve `null` en vez de crear el usuario cuando no hay usuario con ese `oid` ni con ese email
- [x] 1.2 `MicrosoftLoginController` registra `login_fallido` con motivo `sin_cuenta` cuando no hay usuario con ese email, y `cuenta_vinculada_a_otra` cuando lo hay
- [x] 1.3 `MicrosoftLoginTest`: "persona nueva" pasa a probar el rechazo (mensaje, sin sesión, sin usuario creado); los tests que dependían del alta crean el usuario con la factory
- [x] 1.4 Ajustar `AuthenticationEventsTest` si depende del alta y agregar el caso `sin_cuenta` con Microsoft

## 2. Eliminar los comandos `oauth:*`

- [x] 2.1 Reescribir `ClientScopesTest` asignando los roles del cliente sin comandos; borrar los tests que solo prueban comandos
- [x] 2.2 Borrar los 9 comandos de `app/Console/Commands`, `Concerns` y `OAuthScopeCommandsTest`
- [x] 2.3 Buscar y quitar cualquier otra referencia a los comandos en código y tests

## 3. Documentación y verificación

- [x] 3.1 README: reemplazar la administración por consola por el portal de API-IAM y agregar el orden de la primera instalación en "Despliegue"
- [x] 3.2 Correr `vendor/bin/pint --dirty --format agent` y la suite completa de tests contra el SQL Server de Docker
