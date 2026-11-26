# Plan — Fase 6 / Área 3: Estaciones habilitadas por empresa

Orden de implementación; cada tarea deja el árbol en verde (`php artisan test`).
Fuente: `fase-6-estaciones-por-empresa.md` (mismo directorio). Decisiones D1–D8 aprobadas.
Rama sugerida: `fase-6-estaciones-por-empresa`.

## T1 — Datos y modelo
- [ ] Migración `add_restrict_stations_to_companies_and_create_company_stations_table`:
  - `companies.restrict_stations boolean not null default false`.
  - Nueva tabla `company_stations` (`company_id` FK cascade, `station_id` FK cascade, unique `(company_id, station_id)`, índice `company_id`).
- [ ] `App\Models\Company`: cast `restrict_stations => boolean`; relación `stations()` (belongsToMany vía `company_stations`).
- [ ] `App\Models\CompanyStation` (fillable company_id, station_id; relaciones).
- [ ] `CompanyFactory`: `restrict_stations => false` (evita el modo estricto en pruebas).

## T2 — Servicio `CompanyStationService`
- [ ] `allowed(Company $company, Station $station): bool`
  - `!restrict_stations` → true; si restringe → existe fila en `company_stations`.
- [ ] `sync(Company $company, bool $restrict, array $stationIds, User $actor): void`
  - Transacción; empresa inactiva → 403 `company_inactive`; bloqueo de la empresa.
  - Valida estaciones existentes y **activas** (ValidationException `station_ids`).
  - Actualiza `restrict_stations`; reemplaza filas de `company_stations` (inserta las indicadas, borra las demás); conserva la selección si el interruptor se apaga.
  - Audita `company.stations_changed` con antes/después (`restrict_stations` + lista).
- [ ] `App\Support\AuditDisplay`: etiqueta `company.stations_changed`.

## T3 — Ingesta POS
- [ ] `PurchaseDeclined::stationNotAllowed()` → 403 `station_not_allowed`, mensaje claro.
- [ ] `FuelTransactionService`: inyectar `CompanyStationService`; en `record()`, tras `ensureCardAccepts` y **antes** de cotizar/reglas de flota, comprobar `allowed($card->company, $station)`; si no → `PurchaseDeclined::stationNotAllowed()`.
- [ ] El replay (idempotencia) sigue antes de todo: un reintento de compra aceptada responde 200 sin reevaluar.
- [ ] Docs: `docs/05-API-CONTRACT.md` añade el código `station_not_allowed`.

## T4 — Web (solo admin)
- [ ] Rutas en `role:admin`: `GET /companies/{company}/stations` (`companies.stations.edit`) y `PUT /companies/{company}/stations` (`companies.stations.update`).
- [ ] `Web\CompanyStationController` (edit/update) usando `CompanyStationService`.
- [ ] `Requests\Reference\UpdateCompanyStationsRequest`: `restrict_stations` booleano; `station_ids` array nullable; `station_ids.*` entero existe y activo; mensajes español.
- [ ] Vista `companies/stations.blade.php`: interruptor “Restringir estaciones”, checkboxes de estaciones **activas** (marcadas las habilitadas), Guardar; resumen legible.
- [ ] Enlace “Estaciones habilitadas” desde la edición de empresa; columna/resumen en la lista de empresas (opcional).
- [ ] El gerente/operador no acceden (403, ruta admin).

## T5 — Pruebas (agrupadas, ~10)
- [ ] **Por defecto:** empresa sin restricción acepta compra en cualquier estación activa (no rompe lo actual).
- [ ] **Con lista:** estación habilitada → acepta; no habilitada → 403 `station_not_allowed` y no escribe.
- [ ] **Lista vacía:** bloquea todas.
- [ ] **Aplica a toda la empresa:** dos tarjetas distintas afectadas igual.
- [ ] **Combinación con flota:** habilitación por empresa + reglas de flota se aplican en conjunto.
- [ ] **Replay:** compra aceptada antes de restringir, reintentada → 200.
- [ ] **Admin sincroniza:** guardar interruptor + lista persiste y audita `company.stations_changed`.
- [ ] **Autorización:** gerente/operador 403; invitado al login.
- [ ] **Validación:** `station_ids.*` inexistente/inactivo → error.
- [ ] **Historial:** compras previas y reportes no cambian al restringir.

## T6 — Cierre
- [ ] `vendor/bin/pint` y `phpstan analyse` (app + simulador POS) sin errores.
- [ ] Suite completa en verde (las actuales + nuevas).
- [ ] Docs: `docs/03-DATA-MODEL.md` (`companies.restrict_stations` + `company_stations`), `docs/05-API-CONTRACT.md` (código de error), `CHANGELOG.md`, `docs/PROGRESS.md`, y copia de `spec.md`/`plan.md` + `EVIDENCE.md` en `docs/sdd/fase-6-estaciones-por-empresa/`.
- [ ] Sin cambios de `openapi.json` (no hay endpoints nuevos).

## Notas de implementación
- `companies.restrict_stations=false` por defecto ⇒ compatibilidad total con datos y pruebas existentes.
- La comprobación es por **empresa** (aplica a todas sus tarjetas), y se combina con las reglas de flota (ambas deben permitir).
- No se tocan compras históricas ni reportes; solo compras nuevas.
- Reutilizar políticas de empresa y `role:admin`; `{company}` ya es tenant-scoped.
- Evitar lecturas extra costosas: una consulta EXISTS por compra (o cachear por request si aplica).

## Criterios de aceptación (resumen)
- Admin activa restricción y elige estaciones por empresa; por defecto todas permitidas; lista vacía bloquea todo.
- POS rechaza con 403 `station_not_allowed` fuera de la lista, sin escribir; replay idempotente intacto.
- Las reglas de flota siguen aplicándose en conjunto; auditoría de cambios; suite/Pint/Larastan en verde.
