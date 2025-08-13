(function ($) {
  'use strict';
  let lastKnownId = parseInt(WIRBadge.last_id, 10) || 0;

  // Update the admin menu badge (works on any admin page)
  function updateUnreadBadge(count) {
    const $menu = $('#toplevel_page_wir .wp-menu-name');
    if (!$menu.length) return;
    let $badge = $menu.find('.update-plugins');
    if (count > 0) {
      if (!$badge.length) {
        $badge = $(
          '<span class="update-plugins count-' +
            count +
            '"><span class="plugin-count">' +
            count +
            '</span></span>'
        );
        $menu.append($badge);
      } else {
        $badge.attr('class', 'update-plugins count-' + count);
        $badge.find('.plugin-count').text(count);
      }
    } else if ($badge.length) {
      $badge.remove();
    }
  }

    // Insert an item into the list while keeping pinned at the top (same logic as admin.js)
    function placeItem($item) {
    const $list = $('.wir-list-inner');
    if (!$list.length) return; // not on the mailbox page
    const time = parseInt($item.data('time'), 10) || 0;
    if ($item.hasClass('is-pinned')) {
      $list.prepend($item);
      return;
    }

      let inserted = false;
    const $unpinned = $list.children('.wir-item').not('.is-pinned');
    $unpinned.each(function () {
      const t = parseInt($(this).data('time'), 10) || 0;
      if (!inserted && time > t) {
        $(this).before($item);
        inserted = true;
        return false;
      }
    });
      if (!inserted) {
        const $lastPinned = $list.children('.wir-item.is-pinned').last();
        if ($lastPinned.length) $lastPinned.after($item);
        else $list.append($item);
      }
    }

    // Expose utilities globally for admin.js
    window.updateUnreadBadge = updateUnreadBadge;
    window.placeItem = placeItem;

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
