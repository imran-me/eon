# EON — stall printing, Bangladesh AI Business Summit 2026

Everything here is sized from the organiser's own documents, not guessed.
Sources are filed in `reference/` so any number below can be checked.

## The facts that drive the artwork

| | |
| --- | --- |
| Event | Bangladesh AI Business Summit 2026 — "AI-fication For Tomorrow's Economy" |
| Date | **Thursday 5 November 2026** |
| Venue | Bangladesh-China Friendship Conference Center (BCFCC), Dhaka — **Milky Way Hall** |
| Organisers | Ministry of Science and Technology (GoB) + Dhaka Chamber of Commerce & Industry |
| **EON's stall** | **U-13**, **6' × 6'**, **University Students** zone |
| Listed as | EON — Daffodil International University (expo page `/expo/105/`) |
| Neighbours | U-14 Neuro (left), U-12 Krishi (right); U-15 Anonymous, U-11 WIN+R |
| Aisle | 1 ft gap front and back; 7 ft clear in front of the row |

## The two measurements that matter

Both come from the **Exhibitor Manual** (`reference/Exhibitor_Manual.pdf`):

1. **Backdrop panel — 5.9 ft × 8.33 ft.** Item 3: send the artwork *in Adobe
   Illustrator format* and **the organisers print and fix it for you**. This is
   the main graphic; it is the back wall of the stall.
2. **X-banner — 5 ft × 2 ft.** Item 17: a 6 × 6 stall may display **only one**
   X-banner, inside the stall. (A 10 × 10 or mini pavilion gets two — not us.)
   X-banners stand upright, so the artwork here is **2 ft wide × 5 ft tall**.

### The deadline, and it is the binding one

> "Kindly share us the artwork … by **30 October, 2026**. We will print and fix it
> on behalf of you. after 30 October, 2026 we will not be able to provide this
> facility."

**That is tomorrow.** Miss it and the backdrop has to be printed and fixed
privately, at our cost and with no guarantee of access to fix it.

## THE BACK WALL — one design, 5.9 x 8.33 ft (current deliverable)

`print/EON-backwall-5.9x8.33ft.pdf` + `-150dpi.png` (10921 x 15296 px).
Trim 70.8 x 99.96 in, 1 in bleed on every side. **White ground.**

### Where the TV sits, and how it was derived

Read off the organiser's dimensioned render, not guessed:

| | |
| --- | --- |
| Printable blue wall | 70.8 x 99.96 in (5.9 x 8.33 ft) |
| 43" TV screen | **38 x 21.5 in** — which is exactly 16:9, so those are the screen dimensions, not the bezel |
| Horizontal | branding strip 30 in + TV 38 in = 68 in of 70.8, leaving 1.4 in each side, so the TV sits **x = 30.5 .. 68.5 in** |
| Vertical | the render puts the TV top about 4 in below the wall top, so **y = 4 .. 25.5 in**; TV centre lands ~7.1 ft off the floor |
| **Reserved in the artwork** | **x 28.5 .. 70.8, y 1.0 .. 28.5** — slack on every side, so a mount a few inches out still covers nothing |

The reserve sits inside a **dark header band**, so the screen bezel reads as part
of the design rather than a hole in it. `EON-backwall-TV-GUIDE.pdf` is the same
artwork with that zone outlined in red — **check against it, never print it.**

The 1 ft organiser fascia and the 8 in "Company Name & Logo" band sit *above*
the blue wall and are DCCI's to print. They are not part of this file.

### What is on it, and why

Built to the owner's markup:

- **EON appears three times, and speaks.** The header figure, a waving figure
  asking a real question in English *and* বাংলা, and a third low on the panel
  inviting the visitor in Bangla. A mascot that asks a question stops someone in
  an aisle; a logo does not.
- **The headline is ~30% smaller than the first draft**, with real body copy
  under it instead of a bare slogan.
- **Two business sections** rather than feature-speak: *What it does for the
  business* (accounts, payroll, HR, sales, CRM; the rule, not just a number;
  what needs approval; drafts) and *Why you can act on it* (no language model,
  it shows the working).
- **The numbers** — 45 / 3 / 203 / 0 — glanceable from the aisle.
- **DIU logo** in the header, on a white chip so it keeps its own colours
  against the dark band.

### Vertical policy

Everything that carries meaning lives between the top and the middle, because
**staff stand at this wall.** The **QR is at ~3.2 ft off the floor**, where a
standing visitor can actually point a phone — not at the foot of the panel. Below
~85 in there is only a greeting and a credit line, over a quiet wash.

## The real shell — what actually gets printed (updated from the organiser's 3D render)

The render the owner supplied settles what the written documents never did. The
back wall is **5.9 ft x 8.33 ft**, but it is not all ours to print:

- a **43" TV (38 x 21.5 in)** is mounted on the right of the back wall;
- the usable graphic beside it is the **Branding Area, 2.5 ft wide x 8.33 ft**;
- the stall also has a **side wing, 2 ft wide x 8.3 ft**, which is what people
  see first as they walk down the aisle;
- above both runs a **1 ft organiser fascia** (Ministry / Summit / DCCI logos)
  and an **8 in "Company Name & Logo"** band — DCCI's to print, not ours;
- the counter is **3 ft x 1 ft x 2.5 ft tall**, with two chairs.

**These two panels are the deliverable.** The full-wall 5.9 x 8.33 artwork from
the first pass is in `superseded/`: it was drawn before the TV was known about,
so printing it would put content behind the screen. Do not send it.

| File | Trim | Bleed | Output |
| --- | --- | --- | --- |
| `EON-branding-area-2.5x8.33ft` | 30 x 99.96 in | 1 in | PDF + 4800 x 15296 px PNG @150dpi |
| `EON-side-panel-2x8.3ft` | 24 x 99.6 in | 1 in | PDF + 3900 x 15242 px PNG @150dpi |

### The avatar is the real one, not an illustration

The figure on both panels is the actual **three.js EON avatar** from
`ai-companion/js/eon-model.js`, rendered headlessly through
`designs/scripts/avatar-render.html` at 4200 x 6000 with a transparent
background, then cropped to its own alpha bounds. The source of truth for the
mascot is the running product, so the panel can never drift from what visitors
see on screen. Figures print ~3870 px tall, about **120 dpi at final size** —
correct for large-format viewed from a foot or more away.

Two poses, each doing a different job:

- **Side wing — waving.** It faces the aisle, so it greets people approaching.
  A **speech bubble** carries three real questions, one of them in বাংলা, so a
  passer-by learns what EON does without reading a word of body copy.
- **Branding area — standing, open.** Seen up close beside the TV, next to the
  numbers and the QR.

### Layout decisions that are load-bearing

- **The QR sits a little above mid-panel, roughly 3.5 ft off the floor.** In the
  first draft a single flexible spacer pooled every spare inch at the bottom and
  pushed the QR to knee height, where nobody can scan it. Content is now grouped
  into bands distributed with `space-between`.
- **Panels are laid out by the browser, not by hand-computed coordinates.** The
  first SVG pass positioned text by guessing glyph widths and several lines
  overlapped. The browser measures text; that class of bug is now impossible.
- **Nothing essential is below 2 ft**, where the counter and chairs sit.

### Regenerating

Serve the repo root, then:

    node designs/scripts/print-panels.mjs "<chrome.exe>" http://127.0.0.1:8777 <outdir>

`print-panels.mjs` passes `paperWidth`/`paperHeight` explicitly rather than
relying on `preferCSSPageSize` — `--print-to-pdf` silently falls back to Letter,
and that failure reads as a design bug rather than a flag bug. It also waits for
every image to decode before printing, because a PDF printed early shows an
empty box where the avatar should be.

To re-render the avatar (poses include `happy`, `wave`, `excited`, `celebrate`,
`point`, `thinking`):

    designs/scripts/avatar-render.html?w=4200&h=6000&frame=full&pose=wave

## Files (first pass — see above)

`print/` — artwork, ready to send. Each piece is supplied as **SVG** (opens
directly in Adobe Illustrator, fully editable, all live vector) and **PDF**.
`reference/` — PNG previews plus the organiser's own PDFs.

| File | Trim size | Bleed | Use |
| --- | --- | --- | --- |
| `EON-backdrop-5.9x8.33ft` | 5.9 × 8.33 ft | 1.5 in all round | **Send to DCCI by 30 Oct** |
| `EON-xbanner-2x5ft` | 2 × 5 ft | 1 in all round | Our own X-banner stand |
| `EON-leaflet-A4` | 210 × 297 mm | 3 mm all round | Handout at the stall |

## Layout decisions worth knowing

- **The backdrop's bottom third is treated as expendable.** A 6 × 6 stall
  normally has a table in front of the panel, so everything that must be read —
  the wordmark, the promise, the QR code — sits **above 2.5 ft from the floor**.
  The stat cards and the credit line are below it deliberately: if a table
  covers them, nothing essential is lost.
- **The QR sits at roughly 2.6–3.5 ft off the floor**, which is where a person
  standing at the table can actually point a phone. It resolves to
  `eon.gulfrabit.com/eon/` — the live panel, so a visitor can ask EON a question
  themselves while standing there.
- **No gradient is used on any text**, only on backgrounds. Gradient-filled type
  is where print files break; every headline is a solid colour.
- **Nothing is claimed that the repo cannot back.** 45 subjects, 3 languages,
  203 tables, 1,376 routes, no language model — all of these are measured
  figures from `CLAUDE.md`, not marketing.

## Before you send — two things to do in Illustrator

1. **Convert all text to outlines** (`Type → Create Outlines`). This matters
   most for the **বাংলা**: the printer will not have a Bengali font, and
   unoutlined Bangla drops to tofu boxes or reflows. Keep an un-outlined copy.
2. **Convert to CMYK** if the printer asks for it. The artwork is authored in
   RGB; the deep navy (`#0E1526`) and the teal (`#22A3C4`) both hold up in CMYK,
   but the teal will read slightly duller on press — that is normal and expected.

Palette: ink `#0E1526` / `#161F36`, teal `#22A3C4`, blue `#3B6FE0`,
muted `#A9B6D0`, white. Type is set in Inter (substitute Segoe UI or Helvetica);
Bangla in Noto Sans Bengali.

## Regenerating

The artwork is generated, not hand-drawn, so a change to a number or a line of
copy is a one-line edit and a re-run:

    python designs/scripts/make_designs.py designs/print

## What is NOT specified anywhere, and should be asked

The summit site, the Exhibitor Manual, the stall layout and the ToR between them
never state these, so do not assume them:

- whether the 6 × 6 shell comes with a **table, chairs, fascia name board,
  spotlights or a power socket** — ask the DCCI contacts below;
- the **backdrop's mounting method** (the 5.9 × 8.33 ratio suggests a panel in a
  frame, but nothing says whether anything is lost under a batten at top/bottom);
- whether a **monitor/TV** may be mounted on the back panel. EON demos live, so
  this is worth confirming — item 9 only bans loud audio, not screens.

DCCI exhibition contacts (from the manual):
Rafiqul Islam 01856665299 · Rafiqul Islam Rony 01673822552 ·
Mehadi Hasan 01712272930 · Sagor Hawlader 01722333665

## Other rules from the manual that affect the day

- Venue opens for stall setup **11:55 pm, 3 November**.
- Keep all printed material at the stall on the **night of 4 November** —
  guards are posted that night only.
- Morning of 5 November: entry with **laptop, charger and bag only**, by
  **6:30 am**, subject to SSF clearance.
- The **President of Bangladesh may visit**: stay at the stall through the
  inaugural session, no photos or selfies, and **nobody may leave the venue
  until the President departs**.
- No drones, no loud audio, no flammables or cylinders. SB pass worn at all
  times. Close the stall by **7:00 pm**. No food is provided to exhibitors.
