(function (global) {
  'use strict';

  var SIZE_CARD = 'card';
  var SIZE_LARGE = 'large';
  var SIZE_FULL = 'full';
  var SIZE_HERO = 'hero';
  var SIZE_THUMB = 'thumb';

  var DEFAULT_SIZES = {
    thumb: '(max-width: 640px) 40vw, 160px',
    card: '(max-width: 640px) 90vw, (max-width: 1100px) 40vw, 320px',
    large: '(max-width: 900px) 100vw, min(960px, 70vw)',
    hero: '(max-width: 900px) 100vw, min(1100px, 92vw)',
    full: '(max-width: 900px) 100vw, min(1200px, 92vw)'
  };

  function escAttr(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  }

  function firstNonEmpty() {
    for (var i = 0; i < arguments.length; i++) {
      var v = arguments[i];
      if (v != null && String(v).trim() !== '') return String(v).trim();
    }
    return '';
  }

  function pickFallback(item) {
    if (!item) return '';
    var images = item.images || {};
    return firstNonEmpty(
      item.full_url, item.original_url, images.full,
      item.large_url, images.large,
      item.medium_large_url, images.medium_large,
      item.image_url, item.output_url, item.asset_url,
      item.display_url, item.url,
      item.thumbnail_url, images.thumbnail, item.thumbnail
    );
  }

  function resolveUrls(item) {
    var images = (item && item.images) || {};
    var full = firstNonEmpty(
      item.full_url, item.original_url, images.full,
      item.image_url, item.output_url, item.asset_url, item.url
    );
    var large = firstNonEmpty(
      item.large_url, images.large,
      item.medium_large_url, images.medium_large,
      item.display_url, full
    );
    var mediumLarge = firstNonEmpty(
      item.medium_large_url, images.medium_large, large, full
    );
    var medium = firstNonEmpty(item.medium_url, images.medium, mediumLarge);
    var thumb = firstNonEmpty(
      item.thumbnail_url, images.thumbnail, item.thumbnail, medium, mediumLarge
    );
    return {
      full: full,
      large: large,
      mediumLarge: mediumLarge,
      medium: medium,
      thumb: thumb
    };
  }

  /**
   * targetSize: thumb | card | large | hero | full
   * card ≈ large discovery/home cards (never prefer 150px thumb first)
   */
  function pickUrl(item, size) {
    if (!item) return '';
    var urls = resolveUrls(item);
    var target = String(size || SIZE_LARGE).toLowerCase();

    if (target === SIZE_FULL || target === SIZE_HERO) {
      return firstNonEmpty(urls.full, urls.large, urls.mediumLarge, pickFallback(item));
    }
    if (target === SIZE_THUMB) {
      return firstNonEmpty(urls.thumb, urls.medium, urls.mediumLarge, urls.large, pickFallback(item));
    }
    // card + large (default): prefer large/full over thumbnail
    return firstNonEmpty(urls.large, urls.full, urls.mediumLarge, urls.medium, pickFallback(item));
  }

  function buildSrcset(item) {
    if (!item) return '';
    if (item.srcset) return String(item.srcset);
    var urls = resolveUrls(item);
    var parts = [];
    var seen = {};

    function add(url, w) {
      if (!url || seen[url]) return;
      seen[url] = true;
      parts.push(url + (w ? (' ' + w + 'w') : ''));
    }

    // Approximate WP size widths when srcset is absent
    add(urls.thumb, 150);
    add(urls.medium, 300);
    add(urls.mediumLarge, 768);
    add(urls.large, 1024);
    add(urls.full, 2048);

    return parts.length > 1 ? parts.join(', ') : '';
  }

  function imgTag(item, opts) {
    opts = opts || {};
    var size = opts.size || SIZE_LARGE;
    var src = pickUrl(item, size);
    if (!src) return '';
    var srcset = opts.srcset != null ? opts.srcset : buildSrcset(item);
    var sizes = opts.sizes || item.sizes || DEFAULT_SIZES[size] || DEFAULT_SIZES.card;
    var attrs = ' src="' + escAttr(src) + '" alt="" class="' + escAttr(opts.className || 'yai-gallery-img') + '"';
    if (srcset) {
      attrs += ' srcset="' + escAttr(srcset) + '"';
      if (sizes) {
        attrs += ' sizes="' + escAttr(sizes) + '"';
      }
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

  /**
   * Dev/admin-only: log when rendered CSS width exceeds naturalWidth.
   * Never shown to end users.
   */
  function watchUpscale(root, threshold) {
    threshold = threshold || 1.35;
    var debug = !!(global.YooYStudio && global.YooYStudio.debug);
    var isAdmin = !!(global.YooYStudio && global.YooYStudio.isAdmin);
    if (!debug && !isAdmin) return;
    if (typeof console === 'undefined' || !console.warn) return;
    var scope = root && root.querySelectorAll ? root : document;
    var nodes = scope.querySelectorAll
      ? scope.querySelectorAll('img.yai-gallery-img, .yai-hd-thumb img, .yai-work-card-thumb img, .yai-pub-card__thumb img, .ygl-thumb img')
      : [];
    Array.prototype.forEach.call(nodes, function (img) {
      if (img.dataset.yoyQualityWatch === '1') return;
      img.dataset.yoyQualityWatch = '1';
      function check() {
        var nw = img.naturalWidth || 0;
        var rw = img.clientWidth || 0;
        if (nw > 0 && rw > 0 && rw > nw * threshold) {
          console.warn('[YooY image quality] Possible upscale', {
            src: img.currentSrc || img.src,
            naturalWidth: nw,
            renderedWidth: rw,
            dpr: global.devicePixelRatio || 1
          });
        }
      }
      if (img.complete) check();
      else img.addEventListener('load', check, { once: true });
    });
  }

  global.YooYGalleryImage = {
    pickUrl: pickUrl,
    pickFallback: pickFallback,
    buildSrcset: buildSrcset,
    imgTag: imgTag,
    watchUpscale: watchUpscale,
    SIZE_THUMB: SIZE_THUMB,
    SIZE_CARD: SIZE_CARD,
    SIZE_LARGE: SIZE_LARGE,
    SIZE_HERO: SIZE_HERO,
    SIZE_FULL: SIZE_FULL
  };
})(window);
