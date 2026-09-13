# Fase 2 — Cadastros principais

## Escopo entregue

- CRUD de **Clientes** (nome, CPF/CNPJ, e-mail, telefone, endereço, status)
- **Usuários do cliente** (login + perfis) em `/clients/{id}/users`
- CRUD de **Motoristas** (CNH, validade, observações, vínculo com cliente)
- CRUD de **Serviços** (nome e valor) em `/services`
- CRUD de **Contratos** (nome + texto rico com variáveis) em `/contracts`
- **Pedido do cliente** (serviços × veículos) em `/clients/{id}/edit?tab=pedido`
- Cadastro de cliente em **wizard**: informações básicas → endereço → usuários → veículos → serviço

## Isolamento por cliente (portal)

Análogo ao `tenant_id`:

| Peça | Papel |
|------|--------|
| `CurrentClient` / `ClientContext` | Contexto da request |
| `ResolveClientScope` middleware (`client.scope`) | Ativa o contexto se `user.client_id` estiver preenchido |
| `BelongsToClient` + `ClientScope` | `where client_id = ?` automático |
| `Client` (coluna `id`) | Portal só vê o próprio cliente |

**Staff** (`client_id` null): sem restrição por cliente.  
**Portal**: só dados do próprio cliente.

Para novos recursos (ex.: veículos): `use BelongsToClient` no model.

## Endpoints

| Método | Rota | Permissão |
|--------|------|-----------|
| GET/POST | `/api/clients` | `client.read` / `client.create` |
| GET/PUT | `/api/clients/{client}/order` | `client.read` / `finance-subscription.create` |
| GET/POST | `/api/clients/{client}/users` | `client.read` / `client.update` |
| PUT/DELETE | `/api/clients/{client}/users/{user}` | `client.update` |
| GET/POST | `/api/drivers` | `driver.read` / `driver.create` |
| GET/PUT/DELETE | `/api/drivers/{driver}` | `driver.read/update/delete` |
| GET/POST | `/api/services` | `service.read` / `service.create` |
| GET/PUT/DELETE | `/api/services/{service}` | `service.read/update/delete` |
| GET/POST | `/api/contracts` | `contract.read` / `contract.create` |
| GET/PUT/DELETE | `/api/contracts/{contract}` | `contract.read/update/delete` |

## Testes

- `tests/Feature/Client/ClientCrudTest.php`
- `tests/Feature/Client/ClientScopeIsolationTest.php`
- `tests/Feature/Driver/DriverCrudTest.php`
- `tests/Feature/Service/ServiceCrudTest.php`
- `tests/Feature/Contract/ContractCrudTest.php`
- `tests/Feature/Client/ClientOrderTest.php`
