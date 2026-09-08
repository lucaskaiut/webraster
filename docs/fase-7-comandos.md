# Fase 7 — Comandos Traccar

## Resumo

Módulo de comandos **100% dinâmico**: a única fonte da verdade é `GET /api/commands/types?deviceId={id}` do Traccar. Não há mapeamento por protocolo, fabricante ou modelo.

## Fluxo

```text
Mapa → veículo selecionado → aba Comandos
  → GET /api/devices/{equipmentUuid}/commands
  → cache Redis 5 min → tipos do Traccar
  → botões dinâmicos (+ labels opcionais)
  → POST /api/devices/{equipmentUuid}/commands
  → revalida tipos → POST /api/commands/send
  → device_command_logs (auditoria)
```

## Arquitetura

```text
app/Integrations/Traccar/
  TraccarClient.php
  TraccarCommandService.php
  DTOs/

app/Modules/DeviceCommand/
  Controllers / Services / Models / Policies
```

## API

| Método | Rota | Permissão |
|--------|------|-----------|
| GET | `/api/devices/{device}/commands` | `device.commands.send` |
| POST | `/api/devices/{device}/commands` | `device.commands.send` |

`{device}` = UUID do **equipamento** local.

Payload de envio:

```json
{ "type": "engineStop", "attributes": {} }
```

```json
{ "type": "custom", "attributes": { "data": "RELAY,1#" } }
```

Comando não suportado → **422** com `Command not supported by device.`

## Cache

Chave: `traccar:command-types:{traccarDeviceId}`  
TTL: **5 minutos**  
Envio sempre reconsulta tipos (`fresh: true`) antes de validar.

## UI

Painel do veículo no monitoramento:

- Informações
- Eventos
- Comandos (se tiver permissão)

Labels são só tradução visual (`commandLabels`). Tipo desconhecido → exibe o `type` cru.

Críticos (`engineStop`, `engineResume`, `alarmArm`, `alarmDisarm`, …) pedem confirmação.

## Testes

```bash
docker compose exec api php artisan test --filter=DeviceCommandTest
```
