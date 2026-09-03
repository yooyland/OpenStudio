/**
 * Result-oriented Templates vs Quick Tools.
 * Config map only — not a second Store/Router.
 */
(function (global) {
  'use strict';

  var CATEGORIES = [
    { id: 'popular', label: '인기' },
    { id: 'ads', label: '광고 / 제품' },
    { id: 'sns', label: 'SNS' },
    { id: 'video', label: '영상' },
    { id: 'writing', label: '글쓰기' },
    { id: 'audio', label: '음악 / 음성' },
    { id: 'business', label: '비즈니스' },
    { id: 'translate', label: '번역' }
  ];

  var TEMPLATES = [
    { id: 'tpl-product-ad', title: '제품 광고', description: '제품 사진 하나로 광고 이미지 만들기', category: 'ads', studio: 'image', art: 'image', featured: true, home: true, aspect: '4:5',
      fields: [
        { id: 'image', type: 'image', label: '제품 이미지', required: true },
        { id: 'product_name', type: 'text', label: '제품명', required: false },
        { id: 'style_hint', type: 'select', label: '원하는 느낌', options: ['고급스럽게', '산뜻하게', '한국 광고 톤', '미니멀'] }
      ],
      prompt: '프리미엄 제품 광고 이미지를 만들어줘. 제품명: {product_name}. 느낌: {style_hint}. 업로드한 제품을 주인공으로 유지해줘.' },
    { id: 'tpl-sns-poster', title: 'SNS 포스터', description: '피드에 바로 올리는 정사각 포스터', category: 'sns', studio: 'image', art: 'image', featured: true, home: true, aspect: '1:1',
      fields: [
        { id: 'topic', type: 'text', label: '주제 / 문구', required: true },
        { id: 'style_hint', type: 'select', label: '분위기', options: ['트렌디', '고급', '캐주얼', '행사'] }
      ],
      prompt: '인스타그램용 SNS 포스터를 만들어줘. 주제: {topic}. 분위기: {style_hint}.' },
    { id: 'tpl-yt-thumb', title: '유튜브 썸네일', description: '클릭을 유도하는 와이드 썸네일', category: 'sns', studio: 'image', art: 'image', featured: true, home: true, aspect: '16:9',
      fields: [{ id: 'topic', type: 'text', label: '영상 제목', required: true }],
      prompt: '유튜브 썸네일 이미지를 만들어줘. 제목: {topic}. 대비가 강하고 텍스트 공간이 있게.' },
    { id: 'tpl-travel', title: '여행 포스터', description: '여행지 분위기의 포스터', category: 'ads', studio: 'image', art: 'image', featured: false, home: false, aspect: '3:4',
      fields: [{ id: 'topic', type: 'text', label: '여행지 / 테마', required: true }],
      prompt: '여행 포스터 이미지를 만들어줘. 장소: {topic}.' },
    { id: 'tpl-portrait', title: '인물 프로필', description: '프로필·명함용 인물 이미지', category: 'business', studio: 'image', art: 'image', featured: false, aspect: '3:4',
      fields: [{ id: 'topic', type: 'text', label: '분위기 / 직업', required: false }],
      prompt: '전문적인 인물 프로필 이미지를 만들어줘. 분위기: {topic}.' },
    { id: 'tpl-food', title: '메뉴/음식 광고', description: '메뉴판·배달앱용 음식 컷', category: 'ads', studio: 'image', art: 'image', featured: false, aspect: '1:1',
      fields: [{ id: 'topic', type: 'text', label: '메뉴 이름', required: true }],
      prompt: '먹음직스러운 음식 광고 이미지를 만들어줘. 메뉴: {topic}.' },
    { id: 'tpl-realestate', title: '부동산 홍보 이미지', description: '매물·분양 홍보 비주얼', category: 'business', studio: 'image', art: 'image', featured: false, aspect: '16:9',
      fields: [{ id: 'topic', type: 'text', label: '매물 / 단지명', required: true }],
      prompt: '부동산 홍보 이미지를 만들어줘. 대상: {topic}.' },
    { id: 'tpl-product-10s', title: '10초 제품 광고', description: '짧은 제품 소개 영상', category: 'video', studio: 'video', art: 'video', featured: true, home: true, duration: 10, aspect: '9:16',
      fields: [
        { id: 'image', type: 'image', label: '제품 이미지', required: false },
        { id: 'topic', type: 'text', label: '메시지', required: true }
      ],
      prompt: '10초 제품 광고 영상을 만들어줘. 메시지: {topic}.' },
    { id: 'tpl-shorts', title: '쇼츠 영상', description: '세로형 짧은 쇼츠', category: 'video', studio: 'video', art: 'video', featured: true, home: true, duration: 15, aspect: '9:16',
      fields: [{ id: 'topic', type: 'text', label: '영상 내용', required: true }],
      prompt: '15초 쇼츠 광고 영상을 만들어줘. 내용: {topic}.' },
    { id: 'tpl-photo-video', title: '사진을 영상으로', description: '한 장의 사진으로 짧은 영상', category: 'video', studio: 'video', art: 'video', featured: true, home: false, duration: 8, aspect: '9:16',
      fields: [
        { id: 'image', type: 'image', label: '사진', required: true },
        { id: 'topic', type: 'text', label: '움직임 / 분위기', required: false }
      ],
      prompt: '이 사진을 짧은 영상으로 만들어줘. 분위기: {topic}.' },
    { id: 'tpl-sns-motion', title: 'SNS 모션 광고', description: '피드용 모션 광고', category: 'sns', studio: 'video', art: 'video', featured: false, duration: 8, aspect: '1:1',
      fields: [{ id: 'topic', type: 'text', label: '캠페인 메시지', required: true }],
      prompt: 'SNS 모션 광고 영상을 만들어줘. 메시지: {topic}.' },
    { id: 'tpl-slide', title: '슬라이드 영상', description: '장면이 이어지는 소개 영상', category: 'video', studio: 'video', art: 'video', featured: false, duration: 15, aspect: '16:9',
      fields: [{ id: 'topic', type: 'text', label: '소개할 내용', required: true }],
      prompt: '슬라이드처럼 장면이 이어지는 소개 영상을 만들어줘. 내용: {topic}.' },
    { id: 'tpl-blog', title: '블로그 글', description: '주제로 바로 초안 쓰기', category: 'writing', studio: 'writing', art: 'writing', featured: true, home: true,
      fields: [
        { id: 'topic', type: 'text', label: '주제', required: true },
        { id: 'style_hint', type: 'select', label: '톤', options: ['친절하게', '전문적으로', '캐주얼하게'] }
      ],
      prompt: '블로그 글을 써줘. 주제: {topic}. 톤: {style_hint}.' },
    { id: 'tpl-product-copy', title: '제품 소개', description: '상세페이지용 소개 글', category: 'writing', studio: 'writing', art: 'writing', featured: false,
      fields: [{ id: 'topic', type: 'text', label: '제품명 / 특징', required: true }],
      prompt: '이 제품 소개글을 써줘. {topic}' },
    { id: 'tpl-ad-copy', title: '광고 카피', description: '짧은 헤드라인과 카피', category: 'ads', studio: 'writing', art: 'writing', featured: false,
      fields: [{ id: 'topic', type: 'text', label: '제품 / 혜택', required: true }],
      prompt: '광고 카피를 여러 안 써줘. 대상: {topic}.' },
    { id: 'tpl-company', title: '회사 소개', description: '브랜드 소개문 초안', category: 'business', studio: 'writing', art: 'writing', featured: false,
      fields: [{ id: 'topic', type: 'text', label: '회사 / 사업', required: true }],
      prompt: '회사 소개글을 써줘. {topic}' },
    { id: 'tpl-sns-post', title: 'SNS 게시글', description: '캡션과 해시태그', category: 'sns', studio: 'writing', art: 'writing', featured: false,
      fields: [{ id: 'topic', type: 'text', label: '올릴 내용', required: true }],
      prompt: 'SNS 게시글과 해시태그를 써줘. {topic}' },
    { id: 'tpl-press', title: '보도자료 초안', description: '뉴스용 보도자료 골격', category: 'business', studio: 'writing', art: 'writing', featured: false,
      fields: [{ id: 'topic', type: 'text', label: '발표 내용', required: true }],
      prompt: '보도자료 초안을 써줘. {topic}' },
    { id: 'tpl-brand-bgm', title: '브랜드 BGM', description: '브랜드에 맞는 잔잔한 BGM', category: 'audio', studio: 'music', art: 'music', featured: true, home: true,
      fields: [
        { id: 'style_hint', type: 'select', label: '분위기', options: ['잔잔한', '밝은', '고급스러운', '시네마틱'] },
        { id: 'topic', type: 'text', label: '브랜드 / 용도', required: false }
      ],
      prompt: '브랜드 BGM을 만들어줘. 분위기: {style_hint}. 용도: {topic}.' },
    { id: 'tpl-piano', title: '감성 피아노', description: '잔잔한 피아노 트랙', category: 'audio', studio: 'music', art: 'music', featured: false,
      fields: [],
      prompt: '잔잔한 피아노 BGM을 만들어줘.' },
    { id: 'tpl-shorts-bgm', title: '쇼츠용 BGM', description: '짧은 영상에 붙는 BGM', category: 'audio', studio: 'music', art: 'music', featured: false,
      fields: [{ id: 'style_hint', type: 'select', label: '분위기', options: ['업비트', '감성', '트렌디'] }],
      prompt: '쇼츠용 짧은 BGM을 만들어줘. 분위기: {style_hint}.' },
    { id: 'tpl-ad-narration', title: '광고 나레이션', description: '광고 멘트를 목소리로', category: 'audio', studio: 'voice', art: 'voice', featured: false,
      fields: [{ id: 'topic', type: 'text', label: '읽을 문장', required: true }],
      prompt: '이 문장을 광고 나레이션으로 읽어줘. {topic}' },
    { id: 'tpl-guide-voice', title: '안내 음성', description: '매장·앱 안내 멘트', category: 'audio', studio: 'voice', art: 'voice', featured: false,
      fields: [{ id: 'topic', type: 'text', label: '안내 문구', required: true }],
      prompt: '안내 음성으로 읽어줘. {topic}' },
    { id: 'tpl-video-narration', title: '영상 나레이션', description: '영상에 올릴 내레이션', category: 'audio', studio: 'voice', art: 'voice', featured: true, home: false,
      fields: [{ id: 'topic', type: 'text', label: '스크립트', required: true }],
      prompt: '영상 나레이션으로 읽어줘. {topic}' },
    { id: 'tpl-doc-translate', title: '문서 번역', description: '문서를 다른 언어로', category: 'translate', studio: 'translator', art: 'translator', featured: false,
      fields: [
        { id: 'file', type: 'file', label: '문서', required: false },
        { id: 'topic', type: 'text', label: '번역할 문장', required: false }
      ],
      prompt: '영어로 번역해줘. {topic}' },
    { id: 'tpl-web-translate', title: '웹페이지 번역', description: 'URL 글을 번역', category: 'translate', studio: 'translator', art: 'translator', featured: false,
      fields: [{ id: 'url', type: 'url', label: '웹페이지 URL', required: true }],
      prompt: '이 내용을 영어로 번역해줘.' },
    { id: 'tpl-mkt-translate', title: '마케팅 문구 번역', description: '광고 카피 번역', category: 'translate', studio: 'translator', art: 'translator', featured: false,
      fields: [{ id: 'topic', type: 'text', label: '원문', required: true }],
      prompt: '마케팅 문구를 자연스러운 영어로 번역해줘. {topic}' }
  ];

  var TOOLS = [
    { id: 'tool-enhance', label: 'AI 이미지 개선', studio: 'image', enabled: true, templateId: null, requireImage: true,
      prompt: '이 사진의 품질을 개선하고 더 선명하게 만들어줘.' },
    { id: 'tool-product-ad', label: '제품 광고 만들기', studio: 'image', enabled: true, templateId: 'tpl-product-ad' },
    { id: 'tool-photo-video', label: '사진을 영상으로', studio: 'video', enabled: true, templateId: 'tpl-photo-video' },
    { id: 'tool-shorts', label: '쇼츠 만들기', studio: 'video', enabled: true, templateId: 'tpl-shorts' },
    { id: 'tool-sns-poster', label: 'SNS 포스터', studio: 'image', enabled: true, templateId: 'tpl-sns-poster' },
    { id: 'tool-summarize', label: '문서 요약', studio: 'writing', enabled: true, requireFile: true,
      prompt: '이 내용을 요약해서 글로 만들어줘.' },
    { id: 'tool-translate', label: '텍스트 번역', studio: 'translator', enabled: true, templateId: 'tpl-mkt-translate' },
    { id: 'tool-narration', label: '나레이션 만들기', studio: 'voice', enabled: true, templateId: 'tpl-ad-narration' },
    { id: 'tool-music-cover', label: '음악 커버 만들기', studio: 'music', enabled: true, templateId: 'tpl-brand-bgm' }
  ];

  function byId(id) {
    var i;
    for (i = 0; i < TEMPLATES.length; i++) if (TEMPLATES[i].id === id) return TEMPLATES[i];
    return null;
  }

  function toolById(id) {
    var i;
    for (i = 0; i < TOOLS.length; i++) if (TOOLS[i].id === id) return TOOLS[i];
    return null;
  }

  function featured(limit) {
    var list = TEMPLATES.filter(function (t) { return t.home || t.featured; });
    return list.slice(0, limit || 8);
  }

  function inCategory(cat) {
    if (!cat || cat === 'popular') return featured(24);
    return TEMPLATES.filter(function (t) { return t.category === cat; });
  }

  function fillPrompt(tpl, values) {
    var text = String(tpl.prompt || '');
    values = values || {};
    Object.keys(values).forEach(function (k) {
      text = text.split('{' + k + '}').join(values[k] || '');
    });
    return text.replace(/\s{2,}/g, ' ').replace(/:\s*\./g, '.').trim();
  }

  global.YooYCreationCatalog = {
    CATEGORIES: CATEGORIES,
    TEMPLATES: TEMPLATES,
    TOOLS: TOOLS.filter(function (t) { return t.enabled !== false; }),
    byId: byId,
    toolById: toolById,
    featured: featured,
    inCategory: inCategory,
    fillPrompt: fillPrompt
  };
})(typeof window !== 'undefined' ? window : this);
