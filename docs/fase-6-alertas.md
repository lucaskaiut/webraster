# Fase 6 — Alertas e Notificações

## Resumo

Motor de alertas integrado ao `TrackingService::persistPosition`, com regras configuráveis por **veículo**, **cliente** ou **toda a frota**, notificações in-app + e-mail, job de offline e UI (histórico, cadastro de regras, sino, dashboard, mapa).

## Escopo das regras

Cada `AlertConfig` define:

| Escopo | Campos | Dispara para |
|--------|--------|--------------|
| Todos | `client_id` e `vehicle_id` nulos | Todos os veículos do tenant |
| Cliente | só `client_id` | Veículos daquele cliente |
| Veículo | só `vehicle_id` | Aquele veículo |

Não é permitido preencher cliente e veículo ao mesmo tempo.

## Fluxo

```text
GPS → persistPosition → GeofenceDetection → AlertEngine
  → matchingForVehicle(type) → Speed / Ignition / SOS / Battery / Jamming
  → OfflineAlertService.markOnline
  → Alert + UserNotification (+ e-mail opcional)

Schedule everyMinute → CheckOfflineDevicesJob → DEVICE_OFFLINE
```

## Alertas

| Tipo | Severidade | Deduplicação |
|------|------------|--------------|
| speed | medium | duração mínima + estado por config |
| ignition_on/off | low | transição de estado |
| sos | critical | estado ativo enquanto SOS=true |
| offline/online | high | job + estado por config |
| battery | medium | só reentra após subir do limite (por config) |
| jamming | critical | só com atributo real do protocolo |

Velocidade: Traccar em **nós**; limite configurado em **km/h**.

## API

- `GET /alerts`, `/alerts/map`, `/alerts/dashboard`, `POST .../acknowledge|resolve`
- `GET/POST /alert-configs`, `PUT/DELETE /alert-configs/{id}`
- `GET /notifications`, `/notifications/unread-count`, `POST .../read`

## Testes

```bash
docker compose exec api php artisan test --filter='AlertSupportTest|AlertEngineTest'
```
