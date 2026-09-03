#!/usr/bin/env python3
"""Upload ONLY YooY Phase 5.6 (12.9.2) Writing provider fix files via SFTP."""
from __future__ import annotations

import os
import sys
import time
from pathlib import Path

try:
    import paramiko
except ImportError:
    print("Install paramiko: python -m pip install paramiko", file=sys.stderr)
    raise

ROOT = Path(__file__).resolve().parents[1]
REMOTE_BASE = os.environ.get(
    "YOOY_SFTP_REMOTE",
    "applications/hjevjnjenx/public_html/wp-content/plugins/yooy-ai-studio",
)
HOST = os.environ.get("YOOY_SFTP_HOST", "158.247.236.125")
PORT = int(os.environ.get("YOOY_SFTP_PORT", "22"))
USER = os.environ.get("YOOY_SFTP_USER", "yooyland")
PASSWORD = os.environ.get("YOOY_SFTP_PASSWORD", "")

FILES = [
    ("plugin/yooy-ai-studio/yoy-ai-studio.php", "yoy-ai-studio.php"),
    ("plugin/yooy-ai-studio/assets/js/studio.js", "assets/js/studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/gallery/gallery.js", "assets/modules/gallery/gallery.js"),
    ("plugin/yooy-ai-studio/includes/core/class-yoy-job-normalizer.php", "includes/core/class-yoy-job-normalizer.php"),
    ("modules/ai-router/includes/class-ai-router-dispatcher.php", "modules/ai-router/includes/class-ai-router-dispatcher.php"),
    ("modules/ai-router/class-yoy-module-ai-router.php", "modules/ai-router/class-yoy-module-ai-router.php"),
    ("modules/gallery/includes/class-gallery-store.php", "modules/gallery/includes/class-gallery-store.php"),
    ("modules/gallery/class-yoy-module-gallery.php", "modules/gallery/class-yoy-module-gallery.php"),
    ("providers/helpers/class-yoy-openai-chat.php", "providers/helpers/class-yoy-openai-chat.php"),
]


def ensure_dir(sftp, remote_dir: str) -> None:
    parts = []
    for part in remote_dir.strip("/").split("/"):
        parts.append(part)
        cur = "/" + "/".join(parts)
        try:
            sftp.stat(cur)
        except FileNotFoundError:
            sftp.mkdir(cur)


def main() -> int:
    if not PASSWORD:
        print("Set YOOY_SFTP_PASSWORD before running.", file=sys.stderr)
        return 2
    transport = paramiko.Transport((HOST, PORT))
    transport.connect(username=USER, password=PASSWORD)
    sftp = paramiko.SFTPClient.from_transport(transport)
    uploaded = []
    for local_rel, remote_rel in FILES:
        local_path = ROOT / local_rel
        if not local_path.is_file():
            raise FileNotFoundError(local_path)
        remote_path = REMOTE_BASE.rstrip("/") + "/" + remote_rel.replace("\\", "/")
        ensure_dir(sftp, os.path.dirname(remote_path))
        sftp.put(str(local_path), remote_path)
        st = sftp.stat(remote_path)
        uploaded.append((remote_rel, int(st.st_size), float(st.st_mtime)))
    sftp.close()
    transport.close()
    print("Uploaded %d Phase-5.6 files to %s" % (len(uploaded), REMOTE_BASE))
    for name, size, mtime in uploaded:
        print("  - %s  size=%s  mtime=%sZ" % (
            name, size, time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime(mtime))
        ))
    return 0


if __name__ == "__main__":
    sys.exit(main())
