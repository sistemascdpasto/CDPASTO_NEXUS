#!/usr/bin/env bash
# Copia el agente Nexus a cada app (packages/nexus-agent) y actualiza su composer.lock.
# Uso: bash agent/sync-to-apps.sh   (después: commit + push en cada app para desplegar)
set -euo pipefail

SRC="$(cd "$(dirname "$0")" && pwd)"
APPS=(
  "/c/Users/Brian/OneDrive/Escritorio/SistemaGestionAdenar-main/SistemaGestionAdenar-main"
  "/c/Users/Brian/OneDrive/Escritorio/CDPASTO_SistemaGestionEASYOL"
  "/c/Users/Brian/OneDrive/Escritorio/SISTEMA DE TICKETS/sistema_tickets"
  "/c/Users/Brian/OneDrive/Escritorio/Formularios-5-s-main/Formularios-5-s-main"
)

for app in "${APPS[@]}"; do
  echo "== $app"
  rm -rf "$app/packages/nexus-agent"
  mkdir -p "$app/packages/nexus-agent"
  cp -r "$SRC/composer.json" "$SRC/README.md" "$SRC/config" "$SRC/database" "$SRC/routes" "$SRC/src" "$app/packages/nexus-agent/"
  # update: refresca composer.lock si cambió el composer.json del agente; reinstall: recopia el código en vendor/.
  (cd "$app" && composer update cdpasto/nexus-agent --no-interaction --quiet --no-scripts && composer reinstall cdpasto/nexus-agent --no-interaction --quiet)
done
