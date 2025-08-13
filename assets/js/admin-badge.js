
(function ($) {
  'use strict';
  // Shared utilities
  const { updateUnreadBadge, placeItem } = window.WIRUtils;
  let lastKnownId = parseInt(WIRBadge.last_id, 10) || 0;

    function scanInitialMaxId() {
    const ids = $('.wir-item')
      .map(function () {
        return parseInt($(this).data('id'), 10) || 0;
      })
      .get();
    if (ids.length) {
      const domMax = Math.max.apply(null, ids);
      if (domMax > lastKnownId) lastKnownId = domMax;
    }
  }

  function poll() {
    $.post(
      WIRBadge.ajax,
      { action: 'wir_check_new', nonce: WIRBadge.nonce, last_id: lastKnownId },
      function (res) {
        if (!res || !res.success) return;

        // 1) Update menu badge everywhere
        if (typeof res.data.unread !== 'undefined') {
          updateUnreadBadge(parseInt(res.data.unread, 10) || 0);
        }

        // 2) If on mailbox page, insert new items; otherwise ignore list HTML
        if (Array.isArray(res.data.items) && res.data.items.length) {
          res.data.items.forEach(function (html) {
            placeItem($(html));
          });
        }

        if (res.data.last_id) {
          const n = parseInt(res.data.last_id, 10);
          if (n > lastKnownId) lastKnownId = n;
        }
      }
    );
  }

  document.addEventListener('DOMContentLoaded', function () {
    scanInitialMaxId();
    setInterval(poll, 15000);
  });
})(jQuery);
