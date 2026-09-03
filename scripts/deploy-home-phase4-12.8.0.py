#!/usr/bin/env python3
"""Upload ONLY YooY Phase 4 (12.8.0) plugin files via SFTP."""
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
    ("plugin/yooy-ai-studio/includes/class-yoy-ai-studio.php", "includes/class-yoy-ai-studio.php"),
    ("plugin/yooy-ai-studio/assets/css/studio-simple-mode.css", "assets/css/studio-simple-mode.css"),
    ("plugin/yooy-ai-studio/assets/js/studio-simple-mode.js", "assets/js/studio-simple-mode.js"),
    ("plugin/yooy-ai-studio/assets/js/studio.js", "assets/js/studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/image-studio/image-studio.js", "assets/modules/image-studio/image-studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/video-studio/video-studio.js", "assets/modules/video-studio/video-studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/music-studio/music-studio.js", "assets/modules/music-studio/music-studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/voice-studio/voice-studio.js", "assets/modules/voice-studio/voice-studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/avatar-studio/avatar-studio.js", "assets/modules/avatar-studio/avatar-studio.js"),
    ("plugin/yooy-ai-studio/assets/modules/translator-studio/translator-studio.js", "assets/modules/translator-studio/translator-studio.js"),
    ("modules/image-studio/class-yoy-module-image-studio.php", "modules/image-studio/class-yoy-module-image-studio.php"),
    ("modules/video-studio/class-yoy-module-video-studio.php", "modules/video-studio/class-yoy-module-video-studio.php"),
    ("modules/music-studio/class-yoy-module-music-studio.php", "modules/music-studio/class-yoy-module-music-studio.php"),
    ("modules/voice-studio/class-yoy-module-voice-studio.php", "modules/voice-studio/class-yoy-module-voice-studio.php"),
    ("modules/avatar-studio/class-yoy-module-avatar-studio.php", "modules/avatar-studio/class-yoy-module-avatar-studio.php"),
    ("modules/translator-studio/class-yoy-module-translator-studio.php", "modules/translator-studio/class-yoy-module-translator-studio.php"),
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
    print(f"Uploaded {len(uploaded)} Phase-4 files to {REMOTE_BASE}")
    for name, size, mtime in uploaded:
        print(f"  - {name}  size={size}  mtime={time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(mtime))}Z")
    return 0


if __name__ == "__main__":
    sys.exit(main())
