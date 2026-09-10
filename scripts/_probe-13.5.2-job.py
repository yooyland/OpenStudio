#!/usr/bin/env python3
"""Probe 13.5.2 job + redeploy formatter; fetch gallery title/trace."""
from __future__ import annotations

import base64
import json
import sys
from pathlib import Path

import paramiko

PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
REMOTE_ROOT = "applications/hjevjnjenx/public_html"
REMOTE_PLUGIN = f"{REMOTE_ROOT}/wp-content/plugins/yooy-ai-studio/"
ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "scripts" / "_qa-13.5.2"
JOB = "img_bd17b122-48db-4b90-a38b-e70a5bc4b06f"

FILES = [
    ("modules/image-studio/includes/prompt-engine/class-image-prompt-formatter.php",
     "modules/image-studio/includes/prompt-engine/class-image-prompt-formatter.php"),
    ("modules/image-studio/includes/prompt-engine/class-image-prompt-composer.php",
     "modules/image-studio/includes/prompt-engine/class-image-prompt-composer.php"),
]

PROBE = r'''<?php
require dirname(__DIR__, 2) . '/wp-load.php';
$job = trim(file_get_contents(WP_CONTENT_DIR . '/uploads/_yoy_1352_job.txt'));
$users = get_users(array('role' => 'administrator', 'number' => 1));
wp_set_current_user($users[0]->ID);
$uid = $users[0]->ID;
$out = array('job' => $job, 'version' => defined('YOY_AI_STUDIO_VERSION') ? YOY_AI_STUDIO_VERSION : '');

// Image history
$hist = get_user_meta($uid, 'yoy_image_history', true);
if (!is_array($hist)) { $hist = get_user_meta($uid, 'yoy_image_studio_history', true); }
$found = null;
if (is_array($hist)) {
  foreach ($hist as $row) {
    if (!is_array($row)) continue;
    $id = (string)($row['job_id'] ?? $row['id'] ?? '');
    if ($id === $job || strpos(json_encode($row), $job) !== false) { $found = $row; break; }
  }
  if (!$found && $hist) {
    // newest first
    $vals = array_values($hist);
    $found = is_array($vals[0] ?? null) ? $vals[0] : null;
  }
}
$out['history_keys'] = is_array($hist) ? array_slice(array_keys($hist), 0, 5) : array();
$out['history_entry'] = $found;

// Gallery
$gal = array();
if (class_exists('YooY_Gallery_Store')) {
  // try common APIs
}
$req = new WP_REST_Request('GET', '/yoy-ai-studio/v1/gallery');
$req->set_param('type', 'image');
$req->set_param('per_page', 10);
$res = rest_do_request($req);
$data = $res->get_data();
$items = array();
if (is_array($data)) {
  $items = $data['items'] ?? ($data['data']['items'] ?? ($data['gallery'] ?? array()));
}
$match = null;
foreach ((array)$items as $it) {
  if (!is_array($it)) continue;
  $blob = wp_json_encode($it);
  if (strpos($blob, $job) !== false || strpos($blob, 'bd17b122') !== false) { $match = $it; break; }
}
if (!$match && $items) { $match = $items[0]; }
$out['gallery_item'] = $match;
$out['gallery_count'] = is_array($items) ? count($items) : 0;

// Fresh compose for trace sample
$raw = "어린이를 위한 꿈같은 판타지 장면을 현대적이고 세련된 고급 일러스트 스타일로 그려줘. 거대한 푸른 고래가 밤하늘을 날고 있고, 그 위에 귀여운 팽귄 가족이 함께 타고 세계 여행을 하고 있다. 별이 가득한 하늘, 은은한 달빛, 반짝이는 구름, 오로라 같은 몽환적인 빛을 넣고, 멀리 에펠탑, 피라미드, 빅벤, 자유의 여신상 등 세계 여행을 상징하는 랜드마크가 환상적으로 보이게 해줘. 전체적으로 따뜻하고 감동적이지만 촌스럽지 않게, 디즈니풍 감성보다는 고급 그림책 표지 같은 세련된 분위기, 풍부한 디테일, 아름다운 조명, 깊이감 있는 구도로 표현해줘. 실내 장면처럼 보이지 않게 하고, 벽화처럼 단순한 느낌도 피하고, 실제로 하늘을 나는 모험 장면처럼 웅장하고 아름답게 표현해줘.";
require_once WP_CONTENT_DIR . '/plugins/yooy-ai-studio/modules/image-studio/includes/prompt-intelligence/class-studio-prompt-intelligence.php';
$intel = new YooY_Studio_Prompt_Intelligence();
$run = $intel->run_for_image($raw, array('generation_mode' => 'premium'));
$out['compose'] = array(
  'preset' => $run['preset'] ?? '',
  'intent' => $run['intent_domain'] ?? '',
  'title_preview' => $run['title_preview'] ?? '',
  'negative' => $run['negative_prompt'] ?? '',
  'final' => $run['composed_prompt'] ?? '',
  'qa' => $run['visual_qa'] ?? array(),
  'escalator' => $run['quality_escalator'] ?? array(),
  'prompt_version' => $run['prompt_version'] ?? '',
);
file_put_contents(WP_CONTENT_DIR . '/uploads/_yoy_1352_probe.json', wp_json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo 'OK';
'''


def main() -> int:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect("158.247.236.125", username="yooyland", password=PASSWORD, timeout=30)
    s = c.open_sftp()
    for loc, rem in FILES:
        remote = REMOTE_PLUGIN + rem
        with s.file(remote + ".new", "wb") as f:
            f.write((ROOT / loc).read_bytes())
        print("put", rem, c.exec_command(f"mv -f {remote}.new {remote}; wc -c < {remote}", timeout=20)[1].read().decode().strip())

    php = "<?php if (function_exists('opcache_reset')) { opcache_reset(); echo 'opc=1'; } else { echo 'opc=0'; }"
    rp = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_opcache_reset.php"
    with s.file(rp + ".new", "w") as f:
        f.write(php)
    c.exec_command(f"mv -f {rp}.new {rp}", timeout=20)[1].channel.recv_exit_status()
    print(c.exec_command(f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_opcache_reset.php; rm -f wp-content/uploads/_yoy_opcache_reset.php; grep -n \"13.5.2\" {REMOTE_PLUGIN}yoy-ai-studio.php | head -3", timeout=40)[1].read().decode())

    with s.file(f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_job.txt.new", "w") as f:
        f.write(JOB)
    c.exec_command(f"mv -f {REMOTE_ROOT}/wp-content/uploads/_yoy_1352_job.txt.new {REMOTE_ROOT}/wp-content/uploads/_yoy_1352_job.txt", timeout=20)[1].channel.recv_exit_status()
    with s.file(f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_probe.php.new", "w") as f:
        f.write(PROBE)
    c.exec_command(f"mv -f {REMOTE_ROOT}/wp-content/uploads/_yoy_1352_probe.php.new {REMOTE_ROOT}/wp-content/uploads/_yoy_1352_probe.php", timeout=20)[1].channel.recv_exit_status()
    print(c.exec_command(f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_1352_probe.php", timeout=120)[1].read().decode())
    with s.file(f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_probe.json", "r") as f:
        data = f.read().decode("utf-8", errors="replace")
    (OUT / "probe.json").write_text(data, encoding="utf-8")
    j = json.loads(data)
    comp = j.get("compose") or {}
    print("preset", comp.get("preset"), "intent", comp.get("intent"), "title", comp.get("title_preview"))
    print("qa", (comp.get("qa") or {}).get("score"), (comp.get("qa") or {}).get("scores"))
    print("final_len", len(comp.get("final") or ""))
    gal = j.get("gallery_item") or {}
    print("gallery_title", gal.get("title") or gal.get("display_title"), "url", gal.get("url") or gal.get("image_url"))
    he = j.get("history_entry") or {}
    if he:
        print("hist_title", he.get("title") or he.get("display_title"))
        print("hist_preset", (he.get("generation_trace") or {}).get("art_direction_preset") or he.get("preset"))
        print("hist_trace_keys", list((he.get("generation_trace") or {}).keys())[:20])
    c.exec_command(
        f"rm -f {REMOTE_ROOT}/wp-content/uploads/_yoy_1352_probe.php "
        f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_job.txt "
        f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_probe.json",
        timeout=20,
    )[1].channel.recv_exit_status()
    s.close()
    c.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
