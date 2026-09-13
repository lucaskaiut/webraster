# Fase 10 — Sistema de Gestão Financeira

## Resumo

Módulo **Finance** (tenant → cliente operacional). Pagamentos passam por `PaymentGatewayInterface` (`config/finance.php`). O Asaas é uma implementação. O Billing legado (plataforma → tenant) permanece separado e com UI desabilitada.

## Domínio

| Entidade | Código | Descrição |
|----------|--------|-----------|
| Plano | — | Valor, periodicidade, limite de dispositivos |
| Assinatura | — | Cliente + plano (snapshot) + regras de bloqueio + recorrência |
| Cobrança (billing) | `BILL-000001` | Fatura gerada a partir da assinatura |

Cliente possui `plan_id` / `plan` opcional (legado). A fonte de verdade do contrato operacional é o **Pedido** (serviços × veículos), que grava o total na assinatura (`plan_price_cents`).

## Status da cobrança

`pending` → `awaiting_payment` → `paid`  
também: `overdue`, `cancelled`, `refunded`

## Status da assinatura

`active` | `past_due` | `suspended` | `cancelled`

## Fluxo

```text
Pedido (serviços × veículos) → Assinatura (snapshot do total) → Cobrança (billing) → Gateway → Webhook → Liquidação
                                                                                              ↓
                                                                                      (reativa dispositivos)
```

Inadimplência: após `block_after_days` com `block_on_overdue`, motor de suspensão + `DeviceSuspensionProvider` suspende equipamentos.

## API (operador)

| Método | Rota |
|--------|------|
| CRUD | `/finance/plans` |
| list/show/PATCH | `/finance/subscriptions`, `/finance/subscriptions/{id}` |
| POST assign / cancel / reactivate | `/finance/subscriptions/assign`, `.../cancel`, `.../reactivate` |
| GET/PUT | `/clients/{clientId}/order` | pedido atual (itens + veículos + total) |
| GET overview | `/finance/clients/{clientId}/overview` → `{ plan?, subscription?, open_billing?, billings[] }` |
| list/show/generate/charge/cancel/mark-paid | `/finance/billings` |
| GET/PUT | `/finance/payment-gateway-config` |
| GET | `/finance/dashboard` |
| GET | `/finance/reports?type=` (`billings`, `delinquency`, `receipts`, `subscriptions`, `blocked_clients`) |

## Portal do cliente

| Método | Rota |
|--------|------|
| GET | `/finance/portal/subscription` |
| GET | `/finance/portal/billings` |
| POST | `/finance/portal/billings/{id}/pay` |

## Webhook

`POST /api/webhooks/payments/{gateway}/{tenantUuid}`

A autenticação e o parse do payload são responsabilidade do gateway. Eventos de pagamento só liquidam a cobrança depois de `getPayment()` confirmar status `PAID`.

## Commands

```bash
php artisan finance:generate-billings
php artisan finance:mark-overdue
php artisan finance:process-delinquency
php artisan finance:notify-due-soon
```

## Frontend

- Operador: `/finance/dashboard`, `/finance/plans`, `/finance/subscriptions`, `/finance/billings`, `/finance/reports`, `/finance/gateway-config`
- Cliente (aba Pedido): montagem de serviços × veículos + vencimento + histórico de cobranças
- Portal: `/finance/portal/billings`, `/finance/portal/subscription`, `/finance/portal/history`

## Testes

```bash
docker compose exec api php artisan test --filter=FinanceModuleTest
```
