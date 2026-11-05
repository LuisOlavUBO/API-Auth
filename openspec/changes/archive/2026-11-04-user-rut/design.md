# Design

## Context

El registro valida con `RegisterRequest` y crea el usuario con `new User($request->validated())`, así que basta con que `rut` sea validado y asignable. El perfil sale de `UserResource`, que usan `/v1/me`, el registro e introspect. El cambio `user-uuid` (sin archivar) ya modifica el requisito del perfil. Ver proposal.md para el motivo.

## Goals / Non-Goals

**Goals:**
- Un solo formato de RUT guardado, para que los satélites puedan comparar textos.

**Non-Goals:**
- Una tabla o un tipo propio para el RUT, o validar documentos extranjeros.

## Decisions

- **Normalizar antes de validar** (`prepareForValidation` en `RegisterRequest`): quitar puntos y espacios, pasar a mayúscula y agregar el guion antes del último carácter si no lo trae. Así la regla de unicidad compara contra el formato guardado: `12.345.678-5` choca con `12345678-5`. Alternativa descartada: normalizar en un mutator del modelo, porque la validación de unicidad correría con el texto sin normalizar.
- **Regla `App\Rules\Rut`** (creada con `make:rule`): exige el formato `^\d{7,8}-[\dK]$` y calcula el dígito verificador con módulo 11. Una regla propia es más simple que agregar un paquete de validación chilena (dependencia nueva).
- **Columna `string('rut', 10)` nullable con índice único filtrado** (`WHERE rut IS NOT NULL`), creado con `DB::statement`, porque Blueprint no permite índices filtrados. En SQL Server un índice único normal admite un solo `NULL`, y la mayoría de los usuarios no tendrá RUT al principio.
- **Fuera del token:** no se toca `AccessToken`. Los satélites que lo necesiten lo leen de `/v1/me` o de introspect.

## Risks / Trade-offs

- [Orden de archivo] El requisito del perfil en esta spec incluye `uuid` → archivar `user-uuid` antes que `user-rut`.
- [Usuarios existentes quedan sin RUT] Es esperado: el campo es opcional y su carga posterior está fuera de alcance.

## Migration Plan

1. Desplegar con la migración. No modifica filas existentes.
2. Rollback: `down` borra el índice y la columna.
