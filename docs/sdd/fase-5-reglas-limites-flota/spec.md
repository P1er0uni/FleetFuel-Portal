# Spec — Fase 5 / Área 2: Reglas y límites de flota

Fuente: conversación con el usuario (análisis de huecos frente a ATIONET, área 2). Objetivo:
hacer que un gerente pueda **administrar bien las reglas y límites de una flota** (incluida **en qué
estaciones puede recargar**) y ampliar los límites disponibles, con reglas **bloqueantes** o **solo aviso**.

## Contexto actual (estado real del código)

- `fleet_rules` guarda **una regla por (fleet_id, rule_type)** en `rule_value` (JSON) con `active`.
- Tipos actuales (`App\Enums\FleetRuleType`): `allowed_products`, `allowed_stations`,
  `max_liters_per_transaction`, `max_transactions_per_day`. Se aplican en la ingesta POS desde
  `FleetRuleService::ensureAccepts()` dentro del bloqueo de la tarjeta; una violación es 422.
- **Problema de UX detectado:** el formulario de reglas (`fleets/_rules.blade.php`) usa **un único
  multiselect** que mezcla productos y estaciones, y solo está en la página de detalle (no al crear la
  flota). Por eso “no se podía administrar en qué estación recarga la flota” de forma clara.
- Las cuotas por tarjeta (`monthly_limit_l/usd`) siguen siendo independientes; no hay motor de reglas
  por tarjeta.
- Existe el evento/notificación `fleet_rule_violated` (reglas de notificación por evento).

## Decisiones registradas

| # | Tema | Decisión |
|---|---|---|
| D1 | Ámbito | Reglas **por flota** (`fleet_rules`). Las cuotas mensuales de tarjeta no cambian; no se añade motor de reglas por tarjeta en esta fase. |
| D2 | Tipos nuevos | Añadir: `max_liters_per_day`, `max_amount_per_transaction_usd`, `max_amount_per_day_usd`, `allowed_time_windows`. Mantener los cuatro existentes. |
| D3 | Bloqueo vs aviso | Nueva columna `enforcement` (`block`/`warn`, default `block`). **block** → rechaza la compra (422 `fleet_rule_violated`); **warn** → permite la compra y **notifica** (evento existente `fleet_rule_violated` con `enforcement=warn`). No se crea un `event_type` nuevo (evita migrar el CHECK de `notifications`). |
| D4 | Vigencia | Nuevas columnas `valid_from`/`valid_to` (date, nullable). Fuera del rango, la regla **no aplica**. |
| D5 | UX | Editor por tipo con el campo correcto: multiselect de **productos** para productos, multiselect de **estaciones** para estaciones, campo numérico para límites, editor de **franjas/días** para horarios. Disponible en **crear**, **editar** y **detalle** de la flota. Se elimina el select mezclado actual. |
| D6 | Alta con reglas | Al crear la flota se pueden definir reglas; se guardan en la misma transacción (crear flota → crear reglas) y luego se editan. |
| D7 | Cálculos | Día = calendario de **Beirut**. Litros desde el `quote->liters`; montos desde `quote->amountUsd`. Los límites “por día” se calculan **por tarjeta** (la tarjeta pertenece a la flota) sumando `fuel_transactions` de ese día, incluida la compra en curso. |
| D8 | Orden de validación | Para poder evaluar límites de **monto**, el precio (quote) se calcula **antes** de aplicar las reglas de flota (hoy se aplican antes de cotizar). La cuota de tarjeta se sigue comprobando después, con el bloqueo del contador. |
| D9 | Horarios | Días ISO (1=Lunes … 7=Domingo); franjas `HH:MM` en hora de Beirut; se permiten **varias franjas**; válida si el instante cae en alguna franja de un día permitido. |
| D10 | Auditoría | Los cambios de reglas se siguen auditando (`fleet.rules_changed`), incluyendo enforcement y vigencia. |
| D11 | Fuera de esta fase | **Límites por producto** (cuota por producto), **estaciones habilitadas por empresa** (área 3), y el motor de reglas por tarjeta. Quedan preparados como fase posterior. |

## Historias de usuario

Ver HU-01…HU-12 arriba (resumen): administrar reglas en alta/edición; productos y estaciones permitidos;
máx litros/transacción; máx litros/día; máx USD/transacción; máx USD/día; días y horarios; regla
bloqueante vs aviso; vigencia; resumen y auditoría; aplicación real en el POS.

## Catálogo de reglas

| `rule_type` | `rule_value` (JSON) | Evaluación |
|---|---|---|
| `allowed_products` | `{"ids":[1,2]}` | El producto debe estar en la lista. |
| `allowed_stations` | `{"ids":[1,3]}` | La estación debe estar en la lista. |
| `max_liters_per_transaction` | `{"value":"60.00"}` | `quote.liters <= value`. |
| `max_transactions_per_day` | `{"value":"3"}` | Nº de compras de la tarjeta ese día `< value` (antes de la actual). |
| `max_liters_per_day` | `{"value":"300.00"}` | Litros del día (tarjeta) + `quote.liters <= value`. |
| `max_amount_per_transaction_usd` | `{"value":"50.00"}` | `quote.amountUsd <= value`. |
| `max_amount_per_day_usd` | `{"value":"200.00"}` | USD del día (tarjeta) + `quote.amountUsd <= value`. |
| `allowed_time_windows` | `{"windows":[{"days":[1,2,3,4,5],"start":"06:00","end":"20:00"}]}` | El instante (Beirut) cae en alguna franja de un día permitido. |

Cada regla activa y dentro de vigencia se evalúa; si se incumple y `enforcement=block` → rechaza;
si `enforcement=warn` → se registra un aviso (notificación) y la compra continúa.

## Modelo de datos (migración)

- `fleet_rules` añade:
  - `enforcement varchar(10) not null default 'block'` con CHECK (`block`, `warn`).
  - `valid_from date null`, `valid_to date null`.
- `rule_value` sigue siendo JSON; los tipos nuevos usan los formatos de la tabla.
- Sin cambios en `fuel_transactions`, `notifications` ni `openapi.json` (solo web, sin API).

## Rutas y UI (web)

- Se mantienen las rutas de reglas existentes (`POST /fleets/{fleet}/rules`, `DELETE .../rules/{rule}`)
  ampliadas con `enforcement`, `valid_from`, `valid_to` y los nuevos tipos (incluido `windows`).
- **Crear/editar flota:** sección “Reglas y límites” con:
  - Tabla/resumen de reglas configuradas (tipo, valor legible, bloqueo/aviso, vigencia, activa) con botones
    Editar/Eliminar.
  - Formulario “Agregar o reemplazar regla” que cambia sus campos según el tipo:
    - Productos → multiselect de **productos activos**.
    - Estaciones → multiselect de **estaciones activas**.
    - Numéricos → input decimal con su unidad (L, L/día, USD, USD/día, transacciones/día).
    - Horarios → editor de franjas: días (checkboxes L–D) + hora inicio/fin, permitiendo varias.
  - Selector “Aplicación”: **Bloquear** o **Solo avisar**.
  - Vigencia opcional (desde/hasta).
- **Crear flota con reglas:** el formulario de alta permite añadir reglas antes de guardar; al enviar, se crea
  la flota y sus reglas en una transacción; si una regla es inválida no se crea la flota.
- En el **detalle** se muestra el mismo resumen legible.

## Validación (Form Request `StoreFleetRuleRequest`, español)

- `rule_type`: obligatorio, enum.
- `enforcement`: `block|warn` (default `block`).
- `valid_from`, `valid_to`: fecha nullable; `valid_to >= valid_from`.
- `active`: booleano.
- Listas: `ids` requerido para reglas de lista; `ids.*` existe y **activo**, del tipo correcto
  (**productos** para `allowed_products`, **estaciones** para `allowed_stations`).
- Numéricos: `value` requerido, `DecimalString` con escala adecuada (L: 2; USD: 2; transacciones: 0 enteros).
- Horarios: `windows` requerido (si el tipo es horarios), array 1..N; `windows.*.days` array de enteros 1..7 no vacío;
  `windows.*.start`/`end` formato `HH:MM`; `end > start` (misma jornada).
- Al crear flota con reglas, se validan todas antes de persistir (transacción).

## Aplicación en el POS (algoritmo)

En `FuelTransactionService::record()` (dentro del bloqueo de la tarjeta), nuevo orden:
1. `ensureCardAccepts` (estado, empresa, asignaciones, producto, fuel type).
2. **Cotizar** (`PriceResolver::quote`) — necesario para reglas de monto.
3. `FleetRuleService::ensureAccepts(card, station, product, purchase, quote)`:
   - Carga reglas activas del `fleet_id`, filtra por **vigencia** (día del evento).
   - Evalúa cada una; si incumple:
     - `block` → lanza `FleetRuleViolation` (422 en API, error en web) con mensaje claro.
     - `warn` → acumula un aviso; **no** lanza.
   - Devuelve la lista de avisos.
4. Bloqueo y comprobación de **cuota** de tarjeta (sin cambios).
5. Insertar compra + incrementar contador (sin cambios).
6. Si hay avisos (`warn`), dispara el evento existente `FleetRuleViolated` con `enforcement=warn`
   (mismo transacción) para que el listener de notificaciones lo entregue a los gerentes.

Notas:
- Los límites “por día” leen `fuel_transactions` de la tarjeta para el día de Beirut (incluye la compra actual;
  el conteo de transacciones usa el número previo).
- El orden de pasos cambia respecto de hoy (quote antes de reglas); se actualizan pruebas existentes si aplica.

## Casos borde

- Regla de aviso incumplida: la compra se registra y se notifica (sin duplicar spam: dedupe 1 h del listener).
- Regla bloqueante incumplida: nada se escribe (rollback), 422/error con campo y mensaje.
- Regla fuera de vigencia: no aplica.
- Franja que cruza medianoche: **no se admite** (`end > start`); se pueden crear dos franjas.
- Estación/producto inactivo elegido al editar: se valida como no existente (error).
- Cambiar el tipo de una regla: reemplaza la anterior del mismo tipo (la clave única).
- Límites a 0: `max_...=0` bloquea todo de ese tipo (válido).

## Pruebas (agrupadas por valor, mínimo ~14)

1. **Regresión**: los cuatro tipos actuales siguen funcionando (productos, estaciones, litros/transacción, transacciones/día).
2. **Límite de litros por día**: superar el total del día → bloquea; dentro → pasa.
3. **Monto por transacción (USD)**: supera → bloquea; dentro → pasa.
4. **Monto por día (USD)**: acumulado del día + compra → bloquea/pasa.
5. **Horarios**: compra en franja/día permitido → pasa; fuera → bloquea.
6. **Vigencia**: regla fuera de fechas no aplica; dentro aplica.
7. **Aviso vs bloqueo**: regla `warn` permite la compra y genera notificación `fleet_rule_violated`;
   regla `block` la rechaza y no escribe nada.
8. **Orden quote→reglas**: una regla de monto usa el precio/tasa del evento correctamente.
9. **UX crear flota con reglas**: al crear con reglas válidas se guardan; con una inválida no se crea la flota.
10. **Editor correcto**: productos solo lista productos; estaciones solo estaciones (sin mezcla).
11. **Validación horarios**: días vacíos o `end <= start` → error.
12. **Auditoría**: guardar/editar/eliminar regla emite `fleet.rules_changed` con enforcement y vigencia.
13. **Authorization**: un gerente no puede editar reglas de otra empresa (404); operador 403.
14. **Notificación de aviso**: no rompe la compra y respeta la deduplicación de 1 h.

## Fuera de alcance (esta fase)

- Límites/cuotas por producto (D11) y estaciones habilitadas por empresa (área 3).
- Reglas por tarjeta (el motor sigue en la flota).
- Cambios de API/`openapi.json` y del POS (preautorización, offline).

## Criterios de aceptación

- El gerente administra todas las reglas de su flota (incluidas **estaciones permitidas**) desde crear/editar/detalle,
  con campos claros y sin el multiselect mezclado.
- Existen los nuevos límites (litros/día, USD/transacción, USD/día, horarios) además de los actuales.
- Cada regla es **bloqueante** o **solo aviso**, y el POS lo aplica: rechaza o permite+notifica.
- La vigencia y la auditoría funcionan.
- Suite completa, Pint y Larastan en verde.
