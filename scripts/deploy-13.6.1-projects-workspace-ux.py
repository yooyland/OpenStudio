#!/usr/bin/env python3
"""Atomic deploy of 13.6.1 Projects / Workspace UX cleanup."""
import base64
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
REMOTE_PLUGIN = "applications/hjevjnjenx/public_html/wp-content/plugins/yooy-ai-studio/"
REMOTE_ROOT = "applications/hjevjnjenx/public_html"

FILES = [
    ("plugin/yooy-ai-studio/yoy-ai-studio.php", "yoy-ai-studio.php"),
    ("plugin/yooy-ai-studio/templates/studio-shell.php", "templates/studio-shell.php"),
    ("plugin/yooy-ai-studio/assets/js/studio.js", "assets/js/studio.js"),
    ("plugin/yooy-ai-studio/assets/css/studio.css", "assets/css/studio.css"),
    ("plugin/yooy-ai-studio/assets/css/spacious.css", "assets/css/spacious.css"),
    (
        "plugin/yooy-ai-studio/assets/modules/shared/studio-navigation.js",
        "assets/modules/shared/studio-navigation.js",
    ),
]


def atomic_put(sftp, client, local: Path, remote: str) -> None:
    tmp = remote + ".new"
    parent = "/".join(remote.split("/")[:-1])
    client.exec_command(f"mkdir -p {parent}", timeout=20)[1].channel.recv_exit_status()
    with sftp.file(tmp, "wb") as f:
        f.write(local.read_bytes())
        f.flush()
    out = client.exec_command(
        f"mv -f {tmp} {remote}; touch {remote}; wc -c < {remote}", timeout=30
    )[1].read().decode().strip()
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
            f"cd {REMOTE_ROOT}; php wp-content/uploads/_yoy_opcache_reset.php; "
            f"rm -f wp-content/uploads/_yoy_opcache_reset.php; "
            f"grep -n \"13.6.1\" {REMOTE_PLUGIN}yoy-ai-studio.php | head -3; "
            f"grep -c \"yai-workspace-overview--empty\" {REMOTE_PLUGIN}assets/js/studio.js; "
            f"grep -c \"yai-project-card-cover--empty\" {REMOTE_PLUGIN}assets/css/studio.css; "
            f"grep -c \"yai-back-btn\" {REMOTE_PLUGIN}templates/studio-shell.php || true",
            timeout=40,
        )[1].read().decode()
    )
    s.close()
    c.close()


if __name__ == "__main__":
    main()
