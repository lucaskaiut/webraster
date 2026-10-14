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

- Transições inválidas são bloqueadas no backend.
- O formulário de criação permite escolher a situação inicial (`open` ou `in_progress`) e o de
  edição oferece apenas a situação atual e as transições permitidas — a mudança reflete no Kanban.

## Numeração

Sequencial por tenant: `OS-000001`, `OS-000002`, …

## Serviços realizados

- Cada OS pode ter uma lista de serviços do catálogo (`service_order_items`), com quantidade e
  valor unitário ajustável.
- O nome e o valor do serviço são gravados como snapshot no momento do lançamento.
- Serviços são informados no formulário da OS (`items` no payload).

## Cobrança a partir da OS

- Na tela da OS, o botão **Gerar cobrança** cria uma cobrança no financeiro
  (`finance_billings.service_order_id`) com o valor total dos serviços lançados.
- Requer a permissão `finance-billing.create`; o vencimento é informado no modal.
- Não é permitido gerar cobrança de OS cancelada, sem serviços ou com cobrança em aberto
  (cobranças canceladas/estornadas permitem gerar novamente).
- A cobrança gerada fica vinculada à OS e aparece no detalhe com link para o financeiro.

## Fotos e observações

- `service_order_photos` guarda fotos da execução (upload via `/uploads`, mesmo padrão das fotos
  de veículo).
- As observações da execução usam o campo `execution_notes`, editável na tela da OS enquanto ela
  não está concluída/cancelada.
- Fotos não podem ser alteradas em OS cancelada.

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
| POST | `/service-orders/{id}/billing` | `finance-billing.create` |
| POST | `/service-orders/{id}/photos` | `service-order.update` |
| DELETE | `/service-orders/{id}/photos/{photo}` | `service-order.update` |

## Frontend

- `/service-orders` — lista + filtros
- `/service-orders/kanban` — colunas por status
- `/service-orders/calendar` — agenda por dia/técnico
- `/service-orders/create` e `/:id/edit` — incluem situação e serviços realizados
- `/service-orders/:id` — detalhes, serviços/cobrança, fotos, observações e histórico

## Testes

```bash
docker compose exec api php artisan test --filter=ServiceOrderTest
docker compose exec api php artisan test --filter=ServiceOrderItemsTest
```
