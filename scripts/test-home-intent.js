/* Phase 2 routing cases A–F, J. Run: node scripts/test-home-intent.js */
var fs = require('fs');
var vm = require('vm');
var path = require('path');
var code = fs.readFileSync(path.join(__dirname, '../plugin/yooy-ai-studio/assets/js/home-intent.js'), 'utf8');
var ctx = { window: {} };
ctx.window = ctx;
vm.runInNewContext(code, ctx);
var resolve = ctx.YooYHomeIntent.resolve;
var fails = 0;
function expect(name, prompt, att, studio, ambiguous) {
  var r = resolve(prompt, att);
  var ok = r.studio === studio && !!r.ambiguous === !!ambiguous;
  if (!ok) {
    fails += 1;
    console.error('FAIL', name, r);
  } else {
    console.log('OK', name, '→', r.studio, r.ambiguous ? '(ambiguous)' : '');
  }
}
expect('A', '고급 화장품 광고 이미지 만들어줘', null, 'image', false);
expect('B', '15초 쇼츠 광고 영상 만들어줘', null, 'video', false);
expect('C', '이 제품 소개글 써줘', null, 'writing', false);
expect('D', '잔잔한 피아노 BGM 만들어줘', null, 'music', false);
expect('E', '이 문장을 여성 목소리로 읽어줘', null, 'voice', false);
expect('F', '영어로 번역해줘', null, 'translator', false);
expect('G', '이 사진을 광고 포스터처럼 만들어줘', { type: 'image' }, 'image', false);
expect('H', '이 사진으로 10초 영상 만들어줘', { type: 'image' }, 'video', false);
expect('J', '이걸 멋있게 만들어줘', null, '', true);
expect('K', '이 내용을 요약해서 글로 만들어줘', { type: 'url', url: 'https://example.com' }, 'writing', false);
if (fails) process.exit(1);
console.log('home-intent: all cases passed');
