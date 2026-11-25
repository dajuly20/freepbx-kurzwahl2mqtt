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
            announce_type ENUM('none','tts','file') NOT NULL DEFAULT 'none',
            announce_value TEXT,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY unique_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->FreePBX->Database->exec("CREATE TABLE IF NOT EXISTS kurzwahl2mqtt_settings (
            `key` VARCHAR(50) NOT NULL PRIMARY KEY,
            `value` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Update älterer Installationen: Vanity-Wort zum Code
        $this->FreePBX->Database->exec(
            "ALTER TABLE kurzwahl2mqtt_entries ADD COLUMN IF NOT EXISTS word VARCHAR(20) DEFAULT '' AFTER code"
        );

        $defaults = [
            'prefix'    => '8',
            'star'      => '1',
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

    // Vanity: Buchstaben auf der Telefontastatur → Ziffern (OPEN → 6736)
    public static function vanityToDigits($s) {
        $map = ['ABC'=>2,'DEF'=>3,'GHI'=>4,'JKL'=>5,'MNO'=>6,'PQRS'=>7,'TUV'=>8,'WXYZ'=>9];
        $out = '';
        foreach (str_split(strtoupper($s)) as $ch) {
            if (ctype_digit($ch)) { $out .= $ch; continue; }
            foreach ($map as $letters => $d) {
                if (strpos($letters, $ch) !== false) { $out .= $d; break; }
            }
        }
        return $out;
    }

    // Wählbare Nummern eines Codes (z. B. 86736 und *86736)
    public function dialStrings($code) {
        $st = $this->getSettings();
        $p  = $st['prefix'] ?? '8';
        $d  = [$p . $code];
        if (($st['star'] ?? '1') === '1') $d[] = '*' . $p . $code;
        return $d;
    }

    // Kollisionen: Nebenstellen, Feature-Codes, andere Kurzwahlen (gleich oder Präfix)
    public function findCollisions($code, $id = 0) {
        $db = $this->FreePBX->Database;
        $problems = [];
        foreach ($this->dialStrings($code) as $dial) {
            $sth = $db->prepare("SELECT extension FROM users WHERE extension = ?");
            $sth->execute([$dial]);
            if ($sth->fetchColumn()) $problems[] = "$dial ist eine Nebenstelle";
            $sth = $db->prepare("SELECT featurename FROM featurecodes WHERE enabled = 1
                                 AND (customcode = ? OR (customcode = '' AND defaultcode = ?))");
            $sth->execute([$dial, $dial]);
            if ($f = $sth->fetchColumn()) $problems[] = "$dial ist der Feature-Code $f";
        }
        foreach ($this->getEntries() as $e) {
            if ((int)$e['id'] === (int)$id) continue;
            if ($e['code'] === $code) $problems[] = "Code $code ist schon vergeben ({$e['label']})";
            elseif (strpos($e['code'], $code) === 0 || strpos($code, $e['code']) === 0)
                $problems[] = "Code $code und {$e['code']} beginnen gleich – der kürzere wird sofort gewählt";
        }
        return $problems;
    }

    public function saveEntry($data) {
        // Code darf als Wort (OPEN) oder Ziffern (6736) eingegeben werden
        $raw = preg_replace('/[^0-9A-Za-z]/', '', $data['code'] ?? '');
        if (preg_match('/[A-Za-z]/', $raw)) {
            $data['word'] = strtoupper($raw);
            $data['code'] = self::vanityToDigits($raw);
        } else {
            $data['code'] = $raw;
            $data['word'] = strtoupper(preg_replace('/[^A-Za-z]/', '', $data['word'] ?? ''));
        }
        if ($data['code'] === '') throw new \Exception('Code fehlt');
        $problems = $this->findCollisions($data['code'], $data['id'] ?? 0);
        if ($problems && empty($data['force'])) throw new \Exception(implode('; ', $problems));

        $fields = ['code','word','label','action_type','action_target','action_payload',
                   'action_headers','announce_type','announce_value','enabled'];
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
                'word'           => $e['word'] ?? '',
                'label'          => $e['label'],
                'action_type'    => $e['action_type'],
                'action_target'  => $e['action_target'],
                'action_payload' => $e['action_payload'],
                'action_headers' => $e['action_headers']
                    ? json_decode($e['action_headers'], true) : (object)[],
                'announce_type'  => $e['announce_type'],
                'announce_value' => $e['announce_value'],
            ];
        }

        file_put_contents(
            '/etc/asterisk/kurzwahl2mqtt.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Exakte Einträge je Code (8<code> und optional *8<code>) – kein Sammelmuster,
        // damit andere Nummern mit gleicher Vorwahl normal weiterlaufen.
        $dp  = "; Generated by kurzwahl2mqtt — do not edit manually\n";
        $dp .= "[from-internal-custom]\n";
        foreach ($entries as $e) {
            if (!$e['enabled']) continue;
            foreach ($this->dialStrings($e['code']) as $dial) {
                $note = $e['word'] ? " ; {$e['word']}" : '';
                $dp .= "exten => {$dial},1,Goto(kurzwahl2mqtt-run,{$e['code']},1){$note}\n";
            }
        }
        $dp .= "\n[kurzwahl2mqtt-run]\n";
        $dp .= "exten => _X.,1,Answer()\n";
        $dp .= " same => n,AGI(kurzwahl2mqtt.sh,\${EXTEN},\${CALLERID(num)})\n";
        $dp .= " same => n,Hangup()\n";

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
        try {
            switch ($cmd) {
                case 'save':
                    $this->saveEntry($_POST);
                    $this->generateConfig();
                    return ['status' => true, 'message' => 'Gespeichert – Apply Config lädt die Anlage neu'];

                case 'delete':
                    $this->deleteEntry($_POST['id'] ?? 0);
                    $this->generateConfig();
                    return ['status' => true, 'message' => 'Deleted'];

                case 'saveSettings':
                    foreach (['prefix','star','mqtt_host','mqtt_port','mqtt_user','mqtt_pass'] as $key) {
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
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
        return ['status' => false, 'message' => 'Unknown command'];
    }
}
