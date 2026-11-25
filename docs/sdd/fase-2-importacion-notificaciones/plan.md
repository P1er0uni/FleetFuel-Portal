# Plan — Fase 2: Importación Masiva + Notificaciones (SDD)

Orden de implementación; cada tarea deja el árbol en verde (`php artisan test`).
Fuente: `spec.md` (mismo directorio). El principio de tests es el de fase 1, a petición
del usuario: pocos tests de alto valor que agrupen los casos de la spec, no un test por caso.

## Estado actual (2026-11-25)

- **PARTE A (A1–A3): DONE.** Importación CSV con servicio, web y API; contrato `openapi.json` 0.12.0 y tests verdes.
- **PARTE B (B1–B4): DONE.** Modelo de datos, eventos + listener, pantallas web y API de notificaciones; tests verdes.
- **B5 (cierre): DONE.** Pint y Larastan en verde, suite completa en verde y documentación escrita.
- Todo está en el árbol de trabajo (rama `fleets-fase-1`), sin commit ni push.

Verificación local (PHP 8.3.37 + MySQL 8.4.13 portables en Windows, sin Docker; el MySQL se reinició con `~/tools/start-fleetfuel-mysql.ps1` en esta sesión):
- `vendor/bin/pint` → **passed**.
- `phpstan analyse` (app) → **[OK] No errors**; `-c tools/pos-simulator/phpstan.neon` → **[OK] No errors**.
- Tests nuevos verdes: `BulkImportServiceTest` 10/37, `ImportScreensTest` 6/25, `ImportApiTest` 6/70,
  `NotificationDispatchTest` 8/18, `NotificationScreensTest` 7/39, `NotificationApiTest` 5 (con contrato).
- `OpenApiContractTest` 7/221 (paridad de rutas/abilities/roles y esquema oficial).
- **Suite completa: `php artisan test` → 677 passed / 6820 assertions (172.06 s).**

## PARTE A — Importación masiva vía CSV

### A1 — Servicio de importación ✅
- [x] `App\Support\CsvImport\ImportResult` (total_rows, successful, failed, errors) y
      validador de header/límites (5000 filas, 5 MB)
- [x] `App\Services\BulkImportService`: un método por tipo (vehicles/drivers/cards) con
      resolución de `fleet_name` → `fleet_id` y de `vehicle_plate`/`driver_license` → IDs,
      transacción por fila, `dry_run`, auditoría (un `audit_logs` por importación)
- [x] Plantillas en memoria (header + fila de ejemplo) compartidas por web y API
- [x] Tests de servicio: 10 válidas → 10 insertadas; 1 duplicada → 9+1 con error;
      flota inexistente → error de fila; flota vacía → NULL; tarjeta con vehículo
      inexistente → error; dry-run no inserta; 5001 filas → rechazo; duplicado contra
      la BD; scope (manager de A no importa a B)

Evidencia A1:
- `App\Support\CsvImport\CsvTemplate` (headers/examples/filename; `gasoline`→`petrol`, D3) e `ImportResult::toArray()`.
- `BulkImportService::import(type, company, actor, dryRun, csv:, rows:)` admite CSV o filas JSON; validación con las mismas reglas que los formularios (placa normalizada, `DecimalString`, únicos), detección de duplicados dentro del archivo y contra la BD, resolución de flota/vehículo/conductor por compañía, y un `audit_logs` `import.completed` por importación (D7).
- `tests/Feature/Import/BulkImportServiceTest.php`: **10 passed / 37 assertions** (incluye 5001 filas → `ValidationException`, header erróneo, dry-run y `rows:` como array).
- El scope manager-no-importa-a-otra-compañía se cubre en web (A2) y API (A3), donde la compañía la decide el servidor.

### A2 — Web ✅
- [x] Rutas `/import` (página con 3 tabs), `/import/{type}/template` (descarga) y
      `POST /import/{type}`; roles admin + company_manager; admin elige compañía
- [x] Vistas `import/` (tabs, plantilla, subida, checkbox dry-run, tabla de
      resultados con errores por fila/campo/mensaje)
- [x] Enlace "Importar" en el menú (grupo Flota)
- [x] Tests web: página abre por rol, plantilla descargable con header exacto,
      importación con resultados visibles, operador 403, manager no elige compañía

Evidencia A2:
- `Web\ImportController` (index/template/store), `Requests\Import\ImportRequest` (usa `ResolvesCompany`), `Gate::define('importData', …)`.
- `resources/views/import/index.blade.php`: 3 pestañas Bootstrap, descarga de plantilla, formulario con `file` + `dry_run`, empresa solo para admin, y tabla de resultado (leídas/guardadas/con error) con `{row, field, message}`. Resultado vía flash + redirect (PRG).
- Rutas en `routes/web.php` dentro de `role:admin,company_manager`; enlace "Importar" en `layouts/app.blade.php`.
- `tests/Feature/Import/ImportScreensTest.php`: **6 passed / 25 assertions**.

### A3 — API ✅
- [x] `Api\V1\ImportController` (multipart `file` o JSON `rows`) y
      `ImportTemplateController` (descarga) — *implementado como un solo controlador con `store`/`template`*
- [x] Requests de API (inglés, `RejectsUnknownFields`, company_id entero JSON para admin)
- [x] Rutas con abilities `fleet:write`; contrato en `openapi.json` 0.12.0
      (3 POST + 1 GET template, con el schema del resultado de importación)
- [x] Tests API: import por multipart y por JSON, resultado documentado, scope,
      abilities, límites 422

Evidencia A3:
- `Api\V1\ImportController` + `Requests\Api\V1\ImportRequest` (file o rows, `dry_run`, `company_id` entero en JSON / numérico en multipart).
- Rutas `POST /api/v1/import/{type}` (`fleet:write`) y `GET /api/v1/import/templates/{type}` (`fleet:read`).
- `openapi.json` 0.12.0: paths `/import/{type}` y `/import/templates/{type}`, schema `ImportResult`.
- `tests/Feature/Api/ImportApiTest.php`: **6 passed / 70 assertions**; `OpenApiContractTest` 7 verdes (paridad incluida).
- **Nota:** el contrato documenta el path parametrizado `/import/{type}` en vez de tres paths separados; las "3 POST" son los tres tipos sobre ese mismo path.

## PARTE B — Notificaciones

### B1 — Modelo de datos ✅
- [x] Migraciones `notification_rules` y `notifications` (columnas string + índices de la spec)
- [x] Enums `NotificationEventType`, `NotificationChannel`, `ThresholdOperator`
- [x] Modelos `NotificationRule`, `Notification` (+ `NotificationEntity` value-object
      con `entityHash()` para la deduplicación); factories
- [x] `morphMap` y `AuditDisplay` para las entidades nuevas

Evidencia B1:
- Migraciones `2026_11_24_120000_create_notification_rules_table` y `..._120001_create_notifications_table` (CHECKs de eventos/canal/operador, índice de dedupe `(notification_rule_id, event_type, entity_hash)`).
- Enums con `label()`; `ThresholdOperator::satisfiedBy()` con brick/math; `Support\NotificationEntity::entityHash()` (sha256 de `tipo:id`).
- Modelos con scopes (`NotificationRule::visibleTo`, `Notification::forUser/unread`); `NotificationRuleFactory` y `NotificationFactory`.
- `morphMap` añade `notification_rule`; `AuditDisplay` añade entidad y acciones `notification.sent` / `notification_rule.*`.

### B2 — Eventos y listener ✅
- [x] 7 eventos (payload de IDs) + `ProcessNotificationEvent` (listener): busca reglas
      activas por compañía/flota/evento, aplica el umbral solo a `quota_warning`
      (brick/math), deduplica 1 hora por `(rule, event, entity_hash)` y entrega por canal:
      `in_app` (notifications a los company_managers), `email` (Mailable, mailer array en
      tests), `log` (audit_logs + log de Laravel)
- [x] Disparo desde los servicios: `FuelTransactionService` (quota_exceeded,
      quota_warning, fleet_rule_violated, anomaly_detected por carga rápida),
      `FuelCardService` (card_blocked), `DeliveryOrderService` (delivery_completed/cancelled)
- [x] Tests: regla por compañía vs flota ajena; regla inactiva; umbral 79% no / 80% sí;
      deduplicación 2 disparos en 1 h → 1, a 1.5 h → 2; canales in_app/email/log;
      scope por compañía

Evidencia B2:
- Eventos en `app/Events/*` sobre `DomainNotificationEvent`; registrados en `AppServiceProvider::registerNotificationEvents()` hacia un único listener.
- `ProcessNotificationEvent`: filtra por compañía/flota/evento, umbral solo `quota_warning` (`ThresholdOperator`), dedupe por `(rule, event, entity_hash)` en 60 min, y entrega `in_app` (una fila por company_manager), `email` (`NotificationMail`) y `log` (audit + `Log::warning`).
- Disparos: `FuelTransactionService::dispatchPostPurchase` (quota_warning + anomaly por carga rápida < 30 min) y `emitQuotaExceeded` tras rollback; `FleetRuleViolation` (subclase de `ValidationException` con IDs) → `FleetRuleViolated`; `FuelCardService::changeStatus` → `CardBlocked`; `DeliveryOrderService::transitionAt` → `DeliveryCompleted`/`DeliveryCancelled`.
- `tests/Feature/Notifications/NotificationDispatchTest.php`: **8 passed / 18 assertions**.
- **Nota de diseño (D11):** la deduplicación se apoya en `notifications`; solo el canal `in_app` escribe filas, así que la dedupe aplica de hecho a `in_app` (los canales email/log no crean fila marcadora).

### B3 — Web ✅
- [x] Campana en el layout con badge (unread-count, poll 60 s) y dropdown con las 5 últimas
- [x] Página `/notifications`: tabla paginada con filtros (leídas/no leídas/tipo),
      marcar leída, marcar todas
- [x] Página `/notifications/rules`: CRUD con toggle, formulario dependiente del evento
      (umbral solo en quota_warning), flota opcional de la compañía
- [x] Tests web: campana con contador, lista y filtros, marcar leída/todas,
      CRUD de reglas con scope (regla de otra compañía → 404), operador 403

Evidencia B3:
- `AppServiceProvider::shareNotificationBell()` (view composer con 2 consultas constantes), `layouts/_notifications.blade.php` (badge `data-bell-count`, dropdown, poll cada 60 s a `notifications.unread-count`).
- `Web\NotificationController` (index/unreadCount/read/markAllRead), `Web\NotificationRuleController` (index/create/store/edit/update/toggle/destroy), `NotificationRuleService`, requests `Store/UpdateNotificationRuleRequest` + `ListNotificationsRequest`, policies `NotificationPolicy`/`NotificationRulePolicy`, y binding tenant `notificationRule`.
- Vistas `notifications/index.blade.php` y `notifications/rules/{index,form}.blade.php` (umbral dependiente del evento por JS).
- `tests/Feature/Notifications/NotificationScreensTest.php`: **7 passed / 39 assertions**.

### B4 — API ✅
- [x] `Api\V1\NotificationController` (lista, unread-count, read, mark-all-read) y
      `NotificationRuleController` (CRUD)
- [x] Rutas con abilities `notifications:read`/`notifications:write` (nuevas en
      `UserRole::tokenAbilities()`); contrato en `openapi.json`
- [x] Tests API: lista paginada con filtros, unread-count, marcar leída,
      CRUD de reglas con scope y abilities, respuestas validadas contra el contrato

Evidencia B4:
- `Api\V1\NotificationController` y `Api\V1\NotificationRuleController`, requests `ListNotificationsRequest`/`ListNotificationRulesRequest`/`Store|UpdateNotificationRuleRequest` (API, `RejectsUnknownFields`), resources `NotificationResource`/`NotificationRuleResource`.
- Rutas `/api/v1/notifications*` y `/api/v1/notifications/rules*` con `notifications:read`/`notifications:write`; `UserRole::tokenAbilities()` ampliado (admin y manager).
- `openapi.json` 0.12.0: 6 paths de notificaciones + schemas `Notification`, `NotificationRule`, `NotificationRuleCreateRequest`, `NotificationRuleUpdateRequest`, `UnreadCount`, `MarkAllReadResult`.
- `tests/Feature/Api/NotificationApiTest.php`: **5 passed** (cada respuesta validada contra el contrato).

### B5 — Cierre ✅ (DONE)
- [x] Pint + Larastan level 6 sin errores (app y simulador POS)
- [x] Suite completa en verde (635 existentes + los nuevos de la fase) → **677 passed / 6820 assertions** (172.06 s)
- [x] `docs/05-API-CONTRACT.md` (secciones de import y notificaciones; tabla de abilities por rol actualizada)
- [x] `docs/03-DATA-MODEL.md` (diagrama ER + tablas `notification_rules`/`notifications`)
- [x] `CHANGELOG.md`
- [x] Actualizar `docs/sdd/fase-2-importacion-notificaciones/` con evidencia
- [x] Actualizar la tabla de abilities por rol en `docs/05-API-CONTRACT.md` (añadir `notifications:read`/`notifications:write`)

Evidencia B5:
- `vendor/bin/pint` → passed; `phpstan analyse` (app) → `[OK] No errors`; simulador POS → `[OK] No errors`.
- **Test en rojo resuelto:** `TenantIsolationTest` fallaba porque `POST /api/v1/import/{type}` respondía 404 a un invitado (el test sustituye `{type}` por `1` y el `whereIn('type', …)` impedía que la ruta casara). Se quitó el `whereIn` de las rutas de import (web y API); el `abort_unless(CsvTemplate::isType(...))` del controlador sigue dando 404 a tipos inválidos, pero el middleware de auth corre primero → ahora el invitado recibe 401/redirect. Verificado: `TenantIsolationTest` verde.
- `UserRoleTest` actualizado con las abilities `notifications:read`/`notifications:write` (Admin y Company manager), igual que la tabla de `docs/05`.
- Documentación escrita: `docs/05-API-CONTRACT.md` (filas de endpoints + secciones "Bulk import (fase 2)" y "Notifications (fase 2)" + tabla de abilities), `docs/03-DATA-MODEL.md` (relaciones del diagrama y tablas nuevas), `CHANGELOG.md` (entrada de la fase), `docs/PROGRESS.md` (sesión) y este plan.

## Cierre
Fase 2 completa localmente. Nada commiteado ni empujado. `main` cambia solo por pull request (CLAUDE.md); el cambio está en la rama de trabajo `fleets-fase-1`.
Sugerencia de commit: `feat: add CSV bulk import and notification rules`.
