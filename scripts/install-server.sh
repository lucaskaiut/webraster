#!/usr/bin/env bash
#
# Setup base da VPS nuvemax (Ubuntu 24.04): Docker, swap, firewall (UFW),
# fail2ban, atualizações automáticas e redes externas do painel.
#
# Uso (como root, na raiz do clone do repo):
#   bash scripts/install-server.sh

set -Eeuo pipefail

log() { echo "[install] $*"; }

# ---------------------------------------------------------------------------
# Pacotes base
# ---------------------------------------------------------------------------
if ! command -v git >/dev/null 2>&1 || ! command -v curl >/dev/null 2>&1; then
  log "Instalando pacotes base (git, curl)..."
  DEBIAN_FRONTEND=noninteractive apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq git curl ca-certificates >/dev/null
fi

# ---------------------------------------------------------------------------
# Docker Engine + Compose plugin
# ---------------------------------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
  log "Instalando Docker..."
  curl -fsSL https://get.docker.com | sh
  systemctl enable --now docker
else
  log "Docker já instalado: $(docker --version)"
fi

# ---------------------------------------------------------------------------
# Swap (4 GB — o host tem 8 GB e nenhum swap)
# ---------------------------------------------------------------------------
if [ ! -f /swapfile ]; then
  log "Criando swap de 4 GB..."
  fallocate -l 4G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile >/dev/null
  swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
  sysctl -w vm.swappiness=10 >/dev/null
  grep -q '^vm.swappiness' /etc/sysctl.conf || echo 'vm.swappiness=10' >> /etc/sysctl.conf
else
  log "Swap já configurada"
fi

# ---------------------------------------------------------------------------
# fail2ban + atualizações automáticas de segurança
# ---------------------------------------------------------------------------
log "Instalando fail2ban e unattended-upgrades..."
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq fail2ban unattended-upgrades >/dev/null
printf 'y\n' | dpkg-reconfigure -f noninteractive unattended-upgrades >/dev/null 2>&1 || true
cat > /etc/fail2ban/jail.d/sshd.local <<'EOF'
[sshd]
enabled = true
backend = systemd
maxretry = 5
bantime = 1h
findtime = 10m
EOF
systemctl enable --now fail2ban >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------
# UFW — somente o necessário
# ---------------------------------------------------------------------------
log "Configurando UFW..."
ufw allow 22/tcp >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
# Protocolos GPS do Traccar publicados direto no host
for port in 5011 5023 5130 5131 5135; do
  ufw allow "${port}/tcp" >/dev/null
done
ufw --force enable >/dev/null
ufw status

# ---------------------------------------------------------------------------
# Redes externas (subnets fixas — usadas em TRUSTED_PROXIES e pelo Traefik)
# ---------------------------------------------------------------------------
for entry in "nuvemax_edge 172.31.0.0/24" "nuvemax_shared 172.31.1.0/24"; do
  set -- $entry
  if ! docker network inspect "$1" >/dev/null 2>&1; then
    log "Criando rede $1 ($2)..."
    docker network create --subnet "$2" "$1" >/dev/null
  else
    log "Rede $1 já existe"
  fi
done

log "Concluído."
