# Fase 5 — Geocercas e POIs

## Resumo

Módulo de geocercas (círculo/polígono), POIs com categorias e detecção real de entrada/saída integrada ao fluxo de persistência GPS (`TrackingService::persistPosition`).

## Banco

| Migration | Tabelas |
|-----------|---------|
| `2026_09_05_050001_create_geofences_table` | `geofences` |
| `2026_09_05_050002_create_poi_categories_table` | `poi_categories` |
| `2026_09_05_050003_create_pois_table` | `pois` |
| `2026_09_05_050004_create_geofence_events_table` | `geofence_events` |
| `2026_09_05_050005_create_vehicle_geofence_states_table` | `vehicle_geofence_states` |
| `2026_09_05_050006_sync_phase5_permissions` | sync ACL |

### Campos principais

- **geofences**: tenant/client, name, description, type (`circle`|`polygon`), is_active, center/radius, geometry JSON, bbox, soft deletes
- **pois**: tenant/client, category, name, lat/lng, address, is_active, soft deletes
- **geofence_events**: vehicle, geofence, gps_position, type (`entry`|`exit`), coords, recorded_at, processed_at — unique `(gps_position_id, geofence_id, type)`
- **vehicle_geofence_states**: estado `is_inside` + último `recorded_at` / `gps_position_id`

## Processamento GPS

```text
Traccar / persistPosition
  → GpsPosition updateOrCreate
  → GeofenceDetectionService::process
  → geocercas ativas do mesmo tenant+client (pré-filtro bbox)
  → estado anterior (vehicle_geofence_states)
  → se recorded_at < last_recorded_at → ignora (fora de ordem)
  → se mesma position já processada → ignora
  → contains() Haversine / point-in-polygon
  → fora→dentro = entry | dentro→fora = exit
  → persiste evento + atualiza estado
```

### Limitações documentadas

- Posições atrasadas com timestamp antigo **após** o estado já ter avançado não reescrevem o histórico (evita eventos espúrios).
- Histórico Traccar é ordenado por `recorded_at` antes da persistência.

## API

- `GET/POST /geofences`, `GET /geofences/map`, `GET|PUT|DELETE /geofences/{id}`
- `GET /geofence-events`
- `GET/POST /pois`, `GET /pois/map`, `GET /poi-categories`, `GET|PUT|DELETE /pois/{id}`

Permissões: `geofence.*`, `poi.*`

## Frontend

- `/geofences`, `/geofences/create`, `/geofences/:id/edit`
- `/geofence-events`
- `/pois`, `/pois/create`, `/pois/:id/edit`
- Camadas no `/monitoring` (Veículos / Geocercas / POIs)

## Testes

```bash
docker compose exec api php artisan test --filter='GeoMathTest|GeofenceDetectionTest|PoiCrudTest'
```
