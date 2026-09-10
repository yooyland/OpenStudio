#!/usr/bin/env python3
"""13.5.3 acceptance gate — golden benchmark A–F on production (compose+generate)."""
from __future__ import annotations

import base64
import json
import sys
from pathlib import Path

import paramiko

PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
REMOTE_ROOT = "applications/hjevjnjenx/public_html"
OUT = Path(__file__).resolve().parents[1] / "scripts" / "_qa-acceptance-gate"
OUT.mkdir(parents=True, exist_ok=True)
OUT_NAME = "after-af.json"

CASES = [
    {
        "id": "A",
        "label": "PREMIUM_STORYBOOK",
        "raw": (
            "어린이를 위한 꿈같은 판타지 장면을 현대적이고 세련된 고급 일러스트 스타일로 그려줘. "
            "거대한 푸른 고래가 밤하늘을 날고 있고, 그 위에 귀여운 팽귄 가족이 함께 타고 세계 여행을 하고 있다. "
            "별이 가득한 하늘, 은은한 달빛, 반짝이는 구름, 오로라 같은 몽환적인 빛을 넣고, "
            "멀리 에펠탑, 피라미드, 빅벤, 자유의 여신상 등 세계 여행을 상징하는 랜드마크가 환상적으로 보이게 해줘. "
            "전체적으로 따뜻하고 감동적이지만 촌스럽지 않게, 디즈니풍 감성보다는 고급 그림책 표지 같은 세련된 분위기, "
            "풍부한 디테일, 아름다운 조명, 깊이감 있는 구도로 표현해줘. "
            "실내 장면처럼 보이지 않게 하고, 벽화처럼 단순한 느낌도 피하고, "
            "실제로 하늘을 나는 모험 장면처럼 웅장하고 아름답게 표현해줘."
        ),
        "aspect_ratio": "16:9",
        "size": "1536x1024",
    },
    {
        "id": "B",
        "label": "BEAUTY",
        "raw": "여름 바닷가 화장품 광고 이미지 만들어줘",
        "aspect_ratio": "4:5",
        "size": "1024x1536",
    },
    {
        "id": "C",
        "label": "ARCHITECTURE",
        "raw": "프리미엄 아파트 분양 광고 이미지 만들어줘",
        "aspect_ratio": "16:9",
        "size": "1536x1024",
    },
    {
        "id": "D",
        "label": "HUMAN_LIFESTYLE",
        "raw": "서울의 현대적인 아파트 단지에서 자연스럽게 이야기하는 30대 부부",
        "aspect_ratio": "3:4",
        "size": "1024x1536",
    },
    {
        "id": "E",
        "label": "PRODUCT",
        "raw": "럭셔리 스킨케어 크림 제품 사진",
        "aspect_ratio": "1:1",
        "size": "1024x1024",
    },
    {
        "id": "F",
        "label": "PORTRAIT",
        "raw": "세련되고 신뢰감 있는 30대 한국 여성의 프리미엄 브랜드 화보",
        "aspect_ratio": "3:4",
        "size": "1024x1536",
    },
]

BENCH_PHP = r'''<?php
require dirname(__DIR__, 2) . '/wp-load.php';
$cases = json_decode(file_get_contents(WP_CONTENT_DIR . '/uploads/_yoy_gate_cases.json'), true);
$users = get_users(array('role' => 'administrator', 'number' => 1));
if (!$users) { echo json_encode(array('error' => 'no admin')); exit(1); }
wp_set_current_user($users[0]->ID);

require_once WP_CONTENT_DIR . '/plugins/yooy-ai-studio/modules/image-studio/includes/prompt-intelligence/class-studio-prompt-intelligence.php';
$intel = new YooY_Studio_Prompt_Intelligence();
$results = array();
$partial = WP_CONTENT_DIR . '/uploads/_yoy_gate_partial.json';

foreach ($cases as $c) {
  $raw = $c['raw'];
  $t0 = microtime(true);
  $run = $intel->run_for_image($raw, array('generation_mode' => 'premium', 'smart_auto' => true));
  $compose_ms = (int) round((microtime(true) - $t0) * 1000);

  $req = new WP_REST_Request('POST', '/yoy-ai-studio/v1/image-studio/generate');
  $req->set_header('Content-Type', 'application/json');
  $body = array(
    'prompt' => $raw,
    'user_prompt' => $raw,
    'raw_user_request' => $raw,
    'provider' => 'openai',
    'model' => 'gpt-image-1',
    'generation_mode' => 'premium',
    'quality' => 'hd',
    'smart_auto' => true,
    'commercial' => true,
    'auto_save' => true,
    'size' => $c['size'],
    'aspect_ratio' => $c['aspect_ratio'],
    'image_count' => 1,
    'output_format' => 'png',
  );
  $req->set_body(wp_json_encode($body));
  foreach ($body as $k => $v) { $req->set_param($k, $v); }
  $t1 = microtime(true);
  $res = rest_do_request($req);
  $gen_ms = (int) round((microtime(true) - $t1) * 1000);
  $data = $res->get_data();
  // Unwrap success envelopes
  if (is_array($data) && isset($data['data']) && is_array($data['data']) && !isset($data['job_id'])) {
    $data = $data['data'];
  }
  $url = '';
  if (is_array($data)) {
    if (!empty($data['output']['primary'])) $url = $data['output']['primary'];
    elseif (!empty($data['images'][0]['url'])) $url = $data['images'][0]['url'];
    elseif (!empty($data['url'])) $url = $data['url'];
  }
  $trace = is_array($data) ? ($data['generation_trace'] ?? ($data['meta']['generation_trace'] ?? array())) : array();
  $pi = is_array($data) ? ($data['composer_meta']['prompt_intelligence'] ?? array()) : array();
  $prov_req = is_array($data) ? ($data['provider_request'] ?? ($data['meta']['provider_request'] ?? ($data['debug']['request'] ?? array()))) : array();
  $row = array(
    'id' => $c['id'],
    'label' => $c['label'],
    'http' => $res->get_status(),
    'version' => defined('YOY_AI_STUDIO_VERSION') ? YOY_AI_STUDIO_VERSION : '',
    'compose_ms' => $compose_ms,
    'generate_ms' => $gen_ms,
    'credits_used' => is_array($data) ? ($data['credits_used'] ?? ($data['credits']['deducted'] ?? null)) : null,
    'url' => $url,
    'job_id' => is_array($data) ? ($data['job_id'] ?? '') : '',
    'title' => is_array($data) ? ($data['display_title'] ?? ($data['title'] ?? ($trace['display_title'] ?? ($run['title_preview'] ?? '')))) : '',
    'intent' => $run['intent_domain'] ?? ($trace['intent_domain'] ?? ''),
    'preset' => $run['preset'] ?? ($trace['art_direction_preset'] ?? ''),
    'final_prompt' => $trace['final_prompt'] ?? ($run['composed_prompt'] ?? ''),
    'final_len' => mb_strlen((string)($trace['final_prompt'] ?? ($run['composed_prompt'] ?? ''))),
    'negative' => $trace['negative_prompt'] ?? ($run['negative_prompt'] ?? ''),
    'provider' => $trace['provider'] ?? (is_array($data) ? ($data['provider'] ?? ($data['provider_used'] ?? '')) : ''),
    'model' => $trace['model'] ?? (is_array($data) ? ($data['model'] ?? '') : ''),
    'quality' => $trace['quality'] ?? (is_array($data) ? ($data['quality'] ?? '') : ''),
    'size' => $trace['size'] ?? (is_array($data) ? ($data['size'] ?? $c['size']) : $c['size']),
    'output_format' => is_array($data) ? ($data['output_format'] ?? ($data['format'] ?? 'png')) : 'png',
    'provider_request' => $prov_req,
    'visual_qa' => $trace['visual_qa'] ?? ($run['visual_qa'] ?? array()),
    'qa_scores' => $trace['qa_scores'] ?? array(),
    'pipeline' => $run['pipeline'] ?? array(),
    'prompt_version' => $run['prompt_version'] ?? '',
    'error' => is_array($data) ? ($data['message'] ?? ($data['error'] ?? '')) : '',
    'response_keys' => is_array($data) ? array_keys($data) : array(),
  );
  // Count bloat keywords in final
  $fp = mb_strtolower((string)$row['final_prompt']);
  $bloat = array();
  foreach (array('premium','luxury','high-end','cinematic','professional','refined','sophisticated','elegant','editorial') as $kw) {
    $n = substr_count($fp, $kw);
    if ($n > 0) $bloat[$kw] = $n;
  }
  $row['bloat_counts'] = $bloat;
  $results[] = $row;
  file_put_contents($partial, wp_json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
file_put_contents(WP_CONTENT_DIR . '/uploads/_yoy_gate_out.json', wp_json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo 'OK ' . count($results);
'''


def main() -> int:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect("158.247.236.125", username="yooyland", password=PASSWORD, timeout=30, banner_timeout=30)
    s = c.open_sftp()

    cases_path = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_gate_cases.json"
    with s.file(cases_path + ".new", "w") as f:
        f.write(json.dumps(CASES, ensure_ascii=False))
    c.exec_command(f"mv -f {cases_path}.new {cases_path}", timeout=20)[1].channel.recv_exit_status()

    php_path = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_gate_bench.php"
    with s.file(php_path + ".new", "w") as f:
        f.write(BENCH_PHP)
    c.exec_command(f"mv -f {php_path}.new {php_path}", timeout=20)[1].channel.recv_exit_status()

    print("running A–F generate (may take several minutes)...")
    stdin, stdout, stderr = c.exec_command(
        f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_gate_bench.php",
        timeout=1200,
    )
    print("status:", stdout.read().decode("utf-8", errors="replace"))
    err = stderr.read().decode("utf-8", errors="replace")
    if err:
        print("stderr:", err[:2000])

    remote_out = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_gate_out.json"
    try:
        with s.file(remote_out, "r") as f:
            data = f.read().decode("utf-8", errors="replace")
        (OUT / OUT_NAME).write_text(data, encoding="utf-8")
        rows = json.loads(data)
        for r in rows:
            print(
                f"{r['id']} http={r.get('http')} preset={r.get('preset')} "
                f"title={r.get('title')} len={r.get('final_len')} "
                f"q={r.get('quality')} size={r.get('size')} url={r.get('url')}"
            )
            print(f"   bloat={r.get('bloat_counts')} credits={r.get('credits_used')} ms={r.get('generate_ms')}")
    except Exception as e:
        print("fetch failed", e)
        # try partial
        try:
            with s.file(f"{REMOTE_ROOT}/wp-content/uploads/_yoy_gate_partial.json", "r") as f:
                (OUT / "after-af-partial.json").write_text(f.read().decode("utf-8", errors="replace"), encoding="utf-8")
                print("saved partial")
        except Exception as e2:
            print("partial failed", e2)

    c.exec_command(
        f"rm -f {php_path} {cases_path} {remote_out} "
        f"{REMOTE_ROOT}/wp-content/uploads/_yoy_gate_partial.json",
        timeout=20,
    )[1].channel.recv_exit_status()
    s.close()
    c.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
