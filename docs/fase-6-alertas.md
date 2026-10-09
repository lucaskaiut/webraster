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
| `notify_in_app` | Notificação no app para os usuários do **cliente** |
| `notify_monitoring` | Notificação no app para os **operadores do tenant** |
| `notify_push` | Push (Expo), para operador e cliente |
| `notify_email` | Envio por e-mail, para operador e cliente |
| `settings` | Parâmetros do tipo (limite de velocidade, minutos offline, bateria) |

- Cada alarme do dispositivo tem linha própria por veículo (`alarm_code`), com canais independentes. Códigos novos enviados pelo protocolo são catalogados automaticamente (desabilitados) e passam a aparecer no formulário.
- Entrada/saída de geocerca também é alertada pelo sistema (`geofence`), a partir dos `GeofenceEvent` detectados por posição — independe do rastreador enviar o alarme. O padrão do veículo novo liga esse alerta.
- Quando o protocolo envia `powerCut,tampering` na mesma mensagem, a violação é suprimida e somente "Alimentação cortada" é alertada. Se `tampering` vier sozinho, ele alerta normalmente.
- O padrão de um veículo novo habilita **SOS, jamming e offline** e os alarmes de dispositivo críticos (**alimentação cortada, violação, remoção e acidente**); os demais começam desligados.
- **In-app** notifica apenas os usuários do cliente e **Monitoramento** apenas os operadores. Push e e-mail notificam ambos, respeitando o silenciamento do cliente.
- Configurações por cliente não participam do motor: são apenas um silenciador. Se o cliente desligar um alerta no portal, ele deixa de receber notificações — o operador continua sendo notificado e o alerta continua no histórico.
- A configuração é enviada junto do cadastro do veículo (`POST/PUT /vehicles`, campo `alert_configs`) e exige a permissão `alert-config.update`.

## Fluxo

```text
GPS → persistPosition → GeofenceDetection → AlertEngine
  → matchingForVehicle(type) / deviceAlarmConfig(code)
  → Speed / Ignition / SOS / Battery / Jamming / DeviceAlarm / Geofence
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
| geofence | medium | um alerta por transição de cerca (entry/exit) |

Velocidade: Traccar em **nós**; limite configurado em **km/h**.

## API

- `GET /alerts`, `/alerts/map`, `/alerts/dashboard`, `POST .../acknowledge|resolve`
- `POST/PUT /vehicles` com `alert_configs` (operador define alarmes e canais por veículo)
- `GET/POST /alert-configs`, `PUT/DELETE /alert-configs/{id}` (API administrativa)
- `GET/PUT /alert-configs/portal[/{type}]` (cliente silencia/volta a receber)
- `GET/PUT /alert-sound-preferences[/{type}]` (usuário escolhe o som de cada alerta no app)
- `GET /notifications`, `/notifications/unread-count`, `POST .../read`

## Som das notificações

- Cada usuário escolhe, no app (**Mais → Sons das notificações**), o som de **três grupos**:
  **Ignição ligada/desligada**, **Entrada e saída de cerca** e **Alimentação cortada**. A
  preferência é por usuário e por grupo (`alert_sound_preferences.type` guarda a chave do grupo:
  `ignition`, `geofence` ou `powercut`).
- O grupo `powercut` casa com o alarme de dispositivo cujo `alarm_code` é `powercut`; os demais
  tipos sem grupo (sos, offline, bateria, ...) mantêm a preferência antiga por tipo, quando existir.
- O catálogo de sons vive em `config/notification.php → push.sounds` e é espelhado no app
  (`src/constants/notification-sounds.ts`): a API envia `sound` (iOS, arquivo embarcado) e
  `channelId` (Android, canal criado pelo app) no push.
- No Android o som pertence ao canal e não pode ser alterado depois de criado, por isso há
  **um canal por som** (`alert_sound_*`); o app cria todos no registro do dispositivo.
- Os três grupos aparecem sempre na lista; silenciar um alerta no portal continua valendo para o envio, sem apagar a escolha de som.
- Novos arquivos de som entram no app via plugin `expo-notifications` (`sounds`) e exigem
  novo build; rode `npm run sounds` para regenerar os WAVs de exemplo.

## Testes

```bash
docker compose exec api php artisan test --filter='AlertSupportTest|AlertEngineTest'
```
