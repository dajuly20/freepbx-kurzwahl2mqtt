#!/bin/bash
# AGI script for kurzwahl2mqtt
# Called by Asterisk with the dialed code as $1 (digits after prefix)

set -euo pipefail

# Consume AGI headers (mandatory)
while IFS= read -r line && [[ "$line" != "" ]]; do :; done

CODE="${1:-}"
CONFIG="/etc/asterisk/kurzwahl2mqtt.json"
TMPFILE="/tmp/k2m_$$"

log() { logger -t kurzwahl2mqtt "$*"; }

cleanup() { rm -f "${TMPFILE}.wav"; }
trap cleanup EXIT

# ── Sanity checks ─────────────────────────────────────────────────────────────

if [[ -z "$CODE" ]]; then
    log "No code provided"
    echo "HANGUP"
    exit 0
fi

if ! command -v jq &>/dev/null; then
    log "ERROR: jq not installed — cannot parse config"
    echo "HANGUP"
    exit 1
fi

if [[ ! -f "$CONFIG" ]]; then
    log "ERROR: Config not found at $CONFIG — run Apply Config in FreePBX"
    echo "HANGUP"
    exit 1
fi

# ── Look up entry ─────────────────────────────────────────────────────────────

ENTRY=$(jq -c ".entries[\"$CODE\"] // empty" "$CONFIG")

if [[ -z "$ENTRY" ]]; then
    log "No entry found for code: $CODE"
    echo "HANGUP"
    exit 0
fi

log "Executing code: $CODE"

ACTION_TYPE=$(echo "$ENTRY"    | jq -r '.action_type')
ACTION_TARGET=$(echo "$ENTRY"  | jq -r '.action_target // ""')
ACTION_PAYLOAD=$(echo "$ENTRY" | jq -r '.action_payload // ""')
ANNOUNCE_TYPE=$(echo "$ENTRY"  | jq -r '.announce_type')
ANNOUNCE_VALUE=$(echo "$ENTRY" | jq -r '.announce_value // ""')

# Replace {CODE} placeholder in payload
ACTION_PAYLOAD="${ACTION_PAYLOAD//\{CODE\}/$CODE}"

# MQTT broker settings — per-entry overrides fall back to global
MQTT_HOST=$(jq -r --argjson e "$ENTRY" '$e.mqtt_host // .mqtt.host // "localhost"' "$CONFIG")
MQTT_PORT=$(jq -r --argjson e "$ENTRY" '$e.mqtt_port // .mqtt.port // 1883'        "$CONFIG")
MQTT_USER=$(jq -r --argjson e "$ENTRY" '$e.mqtt_user // .mqtt.user // ""'           "$CONFIG")
MQTT_PASS=$(jq -r --argjson e "$ENTRY" '$e.mqtt_pass // .mqtt.pass // ""'           "$CONFIG")

# ── Execute action ─────────────────────────────────────────────────────────────

case "$ACTION_TYPE" in
    http_get)
        curl -sf --max-time 5 "$ACTION_TARGET" &>/dev/null || true
        log "HTTP GET → $ACTION_TARGET"
        ;;

    http_post)
        # Build header args safely from JSON object
        mapfile -t HEADER_ARGS < <(
            echo "$ENTRY" | jq -r '.action_headers // {} | to_entries[] | "\(.key): \(.value)"' 2>/dev/null \
            | while IFS= read -r h; do printf -- '-H\n%s\n' "$h"; done
        )
        curl -sf --max-time 5 -X POST "${HEADER_ARGS[@]}" \
             -d "$ACTION_PAYLOAD" "$ACTION_TARGET" &>/dev/null || true
        log "HTTP POST → $ACTION_TARGET"
        ;;

    mqtt)
        MQTT_CMD=(mosquitto_pub -h "$MQTT_HOST" -p "$MQTT_PORT" -t "$ACTION_TARGET" -m "$ACTION_PAYLOAD")
        [[ -n "$MQTT_USER" ]] && MQTT_CMD+=(-u "$MQTT_USER")
        [[ -n "$MQTT_PASS" ]] && MQTT_CMD+=(-P "$MQTT_PASS")
        "${MQTT_CMD[@]}" || true
        log "MQTT → $ACTION_TARGET = $ACTION_PAYLOAD"
        ;;

    *)
        log "Unknown action_type: $ACTION_TYPE"
        ;;
esac

# ── Announcement ──────────────────────────────────────────────────────────────

case "$ANNOUNCE_TYPE" in
    tts)
        if [[ -n "$ANNOUNCE_VALUE" ]] && command -v flite &>/dev/null; then
            flite -t "$ANNOUNCE_VALUE" -o "${TMPFILE}.wav" &>/dev/null
            echo "STREAM FILE ${TMPFILE} ''"
            read -r _AGI_RESPONSE || true
        fi
        ;;

    file)
        if [[ -n "$ANNOUNCE_VALUE" ]]; then
            echo "STREAM FILE custom/${ANNOUNCE_VALUE} ''"
            read -r _AGI_RESPONSE || true
        fi
        ;;

    none|*)
        ;;
esac

echo "HANGUP"
