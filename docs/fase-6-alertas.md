# Fase 6 — Alertas e Notificações

## Resumo

Motor de alertas integrado ao `TrackingService::persistPosition`, com regras configuradas **por veículo** pelo operador no cadastro do veículo, notificações in-app + push + e-mail, job de offline e UI (histórico, sino, dashboard, mapa).

## Escopo das regras

Cada `AlertConfig` é definida por veículo (`vehicle_id` preenchido, `client_id` nulo):

| Campo | Efeito |
|-------|--------|
| `type` | Tipo do alarme (`speed`, `sos`, `device_alarm`, ...) |
| `alarm_code` | Código do protocolo quando `type = device_alarm` (ex.: `powercut`, `tow`) |
| `is_enabled` | Liga/desliga o alarme naquele veículo |
| `notify_in_app` | Notificação no app |
| `notify_push` | Push (Expo) |
| `notify_email` | Envio por e-mail |
| `settings` | Parâmetros do tipo (limite de velocidade, minutos offline, bateria) |

- Cada alarme do dispositivo tem linha própria por veículo (`alarm_code`), com canais independentes. Códigos novos enviados pelo protocolo são catalogados automaticamente (desabilitados) e passam a aparecer no formulário.
- O padrão de um veículo novo habilita **SOS, jamming e offline** e os alarmes de dispositivo críticos (**alimentação cortada, violação, remoção e acidente**); os demais começam desligados.
- Configurações por cliente não participam do motor: são apenas um silenciador. Se o cliente desligar um alerta no portal, ele deixa de receber notificações — o operador continua sendo notificado e o alerta continua no histórico.
- A configuração é enviada junto do cadastro do veículo (`POST/PUT /vehicles`, campo `alert_configs`) e exige a permissão `alert-config.update`.

## Fluxo

```text
GPS → persistPosition → GeofenceDetection → AlertEngine
  → matchingForVehicle(type) / deviceAlarmConfig(code)
  → Speed / Ignition / SOS / Battery / Jamming / DeviceAlarm
  → OfflineAlertService.markOnline
  → Alert + UserNotification (+ push/e-mail opcionais)

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
- `POST/PUT /vehicles` com `alert_configs` (operador define alarmes e canais por veículo)
- `GET/POST /alert-configs`, `PUT/DELETE /alert-configs/{id}` (API administrativa)
- `GET/PUT /alert-configs/portal[/{type}]` (cliente silencia/volta a receber)
- `GET /notifications`, `/notifications/unread-count`, `POST .../read`

## Testes

```bash
docker compose exec api php artisan test --filter='AlertSupportTest|AlertEngineTest'
```
