# Plan — Fase 5 / Área 2: Reglas y límites de flota

Orden de implementación; cada tarea deja el árbol en verde (`php artisan test`).
Fuente: `fase-5-reglas-limites-flota.md` (mismo directorio). Decisiones D1–D11 aprobadas.
Rama sugerida: `fase-5-reglas-limites-flota`.

## T1 — Datos y enums
- [ ] Migración `add_enforcement_and_validity_to_fleet_rules_table`:
  - `enforcement varchar(10) not null default 'block'` + CHECK (`block`, `warn`).
  - `valid_from date null`, `valid_to date null`.
- [ ] `App\Enums\RuleEnforcement` (`block`/`warn`) con `label()` (`Bloqueante` / `Solo aviso`).
- [ ] `App\Models\FleetRule`: cast `enforcement => RuleEnforcement`, `valid_from`/`valid_to` => `date`; defaults.
- [ ] `FleetRuleFactory`: `enforcement => RuleEnforcement::Block`, `valid_from/valid_to => null`; helpers `warning()`, `validBetween()`.
- [ ] `App\Enums\FleetRuleType`: nuevos casos `MaxLitersPerDay`, `MaxAmountPerTransactionUsd`, `MaxAmountPerDayUsd`, `AllowedTimeWindows`; `label()`; helpers `isListRule()`, `isNumericRule()`, `isWindowsRule()`, `unit()`.

## T2 — Validación de reglas (trait reutilizable)
- [ ] `App\Http\Requests\Fleets\Concerns\ValidatesFleetRules` (trait): reglas para un prefijo (`rule_type`, `enforcement`, `valid_from`, `valid_to`, `active`, `ids/ids.*`, `value`, `windows/windows.*.days/start/end`), mensajes en español y normalización a `rule_value`.
  - `ids.*` valida **productos** para `allowed_products` y **estaciones** para `allowed_stations` (activos).
  - numéricos: `DecimalString` (L y USD escala 2; transacciones entero).
  - horarios: `windows` no vacío, días 1..7 no vacíos, `HH:MM`, `end > start`.
- [ ] `StoreFleetRuleRequest` usa el trait (mantiene su firma pública `ruleType()`, `ruleValue()`, `active()`; añade `enforcement()` y `validity()`).

## T3 — Servicio de reglas
- [ ] `FleetRuleService::save()` acepta `RuleEnforcement` y vigencia; guarda en `fleet_rules`; audita `fleet.rules_changed` con `rule_type`, `rule_value`, `enforcement`, `valid_from`, `valid_to`, `active`.
- [ ] `delete()` sin cambios salvo auditoría.
- [ ] `FleetRuleService::ensureAccepts(FuelCard $card, Station $station, Product $product, PosPurchase $purchase, PriceQuote $quote): array` (devuelve lista de avisos `warn`):
  - Carga reglas activas del `fleet_id`; **filtra por vigencia** (día del evento en Beirut).
  - Evalúa cada tipo (ver catálogo del spec):
    - listas: producto/estación en `ids`.
    - `max_liters_per_transaction`, `max_amount_per_transaction_usd`: contra el `quote`.
    - `max_transactions_per_day` (compras previas del día), `max_liters_per_day` (litros del día + quote), `max_amount_per_day_usd` (USD del día + quote): por tarjeta y día de Beirut.
    - `allowed_time_windows`: día ISO + franja `HH:MM` (Beirut).
  - Si incumple y `enforcement=block` → `throw FleetRuleViolation::forRule(...)` (422).
  - Si incumple y `enforcement=warn` → añade aviso `{rule_type, message, enforcement}` y continúa.
- [ ] Actualizar pruebas existentes de reglas que cambian de firma/orden si aplica.

## T4 — Ingesta POS (orden y avisos)
- [ ] `FuelTransactionService::record()`: mover la **cotización** antes de las reglas de flota y pasar el `PriceQuote` a `ensureAccepts` (D8).
- [ ] Recoger los avisos devueltos; tras insertar la compra (misma transacción) disparar `FleetRuleViolated` con `enforcement = 'warn'` por cada aviso.
- [ ] `App\Events\FleetRuleViolated`: añadir parámetro opcional `enforcement` (default `'block'`) y ajustar `context()`/`message()` para distinguir aviso vs bloqueo (mismo `event_type`).
- [ ] Mantener el disparo del evento en el caso bloqueante (tras el rollback, como hoy).

## T5 — Crear/editar flota con reglas (UX)
- [ ] `StoreFleetRequest`: aceptar `rules` opcional (array de reglas iniciales) usando el trait con prefijo `rules.*`.
- [ ] `FleetService::createWithRules(Company, array $data, array $rules, User $actor)`: en una transacción, crear la flota y guardar sus reglas (vía `FleetRuleService::save`); si una regla es inválida, no se crea nada.
- [ ] `FleetController@store` usa `createWithRules`.
- [ ] Partial reutilizable `fleets/_rules-editor.blade.php` (usado en create, edit y show):
  - Resumen legible: tipo, valor con unidad, **Bloqueante/Solo aviso**, vigencia, activa; botón Eliminar.
  - Formulario “Agregar o reemplazar regla” con campos **según el tipo** (JS): productos (multiselect productos), estaciones (multiselect estaciones), límites (input + unidad), horarios (editor de franjas días+hora), `enforcement`, vigencia (desde/hasta), activo.
  - **Elimina el multiselect mezclado actual**.
- [ ] En el formulario de **crear**, sección “Reglas iniciales” construida con el mismo editor (envía `rules[...]`).
- [ ] `FleetController@edit/show` cargan `ruleOptions` separados por tipo (productos / estaciones) y las reglas existentes.

## T6 — Pruebas (agrupadas por valor, ~14)
- [ ] **Regresión** de los 4 tipos actuales (productos, estaciones, litros/transacción, transacciones/día).
- [ ] `max_liters_per_day`: total del día + compra supera → bloquea; dentro → pasa.
- [ ] `max_amount_per_transaction_usd` y `max_amount_per_day_usd`: bloquean/pasan.
- [ ] `allowed_time_windows`: dentro de franja/día → pasa; fuera → bloquea.
- [ ] Vigencia: fuera de rango no aplica; dentro aplica.
- [ ] **Aviso vs bloqueo**: `warn` permite la compra y crea notificación `fleet_rule_violated`; `block` la rechaza y no escribe.
- [ ] Orden quote→reglas: un límite de monto usa el precio/tasa del evento.
- [ ] Crear flota con reglas: válidas → se crean flota+reglas; una inválida → no se crea la flota.
- [ ] Editor: productos solo lista productos, estaciones solo estaciones.
- [ ] Validación horarios: días vacíos o `end <= start` → error.
- [ ] Auditoría `fleet.rules_changed` con enforcement/vigencia.
- [ ] Autorización: gerente de otra empresa 404; operador 403.
- [ ] Notificación de aviso respeta deduplicación de 1 h.

## T7 — Cierre
- [ ] `vendor/bin/pint` y `phpstan analyse` (app + simulador POS) sin errores.
- [ ] Suite completa en verde (725 actuales + nuevos).
- [ ] Docs: `docs/03-DATA-MODEL.md` (columnas y enum de reglas), `CHANGELOG.md`, `docs/PROGRESS.md`,
  y copia de `spec.md`/`plan.md` + `EVIDENCE.md` en `docs/sdd/fase-5-reglas-limites-flota/`.
- [ ] Nota: sin cambios de API/`openapi.json` (solo web) salvo que se decida exponer reglas nuevas por API (fuera de alcance).

## Notas de implementación
- Cálculo “por día” por **tarjeta** y día de Beirut; consultas acotadas (pocas por compra, no N+1 por filas).
- Franjas que cruzan medianoche no se admiten; usar dos franjas.
- Los avisos (`warn`) no bloquean; se notifican con el evento existente para no tocar el CHECK de `notifications`.
- Reutilizar `x-form.*`, componentes de tablas y el estilo del proyecto; JS mínimo para mostrar campos por tipo.
- Actualizar los tests de POS/reglas si el nuevo orden quote→reglas cambia algún código de error esperado.

## Criterios de aceptación (resumen)
- Todas las reglas (incluidas **estaciones permitidas**) se administran con campos claros desde crear/editar/detalle.
- Nuevos límites (litros/día, USD/transacción, USD/día, horarios) funcionan en el POS.
- Cada regla es bloqueante o solo aviso, aplicándose como corresponde (rechazo o permiso+notificación).
- Vigencia y auditoría operativas; suite completa, Pint y Larastan en verde.
