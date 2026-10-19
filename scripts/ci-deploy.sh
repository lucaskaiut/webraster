#!/usr/bin/env bash
#
# Entrypoint restrito do deploy automático (GitHub Actions).
# Esta é a única ação permitida pela chave de CI (command= na authorized_keys
# do usuário root na VPS nuvemax).
#
# Garante o código mais recente antes de executar o deploy (o arquivo
# scripts/deploy.sh é lido do disco pelo novo processo, já atualizado).

set -Eeuo pipefail
cd /opt/nuvemax/panel
git pull -q --ff-only
exec bash scripts/deploy.sh
