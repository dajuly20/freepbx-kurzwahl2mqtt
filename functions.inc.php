<?php
// Legacy wrapper — BMO handles everything via Kurzwahl2mqtt.class.php
// This file is required by FreePBX module loader.

function kurzwahl2mqtt_get_config($engine) {
    // Dialplan is written to /etc/asterisk/kurzwahl2mqtt_dialplan.conf
    // which must be #include'd in extensions_custom.conf (done by install.sh)
}
