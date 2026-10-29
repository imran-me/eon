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

## Files

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
