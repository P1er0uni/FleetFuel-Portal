# Evidencia — Fase 4 / Área 1: Administración de usuarios (solo Admin)

Fecha: 2026-11-26. Rama: `fase-4-usuarios-admin`.

## Comandos y resultados

| Comando | Resultado |
| --- | --- |
| `php artisan migrate --force` | `2026_11_26_120000_add_must_change_password_to_users_table` DONE |
| `php artisan test tests/Feature/Users` | **15 passed / 64 assertions** |
| `vendor/bin/pint` | Passed |
| `phpstan analyse` (app) | `[OK] No errors` |
| `php artisan test` (suite completa) | **725 passed / 7182 assertions** (255.74 s) |

## Qué se implementó

- **Datos:** migración `users.must_change_password` (boolean, default false); cast en `User`; `UserFactory` incluye el valor.
- **Servicio `UserAccountService`:** `create` (marca `must_change_password`), `update` (nombre/email y rol/alcance, con auditoría y revocación al cambiar rol/alcance), `resetPassword` (temporal + flag + revocación), `changeOwnPassword`, `revokeAccess`. Protecciones: no desactivar la propia cuenta ni dejar 0 admins activos.
- **Autorización:** `UserPolicy` (solo admin). Rutas bajo `role:admin`.
- **Web:** `Web\UserController` (index/create/store/edit/update/updateStatus/resetPassword), Form Requests (español), vistas `users/index` y `users/form` (campos Empresa/Estación según rol; restablecer contraseña), enlace “Usuarios” en Administración.
- **Cambio obligatorio:** middleware `RequirePasswordChange` (alias `password.changed`) + `/password/change` (`ChangePasswordController`, `ChangePasswordRequest`, vista).
- **Auditoría:** `user.created/updated/role_changed/password_reset/password_changed/activated/deactivated`; sin contraseñas. `AuditDisplay` con las etiquetas.
- **Sin borrado físico:** no existe ruta `DELETE /users/{id}` (405).

## Pruebas (agrupadas)

`tests/Feature/Users/UserManagementTest` (11): listar/filtrar; crear gerente; validación de rol/alcance y email; editar; cambio de rol revoca tokens y audita; activar/desactivar; autoprotección; no-admin 403 / invitado al login; sin DELETE; restricción de BD; auditoría sin contraseñas.

`tests/Feature/Users/PasswordChangeTest` (4): la temporal fuerza el cambio; cambiar la contraseña limpia el flag y valida; contraseña actual incorrecta se rechaza; usuario normal no forzado.

## Pendiente/observaciones

- La API de usuarios queda fuera (solo web), según el spec.
- No hay recuperación de contraseña por correo ni autogestión de perfil (fuera de alcance).
- `users:create/activate/deactivate` siguen disponibles por consola y ahora `create` también marca `must_change_password`.
