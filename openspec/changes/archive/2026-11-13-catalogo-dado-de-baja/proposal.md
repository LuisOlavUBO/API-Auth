# Proposal

## Why

API-IAM va a permitir dar de baja aplicaciones y roles de forma reversible (cambio `aplicaciones-baja` de API-IAM), con una columna `disabled_at` en `applications` y en `roles`. Para que la baja tenga efecto, API-Auth tiene que dejar de contar un rol de baja y de atender una aplicación de baja al dar acceso, conceder scopes y emitir tokens, igual que ya hace con las personas dadas de baja.

## What Changes

- **Rol de baja** (`roles.disabled_at`): no cuenta como rol de la persona en la aplicación (ni para el acceso al login central ni en la claim `roles`), y sus scopes no se conceden a personas ni a clientes.
- **Aplicación de baja** (`applications.disabled_at`):
  - nadie entra a ella por el login central (pantalla «Sin acceso»);
  - sus clientes no obtienen ni renuevan tokens (`invalid_client`, como un cliente revocado);
  - sus roles no cuentan y sus scopes no se conceden en ningún token;
  - la introspección reporta inactivos los tokens de sus clientes.
- La copia del esquema que API-Auth usa en sus tests suma las columnas, que en los demás ambientes agrega la migración de API-IAM.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `scope-authorization`: roles y aplicaciones de baja no conceden scopes.
- `central-login`: sin acceso a una aplicación de baja ni por roles de baja.
- `token-introspection`: tokens de clientes de una aplicación de baja, inactivos.

## Impact

- `app/Models/User.php` (`rolesIn`, `allowedScopeNames`), `app/Models/OAuthScope.php` (`grantedToClient`), `app/Services/ScopeRepository.php`, `app/Http/Middleware/RequireRolesInClientApplication.php`, la búsqueda de clientes activos y `TokenIntrospectionController`; sus tests; la copia del esquema de tests.
- **Depende de la migración de API-IAM `aplicaciones-baja`**: se despliega después de correrla, porque lee columnas que crea API-IAM.
