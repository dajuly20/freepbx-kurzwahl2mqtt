<?php
$module  = \FreePBX::Kurzwahl2mqtt();
$entries = $module->getEntries();
$baseUrl = '?display=kurzwahl2mqtt';
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h4>Kurzwahl2MQTT — Speed Dial Actions</h4>

      <div class="mb-3">
        <a href="<?= $baseUrl ?>&view=form" class="btn btn-primary btn-sm">+ Add Entry</a>
        <a href="<?= $baseUrl ?>&view=settings" class="btn btn-secondary btn-sm ml-2">Settings</a>
        <button id="btn-apply" class="btn btn-success btn-sm ml-2">Apply Config</button>
      </div>

      <table class="table table-sm table-bordered">
        <thead class="thead-dark">
          <tr>
            <th>Code</th>
            <th>Label</th>
            <th>Action</th>
            <th>Target</th>
            <th>Announcement</th>
            <th>Enabled</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($entries)): ?>
          <tr><td colspan="7" class="text-center text-muted">No entries yet. Click "+ Add Entry" to create one.</td></tr>
        <?php else: ?>
          <?php foreach ($entries as $e): ?>
          <tr>
            <td><code><?= htmlspecialchars($e['code']) ?></code></td>
            <td><?= htmlspecialchars($e['label']) ?></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($e['action_type']) ?></span></td>
            <td class="text-truncate" style="max-width:200px"><?= htmlspecialchars($e['action_target']) ?></td>
            <td><?= htmlspecialchars($e['announce_type']) ?><?= $e['announce_value'] ? ': <em>' . htmlspecialchars($e['announce_value']) . '</em>' : '' ?></td>
            <td><?= $e['enabled'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></td>
            <td>
              <a href="<?= $baseUrl ?>&view=form&id=<?= (int)$e['id'] ?>" class="btn btn-xs btn-default">Edit</a>
              <button class="btn btn-xs btn-danger btn-delete" data-id="<?= (int)$e['id'] ?>">Delete</button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="modules/kurzwahl2mqtt/assets/js/kurzwahl2mqtt.js"></script>
