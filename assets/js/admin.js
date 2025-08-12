/* global jQuery, WIRAdmin */
(function ($) {
  'use strict';
  const $doc = $(document);
  let currentId = 0;

  // Height var (keep)
  function setMailboxHeightVar() {
    var grid = document.querySelector('.wir-mailbox');
    if (!grid) return;
    var r = grid.getBoundingClientRect();
    var t = Math.max(120, r.top);
    document.documentElement.style.setProperty('--wir-top', t + 40 + 'px');
  }
  document.addEventListener('DOMContentLoaded', setMailboxHeightVar);
  window.addEventListener('resize', setMailboxHeightVar);
  document.addEventListener('scroll', setMailboxHeightVar, true);

  // Helpers
  function badge(status) {
    const map = { open: '#2563eb', replied: '#059669', closed: '#6b7280' };
    const color = map[status] || '#2563eb';
    return (
      '<span class="wir-badge" style="background:' +
      color +
      '1a;color:' +
      color +
      '">' +
      status +
      '</span>'
    );
  }
  function renderThread(items) {
    const $t = $('.wir-thread').empty();
    if (!Array.isArray(items) || !items.length) {
      $t.append('<div class="wir-empty">' + WIRAdmin.i18n.no_messages + '</div>');
      return;
    }
    items.forEach((m) => {
      const who = m.type === 'out' ? 'admin' : 'user';
      const status = m.status ? '<div class="wir-msg-status">' + m.status + '</div>' : '';
      const ts = m.time ? new Date(m.time * 1000).toLocaleString() : '';
      $t.append(
        '<div class="wir-msg is-' +
          who +
          '">' +
          '<div class="wir-msg-head">' +
          (who === 'admin' ? 'You' : 'User') +
          ' · ' +
          ts +
          '</div>' +
          '<div class="wir-msg-body">' +
          $('<div/>')
            .text(m.message || '')
            .html() +
          '</div>' +
          status +
          '</div>'
      );
    });
  }

  function fillPreviewFromServer(id) {
    $.post(
      WIRAdmin.ajax,
      { action: 'wir_get_header', nonce: WIRAdmin.nonce, request_id: id },
      function (res) {
        if (res && res.success) {
          const h = res.data;
          $('.wir-preview-empty').hide();
          $('.wir-preview-body').show();
          $('.wir-preview-name').text(h.name || 'Guest');
          $('.wir-preview-email')
            .attr('href', h.email ? 'mailto:' + h.email : '#')
            .text(h.email || '');
          $('.wir-preview-topic')
            .text(h.topic || '')
            .toggle(!!h.topic);
          $('.wir-preview-object')
            .attr('href', h.product_url || '#')
            .text(h.product || '');
          $('.wir-preview-status-badge').html(badge(h.status));
          $('.wir-preview-assignee').text(WIRAdmin.i18n.assignee + ': ' + (h.assignee || '—'));
          $('.wir-preview-content').text(h.content || '');
        }
      }
    );

    // thread
    $.post(
      WIRAdmin.ajax,
      { action: 'wir_get_thread', nonce: WIRAdmin.nonce, request_id: id },
      function (res) {
        if (res && res.success) {
          renderThread(res.data.items || []);
        }
      }
    );
  }

  // Select item
  $doc.on('click', '.wir-item', function () {
    $('.wir-item').removeClass('is-active');
    $(this).addClass('is-active');
    currentId = parseInt($(this).data('id'), 10);
    fillPreviewFromServer(currentId);
  });

  // Send reply
  $doc.on('click', '#wir-send-reply', function (e) {
    e.preventDefault();
    if (!currentId) return;
    const $btn = $(this),
      $status = $('.wir-reply-status');
    const msg = $('#wir-reply-text').val().trim();
    if (!msg) {
      $status.css('color', '#b91c1c').text(WIRAdmin.i18n.required);
      return;
    }
    $btn.prop('disabled', true);
    $status.text('');

    $.post(
      WIRAdmin.ajax,
      { action: 'wir_admin_reply', nonce: WIRAdmin.nonce, request_id: currentId, message: msg },
      function (res) {
        if (res && res.success) {
          $status.css('color', '#059669').text(WIRAdmin.i18n.reply_sent);
          $('#wir-reply-text').val('');
          renderThread(res.data.items || []);
          // update list badge to replied
          $('.wir-item.is-active').attr('data-status', 'replied');
        } else {
          $status.css('color', '#b91c1c').text((res && res.data) || WIRAdmin.i18n.error);
        }
      }
    )
      .fail(function () {
        $status.css('color', '#b91c1c').text(WIRAdmin.i18n.error);
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  // Save note
  $doc.on('click', '#wir-save-note', function (e) {
    e.preventDefault();
    if (!currentId) return;
    const $btn = $(this),
      $nStatus = $('.wir-note-status'),
      note = $('#wir-note-text').val().trim();
    if (!note) {
      $nStatus.css('color', '#b91c1c').text(WIRAdmin.i18n.required);
      return;
    }
    $btn.prop('disabled', true);
    $nStatus.text('');
    $.post(
      WIRAdmin.ajax,
      { action: 'wir_save_note', nonce: WIRAdmin.nonce, request_id: currentId, note: note },
      function (res) {
        if (res && res.success) {
          $nStatus.css('color', '#059669').text(WIRAdmin.i18n.saved);
          renderThread(res.data.items || []);
          $('#wir-note-text').val('');
        } else {
          $nStatus.css('color', '#b91c1c').text((res && res.data) || WIRAdmin.i18n.error);
        }
      }
    ).always(function () {
      $btn.prop('disabled', false);
    });
  });

  // Toggle status
  $doc.on('click', '#wir-toggle-status', function (e) {
    e.preventDefault();
    if (!currentId) return;
    $.post(
      WIRAdmin.ajax,
      { action: 'wir_toggle_status', nonce: WIRAdmin.nonce, request_id: currentId },
      function (res) {
        if (res && res.success) {
          $('.wir-preview-status-badge').html(badge(res.data.status));
          $('.wir-item.is-active').attr('data-status', res.data.status);
        }
      }
    );
  });

  // Assign to me
  $doc.on('click', '#wir-assign-me', function (e) {
    e.preventDefault();
    if (!currentId) return;
    $.post(
      WIRAdmin.ajax,
      { action: 'wir_assign_me', nonce: WIRAdmin.nonce, request_id: currentId },
      function (res) {
        if (res && res.success) {
          $('.wir-preview-assignee').text(WIRAdmin.i18n.assignee + ': ' + res.data.name);
          $('.wir-item.is-active').attr('data-assignee_name', res.data.name);
        }
      }
    );
  });
})(jQuery);
