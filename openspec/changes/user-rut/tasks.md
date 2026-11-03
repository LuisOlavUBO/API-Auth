# Tasks

## 1. Columna rut

- [x] 1.1 Crear con `php artisan make:migration` la migración que agrega `users.rut` (`string(10)`, nullable) y el índice único filtrado `WHERE rut IS NOT NULL`; `down` borra índice y columna. Verificar con `migrate` y `migrate:rollback` contra la base de tests en Docker (:14334), y que dos usuarios sin RUT se pueden guardar.

## 2. Registro con RUT

- [x] 2.1 Crear `App\Rules\Rut` con `php artisan make:rule` (formato `^\d{7,8}-[\dK]$` y dígito verificador módulo 11). Verificar con un test unitario que acepta `12345678-5`, `1234567-4` y un RUT válido con dígito verificador `K` y rechaza un dígito verificador incorrecto y un formato inválido.
- [x] 2.2 En `RegisterRequest` normalizar `rut` en `prepareForValidation` y validarlo como `nullable`, `Rut` y `unique:users,rut`; agregar `rut` al fillable de `User`. Verificar en `AuthTest` los escenarios de registro con RUT con puntos, RUT con dígito verificador inválido, RUT ya registrado y dos usuarios sin RUT.

## 3. Perfil

- [x] 3.1 Agregar `rut` a `UserResource`. Verificar en `AuthTest` que `/v1/me` responde el `rut`, en `TokenIntrospectionTest` que `user.rut` viene en la introspección, y que el payload del JWT no contiene el RUT.
- [x] 3.2 Documentar `rut` en `docs/openapi/openapi.yaml` (esquema `Usuario` y body de registro) y en `docs/bruno/Usuarios/Registrar.bru` y `Perfil.bru`. Verificar que `DocsTest` pasa.

## 4. Cierre

- [x] 4.1 Correr `vendor/bin/pint --dirty --format agent` y la suite completa con la base de tests en Docker, y verificar que todo pasa.

## Workflow follow-up

- Archivar `user-uuid` antes que este cambio; luego archivar con `/opsx:archive`.
