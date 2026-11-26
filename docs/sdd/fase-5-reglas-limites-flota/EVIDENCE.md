# Evidencia — Fase 5 / Área 2: Reglas y límites de flota

Fecha: 2026-11-26. Rama: `fase-5-reglas-limites-flota`.

## Comandos y resultados

| Comando | Resultado |
| --- | --- |
| `php artisan migrate` (dev) y DB de testing | columnas `enforcement`/`valid_from`/`valid_to`; CHECK `fleet_rules_type_check` ampliado a 8 tipos |
| `php artisan test tests/Feature/Fleets/FleetRuleLimitsTest.php tests/Feature/Fleets/FleetRuleEditorTest.php` | **12 passed / 25 assertions** |
| `php artisan test tests/Feature/Pos tests/Feature/Notifications tests/Feature/Fleets` | 135 passed / 698 assertions |
| `vendor/bin/pint` / `phpstan analyse` | passed / `[OK] No errors` |
| `php artisan test` (suite completa) | **737 passed / 7207 assertions** (232.00 s) |

## Qué se implementó

- **Datos:** `fleet_rules.enforcement` (`block`/`warn`), `valid_from`, `valid_to`; enum `RuleEnforcement`; CHECK de `rule_type` ampliado.
- **Tipos de regla:** además de los 4 existentes, `max_liters_per_day`, `max_amount_per_transaction_usd`, `max_amount_per_day_usd`, `allowed_time_windows` (días ISO + franjas `HH:MM`).
- **Servicio:** `FleetRuleService::ensureAccepts(card, station, product, purchase, quote)` evalúa todas las reglas (listas, límites por transacción/día en L y USD, horarios), filtra por vigencia, devuelve avisos `warn` o lanza `FleetRuleViolation` (`block`).
- **POS:** cotiza antes de reglas (para límites de monto); los `warn` se registran como `FleetRuleViolated` con `enforcement=warn` aunque la compra se acepte.
- **Validación/UI:** trait `ValidatesFleetRules`; editor con campos por tipo (productos, estaciones, valor numérico, días/horas), selector bloqueante/aviso y vigencia; reemplaza el multiselect mezclado; **regla inicial opcional al crear la flota** (flota + reglas en una transacción).
- **Docs:** `03-DATA-MODEL`, `CHANGELOG`, `PROGRESS`.

## Pruebas (agrupadas)

`FleetRuleLimitsTest` (7): litros/día, importe/transacción, importe/día, horarios (dentro/fuera), vigencia, **aviso permite + notifica**, **bloqueo rechaza y no escribe**.

`FleetRuleEditorTest` (5): crear flota con regla inicial de estaciones; regla inicial inválida impide la flota; el editor muestra campos separados de productos/estaciones/aviso; guardar regla con enforcement y vigencia; validación de franjas.

## Fuera de alcance (como en el spec)

- Límites por producto, motor de reglas por tarjeta y estaciones habilitadas por empresa (área 3).
- Cambios de API/`openapi.json`.
