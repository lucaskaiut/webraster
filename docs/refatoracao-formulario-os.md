# Refatoração do formulário de OS — regras e dependências

Este documento registra as regras identificadas antes da reorganização visual do
formulário de criação/edição de Ordens de Serviço. A refatoração é exclusivamente de
interface: contratos de API, persistência, cálculos e disponibilidade permanecem
inalterados.

## Arquivos envolvidos

- `web/src/modules/service-orders/components/ServiceOrderForm.tsx` — orquestra o
  formulário, os itens e o payload.
- `web/src/modules/service-orders/components/ServiceOrderSchedulingPanel.tsx` —
  endereço, duração, data, sugestão de horários, deslocamento e trajeto.
- `web/src/modules/service-orders/components/ServiceOrderRouteMap.tsx` — mapa das rotas.
- `web/src/modules/service-orders/schemas/service-order.schema.ts` — validações do
  frontend.
- `web/src/modules/service-orders/services/service-orders.service.ts` — contrato do
  payload e da estimativa.
- `api/app/Modules/ServiceOrder/Http/Requests/{Store,Update}ServiceOrderRequest.php`
  — validações do backend.
- `api/app/Modules/ServiceOrder/Services/ServiceOrderService.php` — regras de criação,
  atualização, conflito, duração e cobrança.
- `api/app/Modules/Scheduling/Services/SchedulingService.php` — jornada, slots,
  deslocamento e conflitos.
- `api/app/Modules/Scheduling/Http/Controllers/ScheduleEstimateController.php` —
  estimativa usada pelo painel.

## Campos e obrigatoriedade

| Campo | Regra |
|-------|-------|
| `client_id` | Obrigatório no frontend e no backend. |
| `priority` | Obrigatório; padrão `normal` na criação. |
| `status` | Obrigatório no formulário. Na criação aceita `open` ou `in_progress`; o backend usa `open` quando omitido. Na edição só aparecem a situação atual e as transições permitidas. |
| `vehicle_id` | Opcional. Deve pertencer ao cliente selecionado. |
| `equipment_id` | Opcional. Quando há veículo, se o dispositivo tiver vínculo, deve pertencer a esse veículo. |
| `technician_id` | Opcional. Precisa ser um usuário técnico. |
| `address` | Opcional. Em branco, o backend usa o endereço do cliente. Máximo 500 caracteres. |
| `estimated_duration_minutes` | Opcional; inteiro entre 1 e 1440. Em branco, o backend soma o tempo estimado dos serviços. |
| `scheduled_start_at` / `scheduled_end_at` | Opcionais. O fim deve ser posterior ao início. Horários são arredondados para a grade de 15 minutos. |
| `description` / `notes` | Texto livre, até 5000 caracteres cada. Não têm relação com cobrança. |
| `cancellation_reason` | Obrigatório quando a situação é `cancelled`. |
| `items[]` | Opcional. Cada item exige `service_id` do catálogo, quantidade inteira >= 1 e valor unitário em centavos >= 0. |
| `ignore_schedule_conflict` | Opcional; permite salvar mesmo com conflito de agenda. |

## Dependências entre campos

- Ao trocar o cliente, o formulário limpa `vehicle_id` e `address` para não manter
  vínculos/endereço do cliente anterior.
- As opções de veículo são consultadas com `client_id`; o backend valida novamente o
  vínculo.
- O técnico lista apenas usuários `type=technician` e `technician_active=true`.
- O dispositivo não é filtrado pelo veículo na consulta atual; a validação de vínculo
  acontece no backend.
- Situação `cancelled` revela o campo de motivo do cancelamento.
- A estimativa considera `client_id`, `address`, `technician_id`, `duration_minutes`,
  `items[]` e `service_order_id` (para ignorar a própria OS na edição).

## Serviços e valores

- Serviço, quantidade e valor unitário são editáveis.
- Ao escolher um serviço do catálogo, nome e valor unitário são preenchidos com os
  dados atuais do catálogo; o nome e o valor ficam gravados como snapshot na OS.
- O total da linha é `valor unitário × quantidade`.
- O total geral soma apenas linhas válidas (com serviço do catálogo).
- Linhas com serviço removido do catálogo (`service_id` nulo) são mantidas na tela,
  aparecem com aviso e são excluídas no próximo salvamento.
- Linhas sem serviço ou com quantidade inválida bloqueiam o envio com mensagem.
- A cobrança não é gerada no formulário de OS; ela é gerada depois, na tela da OS.
  A única exceção é o fluxo do cadastro do cliente, que usa o `footer` do formulário
  para gerar a cobrança explicitamente após criar a OS.

## Agendamento e disponibilidade

- A duração vem, nesta ordem: duração manual informada; soma de
  `service.estimated_minutes × quantity` dos itens; duração existente da OS; padrão de
  60 minutos.
- O endereço do atendimento usa o endereço informado ou, em branco, o endereço
  cadastrado do cliente. O backend geocodifica e grava latitude/longitude.
- O deslocamento de ida parte da OS anterior do técnico ou da base; o de volta vai para
  a próxima OS ou para a base. A base é o endereço-base do técnico.
- A agenda do dia considera apenas OS não canceladas e não concluídas do técnico,
  exceto a própria OS em edição.
- A jornada vem de `user_work_hours`; sem jornada cadastrada, usa 08:00–18:00.
- Os horários candidatos são gerados a cada 15 minutos dentro da jornada, a partir de
  agora, e cada candidato é testado contra sobreposição, deslocamento de ida/volta e
  limites da jornada.
- Horários bloqueados continuam sendo devolvidos pela API com motivo e OS responsável.
- A seleção de um horário disponível grava `scheduled_start_at` e `scheduled_end_at` no
  formulário. Antes de aplicar, o painel refaz a estimativa e confirma que o horário
  continua disponível com os dados atuais.
- O backend recalcula conflitos ao salvar. `ignore_schedule_conflict` é a válvula de
  escape existente e continua explícita no formulário.
- Alterações de cliente, endereço, duração, data ou itens precisam atualizar a
  disponibilidade; um horário selecionado não deve ser tratado como válido se deixou de
  estar disponível.
- Execução e deslocamento são períodos distintos: os slots mostram o período de
  execução no cliente; o deslocamento é calculado à parte.

## Situação e fluxo

- Criação: `open` ou `in_progress`.
- Edição: a situação atual e as transições permitidas:
  `open → in_progress|cancelled`, `in_progress → completed|cancelled`,
  `completed` e `cancelled` ficam terminais.
- A troca de situação em edição depende da permissão
  `service-order.change-status`, validada também no backend.
- OS concluída ou cancelada não pode ser editada.
- O `footer` do formulário é preservado para o fluxo de cobrança opcional do cadastro
  do cliente.

## Diretrizes da refatoração

- Manter os mesmos campos, payload, validações, defaults e mensagens de erro.
- Organizar em etapas visuais em uma única página: dados essenciais, serviços,
  agendamento, descrição/observações e conclusão.
- Deixar a seleção de horário em destaque, com detalhes de trajeto sob demanda.
- Manter o mapa e todas as informações operacionais acessíveis.
- Usar superfícies, espaçamento e tipografia do design system, sem bordas em cards.
- Preservar navegação por teclado, rótulos e mensagens de validação.

## Organização implementada

1. **Dados essenciais** — cliente, veículo, dispositivo, técnico, prioridade e
   situação em uma grade de 4 colunas no desktop e 2 no tablet. Os campos opcionais
   estão identificados no próprio rótulo auxiliar.
2. **Serviços e valores** — lista compacta com colunas de serviço, quantidade, valor
   unitário e total por linha. A inclusão fica no cabeçalho da etapa (ou no estado
   vazio) e o total geral permanece sempre visível.
3. **Agendamento** — endereço e duração no topo; data e horários em um bloco único;
   horários disponíveis com destaque e bloqueados com motivo. Início/Fim continuam
   editáveis logo abaixo. Execução, deslocamento e trajeto aparecem em resumo
   compacto; o mapa e os endereços completos ficam em um detalhe expansível.
4. **Descrição e observações** — dois campos de texto livre lado a lado no desktop.
5. **Revisão e gravação** — resumo de serviços, agendamento e configuração, seguido
   de barra de ações persistente com total, agendamento, cancelar e salvar.

### Atualização automática da disponibilidade

O painel consulta `/scheduling/estimate` após uma pausa (`debounce` de 500 ms) quando
cliente, endereço, duração, técnico, data, serviços ou Início/Fim mudam. O botão de
atualização manual continua disponível. Antes de aplicar um horário, a API é
consultada novamente com o horário escolhido; se ele deixou de estar disponível, o
horário não é aplicado e o usuário recebe um aviso. Respostas antigas são descartadas
para impedir que uma disponibilidade calculada com dados anteriores sobrescreva a
atual. A confirmação feita ao clicar em um horário dispensa a consulta duplicada
disparada apenas pelo preenchimento automático de Início/Fim.

## Testes

- `cd panel/web && npm test` — testes unitários dos cálculos de itens e do
  agrupamento de bloqueios da agenda.
- `cd panel/web && npm run build` — verificação TypeScript + build de produção.
- `cd panel/web && npm run lint` — análise estática.
- `docker compose exec -T api php artisan test --filter='SchedulingTest|ServiceOrderTest|ServiceOrderItemsTest'`
  — regras de agendamento, conflitos, duração, serviços, situação e permissões.
