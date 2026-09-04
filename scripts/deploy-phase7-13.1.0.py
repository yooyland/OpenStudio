#!/usr/bin/env python3
"""Deploy Phase 7 (13.1.0) onboarding via SSH atomic mv."""
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
    ("plugin/yooy-ai-studio/includes/class-yoy-ai-studio.php", "includes/class-yoy-ai-studio.php"),
    ("plugin/yooy-ai-studio/includes/core/class-yoy-onboarding.php", "includes/core/class-yoy-onboarding.php"),
    ("plugin/yooy-ai-studio/templates/studio-shell.php", "templates/studio-shell.php"),
    ("plugin/yooy-ai-studio/assets/js/home-onboarding.js", "assets/js/home-onboarding.js"),
    ("plugin/yooy-ai-studio/assets/js/home-bottom-composer.js", "assets/js/home-bottom-composer.js"),
    ("plugin/yooy-ai-studio/assets/js/studio.js", "assets/js/studio.js"),
    ("plugin/yooy-ai-studio/assets/css/home-dashboard.css", "assets/css/home-dashboard.css"),
    ("plugin/yooy-ai-studio/assets/modules/gallery/gallery.js", "assets/modules/gallery/gallery.js"),
    ("plugin/yooy-ai-studio/assets/modules/image-studio/image-studio.js", "assets/modules/image-studio/image-studio.js"),
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
        f"test -f {REMOTE_REL}/includes/core/class-yoy-onboarding.php && echo onboarding_php_ok; "
        f"test -f {REMOTE_REL}/assets/js/home-onboarding.js && echo onboarding_js_ok; "
        f"grep -c yai-home-onboarding {REMOTE_REL}/templates/studio-shell.php; "
        f"grep -c aspect-ratio:\\ 4\\ /\\ 5.5 {REMOTE_REL}/assets/css/home-dashboard.css",
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
    print("13.1.0 in html", "13.1.0" in html)
    print("home-onboarding.js", "home-onboarding.js?ver=13.1.0" in html)
    print("yai-home-onboarding mount", "yai-home-onboarding" in html)
    print("no Guest님의", "Guest님의" not in html)
    print("guest headline", "당신의 아이디어를 완벽한 결과물로 완성하세요" in html)
    print("13.0.5 header gone greeting", "안녕하세요" not in html)

    # Guest: onboarding should be disabled in localize if present
    print("onboarding localize present", "onboarding" in html)
    print("enabled false for guest likely", '"enabled":false' in html or '"enabled": false' in html)

    css = urllib.request.urlopen(
        f"https://yooyland.com/wp-content/plugins/yooy-ai-studio/assets/css/home-dashboard.css?ver=13.1.0&cb={cb}",
        timeout=30,
    ).read().decode("utf-8", "replace")
    print("polish 4/5.5", "aspect-ratio: 4 / 5.5" in css)
    print("polish 7col", "repeat(7, minmax(0, 1fr))" in css)
    print("ob panel css", ".yai-ob-panel" in css)

    # onboarding REST as guest should 401
    try:
        urllib.request.urlopen(
            f"https://yooyland.com/?rest_route=/yoy-ai-studio/v1/core/onboarding&cb={cb}", timeout=20
        )
        print("onboarding GET guest unexpected 200")
    except urllib.error.HTTPError as e:
        print("onboarding GET guest status", e.code)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
