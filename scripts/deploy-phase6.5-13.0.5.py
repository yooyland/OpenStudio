#!/usr/bin/env python3
"""Deploy 13.0.5 Home header cleanup via SSH atomic mv."""
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
    ("plugin/yooy-ai-studio/templates/studio-shell.php", "templates/studio-shell.php"),
    ("plugin/yooy-ai-studio/assets/js/home-dashboard.js", "assets/js/home-dashboard.js"),
    ("plugin/yooy-ai-studio/assets/css/home-dashboard.css", "assets/css/home-dashboard.css"),
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
        out = c.exec_command(
            f"mv -f {remote_tmp} {remote_final} && stat -c '%s %Y' {remote_final}",
            timeout=20,
        )[1].read().decode("utf-8", "replace").strip()
        print(f"OK {remote_rel} -> {out}")

    verify = c.exec_command(
        f"grep -E \"Version:|YOY_AI_STUDIO_VERSION\" {REMOTE_REL}/yoy-ai-studio.php; "
        f"grep -c \"안녕하세요\" {REMOTE_REL}/templates/studio-shell.php || true; "
        f"grep -n \"updateStudioRecoHeadline\\|당신의 아이디어\\|님의 아이디어\\|greeting--toolbar\\|aspect-ratio: 4 / 5.5\\|repeat(7\" "
        f"{REMOTE_REL}/templates/studio-shell.php {REMOTE_REL}/assets/js/home-dashboard.js "
        f"{REMOTE_REL}/assets/css/home-dashboard.css | head -40",
        timeout=20,
    )[1].read().decode("utf-8", "replace")
    print("VERIFY:\n", verify)
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
    print("13.0.5 in html", "13.0.5" in html)
    print("no greeting hello", "안녕하세요" not in html)
    print("no imagination sub", "상상한 모든 것을" not in html)
    print("guest headline", "당신의 아이디어를 완벽한 결과물로 완성하세요" in html)
    print("no Guest님의", "Guest님의" not in html)
    print("has toolbar class or no greeting h1", "yai-hd-greeting-title" not in html)
    print("css ver", "home-dashboard.css?ver=13.0.5" in html)
    print("js ver", "home-dashboard.js?ver=13.0.5" in html)
    print("polish intact markers in css fetch:")
    css = urllib.request.urlopen(
        f"https://yooyland.com/wp-content/plugins/yooy-ai-studio/assets/css/home-dashboard.css?ver=13.0.5&cb={cb}",
        timeout=30,
    ).read().decode("utf-8", "replace")
    print("  4/5.5", "aspect-ratio: 4 / 5.5" in css)
    print("  7col", "repeat(7, minmax(0, 1fr))" in css)
    print("  clamp2", "line-clamp: 2" in css)
    print("  hover-3", "translateY(-3px)" in css)
    print("  scale102", "scale(1.02)" in css)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
