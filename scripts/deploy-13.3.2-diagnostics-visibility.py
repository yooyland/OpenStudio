#!/usr/bin/env python3
"""Deploy 13.3.2 diagnostics visibility cleanup via SSH atomic mv."""
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
    ("plugin/yooy-ai-studio/includes/core/class-yoy-rest-controller.php", "includes/core/class-yoy-rest-controller.php"),
    ("plugin/yooy-ai-studio/templates/studio-shell.php", "templates/studio-shell.php"),
    ("plugin/yooy-ai-studio/assets/js/diagnostics.js", "assets/js/diagnostics.js"),
    ("plugin/yooy-ai-studio/assets/js/studio.js", "assets/js/studio.js"),
    ("plugin/yooy-ai-studio/assets/js/home-dashboard.js", "assets/js/home-dashboard.js"),
    ("plugin/yooy-ai-studio/assets/css/studio.css", "assets/css/studio.css"),
    ("plugin/yooy-ai-studio/assets/css/home-dashboard.css", "assets/css/home-dashboard.css"),
    ("plugin/yooy-ai-studio/assets/modules/ai-assistant/ai-assistant.js", "assets/modules/ai-assistant/ai-assistant.js"),
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
        f"grep -c manage_options {REMOTE_REL}/includes/core/class-yoy-rest-controller.php; "
        f"grep -c '관리자 도구' {REMOTE_REL}/templates/studio-shell.php; "
        f"grep -c isAdminOnly {REMOTE_REL}/assets/js/diagnostics.js; "
        f"grep -c 'System Ready' {REMOTE_REL}/assets/modules/ai-assistant/ai-assistant.js || true",
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
    print("13.3.2 in html", "13.3.2" in page)
    print("admin tools in guest html", "관리자 도구" in page)  # guest page may not include if not admin
    print("diagnostics js present", "diagnostics.js" in page)

    try:
        urllib.request.urlopen(
            f"https://yooyland.com/?rest_route=/yoy-ai-studio/v1/core/system-check&cb={cb}", timeout=20
        )
        print("system-check guest unexpected 200")
    except urllib.error.HTTPError as e:
        print("system-check guest/unauth status", e.code)

    js = urllib.request.urlopen(
        f"https://yooyland.com/wp-content/plugins/yooy-ai-studio/assets/js/diagnostics.js?ver=13.3.2&cb={cb}",
        timeout=30,
    ).read().decode("utf-8", "replace")
    print("admin-only boot", "Admin only" in js or "admin-only" in js.lower() or "isAdminUser" in js)
    print("no auto ready for creators", "never auto-mount developer health" in js.lower() or "Admin only" in js)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
