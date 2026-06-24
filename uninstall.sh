#!/bin/bash
# kurzwahl2mqtt uninstaller

set -euo pipefail

MODULE_DIR="/var/www/html/admin/modules/kurzwahl2mqtt"
AGI_DIR="/var/lib/asterisk/agi-bin"
ASTERISK_CONF_DIR="/etc/asterisk"
CUSTOM_CONF="${ASTERISK_CONF_DIR}/extensions_custom.conf"
INCLUDE_LINE="#include kurzwahl2mqtt_dialplan.conf"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
ok()   { echo -e "${GREEN}  ✓${NC} $*"; }
warn() { echo -e "${YELLOW}  !${NC} $*"; }
err()  { echo -e "${RED}  ✗${NC} $*"; exit 1; }

echo ""
echo "=== kurzwahl2mqtt Uninstaller ==="
echo ""

[[ $EUID -eq 0 ]] || err "Run as root: sudo $0"

# ── FreePBX unregister (triggers PHP uninstall() — drops DB tables, removes conf files) ──
echo "Unregistering module…"
if fwconsole ma uninstall kurzwahl2mqtt 2>&1 | grep -qiE "error|exception|unable|not installed"; then
    warn "'fwconsole ma uninstall' reported an issue — continuing cleanup"
fi
fwconsole ma remove kurzwahl2mqtt 2>&1 || warn "fwconsole ma remove had issues — continuing"
ok "Module unregistered from FreePBX"

# ── Remove module directory ───────────────────────────────────────────────────
if [[ -d "$MODULE_DIR" ]]; then
    rm -rf "$MODULE_DIR"
    ok "Removed ${MODULE_DIR}"
else
    warn "Module directory not found (already removed?): ${MODULE_DIR}"
fi

# ── Remove AGI script ─────────────────────────────────────────────────────────
AGI_SCRIPT="${AGI_DIR}/kurzwahl2mqtt.sh"
if [[ -f "$AGI_SCRIPT" ]]; then
    rm -f "$AGI_SCRIPT"
    ok "Removed ${AGI_SCRIPT}"
else
    warn "AGI script not found (already removed?): ${AGI_SCRIPT}"
fi

# ── Remove include line from extensions_custom.conf ──────────────────────────
if [[ -f "$CUSTOM_CONF" ]] && grep -qF "$INCLUDE_LINE" "$CUSTOM_CONF"; then
    # Remove the line (and any preceding blank line that was added with it)
    sed -i "\|^${INCLUDE_LINE}$|d" "$CUSTOM_CONF"
    ok "Removed include line from extensions_custom.conf"
else
    warn "Include line not found in extensions_custom.conf (already removed?)"
fi

# ── Reload Asterisk dialplan ──────────────────────────────────────────────────
echo "Reloading FreePBX…"
fwconsole chown --quiet 2>/dev/null || true
fwconsole reload 2>&1 | tail -3
ok "FreePBX reloaded"

echo ""
echo -e "${GREEN}Done!${NC} kurzwahl2mqtt has been removed."
echo ""
