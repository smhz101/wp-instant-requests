/* global jQuery, WIRAdmin */
(function ($) {
  'use strict';
  const $doc = $(document);
  let currentId = 0;

  // Set mailbox height based on available viewport space
  function setMailboxHeightVar() {
    const grid = document.querySelector('.wir-mailbox');
    if (!grid) return;
    const footer = document.getElementById('wpfooter');
    const footerH = footer ? footer.offsetHeight : 0;
    const h = window.innerHeight - grid.getBoundingClientRect().top - footerH;
    grid.style.height = h + 'px';
  }

    document.addEventListener('DOMContentLoaded', function () {
      setMailboxHeightVar();
    });
  window.addEventListener('resize', setMailboxHeightVar);

  // Helpers
  function badge(status) {
    const colorMap = { open: '#2563eb', replied: '#059669', closed: '#6b7280' };
    const iconMap = { open: 'email-alt', replied: 'yes', closed: 'no-alt' };
    const color = colorMap[status] || '#2563eb';
    const icon = iconMap[status] || 'email-alt';
    return (
      '<span class="wir-badge" style="background:' +
      color +
      '1a;color:' +
      color +
      '"><span class="dashicons dashicons-' +
      icon +
      '"></span>' +
      status +
      '</span>'
    );
  }

  function updateActiveItemStatus(status) {
    const $item = $('.wir-item.is-active');
    if (!$item.length) return;
    $item.attr('data-status', status);
    const $sub = $item.find('.wir-item-sub');
    $sub.find('.wir-status-badge').remove();
    if (status !== 'open') {
      $sub.append($(badge(status)).addClass('wir-status-badge'));
    }
  }

    // placeItem is provided by admin-badge.js

  function renderThread(items) {
    const $t = $('.wir-thread').empty();
    if (!Array.isArray(items) || !items.length) {
      $t.append('<div class="wir-empty">' + WIRAdmin.i18n.no_messages + '</div>');
      return;
    }
    items.forEach((m, i) => {
      const who = m.type === 'out' ? 'admin' : 'user';
      const status = m.status ? '<div class="wir-msg-status">' + m.status + '</div>' : '';
      const ts = m.time ? new Date(m.time * 1000).toLocaleString() : '';
      let body = $('<div/>')
        .text(m.message || '')
        .html();
      if (m.type === 'out') {
        body += '<span class="dashicons dashicons-email"></span>';
      }
      $t.append(
        '<div class="wir-msg is-' +
          who +
          (i === 0 ? ' is-first' : '') +
          '">' +
          '<div class="wir-msg-head">' +
          (who === 'admin' ? 'You' : 'User') +
          ' · ' +
          ts +
          '</div>' +
          '<div class="wir-msg-body">' +
          body +
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

  // updateUnreadBadge is provided by admin-badge.js

  // Select item
  $doc.on('click', '.wir-item', function () {
    const $item = $(this);
    $('.wir-item').removeClass('is-active');
    $item.addClass('is-active');
    currentId = parseInt($item.data('id'), 10);
    $.post(
      WIRAdmin.ajax,
      { action: 'wir_mark_read', nonce: WIRAdmin.nonce, request_id: currentId },
      function (res) {
        if (res && res.success) {
          $item.removeClass('is-unread');
          if (typeof res.data.unread !== 'undefined') {
            updateUnreadBadge(parseInt(res.data.unread, 10) || 0);
          }
        }
      }
    );
    fillPreviewFromServer(currentId);
  });

  $doc.on('click', '.wir-pin', function (e) {
    e.preventDefault();
    e.stopPropagation();
    const $item = $(this).closest('.wir-item');
    const id = parseInt($item.data('id'), 10);
    $.post(
      WIRAdmin.ajax,
      { action: 'wir_toggle_pin', nonce: WIRAdmin.nonce, request_id: id },
      function (res) {
        if (res && res.success) {
          if (res.data.pinned) {
            $item.addClass('is-pinned');
          } else {
            $item.removeClass('is-pinned');
          }
          placeItem($item);
        }
      }
    );
  });

  $doc.on('click', '.wir-star', function (e) {
    e.preventDefault();
    e.stopPropagation();
    const $icon = $(this);
    const $item = $icon.closest('.wir-item');
    const id = parseInt($item.data('id'), 10);
    $.post(
      WIRAdmin.ajax,
      { action: 'wir_toggle_star', nonce: WIRAdmin.nonce, request_id: id },
      function (res) {
        if (res && res.success) {
          if (res.data.starred) {
            $item.addClass('is-starred');
            $icon.removeClass('dashicons-star-empty').addClass('dashicons-star-filled');
          } else {
            $item.removeClass('is-starred');
            $icon.removeClass('dashicons-star-filled').addClass('dashicons-star-empty');
          }
        }
      }
    );
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
          updateActiveItemStatus('replied');
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

  // Polling handled by admin-badge.js

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
          updateActiveItemStatus(res.data.status);
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
