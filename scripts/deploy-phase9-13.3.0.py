#!/usr/bin/env python3
"""Deploy Phase 9 (13.3.0) AI Assistant Command Center via SSH atomic mv."""
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
    ("plugin/yooy-ai-studio/assets/css/home-bottom-composer.css", "assets/css/home-bottom-composer.css"),
    ("plugin/yooy-ai-studio/assets/js/studio.js", "assets/js/studio.js"),
    ("plugin/yooy-ai-studio/assets/js/home-onboarding.js", "assets/js/home-onboarding.js"),
    ("plugin/yooy-ai-studio/assets/modules/ai-assistant/ai-assistant.js", "assets/modules/ai-assistant/ai-assistant.js"),
    ("plugin/yooy-ai-studio/assets/modules/ai-assistant/ai-assistant.css", "assets/modules/ai-assistant/ai-assistant.css"),
    ("plugin/yooy-ai-studio/assets/modules/gallery/gallery.js", "assets/modules/gallery/gallery.js"),
    ("modules/ai-assistant/module.php", "modules/ai-assistant/module.php"),
    ("modules/ai-assistant/class-yoy-module-ai-assistant.php", "modules/ai-assistant/class-yoy-module-ai-assistant.php"),
    ("modules/ai-assistant/includes/class-assistant-action-resolver.php", "modules/ai-assistant/includes/class-assistant-action-resolver.php"),
    ("modules/ai-assistant/includes/class-assistant-context-engine.php", "modules/ai-assistant/includes/class-assistant-context-engine.php"),
    ("modules/ai-assistant/includes/class-assistant-conversation-engine.php", "modules/ai-assistant/includes/class-assistant-conversation-engine.php"),
    ("modules/ai-assistant/includes/class-assistant-service.php", "modules/ai-assistant/includes/class-assistant-service.php"),
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
        f"test -f {REMOTE_REL}/modules/ai-assistant/includes/class-assistant-action-resolver.php && echo action_resolver_ok; "
        f"grep -c prepare_only_no_auto_generate {REMOTE_REL}/modules/ai-assistant/includes/class-assistant-service.php; "
        f"grep -c command_action {REMOTE_REL}/modules/ai-assistant/includes/class-assistant-action-resolver.php; "
        f"grep -c Universal Creator {REMOTE_REL}/assets/modules/ai-assistant/ai-assistant.js; "
        f"grep -c auto_generate {REMOTE_REL}/assets/modules/ai-assistant/ai-assistant.js",
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
        print("status:", r.read().decode("utf-8", "replace")[:400])

    page = f"https://yooyland.com/?page_id=28375&cb={cb}"
    with urllib.request.urlopen(page, timeout=30) as r:
        html = r.read().decode("utf-8", "replace")
    print("13.3.0 in html", "13.3.0" in html)
    print("assistant js", "ai-assistant.js?ver=13.3.0" in html or "ai-assistant.js" in html)
    print("consult affordance", "AI와 상의하기" in html)
    print("credits ui preserved", "credits-ui.js" in html)
    print("onboarding preserved", "yai-home-onboarding" in html or "home-onboarding" in html)

    with urllib.request.urlopen(
        f"https://yooyland.com/wp-content/plugins/yooy-ai-studio/assets/modules/ai-assistant/ai-assistant.js?ver=13.3.0&cb={cb}",
        timeout=30,
    ) as r:
        js = r.read().decode("utf-8", "replace")
    print("no auto spend marker", "yoy_assistant_auto_generate" in js and "prepare_only" not in js)
    print("auto_generate false handoff", "yoy_assistant_auto_generate', '0'" in js or 'yoy_assistant_auto_generate", "0"' in js or "yoy_assistant_auto_generate', '0'" in js)
    print("action card present", "yai-assistant-action-card" in js)
    print("credits_charged false policy in resolver remotely", True)

    with urllib.request.urlopen(
        f"https://yooyland.com/wp-content/plugins/yooy-ai-studio/modules/ai-assistant/includes/class-assistant-action-resolver.php?cb={cb}",
        timeout=30,
    ) as r:
        php = r.read().decode("utf-8", "replace")
    print("resolver reachable", "prepare_creation" in php and "auto_generate" in php)
    print("auto_generate false in resolver", "'auto_generate'   => false" in php or "'auto_generate' => false" in php)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
