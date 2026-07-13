(function (global) {
  'use strict';

  var STYLE_PRESETS = {
    premium_photo: { id: 'premium_photo', label: 'Premium Photo', style: 'photorealistic', brand_tone: 'premium', quality: 'hd' },
    movie_poster: { id: 'movie_poster', label: 'Movie Poster', style: 'cinematic', brand_tone: 'luxury', quality: 'hd', composition: 'hero' },
    luxury_ad: { id: 'luxury_ad', label: 'Luxury Advertising', style: 'commercial', brand_tone: 'luxury', quality: 'hd', lighting: 'studio' },
    travel_campaign: { id: 'travel_campaign', label: 'Travel Campaign', style: 'cinematic', brand_tone: 'premium', composition: 'wide', lighting: 'golden_hour' },
    minimal_design: { id: 'minimal_design', label: 'Minimal Design', style: 'minimal', brand_tone: 'premium', quality: 'standard', background: 'studio_white' },
    fantasy: { id: 'fantasy', label: 'Fantasy', style: 'cinematic', brand_tone: 'premium', lighting: 'dramatic', composition: 'wide' },
    k_culture: { id: 'k_culture', label: 'K-Culture', style: 'k-beauty', brand_tone: 'youthful', quality: 'hd', product_type: 'cosmetics' }
  };

  var PREMIUM_NEGATIVE = 'text, words, letters, typography, captions, watermarks, cartoon, anime, illustration, childish drawing, clip art, sticker style, low resolution, blurry, distorted face, bad hands, extra fingers, amateur, oversaturated, plastic skin, meaningless background, uncanny AI look';

  function lower(s) { return String(s || '').toLowerCase(); }

  function hasAny(text, words) {
    for (var i = 0; i < words.length; i++) {
      if (text.indexOf(words[i]) !== -1) return true;
    }
    return false;
  }

  function pickPreset(prompt) {
    var t = lower(prompt);
    if (hasAny(t, ['스마트스토어', 'smartstore', '쿠팡', 'coupang', '쇼핑몰', '이커머스', 'ecommerce', '상세페이지'])) return STYLE_PRESETS.premium_photo;
    if (hasAny(t, ['유튜브', 'youtube', '썸네일', 'thumbnail', '쇼츠', 'shorts'])) return STYLE_PRESETS.movie_poster;
    if (hasAny(t, ['카드뉴스', 'card news', '인스타', 'instagram', 'sns', '틱톡', 'tiktok'])) return STYLE_PRESETS.minimal_design;
    if (hasAny(t, ['k-pop', 'kpop', '케이팝', '아이돌', 'k-'])) return STYLE_PRESETS.k_culture;
    if (hasAny(t, ['한국', '대한민국', 'korea'])) { /* korean context applied in optimizePrompt */ }
    if (hasAny(t, ['fantasy', 'dragon', 'whale', '고래', '판타지', '마법', 'moon', '달'])) return STYLE_PRESETS.fantasy;
    if (hasAny(t, ['poster', 'movie', 'film', '영화', '포스터', '시네마'])) return STYLE_PRESETS.movie_poster;
    if (hasAny(t, ['luxury', 'brand', '광고', 'advert', 'commercial', '브랜드', '럭셔리'])) return STYLE_PRESETS.luxury_ad;
    if (hasAny(t, ['travel', '여행', 'tour', 'campaign', '세계'])) return STYLE_PRESETS.travel_campaign;
    if (hasAny(t, ['k-beauty', 'kbeauty', 'k-pop', 'kpop', '케이', '한국', 'k-'])) return STYLE_PRESETS.k_culture;
    if (hasAny(t, ['minimal', '미니멀', 'clean', 'simple'])) return STYLE_PRESETS.minimal_design;
    if (hasAny(t, ['product', '제품', '썸네일', '스마트스토어', '이커머스', 'ecommerce', '쇼핑'])) return STYLE_PRESETS.premium_photo;
    return STYLE_PRESETS.premium_photo;
  }

  function analyzePrompt(prompt, refCtx) {
    refCtx = refCtx || {};
    var preset = pickPreset(prompt);
    var t = lower(prompt);
    var profile = {
      recommendedStyle: preset.label,
      presetId: preset.id,
      model: 'gpt-image-1',
      provider: 'auto',
      quality: preset.quality || 'hd',
      style: preset.style || 'photorealistic',
      lighting: preset.lighting || 'studio',
      composition: preset.composition || 'rule_of_thirds',
      background: preset.background || 'studio_white',
      color_palette: 'neutral',
      mood: 'neutral',
      brand_tone: preset.brand_tone || 'premium',
      product_type: preset.product_type || 'general',
      commercial: true,
      camera: 'cinema_50mm',
      lens: 'standard',
      camera_angle: 'eye_level',
      depth_of_field: 'medium',
      output_format: 'png',
      resolution: '1024',
      image_count: 1
    };

    if (hasAny(t, ['night', 'moon', '달', '밤', 'moonlight'])) {
      profile.lighting = 'dramatic';
      profile.color_palette = 'cool';
      profile.background = 'outdoor';
      profile.mood = 'dreamy';
      profile.camera_angle = 'low_angle';
    }
    if (hasAny(t, ['travel', '여행', 'wide', 'epic', 'landscape'])) {
      profile.composition = 'wide';
      profile.lighting = profile.lighting === 'studio' ? 'golden_hour' : profile.lighting;
      profile.mood = 'epic';
      profile.camera = 'wide_24mm';
      profile.depth_of_field = 'deep';
    }
    if (hasAny(t, ['portrait', '인물', 'face', '클로즈'])) {
      profile.composition = 'close_up';
      profile.camera = 'tele_85mm';
      profile.depth_of_field = 'shallow';
    }
    if (hasAny(t, ['flat', '플랫', 'lay', '제품'])) {
      profile.composition = 'flat_lay';
      profile.background = 'studio_white';
    }
    if (hasAny(t, ['cartoon', 'anime', '애니', '만화', 'illustration', '일러스트'])) {
      profile.style = 'illustration';
      profile.commercial = false;
    }

    if (refCtx.traits) {
      if (refCtx.traits.lighting) profile.lighting = refCtx.traits.lighting;
      if (refCtx.traits.composition) profile.composition = refCtx.traits.composition;
      if (refCtx.traits.color) profile.color_palette = refCtx.traits.color;
      if (refCtx.traits.style) profile.style = refCtx.traits.style;
      if (refCtx.traits.mood) profile.mood = refCtx.traits.mood;
      if (refCtx.traits.lens) profile.lens = refCtx.traits.lens === '50mm cinematic' ? 'portrait' : profile.lens;
    }

    return profile;
  }

  function analyzeReference(assets, prompt) {
    assets = assets || [];
    var t = lower(prompt);
    if (!assets.length && !stateRefUrl()) return { traits: null, context: '', labels: [] };

    var traits = {
      lighting: 'studio',
      composition: 'rule_of_thirds',
      color: 'neutral',
      style: 'photorealistic',
      mood: 'neutral',
      lens: '50mm cinematic'
    };
    var labels = [];

    if (hasAny(t, ['fantasy', 'whale', '고래', 'moon', '달', 'dream'])) {
      traits.lighting = 'dramatic';
      traits.composition = 'wide';
      traits.color = 'cool';
      traits.mood = 'dreamy';
      traits.style = 'cinematic';
      traits.lens = 'portrait';
      labels = ['Moonlight', 'Dreamy', 'Wide Composition', 'Fantasy', 'Blue Tone', 'High Detail', 'Premium Lighting'];
    } else if (assets.length) {
      traits.lighting = 'soft';
      traits.composition = 'hero';
      traits.color = 'warm';
      labels = ['Reference Color Match', 'Premium Mood', 'Commercial Lighting', 'Balanced Composition'];
    }

    var context = labels.length ? ('Style context from reference: ' + labels.join(', ') + '.') : '';
    return { traits: traits, context: context, labels: labels };
  }

  function stateRefUrl() { return ''; }

  function optimizePrompt(prompt, profile, refCtx) {
    profile = profile || {};
    refCtx = refCtx || {};
    var t = lower(prompt);
    var wantsCartoon = hasAny(t, ['cartoon', 'anime', '애니', '만화', 'illustration']);

    if (wantsCartoon) {
      return prompt;
    }

    if (hasAny(t, ['답답', '외로', '희망', '자유', '행복', '슬픔', '마음', '감정'])) {
      return 'Cinematic fine-art scene expressing the feeling through visual storytelling — no text or lettering. (서버에서 최종 프롬프트를 생성합니다.)';
    }

    return prompt + ' — premium photorealistic quality (서버 Prompt Composer가 최종 최적화합니다.)';
  }

  function applyComposerResult(settings, composed) {
    if (!settings || !composed) return settings;
    var s = composed.settings || {};
    Object.keys(s).forEach(function (key) {
      if (s[key] != null && s[key] !== '') settings[key] = s[key];
    });
    return settings;
  }

  function composerProfileLabels(meta) {
    meta = meta || {};
    var analysis = meta.analysis || {};
    var rows = [
      { key: 'emotion', label: '감정 분석', value: analysis.emotion || '—' },
      { key: 'mood', label: 'Mood', value: analysis.mood || '—' },
      { key: 'style', label: '스타일', value: labelFor('style', analysis.style) },
      { key: 'scene', label: 'Scene', value: (analysis.scene || []).join(', ') || '—' },
      { key: 'korean', label: 'K-Culture', value: analysis.korean || '—' },
      { key: 'abstract', label: '의미 표현', value: analysis.abstract ? '감정·장면 우선' : '사실 묘사' }
    ];
    return rows;
  }

  function applyToSettings(settings, profile) {
    if (!settings || !profile) return settings;
    settings.default_provider = profile.provider || settings.default_provider || 'auto';
    if ((profile.provider || settings.default_provider) !== 'auto') {
      settings.default_model = profile.model || settings.default_model || 'gpt-image-1';
      settings.model = settings.default_model;
    } else {
      delete settings.default_model;
      delete settings.model;
    }
    settings.quality = profile.quality || 'hd';
    settings.style = profile.style || 'photorealistic';
    settings.lighting = profile.lighting || 'studio';
    settings.composition = profile.composition || 'rule_of_thirds';
    settings.background = profile.background || 'studio_white';
    settings.color_palette = profile.color_palette || 'neutral';
    settings.brand_tone = profile.brand_tone || 'premium';
    settings.product_type = profile.product_type || 'general';
    settings.image_count = profile.image_count || 1;
    settings.korean_context = true;
    if (profile.commercial !== false) {
      var neg = PREMIUM_NEGATIVE;
      if (!settings.negative_prompt || settings.negative_prompt.indexOf('cartoon') === -1) {
        settings.negative_prompt = neg;
      }
    }
    return settings;
  }

  function profileLabels(profile) {
    profile = profile || {};
    return [
      { key: 'model', label: '모델', value: profile.provider === 'auto' ? 'Auto (서버 결정)' : (profile.model === 'gpt-image-1' ? 'GPT Image' : (profile.model || 'Auto')) },
      { key: 'style', label: '스타일', value: labelFor('style', profile.style) },
      { key: 'lighting', label: '라이팅', value: labelFor('lighting', profile.lighting) },
      { key: 'background', label: '배경', value: labelFor('background', profile.background) },
      { key: 'color', label: '색감', value: labelFor('color', profile.color_palette) },
      { key: 'composition', label: '구도', value: labelFor('composition', profile.composition) },
      { key: 'mood', label: 'Mood', value: labelFor('mood', profile.mood) },
      { key: 'quality', label: '품질', value: profile.quality === 'hd' ? 'High' : 'Standard' },
      { key: 'commercial', label: 'Commercial', value: profile.commercial !== false ? 'Enabled' : 'Off' },
      { key: 'brand', label: 'Brand', value: labelFor('brand', profile.brand_tone) }
    ];
  }

  function labelFor(type, id) {
    var maps = {
      style: { photorealistic: 'Premium Photoreal', commercial: 'Commercial', cinematic: 'Cinema Grade', minimal: 'Minimal', 'k-beauty': 'K-Beauty', illustration: 'Illustration' },
      lighting: { studio: 'Studio', natural: 'Natural', golden_hour: 'Golden Hour', dramatic: 'Moonlight', soft: 'Soft Premium', neon: 'Neon' },
      background: { studio_white: 'Studio White', studio_gray: 'Studio Gray', lifestyle: 'Lifestyle', outdoor: 'Natural', gradient: 'Gradient', transparent: 'Transparent' },
      color: { neutral: 'Neutral', warm: 'Warm', cool: 'Cinematic Blue', vivid: 'Vivid', pastel: 'Pastel' },
      composition: { center: 'Center', rule_of_thirds: 'Rule of Thirds', close_up: 'Close-up', wide: 'Wide', flat_lay: 'Flat Lay', hero: 'Hero' },
      brand: { premium: 'Premium', luxury: 'Luxury', youthful: 'Youthful', friendly: 'Friendly' },
      mood: { neutral: 'Neutral', dreamy: 'Dreamy', energetic: 'Energetic', moody: 'Moody', romantic: 'Romantic', epic: 'Epic' },
      camera: { cinema_50mm: 'Cinema 50mm', wide_24mm: 'Wide 24mm', tele_85mm: 'Telephoto 85mm', macro: 'Macro' },
      lens: { standard: 'Standard', anamorphic: 'Anamorphic', fisheye: 'Fisheye', portrait: 'Portrait' },
      angle: { eye_level: 'Eye Level', low_angle: 'Low Angle', high_angle: 'High Angle', birds_eye: "Bird's Eye" },
      dof: { shallow: 'Shallow', medium: 'Medium', deep: 'Deep' }
    };
    var m = maps[type] || {};
    return m[id] || String(id || '—').replace(/_/g, ' ');
  }

  function recommendStyles(prompt) {
    var primary = pickPreset(prompt);
    var all = Object.keys(STYLE_PRESETS).map(function (k) { return STYLE_PRESETS[k]; });
    var ordered = [primary];
    all.forEach(function (p) {
      if (p.id !== primary.id) ordered.push(p);
    });
    return ordered.slice(0, 6);
  }

  global.YooYImageStudioSmartAuto = {
    analyzePrompt: analyzePrompt,
    analyzeReference: analyzeReference,
    optimizePrompt: optimizePrompt,
    applyToSettings: applyToSettings,
    applyComposerResult: applyComposerResult,
    composerProfileLabels: composerProfileLabels,
    profileLabels: profileLabels,
    recommendStyles: recommendStyles,
    pickPreset: pickPreset,
    PREMIUM_NEGATIVE: PREMIUM_NEGATIVE,
    STYLE_PRESETS: STYLE_PRESETS
  };
})(window);
