#!/usr/bin/env python3
"""Deploy Phase 6.5 visual polish (13.0.4) via SSH atomic mv."""
from __future__ import annotations

import base64
import time
import urllib.request
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
HOST = "158.247.236.125"
USER = "yooyland"
REMOTE_REL = "applications/hjevjnjenx/public_html/wp-content/plugins/yooy-ai-studio"

FILES = [
    ("plugin/yooy-ai-studio/yoy-ai-studio.php", "yoy-ai-studio.php"),
    ("plugin/yooy-ai-studio/assets/css/home-dashboard.css", "assets/css/home-dashboard.css"),
    ("plugin/yooy-ai-studio/assets/js/home-dashboard.js", "assets/js/home-dashboard.js"),
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=25)
    sftp = c.open_sftp()

    for local_rel, remote_rel in FILES:
        data = (ROOT / local_rel).read_bytes()
        remote_final = f"{REMOTE_REL}/{remote_rel}".replace("\\", "/")
        remote_tmp = remote_final + ".new"
        parent = "/".join(remote_final.split("/")[:-1])
        c.exec_command(f"mkdir -p {parent}", timeout=20)[1].channel.recv_exit_status()
        with sftp.file(remote_tmp, "wb") as f:
            f.write(data)
            f.flush()
        stdin, stdout, stderr = c.exec_command(
            f"mv -f {remote_tmp} {remote_final} && stat -c '%s %Y' {remote_final}",
            timeout=20,
        )
        print(f"OK {remote_rel} -> {stdout.read().decode('utf-8', 'replace').strip()}")

    stdin, stdout, stderr = c.exec_command(
        f"grep -E \"Version:|YOY_AI_STUDIO_VERSION\" {REMOTE_REL}/yoy-ai-studio.php; "
        f"grep -n \"aspect-ratio: 4 / 5.5\\|line-clamp: 2\\|min-height: 30px\\|translateY(-3px)\\|slice(0, 3)\\|repeat(7\" "
        f"{REMOTE_REL}/assets/css/home-dashboard.css {REMOTE_REL}/assets/js/home-dashboard.js | head -30",
        timeout=20,
    )
    print("VERIFY:\n", stdout.read().decode("utf-8", "replace"))
    sftp.close()
    c.close()

    time.sleep(3)
    cb = int(time.time())
    with urllib.request.urlopen(
        f"https://yooyland.com/?rest_route=/yoy-ai-studio/v1/core/status&cb={cb}", timeout=30
    ) as r:
        print("status:", r.read().decode("utf-8", "replace")[:280])

    page = f"https://yooyland.com/?page_id=28375&cb={cb}"
    with urllib.request.urlopen(page, timeout=30) as r:
        html = r.read().decode("utf-8", "replace")
    print("13.0.4 in html", "13.0.4" in html)
    print("css ver", "home-dashboard.css?ver=13.0.4" in html)
    print("js ver", "home-dashboard.js?ver=13.0.4" in html)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
