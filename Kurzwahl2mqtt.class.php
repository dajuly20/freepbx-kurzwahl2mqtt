<?php
class Kurzwahl2mqtt extends FreePBX_Helpers implements BMO {

    public function install() {
        $this->FreePBX->Database->exec("CREATE TABLE IF NOT EXISTS kurzwahl2mqtt_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL,
            label VARCHAR(100) DEFAULT '',
            action_type ENUM('http_get','http_post','mqtt') NOT NULL DEFAULT 'mqtt',
            action_target TEXT,
            action_payload TEXT,
            action_headers TEXT,
            mqtt_host VARCHAR(255) DEFAULT NULL,
            mqtt_port SMALLINT UNSIGNED DEFAULT NULL,
            mqtt_user VARCHAR(100) DEFAULT NULL,
            mqtt_pass VARCHAR(100) DEFAULT NULL,
            announce_type ENUM('none','tts','file') NOT NULL DEFAULT 'none',
            announce_value TEXT,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY unique_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->FreePBX->Database->exec("CREATE TABLE IF NOT EXISTS kurzwahl2mqtt_settings (
            `key` VARCHAR(50) NOT NULL PRIMARY KEY,
            `value` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $defaults = [
            'prefix'    => '8',
            'mqtt_host' => 'localhost',
            'mqtt_port' => '1883',
            'mqtt_user' => '',
            'mqtt_pass' => '',
        ];
        $sth = $this->FreePBX->Database->prepare(
            "INSERT IGNORE INTO kurzwahl2mqtt_settings (`key`, `value`) VALUES (?, ?)"
        );
        foreach ($defaults as $k => $v) {
            $sth->execute([$k, $v]);
        }
    }

    public function uninstall() {
        $this->FreePBX->Database->exec("DROP TABLE IF EXISTS kurzwahl2mqtt_entries");
        $this->FreePBX->Database->exec("DROP TABLE IF EXISTS kurzwahl2mqtt_settings");
        @unlink('/etc/asterisk/kurzwahl2mqtt.json');
        @unlink('/etc/asterisk/kurzwahl2mqtt_dialplan.conf');
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function getEntries() {
        return $this->FreePBX->Database->query(
            "SELECT * FROM kurzwahl2mqtt_entries ORDER BY code"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEntry($id) {
        $sth = $this->FreePBX->Database->prepare(
            "SELECT * FROM kurzwahl2mqtt_entries WHERE id = ?"
        );
        $sth->execute([$id]);
        return $sth->fetch(PDO::FETCH_ASSOC);
    }

    public function saveEntry($data) {
        $fields = ['code','label','action_type','action_target','action_payload',
                   'action_headers','mqtt_host','mqtt_port','mqtt_user','mqtt_pass',
                   'announce_type','announce_value','enabled'];
        $values = array_map(fn($f) => $data[$f] ?? '', $fields);

        if (!empty($data['id'])) {
            $set = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sth = $this->FreePBX->Database->prepare(
                "UPDATE kurzwahl2mqtt_entries SET $set WHERE id = ?"
            );
            $sth->execute([...$values, $data['id']]);
        } else {
            $cols = implode(', ', $fields);
            $ph   = implode(', ', array_fill(0, count($fields), '?'));
            $sth  = $this->FreePBX->Database->prepare(
                "INSERT INTO kurzwahl2mqtt_entries ($cols) VALUES ($ph)"
            );
            $sth->execute($values);
        }
    }

    public function deleteEntry($id) {
        $sth = $this->FreePBX->Database->prepare(
            "DELETE FROM kurzwahl2mqtt_entries WHERE id = ?"
        );
        $sth->execute([$id]);
    }

    // ── Settings ─────────────────────────────────────────────────────────────

    public function getSettings() {
        $rows = $this->FreePBX->Database->query(
            "SELECT `key`, `value` FROM kurzwahl2mqtt_settings"
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'value', 'key');
    }

    public function saveSetting($key, $value) {
        $sth = $this->FreePBX->Database->prepare(
            "INSERT INTO kurzwahl2mqtt_settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $sth->execute([$key, $value]);
    }

    // ── Config generation ─────────────────────────────────────────────────────

    public function generateConfig() {
        $settings = $this->getSettings();
        $entries  = $this->getEntries();
        $prefix   = $settings['prefix'] ?? '8';

        $config = [
            'prefix' => $prefix,
            'mqtt'   => [
                'host' => $settings['mqtt_host'] ?? 'localhost',
                'port' => (int)($settings['mqtt_port'] ?? 1883),
                'user' => $settings['mqtt_user'] ?? '',
                'pass' => $settings['mqtt_pass'] ?? '',
            ],
            'entries' => [],
        ];

        foreach ($entries as $e) {
            if (!$e['enabled']) continue;
            $config['entries'][$e['code']] = [
                'label'          => $e['label'],
                'action_type'    => $e['action_type'],
                'action_target'  => $e['action_target'],
                'action_payload' => $e['action_payload'],
                'action_headers' => $e['action_headers']
                    ? json_decode($e['action_headers'], true) : (object)[],
                'mqtt_host'      => $e['mqtt_host'] ?: null,
                'mqtt_port'      => $e['mqtt_port'] ? (int)$e['mqtt_port'] : null,
                'mqtt_user'      => $e['mqtt_user'] ?: null,
                'mqtt_pass'      => $e['mqtt_pass'] ?: null,
                'announce_type'  => $e['announce_type'],
                'announce_value' => $e['announce_value'],
            ];
        }

        file_put_contents(
            '/etc/asterisk/kurzwahl2mqtt.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $dp  = "; Generated by kurzwahl2mqtt — do not edit manually\n";
        $dp .= "[from-internal-custom]\n";
        $dp .= "exten => _{$prefix}.,1,Answer()\n";
        $dp .= "exten => _{$prefix}.,n,AGI(kurzwahl2mqtt.sh,\${EXTEN:1})\n";
        $dp .= "exten => _{$prefix}.,n,Hangup()\n";

        file_put_contents('/etc/asterisk/kurzwahl2mqtt_dialplan.conf', $dp);

        return true;
    }

    public function doConfigPageInit($page) {}

    // Called by FreePBX Apply Config
    public function genConfig() {
        $this->generateConfig();
    }

    // ── AJAX ─────────────────────────────────────────────────────────────────

    public function ajaxRequest($req, &$setting) {
        return true;
    }

    public function ajaxHandler() {
        $cmd = $_REQUEST['command'] ?? '';
        switch ($cmd) {
            case 'save':
                $this->saveEntry($_POST);
                return ['status' => true, 'message' => 'Saved'];

            case 'delete':
                $this->deleteEntry($_POST['id'] ?? 0);
                return ['status' => true, 'message' => 'Deleted'];

            case 'saveSettings':
                foreach (['prefix','mqtt_host','mqtt_port','mqtt_user','mqtt_pass'] as $key) {
                    if (array_key_exists($key, $_POST)) {
                        $this->saveSetting($key, $_POST[$key]);
                    }
                }
                $this->generateConfig();
                return ['status' => true, 'message' => 'Settings saved'];

            case 'applyConfig':
                $ok = $this->generateConfig();
                return ['status' => $ok, 'message' => $ok ? 'Config generated' : 'Error'];
        }
        return ['status' => false, 'message' => 'Unknown command'];
    }
}
