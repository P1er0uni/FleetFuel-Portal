# Spec — Fase 4 / Área 1: Administración de usuarios (solo Administrador)

Fuente: conversación con el usuario (análisis de huecos frente a ATIONET, área 1). Objetivo:
que **solo el rol `admin`** pueda crear y administrar cuentas desde la aplicación web (hoy solo
existen comandos de consola). La UI y los mensajes van en español; la lógica se comparte con el
servicio existente.

## Contexto actual (estado real del código)

- Existe `App\Services\UserAccountService` con `create`, `activate`, `deactivate` y `ensureValidScope`.
- Existen comandos CLI `users:create`, `users:activate`, `users:deactivate` (contraseña por prompt oculto).
- **No hay ninguna ruta ni pantalla de usuarios** (`/users` → 404 para todos los roles). Verificado en vivo.
- La tabla `users` ya tiene `role`, `company_id`, `station_id`, `is_active`, y la restricción
  `users_role_scope_check`: admin sin empresa/estación, gerente exactamente una empresa, operador
  exactamente una estación.
- Existe auditoría (`AuditService`) y las acciones `user.created/activated/deactivated` ya se emiten
  desde el servicio; falta auditar la edición, cambio de rol/alcance y restablecimiento de contraseña.
- Roles: `App\Enums\UserRole` = `admin`, `company_manager`, `station_operator`.

## Decisiones registradas

| # | Tema | Decisión |
|---|---|---|
| D1 | Alcance de la pantalla | Solo web (sesión), sección **Administración → Usuarios**, visible y accesible únicamente para `admin`. La API de usuarios queda **fuera** de esta fase. |
| D2 | Quién gestiona | Únicamente `admin`. `company_manager` y `station_operator` reciben **403**; invitados van al login. Política `UserPolicy` + middleware `role:admin`. |
| D3 | Reutilización | La UI llama a `UserAccountService` (extendido). Los comandos CLI se mantienen como están. |
| D4 | Rol y alcance | Al crear/editar se respetan las reglas de `users_role_scope_check`: admin sin empresa/estación; gerente exactamente una **empresa activa**; operador exactamente una **estación activa**. Validación en Form Request **y** servicio. |
| D5 | Cambio de rol/alcance | Permitido al admin; se **audita** y se **revocan sesiones y tokens** del afectado (cambian sus privilegios). |
| D6 | Desactivación | Desactivar cierra sesiones y revoca tokens (ya lo hace el servicio). **No hay borrado físico** de usuarios (el historial/auditoría los referencia); se desactiva. |
| D7 | Contraseñas | El admin nunca ve contraseñas. Al **crear** y al **restablecer** fija una contraseña temporal y marca `must_change_password=true`; revoca sesiones/tokens y audita. El usuario debe cambiarla en su próximo ingreso. Mínimo 12 caracteres. |
| D8 | Autoprotección | El admin **no puede desactivarse a sí mismo** ni **degradar su propio rol**; y no se puede dejar el sistema **sin ningún admin activo**. |
| D9 | Email | Único y normalizado a minúsculas; editable por el admin. |
| D10 | Auditoría | Acciones nuevas: `user.updated`, `user.role_changed`, `user.password_reset`. Se mantienen `user.created/activated/deactivated`. Se registran solo campos de negocio (rol, empresa, estación, estado); **nunca** contraseñas. |

## Fuera de alcance (explícito)

- Autorregistro público.
- Recuperación de contraseña por correo (“olvidé mi contraseña”).
- Autogestión de perfil (cambiar el propio nombre/email) — salvo el **cambio obligatorio** de contraseña tras un restablecimiento (D7).
- Endpoints de API para usuarios.
- Borrado de usuarios.

## Modelo de datos

- No cambia el esquema salvo **una columna nueva** para D7:
  - `users.must_change_password boolean not null default false`.
- Se reutilizan `role`, `company_id`, `station_id`, `is_active`, `email`, `name`.

## Rutas (web, solo admin)

| Método | Ruta | Acción | Descripción |
|---|---|---|---|
| GET | `/users` | index | Lista con búsqueda y filtros (rol, empresa, estación, estado) |
| GET | `/users/create` | create | Formulario de alta |
| POST | `/users` | store | Crea la cuenta + contraseña temporal |
| GET | `/users/{user}/edit` | edit | Formulario de edición |
| PUT | `/users/{user}` | update | Edita nombre/email y (si aplica) rol/alcance |
| PATCH | `/users/{user}/status` | updateStatus | Activa/desactiva |
| POST | `/users/{user}/password` | resetPassword | Restablece contraseña temporal |

- `{user}` se resuelve sin scope de empresa (el admin gestiona a todos), pero **nunca** elimina ni
  permite acciones sobre sí mismo peligrosas (D8).
- No existe ruta `DELETE`.
- Cambio obligatorio de contraseña: `GET/PUT /password/change` (cualquier usuario autenticado con
  `must_change_password=true`), con middleware que redirige a esa pantalla mientras el flag esté activo.

## Validaciones (Form Requests, español)

**Crear (`StoreUserRequest`)**
- `name`: requerido, texto, ≤255.
- `email`: requerido, email válido, ≤255, único en `users` (normalizado a minúsculas).
- `role`: requerido, uno de `admin|company_manager|station_operator`.
- `company_id`: requerido **solo** si rol = gerente (empresa **activa** y existente); prohibido en otros roles.
- `station_id`: requerido **solo** si rol = operador (estación **activa** y existente); prohibido en otros roles.
- `password` + `password_confirmation`: requeridos, mínimo 12, coincidentes.

**Editar (`UpdateUserRequest`)**
- Igual que crear para `name`, `email` (único ignorando el propio), `role`, `company_id`, `station_id`.
- Sin contraseña (se cambia por la ruta de restablecimiento).

**Estado (`ChangeUserStatusRequest`)**
- `is_active`: booleano requerido.
- Reglas D8: no desactivar el propio usuario; no dejar 0 admins activos.

**Restablecer contraseña (`ResetUserPasswordRequest`)**
- `password` + `password_confirmation`: requeridos, mínimo 12, coincidentes.

**Cambio obligatorio (`ChangePasswordRequest`)**
- `current_password` (la temporal), `password` + confirmación (mínimo 12, distinta a la temporal).

Los mensajes se redactan en español. La validación de rol/alcance se replica en
`UserAccountService::ensureValidScope` (defensa en profundidad + restricción de BD).

## Lógica de servicio (extender `UserAccountService`)

- `create(...)` — ya existe; se mantiene (añade `must_change_password=false`).
- `update(User $user, array $data, User $actor): User`
  - Valida `ensureValidScope`.
  - Si cambian `role/company_id/station_id`: audita `user.role_changed` y **revoca tokens** y **sesiones**.
  - Si cambian nombre/email: audita `user.updated` con antes/después (sin secretos).
- `resetPassword(User $user, string $password, User $actor): void`
  - Fija `password`, `must_change_password=true`, `remember_token` nuevo.
  - Revoca tokens y sesiones; audita `user.password_reset`.
- `changeOwnPassword(User $user, string $current, string $new): void` (para el flujo obligatorio)
  - Verifica la actual; fija la nueva; `must_change_password=false`; audita `user.password_changed`.
- `deactivate/activate` — ya existen; se conservan (auditoría y corte de sesiones/tokens).

Todo dentro de `DB::transaction`. La contraseña **nunca** se audita ni se registra en logs.

## Autorización

- `UserPolicy`: `viewAny/create/update/updateStatus/resetPassword` → solo `admin` activo.
- Middleware `role:admin` en el grupo de rutas + policies (defensa en profundidad).
- El propio `admin` no puede ejecutar acciones destructivas sobre su cuenta (D8), pero sí editar su nombre/email.
- Gerente/operador: 403. Invitado: redirección a login.

## UI (Blade + Bootstrap, español)

- **Menú:** “Administración → Usuarios” (solo admin).
- **Lista (`users/index`):** tabla con nombre, correo, rol, empresa/estación, estado y acciones
  (Editar, Activar/Desactivar, Restablecer contraseña). Buscador y filtros por rol, empresa, estación y estado.
  Paginación.
- **Formulario (`users/form`):** nombre, correo, rol (select). Los campos **Empresa** (si gerente) y
  **Estación** (si operador) se muestran/ocultan según el rol (JS mínimo); contraseña + confirmación
  solo al crear.
- **Restablecer contraseña:** formulario/modal con contraseña temporal + confirmación.
- **Cambio obligatorio:** pantalla simple `password/change` que se muestra antes de cualquier otra página
  mientras `must_change_password` sea verdadero.
- Feedback con alertas de éxito/errores; nada de contraseñas en pantalla ni en HTML.

## Casos borde

- Intentar dejar el sistema sin admin activo (o desactivar al último admin) → error claro.
- Admin desactivándose a sí mismo → prohibido.
- Correo duplicado → error de validación (BD también lo impide).
- Empresa/estación inactiva al crear/editar → error de validación.
- Cambio de rol que contradice el alcance (p. ej. gerente sin empresa) → error.
- Desactivar un usuario invalida sesiones y tokens al instante (reutiliza el servicio existente).
- Restablecer contraseña mientras el usuario está conectado → debe reautenticarse (sesión cerrada).

## Pruebas (agrupadas por valor, mínimo ~10)

1. **Admin lista y busca** usuarios; filtra por rol/empresa/estación/estado.
2. **Admin crea** un gerente (con empresa) y un operador (con estación); se guardan con su alcance y se audita `user.created`.
3. **Creación inválida:** gerente sin empresa/operador sin estación/admin con empresa → error; correo duplicado → error.
4. **Admin edita** nombre/email; se audita `user.updated`.
5. **Cambio de rol/alcance** por el admin: se guarda, se audita `user.role_changed`, se revocan tokens/sesiones del afectado.
6. **Activar/desactivar:** desactivar impide login y revoca tokens/sesiones; activar restaura.
7. **Restablecer contraseña:** fija temporal, marca `must_change_password`, revoca sesiones/tokens, audita; el usuario debe cambiarla al entrar; el nuevo hash valida y el viejo no.
8. **Autoprotección:** admin no puede desactivarse a sí mismo ni dejar 0 admins activos.
9. **Autorización:** gerente y operador reciben 403 en todas las rutas de usuarios; invitado va al login.
10. **Restricción de BD:** un insert directo con rol/alcance inválido es rechazado por `users_role_scope_check`.
11. **No hay borrado:** `DELETE /users/{id}` no existe (405/404).
12. **Auditoría sin secretos:** ninguna fila de `audit_logs` contiene la contraseña.

## Criterios de aceptación

- Un administrador puede, desde la web: listar/buscar/filtrar, crear, editar, cambiar rol/alcance,
  activar/desactivar y restablecer contraseñas.
- Un no-admin no puede acceder a ninguna ruta de usuarios.
- Se respetan las reglas de rol/alcance en validación, servicio y base de datos.
- Desactivar y restablecer cortan sesiones/tokens; el restablecimiento obliga a cambiar contraseña.
- Toda acción sensible queda auditada, sin contraseñas.
- La suite completa sigue en verde; Pint y Larastan limpios.
