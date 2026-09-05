# Fase 3 — Frota

## Escopo entregue

- CRUD de **Veículos** (placa, chassi, renavam, marca, modelo, cor, ano, cliente)
- CRUD de **Equipamentos** (IMEI, modelo, ICCID, operadora)
- **Associação** veículo ↔ equipamento (instalação, remoção, troca)
- **Histórico** de associação em `equipment_assignment_events`

## Isolamento

| Entidade | Tenant | Cliente |
|----------|--------|---------|
| Vehicle | `BelongsToTenant` | `BelongsToClient` |
| Equipment | `BelongsToTenant` | Via veículo quando instalado |
| Assignment events | `BelongsToTenant` | Via veículo |

## Endpoints

| Método | Rota | Permissão |
|--------|------|-----------|
| GET/POST | `/api/vehicles` | `vehicle.read` / `vehicle.create` |
| GET/PUT/DELETE | `/api/vehicles/{vehicle}` | `vehicle.read/update/delete` |
| GET | `/api/vehicles/{vehicle}/equipment-history` | `vehicle.read` |
| POST | `/api/vehicles/{vehicle}/equipment/install` | `vehicle.update` |
| POST | `/api/vehicles/{vehicle}/equipment/remove` | `vehicle.update` |
| POST | `/api/vehicles/{vehicle}/equipment/swap` | `vehicle.update` |
| GET/POST | `/api/equipments` | `equipment.read` / `equipment.create` |
| GET/PUT/DELETE | `/api/equipments/{equipment}` | `equipment.read/update/delete` |
| GET | `/api/equipments/{equipment}/assignment-history` | `equipment.read` |

## Migrations

- `2026_09_04_030001_create_vehicles_table`
- `2026_09_04_030002_create_equipments_table`
- `2026_09_04_030003_create_equipment_assignment_events_table`
- `2026_09_04_030004_sync_phase3_permissions`

## Testes

- `tests/Feature/Vehicle/VehicleCrudTest.php`
- `tests/Feature/Equipment/EquipmentCrudTest.php`

## Frontend

- Menu **Cadastros**: Veículos e Equipamentos
- Edição de veículo inclui instalação/troca/remoção e histórico
