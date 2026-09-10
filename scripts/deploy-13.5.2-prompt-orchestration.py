#!/usr/bin/env python3
"""Atomic deploy of 13.5.2 premium prompt orchestration hardening."""
import base64
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
REMOTE_PLUGIN = "applications/hjevjnjenx/public_html/wp-content/plugins/yooy-ai-studio/"
REMOTE_ROOT = "applications/hjevjnjenx/public_html"

FILES = [
    ("plugin/yooy-ai-studio/yoy-ai-studio.php", "yoy-ai-studio.php"),
    ("plugin/yooy-ai-studio/assets/modules/image-studio/image-studio.js", "assets/modules/image-studio/image-studio.js"),
    ("modules/gallery/includes/class-gallery-title-service.php", "modules/gallery/includes/class-gallery-title-service.php"),
    ("modules/image-studio/includes/class-image-generator.php", "modules/image-studio/includes/class-image-generator.php"),
    ("modules/image-studio/includes/prompt-engine/class-image-prompt-composer.php", "modules/image-studio/includes/prompt-engine/class-image-prompt-composer.php"),
    ("modules/image-studio/includes/prompt-engine/class-image-prompt-formatter.php", "modules/image-studio/includes/prompt-engine/class-image-prompt-formatter.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-art-direction.php", "modules/image-studio/includes/prompt-intelligence/class-image-art-direction.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-domain-prompt-composer.php", "modules/image-studio/includes/prompt-intelligence/class-image-domain-prompt-composer.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-visual-qa.php", "modules/image-studio/includes/prompt-intelligence/class-image-visual-qa.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-studio-intent-analyzer.php", "modules/image-studio/includes/prompt-intelligence/class-studio-intent-analyzer.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-studio-prompt-intelligence.php", "modules/image-studio/includes/prompt-intelligence/class-studio-prompt-intelligence.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-prompt-orchestrator.php", "modules/image-studio/includes/prompt-intelligence/class-image-prompt-orchestrator.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-input-normalizer.php", "modules/image-studio/includes/prompt-intelligence/class-image-input-normalizer.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-subject-scene-extractor.php", "modules/image-studio/includes/prompt-intelligence/class-image-subject-scene-extractor.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-quality-escalator.php", "modules/image-studio/includes/prompt-intelligence/class-image-quality-escalator.php"),
]


def atomic_put(sftp, client, local: Path, remote: str) -> None:
    tmp = remote + ".new"
    parent = "/".join(remote.split("/")[:-1])
    client.exec_command(f"mkdir -p {parent}", timeout=20)[1].channel.recv_exit_status()
    with sftp.file(tmp, "wb") as f:
        f.write(local.read_bytes())
        f.flush()
    out = client.exec_command(f"mv -f {tmp} {remote}; touch {remote}; wc -c < {remote}", timeout=30)[1].read().decode().strip()
    print(f"OK {remote} bytes={out}")


def main() -> None:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect("158.247.236.125", username="yooyland", password=PASSWORD, timeout=25)
    s = c.open_sftp()
    for loc, rem in FILES:
        atomic_put(s, c, ROOT / loc, REMOTE_PLUGIN + rem)
    php = "<?php if (function_exists('opcache_reset')) { opcache_reset(); echo 'opc=1'; } else { echo 'opc=0'; }"
    rp = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_opcache_reset.php"
    with s.file(rp + ".new", "w") as f:
        f.write(php)
    c.exec_command(f"mv -f {rp}.new {rp}", timeout=20)[1].channel.recv_exit_status()
    print(
        c.exec_command(
            f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_opcache_reset.php; rm -f wp-content/uploads/_yoy_opcache_reset.php; grep -n \"13.5.2\" {REMOTE_PLUGIN}yoy-ai-studio.php | head -3",
            timeout=40,
        )[1].read().decode()
    )
    s.close()
    c.close()


if __name__ == "__main__":
    main()
