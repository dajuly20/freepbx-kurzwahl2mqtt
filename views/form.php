<?php
$module  = \FreePBX::Kurzwahl2mqtt();
$id      = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$entry   = $id ? $module->getEntry($id) : [];
$baseUrl = '?display=kurzwahl2mqtt';

$v = fn($f, $d = '') => htmlspecialchars($entry[$f] ?? $d);
?>
<div class="container-fluid">
  <h4><?= $id ? 'Edit' : 'Add' ?> Speed Dial Entry</h4>

  <form id="entry-form" method="post">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Code <small class="text-muted">(digits after prefix)</small></label>
      <div class="col-sm-3">
        <input type="text" class="form-control" name="code" value="<?= $v('code') ?>"
               pattern="[0-9]+" required placeholder="e.g. 6736">
      </div>
    </div>

    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Label</label>
      <div class="col-sm-4">
        <input type="text" class="form-control" name="label" value="<?= $v('label') ?>"
               placeholder="e.g. OPEN Tür">
      </div>
    </div>

    <hr>
    <h6>Action</h6>

    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Action Type</label>
      <div class="col-sm-3">
        <select class="form-control" name="action_type" id="action_type">
          <?php foreach (['mqtt' => 'MQTT Publish', 'http_get' => 'HTTP GET', 'http_post' => 'HTTP POST'] as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= ($entry['action_type'] ?? 'mqtt') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group row">
      <label class="col-sm-2 col-form-label" id="target-label">Topic / URL</label>
      <div class="col-sm-6">
        <input type="text" class="form-control" name="action_target" value="<?= $v('action_target') ?>"
               placeholder="home/door/trigger  or  http://ha.local:8123/api/webhook/xyz">
      </div>
    </div>

    <div class="form-group row" id="row-payload">
      <label class="col-sm-2 col-form-label">Payload / Body</label>
      <div class="col-sm-6">
        <input type="text" class="form-control" name="action_payload" value="<?= $v('action_payload') ?>"
               placeholder="{CODE} or ON or {&quot;state&quot;:&quot;on&quot;}">
        <small class="form-text text-muted"><code>{CODE}</code> is replaced by the dialed digits.</small>
      </div>
    </div>

    <div class="form-group row" id="row-headers">
      <label class="col-sm-2 col-form-label">HTTP Headers <small>(JSON)</small></label>
      <div class="col-sm-6">
        <textarea class="form-control" name="action_headers" rows="2"
                  placeholder='{"Authorization":"Bearer TOKEN","Content-Type":"application/json"}'><?= $v('action_headers') ?></textarea>
      </div>
    </div>

    <div id="row-mqtt-overrides" style="display:none">
      <hr>
      <h6>MQTT Broker Override <small class="text-muted font-weight-normal">(leave blank to use global Settings)</small></h6>

      <div class="form-group row">
        <label class="col-sm-2 col-form-label">Host</label>
        <div class="col-sm-4">
          <input type="text" class="form-control" name="mqtt_host" value="<?= $v('mqtt_host') ?>"
                 placeholder="e.g. 192.168.1.100  (blank = global)">
        </div>
      </div>

      <div class="form-group row">
        <label class="col-sm-2 col-form-label">Port</label>
        <div class="col-sm-2">
          <input type="number" class="form-control" name="mqtt_port" value="<?= $v('mqtt_port') ?>"
                 placeholder="1883">
        </div>
      </div>

      <div class="form-group row">
        <label class="col-sm-2 col-form-label">Username</label>
        <div class="col-sm-3">
          <input type="text" class="form-control" name="mqtt_user" value="<?= $v('mqtt_user') ?>"
                 placeholder="(blank = global)">
        </div>
      </div>

      <div class="form-group row">
        <label class="col-sm-2 col-form-label">Password</label>
        <div class="col-sm-3">
          <input type="password" class="form-control" name="mqtt_pass" value="<?= $v('mqtt_pass') ?>"
                 placeholder="(blank = global)">
        </div>
      </div>
    </div>

    <hr>
    <h6>Announcement</h6>

    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Announce Type</label>
      <div class="col-sm-3">
        <select class="form-control" name="announce_type" id="announce_type">
          <?php foreach (['none' => 'None', 'tts' => 'TTS (flite)', 'file' => 'Sound File'] as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= ($entry['announce_type'] ?? 'none') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group row" id="row-announce-value">
      <label class="col-sm-2 col-form-label" id="announce-value-label">Text / File</label>
      <div class="col-sm-5">
        <input type="text" class="form-control" name="announce_value" value="<?= $v('announce_value') ?>"
               placeholder="TTS: Tür geöffnet  |  File: door-open (without extension)">
        <small class="form-text text-muted" id="announce-hint">Sound files must be in <code>/var/lib/asterisk/sounds/custom/</code></small>
      </div>
    </div>

    <hr>

    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Enabled</label>
      <div class="col-sm-2">
        <select class="form-control" name="enabled">
          <option value="1" <?= ($entry['enabled'] ?? 1) ? 'selected' : '' ?>>Yes</option>
          <option value="0" <?= isset($entry['enabled']) && !$entry['enabled'] ? 'selected' : '' ?>>No</option>
        </select>
      </div>
    </div>

    <div class="form-group row">
      <div class="col-sm-10 offset-sm-2">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="<?= $baseUrl ?>" class="btn btn-secondary ml-2">Cancel</a>
      </div>
    </div>
  </form>
</div>

<script src="assets/js/kurzwahl2mqtt.js"></script>
