## Context

`applications.disabled_at` y `roles.disabled_at` los agrega y escribe API-IAM (`aplicaciones-baja`). API-Auth resuelve roles y scopes en `User::rolesIn` y `allowedScopeNames`, `OAuthScope::grantedToClient`, `namesForClient` y `namesForClientInApplication`, y `ScopeRepository` (que además arma `aud` y `roles`). El acceso al login lo decide `RequireRolesInClientApplication` con `rolesIn`. Los clientes activos los busca Passport con `ClientRepository::findActive`, y la introspección ya reporta inactivos los tokens de clientes revocados. Las personas dadas de baja ya se manejan con `users.disabled_at`.

## Goals / Non-Goals

**Goals:**
- Que un rol o una aplicación de baja deje de dar acceso y scopes en el siguiente token, y que los clientes de una aplicación de baja queden fuera de inmediato.

**Non-Goals:**
- Revocar los tokens de personas emitidos antes de la baja de un rol (rigen hasta vencer, como cualquier cambio de roles).

## Decisions

**D1. Un solo filtro de «rol vigente».** Un scope local de Eloquent en `Role` (`vigentes`: `roles.disabled_at` nulo y su aplicación con `disabled_at` nulo) usado por `rolesIn`, `allowedScopeNames` y `grantedToClient`. Así el acceso, los scopes y la claim `roles` salen del mismo criterio.

**D2. Scopes de una aplicación de baja.** `getScopeEntityByIdentifier` y los métodos de nombres excluyen los scopes cuya aplicación tiene `disabled_at` (un scope pedido de una aplicación de baja da `invalid_scope`).

**D3. Clientes.** Un cliente cuya aplicación está de baja se trata como no activo: extender la búsqueda de cliente activo (el `ClientRepository` de Passport ya enlazado o el punto donde la app lo resuelve) para exigir además que su aplicación esté activa. Eso cubre emisión, refresh, `TokenGuard` y la introspección (que ya usa `findActive`).

**D4. Esquema de tests.** Agregar `disabled_at` a `applications` y `roles` en la copia del esquema que usan los tests de API-Auth, igual que se hizo con `users.disabled_at`.

## Risks / Trade-offs

- [Desplegar antes que la migración de API-IAM] → Las consultas fallarían por columnas inexistentes. Orden obligatorio: migración de API-IAM primero.
- [Más condiciones en cada emisión] → Son joins por clave sobre tablas chicas.

## Migration Plan

Desplegar después de correr la migración de API-IAM `aplicaciones-baja`. Volver atrás: la versión anterior ignora las columnas.
