# Plan — Fase 3: Mapa + Favoritos + Preferencias (SDD)

Orden de implementación; cada tarea deja el árbol en verde (`php artisan test`).
Fuente: `spec.md`. Tests agrupados por valor (principio de fases 1–2), mínimo 25 nuevos.

## Estado actual (2026-11-25)

- **PARTE A (mapa), PARTE B (favoritos), PARTE C (preferencias): DONE.**
- Pint y Larastan limpios; **suite completa: 709 tests / 7098 assertions**.
- 32 tests nuevos: mapa 8, favoritos 10, preferencias 14.
- Rama `fase-3-mapa-favoritos-preferencias`, sin commit ni push.

## PARTE A — Mapa de estaciones ✅

### A1 — API y servicio ✅
- [x] `Services\StationMapService` (filas del mapa, compartido web/API)
- [x] `Api\V1\MapController@stations` + `Requests\Api\V1\MapStationsRequest` (filtros `status`/`product`/`q`)
- [x] Ruta `GET /api/v1/map/stations` con ability `map:read` (admin + company_manager)
- [x] `openapi.json`: path `/map/stations` + schema `MapStation`

### A2 — Web ✅
- [x] `Web\MapController@index` + `@data`; rutas `/map` y `/map/stations` (admin, company_manager)
- [x] `views/map/index.blade.php`: Leaflet + markercluster (CDN), panel de filtros, popups, geolocalización, sección "sin ubicación"
- [x] Enlace "Mapa" en el menú (General) y botón en el dashboard

### A3 — Tests ✅
- [x] `MapApiTest` (5): solo con coordenadas; métricas/productos del día; manager vs admin (inactiva); filtros; operador 403 y parámetros desconocidos 422
- [x] `MapScreenTest` (3): `/map` 200 con `station-map`; mensaje sin coordenadas; endpoint de sesión

## PARTE B — Favoritos ✅

### B1 — Datos ✅
- [x] Migración `user_favorites` (unique + índice); modelo `UserFavorite` (morphTo) + factory
- [x] `FavoriteService`: alta con scope, baja, toggle, reorder, listado resuelto (label + url)

### B2 — API ✅
- [x] `Api\V1\FavoriteController` (index/store/destroy/reorder), requests, contrato `openapi.json` (`Favorite`)
- [x] Rutas con abilities `favorites:read`/`favorites:write` (todos los roles)

### B3 — Web ✅
- [x] Estrella toggle AJAX en `fleets.show` y `vehicles.edit` (`partials/favorite-star` + rutas de sesión `/favorites*`)
- [x] Panel "Favoritos" en el dashboard con enlaces y reordenación (SortableJS)

### B4 — Tests ✅
- [x] `FavoriteApiTest` (6): alta + label, duplicado 422, ajena 404, baja, reorder, abilities
- [x] `FavoriteScreensTest` (4): toggle web, panel del dashboard, estrella, borrado

## PARTE C — Preferencias ✅

### C1 — Datos ✅
- [x] Migración `user_preferences` (unique `(user_id,key)`); modelo `UserPreference` + factory

### C2 — Servicio ✅
- [x] `UserPreferenceService`: `get/set/all` con caché por instancia; getters tipados (`itemsPerPage`, `dateFormat`, `dashboardWidgets`, `defaultFleetId`, `mapCenter`, `typed`, `toArray`); allowlist de claves

### C3 — API y Web ✅
- [x] `Api\V1\PreferenceController` (index/update/showKey/updateKey) + requests + abilities `preferences:read`/`preferences:write`
- [x] `Web\PreferenceController` (edit/update) + `PreferenceRequest` + `views/preferences/edit.blade.php` + enlace en la cuenta

### C4 — Integraciones ✅
- [x] Dashboard: renderiza solo `dashboard_widgets` en orden (widget desconocido se salta; sin preferencia = todos)
- [x] Reportes: preselección de `default_fleet_id` en el filtro de flota
- [x] `items_per_page` en las listas de estaciones y notificaciones
- [x] `Display::date()` para `date_format`; el mapa usa `map_default_*`

### C5 — Tests ✅
- [x] `PreferenceApiTest` (5), `PreferenceScreensTest` (4), `DashboardPreferenceTest` (2), `UserPreferenceServiceTest` (3, incluido el test de una sola query)

## Cierre ✅
- [x] Pint + Larastan level 6 (app y simulador POS) sin errores
- [x] Suite completa en verde: **709 tests / 7098 assertions** (195.14 s)
- [x] Docs: `05-API-CONTRACT.md`, `03-DATA-MODEL.md` (diagrama + tablas), `CHANGELOG.md`, `PROGRESS.md` y esta carpeta

## Notas de adaptación (ver `spec.md`)
- Rutas sin `/admin`; roles reales; `stations` sin `address/status/company_id` (compuesto/derivado).
- `report` como favorito diferido (no hay tabla de reportes guardados).
- Enlace de favorito de estación → `/stations` (no hay detalle; el mapa es solo admin/manager).
- `items_per_page` aplicado a las listas de estaciones y notificaciones (no globalmente, para no alterar pantallas existentes).
- `date_format` vía `Display::date()` (helper estático, sin función global).
- Bug latente corregido en `ReportController` (el `when()` pasaba un booleano en vez del id de compañía).
