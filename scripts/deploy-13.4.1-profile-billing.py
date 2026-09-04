#!/usr/bin/env python3
"""Deploy 13.4.1 Phase 10 profile + billing clarification via SSH atomic mv."""
from __future__ import annotations

import base64
import time
import urllib.error
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
    ("plugin/yooy-ai-studio/assets/js/core.js", "assets/js/core.js"),
    ("plugin/yooy-ai-studio/assets/js/my-account.js", "assets/js/my-account.js"),
    ("plugin/yooy-ai-studio/assets/css/my-account.css", "assets/css/my-account.css"),
    ("plugin/yooy-ai-studio/includes/core/class-yoy-credits-service.php", "includes/core/class-yoy-credits-service.php"),
    ("modules/user-profile/class-yoy-module-user-profile.php", "modules/user-profile/class-yoy-module-user-profile.php"),
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
        f"grep -c upload_avatar {REMOTE_REL}/modules/user-profile/class-yoy-module-user-profile.php; "
        f"grep -c '플랜 및 결제' {REMOTE_REL}/templates/studio-shell.php; "
        f"grep -c billingSection {REMOTE_REL}/assets/js/my-account.js; "
        f"grep -c saved_cards_supported {REMOTE_REL}/includes/core/class-yoy-credits-service.php",
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
    print("13.4.1 in html", "13.4.1" in page)

    js = urllib.request.urlopen(
        f"https://yooyland.com/wp-content/plugins/yooy-ai-studio/assets/js/my-account.js?ver=13.4.1&cb={cb}",
        timeout=30,
    ).read().decode("utf-8", "replace")
    print("unified profile form", "변경사항 저장" in js)
    print("billing section", "플랜 및 결제" in js and "결제수단" in js)
    print("no fake card add", "새 결제수단 추가" not in js)

    try:
        urllib.request.urlopen(
            f"https://yooyland.com/?rest_route=/yoy-ai-studio/v1/user-profile/me/avatar&cb={cb}",
            timeout=20,
        )
        print("avatar guest unexpected 200")
    except urllib.error.HTTPError as e:
        print("avatar guest status", e.code)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
