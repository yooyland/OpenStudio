#!/usr/bin/env python3
"""Deploy Phase 6.5 hotfix (13.0.3) Studio 7-card grid via SSH atomic mv."""
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
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=25)
    sftp = c.open_sftp()

    for local_rel, remote_rel in FILES:
        local_path = ROOT / local_rel
        data = local_path.read_bytes()
        remote_final = f"{REMOTE_REL}/{remote_rel}".replace("\\", "/")
        remote_tmp = remote_final + ".new"
        parent = "/".join(remote_final.split("/")[:-1])
        stdin, stdout, stderr = c.exec_command(f"mkdir -p {parent}", timeout=20)
        stdout.channel.recv_exit_status()
        with sftp.file(remote_tmp, "wb") as f:
            f.write(data)
            f.flush()
        stdin, stdout, stderr = c.exec_command(
            f"mv -f {remote_tmp} {remote_final} && stat -c '%s %Y' {remote_final}",
            timeout=20,
        )
        out = stdout.read().decode("utf-8", "replace").strip()
        print(f"OK {remote_rel} -> {out}")

    stdin, stdout, stderr = c.exec_command(
        f"grep -E \"Version:|YOY_AI_STUDIO_VERSION\" {REMOTE_REL}/yoy-ai-studio.php; "
        f"grep -n \"studio-recos__row\\|repeat(7\\|min-width: 210\\|78vw\\|overflow-x\" "
        f"{REMOTE_REL}/assets/css/home-dashboard.css | head -40",
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
        print("status:", r.read().decode("utf-8", "replace")[:320])

    page = f"https://yooyland.com/?page_id=28375&cb={cb}"
    with urllib.request.urlopen(page, timeout=30) as r:
        html = r.read().decode("utf-8", "replace")
    print("13.0.3 in html", "13.0.3" in html)
    print("home-dashboard.css ver", "home-dashboard.css?ver=13.0.3" in html)

    css = (
        "https://yooyland.com/wp-content/plugins/yooy-ai-studio/"
        f"assets/css/home-dashboard.css?ver=13.0.3&cb={cb}"
    )
    with urllib.request.urlopen(css, timeout=30) as r:
        body = r.read().decode("utf-8", "replace")
    idx = body.find(".yai-hd-studio-recos__row {")
    chunk = body[idx : idx + 280] if idx >= 0 else ""
    print("css row chunk:\n", chunk)
    print("has 7-col media", "repeat(7, minmax(0, 1fr))" in body)
    print("has old 78vw", "78vw" in body)
    print("has old min-width 210", "min-width: 210px" in body)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
