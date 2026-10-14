# Melhorias — Monitoramento, Cadastros e Interface

Documento técnico das melhorias implementadas a partir do documento de
solicitação. Cobre API (`api/`) e painel web (`web/`), preservando as
funcionalidades existentes.

## 1. Menu principal

- `SidebarGroup` agora suporta grupos **expansíveis** (accordion), abrindo
  automaticamente quando uma rota filha está ativa e mantendo o modo
  colapsado da sidebar.
- Novo agrupamento: **Dashboard** (Monitoramento + Financeiro), **Painel de
  Monitoramento**, **Cadastros** (clientes, motoristas, serviços, contratos,
  veículos, consulta de placa, equipamentos, geocercas e eventos, POIs),
  **Financeiro**, **Ordens de Serviço**, **Relatórios**, **Mensagens**
  (antigo "Alertas", com painel de alertas, lista, preferências e
  notificações), **CRM** e **Gestão** (empresas, usuários, perfis,
  auditoria, configurações e log de dispositivos).
- Rotas: `/dashboard` passa a ser o dashboard de monitoramento;
  `/alerts/dashboard` mantém o painel de alertas antigo.

## 2. Dashboard de monitoramento

- Endpoint `GET /dashboard/monitoring` (permissão de rastreamento, veículos
  ou equipamentos) com: total de veículos, veículos online, offline até 24h,
  offline de 24h a 36h, offline há mais de 36h, equipamentos ativos,
  equipamentos em estoque e chips em estoque (equipamentos em estoque com
  número de linha).
- Faixas de offline **sem sobreposição**, calculadas pela última comunicação
  (posição persistida ou `lastUpdate` do Traccar); veículos sem sinal contam
  como +36h.
- Página `/dashboard` (`MonitoringDashboardPage`) com cards clicáveis para a
  frota e o estoque. Usuários de portal não veem os números de estoque.
- Os cards de veículos offline levam para a lista de veículos com o filtro
  `?last_signal=...` (`online`, `offline_up_to_24h`, `offline_24h_to_36h`,
  `offline_over_36h`), usando a mesma classificação do dashboard; o filtro
  também está disponível no seletor "Filtrar por último sinal" da tela e é
  respeitado pela exportação.

## 3. Painel de monitoramento

### Alertas

- Alertas **removidos do mapa** (marcadores e card do alerta). Dados
  continuam disponíveis na lista de alertas e no sino.
- Notificações centralizadas no **sino**, com lista das mais recentes, marcação
  de leitura e ação "Encerrar" (resolve o alerta) para usuários com permissão.
- **Sinal sonoro** ao chegar novo alerta: arquivos em `web/public/sounds`
  (mesmos do app), preferência por grupo (ignição, cerca, alimentação
  cortada) lida de `GET /alert-sound-preferences`, com botão de silenciar no
  sino. O navegador libera o áudio na primeira interação da página.
- Alertas permanecem no sino até serem encerrados; ao resolver, as
  notificações do alerta são marcadas como lidas, mas o **histórico é
  preservado**.
- **Auditoria**: `POST /alerts/{id}/acknowledge` e `/resolve` gravam
  `audit_logs` (`alert_acknowledged`, `alert_resolved`) e a lista de alertas
  exibe quem encerrou e quando.

### Espaço na tela

- Barra superior reduzida (`h-14` → `h-12`), controles compactados,
  espaçamentos do container e dos painéis do mapa reduzidos. O mapa ocupa
  toda a área disponível.

### Informações no mapa

- Novo controle "Informações no mapa" com opções independentes: **placa**,
  **identificação do veículo** (marca/modelo) e **nome do motorista**
  (persistidas no navegador). O payload de `GET /tracking/live` passou a
  incluir o motorista.

### Lista de veículos

- Filtro padrão **Online**.
- Removido o tempo de parada/atualização dos itens.
- Indicador de status online/offline reduzido (bolinha de 6px).
- Busca por **IMEI** mantida e consistente com a busca da tela de veículos.
- Controle de quantidade de registros (10, 50, 100, todos) e ordenação por
  mais recentes/mais antigos.

## 4. Clientes e veículos

- Formulários de cliente/veículo exibem apenas as notificações usadas:
  **ignição ligada**, **ignição desligada**, **saída de cerca** e
  **bateria violada**. As demais configurações continuam no backend para uso
  futuro.
- **Toasts** agora aparecem no canto superior, sem cobrir os botões de
  avanço dos wizards.
- Veículos exibem a **data/hora de cadastro** na listagem e no cabeçalho da
  edição.
- Botão **Exportar** na lista de veículos (`GET /vehicles/export`, XLSX) que
  respeita busca e filtro de cliente.

## 5. Usuários do cliente

- Novo campo `users.is_primary`. Ao criar um cliente com e-mail e CPF/CNPJ
  válidos, o **usuário principal** é criado automaticamente com nome, e-mail,
  telefone e perfil Cliente (senha inicial = 6 primeiros dígitos do
  documento). Sem esses dados, a aba de usuários oferece "Preencher com
  dados do cliente".
- Usuários adicionais continuam disponíveis normalmente.

## 6. Equipamentos

- **Validação do número do equipamento**: apenas dígitos, até 16 caracteres,
  sem espaços, obrigatório (frontend e API).
- Campo **ICCID** renomeado para **Número da Linha** (coluna `line_number`),
  com rótulo, busca e exportação atualizados.
- Listagem com **botão de atualizar**, quantidade de registros (10, 50, 100,
  todos) e ordenação por mais recentes/mais antigos.
- **Posições e indicadores sem vínculo**: posições de equipamentos em estoque
  passam a ser persistidas (`gps_positions.vehicle_id`/`client_id` nulos) e
  expostas em `GET /tracking/equipments/live`; a listagem e a edição do
  equipamento exibem última posição e indicadores.
- **Ação de teste**: botão "Testar equipamento" na listagem abre os comandos
  suportados pelo dispositivo (respeitando a integração Traccar) e o
  histórico de comandos, sem exigir vínculo com veículo.

## 7. Gestão de veículos

- Busca por **IMEI do equipamento** na API e na interface.
- Removido o texto "Envie comandos para o rastreador instalado via Traccar.".

## 8. Contratos (prioridade)

- Colunas de evidência em `client_contracts`: `document_hash`,
  `signature_hash` (SHA-256), `signed_ip` e `signed_user_agent`.
- Assinatura pelo app, marcação manual e importação de documento registram
  hashes e dados da requisição; o histórico não é apagado.
- Ações de contrato entram na **auditoria** (`contract_signed`,
  `contract_signature_updated`, `contract_document_uploaded`).
- A seção de contrato do cliente exibe o bloco "Evidências da assinatura"
  com hashes (copiáveis), IP e dispositivo.

## 9. Compartilhamento de veículos entre clientes

- Tabela `vehicle_shares` (veículo + cliente autorizado, únicos) preservando
  a propriedade em `vehicles.client_id`.
- Endpoints em `/clients/{client}/shared-vehicles` (listar, associar,
  remover), com validação de tenant, bloqueio de duplicidade e de
  compartilhamento com o próprio proprietário.
- A listagem de veículos do cliente autorizado inclui os compartilhados; a
  tela de veículos identifica o proprietário e permite desvincular.
- Usuários do portal do cliente autorizado também visualizam os veículos
  compartilhados (escopo `ClientScope` ciente de compartilhamentos).
- Veículo "candidato" via `GET /vehicles?exclude_client_id=...`.

## Ordenação nas tabelas

- O componente `DataTable` exibe um botão de ordenação em cada coluna
  marcada como ordenável. O clique alterna entre **crescente →
  decrescente → desativado**, sem perder as demais colunas ativas.
- Múltiplas colunas podem ser ordenadas ao mesmo tempo (na ordem do clique),
  com indicador numérico da prioridade quando há mais de uma.
- O estado é persistido na URL no formato `?sort=coluna:asc,coluna2:desc` e
  enviado integralmente à API.
- Backend: helper `App\Modules\Shared\Support\QuerySort` aplica somente
  colunas permitidas (com desempate por `id`) e mantém a ordenação padrão de
  cada listagem. Endpoints compatíveis: clientes, veículos (inclusive
  exportação), equipamentos (mantendo `oldest`/`newest` por
  compatibilidade), usuários, usuários do cliente, motoristas, serviços,
  contratos, geocercas e POIs.

## Endpoints novos

| Método | Rota | Descrição |
|---|---|---|
| GET | `/dashboard/monitoring` | Indicadores operacionais |
| GET | `/vehicles/export` | Exportação XLSX de veículos |
| GET | `/tracking/equipments/live` | Posição/indicadores de equipamentos em estoque |
| GET | `/alert-sound-preferences` | Preferências de som (já existente, consumida pelo web) |
| GET/POST/DELETE | `/clients/{client}/shared-vehicles` | Compartilhamento entre clientes |

## Migrations

- `2026_10_14_000001_rename_iccid_to_line_number_on_equipments_table`
- `2026_10_14_000002_make_vehicle_id_nullable_on_gps_positions_table`
- `2026_10_14_000003_add_evidence_to_client_contracts_table`
- `2026_10_14_000004_add_is_primary_to_users_table`
- `2026_10_14_000005_create_vehicle_shares_table`

## Testes

- `tests/Feature/Dashboard/MonitoringDashboardTest.php` — faixas de offline,
  estoque/chips e permissões.
- `tests/Feature/Client/ClientVehicleShareTest.php` — compartilhamento,
  duplicidade, isolamento por tenant e visualização pelo portal.
- `tests/Feature/Client/ClientCrudTest.php` — usuário principal.
- `tests/Feature/Client/ClientContractTest.php` — hashes, evidências e
  auditoria.
- `tests/Feature/Vehicle/VehicleCrudTest.php` — busca por IMEI e exportação.
- `tests/Feature/Equipment/EquipmentCrudTest.php` — validação do número do
  equipamento e Número da Linha.
- `tests/Feature/Tracking/TrackingLiveTest.php` — equipamentos em estoque.
- `tests/Feature/Tracking/TraccarWebhookTest.php` — posição de equipamento
  sem veículo.
