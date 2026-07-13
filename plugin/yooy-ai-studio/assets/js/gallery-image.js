(function (global) {
  'use strict';

  function escAttr(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  }

  function pickFallback(item) {
    if (!item) return '';
    var images = item.images || {};
    return item.full_url || item.original_url || images.full || item.large_url || images.large
      || item.medium_large_url || images.medium_large || item.image_url || item.output_url
      || item.asset_url || item.display_url || item.url || item.thumbnail_url || images.thumbnail
      || item.thumbnail || '';
  }

  function pickUrl(item, size) {
    if (!item) return '';
    var images = item.images || {};
    var full = item.full_url || item.original_url || images.full || item.image_url || item.output_url || item.asset_url || '';
    var large = item.large_url || images.large || item.medium_large_url || images.medium_large || full;
    var mediumLarge = item.medium_large_url || images.medium_large || large;
    var thumb = item.thumbnail_url || images.thumbnail || item.thumbnail || images.medium || mediumLarge;

    if (size === 'full') {
      return full || large || pickFallback(item);
    }
    if (size === 'thumb') {
      return thumb || mediumLarge || large || pickFallback(item);
    }
    return large || full || mediumLarge || pickFallback(item);
  }

  function imgTag(item, opts) {
    opts = opts || {};
    var size = opts.size || 'large';
    var src = pickUrl(item, size);
    if (!src) return '';
    var srcset = item.srcset || '';
    var sizes = opts.sizes || item.sizes || '';
    var attrs = ' src="' + escAttr(src) + '" alt="" class="' + escAttr(opts.className || 'yai-gallery-img') + '"';
    if (srcset) {
      attrs += ' srcset="' + escAttr(srcset) + '"';
    }
    if (sizes && srcset) {
      attrs += ' sizes="' + escAttr(sizes) + '"';
    }
    if (opts.lazy !== false) {
      attrs += ' loading="lazy"';
    }
    attrs += ' decoding="async"';
    if (opts.priority) {
      attrs += ' fetchpriority="high"';
    }
    return '<img' + attrs + '>';
  }

  global.YooYGalleryImage = {
    pickUrl: pickUrl,
    pickFallback: pickFallback,
    imgTag: imgTag
  };
})(window);
