/**
 * Home Auto Studio intent — heuristic reuse of existing studio keywords.
 * Not a second AI Router. Attachment type + prompt decide the Studio.
 */
(function (global) {
  'use strict';

  var STUDIOS = ['image', 'video', 'writing', 'music', 'voice', 'avatar', 'translator'];

  function textOf(prompt) {
    return String(prompt || '').toLowerCase();
  }

  function attachKind(attachment) {
    if (!attachment || !attachment.type) return '';
    var t = String(attachment.type);
    if (t === 'image' || t === 'gallery-image') return 'image';
    if (t === 'file' || t === 'document') return 'file';
    if (t === 'url' || t === 'website') return 'url';
    return t;
  }

  function scorePrompt(t) {
    var hits = [];
    if (/번역|translate|영문|영어로|일본어|중국어|스페인어|프랑스어|독일어/.test(t)) hits.push('translator');
    if (/영상|비디오|video|유튜브|youtube|릴스|reels|쇼츠|shorts|뮤직비디오|\bmv\b|움직이게|영상으로/.test(t)) hits.push('video');
    if (/음악|music|bgm|song|뮤직|멜로디|피아노|사운드트랙/.test(t)) hits.push('music');
    if (/음성|voice|tts|나레이션|더빙|보이스|읽어줘|읽어 줘/.test(t)) hits.push('voice');
    if (/아바타|avatar|버추얼|캐릭터/.test(t)) hits.push('avatar');
    if (/글쓰기|writing|블로그|blog|카피|copy|스크립트|script|원고|소개글|요약|문구/.test(t)) hits.push('writing');
    if (/이미지|image|포스터|그림|사진|제품샷|광고 이미지/.test(t)) hits.push('image');
    return hits;
  }

  function isVague(t) {
    if (!t || t.length < 4) return true;
    if (scorePrompt(t).length) return false;
    return /멋있게|예쁘게|이쁘게|잘 만들|이거|이것|만들어줘|만들어 줘/.test(t) && t.length < 40;
  }

  function resolve(prompt, attachment) {
    var t = textOf(prompt);
    var kind = attachKind(attachment);
    var hits = scorePrompt(t);
    var studio = '';
    var confidence = 'low';
    var ambiguous = false;

    if (kind === 'image' && /영상|video|쇼츠|shorts|움직이게|영상으로/.test(t)) {
      studio = 'video';
      confidence = 'high';
    } else if (kind === 'file' && /번역|translate/.test(t)) {
      studio = 'translator';
      confidence = 'high';
    } else if (kind === 'file' && /요약|글|블로그|정리/.test(t)) {
      studio = 'writing';
      confidence = 'high';
    } else if (kind === 'url' && /번역|translate/.test(t)) {
      studio = 'translator';
      confidence = 'high';
    } else if (kind === 'url' && (/요약|글|블로그|정리|써/.test(t) || !hits.length)) {
      studio = /번역/.test(t) ? 'translator' : 'writing';
      confidence = 'high';
    } else if (hits.indexOf('translator') >= 0) {
      studio = 'translator';
      confidence = 'high';
    } else if (hits.indexOf('video') >= 0) {
      studio = 'video';
      confidence = 'high';
    } else if (hits.indexOf('music') >= 0) {
      studio = 'music';
      confidence = 'high';
    } else if (hits.indexOf('voice') >= 0) {
      studio = 'voice';
      confidence = 'high';
    } else if (hits.indexOf('avatar') >= 0) {
      studio = 'avatar';
      confidence = 'high';
    } else if (hits.indexOf('writing') >= 0) {
      studio = 'writing';
      confidence = 'high';
    } else if (hits.indexOf('image') >= 0) {
      studio = 'image';
      confidence = 'high';
    } else if (kind === 'image') {
      studio = 'image';
      confidence = 'medium';
    } else if (kind === 'file') {
      studio = 'writing';
      confidence = 'medium';
    } else if (kind === 'url') {
      studio = 'writing';
      confidence = 'medium';
    } else if (isVague(t) && !kind) {
      ambiguous = true;
      studio = '';
      confidence = 'low';
    } else {
      studio = 'image';
      confidence = t ? 'medium' : 'low';
    }

    var unique = [];
    hits.forEach(function (h) {
      if (unique.indexOf(h) < 0) unique.push(h);
    });
    if (!ambiguous && unique.length >= 3 && confidence !== 'high') {
      ambiguous = true;
    }

    return {
      studio: studio,
      confidence: confidence,
      ambiguous: ambiguous,
      candidates: ambiguous ? ['image', 'video', 'writing'] : (studio ? [studio] : ['image', 'video', 'writing'])
    };
  }

  function isKnownStudio(name) {
    return STUDIOS.indexOf(name) >= 0;
  }

  global.YooYHomeIntent = {
    resolve: resolve,
    isKnownStudio: isKnownStudio,
    studios: STUDIOS.slice()
  };
})(typeof window !== 'undefined' ? window : this);
