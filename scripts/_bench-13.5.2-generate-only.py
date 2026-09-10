#!/usr/bin/env python3
"""13.5.2 generate-only regression (compose already verified)."""
from __future__ import annotations

import base64
import json
import sys
from pathlib import Path

import paramiko

PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
REMOTE_ROOT = "applications/hjevjnjenx/public_html"
OUT_DIR = Path(__file__).resolve().parents[1] / "scripts" / "_qa-13.5.2"
OUT_DIR.mkdir(parents=True, exist_ok=True)

RAW = (
    "어린이를 위한 꿈같은 판타지 장면을 현대적이고 세련된 고급 일러스트 스타일로 그려줘. "
    "거대한 푸른 고래가 밤하늘을 날고 있고, 그 위에 귀여운 팽귄 가족이 함께 타고 세계 여행을 하고 있다. "
    "별이 가득한 하늘, 은은한 달빛, 반짝이는 구름, 오로라 같은 몽환적인 빛을 넣고, "
    "멀리 에펠탑, 피라미드, 빅벤, 자유의 여신상 등 세계 여행을 상징하는 랜드마크가 환상적으로 보이게 해줘. "
    "전체적으로 따뜻하고 감동적이지만 촌스럽지 않게, 디즈니풍 감성보다는 고급 그림책 표지 같은 세련된 분위기, "
    "풍부한 디테일, 아름다운 조명, 깊이감 있는 구도로 표현해줘. "
    "실내 장면처럼 보이지 않게 하고, 벽화처럼 단순한 느낌도 피하고, "
    "실제로 하늘을 나는 모험 장면처럼 웅장하고 아름답게 표현해줘."
)

GEN_PHP = r'''<?php
require dirname(__DIR__, 2) . '/wp-load.php';
$raw = file_get_contents(WP_CONTENT_DIR . '/uploads/_yoy_1352_raw.txt');
$users = get_users(array('role' => 'administrator', 'number' => 1));
if (!$users) { echo json_encode(array('error' => 'no admin')); exit(1); }
wp_set_current_user($users[0]->ID);
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
  'size' => '1536x1024',
  'aspect_ratio' => '16:9',
  'image_count' => 1,
  'output_format' => 'png',
);
$req->set_body(wp_json_encode($body));
foreach ($body as $k => $v) { $req->set_param($k, $v); }
$res = rest_do_request($req);
$data = $res->get_data();
$url = '';
if (is_array($data)) {
  if (!empty($data['output']['primary'])) $url = $data['output']['primary'];
  elseif (!empty($data['images'][0]['url'])) $url = $data['images'][0]['url'];
  elseif (!empty($data['data']['output']['primary'])) $url = $data['data']['output']['primary'];
  elseif (!empty($data['data']['images'][0]['url'])) $url = $data['data']['images'][0]['url'];
}
$trace = is_array($data) ? ($data['generation_trace'] ?? ($data['meta']['generation_trace'] ?? ($data['data']['generation_trace'] ?? array()))) : array();
$pi = is_array($data) ? ($data['composer_meta']['prompt_intelligence'] ?? ($data['data']['composer_meta']['prompt_intelligence'] ?? array())) : array();
$out = array(
  'http' => $res->get_status(),
  'url' => $url,
  'job_id' => is_array($data) ? ($data['job_id'] ?? ($data['data']['job_id'] ?? '')) : '',
  'title' => is_array($data) ? ($data['display_title'] ?? ($data['title'] ?? ($data['data']['title'] ?? ($trace['display_title'] ?? '')))) : '',
  'provider' => $trace['provider'] ?? (is_array($data) ? ($data['provider'] ?? '') : ''),
  'model' => $trace['model'] ?? (is_array($data) ? ($data['model'] ?? '') : ''),
  'quality' => $trace['quality'] ?? '',
  'size' => $trace['size'] ?? '',
  'preset' => $trace['art_direction_preset'] ?? ($pi['preset'] ?? ''),
  'intent' => $trace['intent_domain'] ?? ($pi['intent_domain'] ?? ''),
  'negative' => $trace['negative_prompt'] ?? '',
  'final_prompt' => $trace['final_prompt'] ?? (is_array($data) ? ($data['prompt'] ?? '') : ''),
  'normalized_prompt' => $trace['normalized_prompt'] ?? '',
  'visual_qa' => $trace['visual_qa'] ?? (is_array($data) ? ($data['visual_qa'] ?? array()) : array()),
  'qa_scores' => $trace['qa_scores'] ?? array(),
  'error' => is_array($data) ? ($data['message'] ?? ($data['error'] ?? '')) : '',
);
file_put_contents(WP_CONTENT_DIR . '/uploads/_yoy_1352_gen_out.json', wp_json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo 'OK';
'''


def main() -> int:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect("158.247.236.125", username="yooyland", password=PASSWORD, timeout=30, banner_timeout=30)
    sftp = c.open_sftp()

    local = Path(__file__).resolve().parents[1] / "modules/image-studio/includes/prompt-intelligence/class-image-visual-qa.php"
    remote = "applications/hjevjnjenx/public_html/wp-content/plugins/yooy-ai-studio/modules/image-studio/includes/prompt-intelligence/class-image-visual-qa.php"
    with sftp.file(remote + ".new", "wb") as f:
        f.write(local.read_bytes())
    c.exec_command(f"mv -f {remote}.new {remote}", timeout=20)[1].channel.recv_exit_status()
    php = "<?php if (function_exists('opcache_reset')) { opcache_reset(); echo 'opc=1'; } else { echo 'opc=0'; }"
    rp = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_opcache_reset.php"
    with sftp.file(rp + ".new", "w") as f:
        f.write(php)
    c.exec_command(f"mv -f {rp}.new {rp}", timeout=20)[1].channel.recv_exit_status()
    print(c.exec_command(f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_opcache_reset.php; rm -f wp-content/uploads/_yoy_opcache_reset.php", timeout=30)[1].read().decode())

    raw_path = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_raw.txt"
    with sftp.file(raw_path + ".new", "w") as f:
        f.write(RAW)
    c.exec_command(f"mv -f {raw_path}.new {raw_path}", timeout=20)[1].channel.recv_exit_status()

    gen_php = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_gen.php"
    with sftp.file(gen_php + ".new", "w") as f:
        f.write(GEN_PHP)
    c.exec_command(f"mv -f {gen_php}.new {gen_php}", timeout=20)[1].channel.recv_exit_status()

    stdin, stdout, stderr = c.exec_command(
        f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_1352_gen.php",
        timeout=360,
    )
    print("status:", stdout.read().decode("utf-8", errors="replace"))
    err = stderr.read().decode("utf-8", errors="replace")
    if err:
        print("stderr:", err[:1500])

    remote_out = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_gen_out.json"
    try:
        with sftp.file(remote_out, "r") as f:
            data = f.read().decode("utf-8", errors="replace")
        (OUT_DIR / "generate.json").write_text(data, encoding="utf-8")
        print("saved generate.json")
        j = json.loads(data)
        print("http", j.get("http"), "url", j.get("url"), "title", j.get("title"), "preset", j.get("preset"))
        print("qa", j.get("visual_qa", {}).get("score"), j.get("qa_scores"))
    except Exception as e:
        print("fetch out failed", e)

    c.exec_command(
        f"rm -f {gen_php} {raw_path} {remote_out}",
        timeout=20,
    )[1].channel.recv_exit_status()
    sftp.close()
    c.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
