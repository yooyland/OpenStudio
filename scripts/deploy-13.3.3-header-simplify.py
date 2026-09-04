#!/usr/bin/env python3
"""Deploy 13.3.3 top-right header simplification via SSH atomic mv."""
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
    ("plugin/yooy-ai-studio/assets/js/studio.js", "assets/js/studio.js"),
    ("plugin/yooy-ai-studio/assets/js/credits-ui.js", "assets/js/credits-ui.js"),
    ("plugin/yooy-ai-studio/assets/css/studio.css", "assets/css/studio.css"),
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
        f"grep -c 'yai-my-menu' {REMOTE_REL}/templates/studio-shell.php; "
        f"grep -c 'bindMyMenu' {REMOTE_REL}/assets/js/studio.js; "
        f"grep -c 'yai-my-menu-credits' {REMOTE_REL}/assets/js/credits-ui.js; "
        f"grep -c 'yai-top-credits' {REMOTE_REL}/templates/studio-shell.php || true; "
        f"grep -c 'yai-topbar-crystal' {REMOTE_REL}/templates/studio-shell.php || true",
        timeout=20,
    )[1].read().decode("utf-8", "replace")
    print("VERIFY:\n", verify)
    sftp.close()
    c.close()

    time.sleep(2)
    cb = int(time.time())
    with urllib.request.urlopen(
        f"https://yooyland.com/?rest_route=/yoy-ai-studio/v1/core/status&cb={cb}", timeout=30
    ) as r:
        print("status:", r.read().decode("utf-8", "replace")[:260])

    page = urllib.request.urlopen(f"https://yooyland.com/?page_id=28375&cb={cb}", timeout=30).read().decode(
        "utf-8", "replace"
    )
    print("13.3.3 in html", "13.3.3" in page)
    print("guest has login", "로그인" in page)
    print("guest has signup", "회원가입" in page)
    print("guest lacks my-menu", "yai-my-menu" not in page)
    print("guest lacks section manage visible", 'id="yai-section-manage-btn"' not in page)

    css = urllib.request.urlopen(
        f"https://yooyland.com/wp-content/plugins/yooy-ai-studio/assets/css/studio.css?ver=13.3.3&cb={cb}",
        timeout=30,
    ).read().decode("utf-8", "replace")
    print("css has my-menu", ".yai-my-menu__trigger" in css)

    js = urllib.request.urlopen(
        f"https://yooyland.com/wp-content/plugins/yooy-ai-studio/assets/js/studio.js?ver=13.3.3&cb={cb}",
        timeout=30,
    ).read().decode("utf-8", "replace")
    print("js has bindMyMenu", "function bindMyMenu" in js)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
