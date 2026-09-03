/**
 * YooY Studio — Home dashboard section registry.
 * Server config (home_sections API) is primary; DEFAULT_SECTIONS is client fallback.
 */
(function (global) {
  'use strict';

  var DEFAULT_SECTIONS = [
    {
      id: 'focus',
      title: '집중형 작품',
      description: '인물, 제품, 포스터 등 집중감 있는 작품',
      visible: true,
      order: 1,
      display_type: 'gallery',
      data_source: 'gallery',
      type: 'gallery',
      layout: 'carousel',
      card_ratio: 'portrait',
      column_count: 'carousel',
      filter: { orientation: 'portrait' },
      limit: 4,
      works: [],
      projects: [],
    },
    {
      id: 'wide',
      title: '확장형 작품',
      description: '풍경, 배경, 배너 등 넓고 시원한 작품',
      visible: true,
      order: 2,
      display_type: 'gallery',
      data_source: 'gallery',
      type: 'gallery',
      layout: 'carousel',
      card_ratio: 'wide',
      column_count: 'carousel',
      filter: { orientation: 'landscape' },
      limit: 4,
      works: [],
      projects: [],
    },
    {
      id: 'templates',
      title: '추천 템플릿',
      description: '바로 시작할 수 있는 인기 템플릿',
      visible: true,
      order: 3,
      display_type: 'template',
      data_source: 'templates',
      type: 'template',
      layout: 'carousel',
      column_count: 'carousel',
      limit: 4,
      works: [],
      projects: [],
    },
    {
      id: 'recent',
      title: '최근 작업',
      description: '이어서 작업하거나 다시 열어보세요',
      visible: true,
      order: 4,
      display_type: 'recent',
      data_source: 'gallery',
      type: 'recent',
      layout: 'grid',
      column_count: 4,
      limit: 4,
      works: [],
      projects: [],
    },
    {
      id: 'saved',
      title: '저장된 템플릿',
      description: '내가 저장한 프롬프트와 템플릿',
      visible: true,
      order: 5,
      display_type: 'template',
      data_source: 'gallery',
      type: 'template',
      layout: 'grid',
      column_count: 4,
      limit: 8,
      works: [],
      projects: [],
    },
    {
      id: 'guide',
      title: '초보자 가이드',
      description: '처음이어도 쉽게 따라 할 수 있어요',
      visible: true,
      order: 6,
      display_type: 'guide',
      data_source: 'guide',
      type: 'guide',
      layout: 'grid',
      column_count: 3,
      limit: 3,
      works: [],
      projects: [],
    },
  ];

  var STUDIO_RECOS = [
    { id: 'image', route: 'image', title: '이미지 Studio', desc: '상상한 장면을 이미지로 만들어보세요.', art: 'image' },
    { id: 'video', route: 'video', title: '영상 Studio', desc: '아이디어를 영상으로 완성해보세요.', art: 'video' },
    { id: 'writing', route: 'writing', title: '글쓰기 Studio', desc: '생각을 글과 카피로 완성해보세요.', art: 'writing' },
    { id: 'music', route: 'music', title: '음악 Studio', desc: '브랜드와 분위기에 맞는 BGM을 만드세요.', art: 'music' },
    { id: 'voice', route: 'voice', title: '보이스 Studio', desc: '나레이션·TTS를 바로 들어보세요.', art: 'voice' },
    { id: 'avatar', route: 'avatar', title: '아바타 Studio', desc: '캐릭터가 말하는 영상을 만들어보세요.', art: 'avatar' },
    { id: 'translator', route: 'translator', title: '번역 Studio', desc: '문맥에 맞는 자연스러운 번역을 얻으세요.', art: 'translator' }
  ];

  var HERO_CHIPS = [
    { label: '이미지 생성', studio: 'image', seed: '고품질 제품 사진을 만들어줘' },
    { label: '영상 만들기', studio: 'video', seed: '15초 광고 영상 스토리보드' },
    { label: '글쓰기', studio: 'writing', seed: '블로그 소개 글 초안' },
    { label: '음악 만들기', studio: 'music', seed: '밝은 팝 BGM 30초' },
    { label: '번역하기', studio: 'translator', seed: '한국어를 자연스러운 영어로' },
    { label: '음성 변환', studio: 'voice', seed: '따뜻한 나레이션 톤' },
    { label: '아바타 생성', studio: 'avatar', seed: '친근한 3D 아바타' },
  ];

  var QUICK_TOOLS = [
    { label: '배경 제거', studio: 'image', seed: '배경을 제거한 깔끔한 컷아웃' },
    { label: 'AI 이미지 개선', studio: 'image', seed: '이미지 품질을 개선하고 선명하게' },
    { label: '제품 광고 만들기', studio: 'image', seed: '프리미엄 제품 광고 포스터' },
    { label: '사진을 영상으로', studio: 'video', seed: '사진을 기반으로 한 짧은 영상' },
    { label: '쇼츠 만들기', studio: 'video', seed: '9:16 쇼츠 영상 10초' },
    { label: 'SNS 포스터', studio: 'image', seed: '인스타그램용 SNS 포스터' },
    { label: '문서 요약', studio: 'writing', seed: '긴 문서를 3줄로 요약' },
    { label: '텍스트 번역', studio: 'translator', seed: '전문적인 톤으로 번역' },
    { label: '나레이션 만들기', studio: 'voice', seed: '텍스트를 자연스러운 나레이션으로' },
    { label: '음악 커버 만들기', studio: 'music', seed: '브랜드용 밝은 커버 BGM' },
  ];

  function cloneDefaults() {
    return DEFAULT_SECTIONS.map(function (s) {
      return Object.assign({}, s, {
        filter: Object.assign({}, s.filter || {}),
        works: (s.works || []).slice(),
        projects: (s.projects || []).slice(),
      });
    });
  }

  function inferDisplayType(api) {
    var dt = api.display_type || api.type || 'gallery';
    if (dt === 'project') return 'projects';
    if (['gallery', 'template', 'recent', 'guide', 'projects'].indexOf(dt) >= 0) return dt;
    if (dt === 'community' || dt === 'marketplace' || dt === 'official' || dt === 'mixed') return 'gallery';
    return 'gallery';
  }

  function mapApiSection(api, index) {
    if (!api) return null;
    var displayType = inferDisplayType(api);
    var layout = api.layout || (api.column_count === 'carousel' ? 'carousel' : 'grid');
    return {
      id: String(api.id || ('sec_' + index)),
      title: String(api.title || '섹션'),
      description: String(api.description || ''),
      visible: api.visible !== false,
      order: Number(api.order != null ? api.order : api.sort_order != null ? api.sort_order : index),
      display_type: displayType,
      data_source: api.data_source || api.source || 'gallery',
      type: displayType,
      layout: layout,
      column_count: api.column_count != null ? api.column_count : (layout === 'carousel' ? 'carousel' : 4),
      card_ratio: api.card_ratio || 'auto',
      text_mode: api.text_mode || 'below',
      filter: api.filter && typeof api.filter === 'object' ? Object.assign({}, api.filter) : {},
      limit: Number(api.limit) || 8,
      works: Array.isArray(api.works) ? api.works.slice() : [],
      projects: Array.isArray(api.projects) ? api.projects.slice() : [],
      _fromServer: true,
    };
  }

  function sectionsFromFeed(feed) {
    var api = (feed && feed.home_sections) || [];
    if (api.length) {
      return api.map(mapApiSection).filter(Boolean);
    }
    return cloneDefaults();
  }

  function hasServerSections(feed) {
    return !!((feed && feed.home_sections) || []).length;
  }

  global.YooYHomeDashboardConfig = {
    DEFAULT_SECTIONS: DEFAULT_SECTIONS,
    HERO_CHIPS: HERO_CHIPS,
    QUICK_TOOLS: QUICK_TOOLS,
    STUDIO_RECOS: STUDIO_RECOS,
    cloneDefaults: cloneDefaults,
    mapApiSection: mapApiSection,
    sectionsFromFeed: sectionsFromFeed,
    hasServerSections: hasServerSections,
  };
})(typeof window !== 'undefined' ? window : globalThis);
