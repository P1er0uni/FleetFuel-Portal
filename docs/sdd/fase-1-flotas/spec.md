# Spec — Fase 1: Flotas (SDD)

Fuente: `fase-1-flotas.md` (prompt del usuario). Esta spec aterriza el prompt a la realidad
del código y registra las decisiones. Idioma: UI y CLI en español, API JSON en inglés
(middleware `SetApiLocale`), igual que el resto del portal.

## Decisiones registradas

| # | Tema | Decisión | Origen |
|---|---|---|---|
| D1 | Rutas web | `/fleets` (sin prefijo `/admin`), consistente con `/companies`, `/vehicles`, `/cards` | Usuario |
| D2 | Eliminación | `DELETE` real de la flota; miembros quedan con `fleet_id = NULL` (FK `SET NULL`); historia en `audit_logs` | Usuario |
| D3 | Columnas enum | `status` y `rule_type` como **columnas string** + enums PHP respaldados (`FleetStatus`, `FleetRuleType`), según convención del proyecto (portabilidad SQL Server) | Convención |
| D4 | Moneda de `monthly_quota_amount` | **USD**, consistente con `fuel_cards.monthly_limit_usd` | Convención |
| D5 | `rule_value` | Columna **JSON** (no varchar), validada por MySQL y por Form Request | Adaptación |
| D6 | Cuota de flota excedida | **No bloquea** transacciones; solo marca estado en dashboard/widget | Prompt (regla 3) |
| D7 | Roles | `admin` (= `distributor_admin` del prompt) y `company_manager`; `station_operator` sin acceso | Adaptación |
| D8 | Reportes | El filtro `fleet_id` aplica sobre `fuel_cards.fleet_id` (las tarjetas son la entidad que gasta) | Adaptación |
| D9 | Conteo por día | `max_transactions_per_day` cuenta por **tarjeta** en día calendario de Beirut | Prompt (regla 7) |
| D10 | Abilities API | Reutilizar `fleet:read` / `fleet:write` ya existentes en `UserRole::tokenAbilities()` | Convención |
| D11 | Servicio | El `FleetService` existente (vehículos/conductores) se renombra a `VehicleAndDriverService`; el nuevo `FleetService` gestiona las flotas | Claridad de dominio |

## Modelo de datos

### `fleets`
- `id` PK, `company_id` FK → companies (cascade), `name` varchar(100),
  `description` varchar(255) nullable, `status` string(20) default `'active'` (`FleetStatus`),
  `monthly_quota_liters` decimal(12,3) nullable, `monthly_quota_amount` decimal(12,2) nullable (USD),
  timestamps.
- UNIQUE `(company_id, name)`; índice `company_id`.

### `fleet_rules`
- `id` PK, `fleet_id` FK → fleets (cascade), `rule_type` string(40) (`FleetRuleType`),
  `rule_value` JSON, `active` boolean default true, timestamps.
- Índice `(fleet_id, rule_type)` (una regla activa por tipo; la validación lo exige).

### Modificaciones
- `vehicles.fleet_id`, `drivers.fleet_id`, `fuel_cards.fleet_id`: bigint unsigned nullable,
  FK → fleets **ON DELETE SET NULL**.

## Reglas de negocio

1. Pertenencia 0..1 flota por registro; sin flota = comportamiento actual.
2. Scope de compañía: flota pertenece a una compañía; manager solo la suya (404 si ajena), admin todas.
3. Cuota mensual de flota = suma de `card_monthly_usage` de las tarjetas de la flota en el mes
   de Beirut; se compara contra `monthly_quota_liters` y/o `monthly_quota_amount`. Excedida ⇒
   estado visible en dashboard (D6: no bloquea).
4. `allowed_products`: transacción con producto fuera de la lista ⇒ 422 `"Product not allowed for this fleet."`
5. `allowed_stations`: estación fuera de la lista ⇒ 422 `"This station is not allowed for this fleet."`
6. `max_liters_per_transaction`: litros > límite ⇒ 422 `"This purchase exceeds the fleet's per-transaction liter limit."`
7. `max_transactions_per_day`: transacciones previas de la tarjeta ese día de Beirut ≥ límite ⇒ 422 `"This card reached the fleet's daily transaction limit."`
8. Asignación: el registro debe pertenecer a la misma `company_id` que la flota; si no, error de validación.
9. DELETE: fila eliminada, miembros a NULL, audit `fleet.deleted` (D2).
10. Auditoría: `fleet.created/updated/status_changed/deleted/assignment_changed/rules_changed`
    vía `AuditService`; etiquetas españolas en `AuditDisplay`.

## Endpoints web (Blade) — `/fleets`

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/fleets` | Listado paginado; buscador por nombre; filtro por estado; admin filtra por compañía |
| GET | `/fleets/create` | Formulario (admin elige compañía activa) |
| POST | `/fleets` | Crear |
| GET | `/fleets/{fleet}` | Detalle con pestañas: resumen (cuota/consumo/estado), vehículos, conductores, tarjetas, reglas |
| GET | `/fleets/{fleet}/edit` | Editar |
| PUT | `/fleets/{fleet}` | Actualizar |
| DELETE | `/fleets/{fleet}` | Eliminar (D2) |
| PATCH | `/fleets/{fleet}/status` | Activar/desactivar |
| POST | `/fleets/{fleet}/rules` | Crear regla |
| PUT | `/fleets/{fleet}/rules/{rule}` | Editar regla |
| DELETE | `/fleets/{fleet}/rules/{rule}` | Eliminar regla |
| POST | `/fleets/{fleet}/assign` | Asignar miembros (arrays `vehicle_ids`, `driver_ids`, `card_ids`) |
| POST | `/fleets/{fleet}/unassign` | Desasignar (NULL) |

Roles: `admin` y `company_manager` (middleware `role:` + `FleetPolicy` + binding con tenant scope).

## Endpoints API (Sanctum) — `/api/v1/fleets`

| Método | Ruta | Ability |
|---|---|---|
| GET | `/api/v1/fleets` | `fleet:read` |
| POST | `/api/v1/fleets` | `fleet:write` |
| GET | `/api/v1/fleets/{fleet}` | `fleet:read` |
| PUT | `/api/v1/fleets/{fleet}` | `fleet:write` |
| DELETE | `/api/v1/fleets/{fleet}` | `fleet:write` |
| GET | `/api/v1/fleets/{fleet}/usage` | `fleet:read` |
| GET | `/api/v1/fleets/{fleet}/vehicles` | `fleet:read` |
| GET | `/api/v1/fleets/{fleet}/drivers` | `fleet:read` |
| GET | `/api/v1/fleets/{fleet}/cards` | `fleet:read` |

Respuestas en inglés (sobreescrituras de `messages()` como los demás requests de API).

## Integración

1. `FuelTransactionService::record()`: tras `ensureCardAccepts`, si la tarjeta tiene flota,
   `FleetRuleService::ensureAccepts($card, $station, $product, $purchase)` valida las reglas
   activas (422 `ValidationException`).
2. `UsageReconciliation`: sin cambios de corrección; la cuota de flota se calcula leyendo
   `card_monthly_usage` (el contador ya reconciliado).
3. `ReportRepository`: filtro `fleet_id` vía `fuel_cards.fleet_id` en consumo, top estaciones,
   excepciones de cuota, anomalías y eficiencia; `ReportScope` gana `fleetId` (manager limitado
   a flotas de su compañía).
4. `DashboardController`: widget "Consumo por flota" (nombre, litros del mes, % cuota, estado)
   para admin y company_manager.
5. Formularios de vehículo/conductor/tarjeta: `<select>` "Flota (opcional)" con flotas activas
   de la compañía; guardado y validación de `fleet_id` (misma compañía).
6. Menú lateral: "Flotas" en el grupo Flota, después de "Tarjetas".
7. `AuditDisplay`: entidades `fleet` ("Flota") y `fleet_rule` ("Regla de flota") + acciones.

## Testing (mínimo 30 tests nuevos)

- Unit: creación + unique (company_id, name); asignación cruzada ⇒ error; cuota agregada
  (3 tarjetas, suma); DELETE ⇒ miembros NULL.
- Reglas en ingesta POS: cada tipo, caso válido y caso 422 (incluido histórico).
- Feature web: CRUD completo, reglas, asignar/desasignar, toggle, aislamiento de tenant (404).
- Feature API: endpoints con token, permisos por ability, scoping.
- Integración: reporte con filtro flota; widget de dashboard.
- Suite completa existente (593) sigue en verde.
