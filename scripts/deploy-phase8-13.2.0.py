#!/usr/bin/env python3
"""Deploy Phase 8 (13.2.0) Credits/Plan UX via SSH atomic mv."""
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
    ("plugin/yooy-ai-studio/includes/class-yoy-ai-studio.php", "includes/class-yoy-ai-studio.php"),
    ("plugin/yooy-ai-studio/templates/studio-shell.php", "templates/studio-shell.php"),
    ("plugin/yooy-ai-studio/assets/js/credits-ui.js", "assets/js/credits-ui.js"),
    ("plugin/yooy-ai-studio/assets/css/credits-ui.css", "assets/css/credits-ui.css"),
    ("plugin/yooy-ai-studio/assets/js/studio.js", "assets/js/studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/image-studio/image-studio.js", "assets/modules/image-studio/image-studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/video-studio/video-studio.js", "assets/modules/video-studio/video-studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/music-studio/music-studio.js", "assets/modules/music-studio/music-studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/translator-studio/translator-studio.js", "assets/modules/translator-studio/translator-studio.js"),
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
        f"test -f {REMOTE_REL}/assets/js/credits-ui.js && echo credits_ui_js_ok; "
        f"grep -c \"플랜 보기\" {REMOTE_REL}/templates/studio-shell.php; "
        f"grep -c YooYCreditsUI {REMOTE_REL}/assets/js/credits-ui.js",
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
        print("status:", r.read().decode("utf-8", "replace")[:300])

    page = f"https://yooyland.com/?page_id=28375&cb={cb}"
    with urllib.request.urlopen(page, timeout=30) as r:
        html = r.read().decode("utf-8", "replace")
    print("13.2.0 in html", "13.2.0" in html)
    print("credits-ui.js", "credits-ui.js?ver=13.2.0" in html)
    print("credits-ui.css", "credits-ui.css?ver=13.2.0" in html)
    print("plan compare menu", "플랜 비교" in html)
    print("no charge button", "충전</button>" not in html)
    print("plan view button", "플랜 보기" in html)
    print("guest signup copy soft", "첫 작품을 만들 수 있는 크레딧" in html)
    print("onboarding preserved", "yai-home-onboarding" in html)
    print("7col polish css still", True)  # checked via css fetch below

    css = urllib.request.urlopen(
        f"https://yooyland.com/wp-content/plugins/yooy-ai-studio/assets/css/home-dashboard.css?ver=13.2.0&cb={cb}",
        timeout=30,
    ).read().decode("utf-8", "replace")
    print("polish 4/5.5", "aspect-ratio: 4 / 5.5" in css)
    print("polish 7col", "repeat(7, minmax(0, 1fr))" in css)

    with urllib.request.urlopen(
        f"https://yooyland.com/?rest_route=/yoy-ai-studio/v1/credits/plans&cb={cb}", timeout=30
    ) as r:
        plans = r.read().decode("utf-8", "replace")
    print("credits plans public", '"free"' in plans and "success" in plans)

    try:
        urllib.request.urlopen(
            f"https://yooyland.com/?rest_route=/yoy-ai-studio/v1/credits/balance&cb={cb}", timeout=20
        )
        print("balance guest unexpected 200")
    except urllib.error.HTTPError as e:
        print("balance guest status", e.code)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
