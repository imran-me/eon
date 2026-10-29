"""Generate print-ready stall artwork for EON at the Bangladesh AI Business Summit 2026.

Coordinate system: 1 SVG user unit = 0.1 inch, so every number below is in
tenths of an inch and the width/height attributes carry real print size.
"""
import os
import sys

import segno

OUT = sys.argv[1]
os.makedirs(OUT, exist_ok=True)

INK = "#0E1526"
INK2 = "#161F36"
BLUE = "#3B6FE0"
TEAL = "#22A3C4"
ORANGE = "#EA7317"
WHITE = "#FFFFFF"
MUTED = "#A9B6D0"
LINE = "#2A3characters"  # placeholder, replaced below
LINE = "#28324B"

SANS = "Inter, 'Segoe UI', Helvetica, Arial, sans-serif"
BANGLA = "'Noto Sans Bengali', 'Nirmala UI', 'Vrinda', sans-serif"

URL = "https://eon.gulfrabit.com/eon/"


def qr_rects(data, x, y, size, dark=WHITE, quiet=2):
    """Vector QR as a group of rects, fitted into a size x size box."""
    q = segno.make(data, error="h")
    m = [list(row) for row in q.matrix]
    n = len(m) + quiet * 2
    cell = size / n
    out = [f'<g transform="translate({x},{y})">']
    for r, row in enumerate(m):
        c = 0
        while c < len(row):
            if row[c]:
                run = 1
                while c + run < len(row) and row[c + run]:
                    run += 1
                out.append(
                    f'<rect x="{(c + quiet) * cell:.3f}" y="{(r + quiet) * cell:.3f}" '
                    f'width="{run * cell:.3f}" height="{cell:.3f}" fill="{dark}"/>')
                c += run
            else:
                c += 1
    out.append("</g>")
    return "\n".join(out)


def head(w_in, h_in, vw, vh, title, bleed=0):
    """SVG header. bleed is in units (0.1in) added on every side."""
    tw, th = w_in + 2 * bleed / 10, h_in + 2 * bleed / 10
    return f'''<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
     width="{tw:.4f}in" height="{th:.4f}in"
     viewBox="{-bleed} {-bleed} {vw + 2 * bleed} {vh + 2 * bleed}">
<title>{title}</title>
<defs>
  <linearGradient id="sky" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="{BLUE}"/><stop offset="1" stop-color="{TEAL}"/>
  </linearGradient>
  <linearGradient id="bg" x1="0" y1="0" x2="0.3" y2="1">
    <stop offset="0" stop-color="{INK2}"/><stop offset="1" stop-color="{INK}"/>
  </linearGradient>
  <radialGradient id="glow" cx="0.5" cy="0.5" r="0.5">
    <stop offset="0" stop-color="{TEAL}" stop-opacity="0.40"/>
    <stop offset="0.55" stop-color="{BLUE}" stop-opacity="0.16"/>
    <stop offset="1" stop-color="{BLUE}" stop-opacity="0"/>
  </radialGradient>
</defs>
'''


def tag(t, x, y, size, fill=WHITE, weight=700, anchor="start", font=SANS,
        spacing=0, opacity=1):
    return (f'<text x="{x}" y="{y}" font-family="{font}" font-size="{size}" '
            f'font-weight="{weight}" fill="{fill}" text-anchor="{anchor}" '
            f'letter-spacing="{spacing}" opacity="{opacity}">{t}</text>')


def orbit(cx, cy, r):
    """The EON mark: a brain-dot inside decision-layer rings."""
    g = [f'<g transform="translate({cx},{cy})">']
    for i, (rr, op) in enumerate([(r, .30), (r * .74, .45), (r * .48, .65)]):
        g.append(f'<circle r="{rr:.2f}" fill="none" stroke="{TEAL}" '
                 f'stroke-width="{r * .035:.2f}" opacity="{op}"/>')
    g.append(f'<circle r="{r * .17:.2f}" fill="{BLUE}"/>')
    for ang, rr in [(-55, r), (28, r * .74), (160, r * .48), (105, r)]:
        import math
        a = math.radians(ang)
        g.append(f'<circle cx="{rr * math.cos(a):.2f}" cy="{rr * math.sin(a):.2f}" '
                 f'r="{r * .055:.2f}" fill="{TEAL}"/>')
    g.append("</g>")
    return "\n".join(g)


# --------------------------------------------------------------- backdrop
def backdrop():
    W, H = 708.0, 999.6          # 5.9ft x 8.33ft in 0.1in units
    BL = 15                       # 1.5in bleed
    s = [head(70.8, 99.96, W, H, "EON backdrop 5.9 x 8.33 ft", bleed=BL)]
    s.append(f'<rect x="{-BL}" y="{-BL}" width="{W + 2 * BL}" height="{H + 2 * BL}" fill="{INK}"/>')
    s.append(f'<rect x="{-BL}" y="{-BL}" width="{W + 2 * BL}" height="{H + 2 * BL}" fill="url(#bg)"/>')
    s.append(f'<ellipse cx="{W * .72}" cy="190" rx="430" ry="360" fill="url(#glow)"/>')
    s.append(f'<ellipse cx="{W * .1}" cy="700" rx="330" ry="300" fill="url(#glow)" opacity="0.5"/>')

    M = 58                       # safe margin
    # eyebrow: pill on its own line, summit name beneath it -- never side by side
    s.append(f'<rect x="{M}" y="72" width="232" height="32" rx="16" fill="none" stroke="{LINE}" stroke-width="1.6"/>')
    s.append(tag("STALL U-13 &#183; UNIVERSITY ZONE", M + 18, 94, 14, TEAL, 700, spacing=1.4))
    s.append(tag("Bangladesh AI Business Summit 2026", M, 132, 16, MUTED, 500))

    # mark + wordmark
    s.append(orbit(M + 56, 212, 52))
    s.append(tag("EON", M + 132, 252, 112, WHITE, 800, spacing=-3))
    s.append(f'<rect x="{M}" y="300" width="{W - 2 * M}" height="1.6" fill="{LINE}"/>')

    # promise -- three separate baselines, solid colours
    s.append(tag("One Brain.", M, 372, 50, WHITE, 800, spacing=-1))
    s.append(tag("Multi Decision Layer", M, 428, 50, TEAL, 800, spacing=-1))
    s.append(tag("for your ERP.", M, 484, 50, WHITE, 800, spacing=-1))

    s.append(tag("Ask in English or &#2476;&#2494;&#2434;&#2482;&#2494;. Get the number, the rule", M, 528, 21, MUTED, 500))
    s.append(tag("behind it, and the action to take &#8212; from the live ERP.", M, 556, 21, MUTED, 500))

    # QR block at 2.6-3.5 ft off the floor: scannable, and above a stall table
    qy = 586
    s.append(f'<rect x="{M}" y="{qy}" width="{W - 2 * M}" height="108" rx="12" '
             f'fill="{WHITE}" fill-opacity="0.05" stroke="{LINE}" stroke-width="1.4"/>')
    s.append(f'<rect x="{M + 12}" y="{qy + 12}" width="84" height="84" rx="6" fill="{WHITE}"/>')
    s.append(qr_rects(URL, M + 12, qy + 12, 84, dark=INK, quiet=2))
    s.append(tag("Try it yourself, right now", M + 112, qy + 40, 24, WHITE, 700))
    s.append(tag("Scan and ask EON a question in English or &#2476;&#2494;&#2434;&#2482;&#2494;", M + 112, qy + 66, 16, MUTED, 500))
    s.append(tag("eon.gulfrabit.com", M + 112, qy + 90, 16, TEAL, 700))

    # proof row -- each sub-line its own <text>, placed off the card's own x
    cards = [("45", ["business subjects,", "asked any way"]),
             ("3", ["languages: English,", "&#2476;&#2494;&#2434;&#2482;&#2494;, Banglish"]),
             ("0", ["language models.", "All figures computed"])]
    cw = (W - 2 * M - 2 * 16) / 3
    cy = 724
    for i, (big, subs) in enumerate(cards):
        x = M + i * (cw + 16)
        s.append(f'<rect x="{x}" y="{cy}" width="{cw:.1f}" height="128" rx="12" '
                 f'fill="{WHITE}" fill-opacity="0.045" stroke="{LINE}" stroke-width="1.4"/>')
        s.append(tag(big, x + 18, cy + 58, 46, TEAL, 800))
        for j, sub in enumerate(subs):
            s.append(tag(sub, x + 18, cy + 86 + j * 20, 15.0, MUTED, 500))

    # footer -- two baselines, never two strings on one
    s.append(f'<rect x="{M}" y="906" width="{W - 2 * M}" height="1.4" fill="{LINE}"/>')
    s.append(tag("Daffodil International University", M, 936, 19, WHITE, 700))
    s.append(tag("Built on the live Epal ERP &#183; 12 companies &#183; 203 tables &#183; 1,376 routes", M, 962, 15, MUTED, 500))
    s.append("</svg>")
    return "\n".join(s)


# --------------------------------------------------------------- x-banner
def xbanner():
    W, H = 240.0, 600.0           # 2ft x 5ft
    BL = 10
    s = [head(24, 60, W, H, "EON X-banner 2 x 5 ft", bleed=BL)]
    s.append(f'<rect x="{-BL}" y="{-BL}" width="{W + 2 * BL}" height="{H + 2 * BL}" fill="{INK}"/>')
    s.append(f'<rect x="{-BL}" y="{-BL}" width="{W + 2 * BL}" height="{H + 2 * BL}" fill="url(#bg)"/>')
    s.append(f'<ellipse cx="{W * .8}" cy="120" rx="190" ry="190" fill="url(#glow)"/>')
    s.append(f'<ellipse cx="{W * .1}" cy="470" rx="170" ry="170" fill="url(#glow)" opacity="0.55"/>')
    M = 24
    s.append(orbit(W / 2, 68, 31))
    s.append(tag("EON", W / 2, 158, 62, WHITE, 800, anchor="middle", spacing=-1.6))
    s.append(tag("STALL U-13", W / 2, 182, 12.5, TEAL, 700, anchor="middle", spacing=2.2))
    s.append(f'<rect x="{M}" y="202" width="{W - 2 * M}" height="1.4" fill="{LINE}"/>')

    s.append(tag("One Brain.", W / 2, 252, 20, WHITE, 800, anchor="middle"))
    s.append(tag("Multi Decision Layer", W / 2, 280, 20, TEAL, 800, anchor="middle"))
    s.append(tag("for your ERP.", W / 2, 308, 20, WHITE, 800, anchor="middle"))

    for i, t in enumerate(["Ask in English or &#2476;&#2494;&#2434;&#2482;&#2494;.",
                           "Get the number, the rule,",
                           "and the action to take."]):
        s.append(tag(t, W / 2, 348 + i * 24, 13, MUTED, 500, anchor="middle"))

    for i, t in enumerate(["45 business subjects", "3 languages, natively written",
                           "No language model at all"]):
        y = 428 + i * 24
        s.append(f'<circle cx="{M + 4}" cy="{y - 4}" r="2.8" fill="{TEAL}"/>')
        s.append(tag(t, M + 14, y, 12.5, WHITE, 600))

    s.append(f'<rect x="{W / 2 - 32}" y="500" width="64" height="64" rx="5" fill="{WHITE}"/>')
    s.append(qr_rects(URL, W / 2 - 32, 500, 64, dark=INK, quiet=2))
    s.append(tag("eon.gulfrabit.com", W / 2, 580, 13, TEAL, 700, anchor="middle"))
    s.append(tag("Daffodil International University", W / 2, 594, 10.5, MUTED, 500, anchor="middle"))
    s.append("</svg>")
    return "\n".join(s)


# --------------------------------------------------------------- A4 leaflet
def leaflet():
    """A4 handout. Units here are millimetres: 1 user unit = 1 mm."""
    W, H = 210.0, 297.0
    BL = 3.0
    s = [f'''<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="{W + 2 * BL}mm" height="{H + 2 * BL}mm"
     viewBox="{-BL} {-BL} {W + 2 * BL} {H + 2 * BL}">
<title>EON A4 leaflet</title>
<defs>
  <linearGradient id="bg" x1="0" y1="0" x2="0.3" y2="1">
    <stop offset="0" stop-color="{INK2}"/><stop offset="1" stop-color="{INK}"/>
  </linearGradient>
  <radialGradient id="glow" cx="0.5" cy="0.5" r="0.5">
    <stop offset="0" stop-color="{TEAL}" stop-opacity="0.40"/>
    <stop offset="0.55" stop-color="{BLUE}" stop-opacity="0.16"/>
    <stop offset="1" stop-color="{BLUE}" stop-opacity="0"/>
  </radialGradient>
</defs>''']
    M = 16.0
    s.append(f'<rect x="{-BL}" y="{-BL}" width="{W + 2 * BL}" height="{H + 2 * BL}" fill="{WHITE}"/>')
    s.append(f'<rect x="{-BL}" y="{-BL}" width="{W + 2 * BL}" height="{46 + BL}" fill="{INK}"/>')
    s.append(f'<rect x="{-BL}" y="{-BL}" width="{W + 2 * BL}" height="{46 + BL}" fill="url(#bg)"/>')
    s.append(f'<ellipse cx="{W * .78}" cy="10" rx="70" ry="48" fill="url(#glow)"/>')

    # header: mark, wordmark, then stall line on its own baseline
    s.append(orbit(M + 9, 22, 8))
    s.append(tag("EON", M + 22, 29, 17, WHITE, 800, spacing=-0.5))
    s.append(tag("Stall U-13 &#183; University Zone", W - M, 19, 4.4, TEAL, 700, anchor="end"))
    s.append(tag("Bangladesh AI Business Summit 2026", W - M, 27, 4.4, MUTED, 500, anchor="end"))

    # headline
    s.append(tag("One Brain.", M, 76, 13, INK, 800, spacing=-0.4))
    s.append(tag("Multi Decision Layer", M, 92, 13, BLUE, 800, spacing=-0.4))
    s.append(tag("for your ERP.", M, 108, 13, INK, 800, spacing=-0.4))

    body = ["EON answers a business question from the live ERP &#8212; in",
            "English, &#2476;&#2494;&#2434;&#2482;&#2494; or Banglish. Not a chatbot reading a document:",
            "every figure is computed from the ledger, with the rule that",
            "produced it and the action to take next."]
    for i, t in enumerate(body):
        s.append(tag(t, M, 126 + i * 7.2, 4.5, "#44506B", 500))

    s.append(f'<rect x="{M}" y="162" width="{W - 2 * M}" height="0.3" fill="#D8DFEC"/>')
    rows = [("45", "business subjects, asked any way"),
            ("3", "languages, each written natively"),
            ("203", "ERP tables read directly"),
            ("0", "language models &#8212; nothing is generated")]
    for i, (k, v) in enumerate(rows):
        y = 180 + i * 15
        s.append(tag(k, M + 20, y, 11, BLUE, 800, anchor="end"))
        s.append(tag(v, M + 26, y - 1.5, 4.6, INK, 600))

    s.append(f'<rect x="{M}" y="{H - 52}" width="{W - 2 * M}" height="0.3" fill="#D8DFEC"/>')
    s.append(f'<rect x="{M}" y="{H - 44}" width="30" height="30" fill="{WHITE}"/>')
    s.append(qr_rects(URL, M, H - 44, 30, dark=INK, quiet=1))
    s.append(tag("Ask EON yourself", M + 36, H - 34, 6, INK, 700))
    s.append(tag("eon.gulfrabit.com", M + 36, H - 26, 5, BLUE, 700))
    s.append(tag("Daffodil International University", M + 36, H - 18, 4.4, "#6B7794", 500))
    s.append("</svg>")
    return "\n".join(s)

files = {"EON-backdrop-5.9x8.33ft.svg": backdrop(),
         "EON-xbanner-2x5ft.svg": xbanner(),
         "EON-leaflet-A4.svg": leaflet()}
for name, data in files.items():
    open(os.path.join(OUT, name), "w", encoding="utf-8").write(data)
    print("wrote", name, len(data), "bytes")
