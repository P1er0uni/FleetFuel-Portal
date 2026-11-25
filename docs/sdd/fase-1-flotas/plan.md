# Plan — Fase 1: Flotas (SDD)

Orden de implementación; cada tarea deja el árbol en verde (`php artisan test`).

## T1 — Modelo de datos y modelos
- [x] Migración `create_fleets_table` + `create_fleet_rules_table` + `add_fleet_id_to_fleet_members`
- [x] Enums `App\Enums\FleetStatus`, `App\Enums\FleetRuleType`
- [x] Modelos `Fleet`, `FleetRule`; relación `fleet()` en Vehicle/Driver/FuelCard
- [x] `FleetFactory`, `FleetRuleFactory`
- [x] morphMap: `fleet`, `fleet_rule` (AppServiceProvider)

## T2 — Servicio y reglas
- [x] `FleetService`: create/update/delete/toggleStatus/assign/unassign + auditoría
- [x] `FleetRuleService` (o sección en FleetService): CRUD de reglas + `ensureAccepts()` para ingesta
- [x] `FleetQuota::for(Fleet, month)`: consumo agregado del mes (card_monthly_usage)
- [x] Integración en `FuelTransactionService::record()`
- [x] Unit tests de servicio y reglas

## T3 — Web CRUD ✅
- [x] `FleetPolicy` + registro en AppServiceProvider
- [x] Form Requests (web, español): Store/Update Fleet, Rules, Assign/Unassign
- [x] `Web\FleetController` (reglas incluidas) + `FleetQuota::many()` para la página
- [x] Rutas `/fleets` + nav "Flotas" (grupo Flota, tras Tarjetas)
- [x] Vistas: index (cuota/%), form, show (pestañas resumen/vehículos/conductores/tarjetas/reglas), partials
- [x] Select de flota en formularios de vehículo/conductor/tarjeta (concern `ResolvesFleet`)
- [x] `AuditDisplay`: entidades `fleet`/`fleet_rule` + acciones `fleet.*`
- [x] Feature tests web: 12 tests (CRUD, reglas, asignar/desasignar, toggle, aislamiento 404)

## T4 — API ✅ (2026-11-24)
- [x] `Api\V1\FleetController` (+ listas de miembros y usage)
- [x] Requests de API (inglés) extendiendo los web
- [x] Rutas `/api/v1/fleets` con abilities `fleet:read`/`fleet:write`
- [x] Contrato: operaciones en `docs/api/openapi.json` (versión 0.11.0)
- [x] Feature tests API: `tests/Feature/Api/FleetsApiTest.php`, 11 tests / 248 aserciones, cada respuesta validada contra el contrato

Evidencia T4:
- `Api\V1\FleetController`: index/store/show/update/destroy/usage/vehicles/drivers/cards;
  `{fleet}` llega con tenant scope (binding existente), writes vía `FleetService`.
- `FleetResource` (+`FleetRuleResource`): lista/escritura con los 7 campos; el detalle añade
  `rules` (ordenadas por tipo) y `usage` (`FleetQuota::toArray()`, nueva).
- Requests: `ListFleetsRequest` (compañía solo admin), `ListFleetMembersRequest`,
  `StoreFleetRequest`/`UpdateFleetRequest` API con `RejectsUnknownFields` y mensajes en inglés.
- `openapi.json` 0.11.0: 6 paths, 9 operaciones `implemented` con `x-abilities`/`x-roles`,
  schemas `Fleet`, `FleetDetail`, `FleetRule`, `FleetUsage`, `FleetCreateRequest`, `FleetUpdateRequest`.
- `docs/05-API-CONTRACT.md`: sección "Fleets (fase 1)"; `CHANGELOG.md`: entrada de la fase.
- Verificación local (PHP 8.3.37 + MySQL 8.4.13 portables en Windows, sin Docker):
  `FleetsApiTest` 11/11; `OpenApiContractTest` + `FleetApiTest` + `AbilitiesAndPoliciesTest` 21/21;
  `tests/Feature/Fleets` + `TenantIsolationTest` + `DocumentationParityTest` 43/43; Pint PASS; Larastan `[OK]`.
- Corregido de T2: `FleetService` normalizaba `monthly_quota_liters` a escala 2 (validación y columna admiten 3).
- Corregido de T1: el diagrama ER de `docs/03-DATA-MODEL.md` no incluía las 5 relaciones de flotas
  (`ErDiagramTest` en rojo); tablas `fleets`/`fleet_rules` documentadas en "Master data".

## T5 — Reportes ✅ (2026-11-24)
- [x] `ReportScope` con `fleetId`; filtro en `ReportRepository` (consumo, top estaciones, excepciones, anomalías, eficiencia)
- [x] Filtro "Flota" en la cabecera de reportes
- [x] Tests de reporte con filtro (2 nuevos, a petición del usuario: uno de repositorio que cubre
      los cinco reportes a la vez, uno de pantalla que cubre filtro + flota ajena; más una línea
      en un test existente del API)

Evidencia T5:
- `ReportScope` gana `fleetId` (constructor, `for()`, `forConsole()`); el manager sigue clavado a
  su compañía, así que una flota ajena no casa nada (verificado en SQL).
- `ReportRepository`: `fleetCondition()` (`fuel_card_id IN (SELECT id FROM fuel_cards WHERE fleet_id = ?)`)
  en `ledgerWhere` (consumo, top estaciones, sobrellenados), en el WHERE exterior de cargas rápidas y
  eficiencia (la ventana LAG sigue viendo todas las cargas del vehículo: el predecesor es contexto
  físico, no fila), y `f.fleet_id = ?` en excepciones de cuota (parámetro opcional).
- `ReportRequest`: `fleet_id` validado (existe + pertenece a la compañía filtrada/admin o a la propia
  del manager); el API hereda el parámetro y queda documentado en `openapi.json` (parámetro `fleet_id`
  en `GET /reports/consumption`).
- `_header.blade.php`: select "Flota", links que conservan el filtro, subtítulo con la flota.
- Tests: `ReportRepositoryTest::test_the_fleet_filter_applies_to_every_report` (5 reportes + flota
  ajena vacía), `ReportScreensTest::test_the_fleet_filter_narrows_the_report_and_refuses_foreign_fleets`,
  y el caso del API dentro de `ReportApiTest::test_consumption_filters_are_scoped_and_allowlisted`.
  26/26 en verde en los tres suites; Pint PASS; Larastan `[OK]`.

## T6 — Dashboard ✅ (2026-11-24)
- [x] Widget "Consumo por flota" en `DashboardController` + vista
- [x] Test del widget (1 nuevo + una línea en el test del manager de Cedar para el estado vacío)

Evidencia T6:
- `DashboardController` pasa `fleets` (`visibleTo`, ordenadas por nombre) y
  `fleetQuotas` (`FleetQuota::many`, una consulta para toda la lista): el número de
  consultas de la página sigue fijo (`test_the_dashboard_runs_a_fixed_number_of_queries` en verde).
- `dashboard.blade.php`: tarjeta "Consumo por flota" con nombre (enlace al detalle),
  litros del mes, barra de progreso con % de la cuota de litros y estado
  ("En orden" / "Cuota excedida" / "Sin cuota de litros"); estado vacío con enlace a `/fleets`.
- `DashboardTest::test_the_dashboard_shows_each_fleets_monthly_consumption_against_its_quota`
  cubre los tres estados, las cifras contra el consumo real de septiembre y el scoping
  (la flota de Cedar no aparece para el gerente de Atlas).

## T7 — Cierre ✅ (2026-11-24)
- [x] `AuditDisplay`: entidades y acciones de flota (hecho en T3: `fleet`/`fleet_rule` y las
      seis acciones `fleet.*`)
- [x] Pint + Larastan level 6 sin errores (app y simulador POS)
- [x] Suite completa en verde: **635 tests / 6470 aserciones** en 267 s
- [x] Actualizar `docs/sdd/fase-1-flotas/` con evidencia

Evidencia T7:
- Suite completa: `php artisan test` → 635 passed / 6470 assertions (267.34 s), localmente
  con PHP 8.3.37 y MySQL 8.4.13 portables en Windows, sin Docker.
- Pint PASS sobre `app`, `tests` y `routes`; Larastan `[OK] No errors` (app) y
  `[OK] No errors` (simulador POS, `tools/pos-simulator/phpstan.neon`).
- Entorno local: PHP 8.3.37 NTS + Composer en `~/tools/php-8.3`, MySQL 8.4.13 en
  `~/tools/mysql-8.4` (proceso de usuario, sin servicio de Windows; arranque con
  `~/tools/start-fleetfuel-mysql.bat`), usuarios `fleetfuel`/`fleetfuel_test` con
  las credenciales de `.env`/`.env.testing`.
- Los tests JS (14) y el build de Vite no se ejecutaron en esta sesión: ningún
  archivo JS cambió en la fase. CI los corre en el push.
