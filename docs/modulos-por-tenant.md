# Controle de tenants filhos: situação e módulos

## Resumo

O tenant umbrella controla, no cadastro de cada empresa filha (**Gestão → Empresas**):

- **Situação** (`tenants.is_active`): empresa inativa tem o acesso bloqueado para todos os usuários (painel web, agendamento, app e tokens de API). O umbrella nunca é inativado.
- **Módulos** (`tenants.modules`, JSON): funcionalidades liberadas para a empresa. O umbrella sempre tem todos.

A cobrança do umbrella aos filhos (módulo `Billing`) está desligada (`BILLING_ENABLED=false`): o controle é manual e nenhuma cobrança é gerada.

## Como o bloqueio funciona

| Regra | Onde |
|-------|------|
| Tenant inativo → 403 com `errors.tenant_inactive` em qualquer rota autenticada | `ResolveTenant` + `TenantInactive` |
| Login em empresa inativa → 422 | `AuthService::login` |
| Master não seleciona nem lista filho inativo | `MasterTenantAccessService`, `TenantSwitchService` |
| Logout funciona mesmo com a empresa inativa | rota `auth/logout` fora do middleware `tenant` |
| Permissões de módulos desativados deixam de valer | `HasRoles::permissionValues()` e `ApiToken::can()` |
| Rotas protegidas só por permissão do núcleo (abas do cliente, preferências de alerta) | middleware `module:<chave>` |
| Alertas de empresa inativa ou sem o módulo são gravados, mas não notificados | `AlertDispatcher` |
| Rotinas financeiras e lembretes de OS ignoram empresas inativas ou sem o módulo | comandos `finance:*` e `service-orders:send-reminders` |

Na tela de perfis de acesso, permissões de módulos não liberados ficam ocultas (`available_permissions` em `/auth/me`); as que já estavam no perfil são mantidas ao salvar.

O cadastro público (`POST /api/auth/register`) foi fechado: empresas filhas só são criadas pelo umbrella.

As permissões continuam salvas nos perfis: reativar um módulo devolve o acesso sem reconfigurar perfis. Como `/auth/me` devolve as permissões já filtradas, o painel web, o agendamento e o app escondem menus e telas pelas checagens de permissão existentes.

Para usuários comuns valem os módulos do próprio tenant. Para masters operando um filho (`X-Tenant-Id`), valem os módulos do tenant selecionado.

O catálogo está em `App\Modules\Tenant\Enums\TenantModule` e é servido em `GET /api/tenant/modules`.

## Núcleo (sempre disponível)

Não depende de módulo: dashboard, usuários, perfis de acesso, configurações da empresa (dados, app, alertas padrão), auditoria, **clientes** (dados e usuários do cliente), notificações in-app, uploads, tokens de API e webhooks (ocultos no front).

## Módulos

Uma permissão pode pertencer a mais de um módulo (cadastros compartilhados): basta um deles estar ativo.

| Módulo (chave) | Telas e funcionalidades | Requer |
|----------------|-------------------------|--------|
| **Rastreamento e monitoramento** (`tracking`) | Painel de monitoramento (mapa ao vivo, histórico), veículos, motoristas, equipamentos, estoques, comandos ao dispositivo, log do dispositivo, consulta de placa, compartilhamento de veículos (aba Veículos do cliente) | — |
| **Geocercas e POIs** (`geofences`) | Geocercas, eventos de geocerca, POIs | `tracking` |
| **Alertas e notificações** (`alerts`) | Painel de alertas, alertas, configurações de alerta (aba Alertas do cliente e do veículo), "Meus alertas" do portal, sons de alerta, envio e histórico de notificações | `tracking` |
| **Relatórios de rastreamento** (`reports`) | Relatórios de posições, paradas, viagens, eventos e comandos, exportação | `tracking` |
| **Ordens de serviço** (`service_orders`) | OS (lista, kanban, calendário, detalhe, PDF, anexos, fotos), cadastro de serviços, checklists, técnicos e jornadas, comissões, app do técnico, painel de agendamento, estimativa de agenda | — |
| **Contratos e assinatura digital** (`contracts`) | Modelos de contrato, contrato do cliente (aba Contrato), assinatura no portal, evidências | — |
| **Financeiro** (`finance`) | Dashboard financeiro, planos, assinaturas, cobranças, relatórios, gateway, pedido do cliente (aba Pedido), portal de faturas do cliente, cobrança da OS | — |
| **CRM** (`crm`) | Conversas (WhatsApp), leads, kanban, etapas, configurações de IA e conexões | — |
| **Assistente de IA** (`assistant`) | Assistente conversacional (tela hoje oculta no front) | — |

Cadastros compartilhados:

- **Veículos e consulta de placa**: `tracking`, `service_orders` e `finance` (a OS e o pedido usam veículos).
- **Equipamentos e estoques**: `tracking` e `service_orders` (instalação e estoque do técnico).
- **Serviços**: `service_orders` e `finance` (o pedido do cliente é serviços × veículos).

## Cobrança do umbrella desligada

Com `BILLING_ENABLED=false` (padrão):

- `billing:generate-invoices`, `billing:check-invoice-status` e `billing:suspend-expired-subscriptions` não rodam no agendador;
- as rotas `billing/subscription*` e `billing/invoices*` respondem 404;
- o cadastro de empresa pelo umbrella não cria assinatura nem fatura.

O módulo **Financeiro** (empresa → seus clientes) não é afetado. Para religar a cobrança do umbrella, defina `BILLING_ENABLED=true`.

## Migração

`2026_10_30_000001_add_status_and_modules_to_tenants_table` cria `is_active` (padrão `true`) e `modules`, preenchendo todos os tenants existentes com todos os módulos: nada muda para quem já usa o sistema até o umbrella desmarcar algo. Tenants novos sem `modules` informado também nascem com todos.
