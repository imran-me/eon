#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""audit-access.py — is everything that must be reachable, reachable?
   And is everything that must not be, still blocked?

     python tools/audit-access.py [--base https://eon.gulfrabit.com] [--token …]

Two failures matter and they pull in opposite directions:
  · something the app needs returns 403/404/500 → the app is broken
  · something private returns 200 → the deployment is leaking

Both are reported. Exit code is non-zero if either kind is found.
"""

import argparse
import sys
import urllib.error
import urllib.parse
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")

# (path, what it is, expectation)
#   'open'   — must answer without a token
#   'auth'   — must work with a token; without one it must NOT hand over data
#   'method' — exists but needs POST, so GET may legitimately be 405
#   'params' — needs arguments, so a bare GET rightly answers 400
#   'block'  — must never be readable from the web
CHECKS = [
    # ---- the API ----
    ("/server/api/health.php", "health", "open"),
    ("/server/api/dataset.php", "the ERP dataset", "auth"),
    ("/server/api/brief.php", "morning brief", "auth"),
    ("/server/api/ask.php", "ask", "method"),
    ("/server/api/actions.php", "actions log", "auth"),
    ("/server/api/memory.php?doc=settings/prefs", "memory", "auth"),
    ("/server/api/tts.php?status=1", "speech status", "auth"),
    ("/server/api/py.php", "python bridge", "auth"),
    ("/server/api/file.php", "file export", "params"),

    # ---- the panel ----
    ("/eon/", "EON panel", "open"),
    ("/eon/workspace.html", "split workspace", "open"),
    ("/app/eon.css", "panel stylesheet", "open"),
    ("/app/eon-app.js", "panel script", "open"),

    # ---- what the panel and the companion load ----
    ("/ai-companion/js/boot.js", "companion boot", "open"),
    ("/ai-companion/eon-brain/voice.js", "voice module", "open"),
    ("/ai-companion/eon-brain/brain.js", "brain", "open"),
    ("/ai-companion/eon-brain/domains/erp/erp-map.json", "the generated ERP map", "open"),
    ("/ai-companion/adapters/erp-adapter.js", "ERP adapter", "open"),

    # ---- what rides on the ERP ----
    ("/embed/eon-embed.js", "companion embed", "open"),
    ("/embed/eon-sidebar.js", "collapsible side menu", "open"),
    ("/embed/eon-ask.js", "ask in place", "open"),

    # ---- the ERP itself ----
    ("/", "the ERP front door", "open"),
    ("/login", "ERP login", "open"),

    # ---- these must never be readable ----
    ("/server/config.local.php", "the secrets file", "block"),
    ("/server/lib/Config.php", "server source", "block"),
    ("/server/lib/Brain.php", "server source", "block"),
    ("/server/storage/logs/eon.log", "logs", "block"),
    ("/server/bootstrap.php", "bootstrap", "block"),
    ("/.git/config", "the git directory", "block"),
    ("/.env", "environment file", "block"),
    ("/erp/.env", "the ERP environment file", "block"),
    ("/CLAUDE.md", "working notes", "block"),
    ("/deploy/deploy.sh", "the deploy script", "block"),
]


def fetch(url, timeout=25):
    req = urllib.request.Request(url, headers={"User-Agent": "EON-audit"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.status, r.read(4096), dict(r.headers)
    except urllib.error.HTTPError as e:
        body = b""
        try:
            body = e.read(2048)
        except Exception:
            pass
        return e.code, body, dict(e.headers or {})
    except Exception as e:
        return 0, str(e).encode(), {}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="https://eon.gulfrabit.com")
    ap.add_argument("--token", default="")
    a = ap.parse_args()
    base = a.base.rstrip("/")
    tok = urllib.parse.quote(a.token)

    broken, leaking, ok = [], [], 0
    print("auditing " + base + "\n")

    for path, what, kind in CHECKS:
        sep = "&" if "?" in path else "?"
        url = base + path
        if tok and kind in ("auth", "method", "params"):
            url = base + path + sep + "token=" + tok
        code, body, hdr = fetch(url)
        note = ""
        good = False

        if kind == "open":
            good = code == 200
            if not good:
                broken.append((path, what, code))
        elif kind == "auth":
            good = code == 200
            if not good:
                broken.append((path, what, code))
            else:
                bare, bbody, _ = fetch(base + path)
                if bare == 200 and len(bbody) > 400:
                    leaking.append((path, what, "answers without a token"))
                    note = "   (also answers unauthenticated)"
                    good = False
        elif kind == "method":
            good = code in (200, 400, 405)
            if not good:
                broken.append((path, what, code))
        elif kind == "params":
            # rejecting a bare call is correct; being reachable at all is the point,
            # and it must still refuse a stranger
            good = code in (200, 400)
            if not good:
                broken.append((path, what, code))
            else:
                bare, _, _ = fetch(base + path)
                if bare not in (401, 403):
                    leaking.append((path, what, "does not require auth"))
                    good = False
        elif kind == "block":
            good = code in (401, 403, 404)
            if not good:
                leaking.append((path, what, "HTTP " + str(code)))

        if good:
            ok += 1
        mark = "ok  " if good else "FAIL"
        print("%s %-4s %-7s %s%s" % (mark, code, kind, path, note))

    print()
    if broken:
        print("UNREACHABLE (%d) — the app needs these:" % len(broken))
        for p, w, c in broken:
            print("   %-5s %s   (%s)" % (c, p, w))
        print()
    if leaking:
        print("EXPOSED (%d) — these must not be readable:" % len(leaking))
        for p, w, c in leaking:
            print("   %-28s %s   (%s)" % (c, p, w))
        print()
    print("%d of %d as they should be" % (ok, len(CHECKS)))
    return 0 if not (broken or leaking) else 1


if __name__ == "__main__":
    raise SystemExit(main())
