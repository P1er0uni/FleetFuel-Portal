# Spec — Fase 3: Mapa + Favoritos + Preferencias (SDD)

Fuente: `fase-3-mapa-favoritos-preferencias.md` (prompt del usuario). Esta spec aterriza el
prompt a la realidad del código y registra las decisiones. Idioma: UI en español, API JSON
en inglés (middleware `SetApiLocale`), igual que el resto del portal.

## Decisiones registradas

| # | Tema | Decisión | Origen |
|---|---|---|---|
| D1 | Rutas | Sin prefijo `/admin` (convención de fase 1): web `/map`, `/preferences`, `/favorites*`; API `/api/v1/map/stations`, `/api/v1/favorites*`, `/api/v1/preferences*` | Convención |
| D2 | Roles | Los reales son `admin`, `company_manager`, `station_operator` (no `distributor_admin`). Mapa: admin + company_manager. Favoritos y preferencias: los tres roles (son por usuario) | Adaptación |
| D3 | `stations` real | Columnas: `name`, `district`, `governorate`, `latitude`, `longitude`, `is_active`. **No existen** `address`, `status`, `company_id`. El DTO del mapa compone `address = "district, governorate"` y `status = is_active ? 'active' : 'inactive'` | Adaptación |
| D4 | Visibilidad de estaciones | Se reutiliza `Station::visibleTo`: el admin ve todas; los demás solo activas. Las estaciones son **globales** (no tienen `company_id`), así que el manager ve todas las activas, no un subconjunto por compañía | Adaptación |
| D5 | Productos por estación | No hay tabla estación–producto. `products` del DTO = códigos de producto **distintos vendidos** en esa estación (`fuel_transactions` → `products`) | Adaptación |
| D6 | "Hoy" del mapa | Calendario de Beirut (`fleetfuel.business_timezone`); `liters_today` = suma de `liters` guardados; `last_transaction_at` = `max(transacted_at)` en ISO `Z` | Convención |
| D7 | Tipos de favorito | `fleet`, `vehicle`, `station`. `report` **diferido**: no hay tabla de reportes guardados que referenciar. Enlaces: fleet → `fleets.show`; vehicle → `vehicles.edit` (no hay ruta show); station → `map?focus={id}` (no hay detalle de estación). `label` por defecto: "Flota: X", "Vehículo: PLACA", "Estación: NOMBRE" | Adaptación |
| D8 | Preferencias | Claves de la spec. `dashboard_widgets` por defecto = **todos los widgets actuales** (para no alterar el dashboard existente); si se guarda, se respeta la lista/orden y los widgets desconocidos se saltan. `items_per_page` se aplica a las listas de **estaciones** y **notificaciones** (fallback 25), no globalmente, para no cambiar pantallas existentes. `date_format` vía `Display::date()` (helper estático; sin función global), aplicado en las pantallas nuevas. `default_fleet_id` debe ser de la compañía del manager (admin: cualquier flota); preselecciona el filtro de flota en reportes | Adaptación |
| D9 | Abilities API | Nuevas `map:read`, `favorites:read`/`favorites:write`, `preferences:read`/`preferences:write`. Mapa solo admin+manager; favoritos y preferencias también para el operador | Convención |
| D10 | Leaflet | Leaflet 1.9 + markercluster + tiles OSM por **CDN** en la vista (no npm), con JS inline; no requiere build de Vite | Adaptación |
| D11 | Tests | Mínimo 25 nuevos, agrupados por valor (no uno por caso) | Usuario |

## PARTE A: Mapa de estaciones

- `GET /api/v1/map/stations` (admin, company_manager; ability `map:read`): devuelve `{stations: [...]}` con
  `id, name, address, latitude, longitude, status, products[], transactions_today, liters_today, last_transaction_at`.
  Solo estaciones con `latitude` y `longitude` no nulos. Filtros: `status` (all/active/inactive), `product` (code), `q` (nombre).
- `GET /map` (admin, company_manager): página full-width con Leaflet, panel de filtros a la izquierda
  (estado, producto, búsqueda, centrar en mi ubicación), popups con "Ver estaciones"; sección
  "Estaciones sin ubicación" con enlace a editar (admin). Enlace "Mapa" en el menú y botón en el dashboard.
- Colores: verde = activa con transacciones hoy, azul = activa sin transacciones hoy, gris = inactiva. Clustering por zoom.

## PARTE B: Favoritos

- Tabla `user_favorites`: `user_id` FK cascade, `favoritable_type varchar(50)` (alias del morph map:
  `fleet`/`vehicle`/`station`), `favoritable_id bigint`, `label varchar(100) nullable`, `position int default 0`;
  unique `(user_id, favoritable_type, favoritable_id)`, índice `(user_id, position)`.
- API: `GET/POST /api/v1/favorites`, `DELETE /api/v1/favorites/{id}`, `PUT /api/v1/favorites/reorder` (`{ids:[...]}`).
  Validación: la entidad debe existir y estar dentro del scope del usuario (otra compañía → 404); duplicado → 422.
- Web: estrella (bi-star / bi-star-fill) en `fleets.show` y `vehicles.edit` como toggle AJAX (rutas de sesión
  `/favorites*`); panel "Favoritos" en el dashboard con enlaces y reordenación (SortableJS por CDN → `PUT /favorites/reorder`).

## PARTE C: Preferencias

- Tabla `user_preferences`: `user_id` FK cascade, `key varchar(100)`, `value varchar(500) nullable`; unique `(user_id, key)`.
- Claves: `default_fleet_id` (int|null), `dashboard_widgets` (JSON), `map_default_zoom` (int, 12),
  `map_default_lat` (-33.4489), `map_default_lng` (-70.6693), `date_format` (`d/m/Y`), `language` (`es`), `items_per_page` (15).
- `UserPreferenceService`: `get/set/all/forget` con caché en memoria por instancia (una consulta por request).
- Página `/preferences` (GET/PUT) para los tres roles, secciones General / Dashboard / Mapa; enlace en la cuenta.
- API: `GET/PUT /api/v1/preferences`, `GET/PUT /api/v1/preferences/{key}`.
- Integración: dashboard renderiza solo los `dashboard_widgets` en orden (widget desconocido se salta); reportes
  preseleccionan `default_fleet_id`; listas de estaciones/notificaciones usan `items_per_page`; el mapa usa `map_default_*`.

## Widgets del dashboard (claves)

`summary` (tarjetas del mes), `quota_alerts`, `open_deliveries`, `fleet_stats` (conteos + tasa), `fleet_consumption`,
`recent_transactions`. Sin preferencia guardada, se muestran todos en el orden actual.
