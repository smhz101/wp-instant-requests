(function ($) {
  'use strict';

  function updateUnreadBadge(count) {
    const $menu = $('#toplevel_page_wir .wp-menu-name');
    if (!$menu.length) return;
    let $badge = $menu.find('.update-plugins');
    if (count > 0) {
      if (!$badge.length) {
        $badge = (
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

  function placeItem($item) {
    const $list = $('.wir-list-inner');
    if (!$list.length) return;
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

  window.WIRUtils = {
    updateUnreadBadge: updateUnreadBadge,
    placeItem: placeItem,
  };
})(jQuery);
