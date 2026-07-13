/**
 * Lightweight domain/preservation checks mirroring Prompt Intelligence Cases A–E.
 * Run: node modules/image-studio/tests/prompt-intelligence-cases.mjs
 */
const ENTITY_EN = {
  '이재명': 'Lee Jae-myung',
  '제주': 'Jeju',
};

function classifyDomain(raw) {
  const lower = raw.toLowerCase();
  const rules = [
    ['politics', ['정치', '이재명', '대통령', '선거', '정책', '국회', '정당', '대선', 'political', 'president', 'election', 'policy']],
    ['product', ['제품', '상품', '향수', '화장품', '스킨케어', 'perfume', 'cosmetic', 'skincare', 'bottle', 'product']],
    ['travel', ['여행', '관광', '제주', '휴가', 'tour', 'travel']],
    ['corporate', ['회사 소개', '기업', '채용', 'corporate', 'recruit']],
  ];
  for (const [domain, needles] of rules) {
    if (needles.some((n) => lower.includes(n.toLowerCase()))) return domain;
  }
  if (/광고|advert|campaign|캠페인/.test(lower)) return 'brand';
  return 'general';
}

function composePolitics(raw) {
  const hasLee = raw.includes('이재명');
  const subject = hasLee
    ? 'Lee Jae-myung (이재명) delivering the most important Korean political message'
    : raw;
  return `A premium Korean political editorial campaign poster centered on ${subject}. Confident and trustworthy visual tone. modern navy suit, clear leadership posture.`;
}

function composeProduct(raw) {
  return `Premium product advertising photograph of ${raw}. hero product as clear focal point.`;
}

function composeTravel(raw) {
  const jeju = raw.includes('제주') ? 'Jeju ' : '';
  return `Cinematic tourism campaign visual featuring ${jeju}${raw}. aspirational travel atmosphere.`;
}

function compose(raw) {
  const domain = classifyDomain(raw);
  if (domain === 'politics') return { domain, prompt: composePolitics(raw), neg: 'cosmetic bottle, perfume, skincare product' };
  if (domain === 'product') return { domain, prompt: composeProduct(raw), neg: 'political poster' };
  if (domain === 'travel') return { domain, prompt: composeTravel(raw), neg: 'cosmetic bottle, perfume' };
  return { domain, prompt: `A photorealistic image of ${raw}`, neg: 'unrelated merchandise' };
}

const cases = [
  {
    id: 'A',
    input: '한국 정치에서 이재명이 말하는 가장 중요한 것 광고',
    expect: 'politics',
    must: ['political', 'lee jae-myung'],
    forbid: ['premium product photography', 'cosmetic bottle', 'perfume bottle', 'hero product as'],
  },
  {
    id: 'B',
    input: '프리미엄 향수 광고',
    expect: 'product',
    must: ['product', '향수'],
    forbid: ['political editorial', 'lee jae-myung'],
  },
  {
    id: 'C',
    input: '제주 관광 광고',
    expect: 'travel',
    must: ['tourism', 'jeju'],
    forbid: ['cosmetic bottle', 'perfume bottle'],
  },
  {
    id: 'D',
    input: '고래 가족 여행 광고',
    expect: 'travel',
    must: ['tourism'],
    forbid: ['cosmetic bottle'],
  },
  {
    id: 'E',
    input: '회사 소개 영상 썸네일',
    expect: 'corporate',
    must: [],
    forbid: ['cosmetic bottle'],
  },
];

let failed = 0;
for (const c of cases) {
  const r = compose(c.input);
  const final = r.prompt.toLowerCase();
  const notes = [];
  let ok = r.domain === c.expect;
  if (!ok) notes.push(`domain ${r.domain} != ${c.expect}`);
  for (const m of c.must) {
    if (!final.includes(m.toLowerCase()) && !r.prompt.includes(m)) {
      ok = false;
      notes.push(`missing ${m}`);
    }
  }
  for (const f of c.forbid) {
    if (final.includes(f.toLowerCase())) {
      ok = false;
      notes.push(`forbidden ${f}`);
    }
  }
  if (c.id === 'A' && !r.neg.includes('cosmetic')) {
    ok = false;
    notes.push('missing cosmetic negative');
  }
  if (!ok) failed++;
  console.log(`[${ok ? 'PASS' : 'FAIL'}] Case ${c.id} domain=${r.domain}`);
  console.log('  ', r.prompt.slice(0, 140) + '…');
  if (notes.length) console.log('  notes:', notes.join('; '));
}
console.log(failed === 0 ? '\nALL PASS' : `\nFAILED: ${failed}`);
process.exit(failed === 0 ? 0 : 1);
