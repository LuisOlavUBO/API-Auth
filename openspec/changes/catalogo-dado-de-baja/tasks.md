## 1. Esquema de tests

- [x] 1.1 `disabled_at` en `applications` y `roles` en la copia del esquema de tests (D4)

## 2. Roles, scopes y clientes

- [x] 2.1 Scope `vigentes` en `Role` (D1) usado por `rolesIn`, `allowedScopeNames` y `grantedToClient`
- [x] 2.2 Scopes de aplicaciones de baja excluidos en `ScopeRepository` y los métodos de nombres (D2)
- [x] 2.3 Clientes de una aplicación de baja tratados como no activos (D3)

## 3. Tests

- [x] 3.1 Rol de baja: sin scopes ni rol en el token de usuario y de cliente; reactivado, vuelven
- [x] 3.2 Aplicación de baja: «Sin acceso» en el login central, `invalid_client` en client credentials y refresh, introspección inactiva de un token previo
- [x] 3.3 Suite completa, Pint y `openspec validate catalogo-dado-de-baja --strict`
