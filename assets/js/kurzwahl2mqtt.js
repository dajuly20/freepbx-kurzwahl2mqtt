/* kurzwahl2mqtt admin JS */
(function ($) {
  'use strict';

  var ajaxUrl = 'ajax.php';

  function showFlash(msg) {
    $('<div id="k2m-flash" class="alert alert-success">')
      .css({ position: 'fixed', top: '70px', right: '24px', zIndex: 9999,
             minWidth: '180px', boxShadow: '0 2px 8px rgba(0,0,0,.25)' })
      .text(msg)
      .appendTo('body');
    setTimeout(function () {
      $('#k2m-flash').fadeOut(300, function () { $(this).remove(); });
    }, 1800);
  }

  function fpbxAjax(command, data, callback) {
    $.post(ajaxUrl, $.extend({ module: 'kurzwahl2mqtt', command: command }, data), callback, 'json')
      .fail(function (xhr) {
        alert('AJAX error (' + xhr.status + '): ' + (xhr.responseText || 'no response'));
      });
  }

  // ── Grid: Delete ──────────────────────────────────────────────────────────
  $(document).on('click', '.btn-delete', function () {
    var id = $(this).data('id');
    if (!confirm('Delete this entry?')) return;
    fpbxAjax('delete', { id: id }, function (res) {
      if (res.status) {
        location.reload();
      } else {
        alert('Error: ' + res.message);
      }
    });
  });

  // ── Grid: Apply Config ────────────────────────────────────────────────────
  $(document).on('click', '#btn-apply', function () {
    var $btn = $(this).prop('disabled', true).text('Applying…');
    fpbxAjax('applyConfig', {}, function (res) {
      $btn.prop('disabled', false).text('Apply Config');
      alert(res.status ? '✓ Config generated. Run: asterisk -rx "dialplan reload"' : 'Error: ' + res.message);
    });
  });

  // ── Form: Save ────────────────────────────────────────────────────────────
  $(document).on('submit', '#entry-form', function (e) {
    e.preventDefault();
    var data = $(this).serializeArray().reduce(function (obj, item) {
      obj[item.name] = item.value;
      return obj;
    }, {});
    fpbxAjax('save', data, function (res) {
      if (res.status) {
        showFlash('Entry saved');
        setTimeout(function () { window.location.href = '?display=kurzwahl2mqtt'; }, 800);
      } else {
        alert('Error: ' + res.message);
      }
    });
  });

  // ── Settings: Save ────────────────────────────────────────────────────────
  $(document).on('submit', '#settings-form', function (e) {
    e.preventDefault();
    var data = $(this).serializeArray().reduce(function (obj, item) {
      obj[item.name] = item.value;
      return obj;
    }, {});
    fpbxAjax('saveSettings', data, function (res) {
      if (res.status) {
        showFlash('Settings saved');
        setTimeout(function () { window.location.href = '?display=kurzwahl2mqtt&view=settings'; }, 800);
      } else {
        alert('Error: ' + res.message);
      }
    });
  });

  // ── Form: show/hide fields based on action_type ───────────────────────────
  function updateActionFields() {
    var type = $('#action_type').val();
    var isHttp = type === 'http_get' || type === 'http_post';
    var isPost = type === 'http_post';

    $('#row-payload').toggle(isHttp || type === 'mqtt');
    $('#row-headers').toggle(isPost);
    $('#target-label').text(type === 'mqtt' ? 'MQTT Topic' : 'URL');

    if (type === 'mqtt') {
      $('[name=action_payload]').attr('placeholder', 'ON  or  {CODE}  or  {"state":"on"}');
    } else {
      $('[name=action_payload]').attr('placeholder', 'POST body (optional)');
    }
  }

  // ── Form: show/hide announce value field ──────────────────────────────────
  function updateAnnounceFields() {
    var type = $('#announce_type').val();
    $('#row-announce-value').toggle(type !== 'none');
    if (type === 'tts') {
      $('#announce-value-label').text('TTS Text');
      $('#announce-hint').hide();
      $('[name=announce_value]').attr('placeholder', 'Tür wurde geöffnet');
    } else if (type === 'file') {
      $('#announce-value-label').text('Sound File');
      $('#announce-hint').show();
      $('[name=announce_value]').attr('placeholder', 'door-open  (without .wav extension)');
    }
  }

  $(document).ready(function () {
    updateActionFields();
    updateAnnounceFields();
    $('#action_type').on('change', updateActionFields);
    $('#announce_type').on('change', updateAnnounceFields);
  });

}(jQuery));
