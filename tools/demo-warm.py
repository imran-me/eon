#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""demo-warm.py — the pre-flight check before EON goes on stage.

    python tools/demo-warm.py --token …

Asks every question in the summit script, checks each one answers with real
figures, then renders and caches its audio. Speech is cached by hash on the
server, so a warmed clip comes back in under a second instead of nearly three —
the difference between a beat and dead air in front of a room.

Run it once an hour before, and again five minutes before, on the machine and
network you will present from.
"""

import argparse
import json
import sys
import time
import urllib.parse
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")

# the ten, in the order they are asked
SCRIPT = [
    ("আজকের ব্রিফ দাও", "bn", "opens in Bangla, grounded in the live ledger"),
    ("ক্যাশ কত আছে?", "bn", "money, in Bangladeshi units"),
    ("why is cash so tight", "en", "the arithmetic no screen performs"),
    ("any accounts error?", "en", "finds what nobody reported"),
    ("how to solve that error", "en", "advice, not just analysis"),
    ("হিসাবে কোনো ভুল আছে", "bn", "the same finding in Bangla, not translated"),
    ("which company is burning money", "en", "ranks the group honestly"),
    ("why is profit down", "en", "decomposes until it is actionable"),
    ("where do I find payslips", "en", "knows the ERP itself"),
    ("how is late deduction calculated", "en", "knows the rule, not just the number"),
]

# spares, if the room asks for more
SPARES = [
    ("which company has the most revenue", "en"),
    ("how long will cash last", "en"),
    ("what am I missing", "en"),
    ("what do we owe", "en"),
    ("hate cash koto ache", "bn"),
    ("beton porishodh hoyeche", "bn"),
]


def ask(api, tok, q):
    body = json.dumps({"question": q}, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(api + "/ask.php?token=" + urllib.parse.quote(tok), data=body,
                                 headers={"Content-Type": "application/json; charset=utf-8"})
    t0 = time.time()
    with urllib.request.urlopen(req, timeout=45) as r:
        d = json.loads(r.read().decode("utf-8"))
    return d, int((time.time() - t0) * 1000)


def warm(api, tok, speak, lang):
    url = (api + "/tts.php?token=" + urllib.parse.quote(tok) + "&lang=" + lang
           + "&text=" + urllib.parse.quote(speak))
    t0 = time.time()
    with urllib.request.urlopen(urllib.request.Request(url), timeout=90) as r:
        n = len(r.read())
        cached = r.headers.get("X-EON-TTS-Cached")
    return n, int((time.time() - t0) * 1000), cached


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="https://eon.gulfrabit.com")
    ap.add_argument("--token", default="")
    ap.add_argument("--spares", action="store_true", help="warm the backup questions too")
    ap.add_argument("--no-audio", action="store_true", help="check the answers only")
    a = ap.parse_args()
    api = a.base.rstrip("/") + "/server/api"

    with urllib.request.urlopen(api + "/health.php", timeout=20) as r:
        h = json.loads(r.read().decode("utf-8"))
    print("build %s · php %s · data %s · llm %s" % (h.get("commit"), h.get("php"), h.get("source"), h.get("llm")))
    if not a.no_audio:
        with urllib.request.urlopen(api + "/tts.php?status=1&token=" + urllib.parse.quote(a.token), timeout=20) as r:
            t = json.loads(r.read().decode("utf-8"))
        print("speech %s · %s clips cached" % (t.get("provider"), t.get("cached")))
        if t.get("provider") == "translate":
            print("  ! still on the keyless endpoint — add tts.azure.key + region for bn-BD neural")
    print()

    items = list(SCRIPT) + [(q, l, "spare") for q, l in SPARES] if a.spares else SCRIPT
    bad = 0
    for i, row in enumerate(items, 1):
        q, lang, why = row
        try:
            d, ms = ask(api, a.token, q)
            text = d.get("text", "")
            fell = ("did not catch" in text) or ("ধরতে পারিনি" in text)
            note = ""
            if fell:
                bad += 1
                note = "  ANSWER FELL THROUGH"
            elif d.get("lang") != lang:
                bad += 1
                note = "  answered in %s, expected %s" % (d.get("lang"), lang)
            audio = ""
            if not a.no_audio and not fell:
                n, tms, cached = warm(api, a.token, d.get("speak", ""), d.get("lang") or lang)
                audio = "  audio %skB in %sms%s" % (n // 1024, tms, "" if cached == "1" else " (rendered now)")
            print("%2d. %-36s %sms%s%s" % (i, q[:36], ms, audio, note))
            if note:
                print("      %s" % text[:100])
        except Exception as e:
            bad += 1
            print("%2d. %-36s FAILED  %s" % (i, q[:36], e))

    print()
    if bad:
        print("%d of %d need attention before you present" % (bad, len(items)))
    else:
        print("all %d answered and their audio is cached — ready" % len(items))
    return 1 if bad else 0


if __name__ == "__main__":
    raise SystemExit(main())
