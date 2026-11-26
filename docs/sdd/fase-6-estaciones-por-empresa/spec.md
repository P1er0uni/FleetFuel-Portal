# Spec — Fase 6 / Área 3: Estaciones habilitadas por empresa

Fuente: conversación con el usuario (análisis de huecos frente a ATIONET, área 3). Objetivo:
que el administrador (“red”) pueda definir **en qué estaciones puede repostar cada empresa**, y que el
POS rechace compras fuera de esa lista. En la terminología ATIONET: **Network habilita estaciones por
empresa**; **Company** las usa.

## Contexto actual (estado real del código)

- `stations` es **referencia global**: no tiene `company_id`; cualquier empresa puede usar cualquier
  estación activa. No existe ninguna tabla de acuerdos empresa–estación.
- La ingesta POS (`FuelTransactionService::record`) valida tarjeta/empresa/asignaciones/producto,
  aplica **reglas de flota** (fase 5) y la cuota. No hay comprobación de “estación habilitada por empresa”.
- El administrador ya gestiona **estaciones** (alta/edición/estado) y **empresas**; el gerente ve su empresa.

## Decisiones registradas

| # | Tema | Decisión |
|---|---|---|
| D1 | Modelo | Nueva tabla `company_stations` (`company_id` FK cascade, `station_id` FK cascade, unique `(company_id, station_id)`). |
| D2 | Interruptor por empresa | Nueva columna `companies.restrict_stations boolean default false`. Si es **false** (por defecto), todas las estaciones activas están permitidas (compatible con lo actual). Si es **true**, solo las de `company_stations`; si la lista está vacía, **no** puede usar ninguna. |
| D3 | Punto de aplicación | En la ingesta POS, después de validar tarjeta/empresa y antes de reglas de flota y cotización: si `restrict_stations` y la estación del evento no está habilitada → **403 `station_not_allowed`** (compra rechazada, nada se escribe). |
| D4 | Alcance | La restricción es **por empresa y aplica a todas sus tarjetas** (no por flota ni por tarjeta). Las **reglas de flota** de “estaciones permitidas” se siguen aplicando **además** (intersección). |
| D5 | Gestión | Solo **administrador**: en la edición de la empresa, sección “Estaciones habilitadas” con el interruptor de restricción y checkboxes de estaciones activas. El gerente **no** la ve ni la edita (403 en la ruta admin). |
| D6 | API | Sin endpoints nuevos. El rechazo del POS usa el código `station_not_allowed` (403); se documenta en `docs/05-API-CONTRACT.md`. `openapi.json` no cambia (el envelope de error es genérico). |
| D7 | Auditoría | Acción `company.stations_changed` con `restrict_stations` y la lista de estaciones habilitadas. |
| D8 | Fuera de alcance | **Precios/restricciones de producto por empresa** y filtrado de la lista de estaciones del gerente por empresa. Se dejan para fases posteriores. |

## Historias de usuario

Ver HU-01…HU-08 arriba (resumen): habilitar estaciones por empresa; comportamiento por defecto (todas);
bloqueo en POS; configurar/ver resumen; solo admin; auditoría; combinación con reglas de flota; aplica a
toda la empresa.

## Modelo de datos

- `companies` añade `restrict_stations boolean not null default false`.
- Nueva `company_stations`:
  - `id`, `company_id` FK → companies (cascade), `station_id` FK → stations (cascade), timestamps.
  - Unique `(company_id, station_id)`; índice `company_id`.
- Sin cambios en `fuel_transactions`, `notifications` ni `openapi.json`.

## Regla de negocio

`stationAllowed(Company $company, Station $station): bool`
- Si `! $company->restrict_stations` → **true** (cualquier estación activa; las inactivas ya se rechazan por otras reglas).
- Si `$company->restrict_stations` → **true** solo si existe la fila `(company_id, station_id)`.

En POS (`FuelTransactionService::record`), tras `ensureCardAccepts` y antes de reglas de flota:
- si no permitida → `throw PurchaseDeclined::stationNotAllowed();` → **403 `station_not_allowed`**.
- La comprobación de replay (idempotencia) sigue antes, así que un reintento de una compra ya aceptada
  responde 200 aunque la estación se haya deshabilitado después (igual que otras reglas mutables).

## Servicio `CompanyStationService`

- `allowed(Company, Station): bool` (regla anterior).
- `sync(Company $company, bool $restrict, array $stationIds, User $actor)`:
  - En una transacción: actualiza `restrict_stations`, reemplaza las filas de `company_stations`
    (inserta las indicadas, borra las que sobran) y audita `company.stations_changed`
    (`restrict_stations` antes/después y la lista resultante).
  - Valida que cada `station_id` exista y esté **activo** (o permitir inactivas? se exige activo).
  - Empresa inactiva → 403 `company_inactive` (no se puede cambiar).

## Rutas y UI (web, solo admin)

- `GET /companies/{company}/stations` (o sección en `companies.edit`) para configurar:
  - Interruptor **“Restringir estaciones”**.
  - Lista de **estaciones activas** con checkboxes (las habilitadas marcadas).
  - Botón Guardar.
- `PUT /companies/{company}/stations` → llama a `CompanyStationService::sync`.
  - Ruta bajo `role:admin`; el gerente/operador reciben 403; {company} ya es tenant-scoped para el admin (todas).
- En la **edición de la empresa** se muestra un resumen legible: “Sin restricción (todas)” o la lista de
  estaciones habilitadas.
- (Opcional) Mostrar el resumen en la lista de empresas (columna “Estaciones”).

## Validación (Form Request `UpdateCompanyStationsRequest`)

- `restrict_stations`: booleano.
- `station_ids`: array nullable; `station_ids.*` entero `exists:stations,id` y `is_active=true`.
- Si `restrict_stations` es false, se ignoran las estaciones (se guardan igualmente para cuando se active,
  o se limpian; decisión: se **conservan** para no perder la selección al desactivar el interruptor).
- Mensajes en español.

## Casos borde

- Empresa sin restricción → todas permitidas (no rompe datos actuales ni pruebas).
- Restricción activada con lista vacía → **ninguna** estación permitida (bloquea todo).
- Estación deshabilitada con compras históricas → el historial se conserva; solo afecta compras nuevas.
- Replay idempotente de una compra antigua → 200 sin reevaluar la habilitación.
- Cambiar la lista no afecta a compras ya registradas ni a reportes históricos.
- Empresa inactiva → no se puede cambiar la configuración (403).

## Pruebas (agrupadas por valor, mínimo ~10)

1. **Por defecto:** empresa sin restricción acepta compra en cualquier estación activa (no rompe lo actual).
2. **Restricción con lista:** compra en estación habilitada → acepta; en estación no habilitada → 403 `station_not_allowed` y no escribe.
3. **Restricción vacía:** bloquea todas las estaciones.
4. **Aplica a toda la empresa:** dos tarjetas distintas de la misma empresa se ven afectadas igual.
5. **Combinación con flota:** aunque la empresa habilite la estación, una regla de flota que la prohíba sigue rechazando (y viceversa).
6. **Replay:** una compra aceptada antes de restringir, reintentada, devuelve 200 (idempotencia) sin reevaluar.
7. **Admin sincroniza:** guardar interruptor + lista persiste; se audita `company.stations_changed`.
8. **Autorización:** gerente/operador 403 en la ruta; invitado al login.
9. **Validación:** `station_ids.*` inexistente/inactivo → error.
10. **Historial:** compras previas y reportes no cambian al restringir.

## Criterios de aceptación

- El administrador puede activar la restricción y elegir las estaciones por empresa.
- Por defecto todas permitidas; con restricción, solo las habilitadas; lista vacía bloquea todo.
- El POS rechaza con 403 `station_not_allowed` fuera de la lista, sin escribir nada; el replay sigue idempotente.
- Las reglas de flota se siguen aplicando en conjunto.
- Auditoría de cambios; sin cambios de `openapi.json`; suite, Pint y Larastan en verde.
