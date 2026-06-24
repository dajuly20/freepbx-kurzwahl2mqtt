#!/bin/bash
# kurzwahl2mqtt installer
# Copies files into FreePBX, installs the module, and patches extensions_custom.conf

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
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
echo "=== kurzwahl2mqtt Installer ==="
echo ""

# ── Root check ────────────────────────────────────────────────────────────────
[[ $EUID -eq 0 ]] || err "Run as root: sudo $0"

# ── Dependency checks ─────────────────────────────────────────────────────────
command -v jq            &>/dev/null || err "jq is required but not installed. Run: apt install jq"
command -v mosquitto_pub &>/dev/null || warn "mosquitto_pub not found — MQTT actions won't work. Run: apt install mosquitto-clients"
command -v flite         &>/dev/null || warn "flite not found — TTS announcements won't work. Run: apt install flite"
command -v fwconsole     &>/dev/null || err "fwconsole not found — is this a FreePBX server?"

# ── Cleanup previous install (safe: all steps skip gracefully if not present) ─
echo "Cleaning up previous install…"
fwconsole ma uninstall kurzwahl2mqtt 2>&1 | grep -iv "not found" || true
fwconsole ma remove    kurzwahl2mqtt 2>&1 | grep -iv "not found" || true
# Clean up any broken setattribute state left by a previous failed install attempt
fwconsole ma uninstall setattribute 2>&1 | grep -iv "not found" | grep -iv "not installed" || true
fwconsole ma remove    setattribute 2>&1 | grep -iv "not found" | grep -iv "not installed" || true
rm -rf "$MODULE_DIR"
rm -f  "${AGI_DIR}/kurzwahl2mqtt.sh"
[[ -f "$CUSTOM_CONF" ]] && sed -i "\|^${INCLUDE_LINE}$|d" "$CUSTOM_CONF" || true
ok "Previous install cleaned up"

# ── AGI script ────────────────────────────────────────────────────────────────
echo "Installing AGI script…"
install -m 755 -o asterisk -g asterisk \
    "${SCRIPT_DIR}/agi-bin/kurzwahl2mqtt.sh" \
    "${AGI_DIR}/kurzwahl2mqtt.sh"
ok "AGI script → ${AGI_DIR}/kurzwahl2mqtt.sh"

# ── Module files ──────────────────────────────────────────────────────────────
echo "Copying module to FreePBX…"
mkdir -p "$MODULE_DIR"
rsync -a --exclude='.git' --exclude='install.sh' --exclude='agi-bin' \
    "${SCRIPT_DIR}/" "${MODULE_DIR}/"
chown -R asterisk:asterisk "$MODULE_DIR"
ok "Module → ${MODULE_DIR}"

# ── Patch extensions_custom.conf ─────────────────────────────────────────────
if [[ -f "$CUSTOM_CONF" ]]; then
    if grep -qF "$INCLUDE_LINE" "$CUSTOM_CONF"; then
        ok "extensions_custom.conf already includes dialplan"
    else
        echo "" >> "$CUSTOM_CONF"
        echo "$INCLUDE_LINE" >> "$CUSTOM_CONF"
        ok "Patched extensions_custom.conf with: $INCLUDE_LINE"
    fi
else
    printf "; FreePBX custom extensions\n%s\n" "$INCLUDE_LINE" > "$CUSTOM_CONF"
    chown asterisk:asterisk "$CUSTOM_CONF"
    ok "Created extensions_custom.conf"
fi

# ── FreePBX module register ───────────────────────────────────────────────────
echo "Registering module…"
fwconsole ma install kurzwahl2mqtt 2>&1
ok "Module installed"

# ── Apply config + reload dialplan ───────────────────────────────────────────
echo "Applying config…"
fwconsole chown --quiet 2>/dev/null || true
fwconsole reload 2>&1 | tail -3
ok "FreePBX reloaded"

echo "Reloading Asterisk dialplan…"
asterisk -rx 'dialplan reload' 2>&1
ok "Dialplan reloaded"

echo ""
echo -e "${GREEN}Done!${NC} Add entries under Admin → Kurzwahl2MQTT, then click 'Apply Config'."
echo ""
