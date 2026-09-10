#!/usr/bin/env python3
import json
import urllib.request
from pathlib import Path

out = Path(__file__).resolve().parents[1] / "scripts" / "_qa-acceptance-gate"
rows = json.loads((out / "baseline-af.json").read_text(encoding="utf-8"))
for r in rows:
    url = (r.get("url") or "").replace("http://", "https://")
    if not url:
        continue
    dest = out / ("bench-%s.png" % r["id"])
    try:
        urllib.request.urlretrieve(url, dest)
        print("ok", r["id"], dest.stat().st_size)
    except Exception as e:
        print("fail", r["id"], e)
