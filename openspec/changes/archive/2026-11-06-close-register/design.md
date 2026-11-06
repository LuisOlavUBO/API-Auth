# Design

## Context

El registro es la acción `register` de `AuthController` con `RegisterRequest` (validación y normalización del RUT). El rol por defecto se busca con `config('oauth.default_role')` y `config('oauth.application')`, que nadie más usa. Ver proposal.md para el motivo.

## Goals / Non-Goals

**Goals:**
- Que no exista ninguna forma pública de crear una cuenta.

**Non-Goals:**
- El portal de administración o un comando `oauth:user` para crear usuarios.
- Tocar los usuarios ya creados por registro o sus contraseñas.

## Decisions

- **Borrar la ruta, no bloquearla.** Sin ruta, Laravel responde el 404 en Problem Details como cualquier ruta inexistente. Alternativa descartada: dejarla respondiendo 403 o 410, que obliga a mantener código para algo que no existe.
- **Borrar `RegisterRequest` y las claves `default_role` y `application` de `config/oauth.php`.** Quedarían sin uso. El rol `user` que crea `RoleScopeSeeder` se mantiene (es dato de demo y otros tests lo usan); solo se corrige su descripción, que habla de usuarios registrados.
- **Conservar `App\Rules\Rut` y `RutTest`.** La regla es independiente del registro y el cambio toca solo lo necesario; el portal la usará al dar de alta usuarios con RUT.
- **Tests:** se borran los de registro de `AuthTest` (incluidos los de RUT y rol por defecto, que solo se podían ejercitar por el registro) y se agrega uno que comprueba 404 sin usuario creado.

## Risks / Trade-offs

- [Alguien usa el registro] Recibirá 404. Ningún satélite lo usa: entran por el login central.
- [No hay forma de crear usuarios con contraseña] Aceptado hasta el portal: las personas de la UBO entran con Microsoft.
- [Cuentas ya preregistradas con el email de otra persona] Siguen existiendo; revisarlas queda fuera de alcance.
