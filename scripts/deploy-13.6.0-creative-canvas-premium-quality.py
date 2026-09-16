#!/usr/bin/env python3
"""Atomic deploy of 13.6.0 Creative Canvas + Premium Quality v2."""
import base64
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
REMOTE_PLUGIN = "applications/hjevjnjenx/public_html/wp-content/plugins/yooy-ai-studio/"
REMOTE_ROOT = "applications/hjevjnjenx/public_html"

FILES = [
    ("plugin/yooy-ai-studio/yoy-ai-studio.php", "yoy-ai-studio.php"),
    ("plugin/yooy-ai-studio/includes/class-yoy-ai-studio.php", "includes/class-yoy-ai-studio.php"),
    ("plugin/yooy-ai-studio/assets/js/core.js", "assets/js/core.js"),
    ("plugin/yooy-ai-studio/assets/js/studio.js", "assets/js/studio.js"),
    ("plugin/yooy-ai-studio/assets/js/creative-canvas.js", "assets/js/creative-canvas.js"),
    ("plugin/yooy-ai-studio/assets/css/creative-canvas.css", "assets/css/creative-canvas.css"),
    ("plugin/yooy-ai-studio/assets/modules/gallery/gallery.js", "assets/modules/gallery/gallery.js"),
    ("plugin/yooy-ai-studio/assets/modules/image-studio/image-studio.js", "assets/modules/image-studio/image-studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/ai-assistant/ai-assistant.js", "assets/modules/ai-assistant/ai-assistant.js"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-composition-planner.php", "modules/image-studio/includes/prompt-intelligence/class-image-composition-planner.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-prompt-orchestrator.php", "modules/image-studio/includes/prompt-intelligence/class-image-prompt-orchestrator.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-art-direction.php", "modules/image-studio/includes/prompt-intelligence/class-image-art-direction.php"),
    ("modules/image-studio/includes/prompt-intelligence/class-image-quality-escalator.php", "modules/image-studio/includes/prompt-intelligence/class-image-quality-escalator.php"),
    ("modules/gallery/includes/class-gallery-title-service.php", "modules/gallery/includes/class-gallery-title-service.php"),
    ("modules/projects/includes/class-project-store.php", "modules/projects/includes/class-project-store.php"),
    ("modules/projects/includes/class-project-canvas-store.php", "modules/projects/includes/class-project-canvas-store.php"),
    ("modules/projects/includes/class-projects-rest.php", "modules/projects/includes/class-projects-rest.php"),
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
            f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_opcache_reset.php; rm -f wp-content/uploads/_yoy_opcache_reset.php; grep -n \"13.6.0\" {REMOTE_PLUGIN}yoy-ai-studio.php | head -3; test -f {REMOTE_PLUGIN}assets/js/creative-canvas.js && echo canvas_js=ok; test -f {REMOTE_PLUGIN}modules/image-studio/includes/prompt-intelligence/class-image-composition-planner.php && echo composition=ok",
            timeout=40,
        )[1].read().decode()
    )
    s.close()
    c.close()


if __name__ == "__main__":
    main()
