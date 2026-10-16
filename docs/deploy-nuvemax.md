# Deploy na VPS nuvemax (Docker Compose + Traefik)

Runbook da stack de produção do painel (API Laravel + Reverb + workers) e do
Traccar na VPS **nuvemax** (`root@185.194.219.192`), substituindo aos poucos a
stack Swarm do servidor **contabo** (`194.163.133.41`).

## Arquitetura

```
Internet
  │ :80/:443            (+ portas GPS: 5011, 5023, 5130-5131, 5135 tcp)
  ▼
nuvemax-gateway (Traefik v3.6 + Let's Encrypt)   rede: nuvemax_edge (172.31.0.0/24)
  ├── new-webraster-api…  → app-a/app-b (blue-green; Nginx + PHP-FPM, migra no boot)
  ├── new-webraster-ws…   → reverb (WebSocket :8080)
  └── new-dispositivos…  → traccar (:8082;  rastreadores entram pelas portas GPS)

nuvemax-panel (docker-compose.prod.yml)          rede: nuvemax_shared (172.31.1.0/24)
  ├── mysql:8.4 (bases webraster + traccar)
  ├── app-a / app-b (blue-green) + worker (x2) / scheduler  — imagem local/webraster-api:latest
  └── reverb

nuvemax-traccar (docker/traccar/docker-compose.yml)
  └── traccar — imagem local/traccar:6.15.3-easytrack-cc (decoder EasyTrack CC)
```

- Um único artefato (`local/webraster-api:latest`) roda os papéis
  `web` / `worker` / `scheduler` / `reverb` via `CONTAINER_ROLE`.
- MySQL é compartilhado entre painel e Traccar (bases e usuários separados).
- O frontend (`web/`) não roda aqui: continua hospedado fora (Vercel),
  falando com a API pelo domínio `API_DOMAIN`.

## DNS necessário

Registros `A` apontando para `185.194.219.192`:

| Domínio                                   | Uso                          |
|-------------------------------------------|------------------------------|
| `new-webraster-api.nuvemax.srv.br`        | API do painel                |
| `new-webraster-ws.nuvemax.srv.br`         | WebSocket (Reverb)           |
| `new-dispositivos.nuvemax.srv.br`              | Interface do Traccar         |

O TLS é emitido automaticamente pelo Traefik (HTTP-01) assim que o DNS
resolver. Sem DNS, os hosts respondem 404 e o certificado fica pendente.

O frontend **não** é apontado para esta VPS: `new-webraster.nuvemax.srv.br`
(transição) e `webraster.nuvemax.srv.br` (definitivo) vão para o Vercel. Ambos
constam em `CORS_ALLOWED_ORIGINS` e `SANCTUM_STATEFUL_DOMAINS` no `.env.prod`.

No cutover, os domínios definitivos (`webraster-api.nuvemax.srv.br`,
`webraster-ws.nuvemax.srv.br`, `dispositivos.nuvemax.srv.br`) podem ser
adicionados trocando as variáveis `API_DOMAIN` / `WS_DOMAIN` / `TRACCAR_DOMAIN`
e repetindo o `up -d`.

## Estrutura no servidor

```
/opt/nuvemax/panel/            # clone do repo (git@github.com:lucaskaiut/webraster)
├── .env.prod                  # segredos do painel (não versionado)
├── docker-compose.prod.yml
├── docker/gateway/.env        # ACME_EMAIL (não versionado)
├── docker/traccar/.env        # segredos do Traccar (não versionado)
└── …
/opt/nuvemax/backups/          # dumps diários (cron)
```

## Instalação inicial (uma vez)

```bash
# 1) Clone (repo público)
git clone https://github.com/lucaskaiut/webraster.git /opt/nuvemax/panel
cd /opt/nuvemax/panel

# 2) Base do servidor: Docker, swap 4G, UFW (22/80/443 + portas GPS),
#    fail2ban, unattended-upgrades e redes nuvemax_edge/nuvemax_shared
bash scripts/install-server.sh

# 3) Segredos
cp .env.prod.example .env.prod                 # preencha (copie do contabo: APP_KEY, REVERB_*, …)
cp docker/gateway/.env.example docker/gateway/.env
cp docker/traccar/.env.example docker/traccar/.env

# 4) Gateway (Traefik)
docker compose --env-file .env -f docker/gateway/docker-compose.yml up -d

# 5) Painel (build + subida + base do Traccar criada automaticamente)
bash scripts/deploy.sh

# 6) (Opcional, somente nesta instância nova) dados de demonstração:
docker compose --env-file .env.prod -f docker-compose.prod.yml --profile a exec app-a \
  php artisan db:seed --force

# 7) Cron do backup (root)
crontab -e
# 30 3 * * * /opt/nuvemax/panel/scripts/backup-databases.sh >> /var/log/nuvemax-backup.log 2>&1
```

## Traccar

A imagem tem um decoder **EasyTrack CC** corrigido, que não existe no Docker
Hub. Ela é transferida do contabo uma vez:

```bash
# No seu computador (bridge entre os dois servidores):
ssh contabo 'docker save local/traccar:6.15.3-easytrack-cc | gzip -1' \
  | ssh nuvemax 'gunzip | docker load'

# Subir (depois do painel e do MySQL):
cd /opt/nuvemax/panel
docker compose --env-file docker/traccar/.env -f docker/traccar/docker-compose.yml up -d
```

- Config por variáveis de ambiente (`CONFIG_USE_ENVIRONMENT_VARIABLES=true`);
  nada de `traccar.xml` em disco.
- Primeiro acesso: abra `https://new-dispositivos.nuvemax.srv.br` e registre o
  usuário administrador (a base está vazia).
- O webhook de posições vai para `http://webraster-app/api/webhooks/traccar`
  (rede interna) com o header `X-Traccar-Webhook-Token` — o mesmo valor de
  `TRACCAR_WEBHOOK_SECRET` no `.env.prod`.

## Deploy (blue-green, sem downtime)

O painel roda em **dois slots** (`app-a` / `app-b`), com os mesmos labels do
Traefik. O script sobe o slot inativo com a imagem nova, espera o healthcheck e
a checagem `https://<API_DOMAIN>/up` via Traefik e só então remove o slot
antigo — o tráfego HTTP nunca fica sem backend. Se qualquer etapa falhar, o
slot antigo permanece no ar e o deploy aborta sem trocar tráfego.

Antes de remover o slot antigo, ele é **drenado**: o script remove a flag
`public/healthz.txt` (servida em `/healthz`, usada no healthcheck do Traefik),
espera o Traefik marcá-lo como DOWN (~10s) e só então para o container —
nenhuma requisição nova é roteada para ele nesse intervalo e as requisições em
andamento terminam normalmente (por isso o endpoint `/healthz` é separado do
`/up` da aplicação).

```bash
cd /opt/nuvemax/panel && bash scripts/deploy.sh
```

- `WORKER_REPLICAS=3 bash scripts/deploy.sh` ajusta a escala do worker.
- MySQL nunca é recriado; a base `traccar` é garantida em toda execução.
- Worker/scheduler/reverb são recriados depois da troca: a fila pausa alguns
  segundos e o WebSocket reconecta sozinho (queda breve; o HTTP não cai).
- Migrações rodam no boot do slot novo, antes de ele receber tráfego. Evite
  migrações destrutivas em um único deploy (use expand/contract) para não
  quebrar o slot antigo na janela de sobreposição.
- A stack do **Traccar não entra no fluxo automático**; mudanças nela são
  aplicadas manualmente com o `up -d` da seção do Traccar.

## Deploy automático (GitHub Actions)

Push na `main` tocando `api/**`, `docker-compose.prod.yml`, `docker/prod/**`
ou `scripts/**` dispara o workflow `.github/workflows/deploy-nuvemax.yml`:
runner da Actions → SSH na VPS (chave restrita que só executa
`scripts/ci-deploy.sh`) → `scripts/deploy.sh`. O grupo `concurrency` do
workflow serializa as execuções (nunca dois deploys em paralelo), e o
`flock` no script é a segunda barreira.

Secrets no repositório (Settings → Secrets and variables → Actions):

| Secret            | Valor                                    |
|-------------------|------------------------------------------|
| `NUVEMAX_HOST`    | `185.194.219.192`                        |
| `NUVEMAX_SSH_KEY` | chave privada de CI (gerada na VPS)      |

A chave de CI é restrita na `authorized_keys` (`command="…/ci-deploy.sh",restrict`):
mesmo que vaze, só consegue rodar o deploy. Para ver a chave privada e colar no
GitHub: `ssh nuvemax 'cat /root/.ssh/nuvemax-ci-deploy'`.

Rollback: `git revert <commit>` + push (dispara novo deploy) — ou re-taguear a
imagem anterior (`docker tag local/webraster-api:<sha> local/webraster-api:latest`)
e rodar `bash scripts/deploy.sh`.

## Cutover (quando decidir aposentar o contabo)

1. **Painel — migrar base**
   ```bash
   # No contabo: dump da base webraster (stack shared)
   ssh contabo 'docker exec infra_shared_mysql sh -c "MYSQL_PWD=\$MYSQL_ROOT_PASSWORD mysqldump -uroot webraster" | gzip -1' \
     > /tmp/webraster.sql.gz
   # Na nuvemax: importar
   gunzip -c /tmp/webraster.sql.gz | docker compose --env-file .env.prod -f docker-compose.prod.yml \
     exec -T -e MYSQL_PWD="$(grep ^MYSQL_ROOT_PASSWORD= .env.prod | cut -d= -f2-)" mysql mysql -uroot webraster
   ```
2. **Traccar — migrar base**: parar o serviço no contabo, dump/restore da base
   `traccar` (mesmo padrão acima, com a base `traccar`), depois ajustar no
   `.env` do Traccar: `TRACCAR_DOMAIN=dispositivos.nuvemax.srv.br`,
   `WEB_URL=https://dispositivos.nuvemax.srv.br` e no `.env.prod`:
   `TRACCAR_SERVER_DNS=dispositivos.nuvemax.srv.br`.
3. **DNS**: apontar `webraster-api`, `webraster-ws` e `dispositivos` para
   `185.194.219.192` e ajustar `API_DOMAIN`/`WS_DOMAIN` no `.env.prod`
   (`webraster-api.nuvemax.srv.br` etc.) + `bash scripts/deploy.sh`.
4. **Frontend**: atualizar as variáveis `VITE_*` (API e Reverb) e rebuildar.
5. **Contabo**: escalar as stacks `webraster`/`traccar` para 0 réplicas.

## Notas

- `TRUSTED_PROXIES=172.31.0.0/24` corresponde à subnet fixa da rede
  `nuvemax_edge` (Traefik). Se a rede for recriada com outra subnet, ajuste.
- Enquanto o contabo operar, esta instância é uma **pré-produção**: o Traccar
  está vazio e o painel usa dados de demonstração (passo 6). Chame o Traccar
  pelo nome interno `http://traccar:8082`; chame o painel de `http://webraster-app`.
- Reverb roda em instância única; ao escalar, lembre que conexões WebSocket
  não são balanceadas por sticky session no Traefik por padrão.
- Backups: `scripts/backup-databases.sh` (retenção 14 dias). Guarde cópias
  fora do servidor (ex.: `rsync`/`rclone` para destino externo).
