# Agendamento

Frontend dedicado ao lançamento de **Ordens de Serviço** (lista, kanban, agenda,
criação, edição e detalhe). Derivado de `../web`, consome a mesma API e usa o
mesmo login (Sanctum SPA via cookie).

## Desenvolvimento

Sobe junto com a stack (`docker compose up -d agendamento`) na porta
`AGENDAMENTO_PORT` (padrão `5175`). O Vite encaminha `/api`, `/sanctum` e
`/storage` para o Nginx da API, então o navegador fala sempre com a mesma origem.

A origem do agendamento precisa estar em `SANCTUM_STATEFUL_DOMAINS` (e, em
produção, em `CORS_ALLOWED_ORIGINS`) no `.env` da API.

## Scripts

- `npm run dev` — servidor de desenvolvimento
- `npm run build` — checagem TypeScript + build de produção
- `npm test` — testes unitários
- `npm run lint` — oxlint
