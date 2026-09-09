#!/usr/bin/env python3
"""Deploy 13.4.20: Gallery card overflow menu E2E action fix."""
from __future__ import annotations

import base64
import re
import urllib.request
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
REMOTE_PLUGIN = "applications/hjevjnjenx/public_html/wp-content/plugins/yooy-ai-studio"
REMOTE_ROOT = "applications/hjevjnjenx/public_html"

FILES = [
    ("plugin/yooy-ai-studio/yoy-ai-studio.php", f"{REMOTE_PLUGIN}/yoy-ai-studio.php"),
    (
        "plugin/yooy-ai-studio/assets/modules/gallery/gallery.js",
        f"{REMOTE_PLUGIN}/assets/modules/gallery/gallery.js",
    ),
    (
        "plugin/yooy-ai-studio/assets/modules/gallery/gallery.css",
        f"{REMOTE_PLUGIN}/assets/modules/gallery/gallery.css",
    ),
    (
        "modules/gallery/includes/class-gallery-actions.php",
        f"{REMOTE_PLUGIN}/modules/gallery/includes/class-gallery-actions.php",
    ),
]


def atomic_put(sftp, client, local: Path, remote: str) -> str:
    data = local.read_bytes()
    tmp = remote + ".new"
    parent = "/".join(remote.split("/")[:-1])
    client.exec_command(f"mkdir -p {parent}", timeout=20)[1].channel.recv_exit_status()
    with sftp.file(tmp, "wb") as f:
        f.write(data)
        f.flush()
    out = client.exec_command(
        f"mv -f {tmp} {remote} && touch {remote} && stat -c '%s %Y' {remote}",
        timeout=20,
    )[1].read().decode("utf-8", "replace").strip()
    return out


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect("158.247.236.125", username="yooyland", password=PASSWORD, timeout=25)
    sftp = c.open_sftp()

    for local_rel, remote in FILES:
        print(f"OK {local_rel} ->", atomic_put(sftp, c, ROOT / local_rel, remote))

    php = "<?php if (function_exists('opcache_reset')) { opcache_reset(); echo 'opcache_reset=1'; } else { echo 'opcache_reset=0'; }"
    remote_php = f"{REMOTE_ROOT}/wp-content/uploads/_yoy_opcache_reset.php"
    with sftp.file(remote_php + ".new", "w") as f:
        f.write(php)
    c.exec_command(f"mv -f {remote_php}.new {remote_php}", timeout=20)[1].channel.recv_exit_status()
    print(
        "FLUSH",
        c.exec_command(
            f"cd {REMOTE_ROOT} && php wp-content/uploads/_yoy_opcache_reset.php; rm -f wp-content/uploads/_yoy_opcache_reset.php",
            timeout=30,
        )[1].read().decode("utf-8", "replace").strip(),
    )

    verify = c.exec_command(
        f"grep -E 'Version:|YOY_AI_STUDIO_VERSION' {REMOTE_PLUGIN}/yoy-ai-studio.php; "
        f"grep -c 'ensureGridDelegation' {REMOTE_PLUGIN}/assets/modules/gallery/gallery.js; "
        f"grep -c 'findItem' {REMOTE_PLUGIN}/assets/modules/gallery/gallery.js; "
        f"grep -c 'is-menu-open .ygl-card-hover' {REMOTE_PLUGIN}/assets/modules/gallery/gallery.css; "
        f"grep -c 'attachment_id' {REMOTE_PLUGIN}/modules/gallery/includes/class-gallery-actions.php",
        timeout=20,
    )[1].read().decode("utf-8", "replace")
    print("VERIFY\n", verify)
    sftp.close()
    c.close()

    url = "https://yooyland.com/?page_id=28375&_yoyv=13420"
    req = urllib.request.Request(url, headers={"Cache-Control": "no-cache", "Pragma": "no-cache"})
    with urllib.request.urlopen(req, timeout=30) as resp:
        html = resp.read().decode("utf-8", "replace")
    ver = re.findall(r'"version"\s*:\s*"([0-9.]+)"', html)
    gal = re.findall(r"gallery\.js\?ver=([0-9.]+)", html)
    print("LOCALIZE_VERSION", ver[:3])
    print("GALLERY_JS_VER", gal[:3])
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
