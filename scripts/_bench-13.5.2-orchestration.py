#!/usr/bin/env python3
"""13.5.2 compose + generate regression for premium fantasy/storybook prompt."""
from __future__ import annotations

import base64
import json
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

COMPOSE_PHP = r'''<?php
require dirname(__DIR__, 2) . '/wp-load.php';
$raw = file_get_contents(WP_CONTENT_DIR . '/uploads/_yoy_1352_raw.txt');
$users = get_users(array('role' => 'administrator', 'number' => 1));
if (!$users) { echo json_encode(array('error' => 'no admin')); exit(1); }
wp_set_current_user($users[0]->ID);

$intel_file = WP_PLUGIN_DIR . '/yooy-ai-studio/modules/image-studio/includes/prompt-intelligence/class-studio-prompt-intelligence.php';
if (!file_exists($intel_file)) {
  $intel_file = WP_CONTENT_DIR . '/plugins/yooy-ai-studio/modules/image-studio/includes/prompt-intelligence/class-studio-prompt-intelligence.php';
}
require_once $intel_file;
$intel = new YooY_Studio_Prompt_Intelligence();
$run = $intel->run_for_image($raw, array('generation_mode' => 'premium', 'smart_auto' => true));

$composer_file = WP_PLUGIN_DIR . '/yooy-ai-studio/modules/image-studio/includes/prompt-engine/class-image-prompt-composer.php';
if (!file_exists($composer_file)) {
  $composer_file = WP_CONTENT_DIR . '/plugins/yooy-ai-studio/modules/image-studio/includes/prompt-engine/class-image-prompt-composer.php';
}
require_once $composer_file;
$composer = new YooY_Image_Prompt_Composer();
$composed = $composer->compose(array(
  'user_prompt' => $raw,
  'prompt' => $raw,
  'smart_auto' => true,
  'generation_mode' => 'premium',
  'quality' => 'hd',
  'commercial' => true,
  'size' => '1536x1024',
  'aspect_ratio' => '16:9',
));

$out = array(
  'version' => defined('YOY_AI_STUDIO_VERSION') ? YOY_AI_STUDIO_VERSION : '',
  'intent_domain' => $run['intent_domain'] ?? '',
  'preset' => $run['preset'] ?? '',
  'escalator' => $run['quality_escalator'] ?? array(),
  'pipeline' => $run['pipeline'] ?? array(),
  'normalized' => $run['normalized'] ?? array(),
  'negative_prompt' => $run['negative_prompt'] ?? ($composed['negative_prompt'] ?? ''),
  'final_prompt' => $composed['prompt'] ?? ($run['composed_prompt'] ?? ''),
  'canonical_prompt' => $composed['canonical_prompt'] ?? '',
  'title_preview' => $run['title_preview'] ?? '',
  'visual_qa' => $run['visual_qa'] ?? array(),
  'prompt_version' => $run['prompt_version'] ?? '',
  'composer_pi' => $composed['meta']['prompt_intelligence'] ?? array(),
);
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
'''

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
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
'''


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect("158.247.236.125", username="yooyland", password=PASSWORD, timeout=30, banner_timeout=30)
    sftp = c.open_sftp()

    raw_path = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_raw.txt"
    with sftp.file(raw_path + ".new", "w") as f:
        f.write(RAW)
    c.exec_command(f"mv -f {raw_path}.new {raw_path}", timeout=20)[1].channel.recv_exit_status()

    compose_php = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_compose.php"
    with sftp.file(compose_php + ".new", "w") as f:
        f.write(COMPOSE_PHP)
    c.exec_command(f"mv -f {compose_php}.new {compose_php}", timeout=20)[1].channel.recv_exit_status()

    stdin, stdout, stderr = c.exec_command(
        f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_1352_compose.php",
        timeout=120,
    )
    compose_out = stdout.read().decode("utf-8", errors="replace")
    compose_err = stderr.read().decode("utf-8", errors="replace")
    (OUT_DIR / "compose.json").write_text(compose_out or compose_err, encoding="utf-8")
    print("=== COMPOSE ===")
    print(compose_out[:4000] if compose_out else compose_err[:2000])

    gen_php = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_gen.php"
    with sftp.file(gen_php + ".new", "w") as f:
        f.write(GEN_PHP)
    c.exec_command(f"mv -f {gen_php}.new {gen_php}", timeout=20)[1].channel.recv_exit_status()

    stdin, stdout, stderr = c.exec_command(
        f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_1352_gen.php",
        timeout=300,
    )
    gen_out = stdout.read().decode("utf-8", errors="replace")
    gen_err = stderr.read().decode("utf-8", errors="replace")
    (OUT_DIR / "generate.json").write_text(gen_out or gen_err, encoding="utf-8")
    print("=== GENERATE ===")
    print(gen_out[:4000] if gen_out else gen_err[:2000])

    c.exec_command(
        f"rm -f {REMOTE_ROOT}/wp-content/uploads/_yoy_1352_compose.php "
        f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_gen.php "
        f"{REMOTE_ROOT}/wp-content/uploads/_yoy_1352_raw.txt",
        timeout=20,
    )[1].channel.recv_exit_status()
    sftp.close()
    c.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
