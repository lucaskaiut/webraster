# Incidente 2026-10-18 — CPU alta no MySQL por `equipment_raw_logs`

## Sintoma

Em produção, o container `nuvemax-panel-mysql-1` ficava acima de 100% de CPU
(mais de um núcleo dos 4 da VPS) de forma contínua, sem relação com picos de
acesso ao painel.

## Diagnóstico

- Nada travado: sem transações abertas em `information_schema.innodb_trx`,
  2 threads ativas, `Slow_queries = 0`, buffer pool com ~99,99% de acerto.
- `Innodb_rows_read` em **10,5 bilhões em ~3h** (≈ 930 mil linhas/s) para
  ~62 SELECTs/s.
- `performance_schema.table_io_waits_summary_by_table`: praticamente todas as
  leituras em `webraster.equipment_raw_logs`, uma tabela de só ~164 mil linhas
  (54 MB). Ou seja, dezenas de milhares de varreduras completas.
- Origem: `EquipmentRawLogService::appendFromPositionAttributes()`, chamado
  a cada posição recebida do Traccar. Ele executava:
  1. `SELECT line … WHERE equipment_id = ? ORDER BY id DESC LIMIT 1`
     (deduplicação da última linha);
  2. `INSERT`;
  3. `SELECT id … WHERE equipment_id = ? ORDER BY id DESC LIMIT 1 OFFSET 2000`
     + `DELETE … WHERE id <= ?` (limite de 2.000 linhas por equipamento).

### Causa raiz

O model usa `BelongsToTenant`, cujo `TenantScope` acrescenta `tenant_id = ?`
às consultas. A tabela tinha apenas:

- `equipment_raw_logs_tenant_id_foreign (tenant_id)` — índice criado
  implicitamente pelo MySQL para a FK;
- `equipment_raw_logs_equipment_id_id_index (equipment_id, id)`.

Com `tenant_id` + `equipment_id` no filtro, o otimizador escolhia o índice de
`tenant_id` ou um `index_merge` (intersect) dos dois índices, seguido de
`filesort`. Como existe um único tenant, o índice de `tenant_id` cobre a
tabela inteira. Medição em produção para a consulta com `OFFSET 2000`:

| Plano | Linhas lidas por consulta |
|---|---|
| Atual (`index_merge` / índice de `tenant_id`) | ~167.000 |
| Somente `(equipment_id, id)` | 2.000 |

Somado ao fato de quase todos os equipamentos já estarem no teto de 2.000
linhas (o prune rodava e apagava a cada posição), o custo por posição recebida
era de centenas de milhares de leituras.

## Correções

### 1. Índice composto `(tenant_id, equipment_id, id)`

Migration `2026_10_18_000001_add_tenant_equipment_index_to_equipment_raw_logs`.

- Atende exatamente o padrão das consultas tenant-scoped: igualdade em
  `tenant_id` e `equipment_id` e varredura reversa em `id` para o
  `ORDER BY id DESC`, sem `filesort` e coberta pelo índice no caso do `OFFSET`.
- Começa por `tenant_id`, então também serve à FK de tenant. O índice simples
  `equipment_raw_logs_tenant_id_foreign` fica redundante e é removido (se ainda
  existir — o MySQL pode descartá-lo sozinho ao surgir o composto).
- O índice `(equipment_id, id)` foi mantido: é ele que sustenta a FK de
  `equipment_id` e as consultas sem escopo de tenant (`withoutTenancy()`).
- Validado num MySQL 8.4.11 local com a mesma distribuição de produção
  (1 tenant, 86 equipamentos × 2.000 linhas): de 173.981 para 2.000 entradas
  lidas na consulta do prune, com o plano mudando de `index_merge` + `filesort`
  para `ref` com varredura reversa. O otimizador escolhe o novo índice sem
  precisar de `FORCE INDEX`.
- `ALTER TABLE … ADD INDEX` no InnoDB é online (`INPLACE`); numa tabela desse
  tamanho leva menos de um segundo e não bloqueia as gravações.

**Por que não remover o escopo de tenant (`withoutTenancy()`) no serviço?**
Também resolveria, mas só para este serviço e à custa de abrir mão da
proteção do `TenantScope`. O índice corrige a causa para qualquer consulta
tenant-scoped da tabela (inclusive a tela de log bruto) e mantém a defesa em
profundidade.

### 2. Prune por amostragem (1 em 50)

`EquipmentRawLogService::PRUNE_ODDS`.

Mesmo com o índice correto, aparar o histórico a cada posição custava um
SELECT de 2.000 entradas + um `DELETE` (com escrita em binlog e
`sync_binlog = 1`) por posição. Agora o prune roda com chance de 1 em 50 por
linha gravada, via `Illuminate\Support\Lottery`.

- O limite de 2.000 linhas por equipamento passa a ser **aproximado**: entre
  um sorteio e outro o equipamento acumula até ~50 linhas extras em média, e cada
  prune volta exatamente para 2.000. Para um log de diagnóstico interno isso
  é irrelevante.
- Sorteio em vez de `id % 50 === 0`: os ids são globais, e com equipamentos
  enviando de forma intercalada alguns poderiam nunca cair num múltiplo e
  crescer sem limite.
- `Lottery::alwaysWin()` / `alwaysLose()` tornam o comportamento determinístico
  nos testes (`EquipmentRawLogTest`).
- A retenção por idade (`tracking:prune`, 30 dias) continua igual.

## Deploy

A migration roda automaticamente na subida do container de produção
(`docker/prod/docker-entrypoint.sh` → `php artisan migrate --force`).

Para confirmar o efeito depois do deploy:

```sql
-- deve listar equipment_raw_logs_tenant_equipment_id_index
SHOW INDEX FROM webraster.equipment_raw_logs;

-- a taxa de leitura deve cair de ~930 mil para alguns milhares de linhas/s
SHOW GLOBAL STATUS LIKE 'Innodb_rows_read';
```
