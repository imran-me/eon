#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""live-check.py — ask the deployed EON real questions and prove it can speak them.

    python tools/live-check.py [--base https://eon.gulfrabit.com] [--token …]

Goes the whole way a browser goes: POST the question to ask.php, take the
`speak` field back, GET it from tts.php, and check real audio comes out.

Note for anyone testing this by hand on Windows: do NOT pass Bengali to
`curl --data-urlencode`. curl transcodes the argument to the ANSI codepage
first, so বাংলা arrives as "???" and an em dash as a stray 0x97 byte, and the
server correctly rejects it as malformed UTF-8. Percent-encode first, as this
script does.
"""

import argparse
import json
import sys
import urllib.parse
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")

QUESTIONS = [
    ("আজকের ব্রিফ দাও", "bn"),
    ("ক্যাশ কত আছে?", "bn"),
    ("লাভ কেন কমছে", "bn"),
    ("কে দেরি করে এসেছে", "bn"),
    ("কোথায় পাবো পে-স্লিপ?", "bn"),
    ("why is cash so tight", "en"),
    ("how long will cash last", "en"),
    ("where do I find payslips", "en"),
    ("top expenses by category", "en"),
    ("hate cash koto ache", "bn"),
]


def post(url, payload, timeout=45):
    body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(url, data=body, headers={"Content-Type": "application/json; charset=utf-8"})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return json.loads(r.read().decode("utf-8"))


def get_audio(url, timeout=60):
    req = urllib.request.Request(url)
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read(), dict(r.headers)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="https://eon.gulfrabit.com")
    ap.add_argument("--token", default="")
    a = ap.parse_args()

    api = a.base.rstrip("/") + "/server/api"
    tok = urllib.parse.quote(a.token)

    with urllib.request.urlopen(api + "/health.php", timeout=20) as r:
        h = json.loads(r.read().decode("utf-8"))
    print(f"build {h.get('commit')}  ·  php {h.get('php')}  ·  data {h.get('source')}  ·  llm {h.get('llm')}")

    with urllib.request.urlopen(f"{api}/tts.php?status=1&token={tok}", timeout=20) as r:
        t = json.loads(r.read().decode("utf-8"))
    print(f"speech provider: {t.get('provider')}  ·  cached clips {t.get('cached')}\n")

    ok = fail = 0
    for q, want in QUESTIONS:
        try:
            ans = post(f"{api}/ask.php?token={tok}", {"question": q})
            text, speak = ans.get("text", ""), ans.get("speak", "")
            intent, lang = ans.get("intent"), ans.get("lang")

            problems = []
            if not text:
                problems.append("no answer")
            if intent in (None, "") or "did not catch" in text or "ধরতে পারিনি" in text:
                problems.append("fell through")
            if lang != want:
                problems.append(f"answered {lang}, asked {want}")
            for bad, why in ((("৳",), "currency sign"), (tuple("0123456789০১২৩৪৫৬৭৮৯"), "digits")):
                if any(c in speak for c in bad):
                    problems.append(f"{why} left in the spoken form")
                    break

            url = f"{api}/tts.php?token={tok}&lang={lang}&text=" + urllib.parse.quote(speak)
            audio, hdr = get_audio(url)
            if not audio.startswith(b"\xff") and b"ID3" not in audio[:16]:
                problems.append("audio is not mp3")
            if len(audio) < 2000:
                problems.append(f"audio too small ({len(audio)}b)")

            mark = "ok " if not problems else "FAIL"
            if problems:
                fail += 1
            else:
                ok += 1
            print(f"{mark} [{lang}] {q}")
            print(f"      intent={intent}  audio={len(audio):,}b  provider={hdr.get('X-EON-TTS-Provider')}  prepared={hdr.get('X-EON-TTS-Prepared')}")
            print(f"      says: {speak[:110]}")
            if problems:
                print(f"      !! {'; '.join(problems)}")
        except Exception as e:
            fail += 1
            print(f"FAIL [{want}] {q}\n      !! {e}")
        print()

    print(f"{ok} of {ok + fail} answered and spoken")
    return 0 if fail == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
