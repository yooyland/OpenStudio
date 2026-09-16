#!/usr/bin/env python3
"""Bench beauty orchestration A/B/C on production via wp-load."""
import base64
import paramiko

PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
REMOTE_ROOT = "applications/hjevjnjenx/public_html"

BENCH_PHP = r'''<?php
require_once __DIR__ . "/../../../wp-load.php";
$base = WP_CONTENT_DIR . "/plugins/yooy-ai-studio/modules/image-studio/includes/prompt-intelligence/";
$engine = WP_CONTENT_DIR . "/plugins/yooy-ai-studio/modules/image-studio/includes/prompt-engine/";
$gallery = WP_CONTENT_DIR . "/plugins/yooy-ai-studio/modules/gallery/includes/";
foreach ([
  $engine . "class-image-emotion-engine.php",
  $base . "class-image-art-direction.php",
  $base . "class-studio-intent-analyzer.php",
  $base . "class-studio-creative-brief-builder.php",
  $base . "class-image-input-normalizer.php",
  $base . "class-image-subject-scene-extractor.php",
  $base . "class-image-quality-escalator.php",
  $base . "class-image-composition-planner.php",
  $base . "class-image-prompt-fidelity.php",
  $base . "class-image-domain-prompt-composer.php",
  $base . "class-studio-prompt-validator.php",
  $base . "class-image-visual-qa.php",
  $base . "class-image-prompt-orchestrator.php",
  $gallery . "class-gallery-title-service.php",
] as $f) {
  if (file_exists($f)) require_once $f;
}
$cases = [
  "A" => "'VVR' 브랜드 안티에이징 화장품 광고 포스터를 만들어 줘",
  "B" => "VVR 안티에이징 크림 제품만 고급 광고컷으로 만들어 줘",
  "C" => "피부가 좋아 보이는 모델과 VVR 안티에이징 크림이 함께 보이는 럭셔리 광고 포스터",
];
$out = [];
$emo = new YooY_Image_Emotion_Engine();
$orch = new YooY_Image_Prompt_Orchestrator();
foreach ($cases as $id => $prompt) {
  $e = $emo->analyze($prompt);
  $r = $orch->run($prompt, ["generation_mode" => "premium"]);
  $fp = (string)($r["composed_prompt"] ?? "");
  $out[$id] = [
    "prompt" => $prompt,
    "emotion_primary" => $e["primary"] ?? "",
    "emotion_mood" => $e["mood"] ?? "",
    "domain" => $r["intent_domain"] ?? "",
    "brief_domain" => $r["creative_brief"]["content_domain"] ?? "",
    "brand_token" => $r["creative_brief"]["brand_token"] ?? "",
    "beauty_mode" => $r["creative_brief"]["beauty_mode"] ?? "",
    "tone" => $r["creative_brief"]["tone"] ?? "",
    "title" => $r["title_preview"] ?? "",
    "preset" => $r["preset"] ?? "",
    "final_prompt_head" => mb_substr($fp, 0, 480),
    "has_model_language" => (bool) preg_match("/model|luminous|skin|campaign poster|advertising poster/i", $fp),
    "has_blank_packaging" => (bool) preg_match("/blank unbranded/i", $fp),
    "has_brand_mark" => (bool) preg_match("/VVR|brand token|BRAND MARK/i", $fp),
    "anger_leak" => (bool) preg_match("/anger|rage|hostile/i", ($e["primary"] ?? "") . " " . ($e["mood"] ?? "")),
  ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
'''


def atomic_put(sftp, client, content: str, remote: str) -> None:
    tmp = remote + ".new"
    parent = "/".join(remote.split("/")[:-1])
    client.exec_command(f"mkdir -p {parent}", timeout=20)[1].channel.recv_exit_status()
    with sftp.file(tmp, "w") as f:
        f.write(content)
    client.exec_command(f"mv -f {tmp} {remote}", timeout=20)[1].channel.recv_exit_status()


def main():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect("158.247.236.125", username="yooyland", password=PASSWORD, timeout=25)
    s = c.open_sftp()
    path = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_beauty_bench.php"
    atomic_put(s, c, BENCH_PHP, path)
    out = c.exec_command(
        f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_beauty_bench.php; rm -f wp-content/uploads/_yoy_beauty_bench.php",
        timeout=90,
    )
    print(out[1].read().decode("utf-8", "replace"))
    err = out[2].read().decode("utf-8", "replace")
    if err.strip():
        print("STDERR:", err[:2000])
    s.close()
    c.close()


if __name__ == "__main__":
    main()
