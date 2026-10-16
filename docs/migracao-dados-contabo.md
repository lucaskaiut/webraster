# Migração de dados contabo → nuvemax

Runbook para levar as bases MySQL (`webraster` e `traccar`) e os volumes de
storage do servidor antigo (**contabo**) para a VPS **nuvemax**.

> **O contabo nunca é modificado.** Ele está em produção (inclusive com outras
> aplicações) e a migração é **somente leitura** lá: dumps consistentes com
> `--single-transaction` (sem locks, sem parar nada) e tar dos volumes.
> O script pode ser executado quantas vezes quiser — cada execução substitui
> os dados da nuvemax pelos dados mais recentes do contabo.

## Como funciona

- Script único e re-executável: `scripts/migrate-from-contabo.sh`, rodado
  **na sua máquina** (ponte SSH entre os dois servidores).
- Janela curta apenas **na nuvemax** (~2–4 min, app parada durante o restore).
- Ao final, **worker e scheduler da nuvemax ficam parados**: enquanto o
  contabo estiver processando em produção, rodá-los duplicaria rotinas
  agendadas (alertas, faturamento, notificações). Ative-os no momento da
  virada (comando no fim deste doc).
- Validação automática: `COUNT(*)` **tabela a tabela** origem × destino.
- Já replicado na implantação (não migra de novo): `APP_KEY`, chaves do
  Reverb, evidências de contrato e demais segredos do `.env.prod`.

## Rodar a migração (ensaio agora — repetir na virada)

```bash
# na sua máquina, na raiz do repo
bash scripts/migrate-from-contabo.sh
```

- Duração esperada: 5–15 min (bases ~890 MB no total).
- O Uptime Kuma vai acusar indisponibilidade durante a janela na nuvemax —
  silencie o monitor ou ignore o alerta.
- Ao final confira: contagens "idênticas em N tabelas", `/up → 200`, painel
  logando com usuários reais, Traccar com os 138 dispositivos.
- Artefatos: dumps em `/tmp/nuvemax-migration/` (sua máquina); backups
  pré-migração em `nuvemax:/opt/nuvemax/backups/`.
- No ensaio, **evite ações que notifiquem clientes** (mensagens, alertas
  manuais): o worker está parado, mas a app funciona para navegação/testes.

Para descartar os dados de um ensaio: restaure os backups
`pre-migration-<ts>-*` de `/opt/nuvemax/backups/` (bases + volumes).

## Dia da virada (checklist)

Recomendado: rodar em horário de baixo uso. O contabo não é desligado em
nenhum momento.

1. **Rodar o script de novo**, o mais próximo possível da virada (leva os
   dados mais recentes):
   ```bash
   bash scripts/migrate-from-contabo.sh
   ```
2. **Validar na nuvemax**
   - [ ] Login no painel com usuário real; mapa, clientes, veículos.
   - [ ] Traccar (`https://new-dispositivos.nuvemax.srv.br`) com os
     dispositivos.
   - [ ] Baixar um arquivo do storage (upload antigo) e um contrato (private).
   - [ ] Se houver `401` no panel→Traccar (`TRACCAR_TOKEN`), gerar novo token
     no Traccar novo e atualizar `TRACCAR_TOKEN` no `.env.prod` +
     `bash scripts/deploy.sh`.
3. **Virar o DNS** (no seu provedor), apontando para `185.194.219.192`:

   | Domínio | Observação |
   |---|---|
   | `webraster-api.nuvemax.srv.br` | usado pelos frontends ao vivo |
   | `webraster-ws.nuvemax.srv.br` | **ainda não existe — criar junto com a virada** |
   | `dispositivos.nuvemax.srv.br` | rastreadores |

4. **Ajustar domínios na nuvemax e aplicar**
   ```bash
   ssh nuvemax
   cd /opt/nuvemax/panel
   sed -i 's/^API_DOMAIN=.*/API_DOMAIN=new-webraster-api.nuvemax.srv.br,webraster-api.nuvemax.srv.br/' .env.prod
   sed -i 's/^WS_DOMAIN=.*/WS_DOMAIN=new-webraster-ws.nuvemax.srv.br,webraster-ws.nuvemax.srv.br/' .env.prod
   sed -i 's/^APP_URL=.*/APP_URL=https:\/\/webraster-api.nuvemax.srv.br/' .env.prod
   sed -i 's/^TRACCAR_SERVER_DNS=.*/TRACCAR_SERVER_DNS=dispositivos.nuvemax.srv.br/' .env.prod
   sed -i 's/^WEB_URL=.*/WEB_URL=https:\/\/dispositivos.nuvemax.srv.br/' docker/traccar/.env
   sed -i 's/^TRACCAR_DOMAIN=.*/TRACCAR_DOMAIN=dispositivos.nuvemax.srv.br/' docker/traccar/.env
   bash scripts/deploy.sh
   docker compose --env-file docker/traccar/.env -f docker/traccar/docker-compose.yml up -d
   ```
   `API_DOMAIN` e `WS_DOMAIN` aceitam **lista separada por vírgula** (o Traefik
   roteia todos os hosts): mantemos os `new-*` ativos junto com os definitivos
   durante a transição. Se o app mobile ainda usar os domínios antigos, inclua
   `,webraster-api.noxtecnologias.com.br` / `,webraster-ws.noxtecnologias.com.br`.
   O deploy regera as rotas a partir dessas variáveis e o Traefik emite os
   certificados definitivos.
5. **Frontend (Vercel)**: atualizar e **rebuildar**:
   ```env
   VITE_API_BASE_URL=https://webraster-api.nuvemax.srv.br
   VITE_REVERB_HOST=webraster-ws.nuvemax.srv.br
   ```
6. **Kuma**: atualizar as URLs dos monitores para os domínios definitivos.
7. **Ativar o processamento na nuvemax** (só quando o contabo deixar de
   processar estes dados):
   ```bash
   ssh nuvemax 'cd /opt/nuvemax/panel && rm -f .workers-paused && docker compose --env-file .env.prod -f docker-compose.prod.yml start worker scheduler'
   ```
   Enquanto o arquivo `.workers-paused` existir, os **deploys automáticos
   também respeitam a pausa** (recriam worker/scheduler sem iniciar).
8. **Sobre o contabo**: permanece rodando (outras aplicações não são
   afetadas). Quando a virada estiver estável, decida com calma o momento de
   reduzir as stacks `webraster`/`traccar` dele (elas continuam executando
   rotinas agendadas mesmo sem tráfego). Comando pronto, para quando você
   quiser — fora do escopo do script:
   ```bash
   ssh contabo 'docker service scale infra-app-webraster_app=0 infra-app-webraster_worker=0 infra-app-webraster_scheduler=0 infra-app-webraster_reverb=0 infra-app-traccar_traccar=0'
   ```

## Sobre a janela de dados (gap)

Como o contabo **não é congelado**, o que for escrito nele entre o dump e a
virada do DNS não existe na nuvemax. Mitigação: rodar o script minutos antes
da virada, em horário de baixo uso. Para o Traccar isso significa perder, no
máximo, alguns minutos de posições — os rastreadores continuam reportando ao
servidor antigo até o DNS virar e, ao reconectar no novo, retomam o envio.

## Rollback

- **Sempre simples**: o contabo nunca é tocado, então basta virar o DNS de
  volta para ele (e restaurar os `VITE_*` antigos na Vercel, se já tiver
  rebuildado).
- Na nuvemax, os backups `pre-migration-<ts>-*` permitem descartar os dados
  restaurados.
