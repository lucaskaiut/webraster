# Fase 2 — Cadastros principais

## Escopo entregue

- CRUD de **Clientes** (nome, CPF/CNPJ, e-mail, telefone, endereço, status)
- **Usuários do cliente** (login + perfis) em `/clients/{id}/users`
- CRUD de **Motoristas** (CNH, validade, observações, vínculo com cliente)

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
| GET/PUT/DELETE | `/api/clients/{client}` | `client.read/update/delete` |
| GET/POST | `/api/clients/{client}/users` | `client.read` / `client.update` |
| PUT/DELETE | `/api/clients/{client}/users/{user}` | `client.update` |
| GET/POST | `/api/drivers` | `driver.read` / `driver.create` |
| GET/PUT/DELETE | `/api/drivers/{driver}` | `driver.read/update/delete` |

## Testes

- `tests/Feature/Client/ClientCrudTest.php`
- `tests/Feature/Client/ClientScopeIsolationTest.php`
- `tests/Feature/Driver/DriverCrudTest.php`
