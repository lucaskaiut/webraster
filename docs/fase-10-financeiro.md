# Fase 10 — Sistema de Gestão Financeira

## Resumo

Módulo **Finance** (tenant → cliente operacional). O Asaas é gateway multi-tenant. O Billing legado (plataforma → tenant) permanece separado e com UI desabilitada.

## Domínio

| Entidade | Código | Descrição |
|----------|--------|-----------|
| Plano | — | Valor, periodicidade, limite de dispositivos |
| Contrato | `CTR-000001` | Cliente + plano + regras de bloqueio |
| Assinatura | — | Recorrência do contrato |
| Conta a receber | `REC-000001` | Cobrança / fatura |

## Status da conta a receber

`pending` → `awaiting_payment` → `received`  
também: `overdue`, `cancelled`, `refunded`

## Fluxo

```text
Contrato ativo → Assinatura → Conta a receber → Asaas → Webhook → Liquidação
                                                      ↓
                                              (reativa dispositivos)
```

Inadimplência: após `block_after_days` com `block_on_overdue`, `BillingSuspensionEngine` + `DeviceSuspensionProvider` suspende equipamentos.

## API (operador)

| Método | Rota |
|--------|------|
| CRUD | `/finance/plans` |
| CRUD + status | `/finance/contracts` |
| list/cancel/reactivate | `/finance/subscriptions` |
| list/generate/charge/cancel/mark-received | `/finance/receivables` |
| GET/PUT | `/finance/asaas-config` |
| GET | `/finance/dashboard` |
| GET | `/finance/reports?type=` |

## Portal do cliente

| Método | Rota |
|--------|------|
| GET | `/finance/portal/subscription` |
| GET | `/finance/portal/receivables` |
| POST | `/finance/portal/receivables/{id}/pay` |

## Webhook

`POST /api/webhooks/asaas/{tenantUuid}`  
Header: `asaas-access-token`

## Commands

```bash
php artisan finance:generate-receivables
php artisan finance:mark-overdue
php artisan finance:process-delinquency
php artisan finance:notify-due-soon
```

## Frontend

- Operador: `/finance`, `/finance/plans`, `/finance/contracts`, `/finance/receivables`, `/finance/subscriptions`, `/finance/reports`, `/finance/asaas-config`
- Portal: `/finance/portal`, `/finance/portal/subscription`, `/finance/portal/history`

## Testes

```bash
docker compose exec api php artisan test --filter=FinanceModuleTest
```
