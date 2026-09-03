/* Phase 3 catalog smoke. Run: node scripts/test-creation-catalog.js */
var fs = require('fs');
var vm = require('vm');
var path = require('path');
var code = fs.readFileSync(path.join(__dirname, '../plugin/yooy-ai-studio/assets/js/creation-catalog.js'), 'utf8');
var ctx = { window: {} };
ctx.window = ctx;
vm.runInNewContext(code, ctx);
var C = ctx.YooYCreationCatalog;
var fails = 0;
function ok(name, cond) {
  if (!cond) { fails += 1; console.error('FAIL', name); }
  else console.log('OK', name);
}
ok('templates exist', C.TEMPLATES.length >= 10);
ok('product ad', C.byId('tpl-product-ad') && C.byId('tpl-product-ad').studio === 'image');
ok('blog writing', C.byId('tpl-blog').studio === 'writing');
ok('bgm music', C.byId('tpl-brand-bgm').studio === 'music');
ok('no remove-bg tool', !C.toolById('tool-remove-bg'));
ok('translate tool', C.toolById('tool-translate').studio === 'translator');
ok('narration tool', C.toolById('tool-narration').studio === 'voice');
ok('summarize writing', C.toolById('tool-summarize').studio === 'writing');
ok('photo video', C.byId('tpl-photo-video').studio === 'video');
ok('featured home', C.featured(4).length === 4);
ok('fill prompt', C.fillPrompt(C.byId('tpl-blog'), { topic: '화장품', style_hint: '친절하게' }).indexOf('화장품') >= 0);
if (fails) process.exit(1);
console.log('creation-catalog: all cases passed');
