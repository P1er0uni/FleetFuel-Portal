# Evidencia — Fase 6 / Área 3: Estaciones habilitadas por empresa

Fecha: 2026-11-26. Rama: `fase-6-estaciones-por-empresa`.

## Comandos y resultados

| Comando | Resultado |
| --- | --- |
| `php artisan migrate` (dev) y DB de testing | `companies.restrict_stations` + tabla `company_stations` |
| `php artisan test tests/Feature/CompanyStations` | **10 passed / 25 assertions** |
| `vendor/bin/pint` / `phpstan analyse` | passed / `[OK] No errors` |
| `php artisan test` (suite completa) | 746 passed + 1 fallo inicial en `ErDiagramTest` (diagrama sin las nuevas relaciones); corregido el diagrama |

## Qué se implementó

- **Datos:** `companies.restrict_stations` (default false) y `company_stations` (unique `(company_id, station_id)`).
- **Servicio `CompanyStationService`:** `allowed()` (sin restricción = todas; con restricción = solo las habilitadas) y `sync()` (valida estaciones activas, reemplaza el acuerdo y audita `company.stations_changed`).
- **POS:** `FuelTransactionService` comprueba la habilitación tras `ensureCardAccepts` y antes de reglas de flota/cotización; rechaza con **403 `station_not_allowed`** (`PurchaseDeclined::stationNotAllowed()`). El replay idempotente sigue antes.
- **Web (solo admin):** `Web\CompanyStationController` + `UpdateCompanyStationsRequest` + vista `companies/stations` (interruptor + checkboxes de estaciones activas + resumen); enlace desde la edición de empresa.
- **Docs:** `03-DATA-MODEL` (tabla y diagrama), `05-API-CONTRACT` (código), `CHANGELOG`, `PROGRESS`.

## Pruebas (agrupadas)

`CompanyStationsTest` (7): sin restricción permite cualquier estación; restricción bloquea no habilitadas (403 `station_not_allowed`, sin escribir); habilitada permite; lista vacía bloquea todo; aplica a todas las tarjetas de la empresa; combinación con regla de flota; `sync` persiste y audita.

`CompanyStationScreensTest` (3): admin abre y guarda el acuerdo; gerente/operador 403; estación inactiva rechazada.

## Fuera de alcance (como en el spec)

- Precios/restricciones de producto por empresa y filtrado de la lista de estaciones del gerente.
