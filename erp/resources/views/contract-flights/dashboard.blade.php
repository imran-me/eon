@extends('layout.app')
@section('meta-information')
    <title>Flight Dashboard</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* ══════════════════════════════════════════════════════════════════════
       FLIGHT DASHBOARD — the Manage Bank Accounts card wall, one card per
       ACTIVE flight (owner, 2026-09-16: "remove everything from the
       Dashboard, and bring the manage banks card layout ... instead of bank,
       here will be Flights with airline details").

       Transcribed from banks/dashboard.blade.php's .bank-acc-card with the
       prefixes renamed (.bank-acc-card → .fd-card, .bac-* → .fdc-*,
       #bankCardsGrid → #fdGrid) so the two sheets can never reach each
       other. The MECHANICS are the bank page's, unchanged, and the long
       reasoning behind each of them lives there — this sheet carries only
       the short version of each. If you change a band's height here,
       re-measure: the resting card must be exactly one 85px row and the open
       card must fit two rows plus the 12px gutter (182px).

       What each bank band became:
         mark          bank logo            → airline logo
         corner badge  company glyph        → (none — removed by the owner)
         rail colour   owning company       → status (open amber, boarding blue)
         band 1        bank · owner         → airline · CF number · agent
         band 2        A/C ···· branch      → DAC ···✈··· BKK  (flags either side)
         band 3        balance · age · flow → departure day · time · countdown
         band 4        TXN · in · out       → PAX · ticketed · vendor due
       ══════════════════════════════════════════════════════════════════════ */

    /* The Manage Bank Accounts banner, fd- prefixed so nothing here can reach
       another page's rules. */
    .fd-banner {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 55%, #7c3aed 100%);
        border-radius: 16px; padding: 0.55rem 0.9rem; color: #fff;
        display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
        margin-bottom: 1rem; position: relative; overflow: hidden;
    }
    .fd-banner::before {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background:
            radial-gradient(120% 150% at 88% -25%, rgba(255,255,255,0.20), transparent 58%),
            radial-gradient(90% 130% at -5% 125%, rgba(255,255,255,0.12), transparent 55%);
    }
    .fd-banner > * { position: relative; z-index: 1; }
    .fd-banner h2 { font-size: 1.1rem; font-weight: 700; margin: 0; letter-spacing: -0.01em; line-height: 1.25; }
    .fd-banner p { font-size: 0.78rem; opacity: 0.85; margin: 2px 0 0; }
    .fd-head { display: flex; align-items: center; gap: 11px; min-width: 0; }
    .fd-head .fd-icon {
        width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0;
        background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.22);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
        display: flex; align-items: center; justify-content: center; font-size: 1rem;
    }
    .fd-stats {
        display: flex; align-items: center; gap: 14px; flex-shrink: 0; flex-wrap: wrap;
        background: rgba(255,255,255,0.10); border: 1px solid rgba(255,255,255,0.16);
        border-radius: 12px; padding: 7px 14px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.12);
    }
    .fd-stat { text-align: left; min-width: 0; }
    .fd-stat + .fd-stat { border-left: 1px solid rgba(255,255,255,0.22); padding-left: 12px; }
    .fd-stat .val { font-size: 1.05rem; font-weight: 700; line-height: 1.25; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fd-stat .lbl { font-size: 0.58rem; opacity: 0.78; text-transform: uppercase; letter-spacing: 0.07em; line-height: 1.35; white-space: nowrap; }

    /* The control strip above the grid: sort toggle, title, view-mode toggle.
       Same 30px square buttons as the bank page's .card-sort-btn, tinted
       indigo while a non-default state is active so the screen says so. */
    .fd-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 0.85rem; }
    .fd-bar h3 { font-size: 0.95rem; font-weight: 700; color: #1e293b; margin: 0; }
    .fd-bar .fd-count { font-size: 0.72rem; font-weight: 600; color: #94a3b8; }
    .fd-tool {
        flex: 0 0 auto; width: 30px; height: 30px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid #e2e8f0; background: #fff; color: #64748b;
        border-radius: 8px; cursor: pointer; font-size: 0.82rem;
        transition: background .15s, color .15s, border-color .15s;
    }
    .fd-tool:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
    .fd-tool.is-alt { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
    .fd-tool.is-alt:hover { background: #e0e7ff; }

    /* ── The grid ─────────────────────────────────────────────────────────
       Rows are a FIXED 85px — one brief — and an opening card claims a second
       with grid-row: span 2, borrowing the cell below in its own column rather
       than stretching the row it shares (see the bank sheet for why). Mode
       "all" switches the rows to auto, because there every card is already
       the open height and there is nothing to borrow. */
    /* 196px minimum column: the owner wants FIVE cards a row at both 90% and
       100% zoom on a 1365px window with the sidebar open, where the grid is
       ~1030px wide — 5 × 196 + 4 × 8 = 1012 fits, 5 × 212 + 4 × 12 did not.
       The brief's route line (flags + codes) needs ~120px, and at 196 there
       is only 196 − 26 (border + padding) − 10 (gap) − 58 (mark) = 102, so
       the container query below tightens the card in the band where five
       columns are squeezed (grid 1012–1109px): narrower paddings, a 48px
       mark, a smaller route gap. Below 1012 the grid drops to four columns
       and cards are ≥ 241px, so the compact set is not needed there. */
    #fdGrid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(196px, 1fr));
        gap: 0.5rem; align-items: start; grid-auto-rows: 85px;
        container-type: inline-size;
    }
    #fdGrid.all-expanded { grid-auto-rows: auto; }
    @container (min-width: 1012px) and (max-width: 1109px) {
        #fdGrid .fd-card { padding-left: 12px; padding-right: 6px; }
        #fdGrid .fdc-full-in { margin: 0 -6px 0 -12px; padding: 0 6px 0 12px; }
        #fdGrid .fdc-brief-row { gap: 8px; }
        #fdGrid .fdc-logo { flex-basis: 48px; min-width: 48px; }
        #fdGrid .fdc-logo.is-mono { flex-basis: 48px; }
        #fdGrid .fb-name { gap: 4px; font-size: 13px; }
    }

    /* ── The card ─────────────────────────────────────────────────────────
       --fdc-tint  the airline's colour; only the monogram fallback wears it.
       --fdc-co    the status colour: the rail, the corner bloom, the hover ring.
       Both arrive inline per card. */
    .fd-card {
        --fdc-tint: #6366f1;
        --fdc-co: #64748b;
        --fdc-co-rgb: 100, 116, 139;
        --fdc-ease: cubic-bezier(.22, 1, .36, 1);
        position: relative; overflow: hidden;
        border: 1px solid rgba(15,23,42,0.20); border-radius: 14px;
        background:
            radial-gradient(135% 95% at 100% 0%, rgba(var(--fdc-co-rgb), 0.09), transparent 56%),
            #fff;
        padding: 5px 9px 0 15px;
        box-shadow: 0 1px 2px rgba(7,12,30,0.06), 0 3px 10px -5px rgba(7,12,30,0.10);
        cursor: pointer;
        transform-origin: center top;
        transition: transform .3s var(--fdc-ease), box-shadow .3s var(--fdc-ease), border-color .24s ease;
    }
    #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover {
        grid-row: span 2;
        z-index: 5;
        transform: scale(1.03);
        border-color: rgba(var(--fdc-co-rgb), 0.5);
        box-shadow: 0 20px 42px -24px rgba(var(--fdc-co-rgb), 0.5),
                    0 6px 16px -10px rgba(7,12,30,0.18);
    }
    /* Bottom row opens UPWARDS into the cell above (placed there, not hung
       over it). --fdc-col / --fdc-row are written by markLastRowCards(). */
    .fd-card.fdc-up { align-self: end; transform-origin: center bottom; }
    #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card.fdc-up:hover {
        grid-column: var(--fdc-col);
        grid-row: var(--fdc-row) / span 2;
    }
    /* A batch still open past its departure day. */
    .fd-card.is-late { border-color: rgba(190,18,60,0.35); }

    /* One card at a time: while a card is open its neighbours cannot take the
       hover, so a re-flow can never hand the hover to the card that slid
       under a still pointer (the blink the bank page had on 2026-08-22). */
    #fdGrid:not(.no-hover-fx):not(.all-expanded):has(.fd-card:hover) .fd-card:not(:hover) {
        pointer-events: none;
    }

    /* Mode "all": every card open, no pointer needed. */
    #fdGrid.all-expanded .fd-card .fdc-brief { grid-template-rows: 0fr; opacity: 0; }
    #fdGrid.all-expanded .fd-card .fdc-full { grid-template-rows: 1fr; opacity: 1; }
    #fdGrid.all-expanded .fd-card .fdc-full-in { transform: scale(1); }

    /* Both states animate on grid-template-rows; the brief fades out ahead of
       the full fading in. Vertical spacing lives one level in, on the row
       elements, so a 0fr state's padding cannot leak into the other's height. */
    .fdc-brief, .fdc-full {
        display: grid; grid-template-rows: 1fr;
        transition: grid-template-rows .26s var(--fdc-ease), opacity .18s ease;
    }
    .fdc-brief-in, .fdc-full-in { overflow: hidden; min-height: 0; }

    .fdc-brief { opacity: 1; }
    #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover .fdc-brief { grid-template-rows: 0fr; opacity: 0; transition-duration: .4s, .12s; }
    /* 7px above the mark and a 13px shim below: together they make the
       resting card exactly one 85px row. */
    .fdc-brief-row { display: flex; align-items: flex-start; gap: 10px; padding: 7px 0 13px; }
    .fb-id   { flex: 1 1 120px; min-width: 0; }   /* basis = the route line's width, see .fdc-logo */
    .fb-co   { font-size: 9.5px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase;
               color: #6b7686; line-height: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fb-name { font-size: 13.8px; font-weight: 700; letter-spacing: -0.015em; color: #1a2333; line-height: 17px;
               margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
               display: flex; align-items: center; gap: 5px; }
    .fb-name .arr { color: #94a3b8; font-size: 11px; }
    .fb-bal  { font-size: 16px; font-weight: 700; letter-spacing: -0.025em; color: #111827; line-height: 20px;
               margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap;
               overflow: hidden; text-overflow: clip; }
    .fb-bal .cur { font-size: 9px; font-weight: 700; letter-spacing: 0.05em; color: #8b95a5; margin-left: 5px; }
    .fb-bal .next-label { margin-right: 3px; color: #4f46e5; font-size: 8px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .fd-card.is-late .fb-bal { color: #dc2626; }
    /* The CF number on the resting card too (owner, 2026-10-01: "show the
       CF… code in the card, while without hovering too"): a small blue tag in
       the top-right corner, like the number on a bank card; the open card
       carries it in its sub line. A float keeps the airline line's FIRST line
       clear of it, and that line may take a second line on any card — the
       row's bottom shim gives up the 8px it needs, so the card stays one
       85px row. */
    .fdc-brief-row { position: relative; }
    .fb-cf { position: absolute; top: 6px; right: 0; padding: 0 5px; border-radius: 5px;
             font: 700 10px/14px ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: 0;
             color: #2563eb; background: rgba(37, 99, 235, .08); white-space: nowrap; }
    .fdc-brief-row:has(.fb-cf) { padding-bottom: 5px; }
    .fdc-brief-row:has(.fb-cf) .fb-id { min-height: 66px; }
    .fdc-brief-row:has(.fb-cf) .fb-co { display: block; white-space: normal; max-height: 24px; overflow: hidden; }
    .fdc-brief-row:has(.fb-cf) .fb-co::before { content: ''; float: right; width: var(--fb-cf-w, 50px); height: 12px; }

    /* The full card. The negative margins pull the inner out to the card's
       edges so the strip can bleed full-width inside the height clip. */
    .fdc-full { grid-template-rows: 0fr; opacity: 0; }
    #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover .fdc-full { grid-template-rows: 1fr; opacity: 1; transition-duration: .4s, .26s; transition-delay: 0s, .1s; }
    .fdc-full-in { transform: scale(.965); transform-origin: center top; transition: transform .4s var(--fdc-ease); }
    #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover .fdc-full-in { transform: scale(1); }
    .fdc-full-in { margin: 0 -9px 0 -15px; padding: 0 9px 0 15px; }

    /* The status rail down the left edge. Thickens on hover. */
    .fd-card::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; z-index: 2;
        background: linear-gradient(180deg, var(--fdc-co) 62%, rgba(var(--fdc-co-rgb), 0.55));
        transition: width .28s cubic-bezier(.2,.7,.2,1);
    }
    #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover::before { width: 7px; }

    /* The glaze that sweeps the face once on hover. pointer-events:none so it
       never eats the click. */
    .fd-card::after {
        content: ''; position: absolute; top: 0; left: -75%; width: 62%; height: 100%;
        z-index: 3; pointer-events: none; transform: skewX(-18deg);
        background: linear-gradient(105deg, transparent 12%, rgba(255,255,255,0.30) 42%,
                    rgba(255,255,255,0.58) 50%, rgba(255,255,255,0.30) 58%, transparent 88%);
        transition: left .7s ease;
    }
    #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover::after { left: 145%; }

    .fdc-head, .fdc-route, .fdc-dep, .fdc-strip { position: relative; z-index: 1; }

    @media (prefers-reduced-motion: reduce) {
        .fd-card, .fd-card::before, .fd-card::after,
        .fd-card .fdc-logo, .fdc-brief, .fdc-full { transition: none; }
        #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover { transform: none; }
        #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover::after { left: -75%; }
    }

    /* The airline's mark, directly on the card, no plate. 58px tall, and as
       WIDE as its own ink asks for, up to 104px: a square roundel (Turkish)
       takes 58, a 3.5:1 wordmark (US-Bangla) takes 104.

       ZOOMED TO THE INK, AUTOMATICALLY (owner, 2026-09-16: "if the logo got
       white spaces very much, it will automatically zoom in, and will just
       2-3 white edge"). The files are used exactly as dropped in
       public/airline-logos/ — most arrive as a small mark floating in a big
       transparent or white square, and in a fixed 58×58 box those came out
       58×15 beside a roundel that filled its box. fitMarks() (script below)
       reads each mark's pixels once it has loaded, finds the bounding box of
       everything that is neither transparent nor near-white, and shows THAT
       box — plus a 4% edge, the two or three pixels the owner asked to keep
       — scaled to fill the 58px height. Nothing is edited on disk; a new
       logo is fitted the first time it is seen. The measured box is cached
       in localStorage so on later visits the card is sized before the image
       arrives and nothing shifts.

       Pre-fit (or if the pixels cannot be read) the mark simply renders
       contained in the box, as before — the zoom is an improvement layered
       on a state that is already correct.

       The box gives way before the text does: flex-shrink 100 against the
       text column's 1, so on a narrow card the mark shrinks back toward 58px
       and the route line keeps its 120px — measured with flags, the widest line in the
       brief. Only once the mark is at 58 does the text start to ellipsise.
       layoutMark() re-scales the crop to whatever width the box actually
       got, via a ResizeObserver, so a shrunk box shows the whole mark
       smaller rather than a slice of it.

       The MONOGRAM fallback keeps a plate — initials need a shape — and is
       also what renders if the logo file 404s, so a missing mark can never
       show a broken image. */
    .fdc-logo {
        position: relative; height: 58px; flex: 0 100 58px; min-width: 58px; max-width: 104px;
        display: flex; align-items: center; justify-content: center;
        transition: transform .28s cubic-bezier(.2,.7,.2,1);
    }
    /* The crop window. Before the fit it is just a centred, height-limited
       frame the mark sits in (contain); after it, an overflow-hidden window
       sized by layoutMark() with the mark positioned inside so only the ink
       shows. */
    .fdc-crop { display: flex; align-items: center; justify-content: center; height: 100%; max-width: 100%; }
    .fdc-logo.is-fit > .fdc-crop { position: relative; overflow: hidden; height: auto; max-width: none; flex: none; }
    .fdc-logo.is-fit > .fdc-crop > img.mark { position: absolute; max-width: none; max-height: none; }
    #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover .fdc-logo { transform: translateY(-1px) scale(1.05); }
    .fdc-logo img.mark { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
    .fdc-logo .fdc-mono { display: none; font-size: 17px; font-weight: 800; letter-spacing: 0.01em; color: #fff; }
    .fdc-logo.is-mono { flex-basis: 58px; background: var(--fdc-tint); border-radius: 12px; }
    .fdc-logo.is-mono > .fdc-crop { display: none; }
    .fdc-logo.is-mono > .fdc-mono { display: block; }

    /* TWO AIRLINES, ONE CARD (owner, 2026-09-21). A via route is two tickets
       on two carriers, so the card carries both marks and the name line reads
       "Gulf Air · Turkish".

       MAIN MARK + SMALL MARK (owner, 2026-10-01 — chosen over the
       overlapping pile, which "looks so odd", and a two-row tile). Leg 1's
       mark is the big one; each later leg's mark is a small one tucked under
       its bottom-right corner. The small mark is a .fdc-logo of its own
       NESTED in the main one (so the card's flex row is untouched), a 20px
       box as wide as its ink asks, up to 40px, with its own ink fit and its
       own monogram, on its own airline's colour, if the file is missing.
       Plain logo — no plate, ring, shadow or hover pop (owner, same day:
       "dont need border, and floating effect").

       THE TWO NEVER CROSS (owner, 2026-10-01: "move the bigger logo a bit up
       and left, remove the space at the top … logos should not cross their
       area"). The main mark is tucked up-left (-5px / -3px) and drawn a
       little smaller, and the small mark sits 3px BELOW its box, right
       edges aligned. Heights are budgeted so nothing moves:
         brief — main 44px from row-top 2, small mark 49–69 of the 78px row
                 (the two-line name column below sets the row);
         open  — main 38px with a 25px bottom margin, so the head is still
                 the single mark's 58px; the small mark ends above the
                 route line.
       A third leg (never seen, the data allows it) sits left of the second. */
    .fdc-logo.has-badge { height: 44px; min-width: 44px; max-width: 79px; margin: -5px 0 0 -3px; align-self: flex-start; }
    .fdc-head .fdc-logo.has-badge { height: 38px; min-width: 38px; max-width: 69px; margin-bottom: 25px; }
    .fdc-logo.has-badge, .fdc-logo.has-badge.is-mono { flex-basis: 44px; }
    .fdc-head .fdc-logo.has-badge, .fdc-head .fdc-logo.has-badge.is-mono { flex-basis: 38px; }
    #fdGrid .fdc-logo.has-badge { min-width: 38px; }   /* not the five-column band's 48px */
    .fdc-logo .fdc-badge { position: absolute; right: 0; top: calc(100% + 3px); height: 20px; width: 20px;
                           min-width: 0; max-width: none; flex: none; margin: 0; transition: none; }
    .fdc-logo .fdc-badge ~ .fdc-badge { right: 46px; }
    .fdc-logo .fdc-badge.is-mono { background: var(--fdc-tint); border-radius: 5px; width: auto; padding: 0 5px; }
    .fdc-logo .fdc-badge .fdc-mono { font-size: 9px; line-height: 20px; }
    /* The five-column band's 48px mark minimum (container query at the top)
       is for the main mark, not the small one. */
    #fdGrid .fdc-logo.fdc-badge { min-width: 0; flex-basis: auto; }
    /* Beside a two-airline mark the name line may take two lines ("GULF AIR
       · TURKISH" does not fit one at 9.5px caps). The text column grows from
       52 to 66px and the row's bottom shim gives up the same 8px, so the
       resting card is still one 85px row. */
    .fdc-brief-row:has(.fdc-badge) { padding-bottom: 5px; }
    .fdc-brief-row:has(.fdc-badge) .fb-id { min-height: 66px; }
    .fdc-brief-row:has(.fdc-badge) .fb-co { white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    /* The small mark rides on the main mark's hover lift; no lift of its own. */
    #fdGrid:not(.no-hover-fx):not(.all-expanded) .fd-card:hover .fdc-logo .fdc-badge { transform: none; }

    /* HOVER: A LITTLE FLIGHT (owner, 2026-10-01: "give some fun animations
       while hovering, with the two logos"). Leg 1's mark dips its wing, a
       small plane (.fdc-hop) hops off it along an arc that stays within the
       mark's own width (the head's gap to the text is 7px), and leg 2's mark
       answers where it lands — the via route, told in about a second.
         hover / all modes — plays in the OPEN card's head (the brief has
           faded by then); leg 2's mark pops in as the plane lands.
         compact mode (.no-hover-fx, cards never open; owner, same day: "in
           this mode too") — plays on the resting card itself; leg 2's mark
           is already showing, so it bounces as the plane lands instead.
       The main mark's IMAGE moves, not its box, so the small mark and the
       plane are not swung with it. --fdc-hop-y is the drop from take-off to
       leg 2's mark: 38px under the head's 38px mark, 44px under the
       brief's 44px one. Off for reduced motion; at rest the plane is hidden. */
    .fdc-logo .fdc-hop { position: absolute; left: calc(100% - 14px); top: 6px; font-size: 9px; line-height: 1;
                         color: #6366f1; opacity: 0; pointer-events: none; --fdc-hop-y: 38px; }
    .fdc-brief .fdc-logo .fdc-hop { --fdc-hop-y: 44px; }
    #fdGrid:not(.no-hover-fx) .fd-card:hover .fdc-head .fdc-logo.has-badge > .fdc-crop,
    #fdGrid.no-hover-fx .fd-card:hover .fdc-brief .fdc-logo.has-badge > .fdc-crop {
        transform-origin: 55% 85%; animation: fdc-flap .7s cubic-bezier(.3, .7, .3, 1) .12s both; }
    #fdGrid:not(.no-hover-fx) .fd-card:hover .fdc-head .fdc-logo.has-badge > .fdc-hop,
    #fdGrid.no-hover-fx .fd-card:hover .fdc-brief .fdc-logo.has-badge > .fdc-hop {
        animation: fdc-hop .65s cubic-bezier(.45, .05, .4, 1) .3s both; }
    #fdGrid:not(.no-hover-fx) .fd-card:hover .fdc-head .fdc-logo.has-badge > .fdc-badge {
        animation: fdc-land .5s cubic-bezier(.34, 1.56, .64, 1) .82s both; }
    #fdGrid.no-hover-fx .fd-card:hover .fdc-brief .fdc-logo.has-badge > .fdc-badge {
        animation: fdc-bump .5s cubic-bezier(.34, 1.56, .64, 1) .85s both; }
    @keyframes fdc-flap {
        0%   { transform: none; }
        35%  { transform: translateY(-2px) rotate(-10deg); }
        65%  { transform: rotate(4deg); }
        100% { transform: none; }
    }
    @keyframes fdc-hop {
        0%   { opacity: 0; transform: translate(0, 0) rotate(-15deg) scale(.6); }
        15%  { opacity: 1; }
        50%  { transform: translate(5px, calc(var(--fdc-hop-y) * .42)) rotate(70deg) scale(1); }
        85%  { opacity: 1; }
        100% { opacity: 0; transform: translate(-2px, var(--fdc-hop-y)) rotate(110deg) scale(.7); }
    }
    @keyframes fdc-land {
        0%   { opacity: 0; transform: scale(.3); }
        60%  { opacity: 1; transform: scale(1.18); }
        100% { opacity: 1; transform: scale(1); }
    }
    @keyframes fdc-bump {
        0%   { transform: none; }
        30%  { transform: translateY(2px) scale(.86); }
        65%  { transform: translateY(-1px) scale(1.16); }
        100% { transform: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        #fdGrid .fd-card:hover .fdc-logo.has-badge > .fdc-crop,
        #fdGrid .fd-card:hover .fdc-logo.has-badge > .fdc-hop,
        #fdGrid .fd-card:hover .fdc-logo.has-badge > .fdc-badge { animation: none; }
    }

    /* Small inline flags — the brief's route line and the full card's route
       band. 3:2, a hairline so a white-edged flag (Japan) still has an edge. */
    .fdc-flag { width: 15px; height: 10px; border-radius: 2px; object-fit: cover; flex-shrink: 0;
                box-shadow: 0 0 0 1px rgba(15,23,42,0.12); display: inline-block; vertical-align: -1px; }

    /* Band 1 — who. Airline, then CF number and agent. */
    .fdc-head { display: flex; align-items: center; gap: 7px; padding-top: 7px; }
    .fdc-id   { flex: 1 1 115px; min-width: 0; }
    .fd-card .name { font-size: 13.5px; font-weight: 700; color: #1a2333; line-height: 17px;
                     letter-spacing: -0.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fd-card .sub  { font-size: 11px; font-weight: 600; color: #5c6775; line-height: 13px;
                     letter-spacing: 0.01em; margin-top: 1px;
                     white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fd-card .sub .cf { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #2563eb; letter-spacing: 0; }
    /* The agent, on a line of its own (owner, 2026-09-16: "in the extended
       view, I need the agent name"). It used to share the CF-number line and
       "EUNION TRAVELS" came out as "EUNION TR…". The head's text column is
       vertically centred beside a 58px mark and was using 31px of it, so a
       third 13px line costs the card no height — the open card is still
       182px. Ink a shade darker than the sub line: it is the thing asked for. */
    .fd-card .agent { font-size: 11px; font-weight: 600; color: #334155; line-height: 13px; margin-top: 1px;
                      white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fd-card .agent i { font-size: 9px; color: #94a3b8; margin-right: 3px; }
    .fd-card .agent.is-none { color: #94a3b8; font-weight: 500; }
    /* Something on this batch still needs a hand — an unticketed head, a
       flagged passenger, or an immigration officer not yet named. */
    .fdc-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #ef4444; margin-right: 4px; vertical-align: 1px; }

    /* Band 2 — the route. Origin at the left, destination at the right, a
       dotted leader between them with the plane on it — the bank card's
       A/C ···· branch line, redrawn as a flight path. */
    /* Height budget: this row is 13px tall like the bank card's A/C line.
       The chips carry an explicit line-height because as inline-flex items
       they otherwise take a 17px line box, and those 4px plus the margins
       put the open card at 189px — past the 182px two rows allow. */
    .fdc-route { display: flex; align-items: center; gap: 5px; margin-top: 8px; height: 13px; }
    .fdc-route .ap { display: inline-flex; align-items: center; gap: 4px; flex: 0 0 auto; line-height: 13px;
                     font-size: 11px; font-weight: 700; color: #334155; letter-spacing: 0.04em; }
    .fdc-route .lead { flex: 1 1 8px; min-width: 6px; height: 0; border-bottom: 1px dotted #c9d2df; }
    .fdc-route .pl { font-size: 9px; color: #94a3b8; flex: 0 0 auto; }
    .fdc-route .cls { font-size: 8px; font-weight: 700; letter-spacing: 0.08em; color: #b9c2cf; text-transform: uppercase; flex: 0 0 auto; }
    .fdc-route .group-route { display: inline-flex; align-items: center; gap: 5px; color: #334155; font-size: 11px; font-weight: 800; }
    .fdc-route .group-route i { color: #6366f1; font-size: 9px; }
    .fdc-route .group-meta { margin-left: auto; color: #64748b; font-size: 9px; font-weight: 700; white-space: nowrap; }

    /* Band 3 — when. The one loud thing on the card: the departure day, its
       time beside it, and the countdown pill at the right. The weekday and
       the arrival are in the line's tooltip — a third item on this line was
       sheared to "Th" beside the pill on a 219px card. */
    .fdc-dep { display: flex; align-items: baseline; justify-content: space-between; gap: 6px; margin: 9px 0 10px; }
    .fdc-dep .money { flex: 0 0 auto; white-space: nowrap; }
    .fdc-dep .amt { font-size: 22.95px; font-weight: 700; line-height: 28.35px; color: #111827;
                    letter-spacing: -0.03em; font-variant-numeric: tabular-nums; }
    .fdc-dep .cur { font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; color: #8b95a5; margin-left: 5px; }
    .fdc-dep .next-label { margin-right: 5px; color: #4f46e5; font-size: 8px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; vertical-align: 3px; }
    .fd-card.is-late .fdc-dep .amt { color: #dc2626; }
    .fdc-flow { flex-shrink: 0; margin-left: auto; font-size: 8.5px; font-weight: 700; line-height: 14px;
                padding: 1px 6px; border-radius: 999px; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .fdc-flow.today { background: #ecfdf5; color: #15803d; }
    .fdc-flow.soon  { background: #fffbeb; color: #b45309; }
    .fdc-flow.late  { background: #fef2f2; color: #b91c1c; }
    .fdc-flow.idle  { background: #f6f8fb; color: #7c8797; }

    /* Band 4 — the numbers. Full-bleed strip, three hairline-divided cells. */
    .fdc-strip {
        display: grid; grid-template-columns: repeat(3, 1fr);
        margin: 0 -9px 0 -15px; border-top: 1px solid #eef1f6;
        background: linear-gradient(180deg, #fbfcfe, #f6f8fc);
        border-radius: 0 0 13px 13px;
    }
    .fdc-strip .cell { padding: 6px 5px 8px; text-align: center; border-left: 1px solid #eef1f6; min-width: 0; }
    .fdc-strip .cell:first-child { border-left: 0; text-align: left;  padding-left: 15px; }
    .fdc-strip .cell:last-child  { text-align: right; padding-right: 9px; }
    .fdc-strip .cv { font-size: 11px; font-weight: 700; line-height: 14px; color: #475569;
                     font-variant-numeric: tabular-nums;
                     white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fdc-strip .cl { font-size: 7.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
                     color: #6b7686; line-height: 11px; margin-top: 1px; }
    .fdc-strip .ok   .cv { color: #15803d; }
    .fdc-strip .warn .cv { color: #b45309; }
    .fdc-strip .bad  .cv { color: #b91c1c; }
    .fdc-strip .cell.is-zero .cv { color: #ccd3dd; }

    /* Nothing active. */
    .fd-empty { grid-column: 1 / -1; background: #fff; border: 1px dashed #cbd5e1; border-radius: 14px;
                padding: 2.5rem 1rem; text-align: center; color: #64748b; }
    .fd-empty i { font-size: 1.8rem; color: #cbd5e1; display: block; margin-bottom: 0.6rem; }
    .fd-empty a { color: #4f46e5; font-weight: 600; }
    .fd-pagination { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 12px; background: #f8fafc; }
    .fd-pagination label, .fd-page-actions { display: flex; align-items: center; gap: 8px; margin: 0; }
    .fd-page-actions { margin-left: auto; }
    .fd-pagination select, .fd-pagination button { border: 1px solid #dce2eb; border-radius: 6px; padding: 5px 9px; background: #fff; color: #4338ca; font-size: 12px; }
    .fd-pagination button:disabled { color: #94a3b8; cursor: default; opacity: .65; }
    .fd-pagination button:not(:disabled):hover { background: #eef2ff; border-color: #a5b4fc; }
    .fd-pagination button:focus-visible, .fd-pagination select:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
    @media (max-width: 640px) { .fd-page-actions { margin-left: 0; width: 100%; justify-content: flex-end; } }
    .fd-table-panel { margin-top: 24px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
    .fd-table-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px; border-bottom: 1px solid #e2e8f0; }
    .fd-table-head h3 { margin: 0; color: #1e293b; font-size: .95rem; font-weight: 700; }
    .fd-table-head a, .fd-table a { color: #4f46e5; font-weight: 600; }
    .fd-table-head a { font-size: .8rem; }
    .fd-table-scroll { overflow-x: auto; }
    .fd-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .83rem; }
    .fd-table th { background: #eef2ff; color: #4338ca; padding: 13px 12px; border-bottom: 1px solid #c7d2fe; text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    .fd-table td { padding: 8px 12px; border-bottom: 1px solid #b8c4d4; color: #334155; }
    .fd-table tbody tr:last-child td { border-bottom: none; }
    .fd-table tbody tr { transition: background-color .15s ease, box-shadow .15s ease; }
    .fd-table tbody tr:hover { background: #eef2ff; box-shadow: inset 3px 0 0 #6366f1; }
    .fd-table .fd-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fd-table .fd-passengers { text-align: left; font-variant-numeric: tabular-nums; white-space: nowrap; }
    #fdPassengerTable { table-layout: fixed; width: 100%; }
    #fdPassengerTable th { white-space: normal; padding: 12px 8px; }
    #fdPassengerTable td { padding: 8px 8px; vertical-align: top; overflow-wrap: anywhere; }
    #fdPassengerTable .fd-num { white-space: normal; }
    #fdPassengerTable .fd-amount-column { padding-left: 8px; padding-right: 8px; }
    /* Opens the passenger's full record as an A4 sheet. Sits under the profit
       figure in its own column so it reads as "more about this row", not as
       one more number. */
    #fdPassengerTable .fdl-eye, .fd-table .fdl-eye { margin-top: 6px; border: 1px solid #dbe3ef; background: #fff; color: #64748b;
        width: 26px; height: 24px; border-radius: 6px; cursor: pointer; font-size: .72rem; line-height: 1; padding: 0; }
    #fdPassengerTable .fdl-eye:hover, .fd-table .fdl-eye:hover { background: #eef2ff; border-color: #a5b4fc; color: #4338ca; }
    #fdPassengerTable small { max-width: none; }
    /* Ledger detail lines: a small grey glyph before each label. */
    #fdPassengerTable .fd-ico { color: #b6c0cf; font-size: .62rem; width: 12px; margin-right: 4px; text-align: center; }
    /* One line, clipped with an ellipsis — the full text is in the tooltip.
       Only possible because both tables are fixed-layout: in an auto-layout
       table a nowrap line would widen its column instead of clipping. */
    .fd-table .fd-clip { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* The flights table: fixed columns so long names clip instead of wrapping
       or pushing the table into a horizontal scroll at 100% zoom. */
    .fd-table { table-layout: fixed; width: 100%; }
    .fd-table th { white-space: normal; padding: 12px 6px; }
    .fd-column-sort {
        display: flex; align-items: center; gap: 5px; width: 100%; padding: 0;
        border: 0; background: transparent; color: inherit; font: inherit;
        letter-spacing: inherit; text-transform: inherit; text-align: inherit; cursor: pointer;
    }
    .fd-column-sort i { flex-shrink: 0; opacity: .45; font-size: 10px; }
    .fd-table .fd-num .fd-column-sort { justify-content: flex-end; }
    .fd-table th[aria-sort="ascending"] .fd-column-sort i,
    .fd-table th[aria-sort="descending"] .fd-column-sort i { opacity: 1; color: #4338ca; }
    .fd-column-sort:hover { color: #4338ca; }
    .fd-column-sort:focus-visible { outline: 2px solid #6366f1; outline-offset: 4px; border-radius: 3px; }
    .fd-table td { padding: 8px 6px; }
    /* Every cell reads against the row's FIRST line (owner, 2026-09-22).
       .fd-table td is vertical-align:middle, so on a row four lines deep the
       one-line figures floated ~25px below the flight they belong to. Applied
       to the whole row, not just the money columns — top-aligning those alone
       would lift them ABOVE the departure and airline beside them. This is
       what #fdPassengerTable already does, which is why the ledger reads
       right. */
    .fd-table td { vertical-align: top; }
    /* And the eye sits UNDER its figure rather than beside it — inline, it
       was pushing "৳0" off the right edge of the column. */
    .fd-table .fdl-eye { display: block; margin-left: auto; }
    /* The passenger and the agent are both links; the agent's name is bold
       and violet so the party reads apart from the person travelling. */
    #fdPassengerTable a.fd-passenger { color: #1e293b; font-weight: 700; }
    #fdPassengerTable .fd-party { color: #7c3aed; font-weight: 700; }
    /* The PNR is a plain-weight link into Ticketing. */
    #fdPassengerTable .fd-pnr { font-weight: 500; color: #4f46e5; }
    #fdPassengerTable .fd-cost { color: #2563eb; }
    .fd-table .fd-nowrap { white-space: nowrap; }
    .fd-table .fd-agent-name { display: flex; align-items: center; gap: 5px; font-weight: 400; color: #334155; }   /* same as the route line (owner, 2026-10-03: "so bold") */
    .fd-table .fd-agent-name i { flex: 0 0 auto; color: #6366f1; font-size: .65rem; }
    .fd-table .fd-airport-detail { display: flex; align-items: center; gap: 4px; }
    .fd-table .fd-airport-detail i { flex: 0 0 auto; width: 11px; color: #818cf8; font-size: .6rem; text-align: center; }
    .fd-table small { display: block; color: #94a3b8; font-size: .7rem; margin-top: 1px; }
    .fd-table .fd-ledger-date { color: #1e293b; font-weight: 600; }   /* the list's .mfp-date */
    .fd-table-status { display: inline-block; padding: 3px 8px; border-radius: 6px; background: #fffbeb; color: #b45309; font-size: .7rem; font-weight: 600; }
    .fd-table-status.boarding { background: #eff6ff; color: #2563eb; }
    .fd-table .fd-table-empty { text-align: center; padding: 32px; color: #64748b; }
    .fd-table-panel { border-radius: 12px; }
    .fd-table-head { padding: 10px 12px; background: #f8fafc; flex-wrap: wrap; gap: 8px; }
    .fd-table-head input, .fd-table-head select, .fd-table-head button { border: 1px solid #dbe3ef; border-radius: 7px; padding: 8px 10px; font-size: .72rem; background: #fff; color: #64748b; min-width: 0; }
    .fd-table-head input[type="search"] { flex: 1 1 260px; }
    .fd-table-head button { background: #4f46e5; color: #fff; cursor: pointer; }
    .fd-table-head label, #fdTableCount { font-size: .7rem; color: #94a3b8; }
    .fd-summary-strip {
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
        gap: 8px 16px; width: calc(100% + 32px); flex: 0 0 calc(100% + 32px);
        margin: -10px -16px 0; padding: 10px 16px;
        background: linear-gradient(110deg, #4338ca, #5b4ddd 65%, #6d42d8); color: #fff;
    }
    .fd-summary-strip .fd-stat .val { color: #fff; }
    .fd-summary-strip .fd-stat .lbl { color: #eef2ff; }
    .fd-summary-strip .fd-stats { gap: 8px; }
    .fd-summary-strip .fd-stat, .fd-summary-strip .fd-stat + .fd-stat {
        padding: 5px 11px; border: 1px solid #ffffff30; border-radius: 8px;
        background: linear-gradient(180deg, #ffffff24, #ffffff10);
        box-shadow: 0 3px 6px #24166b26, inset 0 1px 0 #ffffff20;
        transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
    }
    .fd-summary-strip .fd-stat:hover {
        transform: translateY(-2px); background-color: #ffffff12;
        box-shadow: 0 5px 10px #24166b33, inset 0 1px 0 #ffffff30;
    }
    .fd-stat-link { font: inherit; color: inherit; cursor: pointer; }
    .fd-stat-link:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    .fd-stat-link.is-active { background: #ffffff30; box-shadow: inset 0 0 0 1px #ffffff80; }
    #fdOverview { scroll-margin-top: 95px; }
    .fd-passenger-flight { border: 0; padding: 0; background: transparent; color: #4f46e5; font: inherit; font-weight: 600; cursor: pointer; }
    .fd-passenger-flight:hover { text-decoration: underline; }
    #fdAllPassengersTable { min-width: 850px; }
    @media (prefers-reduced-motion: reduce) {
        .fd-summary-strip .fd-stat { transition: none; }
        .fd-summary-strip .fd-stat:hover { transform: none; }
    }
    .fd-banner .fd-summary-strip #fdTableCount { background: #ffffff20; color: #fff; }
    .fd-summary-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    .fd-summary-actions .fd-tool { width: 26px; height: 26px; color: #fff; border-color: #ffffff40; background: #ffffff18; }
    .fd-summary-actions .fd-tool:hover, .fd-summary-actions .fd-tool.is-alt { color: #fff; background: #ffffff30; border-color: #ffffff70; }
    /* Cards: Airline-wise (one card per airline) or Flight-wise (one card per flight) — owner, 2026-11-01 */
    .fd-groupsw { display: inline-flex; padding: 2px; gap: 2px; border-radius: 8px; border: 1px solid #ffffff40; background: #ffffff18; }
    .fd-groupsw button { border: 0; background: transparent; color: #ffffffcc; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; cursor: pointer; white-space: nowrap; line-height: 1.4; }
    .fd-groupsw button:hover { color: #fff; background: #ffffff20; }
    .fd-groupsw button.is-on { background: #fff; color: #4f46e5; }
    .fd-summary-actions .fd-tool:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    .fd-summary-actions .fd-add-flight {
        display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 7px;
        background: #fff; color: #4338ca; font-size: 11px; font-weight: 700; white-space: nowrap; text-decoration: none;
        transition: background .15s ease, transform .15s ease;
    }
    .fd-summary-actions .fd-add-flight:hover { background: #eef2ff; transform: translateY(-1px); }
    .fd-summary-actions .fd-add-flight:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    @media (max-width: 640px) {
        .fd-summary-strip { width: calc(100% + 24px); flex-basis: calc(100% + 24px); margin-inline: -12px; padding-inline: 12px; }
    }
    /* Quiet dashboard header with a dedicated filter surface. */
    .fd-banner {
        padding: 10px 16px; gap: 4px; color: #1e293b;
        background: linear-gradient(115deg, #ffffff 55%, #f5f3ff);
        border: 1px solid #e2e8f0; border-radius: 16px;
        box-shadow: 0 4px 20px #0f172a05, 0 1px 3px #0f172a04;
    }
    .fd-banner::before { display: none; }
    .fd-banner h2 { font-size: 20px; font-weight: 750; letter-spacing: -.025em; }
    .fd-banner p { margin-top: 5px; color: #64748b; opacity: 1; font-size: 12px; }
    .fd-head .fd-icon {
        width: 44px; height: 44px; color: #4f46e5; background: #eef2ff;
        border-color: #e0e7ff; border-radius: 13px; box-shadow: none;
    }
    .fd-stats { background: transparent; border: 0; box-shadow: none; padding: 0; gap: 20px; }
    .fd-banner .fd-stat { display: flex; align-items: baseline; gap: 7px; }
    .fd-stat + .fd-stat { border-color: #e2e8f0; padding-left: 20px; }
    .fd-stat .val { font-size: 16px; color: #0f172a; letter-spacing: -.025em; }
    .fd-stat .lbl { margin-top: 0; color: #64748b; opacity: 1; font-size: 10px; letter-spacing: .045em; }
    .fd-master-filter {
        display: grid; grid-template-columns: minmax(220px, 1fr) 150px auto 118px 118px auto;
        align-items: end; flex: 1 0 100%; width: 100%; gap: 10px;
        padding: 10px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
    }
    .fd-master-filter > .fdl-print { grid-column: 6; grid-row: 1; height: 28px; }
    .fd-master-filter > .fd-filter-field:nth-child(2) { grid-column: 2; }
    .fd-master-filter > .fd-filter-field:nth-child(3) { grid-column: 4; }
    .fd-master-filter > .fd-filter-field:nth-child(4) { grid-column: 5; }
    .fd-filter-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .fd-master-filter label { color: #475569; font-size: 11px; font-weight: 600; margin: 0; }
    .fd-master-filter input, .fd-master-filter select {
        width: 100%; height: 28px; min-width: 0; border: 1px solid #dce2eb;
        border-radius: 9px; background: #fff; color: #334155; font-size: 12px;
        padding: 0 7px; box-shadow: 0 1px 2px #0f172a03;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .fd-master-filter input[type="search"] { flex: none; padding-left: 36px; }
    .fd-filter-search { position: relative; }
    .fd-filter-search > i { position: absolute; left: 13px; top: 11px; color: #94a3b8; font-size: 12px; pointer-events: none; }
    .fd-master-filter input::placeholder { color: #8793a5; }
    .fd-master-filter input:hover, .fd-master-filter select:hover { border-color: #a5b4fc; }
    .fd-master-filter input:focus, .fd-master-filter select:focus {
        outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px #6366f11a;
    }
    .fd-master-filter button {
        height: 34px; padding: 0 14px; border: 1px solid #dce2eb; border-radius: 9px;
        background: #f8fafc; color: #475569; font-size: 12px; font-weight: 600; white-space: nowrap;
        transition: background .18s ease, border-color .18s ease;
    }
    .fd-master-filter button i { margin-right: 6px; }
    .fd-master-filter button:hover { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
    .fd-master-filter button:focus-visible { outline: 2px solid #6366f1; outline-offset: 3px; }
    .fd-date-presets {
        grid-column: 3; grid-row: 1; display: flex; align-items: center; gap: 5px; flex-wrap: wrap;
        padding-top: 2px;
    }
    .fd-date-presets button {
        height: 28px; padding: 0 8px; border-radius: 7px; font-size: 10px;
        background: #fff; color: #64748b;
    }
    .fd-date-presets button:hover, .fd-date-presets button.is-active {
        background: #eef2ff; border-color: #c7d2fe; color: #4338ca;
    }
    .fd-filter-footer { grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .fd-filter-hint { color: #64748b; font-size: 11px; }
    .fd-filter-hint i { margin-right: 6px; color: #818cf8; }
    .fd-banner #fdTableCount { color: #4338ca; background: #eef2ff; border-radius: 6px; padding: 4px 8px; font-size: 11px; font-weight: 600; white-space: nowrap; }
    @media (max-width: 1100px) {
        .fd-master-filter { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .fd-filter-field:first-child { grid-column: span 2; }
        .fd-master-filter > .fd-filter-field:nth-child(2),
        .fd-master-filter > .fd-filter-field:nth-child(3),
        .fd-master-filter > .fd-filter-field:nth-child(4),
        .fd-date-presets { grid-column: auto; grid-row: auto; }

    }
    @media (max-width: 1200px) { .fd-master-filter > .fdl-print { grid-column: auto; grid-row: auto; } }
    @media (max-width: 640px) {
        .fd-banner { padding: 10px 12px; gap: 8px; }
        .fd-banner h2 { font-size: 18px; }
        .fd-stats { width: 100%; justify-content: space-between; gap: 8px; }
        .fd-stat + .fd-stat { padding-left: 10px; }
        .fd-banner .fd-stat { flex-direction: column; gap: 2px; }
        .fd-stat .val { font-size: 16px; }
        .fd-stat .lbl { font-size: 8px; }
        .fd-master-filter { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .fd-filter-field:first-child { grid-column: 1 / -1; }
        .fd-date-presets { grid-column: 1 / -1; }
        .fd-master-filter button { grid-column: 1 / -1; }
        .fd-filter-footer { align-items: flex-start; flex-wrap: wrap; }
    }
    @media (prefers-reduced-motion: reduce) {
        .fd-master-filter input, .fd-master-filter select, .fd-master-filter button { transition: none; }
    }
    .fd-table td { line-height: 1.3; vertical-align: middle; }
    .fd-table small { max-width: 220px; line-height: 1.3; }
    .fd-table .fd-detail { color: #6366f1; }
    .fd-table .fd-due { color: #dc2626; }
    .fd-table .fd-clear { color: #059669; }
    .fd-table small a { font-weight: inherit; }
    .fd-table a:hover, .fd-table a:focus-visible { text-decoration: underline; }
    .fd-card[aria-pressed="true"] { outline: 2px solid #6366f1; outline-offset: 2px; }
    .fd-table-status { font-size: .6rem; padding: 1px 5px; margin-left: 4px; }
    /* Departed Flights table (owner, 2026-10-01): the same table as above, its
       own title bar. Badges say why a batch left the wall. */
    .fd-table-status.closed { background: #ecfdf5; color: #047857; }
    .fd-table-status.departed { background: #f1f5f9; color: #475569; }
    .fd-table-status.overdue { background: #fffbeb; color: #b45309; }
    .fd-table-status.offloaded { background: #f1f5f9; color: #475569; }
    .fd-status-cell { white-space: normal; }
    .fd-status-cell .fd-table-status { margin-left: 0; text-align: center; line-height: 1.25; }
    .fd-departed-head {
        --fd-head-bg: #f8fafc; --fd-head-edge: #94a3b8; --fd-head-text: #334155;
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 10px 12px; background: linear-gradient(90deg, var(--fd-head-bg), #fff 68%);
        border-left: 3px solid var(--fd-head-edge); border-bottom: 1px solid #e2e8f0;
    }
    .fd-departed-head h3 { margin: 0; color: var(--fd-head-text); font-size: .9rem; font-weight: 750; }
    .fd-departed-head h3 i { color: var(--fd-head-edge); margin-right: 6px; }
    .fd-departed-head span { font-size: .7rem; color: color-mix(in srgb, var(--fd-head-text) 68%, #94a3b8); font-weight: 600; }
    .fd-section-head--active { --fd-head-bg: #eef2ff; --fd-head-edge: #818cf8; --fd-head-text: #3730a3; }
    .fd-section-head--overdue { --fd-head-bg: #fffbeb; --fd-head-edge: #fbbf24; --fd-head-text: #92400e; }
    .fd-section-head--offloaded { --fd-head-bg: #f8fafc; --fd-head-edge: #94a3b8; --fd-head-text: #334155; }
    .fd-section-head--departed { --fd-head-bg: #f0f9ff; --fd-head-edge: #38bdf8; --fd-head-text: #075985; }
    .fd-table-panel--active { --fd-section-th: #e9edff; --fd-section-row: #fafbff; --fd-section-alt: #f4f6ff; --fd-section-hover: #ecefff; --fd-section-edge: #818cf8; }
    .fd-table-panel--overdue { --fd-section-th: #fff4d6; --fd-section-row: #fffef9; --fd-section-alt: #fffaf0; --fd-section-hover: #fff3d6; --fd-section-edge: #f59e0b; }
    .fd-table-panel--offloaded { --fd-section-th: #edf1f5; --fd-section-row: #fcfdfe; --fd-section-alt: #f5f7fa; --fd-section-hover: #edf2f7; --fd-section-edge: #94a3b8; }
    .fd-table-panel--departed { --fd-section-th: #e5f5fd; --fd-section-row: #fafeff; --fd-section-alt: #f0f9ff; --fd-section-hover: #e6f6fd; --fd-section-edge: #38bdf8; }
    .fd-table-panel[class*="fd-table-panel--"] .fd-table th { background: var(--fd-section-th); border-bottom-color: color-mix(in srgb, var(--fd-section-edge) 38%, #dbe4ef); }
    .fd-table-panel[class*="fd-table-panel--"] .fd-table tbody tr { background: var(--fd-section-row); }
    .fd-table-panel[class*="fd-table-panel--"] .fd-table tbody tr:nth-child(even) { background: var(--fd-section-alt); }
    .fd-table-panel[class*="fd-table-panel--"] .fd-table tbody tr > td:first-child { box-shadow: inset 3px 0 0 color-mix(in srgb, var(--fd-section-edge) 72%, transparent); }
    .fd-table-panel[class*="fd-table-panel--"] .fd-table tbody tr:hover { background: var(--fd-section-hover); box-shadow: none; }
    .fd-table-panel[class*="fd-table-panel--"] .fd-table tbody tr:hover > td:first-child { box-shadow: inset 4px 0 0 var(--fd-section-edge); }
    .fd-table-panel[class*="fd-table-panel--"] .fd-table tbody td:nth-last-child(-n+3) { background: rgba(255, 255, 255, .56); }
    .fd-table-panel[class*="fd-table-panel--"] .fd-table tbody tr:hover td:nth-last-child(-n+3) { background: rgba(255, 255, 255, .78); }
    .fd-table-panel--active .fd-table-status { border: 1px solid #fed7aa; background: #fff7ed; color: #c2410c; }
    .fd-table-panel--active .fd-table-status.boarding { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
    .fd-table-panel--overdue .fd-table-status.overdue { border: 1px solid #fde68a; background: #fff7d6; color: #a16207; }
    .fd-table-panel--offloaded .fd-table-status.offloaded { border: 1px solid #fecdd3; background: #fff1f2; color: #be123c; }
    .fd-table-panel--departed .fd-table-status.departed { border: 1px solid #bae6fd; background: #f0f9ff; color: #0369a1; }
    .fd-table-panel--departed .fd-table-status.closed { border: 1px solid #bbf7d0; background: #f0fdf4; color: #047857; }
    .fd-overall-filter { position: relative; margin: 12px 0 14px; overflow: visible; border: 1px solid #c7d2fe; border-radius: 10px; background: #fff; box-shadow: 0 3px 12px rgba(79, 70, 229, .06); }
    .fd-overall-label { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 5px; color: #3730a3; white-space: nowrap; font-size: .7rem; font-weight: 700; }
    .fd-overall-filter-state { display: none; padding: 2px 7px; border-radius: 999px; background: #e0e7ff; color: #3730a3; font-size: .62rem; font-weight: 700; }
    .fd-overall-filter-state.is-active { display: inline-flex; }
    .fd-overall-filter .fdl-bar { flex-wrap: wrap; gap: 6px; padding: 8px 9px; border: 0; border-radius: 0; margin: 0; background: #f8faff; }
    .fd-overall-filter .fdl-search { width: auto; flex: 1 1 420px; min-width: 320px; }
    .fd-overall-filter .fdl-search input { height: 31px; font-size: .7rem; }
    .fd-overall-filter .fdl-seg button { height: 31px; padding: 0 7px; font-size: .66rem; }
    .fd-overall-filter .fdl-date { height: 31px; }
    .fd-overall-filter .fdl-dates input.fdl-date-text { width: 74px; height: 29px; font-size: .68rem; }
    .fd-overall-filter .fdl-go { height: 31px; padding: 0 9px; font-size: .68rem; }
    .fd-overall-agent { position: relative; z-index: 41; align-self: center; width: 116px !important; min-width: 116px; max-width: 116px; flex: 0 0 116px !important; height: 31px !important; min-height: 31px !important; max-height: 31px !important; overflow: visible; }
    #fdOverallAgent + .select2-container { z-index: 42 !important; display: block !important; width: 116px !important; min-width: 116px !important; max-width: 116px !important; height: 31px !important; }
    .fd-overall-agent > .select2-container .select2-selection--single { box-sizing: border-box !important; height: 31px !important; min-height: 31px !important; max-height: 31px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; background: #fff !important; }
    .fd-overall-agent > .select2-container .select2-selection__rendered { display: flex !important; align-items: center !important; box-sizing: border-box !important; height: 29px !important; padding: 0 25px 0 9px !important; color: #334155 !important; font-family: Arial, sans-serif !important; font-size: .68rem !important; font-weight: 600 !important; line-height: 1 !important; }
    .fd-overall-agent > .select2-container .select2-selection__arrow { top: 0 !important; right: 4px !important; width: 18px !important; height: 29px !important; }
    .fd-overall-agent > .select2-container .select2-selection__arrow b { margin-top: -3px !important; border-width: 4px 4px 0 !important; border-color: #64748b transparent transparent !important; }
    .fd-overall-agent > .select2-container--focus .select2-selection--single,
    .fd-overall-agent > .select2-container--open .select2-selection--single { border-color: #6366f1 !important; box-shadow: 0 0 0 2px rgba(99, 102, 241, .12); }
    .fd-overall-agent > .select2-container--open { z-index: 10020 !important; }
    .select2-dropdown.fd-overall-agent-menu { z-index: 10021 !important; width: 210px !important; min-width: 210px !important; margin-top: 0; border: 1px solid #cbd5e1; border-radius: 7px; box-shadow: 0 10px 24px rgba(15, 23, 42, .14); overflow: hidden; }
    .fd-overall-agent-menu .select2-results__option { padding: 7px 10px !important; overflow: hidden; font-size: .7rem !important; line-height: 1.25 !important; text-overflow: ellipsis; white-space: nowrap; }
    .fd-overall-reset { height: 31px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #475569; font-size: .68rem; font-weight: 600; cursor: pointer; }
    .fd-overall-reset:hover { border-color: #818cf8; color: #4338ca; }
    @media (max-width: 1280px) {
        .fd-overall-filter .fdl-search { flex-basis: 320px; }
    }
    @media (max-width: 640px) {
        .fd-overall-filter .fdl-search { min-width: 0; flex-basis: 100%; }
        .fd-overall-filter .fdl-dates { flex: 1 1 100%; flex-wrap: wrap; }
    }
    .fd-table-panel { scroll-margin-top: 95px; }
    .fd-table .fd-agents-more { display: block; margin-top: 2px; padding: 0; border: 0; background: none; color: #4f46e5;
        font-size: .7rem; font-weight: 600; cursor: pointer; }
    .fd-table .fd-agents-more:hover, .fd-table .fd-agents-more:focus-visible { text-decoration: underline; }
    .fd-table .fd-agent-line { font-weight: 400; color: #334155; line-height: 1.18; }
    .fd-table .fd-agent-line i { color: #6366f1; font-size: .65rem; }
    .fd-table .fd-agent-line a { font-size: .7rem; font-weight: 500; color: #6366f1; margin-left: 2px; }
</style>
{{-- The passenger ledger's own sheet — the Manage Banks account card and
     filter bar, fdl- prefixed. Kept beside the ledger's markup and script. --}}
@include('contract-flights.dashboard-ledger-styles')
{{-- The Passenger Ledger table wears .mfp-table (2026-10-03), so it takes the
     Flights & Passengers table's own sheet — every selector there is mfp-
     prefixed, nothing else on this page matches it. --}}
@include('contract-flights.partials.passenger-table-styles')
<style>
    /* The dashboard's #fdPassengerTable tweaks above out-rank .mfp-table by id;
       put Flights & Passengers' spacing back so the two tables are the same. */
    #fdPassengerTable.mfp-table th { padding: 11px 12px; }
    #fdPassengerTable.mfp-table td { padding: 8px 12px; }
    #fdPassengerTable.mfp-table td:last-child { padding-left: 6px; padding-right: 6px; }
</style>
@endsection

@section('main-content')
@php
    $role = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
    $taka = '৳';
@endphp

@include('layout.contract-flight-tabs')

{{-- Banner: what this page is on the left, the live figures on the right.
     Every figure is over the ACTIVE flights only — the same set the cards
     show — so the banner and the wall can never disagree. --}}
<div class="fd-banner">
    <div class="fd-summary-strip">
    <div class="fd-stats">
        <button type="button" class="fd-stat fd-stat-link" onclick="scrollToFlightTable()" aria-label="View active flights table">
            <span class="val">{{ $kpis['flights'] }}</span>
            <span class="lbl">Active Flights</span>
        </button>
        <button type="button" class="fd-stat fd-stat-link" onclick="openDashboardPassengers()" aria-label="View passenger table">
            <span class="val">{{ $kpis['passengers'] }}</span>
            <span class="lbl">Passengers</span>
        </button>
        <button type="button" class="fd-stat fd-stat-link" data-kpi-filter="airline" onclick="applyDashboardKpiFilter('airline')" aria-label="Show flights with airlines">
            <div class="val">{{ $kpis['airlines'] }}</div>
            <div class="lbl">Airlines</div>
        </button>
        <button type="button" class="fd-stat fd-stat-link" data-kpi-filter="vendor_due" onclick="applyDashboardKpiFilter('vendor_due')" aria-label="Show flights with vendor due">
            <div class="val">{{ $taka }}{{ number_format($kpis['vendor_due'], 0) }}</div>
            <div class="lbl">Vendor Due</div>
        </button>
        <button type="button" class="fd-stat fd-stat-link" data-kpi-filter="vendor" onclick="applyDashboardKpiFilter('vendor')" aria-label="Show flights assigned to vendors" title="Unique vendors assigned to active flights">
            <div class="val">{{ $kpis['vendors'] }}</div>
            <div class="lbl">Vendors</div>
        </button>
    </div>
    <div class="fd-summary-actions">
        <div class="fd-groupsw" id="fdGroupSw" role="group" aria-label="How the cards are grouped">
            <button type="button" data-group="airline" onclick="setFlightCardGroup('airline')" title="One card per airline — its flights together">Airline-wise</button>
            <button type="button" data-group="flight" onclick="setFlightCardGroup('flight')" title="One card per flight, with its CF number">Flight-wise</button>
        </div>
        <button type="button" id="fdHoverBtn" class="fd-tool" onclick="cycleFlightHoverMode()" title="Change card view" aria-label="Change card view">
            <i class="fas fa-expand" id="fdHoverIcon"></i>
        </button>
        <span id="fdTableCount" aria-live="polite"></span>
        @can('create contract flight')
            {{-- Opens the Add Flight modal right here (contract-flights.create-modal,
                 included below) — no detour through Flights & Passengers. --}}
            <button type="button" class="fd-add-flight create-btn">
                <i class="fas fa-plus"></i> Add Flight
            </button>
        @endcan
    </div>
    </div>

</div>

<section class="fd-overall-filter" aria-labelledby="fdOverallFilterTitle">
    <div class="fdl-bar" role="search" aria-label="Filter every flight dashboard section">
        <div class="fd-overall-label" id="fdOverallFilterTitle" title="Applies to cards and every flight table"><i class="fas fa-filter" aria-hidden="true"></i> Overall Filter <span id="fdOverallFilterState" class="fd-overall-filter-state">Active</span></div>
        <div class="fdl-search" id="fdOverallSearchWrap">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="fdOverallSearch" autocomplete="off" aria-label="Search every flight section"
                   placeholder="Search all flights, airlines, routes, agents or vendors…">
            <button type="button" class="fdl-clear" id="fdOverallSearchClear" title="Clear overall search"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="fd-overall-agent">
            <select id="fdOverallAgent" class="fd-overall-select" aria-label="Filter every section by agent">
                <option value="">All agents</option>
            </select>
        </div>
        <div class="fdl-seg" id="fdOverallDatePresets" aria-label="Overall quick departure date filters">
            <button type="button" data-overall-preset="today" onclick="applyOverallDatePreset('today')">Today</button>
            <button type="button" data-overall-preset="tomorrow" onclick="applyOverallDatePreset('tomorrow')">Tomorrow</button>
            <button type="button" data-overall-preset="yesterday" onclick="applyOverallDatePreset('yesterday')">Yesterday</button>
            <button type="button" data-overall-preset="week" onclick="applyOverallDatePreset('week')">This week</button>
            <button type="button" class="is-on" data-overall-preset="clear" onclick="applyOverallDatePreset('clear')">All dates</button>
        </div>
        <div class="fdl-dates" id="fdOverallDates">
            <span class="fdl-date">
                <input type="text" id="fdOverallFromText" class="fdl-date-text" placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Overall departure date from">
                <input type="date" id="fdOverallFrom" class="fdl-date-native" aria-label="Pick overall from date">
            </span>
            <span>to</span>
            <span class="fdl-date">
                <input type="text" id="fdOverallToText" class="fdl-date-text" placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Overall departure date to">
                <input type="date" id="fdOverallTo" class="fdl-date-native" aria-label="Pick overall to date">
            </span>
            <button type="button" class="fdl-go" onclick="applyOverallDates()">Apply</button>
            <button type="button" class="fd-overall-reset" onclick="resetOverallFlightFilter()"><i class="fas fa-rotate-left"></i> Reset</button>
        </div>
    </div>
</section>

<div id="fdGrid"></div>
<section id="fdOverview" class="fd-table-panel fd-table-panel--active" aria-labelledby="fdTableTitle">
    <div class="fd-departed-head fd-section-head--active"><h3><i class="fas fa-plane-departure" aria-hidden="true"></i>Active / Upcoming Flights</h3></div>
    <div class="fdl-bar" role="search" aria-label="Filter active and upcoming flights">
        <div class="fdl-search" id="fdActiveSearchWrap">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="fdTableSearch" autocomplete="off" aria-label="Search active flights"
                   placeholder="Search flight, airline, route, agent or vendor…">
            <button type="button" class="fdl-clear" id="fdActiveSearchClear" title="Clear search"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="fdl-seg" id="fdDatePresets" aria-label="Quick departure date filters">
            <button type="button" data-date-preset="today" onclick="applyDashboardDatePreset('today')">Today</button>
            <button type="button" data-date-preset="tomorrow" onclick="applyDashboardDatePreset('tomorrow')">Tomorrow</button>
            <button type="button" data-date-preset="yesterday" onclick="applyDashboardDatePreset('yesterday')">Yesterday</button>
            <button type="button" class="is-on" data-date-preset="clear" onclick="applyDashboardDatePreset('clear')">All dates</button>
        </div>
        <div class="fdl-dates" id="fdActiveDates">
            <span class="fdl-date">
                <input type="text" id="fdActiveFromText" class="fdl-date-text" value="{{ now()->format('d/m/Y') }}"
                       placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Departure date from">
                <input type="date" id="fdTableFrom" class="fdl-date-native" value="{{ now()->toDateString() }}" aria-label="Pick the from date">
            </span>
            <span>to</span>
            <span class="fdl-date">
                <input type="text" id="fdActiveToText" class="fdl-date-text" placeholder="dd/mm/yyyy"
                       inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Departure date to">
                <input type="date" id="fdTableTo" class="fdl-date-native" aria-label="Pick the to date">
            </span>
            <button type="button" class="fdl-go" onclick="applyActiveFlightDates()">Apply</button>
            <button type="button" class="fdl-print" onclick="openDashboardPrint('active')" title="Print all filtered active flights"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>
    <div class="fd-table-scroll">
        <table class="fd-table" id="fdFlightTable" aria-labelledby="fdTableTitle">
            <caption id="fdTableTitle" class="sr-only">Active Flight Details</caption>
            <colgroup><col style="width:8%"><col style="width:14%"><col style="width:17%"><col style="width:16%"><col style="width:9%"><col style="width:10%"><col style="width:8.5%"><col style="width:8.5%"><col style="width:9%"></colgroup>
            <thead>
                <tr>
                    <th scope="col" data-sort-column="departure" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightTableSort('departure')"><span>Departure</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" data-sort-column="airline" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightTableSort('airline')"><span>Flight / Airline</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" data-sort-column="route" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightTableSort('route')"><span>Route</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" data-sort-column="agent" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightTableSort('agent')"><span>Agent / Vendor</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" class="fd-passengers" data-sort-column="passengers" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightTableSort('passengers')"><span>Passengers</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" data-sort-column="status" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightTableSort('status')"><span>Status</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" class="fd-num" data-sort-column="revenue" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightTableSort('revenue')"><span>Agent Charge</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" class="fd-num" data-sort-column="cost" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightTableSort('cost')"><span>Travel Cost</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" class="fd-num" data-sort-column="vendor_due" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightTableSort('vendor_due')"><span>Vendor Due</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                </tr>
            </thead>
            <tbody id="fdTableBody"></tbody>
        </table>
    </div>
    @include('contract-flights.dashboard-pagination', ['paginationSection' => 'active', 'paginationTitle' => 'Active / Upcoming Flights'])
</section>
@include('contract-flights.dashboard-ledger')
{{-- Each section has independent filters and reuses the same flight rows. --}}
@foreach(['overdue' => 'Overdue Flights', 'offloaded' => 'Offloaded / Deported', 'departed' => 'Departed Flights'] as $section => $title)
    @include('contract-flights.dashboard-section')
@endforeach
@can('create contract flight')
@include('contract-flights.create-modal')
@endcan
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @include('contract-flights.partials.ticketing-dropdowns')
<script>
// ── Data ─────────────────────────────────────────────────────────────────
// One entry per active flight, prepared by ManageFlightController::dashboardCard().
const FD_FLIGHTS     = @json($cards);
// Each operational section uses the same flight row data.
const FD_HISTORY = @json($historyCards);
const FD_ALL_FLIGHTS = [...new Map(FD_FLIGHTS.concat(...Object.values(FD_HISTORY)).map(f => [f.id, f])).values()];
const FD_BUSINESS_TODAY = @json(now()->toDateString());
// Rows and eyes in every section open the same ledger / record.
function fdFindFlight(id) {
    return FD_ALL_FLIGHTS.find(f => String(f.id) === String(id));
}
const FD_FLIGHTS_URL = @json(route('role.contract-flights.index', ['role' => $role]));
const FD_PARTY_URL = @json(route('role.party-statement.index', ['role' => $role]));
const FD_TICKETS_URL = @json(route('role.ticket-direct-sale.index', ['role' => $role]));
const FD_PASSPORT_URL = @json(route('role.passport-holder.index', ['role' => $role]));
const FD_PASSENGERS_URL = @json(route('role.contract-flight-passengers.index', ['role' => $role, 'contractFlight' => '__FLIGHT__']));
const FD_STAGE_URL = @json(route('role.contract-flight-passengers.stage', ['role' => $role, 'contractFlightPassenger' => '__PASSENGER__']));
const FD_CAN_EDIT_STAGE = @json(auth()->user()->can('edit contract flight'));
const FD_BOARD_URL   = @json(route('role.contract-flight-board.index', ['role' => $role]));
const FD_AGENTS_URL  = @json(route('role.party-statement.agents', ['role' => $role]));
const FD_VENDORS_URL = @json(route('role.party-statement.vendors', ['role' => $role]));
const FD_INVOICE_URL = @json(route('role.contract-flights.invoice', ['role' => $role, 'contractFlight' => '__FLIGHT__']));
const FD_LOGO_BASE   = @json(asset('airline-logos'));
const FD_FLAG_BASE   = @json(asset('flags'));

@include('contract-flights.partials.airline-brands-script')
@include('contract-flights.partials.ticket-logos-script')

// "Us Bangla  (BS)" → "Us Bangla". The code comes off (the mark says which
// airline), doubled spaces collapse, and on a long name the trailing
// "Airlines"/"Airways" goes too, because at 13.5px the head line holds about
// seventeen characters and "Biman Bangladesh Airlines" is twenty-five. The
// full registered name stays in the card's tooltip.
function tidyAirline(name) {
    let n = String(name || '').replace(/\s*\([A-Z0-9]{2,3}\)\s*$/i, '').replace(/\s+/g, ' ').trim();
    if (n.length > 16) n = n.replace(/\s+(airlines?|airways)$/i, '').trim();
    return n || 'Airline pending';
}

function initials(name) {
    return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2)
        .map(w => w[0]).join('').toUpperCase() || '?';
}

// One stray double quote in an agent's name would close a title="" early and
// take the rest of the card's markup with it, so everything user-entered
// goes through here — as text and as attribute value alike.
function esc(v) {
    return String(v === null || v === undefined ? '' : v)
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ── Dates ────────────────────────────────────────────────────────────────
// Formatted by hand rather than toLocaleDateString: en-GB Chrome now prints
// "Sept", and the card's day line is measured to fit "18 Sep".
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const WDAYS  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

function parseIso(iso) {
    if (!iso) return null;
    const d = new Date(iso);
    return isNaN(d.getTime()) ? null : d;
}
function fmtDay(d)  { return d ? `${d.getDate()} ${MONTHS[d.getMonth()]}` : '—'; }
function fmtFull(d) { return d ? `${fmtDay(d)} ${d.getFullYear()}` : '—'; }
// The departure line every Contract Flight screen prints under a flight —
// the same dress as the Flights & Passengers list: "18 Sep 2026, 05:16 PM".
function fmtDeparture(d) {
    return d ? `${String(d.getDate()).padStart(2, '0')} ${MONTHS[d.getMonth()]} ${d.getFullYear()}, ${fmtTime(d)}` : 'Date pending';
}
function fmtTime(d) {
    if (!d) return '';
    let h = d.getHours(); const m = String(d.getMinutes()).padStart(2, '0');
    const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
    return `${String(h).padStart(2, '0')}:${m} ${ap}`;
}

// How far off departure is, on the DAY — the same reading the pipeline uses:
// a 5pm departure is "today" all day, not "in 3 hours" then "2 hours ago".
//   late   the day has passed and the batch is still open
//   today  departs today
//   soon   within a week
//   idle   further out, or no date set
function depState(d) {
    if (!d) return { cls: 'idle', txt: 'no date', days: null };
    const now = new Date();
    const t0  = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const d0  = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const days = Math.round((d0 - t0) / 86400000);
    if (days < 0)   return { cls: 'late',  txt: (-days) + 'd ago', days };
    if (days === 0) return { cls: 'today', txt: 'today',           days };
    if (days === 1) return { cls: 'soon',  txt: 'tomorrow',        days };
    if (days <= 7)  return { cls: 'soon',  txt: 'in ' + days + 'd', days };
    return               { cls: 'idle',  txt: 'in ' + days + 'd', days };
}

// Money for a strip cell: whole taka up to a lakh, then L / Cr the way the
// figure is read out in the office. The exact amount is in the tooltip.
function fmtMoney(v) { return Number(Math.abs(v) || 0).toLocaleString('en-BD', { maximumFractionDigits: 0 }); }
function fmtShort(v) {
    const n = Math.abs(Number(v) || 0);
    if (n >= 1e7) return (n / 1e7).toFixed(n >= 1e8 ? 0 : 1) + 'Cr';
    if (n >= 1e5) return (n / 1e5).toFixed(n >= 1e6 ? 0 : 1) + 'L';
    return Math.round(n).toLocaleString('en-US');
}

// ── Mark fitting ─────────────────────────────────────────────────────────
// Zoom every airline mark to its ink. See the .fdc-logo comment in the
// sheet above for the why; this is the how.
//
//   inkBox()     reads the loaded image on a small canvas and returns the
//                bounding box of every pixel that is neither transparent
//                nor near-white, in the image's own pixels.
//   cropOf()     pads that box by MARK_EDGE — the 2–3px of edge kept.
//   markBasis()  the width the box asks for: 58px tall, capped at 104.
//   layoutMark() scales the crop into whatever width the box actually got
//                and positions the image so only the crop shows.
//   fitMarks()   wires it up for every mark in a freshly rendered grid.
//
// Measured boxes are memoised for the page and cached in localStorage per
// image URL, keyed with the image's natural size so a replaced file with the
// same name is measured afresh. Every step fails soft: if the pixels cannot
// be read (a tainted canvas, an old browser) the mark stays contained in its
// box exactly as it renders before the fit.
const MARK_H     = 58;     // px, the box height — matches .fdc-logo
const MARK_MAX_W = 104;    // px, the widest a wordmark may go — matches .fdc-logo max-width
const MARK_EDGE  = 0.035;  // of the ink's longer side: 2px on a 58px roundel, ~3px on a 104px wordmark
const MARK_SCAN  = 240;    // px, longer side of the scan canvas — plenty for a bounding box
const INK_MEMO   = new Map();
const INK_KEY    = 'fdMarkInk:';

function inkFromCache(url) {
    if (INK_MEMO.has(url)) return INK_MEMO.get(url);
    try {
        const raw = localStorage.getItem(INK_KEY + url);
        const v = raw ? JSON.parse(raw) : null;
        if (v && v.w > 0 && v.h > 0 && v.nw > 0 && v.nh > 0) { INK_MEMO.set(url, v); return v; }
    } catch (e) {}
    return null;
}
function inkToCache(url, ink) {
    INK_MEMO.set(url, ink);
    try { localStorage.setItem(INK_KEY + url, JSON.stringify(ink)); } catch (e) {}
}

function inkBox(img) {
    const nw = img.naturalWidth, nh = img.naturalHeight;
    if (!nw || !nh) return null;
    const s  = Math.min(1, MARK_SCAN / Math.max(nw, nh));
    const cw = Math.max(1, Math.round(nw * s)), ch = Math.max(1, Math.round(nh * s));
    let data;
    try {
        const cv = document.createElement('canvas');
        cv.width = cw; cv.height = ch;
        const ctx = cv.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(img, 0, 0, cw, ch);
        data = ctx.getImageData(0, 0, cw, ch).data;
    } catch (e) { return null; }
    let minX = cw, minY = ch, maxX = -1, maxY = -1;
    for (let y = 0; y < ch; y++) {
        for (let x = 0; x < cw; x++) {
            const i = (y * cw + x) * 4;
            if (data[i + 3] < 40) continue;                                          // transparent
            if (data[i] > 238 && data[i + 1] > 238 && data[i + 2] > 238) continue;   // near white
            if (x < minX) minX = x; if (x > maxX) maxX = x;
            if (y < minY) minY = y; if (y > maxY) maxY = y;
        }
    }
    if (maxX < 0) return null;   // nothing but background — leave the mark as it is
    return { x: minX / s, y: minY / s, w: (maxX - minX + 1) / s, h: (maxY - minY + 1) / s, nw, nh };
}

function cropOf(ink) {
    const pad = MARK_EDGE * Math.max(ink.w, ink.h);
    return { x: ink.x - pad, y: ink.y - pad, w: ink.w + 2 * pad, h: ink.h + 2 * pad, nw: ink.nw, nh: ink.nh };
}
function markBasis(crop) {
    return Math.max(MARK_H, Math.min(MARK_MAX_W, Math.round(MARK_H * crop.w / crop.h)));
}

const BADGE_H = 20, BADGE_MAX_W = 40;   // px — matches .fdc-badge
function badgeWidth(crop) {
    return Math.max(BADGE_H, Math.min(BADGE_MAX_W, Math.round(BADGE_H * crop.w / crop.h)));
}

function layoutMark(logo) {
    // :scope > — a main mark carries its badges inside it; take its own crop.
    const crop = logo._crop, wrap = logo.querySelector(':scope > .fdc-crop'), img = wrap && wrap.querySelector('img.mark');
    if (!crop || !wrap || !img) return;
    // A badge (.fdc-badge) is smaller than a full mark; the crop fills
    // whatever box it has.
    const H = logo.clientHeight || MARK_H;
    const W = logo.clientWidth || H;
    const s = Math.min(W / crop.w, H / crop.h);
    wrap.style.width  = (crop.w  * s) + 'px';
    wrap.style.height = (crop.h  * s) + 'px';
    img.style.width   = (crop.nw * s) + 'px';
    img.style.height  = (crop.nh * s) + 'px';
    img.style.left    = (-crop.x * s) + 'px';
    img.style.top     = (-crop.y * s) + 'px';
}

const MARK_RO = (typeof ResizeObserver === 'function')
    ? new ResizeObserver(entries => entries.forEach(e => layoutMark(e.target)))
    : null;

function applyInk(logo, ink) {
    if (!logo || logo.classList.contains('is-mono')) return;
    logo._crop = cropOf(ink);
    if (logo.classList.contains('fdc-badge')) {
        // A later leg's small mark (.fdc-badge): a 20px box as wide as its ink.
        logo.style.width = badgeWidth(logo._crop) + 'px';
    } else {
        const H = logo.clientHeight || MARK_H;
        logo.style.flexBasis = Math.round(markBasis(logo._crop) * H / MARK_H) + 'px';
    }
    logo.classList.add('is-fit');
    layoutMark(logo);
    if (MARK_RO) MARK_RO.observe(logo);
}

function fitMarks(root) {
    root.querySelectorAll('.fdc-logo:not(.is-mono) img.mark').forEach(img => {
        const logo = img.closest('.fdc-logo');
        const url  = img.getAttribute('src');
        const known = inkFromCache(url);
        if (known) applyInk(logo, known);
        const scan = () => {
            if (!img.naturalWidth) return;
            const c = INK_MEMO.get(url);
            if (c && c.nw === img.naturalWidth && c.nh === img.naturalHeight) return;   // cache still describes this file
            const ink = inkBox(img);
            if (!ink) return;
            inkToCache(url, ink);
            root.querySelectorAll('img.mark').forEach(i => {
                if (i.getAttribute('src') === url) applyInk(i.closest('.fdc-logo'), ink);
            });
        };
        if (img.complete) scan(); else img.addEventListener('load', scan, { once: true });
    });
}
if (!MARK_RO) window.addEventListener('resize', () => document.querySelectorAll('.fdc-logo.is-fit').forEach(layoutMark));

// ── The card ─────────────────────────────────────────────────────────────
// One flight, in four bands (see .fd-card in the sheet above):
//
//   [ mark ]  US Bangla
//             CF-017 · Open
//             👤 ECN RABBI
//   🇧🇩 DAC ········ ✈ ········ BKK 🇹🇭   BUSINESS
//   18 Sep 05:16 PM                 [ in 2d ]
//   ┌────────┬──────────┬───────────┐
//   │   4    │   4/4    │   ৳0      │
//   │  PAX   │ TICKETED │    DUE    │
//   └────────┴──────────┴───────────┘
const STATUS_INK = {
    open:     { hex: '#f59e0b', rgb: '245, 158, 11',  label: 'Open' },
    boarding: { hex: '#2563eb', rgb: '37, 99, 235',   label: 'Boarding' },
    mixed:    { hex: '#7c3aed', rgb: '124, 58, 237',   label: 'Mixed' },
};

function flagImg(ap, cls) {
    if (!ap || !ap.country) return '';
    return `<img class="${cls}" src="${FD_FLAG_BASE}/${esc(ap.country)}.png" alt="${esc(ap.country_name || '')}" title="${esc(ap.country_name || '')}" loading="lazy" onerror="this.style.display='none'">`;
}
// An airport's name without its "(DAC)" suffix — for lines that already show
// the code.
function airportName(ap, fallback) {
    return (ap?.name || fallback).replace(/\s*\([A-Z0-9]{3,4}\)\s*$/, '');
}

// One mark. `badges` are the later legs' airlines on a via route: each is a
// small .fdc-badge nested in this mark's corner (see .fdc-badge). If this
// browser has measured a mark before, its box takes its final size now.
function markHtml(name, badges = [], title = '') {
    const brand   = airlineBrand(name);
    const airline = tidyAirline(name);
    const markUrl = brand.slug ? `${FD_LOGO_BASE}/${brand.slug}.png` : '';
    const known   = markUrl ? inkFromCache(markUrl) : null;
    const size    = known && !badges.length ? ` style="flex-basis:${markBasis(cropOf(known))}px"` : '';
    const tip     = title ? ` title="${esc(title)}"` : '';
    const inner   = badges.map((b, i) => badgeHtml(b, i)).join('');
    const cls     = badges.length ? ' has-badge' : '';
    // The plane that hops from this mark to the badge on hover (.fdc-hop).
    const hop     = badges.length ? '<i class="fas fa-plane fdc-hop" aria-hidden="true"></i>' : '';
    return brand.slug
        ? `<div class="fdc-logo${cls}"${size}${tip}><span class="fdc-mono">${esc(initials(airline))}</span>` +
          `<span class="fdc-crop"><img class="mark" src="${markUrl}" alt="${esc(airline)}" loading="lazy"` +
          ` onerror="this.closest('.fdc-logo').classList.add('is-mono')"></span>${hop}${inner}</div>`
        : `<div class="fdc-logo is-mono${cls}"${tip}><span class="fdc-mono">${esc(initials(airline))}</span>${hop}${inner}</div>`;
}
function badgeHtml(name, i) {
    const brand   = airlineBrand(name);
    const airline = tidyAirline(name);
    const markUrl = brand.slug ? `${FD_LOGO_BASE}/${brand.slug}.png` : '';
    const known   = markUrl ? inkFromCache(markUrl) : null;
    const style   = ` style="--fdc-tint:${brand.color || '#64748b'}` +
                    (known ? `;width:${badgeWidth(cropOf(known))}px` : '') + '"';
    const tip     = ` title="${esc(`Leg ${i + 2} · ${name}`)}"`;
    return brand.slug
        ? `<span class="fdc-logo fdc-badge"${style}${tip}><span class="fdc-mono">${esc(initials(airline))}</span>` +
          `<span class="fdc-crop"><img class="mark" src="${markUrl}" alt="${esc(airline)}" loading="lazy"` +
          ` onerror="this.closest('.fdc-logo').classList.add('is-mono')"></span></span>`
        : `<span class="fdc-logo fdc-badge is-mono"${style}${tip}><span class="fdc-mono">${esc(initials(airline))}</span></span>`;
}

function flightCardHtml(f) {
    // A via route is two tickets, each on its own airline: the card then
    // carries leg 1's mark with the later legs as badges, and both names (owner,
    // 2026-09-21). `airlines` is distinct and in leg order; a direct route
    // has one entry, the route's own airline.
    const names   = (Array.isArray(f.airlines) && f.airlines.length ? f.airlines : [f.airline]).slice(0, 3);
    const multi   = names.length > 1;
    const brand   = airlineBrand(names[0]);
    // Two names share one line, so each drops its "Airlines"/"Airways" —
    // "Turkish · Emirates" fits where "Turkish Airlines · Emirates" does not.
    const airline = multi
        ? names.map(n => tidyAirline(n).replace(/\s+(airlines?|airways)$/i, '').trim()).join(' · ')
        : tidyAirline(f.airline);
    const airlineFull = multi ? names.join(' · ') : (f.airline || '');
    const ink     = STATUS_INK[f.status] || { hex: '#64748b', rgb: '100, 116, 139', label: f.status || '—' };
    const dep     = parseIso(f.departure_at);
    const arr     = parseIso(f.arrival_at);
    const state   = depState(dep);
    const late    = state.cls === 'late';

    // The mark. Same string in both states so it does not move as the card
    // opens. The destination flag once rode on its corner and the owner had
    // it removed (2026-09-16); the route line carries the flags. Only a via
    // route's later airline wears a corner badge (2026-10-01).
    // The image sits in a crop window that fitMarks() sizes once the mark's
    // ink has been measured. If this browser has measured it before, the box
    // takes its final width now, so the card does not re-flow on load.
    const plate = multi
        ? markHtml(names[0], names.slice(1), names.map((n, i) => `Leg ${i + 1}: ${n}`).join(' · '))
        : markHtml(f.airline);

    // Readiness — anything on this batch that still needs a hand.
    const pax        = Number(f.passengers) || 0;
    const ticketed   = Number(f.ticketed) || 0;
    const unticketed = pax - ticketed;
    const issues = [];
    if (unticketed > 0)              issues.push(`${unticketed} not ticketed`);
    if (f.flagged > 0)               issues.push(`${f.flagged} flagged`);
    if (f.needs_immigration_officer) issues.push('immigration officer not assigned');
    const dot = issues.length ? `<span class="fdc-dot" title="${esc(issues.join(' · '))}"></span>` : '';

    const fromCode = f.from ? f.from.code : '—';
    const toCode   = f.to   ? f.to.code   : '—';
    const routeTitle = esc([
        f.from ? f.from.name : 'origin not set',
        f.to   ? f.to.name   : 'destination not set',
        f.airline_flight_no ? 'airline flight ' + f.airline_flight_no : '',
    ].filter(Boolean).join(' → '));

    const depTitle = esc([
        dep ? `departs ${WDAYS[dep.getDay()]} ${fmtFull(dep)} ${fmtTime(dep)}` : 'departure not set',
        arr ? `arrives ${fmtFull(arr)} ${fmtTime(arr)}` : '',
    ].filter(Boolean).join(' · '));

    const due = Number(f.vendor_due) || 0;
    const cardTitle = esc([
        `${f.flight_number} · ${airlineFull || 'airline pending'} · ${fromCode} → ${toCode}`,
        ink.label,
        f.agent ? 'agent ' + f.agent : '',
        f.category ? f.category : '',
        `revenue ৳${fmtMoney(f.revenue)} · cost ৳${fmtMoney(f.cost)}`,
        f.vendor ? 'vendor ' + f.vendor : '',
        'open on Flights & Passengers',
    ].filter(Boolean).join(' · '));

    const brief = `<div class="fdc-brief"><div class="fdc-brief-in"><div class="fdc-brief-row">
            ${plate}
            <div class="fb-id">
                <div class="fb-co" title="${esc(airlineFull)}">${esc(airline)}</div>
                <div class="fb-name" title="${routeTitle}">${f.airline_group_key && f.route_count > 1 ? `<i class="fas fa-route"></i><span>${esc(f.route_count)} routes</span>` : `${flagImg(f.from, 'fdc-flag')}<span>${esc(fromCode)}</span><span class="arr">→</span><span>${esc(toCode)}</span>${flagImg(f.to, 'fdc-flag')}`}</div>
                <div class="fb-bal">${f.airline_group_key ? '<span class="next-label">Next </span>' : ''}${fmtDay(dep)}${dep ? `<span class="cur">${fmtTime(dep)}</span>` : ''}</div>
            </div>
            ${f.flight_number ? `<span class="fb-cf" title="${esc(f.flight_number)}">${esc(f.flight_number)}</span>` : ''}
        </div></div></div>`;

    const cls = f.ticket_class ? `<span class="cls">${esc(f.ticket_class)}</span>` : '';

    const cardTarget = f.airline_group_key
        ? `data-airline-key="${esc(f.airline_group_key)}"`
        : `data-flight-id="${esc(f.id)}"`;
    const cardLabel = f.airline_group_key
        ? `Open ${f.airline || 'airline'} passenger ledger`
        : `Open passenger ledger for ${f.flight_number}`;
    const cardPressed = f.airline_group_key
        ? FD_LEDGER_ID === `airline:${f.airline_group_key}`
        : String(FD_LEDGER_ID) === String(f.id);
    return `<div class="fd-card${late ? ' is-late' : ''}" ${cardTarget} tabindex="0" role="button" aria-pressed="${cardPressed}" aria-label="${esc(cardLabel)}"
        style="--fdc-tint:${brand.color};--fdc-co:${ink.hex};--fdc-co-rgb:${ink.rgb};" title="${cardTitle}">
        ${brief}
        <div class="fdc-full"><div class="fdc-full-in">
        <div class="fdc-head">
            ${plate}
            <div class="fdc-id">
                <div class="name" title="${esc(airlineFull)}">${dot}${esc(airline)}</div>
                <div class="sub" title="${esc(f.flight_number + ' · ' + ink.label + (f.category ? ' · ' + f.category : ''))}"><span class="cf">${esc(f.flight_number)}</span> · ${esc(f.airline_group_key ? 'Upcoming' : ink.label)}</div>
                ${f.agent
                    ? `<div class="agent" title="${esc('agent: ' + f.agent)}"><i class="fas fa-user-tie"></i>${esc(f.agent)}</div>`
                    : `<div class="agent is-none" title="no agent on this batch or its passengers"><i class="fas fa-user-tie"></i>Direct — no agent</div>`}
            </div>
        </div>
        <div class="fdc-route" title="${f.route_labels?.length ? esc(f.route_labels.join(' · ')) : routeTitle}">
            ${f.airline_group_key && f.route_count > 1
                ? `<span class="group-route"><i class="fas fa-route"></i>${esc(f.route_count)} Routes</span><span class="group-meta">${f.agents.length ? `${esc(f.agents.length)} Agent${f.agents.length === 1 ? '' : 's'}` : 'Direct'}</span>`
                : `<span class="ap">${flagImg(f.from, 'fdc-flag')}${esc(fromCode)}</span><span class="lead"></span><i class="fas fa-plane pl"></i><span class="lead"></span><span class="ap">${esc(toCode)}${flagImg(f.to, 'fdc-flag')}</span>${cls}`}
        </div>
        <div class="fdc-dep" title="${depTitle}">
            <div class="money">${f.airline_group_key ? '<span class="next-label">Next</span>' : ''}<span class="amt">${fmtDay(dep)}</span>${dep ? `<span class="cur">${fmtTime(dep)}</span>` : ''}</div>
            <div class="fdc-flow ${state.cls}">${esc(f.airline_group_key && f.latest_departure_at ? `Latest ${fmtDay(parseIso(f.latest_departure_at))}` : state.txt)}</div>
        </div>
        <div class="fdc-strip">
            <div class="cell${pax ? '' : ' is-zero'}" title="${pax} passenger${pax === 1 ? '' : 's'} on this batch${f.total_seats ? ' · ' + f.total_seats + ' seats' : ''}">
                <div class="cv">${pax}</div><div class="cl">Pax</div>
            </div>
            <div class="cell ${pax ? (unticketed ? 'warn' : 'ok') : 'is-zero'}" title="${pax ? `${ticketed} of ${pax} ticketed` : 'no passengers yet'}">
                <div class="cv">${pax ? `${ticketed}/${pax}` : '—'}</div><div class="cl">Ticketed</div>
            </div>
            <div class="cell ${due > 0 ? 'bad' : 'is-zero'}" title="${due > 0 ? `vendor due ৳${fmtMoney(due)}${f.payable_date ? ' · payable by ' + f.payable_date : ''}` : 'nothing owed to the vendor'}">
                <div class="cv">৳${fmtShort(due)}</div><div class="cl">Due</div>
            </div>
        </div>
        </div></div>
    </div>`;
}

function airlineGroupKey(flight) {
    return String(flight.airline || 'Airline pending').trim().toLocaleLowerCase();
}

function groupDashboardFlightsByAirline(flights) {
    const grouped = new Map();
    flights.forEach(flight => {
        const key = airlineGroupKey(flight);
        if (!grouped.has(key)) grouped.set(key, {key, name: flight.airline || 'Airline pending', flights: []});
        grouped.get(key).flights.push(flight);
    });
    return [...grouped.values()].sort((a, b) => fdNearestKey(a.flights[0]) - fdNearestKey(b.flights[0]));
}

function airlineSummary(group) {
    const flights = sortFlights(group.flights);
    const next = flights[0];
    const sum = key => flights.reduce((total, flight) => total + Number(flight[key] || 0), 0);
    const agents = [...new Map(flights.flatMap(flight => flight.agents || []).map(agent => [String(agent.id), agent])).values()];
    const vendorMap = new Map();
    flights.forEach(flight => {
        if (flight.vendor) vendorMap.set(String(flight.vendor_id || flight.vendor).toLowerCase(), {id: flight.vendor_id, name: flight.vendor});
        (flight.ticket_vendors || []).forEach(vendor => vendorMap.set(String(vendor.id || vendor.name).toLowerCase(), vendor));
    });
    const vendors = [...vendorMap.values()];
    const routeMap = new Map();
    flights.forEach(flight => {
        const label = flight.route_path || [flight.from?.code, flight.to?.code].filter(Boolean).join(' → ') || 'Route pending';
        routeMap.set(label.toLowerCase(), label);
    });
    const routeLabels = [...routeMap.values()];
    const statusCounts = new Map();
    flights.forEach(flight => {
        const rawStatus = flight.status || 'unknown';
        const label = STATUS_INK[rawStatus]?.label || String(rawStatus).replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
        statusCounts.set(label, (statusCounts.get(label) || 0) + 1);
    });
    const statusEntries = [...statusCounts.entries()];
    const flightCount = flights.length;
    const dated = flights.filter(flight => parseIso(flight.departure_at));
    const latest = dated.reduce((selected, flight) => !selected || parseIso(flight.departure_at) > parseIso(selected.departure_at) ? flight : selected, null);
    return {
        ...next,
        airline: group.name,
        airlines: [group.name],
        airline_group_key: group.key,
        flight_number: `${flightCount} flight${flightCount === 1 ? '' : 's'}`,
        passengers: sum('passengers'),
        ticketed: sum('ticketed'),
        flagged: sum('flagged'),
        total_seats: sum('total_seats'),
        revenue: sum('revenue'),
        cost: sum('cost'),
        vendor_due: sum('vendor_due'),
        agent: agents.length === 1 ? agents[0].name : (agents.length ? `${agents.length} agents` : null),
        agents,
        vendor: vendors.length === 1 ? vendors[0].name : (vendors.length ? `${vendors.length} vendors` : null),
        vendor_id: vendors.length === 1 ? vendors[0].id : null,
        ticket_vendors: vendors,
        vendor_count: vendors.length,
        route_count: routeLabels.length,
        route_labels: routeLabels,
        status: statusEntries.length === 1 ? next.status : 'mixed',
        status_summary: statusEntries.length === 1 ? statusEntries[0][0] : 'Mixed status',
        status_breakdown: statusEntries.map(([label, count]) => `${count} ${label}`).join(' · '),
        category: `${flightCount} upcoming flight${flightCount === 1 ? '' : 's'}`,
        latest_departure_at: latest?.departure_at || next.departure_at,
        needs_immigration_officer: flights.some(flight => flight.needs_immigration_officer),
    };
}

function airlineCardHtml(group) {
    return flightCardHtml(airlineSummary(group));
}

// ── Order ────────────────────────────────────────────────────────────────
//   departure (default)  nearest upcoming departure first, undated last.
//   airline              A-Z, then nearest departure first.
const FD_SORTS = {
    departure: ['departure', 'airline', 'number'],
    airline:   ['airline', 'departure', 'number'],
};
const FD_CMP = {
    departure: (a, b) => {
        const av = fdNearestKey(a), bv = fdNearestKey(b);
        if (!Number.isFinite(av) || !Number.isFinite(bv)) return Number(!Number.isFinite(av)) - Number(!Number.isFinite(bv));
        return av - bv;
    },
    // A batch with no ticket yet has no airline; it sorts after every named one.
    airline: (a, b) => (a.airline ? tidyAirline(a.airline) : "\uffff").localeCompare(b.airline ? tidyAirline(b.airline) : "\uffff"),
    number:  (a, b) => String(a.flight_number || '').localeCompare(String(b.flight_number || '')),
};
let FD_SORT = 'departure';
// Parse departure once per comparison; the comparator puts undated flights last.
function fdNearestKey(f) {
    const t = Date.parse(f.departure_at || '');
    return Number.isNaN(t) ? Infinity : t;
}

function sortFlights(list) {
    const chain = (FD_SORTS[FD_SORT] || FD_SORTS.departure).map(k => FD_CMP[k]);
    return [...list].sort((a, b) => { for (const c of chain) { const r = c(a, b); if (r) return r; } return 0; });
}

function paintSortBtn() {
    const btn = document.getElementById('fdSortBtn');
    const ico = document.getElementById('fdSortIcon');
    if (!btn || !ico) return;
    const byAirline = FD_SORT === 'airline';
    ico.className = byAirline ? 'fas fa-plane' : 'fas fa-calendar-days';
    btn.classList.toggle('is-alt', byAirline);
    const label = byAirline
        ? 'Sorted by airline — click to sort by departure'
        : 'Sorted by departure — click to sort by airline';
    btn.title = label;
    btn.setAttribute('aria-label', label);
}

function toggleFlightSort() {
    FD_SORT = FD_SORT === 'departure' ? 'airline' : 'departure';
    paintSortBtn();
    renderFlightCards();
}

// ── Cards grouped by airline or one per flight ───────────────────────────
// Airline-wise (default, 2026-10-29) or Flight-wise — the card wall as it was before (owner,
// 2026-11-01: "a button to decide how to see, airlines wise, or our previous like Flight Wise").
// A browser-local preference, like the card view beside it. Each card opens its own ledger.
let FD_CARD_GROUP = 'airline';
try {
    if (localStorage.getItem('flightCardGroupMode') === 'flight') FD_CARD_GROUP = 'flight';
} catch (e) {}

function paintGroupSw() {
    document.querySelectorAll('#fdGroupSw button').forEach(b => {
        const on = b.dataset.group === FD_CARD_GROUP;
        b.classList.toggle('is-on', on);
        b.setAttribute('aria-pressed', String(on));
    });
}

function setFlightCardGroup(mode) {
    FD_CARD_GROUP = mode === 'flight' ? 'flight' : 'airline';
    try { localStorage.setItem('flightCardGroupMode', FD_CARD_GROUP); } catch (e) {}
    renderFlightCards();
}

// ── View mode ────────────────────────────────────────────────────────────
// hover (expand under the pointer) · off (always compact) · all (every card
// open). A browser-local preference, remembered per machine.
const FD_HOVER_MODES = ['hover', 'off', 'all'];
let FD_HOVER_MODE = 'hover';
try {
    const saved = localStorage.getItem('flightCardHoverMode');
    if (FD_HOVER_MODES.includes(saved)) FD_HOVER_MODE = saved;
} catch (e) {}

const FD_HOVER_ICONS = { hover: 'fas fa-expand', off: 'fas fa-compress', all: 'fas fa-table-cells-large' };
const FD_HOVER_LABELS = {
    hover: 'Cards expand on hover — click to keep them always compact',
    off:   'Cards always compact — click to expand every card at once',
    all:   'Every card expanded — click to go back to hover-to-expand',
};

function paintHoverBtn() {
    const btn  = document.getElementById('fdHoverBtn');
    const ico  = document.getElementById('fdHoverIcon');
    const grid = document.getElementById('fdGrid');
    if (!btn || !ico) return;
    ico.className = FD_HOVER_ICONS[FD_HOVER_MODE] || FD_HOVER_ICONS.hover;
    btn.classList.toggle('is-alt', FD_HOVER_MODE !== 'hover');
    const label = FD_HOVER_LABELS[FD_HOVER_MODE] || FD_HOVER_LABELS.hover;
    btn.title = label;
    btn.setAttribute('aria-label', label);
    if (grid) {
        grid.classList.toggle('no-hover-fx', FD_HOVER_MODE === 'off');
        grid.classList.toggle('all-expanded', FD_HOVER_MODE === 'all');
    }
}

function cycleFlightHoverMode() {
    FD_HOVER_MODE = FD_HOVER_MODES[(FD_HOVER_MODES.indexOf(FD_HOVER_MODE) + 1) % FD_HOVER_MODES.length];
    try { localStorage.setItem('flightCardHoverMode', FD_HOVER_MODE); } catch (e) {}
    paintHoverBtn();
    requestAnimationFrame(markLastRowCards);
}

// ── Render ───────────────────────────────────────────────────────────────
function renderFlightCards() {
    if (!document.getElementById('fdLedger').hidden && FD_LEDGER_ID === 'all') openDashboardPassengers(false);
    if (!document.getElementById('fdLedger').hidden && String(FD_LEDGER_ID || '').startsWith('airline:')) {
        openAirlineLedger(String(FD_LEDGER_ID).slice(8), false);
    }
    const grid = document.getElementById('fdGrid');
    if (!grid) return;
    paintSortBtn();
    paintHoverBtn();
    paintGroupSw();

    const flights = filteredDashboardCards();
    const airlineGroups = groupDashboardFlightsByAirline(flights);
    if (!FD_FLIGHTS.length) {
        grid.innerHTML = `<div class="fd-empty"><i class="fas fa-plane-slash"></i>
            <div style="font-weight:600;color:#475569;">No active flights.</div>
            <div style="font-size:0.8rem;margin-top:4px;">Every batch has departed or been cancelled. <a href="${FD_FLIGHTS_URL}">Open Flights &amp; Passengers</a> to add one.</div></div>`;
        renderFlightTable();
        return;
    }
    if (MARK_RO) MARK_RO.disconnect();   // the cards being replaced were observed; drop them before they go
    const cardsHtml = FD_CARD_GROUP === 'flight' ? sortFlights(flights).map(flightCardHtml) : airlineGroups.map(airlineCardHtml);
    grid.innerHTML = cardsHtml.join('') || '<div class="fd-empty">No active flights match these filters. Clear the search or reset the filters.</div>';
    fitMarks(grid);
    requestAnimationFrame(markLastRowCards);
    renderFlightTable();
}

function fmtTableDate(d) {
    return d ? `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}` : '—';
}

function dashboardLocalDateValue(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function clearDashboardDatePreset() {
    document.querySelectorAll('#fdDatePresets button').forEach(button => button.classList.remove('is-on'));
}

function applyDashboardDatePreset(preset) {
    const from = document.getElementById('fdTableFrom');
    const to = document.getElementById('fdTableTo');
    const today = new Date(FD_BUSINESS_TODAY + 'T12:00:00');
    let start = '', end = '';

    if (preset === 'clear') {
        // The default active view starts today and has no end date, so today
        // and every upcoming flight remain together in this one table.
        from.value = FD_BUSINESS_TODAY;
        to.value = '';
        document.getElementById('fdActiveFromText').value = ledgerDMY(FD_BUSINESS_TODAY);
        document.getElementById('fdActiveToText').value = '';
    } else if (preset === 'week') {
        const day = today.getDay();
        const mondayOffset = day === 0 ? -6 : 1 - day;
        const monday = new Date(today);
        monday.setDate(today.getDate() + mondayOffset);
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        start = dashboardLocalDateValue(monday);
        end = dashboardLocalDateValue(sunday);
        from.value = start;
        to.value = end;
    } else {
        const selected = new Date(today);
        selected.setDate(today.getDate() + (preset === 'yesterday' ? -1 : preset === 'tomorrow' ? 1 : 0));
        start = dashboardLocalDateValue(selected);
        from.value = start;
        to.value = start;
    }

    if (preset !== 'clear') {
        document.getElementById('fdActiveFromText').value = ledgerDMY(from.value);
        document.getElementById('fdActiveToText').value = ledgerDMY(to.value);
    }

    document.querySelectorAll('#fdDatePresets button').forEach(button => {
        button.classList.toggle('is-on', button.dataset.datePreset === preset);
    });
    renderFlightTable();
}

function applyActiveFlightDates() {
    const values = [
        parseFlightSectionDate(document.getElementById('fdActiveFromText').value),
        parseFlightSectionDate(document.getElementById('fdActiveToText').value),
    ];
    const textInputs = [document.getElementById('fdActiveFromText'), document.getElementById('fdActiveToText')];
    values.forEach((value, index) => markPassengerDateBad(textInputs[index], value === null));
    if (values.includes(null)) return;
    if (values[0] && values[1] && values[0] > values[1]) {
        markPassengerDateBad(textInputs[1], true);
        return;
    }
    document.getElementById('fdTableFrom').value = values[0];
    document.getElementById('fdTableTo').value = values[1];
    textInputs.forEach((input, index) => { input.value = values[index] ? ledgerDMY(values[index]) : ''; });
    clearDashboardDatePreset();
    renderFlightTable();
}

function overallFilterIsActive() {
    return Boolean(document.getElementById('fdOverallSearch').value.trim()
        || document.getElementById('fdOverallAgent').value
        || document.getElementById('fdOverallFrom').value
        || document.getElementById('fdOverallTo').value
        || FD_KPI_FILTER);
}

function updateOverallFilterState() {
    document.getElementById('fdOverallFilterState').classList.toggle('is-active', overallFilterIsActive());
}

function renderOverallDashboard() {
    updateOverallFilterState();
    renderFlightCards();
    renderDepartedTable();
}

function applyOverallDatePreset(preset) {
    const from = document.getElementById('fdOverallFrom');
    const to = document.getElementById('fdOverallTo');
    const today = new Date(FD_BUSINESS_TODAY + 'T12:00:00');
    let start = '', end = '';
    if (preset === 'week') {
        const monday = new Date(today);
        monday.setDate(today.getDate() + (today.getDay() === 0 ? -6 : 1 - today.getDay()));
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        start = dashboardLocalDateValue(monday);
        end = dashboardLocalDateValue(sunday);
    } else if (preset !== 'clear') {
        const selected = new Date(today);
        selected.setDate(today.getDate() + (preset === 'yesterday' ? -1 : preset === 'tomorrow' ? 1 : 0));
        start = end = dashboardLocalDateValue(selected);
    }
    from.value = start;
    to.value = end;
    document.getElementById('fdOverallFromText').value = start ? ledgerDMY(start) : '';
    document.getElementById('fdOverallToText').value = end ? ledgerDMY(end) : '';
    document.querySelectorAll('#fdOverallDatePresets button').forEach(button => button.classList.toggle('is-on', button.dataset.overallPreset === preset));
    renderOverallDashboard();
}

function applyOverallDates() {
    const values = ['From', 'To'].map(key => parseFlightSectionDate(document.getElementById('fdOverall' + key + 'Text').value));
    const inputs = ['From', 'To'].map(key => document.getElementById('fdOverall' + key + 'Text'));
    values.forEach((value, index) => markPassengerDateBad(inputs[index], value === null));
    if (values.includes(null)) return;
    if (values[0] && values[1] && values[0] > values[1]) { markPassengerDateBad(inputs[1], true); return; }
    ['From', 'To'].forEach((key, index) => {
        document.getElementById('fdOverall' + key).value = values[index];
        inputs[index].value = values[index] ? ledgerDMY(values[index]) : '';
    });
    document.querySelectorAll('#fdOverallDatePresets button').forEach(button => button.classList.remove('is-on'));
    renderOverallDashboard();
}

function resetOverallFlightFilter() {
    document.getElementById('fdOverallSearch').value = '';
    document.getElementById('fdOverallSearchWrap').classList.remove('has-q');
    document.getElementById('fdOverallAgent').value = '';
    if (window.jQuery) window.jQuery('#fdOverallAgent').trigger('change.select2');
    ['From', 'To'].forEach(key => {
        document.getElementById('fdOverall' + key).value = '';
        document.getElementById('fdOverall' + key + 'Text').value = '';
    });
    FD_KPI_FILTER = '';
    document.querySelectorAll('[data-kpi-filter]').forEach(button => { button.classList.remove('is-active'); button.setAttribute('aria-pressed', 'false'); });
    document.querySelectorAll('#fdOverallDatePresets button').forEach(button => button.classList.toggle('is-on', button.dataset.overallPreset === 'clear'));
    renderOverallDashboard();
}

function populateDashboardAgents() {
    const agents = new Map();
    FD_ALL_FLIGHTS.flatMap(flight => flight.agents || []).forEach(agent => {
        if (agent.id && agent.name) agents.set(String(agent.id), agent.name);
    });
    const options = '<option value="">All agents</option>' + [...agents.entries()]
        .sort(([, first], [, second]) => first.localeCompare(second))
        .map(([id, name]) => `<option value="${esc(id)}">${esc(name)}</option>`).join('');
    const select = document.getElementById('fdOverallAgent');
    if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
        window.jQuery(select).select2('destroy');
    }
    select.innerHTML = options;
    initialiseOverallAgentSelect();
}

function initialiseOverallAgentSelect() {
    if (!window.jQuery?.fn?.select2) return;
    window.jQuery('#fdOverallAgent').select2({
        width: '116px',
        dropdownAutoWidth: true,
        minimumResultsForSearch: Infinity,
        dropdownParent: window.jQuery('.fd-overall-agent'),
        dropdownCssClass: 'fd-overall-agent-menu',
    });
}

let FD_KPI_FILTER = '';

function applyDashboardKpiFilter(filter) {
    FD_KPI_FILTER = FD_KPI_FILTER === filter ? '' : filter;
    document.querySelectorAll('[data-kpi-filter]').forEach(button => {
        const active = button.dataset.kpiFilter === FD_KPI_FILTER;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    renderOverallDashboard();
    if (FD_KPI_FILTER) openDashboardPassengers();
}

function dashboardFlightSearchText(f) {
    return [f.flight_number, f.airline_flight_no, f.airline, ...(f.airlines || []), f.agent, f.vendor,
        ...(f.ticket_vendors || []).map(v => v.name), f.from?.code, f.from?.name, f.to?.code, f.to?.name,
        f.category, ...(f.agents || []).flatMap(agent => [agent.name, agent.display_id]), f.vendor_display_id]
        .join(' ').toLowerCase();
}

function matchesOverallFlightFilter(f, section) {
    const query = document.getElementById('fdOverallSearch').value.trim().toLowerCase();
    const agentId = document.getElementById('fdOverallAgent').value;
    const from = document.getElementById('fdOverallFrom').value;
    const to = document.getElementById('fdOverallTo').value;
    const day = (f.departure_at || '').slice(0, 10);
    const hasAgent = !agentId || (f.agents || []).some(agent => String(agent.id) === agentId);
    const matchesKpi = !FD_KPI_FILTER
        || (FD_KPI_FILTER === 'airline' && f.airline)
        || (FD_KPI_FILTER === 'vendor_due' && Number(f.vendor_due) > 0)
        || (FD_KPI_FILTER === 'vendor' && f.vendor_id);
    return hasAgent && matchesKpi
        && (!query || dashboardFlightSearchText(f).includes(query))
        && (!from || (day && day >= from)) && (!to || (day && day <= to));
}

function filteredDashboardCards() {
    return sortFlights(FD_FLIGHTS).filter(f => matchesOverallFlightFilter(f, 'active'));
}

function filteredDashboardFlights(list = FD_FLIGHTS) {
    const query = document.getElementById('fdTableSearch').value.trim().toLowerCase();
    const from = document.getElementById('fdTableFrom').value;
    const to = document.getElementById('fdTableTo').value;
    return sortFlights(list).filter(f => {
        const day = (f.departure_at || '').slice(0, 10);
        return matchesOverallFlightFilter(f, 'active') && (!query || dashboardFlightSearchText(f).includes(query))
            && (!from || (day && day >= from)) && (!to || (day && day <= to));
    });
}

let FD_ALL_PASSENGERS_ABORT = null;
function hideDashboardPassengers() {
    FD_ALL_PASSENGERS_ABORT?.abort();
    FD_ALL_PASSENGERS_ABORT = null;
}
async function openDashboardPassengers(scroll = true) {
    hideDashboardPassengers();
    const controller = new AbortController();
    FD_ALL_PASSENGERS_ABORT = controller;
    const request = ++FD_LEDGER_REQUEST;
    FD_LEDGER_ID = 'all';
    FD_PASSENGERS = [];
    document.getElementById('fdOverview').hidden = true;
    const panel = document.getElementById('fdLedger');
    panel.hidden = false;
    panel.querySelector('.fdl-header').hidden = true;
    document.getElementById('fdlPrint').hidden = true;
    document.getElementById('fdPassengerTable').hidden = true;
    document.getElementById('fdPassengerSearch').disabled = true;
    document.querySelectorAll('#fdGrid [data-flight-id], #fdGrid [data-airline-key]').forEach(card => card.setAttribute('aria-pressed', 'false'));
    const message = document.getElementById('fdLedgerMessage');
    message.hidden = false;
    message.textContent = 'Loading passengers?';
    document.getElementById('fdLedgerCount').textContent = 'All matching flights';
    panel.setAttribute('aria-busy', 'true');
    if (scroll) {
        document.getElementById('fdLedgerTitle').focus({preventScroll: true});
        panel.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start'});
    }
    const flights = filteredDashboardFlights();
    const results = new Array(flights.length);
    let next = 0;
    try {
        await Promise.all(Array.from({length: Math.min(3, flights.length)}, async () => {
            while (next < flights.length && !controller.signal.aborted) {
                const index = next++;
                const flight = flights[index];
                const response = await fetch(FD_PASSENGERS_URL.replace('__FLIGHT__', encodeURIComponent(flight.id)), {
                    headers: {Accept: 'application/json'}, credentials: 'same-origin', signal: controller.signal
                });
                if (!response.ok) throw new Error('Unable to load passengers');
                const data = await response.json();
                if (!data.success || !Array.isArray(data.passengers)) throw new Error('Invalid passenger response');
                results[index] = data.passengers.map(p => ({...p, dashboard_flight_id: flight.id}));
            }
        }));
        if (request !== FD_LEDGER_REQUEST || controller.signal.aborted) return;
        FD_PASSENGERS = results.flat();
        document.getElementById('fdPassengerSearch').disabled = false;
        document.getElementById('fdLedgerCount').textContent = FD_PASSENGERS.length + ' passengers ? ' + flights.length + ' flights';
        paintLedgerRail(FD_PASSENGERS);
        renderPassengerLedger();
    } catch (error) {
        if (request !== FD_LEDGER_REQUEST || controller.signal.aborted) return;
        controller.abort();
        message.textContent = 'Could not load passengers. Use the reload button to try again.';
    } finally {
        if (request === FD_LEDGER_REQUEST) panel.setAttribute('aria-busy', 'false');
    }
}

async function openAirlineLedger(key, scroll = true) {
    hideDashboardPassengers();
    const flights = filteredDashboardCards().filter(flight => airlineGroupKey(flight) === key);
    if (!flights.length) {
        closeFlightLedger();
        return;
    }
    const airline = flights[0].airline || 'Airline pending';
    const controller = new AbortController();
    FD_ALL_PASSENGERS_ABORT = controller;
    const request = ++FD_LEDGER_REQUEST;
    FD_LEDGER_ID = `airline:${key}`;
    FD_PASSENGERS = [];
    document.getElementById('fdOverview').hidden = true;
    const panel = document.getElementById('fdLedger');
    panel.hidden = false;
    panel.querySelector('.fdl-header').hidden = false;
    document.getElementById('fdlPrint').hidden = false;
    document.getElementById('fdPassengerTable').hidden = true;
    document.getElementById('fdPassengerSearch').disabled = true;
    paintLedgerRail(null);
    paintPassengerBar();
    document.getElementById('fdPassengerCount').style.display = 'none';
    paintAirlineLedgerHeader(airlineSummary({key, name: airline, flights}));
    fitFlightLedgerHeader();
    document.querySelectorAll('#fdGrid [data-airline-key]').forEach(card => {
        card.setAttribute('aria-pressed', String(card.dataset.airlineKey === key));
    });
    const message = document.getElementById('fdLedgerMessage');
    message.hidden = false;
    message.textContent = `Loading ${airline} passengersâ€¦`;
    document.getElementById('fdLedgerCount').textContent = `${airline} Â· ${flights.length} flight${flights.length === 1 ? '' : 's'}`;
    panel.setAttribute('aria-busy', 'true');
    if (scroll) {
        document.getElementById('fdLedgerTitle').focus({preventScroll: true});
        panel.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start'});
    }
    const results = new Array(flights.length);
    let next = 0;
    try {
        await Promise.all(Array.from({length: Math.min(3, flights.length)}, async () => {
            while (next < flights.length && !controller.signal.aborted) {
                const index = next++;
                const flight = flights[index];
                const response = await fetch(FD_PASSENGERS_URL.replace('__FLIGHT__', encodeURIComponent(flight.id)), {
                    headers: {Accept: 'application/json'}, credentials: 'same-origin', signal: controller.signal
                });
                if (!response.ok) throw new Error('Unable to load passengers');
                const data = await response.json();
                if (!data.success || !Array.isArray(data.passengers)) throw new Error('Invalid passenger response');
                results[index] = data.passengers.map(passenger => ({...passenger, dashboard_flight_id: flight.id}));
            }
        }));
        if (request !== FD_LEDGER_REQUEST || controller.signal.aborted) return;
        FD_PASSENGERS = results.flat();
        document.getElementById('fdPassengerSearch').disabled = false;
        document.getElementById('fdLedgerCount').textContent = `${airline} Â· ${FD_PASSENGERS.length} passenger${FD_PASSENGERS.length === 1 ? '' : 's'} Â· ${flights.length} flight${flights.length === 1 ? '' : 's'}`;
        const passengerTable = document.getElementById('fdPassengerTable');
        passengerTable.dataset.cfSortKey = '0';
        passengerTable.dataset.cfSortDir = 'desc';
        paintLedgerRail(FD_PASSENGERS);
        renderPassengerLedger();
    } catch (error) {
        if (request !== FD_LEDGER_REQUEST || controller.signal.aborted) return;
        controller.abort();
        message.textContent = `Could not load ${airline} passengers. Use the reload button to try again.`;
    } finally {
        if (request === FD_LEDGER_REQUEST) panel.setAttribute('aria-busy', 'false');
    }
}

// Every flight section opens newest departure first and keeps column sorts descending.
let FD_TABLE_SORT = { key: 'departure', direction: 'desc' };
const FD_TABLE_SORT_VALUES = {
    nearest: f => { const v = fdNearestKey(f); return Number.isFinite(v) ? v : null; },
    departure: f => { const value = Date.parse(f.departure_at || ''); return Number.isNaN(value) ? null : value; },
    airline: f => [f.airline || 'Airline pending', f.flight_number, f.airline_flight_no].filter(Boolean).join(' '),
    route: f => [f.from?.code || f.from?.name, f.to?.code || f.to?.name].filter(Boolean).join(' '),
    agent: f => [(f.agents || []).map(a => a.name).filter(Boolean).sort().join(', ') || f.agent || 'Direct', f.vendor || (f.ticket_vendors || []).map(v => v.name).join(' ')].join(' '),
    passengers: f => Number(f.passengers || 0),
    status: f => f.case_closed ? 'Case Closed' : (f.status || 'Open'),
    revenue: f => Number(f.revenue || 0),
    cost: f => Number(f.cost || 0),
    vendor_due: f => Number(f.vendor_due || 0)
};
function sortFlightTableRows(flights, sort = FD_TABLE_SORT) {
    const value = FD_TABLE_SORT_VALUES[sort.key];
    const direction = sort.direction === 'asc' ? 1 : -1;
    return [...flights].sort((a, b) => {
        const av = value(a), bv = value(b);
        const missingA = av === null || av === '' || (typeof av === 'number' && !Number.isFinite(av));
        const missingB = bv === null || bv === '' || (typeof bv === 'number' && !Number.isFinite(bv));
        if (missingA || missingB) return Number(missingA) - Number(missingB);
        const comparison = typeof av === 'number' ? av - bv : av.localeCompare(bv, undefined, {numeric: true, sensitivity: 'base'});
        return comparison * direction;
    });
}
function toggleFlightTableSort(key) {
    if (!Object.hasOwn(FD_TABLE_SORT_VALUES, key)) return;
    FD_TABLE_SORT = {key, direction: 'desc'};
    renderFlightTable();
}
function paintFlightTableSort(tableId = 'fdFlightTable', sort = FD_TABLE_SORT) {
    document.querySelectorAll(`#${tableId} th[data-sort-column]`).forEach(th => {
        const active = th.dataset.sortColumn === sort.key;
        const ascending = sort.direction === 'asc';
        th.setAttribute('aria-sort', active ? (ascending ? 'ascending' : 'descending') : 'none');
        th.querySelector('i').className = 'fas ' + (active ? (ascending ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort');
        const action = 'descending';
        const label = th.querySelector('span').textContent;
        const hint = th.dataset.sortColumn === 'departure' ? (action === 'ascending' ? 'oldest first' : 'newest first') : action;
        th.querySelector('button').setAttribute('aria-label', label + ': sort ' + hint);
        th.querySelector('button').title = 'Sort ' + hint;
    });
}

function scrollToFlightTable() {
    hideDashboardPassengers();
    const overview = document.getElementById('fdOverview');
    if (overview.hidden) {
        ++FD_LEDGER_REQUEST;
        FD_LEDGER_ID = null;
        document.getElementById('fdLedger').hidden = true;
        overview.hidden = false;
        document.querySelectorAll('#fdGrid [data-flight-id], #fdGrid [data-airline-key]').forEach(card => card.setAttribute('aria-pressed', 'false'));
    }
    const table = document.getElementById('fdFlightTable');
    table.setAttribute('tabindex', '-1');
    table.focus({preventScroll: true});
    overview.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start'});
}

// Pagination only slices the display; statements retain the full filtered result.
const FD_PAGINATION = Object.fromEntries(['active', ...Object.keys(FD_HISTORY)].map(section => [section, {page: 1, size: 5, signature: ''}]));
function paginateFlightSection(section, flights) {
    const state = FD_PAGINATION[section];
    const signature = JSON.stringify(flights.map(f => f.id));
    if (signature !== state.signature) { state.page = 1; state.signature = signature; }
    const pages = Math.max(1, Math.ceil(flights.length / state.size));
    state.page = Math.max(1, Math.min(state.page, pages));
    const start = (state.page - 1) * state.size;
    document.getElementById('fd' + section + 'PageSize').value = String(state.size);
    document.getElementById('fd' + section + 'PageRange').textContent = flights.length ? (start + 1) + '-' + Math.min(start + state.size, flights.length) + ' of ' + flights.length + ' flights' : '0 flights';
    document.getElementById('fd' + section + 'PageNumber').textContent = 'Page ' + state.page + ' of ' + pages;
    document.getElementById('fd' + section + 'PagePrevious').disabled = state.page === 1;
    document.getElementById('fd' + section + 'PageNext').disabled = state.page === pages;
    return flights.slice(start, start + state.size);
}
function renderFlightPage(section) {
    if (section === 'active') renderFlightTable();
    else renderFlightSection(section);
}
function setFlightPageSize(section, size) {
    if (!Object.hasOwn(FD_PAGINATION, section) || ![5, 10].includes(Number(size))) return;
    FD_PAGINATION[section].size = Number(size);
    FD_PAGINATION[section].page = 1;
    renderFlightPage(section);
}
function changeFlightPage(section, offset) {
    if (!Object.hasOwn(FD_PAGINATION, section) || ![-1, 1].includes(offset)) return;
    FD_PAGINATION[section].page += offset;
    renderFlightPage(section);
}

function renderFlightTable() {
    const body = document.getElementById('fdTableBody');
    const flights = sortFlightTableRows(filteredDashboardFlights());
    paintFlightTableSort();
    document.getElementById('fdTableCount').textContent = `${flights.length} of ${FD_FLIGHTS.length} flights`;
    body.innerHTML = paginateFlightSection('active', flights).map(f => flightTableRowHtml(f, 'active')).join('') || '<tr><td colspan="9" class="fd-table-empty">No active flights match these filters.</td></tr>';
}

let FD_DEPARTED_STATE = 'all';
function setDepartedState(state) {
    FD_DEPARTED_STATE = state;
    document.querySelectorAll('#fddeparted [data-state]').forEach(b => b.classList.toggle('is-on', b.dataset.state === state));
    renderFlightSection('departed');
}
const FD_SECTION_TITLES = {active: 'Active / Upcoming Flights', overdue: 'Overdue Flights', offloaded: 'Offloaded / Deported', departed: 'Departed Flights'};
const FD_SECTION_SORT = Object.fromEntries(Object.keys(FD_HISTORY).map(key => [key, {key: 'departure', direction: 'desc'}]));
function sectionInput(section, suffix) { return document.getElementById('fd' + section + 'Filter' + suffix); }
function filteredFlightSection(section) {
    if (section === 'active') return filteredDashboardFlights();
    const query = (sectionInput(section, 'Search').value || '').trim().toLowerCase();
    const from = sectionInput(section, 'From').value, to = sectionInput(section, 'To').value;
    return FD_HISTORY[section].filter(f => {
        const day = (f.departure_at || '').slice(0, 10);
        const matchesState = section !== 'departed' || FD_DEPARTED_STATE === 'all'
            || (FD_DEPARTED_STATE === 'closed' && f.case_closed)
            || (FD_DEPARTED_STATE === 'departed' && !f.case_closed)
            || (FD_DEPARTED_STATE === 'due' && Number(f.vendor_due) > 0);
        return matchesOverallFlightFilter(f, section) && matchesState
            && (!query || dashboardFlightSearchText(f).includes(query))
            && (!from || (day && day >= from)) && (!to || (day && day <= to));
    });
}
function toggleFlightSectionSort(section, key) {
    if (!Object.hasOwn(FD_TABLE_SORT_VALUES, key)) return;
    FD_SECTION_SORT[section] = {key, direction: 'desc'};
    renderFlightSection(section);
}
function flightSectionRow(f, section) {
    let row = flightTableRowHtml(section === 'departed' ? {...f, status: 'departed'} : f, section);
    const detail = section === 'offloaded' ? f.offloaded_count + ' offloaded / deported' : section === 'overdue' ? 'Departure passed ? update required' : '';
    if (detail) row = row.replace('<td class="fd-passengers">', '<td class="fd-passengers"><small style="color:#dc2626">' + esc(detail) + '</small>');
    return row;
}
function renderFlightSection(section) {
    const flights = sortFlightTableRows(filteredFlightSection(section), FD_SECTION_SORT[section]);
    document.getElementById('fd' + section + 'Count').textContent = flights.length + ' of ' + FD_HISTORY[section].length + ' flights';
    paintFlightTableSort('fd' + section + 'Table', FD_SECTION_SORT[section]);
    document.getElementById('fd' + section + 'Body').innerHTML = paginateFlightSection(section, flights).map(f => flightSectionRow(f, section)).join('') || '<tr><td colspan="9" class="fd-table-empty">No flights match these filters.</td></tr>';
}
function renderDepartedTable() { Object.keys(FD_HISTORY).forEach(renderFlightSection); }
function parseFlightSectionDate(value) {
    const day = parsePassengerDate(value);
    if (!day) return day;
    const date = new Date(day + 'T12:00:00');
    return !Number.isNaN(date.getTime()) && dashboardLocalDateValue(date) === day ? day : null;
}
function applyFlightSectionDates(section) {
    const values = ['From', 'To'].map(key => parseFlightSectionDate(sectionInput(section, key + 'Text').value));
    values.forEach((value, i) => markPassengerDateBad(sectionInput(section, ['From', 'To'][i] + 'Text'), value === null));
    if (values.includes(null)) return;
    if (values[0] && values[1] && values[0] > values[1]) { markPassengerDateBad(sectionInput(section, 'ToText'), true); return; }
    ['From', 'To'].forEach((key, i) => { sectionInput(section, key).value = values[i]; sectionInput(section, key + 'Text').value = values[i] ? ledgerDMY(values[i]) : ''; });
    document.querySelectorAll('#fd' + section + ' [data-preset]').forEach(b => b.classList.remove('is-on'));
    renderFlightSection(section);
}
function flightSectionPreset(section, preset) {
    const date = new Date(FD_BUSINESS_TODAY + 'T12:00:00');
    date.setDate(date.getDate() + (preset === 'yesterday' ? -1 : preset === 'tomorrow' ? 1 : 0));
    const day = preset === 'clear' ? '' : dashboardLocalDateValue(date);
    ['From', 'To'].forEach(key => { sectionInput(section, key).value = day; sectionInput(section, key + 'Text').value = day ? ledgerDMY(day) : ''; markPassengerDateBad(sectionInput(section, key + 'Text'), false); });
    document.querySelectorAll('#fd' + section + ' [data-preset]').forEach(b => b.classList.toggle('is-on', preset !== 'clear' && b.dataset.preset === preset));
    renderFlightSection(section);
}
function wireActiveFlightBar() {
    const search = document.getElementById('fdTableSearch');
    const wrap = document.getElementById('fdActiveSearchWrap');
    let timer = null;
    const run = () => {
        wrap.classList.toggle('has-q', search.value.trim() !== '');
        renderFlightTable();
    };
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 140); });
    search.addEventListener('keydown', event => {
        if (event.key === 'Escape') { search.value = ''; clearTimeout(timer); run(); }
    });
    document.getElementById('fdActiveSearchClear').addEventListener('click', () => {
        search.value = '';
        clearTimeout(timer);
        run();
        search.focus();
    });
    [['fdTableFrom', 'fdActiveFromText'], ['fdTableTo', 'fdActiveToText']].forEach(([nativeId, textId]) => {
        const native = document.getElementById(nativeId);
        const text = document.getElementById(textId);
        native.addEventListener('change', () => {
            text.value = native.value ? ledgerDMY(native.value) : '';
            applyActiveFlightDates();
        });
        text.addEventListener('keydown', event => {
            if (event.key === 'Enter') { event.preventDefault(); applyActiveFlightDates(); }
        });
    });
    document.querySelectorAll('#fdActiveDates .fdl-date').forEach(box => {
        box.addEventListener('click', event => {
            if (event.target !== box) return;
            const native = box.querySelector('.fdl-date-native');
            if (native?.showPicker) { try { native.showPicker(); } catch (_) {} }
        });
    });
}
function wireOverallFlightBar() {
    const search = document.getElementById('fdOverallSearch');
    const wrap = document.getElementById('fdOverallSearchWrap');
    let timer = null;
    const run = () => {
        wrap.classList.toggle('has-q', search.value.trim() !== '');
        renderOverallDashboard();
    };
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 140); });
    search.addEventListener('keydown', event => {
        if (event.key === 'Escape') { search.value = ''; clearTimeout(timer); run(); }
    });
    document.getElementById('fdOverallSearchClear').addEventListener('click', () => { search.value = ''; clearTimeout(timer); run(); search.focus(); });
    document.getElementById('fdOverallAgent').addEventListener('change', () => {
        renderOverallDashboard();
    });
    [['fdOverallFrom', 'fdOverallFromText'], ['fdOverallTo', 'fdOverallToText']].forEach(([nativeId, textId]) => {
        const native = document.getElementById(nativeId);
        const text = document.getElementById(textId);
        native.addEventListener('change', () => { text.value = native.value ? ledgerDMY(native.value) : ''; applyOverallDates(); });
        text.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); applyOverallDates(); } });
    });
    document.querySelectorAll('#fdOverallDates .fdl-date').forEach(box => {
        box.addEventListener('click', event => {
            if (event.target !== box) return;
            const native = box.querySelector('.fdl-date-native');
            if (native?.showPicker) { try { native.showPicker(); } catch (_) {} }
        });
    });
}
function wireDepartedBar() {
    Object.keys(FD_HISTORY).forEach(section => {
        const search = sectionInput(section, 'Search');
        const run = () => { sectionInput(section, 'SearchWrap').classList.toggle('has-q', search.value.trim() !== ''); renderFlightSection(section); };
        search.addEventListener('input', run);
        sectionInput(section, 'Clear').addEventListener('click', () => { search.value = ''; run(); search.focus(); });
        ['From', 'To'].forEach(key => {
            sectionInput(section, key).addEventListener('change', () => { sectionInput(section, key + 'Text').value = sectionInput(section, key).value ? ledgerDMY(sectionInput(section, key).value) : ''; applyFlightSectionDates(section); });
            sectionInput(section, key + 'Text').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); applyFlightSectionDates(section); } });
        });
    });
}
function effectiveFlightPrintPeriod(section) {
    const localFrom = section === 'active' ? document.getElementById('fdTableFrom').value : sectionInput(section, 'From').value;
    const localTo = section === 'active' ? document.getElementById('fdTableTo').value : sectionInput(section, 'To').value;
    const overallFrom = document.getElementById('fdOverallFrom').value;
    const overallTo = document.getElementById('fdOverallTo').value;
    return {
        from: [localFrom, overallFrom].filter(Boolean).sort().at(-1) || '',
        to: [localTo, overallTo].filter(Boolean).sort().at(0) || '',
    };
}
function openDashboardPrint(section) {
    const flights = sortFlightTableRows(filteredFlightSection(section), section === 'active' ? FD_TABLE_SORT : FD_SECTION_SORT[section]);
    const {from, to} = effectiveFlightPrintPeriod(section);
    const headers = [
        {key: 'sl', label: 'SL'},
        {key: 'passenger', label: 'Passenger / Passport'},
        {key: 'agent', label: 'Agent / Travel Details'},
        {key: 'tickets', label: 'Tickets / PNR'},
        {key: 'route', label: 'Route'},
        {key: 'status', label: 'Current Status'},
    ];
    let serial = 0;
    const records = flights.flatMap(flight => {
        const passengers = (flight.passenger_details || []).length ? flight.passenger_details : [{
            id: 'empty', name: 'No passengers recorded', passport_no: null,
            agent: flight.agent || 'Direct', category: flight.category,
            ticket_progress: '0/0 ticketed', pnrs: [], stage: 'Not started', exception: null,
        }];
        const route = flight.route_path || [flight.from?.code, flight.to?.code].filter(Boolean).join(' â†’ ') || 'Route pending';
        const departure = fmtFull(parseIso(flight.departure_at));
        return passengers.map(passenger => ({
            id: `${section}-${flight.id}-${passenger.id}`,
            row: null,
            table: 0,
            date: (flight.departure_at || '').slice(0, 10),
            category: passenger.category || flight.category || 'Other',
            cells: {
                sl: String(++serial),
                passenger: `<b>${esc(passenger.name)}</b><br>${esc(passenger.passport_no || 'Passport not recorded')}`,
                agent: `<b>${esc(passenger.agent || 'Direct')}</b>${passenger.agent_display_id ? `<br>${esc(passenger.agent_display_id)}` : ''}${passenger.category ? `<br>${esc(passenger.category)}` : ''}`,
                tickets: `<b>${esc(passenger.ticket_progress || 'â€”')}</b>${(passenger.pnrs || []).length ? `<br>${passenger.pnrs.map(esc).join('<br>')}` : '<br>PNR not recorded'}`,
                route: `<b>${esc(flight.flight_number || 'â€”')}</b><br>${esc(route)}<br>${esc(departure)}`,
                status: `${esc(passenger.stage || 'â€”')}${passenger.exception ? `<br><b style="color:#dc2626">${esc(passenger.exception)}</b>` : ''}`,
            },
        }));
    });
    if (!window.cfPrintList) {
        Swal.fire('Print unavailable', 'The Contract Flight print designer could not be loaded.', 'error');
        return;
    }
    window.cfPrintList({headers, records, period: {from, to}, title: FD_SECTION_TITLES[section]});
}

function printFlightSection(section) {
    const flights = sortFlightTableRows(filteredFlightSection(section), section === 'active' ? FD_TABLE_SORT : FD_SECTION_SORT[section]);
    const title = FD_SECTION_TITLES[section];
    const from = section === 'active' ? document.getElementById('fdTableFrom').value : sectionInput(section, 'From').value;
    const to = section === 'active' ? document.getElementById('fdTableTo').value : sectionInput(section, 'To').value;
    const sum = key => flights.reduce((total, f) => total + (Number(f[key]) || 0), 0);
    const rows = flights.map(f => '<tr><td>' + esc(fmtFull(parseIso(f.departure_at))) + '</td><td><b>' + esc(f.flight_number) + '</b><br>' + esc(f.airline || '') + '</td><td>' + esc(f.route_path || [f.from?.code, f.to?.code].filter(Boolean).join(' ? ')) + '</td><td>' + esc((f.agents || []).map(a => a.name).join(', ') || f.agent || 'Direct') + '</td><td class="r">' + esc(f.passengers) + '</td><td>' + esc(section === 'missed' ? f.missed_count + ' missed' : section === 'offloaded' ? f.offloaded_count + ' offloaded / deported' : f.case_closed ? 'Case Closed' : section === 'overdue' ? 'Update required' : section === 'departed' ? 'Departed' : f.status) + '</td><td class="r">' + ledgerMoney(f.revenue) + '</td><td class="r">' + ledgerMoney(f.cost) + '</td><td class="r">' + ledgerMoney(f.vendor_due) + '</td></tr>').join('');
    const passengerRows = flights.flatMap(f => (f.passenger_details || []).map((passenger, index) => `<tr>
        <td>${index === 0 ? `<b>${esc(f.flight_number || '—')}</b><br><span class="muted">${esc(fmtFull(parseIso(f.departure_at)))}</span>` : ''}</td>
        <td><b>${esc(passenger.name)}</b>${passenger.category ? `<br><span class="muted">${esc(passenger.category)}</span>` : ''}</td>
        <td>${esc(passenger.passport_no || '—')}</td>
        <td>${esc(passenger.agent || 'Direct')}${passenger.agent_display_id ? `<br><span class="muted">${esc(passenger.agent_display_id)}</span>` : ''}</td>
        <td>${esc(passenger.ticket_progress || '—')}</td>
        <td>${esc(passenger.stage || '—')}${passenger.exception ? `<br><span class="debit">${esc(passenger.exception)}</span>` : ''}</td>
    </tr>`)).join('');
    const passengerTable = '<div class="sh">Passenger Details</div><table class="ledger"><thead><tr><th>Flight</th><th>Passenger</th><th>Passport</th><th>Agent</th><th>Tickets</th><th>Current Status</th></tr></thead><tbody>'
        + (passengerRows || '<tr><td colspan="6" class="muted">No passengers are recorded for the filtered flights.</td></tr>')
        + '</tbody></table>';
    const content = '<div class="label-bar">' + esc(title) + '</div><div class="meta-row"><div><b>Contract Flight Statement</b><p>Departure: ' + esc(from ? ledgerDMY(from) : 'All dates') + (to ? ' to ' + esc(ledgerDMY(to)) : '') + '</p><p>' + flights.length + ' flights ? ' + sum('passengers') + ' passengers</p></div><div>Generated: ' + esc(fmtTableDate(new Date())) + '</div></div><table class="ledger"><thead><tr><th>Departure</th><th>Flight / Airline</th><th>Route</th><th>Agent</th><th class="r">Pax</th><th>Status</th><th class="r">Charge</th><th class="r">Cost</th><th class="r">Vendor Due</th></tr></thead><tbody>' + (rows || '<tr><td colspan="9">No flights match these filters.</td></tr>') + '</tbody><tfoot><tr><td colspan="6">Flight totals</td><td class="r">' + ledgerMoney(sum('revenue')) + '</td><td class="r">' + ledgerMoney(sum('cost')) + '</td><td class="r">' + ledgerMoney(sum('vendor_due')) + '</td></tr></tfoot></table>' + passengerTable + '<p class="nb">Amounts and passenger totals cover each whole flight. Exception counts identify affected passengers; mixed flights can appear in multiple sections.</p>';
    const preview = window.open('', '_blank');
    if (!preview) { alert('Please allow pop-ups to print the statement.'); return; }
    const html = '<!DOCTYPE html><html lang="en"><head>' + document.getElementById('fdStatementHead').innerHTML + '</head><body>' + document.getElementById('fdStatementTemplate').innerHTML.replace('<div id="fdStatementContent"></div>', content) + '</body></html>';
    preview.document.open();
    preview.addEventListener('load', async () => { await preview.document.fonts.ready; preview.focus(); preview.print(); }, {once: true});
    preview.document.write(html); preview.document.close();
    preview.document.title = title;
}

// Why a row is where it is: still in work (Open / Boarding), or off the wall
// because every passenger is at Case Closed, or marked Departed.
// At most two agents per row; the rest fold behind "… +N more", which opens
// them in place (owner, 2026-10-01). The button stops its click so the row's
// own click (open the passenger ledger) does not fire as well.
const FD_AGENTS_SHOWN = 2;
function flightAgentsHtml(agents) {
    const one = agent => `<div><span class="fd-agent-name fd-clip" title="${esc(agent.name)}"><i class="fas fa-user-tie" aria-hidden="true"></i><span>${esc(agent.name)}</span></span><small class="fd-detail fd-clip">Agent ID: <a href="${esc(FD_PARTY_URL)}?party_id=${encodeURIComponent(agent.id)}" title="Open ${esc(agent.name)}'s statement">${esc(agent.display_id)}</a></small></div>`;
    if (agents.length < 2) return agents.map(one).join('');
    // Two or more: one line per agent, the ID beside the name, so two agents
    // plus "+N more" plus the vendor line stand as tall as the four-line
    // Route cell and the row keeps the height of every other row.
    const line = agent => `<div class="fd-agent-line fd-clip" title="${esc(agent.name)} · ${esc(agent.display_id)}"><i class="fas fa-user-tie" aria-hidden="true"></i> ${esc(agent.name)} <a href="${esc(FD_PARTY_URL)}?party_id=${encodeURIComponent(agent.id)}" title="Open ${esc(agent.name)}'s statement">${esc(agent.display_id)}</a></div>`;
    const rest = agents.slice(FD_AGENTS_SHOWN);
    return agents.slice(0, FD_AGENTS_SHOWN).map(line).join('')
        + (rest.length ? `<div class="fd-agents-rest" hidden>${rest.map(line).join('')}</div>`
            + `<button type="button" class="fd-agents-more" aria-expanded="false" onclick="fdToggleAgents(this, event)">… +${rest.length} more</button>` : '');
}
function fdToggleAgents(btn, event) {
    event.stopPropagation();
    const rest = btn.previousElementSibling;
    const open = rest.hidden;
    rest.hidden = !open;
    btn.setAttribute('aria-expanded', String(open));
    btn.textContent = open ? 'Show less' : `… +${rest.children.length} more`;
}

function flightStatusBadge(f, section = 'active') {
    if (section === 'overdue') return `<span class="fd-table-status overdue">Update Required</span>`;
    if (section === 'offloaded') return `<span class="fd-table-status offloaded">${esc(f.offloaded_count || 0)} Offloaded / Deported</span>`;
    if (f.case_closed) return `<span class="fd-table-status closed">Case Closed</span>`;
    if (f.status === 'departed') return `<span class="fd-table-status departed">Departed</span>`;
    return `<span class="fd-table-status${f.status === 'boarding' ? ' boarding' : ''}">${f.status === 'boarding' ? 'Boarding' : 'Open'}</span>`;
}

function flightTableRowHtml(f, section = 'active') {
    const departure = parseIso(f.departure_at);
    const arrival = parseIso(f.arrival_at);
    const href = `#flight-ledger-${encodeURIComponent(f.id)}`;
    // One line per airport name, the "(DAC)" suffix dropped since the code
    // is on the line above — the same three-line route as the ledger.
    const fromName = airportName(f.from, 'Origin pending'), toName = airportName(f.to, 'Destination pending');
    const category = `${f.category || 'Category not set'}${f.ticket_class ? ' · ' + f.ticket_class : ''}`;
    return `<tr data-flight-id="${esc(f.id)}" tabindex="0" aria-label="Open passenger ledger for ${esc(f.flight_number)}" style="cursor:pointer;">
        <td class="fd-nowrap">${esc(fmtFull(departure))}<small>${departure ? esc(fmtTime(departure)) : 'Date not set'}</small></td>
        <td><a href="${esc(href)}">${esc(f.airline ? tidyAirline(f.airline) : 'Airline pending')}</a>
            <small class="fd-detail"><a href="${esc(href)}" title="Open passenger ledger">${esc(f.flight_number)}${f.airline_flight_no ? ' · ' + esc(f.airline_flight_no) : ''}</a></small>
            <small class="fd-clip" title="${esc(category)}">${esc(category)}</small></td>
        <td><div class="fd-nowrap">${flagImg(f.from, 'fdc-flag')} ${esc(f.from?.code || '—')} → ${esc(f.to?.code || '—')} ${flagImg(f.to, 'fdc-flag')}</div>
            <small class="fd-airport-detail fd-clip" title="${esc(fromName)}"><i class="fas fa-plane-departure" aria-hidden="true"></i><span>${esc(fromName)}</span></small>
            <small class="fd-airport-detail fd-clip" title="${esc(toName)}"><i class="fas fa-plane-arrival" aria-hidden="true"></i><span>${esc(toName)}</span></small>
            <small class="fd-clip fd-ledger-date"${arrival ? ` title="Arrival: ${esc(fmtTableDate(arrival))}, ${esc(fmtTime(arrival))}"` : ''}><i class="fas fa-calendar-day" aria-hidden="true"></i> ${esc(fmtDeparture(departure))}</small></td>
        <td>${f.agents?.length ? flightAgentsHtml(f.agents) : `<span class="fd-agent-name fd-clip"><i class="fas fa-user-tie" aria-hidden="true"></i><span>${esc(f.agent || 'Direct')}</span></span>`}
            ${f.vendor
                ? `<small class="fd-clip" title="${esc(f.vendor)}">Vendor: ${esc(f.vendor)}</small>
                   <small class="fd-detail">Vendor ID: <a href="${esc(FD_PARTY_URL)}?party_id=${encodeURIComponent(f.vendor_id)}" title="Open ${esc(f.vendor)}'s statement">${esc(f.vendor_display_id)}</a></small>`
                : ((f.ticket_vendors || []).length
                    ? `<small class="fd-clip" title="Ticket vendor${f.ticket_vendors.length > 1 ? 's' : ''}: ${esc(f.ticket_vendors.map(v => v.name).join(', '))} — from the linked PNRs; no batch (flight-cost) vendor set">Ticket vendor: ${f.ticket_vendors.map(v => `<a href="${esc(FD_PARTY_URL)}?party_id=${encodeURIComponent(v.id)}">${esc(v.name)}</a>`).join(', ')}</small>`
                    : `<small class="fd-clip" title="No vendor on the batch and no PNR linked yet">Vendor: Not assigned</small>`)}</td>
        <td class="fd-passengers">${esc(f.passengers)}<small>${esc(f.ticketed)} ticketed · ${Math.max(0, Number(f.passengers) - Number(f.ticketed))} pending</small>
            <small>${esc(f.total_seats)} seats${f.flagged ? ' · ' + esc(f.flagged) + ' flagged' : ''}</small></td>
        <td class="fd-status-cell">${flightStatusBadge(f, section)}</td>
        <td class="fd-num fd-detail">৳${esc(fmtMoney(f.revenue))}</td>
        <td class="fd-num">৳${esc(fmtMoney(f.cost))}</td>
        <td class="fd-num ${Number(f.vendor_due) > 0 ? 'fd-due' : 'fd-clear'}">৳${esc(fmtMoney(f.vendor_due))}
            ${f.payable_date ? `<small>Due: ${esc(fmtTableDate(parseIso(f.payable_date)))}</small>` : ''}
            <button type="button" class="fdl-eye fdl-flight-eye" data-flight="${esc(f.id)}" title="Open the full record for ${esc(f.flight_number || 'this flight')}"><i class="fas fa-eye" aria-hidden="true"></i></button></td>
    </tr>`;
}


// Which cards are on the BOTTOM row, and where each sits — so the .fdc-up
// hover rule can place an opening card into the cell above its own instead
// of inventing a row below. Measured, not painted: the column count comes
// from auto-fill and nothing in the DOM records where a card landed. Read
// while nothing is hovered; a single-row grid and a FULL bottom row are left
// unmarked (see the bank sheet for the reasoning on each).
function markLastRowCards() {
    const grid = document.getElementById('fdGrid');
    if (!grid || grid.classList.contains('all-expanded')) return;
    const cards = Array.from(grid.querySelectorAll('.fd-card'));
    if (!cards.length) return;
    if (grid.querySelector('.fd-card:hover')) return;

    cards.forEach(c => c.classList.remove('fdc-up'));

    const uniq = v => [...new Set(v)].sort((a, b) => a - b);
    const rows = uniq(cards.map(c => c.offsetTop));
    const cols = uniq(cards.map(c => c.offsetLeft));
    if (rows.length < 2) return;
    const lastTop = rows[rows.length - 1];
    if (cards.filter(c => c.offsetTop === lastTop).length === cols.length) return;

    cards.forEach(c => {
        if (c.offsetTop !== lastTop) {
            c.style.removeProperty('--fdc-col');
            c.style.removeProperty('--fdc-row');
            return;
        }
        c.style.setProperty('--fdc-col', String(cols.indexOf(c.offsetLeft) + 1));
        c.style.setProperty('--fdc-row', String(rows.length - 1));
        c.classList.add('fdc-up');
    });
}

// Paint the primary dashboard before wiring the secondary passenger ledger.
// This guarantees that arriving through the Contract Flight navigation shows
// the cards and table immediately; a later ledger setup issue must never make
// the user click the sort button just to trigger the first render.
function initialiseFlightDashboard() {
    if (!initialiseFlightDashboard.wired) {
        initialiseFlightDashboard.wired = true;
        wireOverallFlightBar();
        wireActiveFlightBar();
        wireDepartedBar();
    }
    populateDashboardAgents();
    renderOverallDashboard();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseFlightDashboard, { once: true });
} else {
    initialiseFlightDashboard();
}
window.addEventListener('pageshow', function (event) {
    if (event.persisted) initialiseFlightDashboard();
});

@include('contract-flights.dashboard-ledger-script')

let fdResizeTimer = null;
window.addEventListener('resize', () => {
    clearTimeout(fdResizeTimer);
    fdResizeTimer = setTimeout(markLastRowCards, 120);
});

</script>
@include('contract-flights.partials.table-sort')
@can('create contract flight')
{{-- Add Flight modal on the dashboard: the same modal, builder and quick-create
     route as Flights & Passengers. operations-script binds .create-btn (opens the
     first passenger card) and validates; this block opens/closes/submits. --}}
<script>
const mfFlights = [];           // operations-script reads it for Edit; nothing to edit here
$(function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
    $('#create_ticket_id').select2({ dropdownParent: $('#createModal'), placeholder: 'Select ticket route...' });
    $('#tkrFromAirport, #tkrToAirport').select2({ dropdownParent: $('#tkrOverlay'), placeholder: '— Select airport —', allowClear: true });
    $('#tkrAirline').select2({ dropdownParent: $('#tkrOverlay'), placeholder: '— Select airline —' });
    $('#tkrVendor').select2({ dropdownParent: $('#tkrOverlay'), placeholder: '— Select vendor —', allowClear: true });
    $('#tkrPortal').select2({ dropdownParent: $('#tkrOverlay'), placeholder: '— Select portal —', allowClear: true });

    $('.create-btn').click(function () {
        $('#createForm')[0].reset();
        $('#createForm .error-message').addClass('hidden');
        $('#create_ticket_id').val(null).trigger('change');
        $('#create_status').val('open');
        $('#createModal').removeClass('hidden');
    });
    // Closes on the ✕ only (owner, 2026-09-22). The backdrop used to close it
    // too, which threw away a part-filled flight on a stray click beside the
    // box — this is a long entry form, so an accidental close costs real work.
    $('.modal-close-create').click(function () {
        $('#createModal').addClass('hidden');
    });
    $('#createSubmit').click(function (e) {
        e.preventDefault();
        if (!validateFlightForm('create')) return;
        $.ajax({
            url: $('#createForm').attr('action'), method: 'POST', data: $('#createForm').serialize(),
            success: function (response) {
                if (response.success) {
                    Swal.fire({ icon: 'success', title: 'Done', text: 'Contract flight created successfully!' });
                    $('#createModal').addClass('hidden');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                }
            },
            error: function (xhr) {
                console.error(xhr.responseText);
                Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create contract flight.' });
            }
        });
    });
});
</script>
@include('contract-flights.partials.ticket-route-quick-create-script')
@include('contract-flights.operations-script')
@endcan
@endsection
