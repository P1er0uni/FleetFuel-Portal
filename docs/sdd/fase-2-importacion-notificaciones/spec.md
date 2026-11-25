# Spec — Fase 2: Importación Masiva + Notificaciones (SDD)

Fuente: `fase-2-importacion-notificaciones.md` (prompt del usuario). Esta spec aterriza el
prompt a la realidad del código y registra las decisiones. Idioma: UI y CLI en español,
API JSON en inglés (middleware `SetApiLocale`), igual que el resto del portal.

## Decisiones registradas

| # | Tema | Decisión | Origen |
|---|---|---|---|
| D1 | Rutas web | Sin prefijo `/admin`: `/import` y `/notifications`, consistente con `/fleets`, `/companies` (fase 1, D1) | Convención |
| D2 | Columnas enum | `channel`, `event_type`, `threshold_operator` y `status` de import como **columnas string** + enums PHP (`NotificationChannel`, `NotificationEventType`, `ThresholdOperator`), según convención del proyecto (portabilidad SQL Server) | Convención |
| D3 | Columnas del CSV | La plantilla usa los campos reales del sistema; el header se valida exacto. Campos del prompt que no existen en el schema se omiten: `vehicle_type` y `license_expiry` no existen; `monthly_quota_liters` de tarjetas mapea a `monthly_limit_l`; `gasoline` mapea a `petrol` | Adaptación |
| D4 | Atomicidad | Transacción por fila: las válidas se insertan, las inválidas se saltan con su error. `dry_run` valida todo sin insertar nada. Sin rollback total | Prompt (regla 5) |
| D5 | Límites | Máximo 5000 filas y 5 MB por archivo; excederse es 422 (API) / error de validación (web) | Prompt (regla 6) |
| D6 | Compañía del import | Como en todo el sistema: el admin elige `company_id` (activa), el manager no puede enviarla (su compañía se toma del servidor) | Convención |
| D7 | Auditoría de import | Un registro de `audit_logs` por importación (no por fila) con tipo, filas exitosas/fallidas y modo dry-run | Prompt (regla 7) |
| D8 | API de import | `POST /api/v1/import/{vehicles,drivers,cards}` acepta multipart (`file`) o JSON (`rows`); `GET /api/v1/import/templates/{type}` devuelve la plantilla. Contrato en `openapi.json` 0.12.0, respuestas en inglés, abilities `fleet:write` | Adaptación |
| D9 | `anomaly_detected` | El sistema no tiene detección de "monto/horario inusual". Se aterriza como **carga rápida en ingesta**: una compra del mismo vehículo a menos de 30 min de la anterior (la misma definición del reporte de anomalías, `ReportRepository::RAPID_FILL_SECONDS`), detectada al aceptar la compra. No se inventan umbrales nuevos | Adaptación |
| D10 | Canales | `in_app` crea `notifications` para los company_managers de la compañía; `email` envía un Mailable a los mismos (mailer `array` en tests; el envío real queda pendiente de configurar SMTP, como ya pasa con el resto del portal); `log` escribe en `audit_logs` y en el log de Laravel | Prompt |
| D11 | Deduplicación | Sin tabla nueva: antes de crear una notificación se busca la última del mismo `(notification_rule_id, event_type, entity_hash)` en `notifications`; si existe y tiene menos de 1 hora, no se crea otra. `entity_hash` es un hash estable de los IDs de la entidad del evento | Prompt (paso 5) |
| D12 | Umbral | `threshold_value`/`threshold_operator` solo aplican a `quota_warning` (porcentaje de cuota); el resto de eventos ignora el umbral. `gte`/`lte`/`eq` sobre el porcentaje con brick/math | Adaptación |
| D13 | Disparo de eventos | Laravel Events + un listener `ProcessNotificationEvent`, síncrono (`QUEUE_CONNECTION=sync`): los eventos se disparan desde `FuelTransactionService` (cuota, reglas de flota, anomalía), `FuelCardService` (bloqueo) y `DeliveryOrderService` (entregas) | Prompt |
| D14 | Campana | Icono en el layout con badge de no leídas (`GET /api/v1/notifications/unread-count`, poll 60 s), dropdown con las 5 más recientes y enlace a la página completa | Prompt |
| D15 | Abilities API | `notifications:read`/`notifications:write` nuevos en `UserRole::tokenAbilities()` para admin y company_manager; el operador no recibe ninguno | Convención |
| D16 | Tests | El prompt pide mínimo 35; a petición del usuario se mantiene el principio de fase 1: pocos tests de alto valor que cubran los casos listados agrupados, no un test por caso | Usuario |

## Parte A: Importación masiva vía CSV

### Formato de los CSV (plantillas del sistema)

- **Vehículos:** `plate_no,fuel_type,tank_capacity_l,odometer_km,fleet_name`
  (ej: `ABC-123,diesel,200,150000,Camiones`). `fuel_type`: `petrol`/`diesel` (el `gasoline`
  del prompt se acepta como sinónimo de `petrol`).
- **Conductores:** `name,phone,license_no,fleet_name`
  (ej: `Juan Perez,+56912345678,ABC12345,Camiones`).
- **Tarjetas:** `card_no,vehicle_plate,driver_license,monthly_limit_l,status,fleet_name`
  (ej: `FC-0001,ABC-123,ABC12345,500,active,Camiones`). `monthly_limit_usd` queda null
  (ilimitado); `status`: `active`/`blocked`.

### Reglas

1. Header exacto a la plantilla; si no, error de archivo (422).
2. `fleet_name` se resuelve por `(company_id, name)`; vacío → `fleet_id = NULL`;
   inexistente → error de fila. En tarjetas, `vehicle_plate` → `vehicle_id` y
   `driver_license` → `driver_id` por `(company_id, ...)`; si se nombran y no existen,
   error de fila.
3. Validación por fila con las mismas reglas que los formularios (placa normalizada y
   única por compañía, teléfono, decimales como strings, duplicados dentro del CSV y
   contra la BD).
4. Respuesta: `total_rows`, `successful`, `failed`, `errors: [{row, field, message}]`
   (fila 1 = primera de datos).
5. Transacción por fila; `dry_run` valida sin insertar. Límites: 5000 filas, 5 MB.
6. Auditoría: un `audit_logs` por importación con el resumen.

## Parte B: Notificaciones

### Modelo de datos

- `notification_rules`: `company_id` FK (cascade), `fleet_id` FK nullable (SET NULL),
  `event_type` string(50), `channel` string(20), `threshold_value` decimal(12,3) nullable,
  `threshold_operator` string(3) nullable, `active` boolean. Índice `(company_id, active)`.
- `notifications`: `company_id` FK, `notification_rule_id` FK (cascade), `user_id` FK nullable,
  `event_type` string(50), `title` varchar(255), `message` text, `payload` json nullable,
  `entity_hash` varchar(64) (deduplicación), `read_at` timestamp nullable.
  Índices `(company_id, read_at)` y `(user_id, read_at)`.

### Eventos (7)

`quota_exceeded`, `quota_warning`, `card_blocked`, `anomaly_detected` (D9),
`delivery_completed`, `delivery_cancelled`, `fleet_rule_violated` — disparados desde
los servicios, con payload de IDs; el listener busca reglas activas que matcheen
(compañía, flota opcional, evento, umbral en `quota_warning`) y entrega por canal,
con deduplicación de 1 hora (D11).

### Endpoints

- Web: `/import` (tabs, plantillas, subida, dry-run) y `/notifications` +
  `/notifications/rules` (lista, marcar leída, leer todas, CRUD de reglas con toggle).
- API: `POST /api/v1/import/{vehicles,drivers,cards}`, `GET /api/v1/import/templates/{type}`,
  `GET /api/v1/notifications`, `GET /api/v1/notifications/unread-count`,
  `PATCH /api/v1/notifications/{id}/read`, `POST /api/v1/notifications/mark-all-read`,
  y el CRUD de reglas. Todo en `openapi.json` 0.12.0.
