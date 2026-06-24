# kurzwahl2mqtt

FreePBX module that turns short dial codes into HTTP/MQTT actions with optional voice announcement.

**Dial `8` + code → fires action → plays announcement → hangs up.**

Example: dial `86736` (= `8` + `OPEN` on keypad) → posts to MQTT topic `home/door/trigger` → flite speaks "Tür wurde geöffnet".

## Features

- Configure any number of speed dial codes via FreePBX GUI
- Action types: **MQTT publish**, **HTTP GET**, **HTTP POST** (with custom headers)
- Announcement types: **TTS via flite**, **pre-recorded sound file**, or **none**
- `{CODE}` placeholder in payload is replaced with the dialed digits
- Configurable prefix (default: `8`), MQTT broker credentials
- Apply Config button regenerates `/etc/asterisk/kurzwahl2mqtt.json` on demand

## Requirements

| Tool | Purpose | Install |
|---|---|---|
| `jq` | Parse config in AGI script | `apt install jq` |
| `mosquitto-clients` | MQTT publish | `apt install mosquitto-clients` |
| `flite` | TTS announcements | `apt install flite` |
| FreePBX 17 | Module framework | — |
| Asterisk 22 | AGI + dialplan | — |

## Installation

```bash
git clone https://github.com/dajuly20/freepbx-kurzwahl2mqtt
cd freepbx-kurzwahl2mqtt
sudo ./install.sh
```

## First Use (step by step)

### 1. Install the module

```bash
git clone https://github.com/dajuly20/freepbx-kurzwahl2mqtt
cd freepbx-kurzwahl2mqtt
sudo ./install.sh
```

The script installs the AGI script, copies the module into FreePBX, and reloads the dialplan. Watch for any errors — `jq`, `mosquitto-clients`, and `flite` must be installed first (see Requirements above).

### 2. Configure the MQTT broker

FreePBX GUI → **Admin → Kurzwahl2MQTT → Settings**

| Field | What to enter |
|---|---|
| **Prefix digit(s)** | The digit callers dial before their code. Default: `8` |
| **Host** | IP or hostname of your MQTT broker (e.g. your Home Assistant host) |
| **Port** | Usually `1883` (or `8883` for TLS) |
| **Username / Password** | Leave empty if your broker has no auth |

Click **Save & Apply**.

> The module uses `mosquitto_pub` to publish — it does **not** include a broker. You need an existing MQTT broker (Mosquitto, Home Assistant's built-in broker, etc.).

### 3. Add your first speed dial entry

FreePBX GUI → **Admin → Kurzwahl2MQTT → + Add Entry**

Example — a door-open button:

| Field | Value |
|---|---|
| **Code** | `6736` (= OPEN on keypad) |
| **Label** | `Haustür öffnen` |
| **Action type** | `mqtt` |
| **Topic** | `home/door/trigger` |
| **Payload** | `{"action":"open","code":"{CODE}"}` |
| **Announcement** | TTS → `Tür wird geöffnet` |
| **Enabled** | ✓ |

Click **Save**, then **Apply Config**.

> `{CODE}` in the payload is replaced with the dialed digits at runtime.

### 4. Reload the dialplan

After the first install (or after changing the prefix), reload Asterisk once:

```bash
asterisk -rx "dialplan reload"
```

Subsequent **Apply Config** clicks in the GUI regenerate the config file — no reload needed for adding/editing entries.

### 5. Test it

Pick up any internal extension and dial **`8`** + your code (e.g. `86736`). You should hear the announcement and see the MQTT message arrive on your broker.

To monitor MQTT traffic:
```bash
mosquitto_sub -h <broker-host> -t '#' -v
```

---

## Usage

1. FreePBX GUI → **Admin → Kurzwahl2MQTT**
2. Click **+ Add Entry**
3. Fill in code, action, and announcement
4. Click **Apply Config** → run `asterisk -rx "dialplan reload"` once
5. Dial `<prefix><code>` from any extension

## Keypad mapping (for vanity codes)

```
A B C → 2    D E F → 3    G H I → 4
J K L → 5    M N O → 6    P Q R S → 7
T U V → 8    W X Y Z → 9
```

`OPEN` = O(6) P(7) E(3) N(6) → `6736` → dial `86736`

## How it works

```
Caller dials 86736
  → Asterisk matches pattern _8. in [from-internal-custom]
  → Calls agi-bin/kurzwahl2mqtt.sh with arg "6736"
  → AGI reads /etc/asterisk/kurzwahl2mqtt.json
  → Fires configured action (MQTT/HTTP)
  → Plays announcement (TTS/file/none)
  → Hangs up
```

## File layout

```
/var/www/html/admin/modules/kurzwahl2mqtt/   FreePBX module
/var/lib/asterisk/agi-bin/kurzwahl2mqtt.sh   AGI runtime
/etc/asterisk/kurzwahl2mqtt.json             Generated config
/etc/asterisk/kurzwahl2mqtt_dialplan.conf    Generated dialplan
```

## License

GPLv3
