# Estoque de equipamentos

## Conceito

O sistema possui vários estoques por organização. Cada equipamento é um item único
(identificado pelo IMEI), portanto **não existe campo de quantidade** no cadastro.

- `stocks` — cadastro dos estoques (nome, descrição e status).
- `equipments.stock_id` — estoque atual do equipamento.
- `users.stock_id` — estoque de uso do técnico.
- `equipment_stock_movements` — livro de movimentações do equipamento.

Equipamentos instalados ficam fora do estoque (`stock_id = null`) e retornam ao
último estoque de origem quando são removidos do veículo.

## Regras

1. O cadastro de equipamento permite escolher um estoque. Sem estoque, o equipamento
   continua existindo, mas não aparece para técnicos com estoque vinculado.
2. O cadastro de técnico permite escolher um estoque de uso. Sem estoque, o técnico
   pode usar qualquer equipamento disponível.
3. Na OS, a lista de equipamentos é filtrada por `available=1` e pelo estoque do
   técnico selecionado. O backend valida novamente a regra ao salvar.
4. Trocar o técnico na OS limpa o equipamento selecionado para evitar manter um item
   incompatível com o novo estoque.
5. Equipamento instalado não pode ter o estoque alterado pelo cadastro. A troca de
   estoque só é permitida enquanto ele está disponível.
6. A exclusão de um estoque é bloqueada enquanto houver equipamentos ou técnicos
   vinculados.

## Movimentações auditadas

| Tipo | Quando ocorre |
|------|----------------|
| `entry` | Equipamento criado ou vinculado a um estoque. |
| `transfer` | Equipamento transferido entre estoques pelo cadastro. |
| `exit` | Equipamento removido de um estoque sem instalação. |
| `installation` | Equipamento instalado em um veículo. |
| `return` | Equipamento removido do veículo e devolvido ao estoque de origem. |

Cada movimentação guarda:

- equipamento;
- estoque de origem e destino;
- tipo e descrição;
- data/hora (`occurred_at`);
- usuário que moveu (`moved_by`, quando houver);
- registro correspondente na auditoria geral (`equipment_stock_moved`).

A área **Movimentações de estoque** dentro da edição do equipamento exibe esses
registros em ordem cronológica decrescente, com data, hora, descrição e responsável.

Ao **concluir uma OS** que tenha equipamento e veículo selecionados, o equipamento é
instalado automaticamente no veículo:

- o equipamento sai do estoque e é vinculado ao veículo;
- a movimentação `installation` é registrada com o código da OS na descrição;
- se o veículo já possuir outro equipamento, ele é substituído pelo fluxo de troca,
  retornando ao estoque de origem com movimentação `return`;
- se a OS possuir equipamento mas não possuir veículo, nenhuma instalação é feita.

A conclusão também aparece no histórico de atribuição do veículo e na auditoria geral
do sistema.

## API

| Método | Rota | Permissão |
|--------|------|-----------|
| GET | `/stocks` | `stock.read` |
| POST | `/stocks` | `stock.create` |
| GET | `/stocks/{stock}` | `stock.read` |
| PUT/PATCH | `/stocks/{stock}` | `stock.update` |
| DELETE | `/stocks/{stock}` | `stock.delete` |
| GET | `/equipments?available=1&technician_id={uuid}` | `equipment.read` |
| GET | `/equipments/{equipment}/stock-movements` | `equipment.read` |

O payload de equipamento aceita `stock_id` (UUID) e o de usuário técnico também.
Transferências e instalações são registradas automaticamente pelo backend; não há
endpoint separado que altere o estoque sem gerar movimentação.

## Testes

```bash
cd panel
docker compose exec -T api php artisan test --filter=StockTest
```

Os testes cobrem CRUD de estoque, bloqueio de exclusão, entrada/transferência auditada,
instalação/retorno ao estoque, bloqueio de transferência de equipamento instalado,
filtro do seletor da OS e vínculo do estoque ao técnico.
