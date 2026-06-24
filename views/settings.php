<?php
$module   = \FreePBX::Kurzwahl2mqtt();
$settings = $module->getSettings();
$baseUrl  = '?display=kurzwahl2mqtt';
$v = fn($k, $d = '') => htmlspecialchars($settings[$k] ?? $d);
?>
<div class="container-fluid">
  <h4>Kurzwahl2MQTT — Settings</h4>

  <form id="settings-form">

    <h6>Dial Prefix</h6>
    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Prefix digit(s)</label>
      <div class="col-sm-2">
        <input type="text" class="form-control" name="prefix" value="<?= $v('prefix', '8') ?>"
               pattern="[0-9*#]+" required>
        <small class="form-text text-muted">Dial <strong><?= $v('prefix', '8') ?>6736</strong> → triggers code <strong>6736</strong></small>
      </div>
    </div>

    <hr>
    <h6>MQTT Broker (used for MQTT actions)</h6>

    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Host</label>
      <div class="col-sm-4">
        <input type="text" class="form-control" name="mqtt_host" value="<?= $v('mqtt_host', 'localhost') ?>">
      </div>
    </div>

    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Port</label>
      <div class="col-sm-2">
        <input type="number" class="form-control" name="mqtt_port" value="<?= $v('mqtt_port', '1883') ?>">
      </div>
    </div>

    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Username <small>(optional)</small></label>
      <div class="col-sm-3">
        <input type="text" class="form-control" name="mqtt_user" value="<?= $v('mqtt_user') ?>">
      </div>
    </div>

    <div class="form-group row">
      <label class="col-sm-2 col-form-label">Password <small>(optional)</small></label>
      <div class="col-sm-3">
        <input type="password" class="form-control" name="mqtt_pass" value="<?= $v('mqtt_pass') ?>">
      </div>
    </div>

    <div class="form-group row">
      <div class="col-sm-10 offset-sm-2">
        <button type="submit" class="btn btn-primary">Save &amp; Apply</button>
        <a href="<?= $baseUrl ?>" class="btn btn-secondary ml-2">Back</a>
      </div>
    </div>

  </form>
</div>

<script src="modules/kurzwahl2mqtt/assets/js/kurzwahl2mqtt.js"></script>
