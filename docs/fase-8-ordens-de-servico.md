# Fase 8 — Ordens de Serviço

## Resumo

Módulo operacional completo de OS com lista, Kanban, agenda, histórico, ACL e multi-tenancy.

## Tipos

- `installation` — Instalação
- `maintenance` — Manutenção
- `removal` — Retirada

## Status e transições

```text
open → in_progress → completed
open → cancelled
in_progress → cancelled
```

Transições inválidas são bloqueadas no backend.

## Numeração

Sequencial por tenant: `OS-000001`, `OS-000002`, …

## API

| Método | Rota | Permissão |
|--------|------|-----------|
| GET | `/service-orders` | `service-order.read` |
| GET | `/service-orders/kanban` | `service-order.read` |
| GET | `/service-orders/calendar` | `service-order.read` |
| POST | `/service-orders` | `service-order.create` |
| GET | `/service-orders/{id}` | `service-order.read` |
| PUT | `/service-orders/{id}` | `service-order.update` |
| DELETE | `/service-orders/{id}` | `service-order.delete` |
| PATCH | `/service-orders/{id}/status` | `service-order.change-status` |
| GET | `/service-orders/{id}/history` | `service-order.read` |

## Frontend

- `/service-orders` — lista + filtros
- `/service-orders/kanban` — colunas por status
- `/service-orders/calendar` — agenda por dia/técnico
- `/service-orders/create` e `/:id/edit`
- `/service-orders/:id` — detalhes + ações + histórico

## Testes

```bash
docker compose exec api php artisan test --filter=ServiceOrderTest
```
