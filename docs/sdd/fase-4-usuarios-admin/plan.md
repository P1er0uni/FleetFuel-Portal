# Plan — Fase 4 / Área 1: Administración de usuarios (solo Administrador)

Orden de implementación; cada tarea deja el árbol en verde (`php artisan test`).
Fuente: `fase-4-usuarios-admin.md` (mismo directorio). Decisiones D1–D10 del spec, con **D7 aprobada**:
al crear y al restablecer se fija contraseña temporal y `must_change_password=true`.
Rama sugerida: `fase-4-usuarios-admin`.

## T1 — Datos y modelo
- [ ] Migración `add_must_change_password_to_users_table` (`boolean not null default false`).
- [ ] `App\Models\User`: cast `must_change_password => boolean` (no mas-assignable; se fija por `forceFill`).
- [ ] (En T7) documentar la columna en `docs/03-DATA-MODEL.md`.

## T2 — Servicio `UserAccountService`
- [ ] `create(...)`: además del alta actual, `must_change_password = true` y (ya existe) auditoría `user.created`.
- [ ] `update(User $user, array $data, User $actor): User`
  - Valida con `ensureValidScope` (admin sin empresa/estación; gerente una empresa activa; operador una estación activa).
  - Normaliza email a minúsculas; valida unicidad en el Request.
  - Si cambian `role`/`company_id`/`station_id`: audita `user.role_changed` y **revoca tokens y sesiones** del afectado.
  - Si cambian `name`/`email`: audita `user.updated` (antes/después, sin secretos).
  - Todo en `DB::transaction`.
- [ ] `resetPassword(User $user, string $password, User $actor): void`
  - Fija contraseña, `must_change_password = true`, `remember_token` nuevo.
  - Revoca tokens y sesiones (`revokeAccess`). Audita `user.password_reset`.
- [ ] `changeOwnPassword(User $user, string $current, string $new): void`
  - Verifica la actual, fija la nueva, `must_change_password = false`. Audita `user.password_changed`.
- [ ] `revokeAccess(User $user): array{tokens:int, sessions:int}` (reutilizable por desactivación/reset/cambio de rol).
- [ ] Reglas de autoprotección (D8) en el servicio: no desactivar el propio usuario; no dejar 0 admins activos.
- [ ] Tests de servicio (agrupados): crear con rol/alcance inválido falla; cambio de rol revoca tokens/sesiones; reset marca flag y audita sin secretos; `changeOwnPassword` valida la actual.

## T3 — Autorización y auditoría
- [ ] `App\Policies\UserPolicy`: `viewAny/create/update/updateStatus/resetPassword` → solo `admin` activo (usa `DeniesInactiveUsers`).
- [ ] Registro por convención de Laravel (auto-descubrimiento como las demás policies).
- [ ] `App\Support\AuditDisplay`: etiquetas `user.updated`, `user.role_changed`, `user.password_reset`, `user.password_changed`.
- [ ] Registrar acciones en `AuditService` desde el servicio (solo campos de negocio; nunca contraseñas).

## T4 — Web (CRUD de usuarios, solo admin)
- [ ] Rutas en `routes/web.php` dentro de `role:admin`:
  - `GET /users`, `GET /users/create`, `POST /users`, `GET /users/{user}/edit`, `PUT /users/{user}`,
    `PATCH /users/{user}/status`, `POST /users/{user}/password`. **Sin `DELETE`.**
- [ ] `Web\UserController` (thin): index/create/store/edit/update/updateStatus/resetPassword; usa `UserAccountService`.
- [ ] Form Requests (español): `StoreUserRequest`, `UpdateUserRequest`, `ChangeUserStatusRequest`, `ResetUserPasswordRequest`
  con las reglas del spec (rol/alcance, empresa/estación activas, contraseña ≥12 + confirmación, email único/minúsculas).
- [ ] Vistas `users/index.blade.php` y `users/form.blade.php`:
  - Tabla: nombre, correo, rol, empresa/estación, estado, acciones (Editar, Activar/Desactivar, Restablecer contraseña).
  - Buscador y filtros por rol/empresa/estación/estado; paginación.
  - Formulario: campos Empresa (si gerente) y Estación (si operador) mostrados según el rol (JS mínimo); contraseña + confirmación solo al crear.
  - Restablecer contraseña: formulario con contraseña temporal + confirmación (nunca se muestra la actual).
  - Usar los componentes `x-form.*` existentes y el estilo del proyecto.
- [ ] Enlace “Usuarios” en el menú **Administración** (visible solo admin).

## T5 — Cambio obligatorio de contraseña
- [ ] Middleware `RequirePasswordChange` (alias p. ej. `password.changed`), aplicado al grupo autenticado:
  - Si `auth()->user()->must_change_password` y la ruta no es `password.change.*` ni `logout`, redirige a `GET/PUT /password/change`.
- [ ] Rutas y `Web\ChangePasswordController` (`GET /password/change`, `PUT /password/change`) para **cualquier** usuario autenticado.
- [ ] `ChangePasswordRequest` (verifica `current_password`, nueva ≥12, distinta, confirmación).
- [ ] Vista simple `password/change`.
- [ ] Test: usuario con flag es redirigido a cambiar contraseña en cualquier página; tras cambiarla accede normal; la temporal deja de valer.

## T6 — Pruebas feature (agrupadas por valor)
- [ ] Admin lista/busca/filtra usuarios.
- [ ] Admin crea gerente (empresa) y operador (estación); se guarda el alcance y se audita `user.created`.
- [ ] Creación inválida: gerente sin empresa, operador sin estación, admin con empresa, email duplicado → error.
- [ ] Admin edita nombre/email; audita `user.updated`.
- [ ] Cambio de rol/alcance: guarda, audita `user.role_changed` y revoca tokens/sesiones del afectado.
- [ ] Activar/desactivar: desactivar impide login y revoca tokens/sesiones; activar restaura.
- [ ] Restablecer contraseña: flag + revocación + auditoría; el usuario debe cambiarla y la nueva valida.
- [ ] Autoprotección: no desactivar el propio usuario; no dejar 0 admins activos.
- [ ] Autorización: gerente/operador 403 en `/users*`; invitado → login.
- [ ] BD: `users_role_scope_check` rechaza un insert inválido.
- [ ] No existe `DELETE /users/{id}` (405/404).
- [ ] Auditoría sin secretos: ninguna fila de `audit_logs` contiene la contraseña.

## T7 — Cierre
- [ ] `vendor/bin/pint` y `phpstan analyse` (app + simulador POS) sin errores.
- [ ] Suite completa en verde (los 710 actuales + los nuevos).
- [ ] Docs: `docs/03-DATA-MODEL.md` (columna `must_change_password`), `CHANGELOG.md`, `docs/PROGRESS.md`,
  y copia de `spec.md`/`plan.md` con evidencia en `docs/sdd/fase-4-usuarios-admin/` del proyecto.
- [ ] Nota: no hay cambios de API (no se toca `openapi.json`); los comandos CLI se mantienen.

## Notas de implementación
- Sin borrado físico: se desactiva (historial/auditoría referencian al usuario).
- Email normalizado a minúsculas en Request y servicio; unicidad también en BD.
- Nada de contraseñas en auditoría, HTML, mensajes ni logs.
- La revocación de sesiones solo borra filas si `session.driver === 'database'` (comportamiento ya existente en el servicio); los tokens de Sanctum se borran siempre.
- El middleware de cambio obligatorio aplica solo a la web (no a la API).
- Reutilizar `x-form.input/select`, `partials/list-filters` y las convenciones de tablas/acciones del proyecto.

## Criterios de aceptación (resumen)
- Solo el `admin` crea y administra usuarios desde la web; gerente/operador 403.
- Reglas de rol/alcance respetadas en validación, servicio y BD.
- Desactivar/restablecer/cambiar rol cortan sesiones y tokens; crear/restablecer obligan cambio de contraseña.
- Todo queda auditado sin secretos; suite completa, Pint y Larastan en verde.
