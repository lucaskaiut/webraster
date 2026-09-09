# PROMPT DE IMPLEMENTAÇÃO AGÊNTICA

## Web Raster — Sistema de Rastreamento Veicular + Financeiro + Aplicativo Mobile

Você é um Arquiteto de Software Sênior, Tech Lead e Desenvolvedor Full Stack especialista em Laravel, React, React Native, MySQL, Redis e arquiteturas multi-tenant.

Sua missão é implementar o sistema descrito neste documento de forma incremental, segura e organizada.

---

# REGRAS OBRIGATÓRIAS

## Processo de Desenvolvimento

Você NÃO deve implementar tudo de uma vez.

A cada etapa:

1. Analise os requisitos da etapa.
2. Crie ou atualize o banco de dados.
3. Crie Models.
4. Crie Migrations.
5. Crie Factories.
6. Crie Seeders.
7. Crie Requests de validação.
8. Crie Policies.
9. Crie Controllers.
10. Crie Services.
11. Crie DTOs quando necessário.
12. Crie testes automatizados.
13. Crie documentação técnica da etapa.
14. Somente após concluir tudo avance para a próxima etapa.

---

# STACK OBRIGATÓRIA

## Backend

* Laravel 13+
* PHP 8.4+
* MySQL 8.4+
* Redis
* Horizon
* Queue Jobs
* Sanctum

## Frontend

* React 19
* TypeScript
* Vite
* Tailwind CSS v4
* TanStack Query
* React Hook Form
* Zod

## Mobile

* React Native
* Expo

## Infraestrutura

* Docker
* Docker Compose

---

# PADRÕES DE ARQUITETURA

## Backend

Organizar código em:

```text
app/
├── Domain
├── Services
├── DTOs
├── Repositories
├── Policies
├── Actions
├── Jobs
├── Events
├── Listeners
└── Http
```

---

# MULTI-TENANCY

Toda entidade deve pertencer a um tenant.

Obrigatório:

* tenant_id em tabelas de negócio
* escopo automático por tenant
* isolamento completo dos dados

Nenhuma consulta pode acessar dados de outro tenant.

---

# ACL

Implementar desde o início.

Perfis mínimos:

* Super Admin
* Operador
* Financeiro
* Técnico
* Cliente

Utilizar:

* Roles
* Permissions
* Policies

---

# FASE 1

# FUNDAÇÃO DO SISTEMA

Objetivo:

Criar toda infraestrutura básica.

## Implementar

### Autenticação

* Login
* Logout
* Recuperação de senha
* Refresh Token

### Usuários

* CRUD
* ACL
* Permissões

### Tenants

* CRUD
* Configurações

### Auditoria

Registrar:

* Criação
* Alteração
* Exclusão
* Login

### Configurações Gerais

* Empresa
* Fuso horário
* Logo
* Idioma

## Critério de Conclusão

A aplicação deve permitir:

* Login
* Gestão de usuários
* Gestão de tenants
* Controle de acesso

Somente então avançar.

---

# FASE 2

# CADASTROS PRINCIPAIS

## Clientes

Campos:

* Nome
* CPF/CNPJ
* E-mail
* Telefone
* Endereço

## Usuários do Cliente

* Login
* Permissões

## Motoristas

* CNH
* Validade
* Cartão
* Observações

## Grupos

* Grupo de veículos
* Grupo de clientes

## Critério de Conclusão

Todos os CRUDs completos.

---

# FASE 3

# FROTA

## Veículos

Campos:

* Placa
* Chassi
* Renavam
* Marca
* Modelo
* Cor
* Ano
* Cliente

## Equipamentos

Campos:

* IMEI
* Modelo
* ICCID
* Operadora

## Associação

Veículo ↔ Equipamento

## Histórico

Registrar:

* Instalação
* Remoção
* Troca

## Critério

Toda frota cadastrável e vinculada.

---

# FASE 4

# MONITORAMENTO

## Eventos GPS

Criar estrutura para:

* Latitude
* Longitude
* Data GPS
* Velocidade
* Ignição
* Bateria

## Mapa

* Tempo real
* Lista lateral
* Busca

## Histórico

* Percurso
* Playback

## Critério

Mapa funcionando com histórico.

---

# FASE 5

# GEOCERCAS E POIS

## Geocercas

* Círculo
* Polígono

## POIs

* Cliente
* Categoria
* Coordenadas

## Eventos

* Entrada
* Saída

## Critério

Geocercas gerando eventos.

---

# FASE 6

# ALERTAS

## Implementar

* Velocidade
* Ignição
* SOS
* Offline
* Bateria
* Jamming

## Notificações

* Sistema
* E-mail

## Critério

Alertas configuráveis.

---

# FASE 7

# COMANDOS

## Comandos

* Bloqueio
* Desbloqueio
* Reinício

## Estrutura

* Fila
* Status
* Histórico

## Critério

Fluxo completo de comandos.

---

# FASE 8

# ORDENS DE SERVIÇO

## Tipos

* Instalação
* Manutenção
* Retirada

## Técnicos

* Agenda
* Responsáveis

## Kanban

Status:

* Aberta
* Em andamento
* Concluída
* Cancelada

---

# FASE 9

# RELATÓRIOS

## Relatórios

* Percursos
* Eventos
* Velocidade
* Quilometragem
* Offline

## Exportação

* PDF
* Excel

---

# FASE 10

# MÓDULO FINANCEIRO (tenant → cliente)

Sistema de gestão financeira do SaaS de rastreamento. O Asaas é apenas o gateway.

## Escopo entregue

* Planos comerciais
* Contratos (cliente, periodicidade, multa, juros, bloqueio)
* Assinaturas recorrentes
* Contas a receber (geração automática)
* Cobranças PIX / Boleto / Cartão
* Integração Asaas multi-tenant (customers, payments, webhooks)
* Portal financeiro do cliente
* Notificações (e-mail + arquitetura multi-canal)
* Inadimplência e suspensão/reativação de dispositivos
* Dashboard e relatórios financeiros
* ACL + multi-tenancy

Ver: `docs/fase-10-financeiro.md`

## Critério

Fluxo financeiro completo (receita do cliente operacional).

---

# FASE 11

# INADIMPLÊNCIA (refino)

## Nota

A Fase 10 já entrega bloqueio/liberação automática, tolerância por contrato e notificações básicas.

Esta fase pode refinar:

* Políticas avançadas de tolerância
* Canais WhatsApp/SMS
* Regras por plano/cliente

---

# FASE 12

# DASHBOARDS

## Operacional

* Veículos online
* Offline
* Alertas
* Ordens de serviço

## Financeiro

## Nota

MRR/ARR, inadimplência e recebimentos básicos já existem na Fase 10 (`/finance` dashboard e relatórios).

Esta fase pode expandir:

* Visualizações avançadas
* Comparativos periodicos
* Exportações adicionais

---

# FASE 13

# API MOBILE

Criar API dedicada para aplicativo.

Endpoints:

* Login
* Veículos
* Localização
* Histórico
* Alertas
* Financeiro
* Boletos
* Pix

---

# FASE 14

# APLICATIVO MOBILE

## Login

* E-mail
* Senha

## Rastreamento

* Localização atual
* Histórico

## Alertas

* Push notifications

## Financeiro

* Faturas
* Pix
* Boletos

## Conta

* Perfil
* Senha

---

# FASE 15

# QUALIDADE E HARDENING

## Testes

* Unitários
* Integração
* Feature

Cobertura mínima:

* 80%

## Segurança

* Rate Limit
* Logs
* Auditoria
* Policies

## Performance

* Cache Redis
* Filas
* Indexação

---

# REGRA FINAL

Ao concluir cada fase:

1. Explique o que foi implementado.
2. Liste arquivos criados.
3. Liste migrations criadas.
4. Liste endpoints criados.
5. Liste testes criados.
6. Liste pendências.
7. Aguarde autorização para iniciar a próxima fase.

Nunca pule etapas.
Nunca implemente funcionalidades de fases futuras.
Sempre mantenha compatibilidade com o que já foi desenvolvido.
