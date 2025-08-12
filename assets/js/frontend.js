/* global WIRData, jQuery */
(function ($) {
  'use strict';
  const $d = $(document);
  const el = (id) => document.getElementById(id);

  function openModal() {
    fillTopics();
    el('wir-gdpr-label').textContent = WIRData.gdpr || '';
    el('wir-name').value = WIRData.user || '';
    el('wir-email').value = WIRData.email || '';
    el('wir-message').value = '';
    el('wir-gdpr').checked = false;
    el('wir-status').textContent = '';
    el('wir-modal').style.display = 'flex';
    el('wir-backdrop').style.display = 'block';
  }
  function closeModal() {
    el('wir-modal').style.display = 'none';
    el('wir-backdrop').style.display = 'none';
  }
  function fillTopics() {
    const s = el('wir-topic');
    s.innerHTML = '';
    (WIRData.topics || []).forEach((t) => {
      const o = document.createElement('option');
      o.value = t;
      o.textContent = t;
      s.appendChild(o);
    });
  }

  $d.on('click', '#wir-open', openModal);
  $d.on('click', '#wir-cancel, #wir-backdrop', closeModal);
  $d.on('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });

  $d.on('click', '#wir-send', function () {
    const status = el('wir-status');
    status.textContent = '';
    const data = {
      action: 'wir_submit',
      nonce: WIRData.nonce,
      pid: WIRData.pid,
      name: el('wir-name').value.trim(),
      email: el('wir-email').value.trim(),
      topic: el('wir-topic').value,
      message: el('wir-message').value.trim(),
      gdpr: el('wir-gdpr').checked ? '1' : '0',
    };
    if (!data.name || !data.email || !data.message) {
      status.textContent = WIRData.i18n_required || 'Please fill required fields.';
      return;
    }
    if (data.gdpr !== '1') {
      status.textContent = WIRData.i18n_consent || 'Please confirm consent.';
      return;
    }

    $.post(WIRData.ajax, data, function (res) {
      if (res && res.success) {
        status.textContent = WIRData.i18n_sent || 'Request sent. Thank you!';
        setTimeout(closeModal, 900);
      } else {
        status.textContent = (res && res.data) || 'Something went wrong.';
      }
    }).fail(function () {
      status.textContent = 'Network error. Please try again.';
    });
  });

  // Apply button text.
  $(function () {
    const $btn = $('#wir-open');
    if ($btn.length && WIRData.button) $btn.find('.wir-label').text(WIRData.button);
  });
})(jQuery);
