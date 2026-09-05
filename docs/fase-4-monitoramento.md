# Fase 4 — Monitoramento

## Escopo entregue

- Estrutura de **eventos GPS** (`gps_positions`: lat, lng, data GPS, velocidade, ignição, bateria)
- **Gateway Traccar** (`TraccarGateway`) para consultar dispositivos e posições
- Mapa **Google Maps** com tempo real, lista lateral e busca
- **Histórico** de percurso + playback
- Escopo por **tenant** e **cliente** (portal só vê a própria frota)

## Integração Traccar

| Config | Descrição |
|--------|-----------|
| `TRACCAR_ENABLED` | Liga o gateway HTTP |
| `TRACCAR_BASE_URL` | Ex.: `http://traccar:8082` |
| `TRACCAR_TOKEN` | Bearer token (preferencial) |
| `TRACCAR_EMAIL` / `TRACCAR_PASSWORD` | Alternativa Basic Auth |

O IMEI do equipamento é o `uniqueId` no Traccar. Ao **criar ou alterar** um equipamento, o sistema:

1. Busca o dispositivo pelo IMEI
2. Se não existir, cria via `POST /api/devices`
3. Grava `equipments.traccar_device_id`

Se o IMEI já existir no Traccar, apenas vincula — não duplica. Com Traccar desligado, o cadastro local segue normalmente (`NullTraccarGateway`).

## Endpoints

| Método | Rota | Permissão |
|--------|------|-----------|
| GET | `/api/tracking/status` | `tracking.read` |
| GET | `/api/tracking/live?search=` | `tracking.read` |
| GET | `/api/tracking/vehicles/{vehicle}/history?from=&to=` | `tracking.read` |

## Migrations

- `2026_09_04_040001_add_traccar_device_id_to_equipments_table`
- `2026_09_04_040002_create_gps_positions_table`
- `2026_09_04_040003_sync_phase4_permissions`

## Frontend

## Frontend

- Rota `/monitoring` (layout full-bleed: mapa dominante)
- Sidebar de frota: busca instantânea, filtros (online/offline/movimento/parado/sem sinal) e contadores
- Painel contextual do veículo selecionado
- Histórico e playback em modal (não ocupa a área principal)
- Eventos derivados do percurso (ignição, movimento, perda de comunicação)
- `VITE_GOOGLE_MAPS_API_KEY` obrigatória para renderizar o mapa
- Polling de live a cada 15s (sem WebSocket)

## Limitações conhecidas

- Sem geocodificação: localização é lat/lng, não endereço
- Sem combustível/temperatura/geocercas: a API atual e o payload Traccar persistido não trazem esses dados de forma confiável
- Eventos não vêm de um endpoint Traccar de events; são derivados das posições do histórico
- Cluster de marcadores não aplicado (frotas pequenas); previsto para dezenas/centenas de veículos

## Testes

- `tests/Feature/Tracking/TrackingLiveTest.php`
- `tests/Feature/Equipment/EquipmentTraccarSyncTest.php`
