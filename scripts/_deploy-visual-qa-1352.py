#!/usr/bin/env python3
import base64
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
PASSWORD = base64.b64decode(b"cXF3dzMyUVFXVyNA").decode()
REMOTE = "applications/hjevjnjenx/public_html/wp-content/plugins/yooy-ai-studio/modules/image-studio/includes/prompt-intelligence/class-image-visual-qa.php"
c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect("158.247.236.125", username="yooyland", password=PASSWORD, timeout=25)
s = c.open_sftp()
with s.file(REMOTE + ".new", "wb") as f:
    f.write((ROOT / "modules/image-studio/includes/prompt-intelligence/class-image-visual-qa.php").read_bytes())
print(c.exec_command(f"mv -f {REMOTE}.new {REMOTE}; wc -c < {REMOTE}", timeout=20)[1].read().decode())
php = "<?php if (function_exists('opcache_reset')) { opcache_reset(); echo 'opc=1'; } else { echo 'opc=0'; }"
rp = "applications/hjevjnjenx/public_html/wp-content/uploads/_yoy_opcache_reset.php"
with s.file(rp + ".new", "w") as f:
    f.write(php)
c.exec_command(f"mv -f {rp}.new {rp}", timeout=20)[1].channel.recv_exit_status()
print(c.exec_command("cd applications/hjevjnjenx/public_html; php wp-content/uploads/_yoy_opcache_reset.php; rm -f wp-content/uploads/_yoy_opcache_reset.php", timeout=30)[1].read().decode())
s.close()
c.close()
