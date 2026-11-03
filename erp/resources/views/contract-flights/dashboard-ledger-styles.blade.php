<style>
    /* ══════════════════════════════════════════════════════════════════════
       PASSENGER LEDGER — the account card and filter bar from Manage Banks
       (banks/dashboard.blade.php: .ledger-header / .lh-* / .ledger-meta /
       .lf-*), transcribed with an fdl- prefix so the two sheets can never
       reach each other. Owner, 2026-09-16: after clicking a flight, the
       marked place is to be the Manage Banks account layout, "pixel to
       pixel same". Every measurement here is the bank sheet's, and the long
       reasoning behind each lives there; this sheet carries the short
       version. If a value changes on the bank page, change it here too, or
       the two drift.

       What each bank slot became:
         mark plate          bank logo               → airline mark, ink-fitted
         name                bank name               → airline
         📍 line             branch                  → route (flags · codes) · airline flight no
         A/C line            account no · type chip  → BATCH CF-nnn · status chip
         Company             owning company          → agent (who brought the batch)
         Current Balance     live balance            → departure, countdown in the qualifier
         Latest Transaction  pill · amount · when    → vendor due · vendor · payable date
         Deposit / Withdraw  green / red             → Agent / Vendor statements
         Print / Reconcile   indigo                  → Manage / Board
         the rail            today in, today out,    → passengers, ticketed, pending,
                             count, opening, debit,    flagged, agent charge,
                             credit, closing           travel cost, profit
         filter bar          search · period · dates → search · state · ticket-purchase dates
       ══════════════════════════════════════════════════════════════════════ */

    /* ── The account card ─────────────────────────────────────────────── */
    .fdl-header {
        position: relative; overflow: hidden; isolation: isolate;
        border-radius: 16px; color: #fff;
        padding: 1rem 1.25rem 0.9rem; margin-bottom: 1rem;
        background: linear-gradient(135deg, #4338ca 0%, #5b4ce0 45%, #6d28d9 100%);
        box-shadow:
            0 26px 50px -30px rgba(67,56,202,0.95),
            0 2px 6px -2px rgba(15,23,42,0.28),
            inset 0 1px 0 rgba(255,255,255,0.22);
        font-family: "Segoe UI Variable Text", "Segoe UI", ui-sans-serif, system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
        font-variant-numeric: tabular-nums;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    .fdl-header::before {
        content: ''; position: absolute; inset: 0; z-index: 0; pointer-events: none;
        background:
            radial-gradient(90% 160% at 100% 0, rgba(255,255,255,0.24), rgba(255,255,255,0) 52%),
            radial-gradient(70% 120% at 0 0, rgba(255,255,255,0.14), rgba(255,255,255,0) 45%),
            radial-gradient(90% 130% at 0 100%, rgba(2,6,23,0.28), rgba(2,6,23,0) 62%);
    }
    .fdl-head-top, .fdl-meta { position: relative; z-index: 1; }

    /* Shared type scale: one label style and one figure style across both bands. */
    .fdl-lbl {
        display: block; font-size: 10px; font-weight: 800; line-height: 1.35;
        text-transform: uppercase; letter-spacing: 0.085em;
        color: rgba(255,255,255,0.88); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .fdl-lbl i { font-size: 9.5px; margin-right: 4px; opacity: 1; }
    .fdl-lbl .fdl-qual { letter-spacing: 0.04em; text-transform: none; opacity: 0.8; font-weight: 700; }
    .fdl-fig {
        display: block; margin-top: 1px; font-size: 1.04rem; font-weight: 800;
        letter-spacing: -0.014em; line-height: 1.22; color: #fff;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .fdl-fig .cur { font-size: 0.8em; font-weight: 700; opacity: 0.85; margin-right: 2px; }
    /* The departure day in the flight cards' red (owner, 2026-09-21); on the
       indigo header a lighter red keeps it readable. The time tag stays white. */
    #fdlDeparture { color: #ef4444; }
    #fdlDeparture .tag { color: #fff; }
    .fdl-fig .tag { font-size: 0.66em; font-weight: 800; letter-spacing: 0.06em; opacity: 0.85; margin-left: 5px; }

    /* ── Band 1 ─────────────────────────────────────────────────────────── */
    .fdl-head-top { display: flex; flex-wrap: wrap; align-items: stretch; row-gap: 12px; }
    .fdl-head-top .fdl-lbl,
    .fdl-head-top .fdl-fig,
    .fdl-head-top .fdl-line,
    .fdl-head-top .fdl-last-note,
    .fdl-head-top .fdl-last-when,
    .fdl-head-top .fdl-co-name,
    .fdl-head-top .fdl-name { text-shadow: 0 1px 2px rgba(2,6,23,0.38); }
    .fdl-head-top .fdl-name { text-shadow: 0 2px 5px rgba(2,6,23,0.34); }
    .fdl-head-top .fdl-lbl { font-weight: 700; letter-spacing: 0.07em; color: rgba(255,255,255,0.96); }
    .fdl-zone { position: relative; min-width: 0; padding: 0 1rem; display: flex; flex-direction: column; justify-content: center; gap: 4px; }
    .fdl-zone:first-child { padding-left: 0; }
    .fdl-zone + .fdl-zone::before {
        content: ''; position: absolute; left: 0; top: 3px; bottom: 3px; width: 1px;
        background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0.36) 48%, rgba(255,255,255,0));
    }
    /* justify-content: flex-start, not the column's centre — .fdl-zone centres
       along its own axis, and this zone's axis runs sideways, so a short
       airline name ("Airline pending") was floating to the middle of the
       zone. The bank statement never shows it because a bank's name fills
       the zone; its landing card needed the same fix (.ca-header). */
    /* The identity zone takes every pixel the other zones leave: the route
       line — flags, codes, airline flight number — must read whole (owner,
       2026-09-16). Basis 320 so it wins the fight before anything clips.
       It does not GROW (owner, 2026-10-01: "many zones") — at a wide window it
       used to take ~500px for ~280px of text, leaving an empty band while the
       Agent and Vendor Due lines were cut short. Spare width goes to them. */
    .fdl-zone-id { flex: 0 1 320px; flex-direction: row; align-items: center; justify-content: flex-start; gap: 0.8rem; }
    .fdl-idcol { flex: 1 1 auto; }
    .fdl-zone-co { flex: 1 1 200px; }
    /* The agents zone's 150px (120 basis + 24 padding + hairline) is borrowed — 50 from the id zone (which
       keeps 270 so the batch number and route stay whole), 60 from the Agent
       zone, whose agent line it replaces, 40 from Vendor Due — so the
       band's total basis is unchanged and showing it can never wrap the actions.
       Measured: no wrap and no height change at 1517 / 1365 / 1241 / 1130. */
    .fdl-zone-agents { flex: 1 1 120px; padding: 0 .75rem; }   /* 120 + its own 24px padding = the 150 it borrows */
    .fdl-head-top:has(.fdl-zone-agents:not([hidden])) .fdl-zone-id { flex-basis: 270px; }
    .fdl-head-top:has(.fdl-zone-agents:not([hidden])) .fdl-zone-co { flex-basis: 140px; }
    .fdl-head-top:has(.fdl-zone-agents:not([hidden])) .fdl-zone-last { flex-basis: 190px; }
    /* The agents list already names everyone, so the single Agent line beside
       it would only repeat the first name (owner: "redundancy"). Departure
       stays; the zone is narrower by the same amount Vendor Due gains. */
    .fdl-head-top:has(.fdl-zone-agents:not([hidden])) .fdl-zone-co > div:first-child { display: none; }
    .fdl-zone-agents[hidden] { display: none; }
    .fdl-agents { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1px 14px; margin-top: 1px; }
    .fdl-agents.is-two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .fdl-agents a { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        color: #fff; font-size: .72rem; font-weight: 600; line-height: 1.25; text-decoration: none; text-shadow: 0 1px 2px rgba(2,6,23,0.38); }
    .fdl-agents a i { font-size: .6rem; opacity: .75; margin-right: 5px; }
    .fdl-agents a:hover { text-decoration: underline; text-underline-offset: 2px; }
    .fdl-zone-last { flex: 1 1 230px; }
    /* Four buttons as a 2×2 block, not a strip (owner, 2026-09-16): half the
       width, which is what gives the route line its room. Each column is
       as wide as its widest label, so the block reads as one control. */
    .fdl-actions { margin-left: auto; padding-left: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 6px; align-content: center; align-self: center; }

    /* The bank page's .bank-btn, with the lift its action row gives it. */
    .fdl-btn {
        display: flex; align-items: center; justify-content: center; gap: 4px;
        padding: 6px 11px; border-radius: 7px; font-size: 12px; font-weight: 600;
        border: none; cursor: pointer; text-decoration: none;
    }
    .fdl-btn:hover { text-decoration: none; }
    .fdl-btn-alt { background: #eef2ff; color: #4338ca; }
    .fdl-btn-alt:hover { background: #c7d2fe; color: #4338ca; }
    .fdl-btn-in { background: #dcfce7; color: #15803d; }
    .fdl-btn-in:hover { background: #bbf7d0; color: #15803d; }
    .fdl-btn-out { background: #fee2e2; color: #b91c1c; }
    .fdl-btn-out:hover { background: #fecaca; color: #b91c1c; }
    /* Every button goes somewhere: Agent / Vendor open the party's own
       ledger when the batch has one, and the Agent / Vendor ledger list when
       it does not (owner: "agent and vendor have to be clickable"). .is-list
       marks the second case so the tooltip can say so; nothing is disabled. */
    .fdl-btn.is-list { opacity: 0.85; }
    .fdl-actions .fdl-btn {
        box-shadow: 0 10px 18px -11px rgba(2,6,23,0.85), inset 0 1px 0 rgba(255,255,255,0.45);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .fdl-actions .fdl-btn:hover { transform: translateY(-1px); box-shadow: 0 13px 22px -11px rgba(2,6,23,0.9), inset 0 1px 0 rgba(255,255,255,0.5); }
    .fdl-actions .fdl-btn:active { transform: translateY(0); }

    /* The mark plate: the bank's open frame with no fill, so the card's
       gradient runs behind the mark — at 64px, not the bank's 92 (owner,
       2026-09-16: "a bit compacted"; the whole card is the bank's at about
       85%). The WIDTH is the one thing that differs in kind — the airline
       files are wordmarks as wide as 3.5:1, and pinned to a square they
       came out as a sliver, so fitLedgerMark() lets the plate grow to 96px
       for a wide mark. A roundel stays 64 square. */
    /* A white floating tile with the mark inside (owner, 2026-10-01: "full
       white floating box, there logo — more standard look"). The tile's own
       padding is read by layoutLedgerMark(), so changing it here is enough. */
    .fdl-avatar {
        flex: 0 0 auto; width: 64px; height: 64px;
        box-sizing: border-box;
        display: grid; place-items: center; padding: 7px; overflow: visible; position: relative;
        background: #fff; border: 0; border-radius: 14px;
        align-self: flex-start; margin-top: 2px;   /* a little higher (owner, 2026-10-01) */
        box-shadow: 0 8px 18px rgba(2,6,23,0.28), 0 0 0 1px rgba(255,255,255,0.6);
    }
    .fdl-avatar.is-mono { padding: 7px; }
    .fdl-badge { position: absolute; right: -9px; bottom: -9px; height: 26px; min-width: 26px; max-width: 48px;
        box-sizing: border-box; padding: 3px 5px; border-radius: 13px; background: #fff;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 3px 8px rgba(2,6,23,0.30), 0 0 0 2px rgba(255,255,255,0.9); }
    .fdl-badge[hidden] { display: none; }
    /* #id, so the tile's own img rules (.fdl-avatar img / .fdl-avatar.is-fit img —
       100% box, then absolute at natural size once the mark is ink-fitted)
       can never reach the badge's image: on live that blew Pegasus up across
       the header (2026-10-01). */
    #fdlBadge img { position: static; inset: auto; height: 18px; width: auto; max-width: 38px; max-height: 18px; object-fit: contain; display: block; }
    .fdl-badge-mono { display: none; font-size: 9px; font-weight: 800; color: #4338ca; }
    #fdlBadge.is-mono img { display: none; }
    .fdl-badge.is-mono .fdl-badge-mono { display: block; }
    .fdl-flightno[hidden] { display: none; }
    .fdl-flightno i { font-size: .62rem; opacity: .8; }
    .fdl-flightno.is-unset { opacity: .6; font-style: italic; cursor: pointer; }
    .fdl-flightno.is-unset:hover { opacity: .9; text-decoration: underline; }
    .fdl-crop { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
    .fdl-avatar img { width: 100%; height: 100%; object-fit: contain; display: block; }
    /* Zoomed to the ink, the same way the cards are: the crop window is sized
       by script and the image positioned inside it so only the ink shows. */
    .fdl-avatar.is-fit .fdl-crop { position: relative; overflow: hidden; width: auto; height: auto; }
    .fdl-avatar.is-fit img { position: absolute; width: auto; height: auto; max-width: none; max-height: none; }
    .fdl-avatar .fdl-mono { display: none; font-size: 1.3rem; font-weight: 800; letter-spacing: 0.01em; color: #4338ca; }
    .fdl-avatar > i { display: none; font-size: 1.6rem; color: #6366f1; }
    .fdl-avatar.is-mono .fdl-crop { display: none; }
    .fdl-avatar.is-mono .fdl-mono { display: block; }
    /* No airline on the batch yet: the plane glyph, not a meaningless monogram. */
    .fdl-avatar.is-mono.is-glyph .fdl-mono { display: none; }
    .fdl-avatar.is-mono.is-glyph > i { display: block; }

    .fdl-idcol { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
    .fdl-name { font-size: 1.18rem; font-weight: 800; letter-spacing: -0.021em; line-height: 1.12; margin: 0; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fdl-line { font-size: 0.78rem; font-weight: 500; line-height: 1.3; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fdl-line i { font-size: 0.72rem; width: 12px; margin-right: 3px; opacity: 1; }
    .fdl-line .fdl-k { font-weight: 700; color: rgba(255,255,255,0.8); letter-spacing: 0.05em; font-size: 0.68rem; margin-right: 5px; text-transform: uppercase; }
    .fdl-ac { font-family: ui-monospace, SFMono-Regular, "Cascadia Mono", Menlo, Consolas, monospace; font-size: 0.77rem; font-weight: 500; letter-spacing: 0.03em; color: #ffffff; }
    .fdl-type {
        display: inline-block; margin-left: 7px; padding: 1px 8px; border-radius: 999px;
        font-size: 0.62rem; font-weight: 700; letter-spacing: 0.045em; text-transform: uppercase;
        color: #ffffff; background: rgba(255,255,255,0.20);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.28);
        vertical-align: 1px;
    }

    .fdl-co { display: flex; align-items: center; gap: 9px; min-width: 0; }
    .fdl-co .ci {
        flex: 0 0 22px; width: 22px; height: 22px; border-radius: 7px;
        display: grid; place-items: center; font-size: 11px; color: #fff;
        background: rgba(255,255,255,0.18);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.30), inset 0 1px 0 rgba(255,255,255,0.35);
    }
    .fdl-co-name { font-size: 0.86rem; font-weight: 700; letter-spacing: -0.004em; color: #fff; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    /* The agent and the vendor are links to their statements — white, like
       the text they sit in; underlined only under the pointer. */
    .fdl-co-name a, .fdl-last-note a { color: inherit; text-decoration: none; }
    .fdl-co-name a:hover, .fdl-last-note a:hover { color: inherit; text-decoration: underline; text-underline-offset: 2px; }

    .fdl-last-row { display: flex; align-items: center; gap: 9px; min-width: 0; }
    .fdl-pill {
        flex: 0 0 auto; display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px 3px 8px; border-radius: 999px;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.01em; white-space: nowrap;
    }
    .fdl-pill i { font-size: 0.62rem; }
    .fdl-pill-in  { background: rgba(4,47,32,0.55);    color: #34f5a0; box-shadow: inset 0 0 0 1px rgba(52,245,160,0.55); }
    .fdl-pill-out { background: rgba(62,8,22,0.55);    color: #ffa8b6; box-shadow: inset 0 0 0 1px rgba(255,168,182,0.55); }
    .fdl-pill-none{ background: rgba(2,6,23,0.35);     color: rgba(255,255,255,0.82); box-shadow: inset 0 0 0 1px rgba(255,255,255,0.28); }
    .fdl-last-amt { font-size: 0.95rem; font-weight: 800; letter-spacing: -0.014em; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fdl-last-amt .cur { font-size: 0.8em; font-weight: 700; opacity: 0.85; margin-right: 2px; }
    .fdl-amt-in  { color: #34f5a0; }
    .fdl-amt-out { color: #ffa8b6; }
    .fdl-last-note { font-size: 0.77rem; font-weight: 500; line-height: 1.3; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* Blocks, not a grid: the header's fit-to-row measure reads max-content,
       and a grid here made it under-measure so the actions wrapped at 1130. */
    .fdl-last-note .fdl-tv, .fdl-last-note .fdl-tv-k, .fdl-last-note .fdl-tv a { display: block; }
    .fdl-last-note .fdl-tv-k { opacity: .8; font-size: .7rem; }
    .fdl-last-note .fdl-tv a { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fdl-last-when { font-size: 0.72rem; font-weight: 500; line-height: 1.3; color: rgba(255,255,255,0.9); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* ── Band 2 — the figures rail ──────────────────────────────────────── */
    .fdl-meta {
        display: flex; flex-wrap: wrap; row-gap: 0.6rem;
        margin-top: 0.85rem; padding: 0.6rem 0.2rem;
        border-radius: 12px;
        background: linear-gradient(180deg, #ffffff 0%, #f6f8fc 100%);
        box-shadow:
            0 10px 22px -12px rgba(2,6,23,0.55),
            0 2px 4px -2px rgba(2,6,23,0.22),
            inset 0 0 0 1px rgba(15,23,42,0.06),
            inset 0 1px 0 #ffffff;
    }
    .fdl-meta > div { position: relative; flex: 1 1 auto; min-width: 0; padding: 0 0.7rem; }
    /* Solid, full height, the card's own indigo — see the bank sheet for why
       this one hairline does not fade. */
    .fdl-meta > div + div::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 1px;
        background: rgba(79,70,229,0.70);
    }
    .fdl-meta .fdl-lbl { font-size: 9px; letter-spacing: 0.06em; color: #64748b; }
    .fdl-meta .fdl-lbl i { opacity: 1; }
    .fdl-meta .fdl-fig { color: #0f172a; }
    .fdl-meta .fdl-fig .cur { opacity: 0.5; font-weight: 700; }
    .fdl-meta .fdl-fig .tag { opacity: 0.55; }
    /* Profit is the figure the whole rail builds towards: it anchors the right
       edge and reads a size larger, in the bank's closing-balance amber — or
       red, when the batch is being run at a loss. */
    .fdl-meta > div.is-close { text-align: right; flex: 1.2 1 auto; }
    .fdl-meta > div.is-close .fdl-fig { font-size: 1.2rem; color: #b45309; }
    .fdl-meta > div.is-close .fdl-lbl { color: #b45309; opacity: 0.85; }
    .fdl-meta > div.is-close.is-loss .fdl-fig,
    .fdl-meta > div.is-close.is-loss .fdl-lbl { color: #b91c1c; }
    .fdl-meta > div.is-in  .fdl-fig, .fdl-meta > div.is-in  .fdl-lbl { color: #15803d; }
    .fdl-meta > div.is-out .fdl-fig, .fdl-meta > div.is-out .fdl-lbl { color: #b91c1c; }
    /* Only the figure greys out; the label keeps its 4.6:1 slate. */
    .fdl-meta > div.is-zero .fdl-fig { color: #64748b; }

    @media (max-width: 1180px) {
        .fdl-zone-co, .fdl-zone-last { flex: 1 1 220px; }
        .fdl-actions { padding-left: 0; margin-left: auto; }
    }
    @media (max-width: 720px) {
        .fdl-header { padding: 0.9rem 0.9rem 0.8rem; }
        .fdl-zone { padding: 0; }
        .fdl-zone + .fdl-zone::before { display: none; }
        .fdl-actions { padding-left: 0; }
        .fdl-meta > div { flex: 1 1 44%; }
    }

    /* ── The filter bar (the bank's .lf-bar) ────────────────────────────── */
    .fdl-bar {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 11px 14px; background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .fdl-search { position: relative; flex: 1 1 260px; min-width: 200px; }
    .fdl-search > i {
        position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 12px; pointer-events: none;
    }
    .fdl-search input {
        width: 100%; height: 34px; padding: 0 30px 0 30px;
        border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;
        font-size: 12.5px; color: #1e293b; outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .fdl-search input::placeholder { color: #a3adbb; }
    .fdl-search input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    .fdl-clear {
        position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
        border: 0; background: none; padding: 4px; cursor: pointer;
        color: #cbd5e1; font-size: 12px; line-height: 1; display: none;
    }
    .fdl-clear:hover { color: #64748b; }
    .fdl-search.has-q .fdl-clear { display: block; }

    /* flex:0 0 auto — the group must never shrink; the search gives way. */
    .fdl-seg { display: flex; flex: 0 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; }
    .fdl-seg button {
        border: 0; border-left: 1px solid #e2e8f0; background: none;
        padding: 0 11px; height: 34px; cursor: pointer;
        font-size: 11.5px; font-weight: 600; color: #64748b; white-space: nowrap;
        transition: background .15s, color .15s;
    }
    .fdl-seg button:first-child { border-left: 0; }
    .fdl-seg button:hover { background: #f1f5f9; color: #334155; }
    .fdl-seg button.is-on { background: #eef2ff; color: #4338ca; font-weight: 700; }

    .fdl-dates { display: flex; flex: 0 0 auto; align-items: center; gap: 6px; }
    .fdl-dates span { font-size: 11px; color: #94a3b8; }
    .fdl-dates .fdl-date-kind { color: #64748b; font-weight: 700; white-space: nowrap; }
    /* A dd/mm/yyyy box and the real calendar, side by side in one border —
       see the bank sheet's .lf-date block for why the date input is stripped
       down to its calendar button. */
    .fdl-date {
        position: relative; display: inline-flex; align-items: center;
        height: 34px; padding-right: 5px; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 8px;
        transition: border-color .15s, background .15s;
    }
    .fdl-date:focus-within { border-color: #6366f1; }
    .fdl-dates input.fdl-date-text {
        width: 88px; height: 32px; padding: 0 2px 0 9px;
        border: 0; background: none; outline: none;
        font-size: 12px; color: #334155;
    }
    .fdl-date.is-bad { border-color: #fca5a5; background: #fef2f2; }
    .fdl-dates input.fdl-date-text.is-bad { color: #b91c1c; }
    .fdl-dates input.fdl-date-native {
        width: 22px; height: 22px; flex: 0 0 auto;
        margin: 0; padding: 0; border: 0; background: none;
        color: transparent; overflow: hidden; cursor: pointer;
    }
    .fdl-date-native::-webkit-datetime-edit,
    .fdl-date-native::-webkit-inner-spin-button,
    .fdl-date-native::-webkit-clear-button { display: none; }
    .fdl-date-native::-webkit-calendar-picker-indicator {
        width: 22px; height: 22px; margin: 0; padding: 0;
        opacity: 0.5; cursor: pointer; transition: opacity .15s;
    }
    .fdl-date:hover .fdl-date-native::-webkit-calendar-picker-indicator,
    .fdl-date-native:focus::-webkit-calendar-picker-indicator { opacity: 0.9; }
    .fdl-dates.is-open .fdl-date { border-color: #c7d2fe; background: #f5f6ff; }
    .fdl-go {
        height: 34px; padding: 0 12px; border: 0; border-radius: 8px; cursor: pointer;
        background: #4f46e5; color: #fff; font-size: 11.5px; font-weight: 700;
    }
    .fdl-go:hover { background: #4338ca; }
    .fdl-reset { height: 34px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #64748b; cursor: pointer; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
    .fdl-reset:hover { border-color: #cbd5e1; background: #f8fafc; color: #334155; }
    .fdl-print {
        height: 34px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 8px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        background: #fff; color: #475569; font-size: 11.5px; font-weight: 700;
        transition: background .15s, color .15s, border-color .15s;
    }
    .fdl-print:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; text-decoration: none; }
    .fdl-print i { font-size: 11px; }

    @media (min-width: 1181px) {
        #fdLedger .fdl-bar { flex-wrap: nowrap; }
        #fdLedger .fdl-search { min-width: 180px; }
        #fdLedger .fdl-dates { min-width: 0; }
    }

    /* Says what the bar did to the list. Only rendered while something
       filters, because a count of everything is not information. */
    .fdl-count {
        flex-shrink: 0; font-size: 11px; font-weight: 700; color: #4338ca;
        background: #eef2ff; border-radius: 20px; padding: 4px 10px; white-space: nowrap;
    }
    .fdl-count.is-none { color: #b91c1c; background: #fef2f2; }

    /* The bank's "Loading…" / "Could not load" slot under the bar. */
    .fdl-message { padding: 20px; color: #64748b; font-size: 0.83rem; }

    #fdLedger .fdl-header[hidden], #fdlPrint[hidden] { display: none; }
    /* One journey belongs to one passenger row. The strong separator makes
       that grouping unambiguous even when several passengers are listed. */
    /* The border is dropped only while the journey row is there to carry it.
       With journeys hidden there is nothing underneath, and zeroing it left
       two people running together as one block (owner, 2026-09-22) — so the
       :not() lets .fd-table's ordinary 1px separator come back, the same one
       every other table on the page uses. Do not paint a border here
       instead: that would hard-code the shared colour in a second place. */
    #fdPassengerTable:not(.fdl-journeys-off) .fdl-passenger-row td { border-bottom: 0; }
    /* Status button off → journey strips hidden */
    #fdPassengerTable.fdl-journeys-off .fdl-route-row { display: none; }
    /* One row clicked (2026-10-03): its strip goes the other way from the rest. */
    #fdPassengerTable.fdl-journeys-off .fdl-route-row.is-flipped { display: table-row; }
    #fdPassengerTable:not(.fdl-journeys-off) .fdl-route-row.is-flipped { display: none; }
    #fdPassengerTable.fdl-journeys-off .fdl-passenger-row:has(+ .fdl-route-row.is-flipped) td { border-bottom: 0; }
    #fdPassengerTable .fdl-passenger-row:has(+ .fdl-route-row) { cursor: pointer; }
    #fdPassengerTable .fdl-passenger-status { background: linear-gradient(90deg, rgba(224,231,255,.72), rgba(238,242,255,.30)); box-shadow: inset 3px 0 0 #818cf8; }
    #fdPassengerTable .fdl-passenger-status span { display: inline-flex; align-items: center; gap: 5px; min-height: 22px; padding: 3px 8px; border: 1px solid #a5b4fc; border-radius: 999px; background: #e0e7ff; color: #3730a3; box-shadow: 0 1px 2px rgba(79,70,229,.12); font-size: .69rem; font-weight: 800; line-height: 1.15; }
    #fdPassengerTable .fdl-passenger-status span::before { width: 6px; height: 6px; border-radius: 50%; background: #6366f1; content: ''; box-shadow: 0 0 0 2px rgba(99,102,241,.14); }
    #fdPassengerTable .fdl-passenger-status small { display: block; margin-top: 4px; color: #e11d48; font-size: .64rem; font-weight: 600; line-height: 1.2; }
    /* the same "Status" button as Flights & Passengers */
    .fdl-status-btn { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 14px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #475569; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .15s; }
    .fdl-status-btn i { font-size: 11px; }
    .fdl-status-btn:hover { border-color: #c7d2fe; color: #4338ca; }
    .fdl-status-btn[aria-pressed="true"] { border-color: #c7d2fe; background: #eef2ff; color: #4338ca; }
    #fdPassengerTable .fdl-route-row td {
        padding: 0 8px 10px; border-bottom: 2px solid #6366f1;
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    }
    /* Each passenger block opens on a tinted band, so the boundary between
       two people reads at a glance rather than as one long list. */
    #fdPassengerTable .fdl-passenger-row td { padding-top: 12px; }
    /* A passenger and their journey are two <tr>s but one thing, so hovering
       either lights both. The route row can't reach back to its passenger
       row with a plain sibling selector, hence :has(). */
    #fdPassengerTable .fdl-passenger-row td, #fdPassengerTable .fdl-route-row td { transition: background-color .15s ease, box-shadow .15s ease; }
    #fdPassengerTable .fdl-passenger-row:hover td,
    #fdPassengerTable .fdl-passenger-row:hover + .fdl-route-row td,
    #fdPassengerTable .fdl-passenger-row:has(+ .fdl-route-row:hover) td,
    #fdPassengerTable .fdl-route-row:hover td { background: #eef2ff; }
    #fdPassengerTable .fdl-passenger-row:hover td:first-child,
    #fdPassengerTable .fdl-passenger-row:has(+ .fdl-route-row:hover) td:first-child,
    #fdPassengerTable .fdl-passenger-row:hover + .fdl-route-row td,
    #fdPassengerTable .fdl-route-row:hover td { box-shadow: inset 3px 0 0 #6366f1; }
    /* Sits under the profit figure on the right, pulled up into the blank
       space the two-line money cell leaves beneath itself, so the journey
       row starts right where the passenger row's text ends. */
    .fdl-route-head {
        display: flex; align-items: center; justify-content: flex-end; gap: 8px;
        margin-top: -26px; padding: 0 3px 4px;
        color: #64748b; font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .055em;
    }
    .fdl-route-head > span i { margin-right: 5px; color: #6366f1; }
    .fdl-route-head strong {
        padding: 3px 8px; border-radius: 999px; background: #eef2ff;
        color: #4338ca; font-size: 10px; letter-spacing: .02em; text-transform: none;
    }
    .fdl-route-flag {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 8px; border-radius: 999px; background: #fff1f2;
        color: #be123c; font-size: 10px; letter-spacing: .01em; text-transform: none;
    }
    .fdl-route-edit {
        display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px;
        margin-left: 2px; border: 1px solid #c7d2fe; border-radius: 6px; background: #fff; color: #4f46e5;
        font-size: 9px; cursor: pointer; transition: background-color .15s ease, color .15s ease;
    }
    .fdl-route-edit:hover, .fdl-route-edit:focus-visible { background: #4f46e5; color: #fff; outline: none; }

    /* Stage editor styles: partials/stage-editor (shared with Flights & Passengers). */

    /* The journey card — the same as Flights & Passengers (2026-10-03). */
@include('contract-flights.partials.journey-card-styles')
    #fdPassengerTable .fdl-route-row td { padding: 10px 12px 14px; border-bottom: 1px solid #e2e8f0; background: #fff; }
    #fdPassengerTable .fdl-route-row:hover td, #fdPassengerTable .fdl-passenger-row:hover + .fdl-route-row td { background: #f8fafc; }

    .fdl-route-scroll {
        overflow-x: auto; padding: 14px 12px 10px; border: 1px solid #e8eaf8;
        border-radius: 12px; background: linear-gradient(120deg, #f8fafc, #f5f3ff 65%, #f0fdf4);
        scrollbar-width: thin; scrollbar-color: #c7d2fe transparent;
    }
    .fdl-route {
        position: relative; display: flex; align-items: flex-start; width: 100%; min-width: 0;
        margin: 0; padding: 0; list-style: none;
    }
    .fdl-route::before, .fdl-route::after {
        content: ''; position: absolute; top: 12px; left: 13px; height: 2px; pointer-events: none; z-index: 0;
    }
    .fdl-route::before { right: 13px; background: #dbe3ef; }
    .fdl-route::after { width: max(0px, calc(var(--fdl-progress) - 13px)); background: #22c55e; }
    .fdl-step { position: relative; flex: .8 1 0; min-width: 0; text-align: center; color: #94a3b8; }
    .fdl-step.is-focus { flex-grow: 1.2; }
    .fdl-step.is-current {
        flex-grow: 1.35; position: relative; z-index: 5;
    }
    .fdl-step-pin {
        position: relative; z-index: 1; display: inline-flex; align-items: center; justify-content: center;
        width: 25px; height: 25px; border: 2px solid #cbd5e1; border-radius: 50%;
        background: #fff; color: #94a3b8; box-shadow: 0 0 0 3px #f8fafc;
    }
    .fdl-step-pin i { font-size: 8px; }
    .fdl-step.is-focus .fdl-step-pin i { font-size: 10px; }
    .fdl-step.is-done .fdl-step-pin { border-color: #22c55e; background: #dcfce7; color: #15803d; }
    /* a ticket slot's Ticket Orders state */
    .fdl-step.is-hold .fdl-step-pin { border-color: #38bdf8; background: #e0f2fe; color: #0369a1; }
    .fdl-step.is-hold .fdl-step-copy b, .fdl-step.is-hold .fdl-step-copy small { color: #0369a1; }
    .fdl-step.is-pending .fdl-step-pin { border-color: #cbd5e1; background: #f1f5f9; color: #475569; }
    .fdl-step.is-pending .fdl-step-copy small { color: #64748b; }
    .fdl-step.is-expired .fdl-step-pin { border-color: #fca5a5; background: #fee2e2; color: #b91c1c; }
    .fdl-step.is-expired .fdl-step-copy small { color: #b91c1c; }
    .fdl-step.is-skipped .fdl-step-pin { border-style: dashed; background: #f8fafc; color: #cbd5e1; }
    .fdl-step.is-skipped .fdl-step-copy b { color: #94a3b8; }
    .fdl-step.is-current .fdl-step-pin {
        border-color: #4f46e5; background: #4f46e5; color: #fff;
        box-shadow: 0 0 0 4px #e0e7ff, 0 4px 10px rgba(79,70,229,.25);
        transform: translateY(-7px) scale(1.4);
        z-index: 3;
    }
    .fdl-step-copy { display: block; margin: 8px auto 0; max-width: 100%; padding: 2px; line-height: 1.3; }
    .fdl-step-copy b { display: block; color: #64748b; font-size: 6.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fdl-step.is-focus .fdl-step-copy { max-width: 105px; }
    .fdl-step.is-focus .fdl-step-copy b { font-size: 8px; white-space: normal; overflow: visible; }
    .fdl-step.is-done .fdl-step-copy b { color: #15803d; }
    .fdl-step.is-current .fdl-step-copy b {
        color: #3730a3; font-size: 10px; white-space: normal;
        overflow: visible; overflow-wrap: anywhere; text-overflow: clip;
    }
    #fdPassengerTable .fdl-step-copy small { margin-top: 2px; color: #a3adbb; font-size: 7px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #fdPassengerTable .fdl-step.is-focus .fdl-step-copy small { font-size: 7.5px; overflow: visible; }
    #fdPassengerTable .fdl-step.is-current .fdl-step-copy small {
        display: block; color: #4338ca; font-size: 8px; font-weight: 700;
        white-space: normal; overflow: visible; line-height: 1.3;
    }

    .fdl-route::after {
        background: linear-gradient(90deg, #22c55e, #34d399, #22c55e);
        transform-origin: left; animation: fdl-route-reveal .85s ease-out both;
    }
    .fdl-step-pin {
        cursor: inherit;
        transition: transform .22s ease, box-shadow .22s ease, background .22s ease, border-color .22s ease;
    }
    .fdl-step-copy {
        position: relative; z-index: 4; border-radius: 5px;
        transition: transform .25s ease, background-color .25s ease, box-shadow .25s ease;
    }
    .fdl-stage-action {
        display: block; width: 100%; padding: 0; border: 0; background: transparent;
        color: inherit; font: inherit; text-align: center; cursor: pointer; border-radius: 8px;
        height: auto; overflow: visible; white-space: normal; line-height: 1.3;
    }
    .fdl-stage-action:disabled { cursor: default; }
    .fdl-stage-action:focus-visible { outline: 2px solid #818cf8; outline-offset: 5px; }
    .fdl-step-copy b, #fdPassengerTable .fdl-step-copy small { transition: color .25s ease; }
    .fdl-step:hover .fdl-step-copy, .fdl-step:focus-within .fdl-step-copy {
        transform: translateY(-3px); background: #e0e7ff;
        box-shadow: 0 0 0 3px #e0e7ff, 0 5px 10px #4f46e51a;
    }
    .fdl-step:hover .fdl-step-copy b, .fdl-step:focus-within .fdl-step-copy b,
    #fdPassengerTable .fdl-step:hover .fdl-step-copy small,
    #fdPassengerTable .fdl-step:focus-within .fdl-step-copy small { color: #4338ca; }
    .fdl-step.is-done:hover .fdl-step-copy, .fdl-step.is-done:focus-within .fdl-step-copy {
        background: #dcfce7; box-shadow: 0 0 0 3px #dcfce7, 0 5px 10px #16a34a1a;
    }
    .fdl-step.is-done:hover .fdl-step-copy b, .fdl-step.is-done:focus-within .fdl-step-copy b,
    #fdPassengerTable .fdl-step.is-done:hover .fdl-step-copy small,
    #fdPassengerTable .fdl-step.is-done:focus-within .fdl-step-copy small { color: #166534; }
    .fdl-step:active .fdl-step-copy { transform: translateY(-1px); }
    .fdl-step.is-current .fdl-step-pin { animation: fdl-current-pulse 2.8s ease-in-out infinite; }
    /* The lifted pin must sit over its neighbours' labels and the line. */
    .fdl-step:hover, .fdl-step:focus-within { z-index: 20; }
    .fdl-step.is-current { z-index: 15; }
    .fdl-step:hover .fdl-step-pin, .fdl-step-pin:focus-visible {
        transform: translateY(-5px) scale(1.2); border-color: #818cf8;
        box-shadow: 0 0 0 5px #e0e7ff, 0 8px 16px rgba(79,70,229,.18);
        outline: 2px solid transparent;
    }
    .fdl-step.is-current:hover .fdl-step-pin, .fdl-step.is-current .fdl-step-pin:focus-visible {
        transform: translateY(-9px) scale(1.5); animation: none;
    }
    .fdl-step.is-done:hover .fdl-step-pin, .fdl-step.is-done .fdl-step-pin:focus-visible {
        border-color: #16a34a; box-shadow: 0 0 0 5px #dcfce7, 0 8px 16px #16a34a22;
    }
    @keyframes fdl-route-reveal { from { transform: scaleX(0); } to { transform: scaleX(1); } }
    @keyframes fdl-current-pulse {
        0%, 100% { box-shadow: 0 0 0 4px #e0e7ff, 0 4px 10px #4f46e540; }
        50% { box-shadow: 0 0 0 8px #e0e7ff55, 0 5px 18px #4f46e550; }
    }
    /* No clipping at desktop, so a lifted pin draws above the strip's edge
       instead of being cut by it; the strip only becomes a scroller at the
       narrow breakpoint where it has to. */
    .fdl-route-scroll {
        transition: border-color .25s ease, box-shadow .25s ease;
        overflow: visible;
    }
    .fdl-route-scroll:hover, .fdl-route-scroll:focus-within {
        border-color: #c7d2fe; box-shadow: inset 0 0 24px #6366f108, 0 3px 12px #6366f10d;
    }
    .fdl-route::after {
        background: linear-gradient(90deg, #22c55e 0%, #22c55e 35%, #bbf7d0 50%, #22c55e 65%, #22c55e 100%);
        background-size: 200% 100%;
        animation: fdl-route-reveal .85s ease-out both, fdl-route-flow 3s linear infinite;
    }
    .fdl-step-pin i {
        animation: fdl-icon-enter .5s cubic-bezier(.2,.8,.2,1) var(--fdl-step-delay, 0ms) backwards;
        transition: transform .3s cubic-bezier(.34,1.56,.64,1);
    }
    .fdl-step:hover .fdl-step-pin i, .fdl-step:focus-within .fdl-step-pin i {
        transform: rotate(-12deg) scale(1.18);
    }
    .fdl-step-pin::after {
        content: ''; position: absolute; inset: -5px; border: 1px solid currentColor;
        border-radius: inherit; opacity: 0; pointer-events: none;
    }
    .fdl-step:hover .fdl-step-pin::after, .fdl-step:focus-within .fdl-step-pin::after {
        animation: fdl-hover-ring 1.4s ease-out infinite;
    }
    .fdl-step .fdl-step-pin:active {
        transform: translateY(-1px) scale(.92); transition-duration: .1s;
    }
    .fdl-step.is-current .fdl-step-pin:active { transform: translateY(-5px) scale(1.15); }
    @keyframes fdl-icon-enter {
        from { opacity: 0; transform: translateY(8px) scale(.45); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes fdl-route-flow { from { background-position: 200% 0; } to { background-position: 0 0; } }
    @keyframes fdl-hover-ring {
        from { opacity: .5; transform: scale(.85); }
        to { opacity: 0; transform: scale(1.55); }
    }
    /* ── Strip hover ──
       The pins nearest the pointer swell the way a dock does (--fdl-near is
       0..1, set by the strip's pointer tracker). The origin is the pin's own
       centre, so a pin grows in place on the line rather than lifting off it.
       The segment leading into the hovered stage lights, a "Set current" hint shows what a click does, and
       the runway background drifts while the strip is hovered. */
    .fdl-stage-action {
        /* The transform makes the button its own stacking context, so the
           pin's z-index can no longer climb past the line — the button itself
           has to sit above it. */
        position: relative; z-index: 2;
        transform-origin: 50% 12.5px;
        transform: scale(calc(1 + var(--fdl-near, 0) * .18));
        transition: transform .18s ease-out;
        will-change: transform;
    }
    .fdl-step:not(.is-current) .fdl-stage-action:not(:disabled) .fdl-step-copy::after {
        content: 'Set current'; display: block; margin-top: 2px; font-size: 6.5px; font-weight: 800;
        letter-spacing: .06em; text-transform: uppercase; color: #4f46e5;
        max-height: 0; opacity: 0; overflow: hidden; transform: translateY(-3px);
        transition: max-height .2s ease, opacity .2s ease, transform .2s ease;
    }
    .fdl-step:not(.is-current):hover .fdl-stage-action:not(:disabled) .fdl-step-copy::after,
    .fdl-step:not(.is-current) .fdl-stage-action:not(:disabled):focus-visible .fdl-step-copy::after {
        max-height: 12px; opacity: 1; transform: translateY(0);
    }
    .fdl-route-scroll { background-size: 200% 100%; transition: border-color .25s ease, box-shadow .25s ease, background-position 1.2s ease; }
    .fdl-route-scroll:hover { background-position: 100% 0; }
    .fdl-step::before {
        content: ''; position: absolute; top: 12px; left: -50%; right: 50%; height: 2px;
        background: #818cf8; opacity: 0; transform: scaleX(.3); transform-origin: right;
        transition: opacity .25s ease, transform .35s cubic-bezier(.22,1,.36,1); pointer-events: none;
    }
    .fdl-step:first-child::before { display: none; }
    .fdl-step:hover::before, .fdl-step:focus-within::before { opacity: .9; transform: scaleX(1); }
    .fdl-step.is-done:hover::before, .fdl-step.is-done:focus-within::before { background: #16a34a; }

    @media (prefers-reduced-motion: reduce), print {
        .fdl-step-copy, .fdl-step-copy b, #fdPassengerTable .fdl-step-copy small { transition: none; }
        .fdl-step:hover .fdl-step-copy, .fdl-step:focus-within .fdl-step-copy,
        .fdl-step:active .fdl-step-copy { transform: none; }
        .fdl-route-scroll, .fdl-step-pin i { transition: none; }
        .fdl-step-pin i, .fdl-step:hover .fdl-step-pin::after,
        .fdl-step:focus-within .fdl-step-pin::after { animation: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .fdl-route::after, .fdl-step.is-current .fdl-step-pin { animation: none; }
        .fdl-step-pin, .fdl-step-copy { transition: none; }
        .fdl-stage-action { transform: none; transition: none; }
        .fdl-step::before { transition: none; }
    }
    @media print {
        .fdl-route-scroll { overflow: visible; padding-bottom: 8px; }
        .fdl-route::after, .fdl-step.is-current .fdl-step-pin { animation: none; }
    }

    @media (max-width: 720px) {
        .fdl-route { min-width: 900px; }
        .fdl-route-scroll { overflow-x: auto; overflow-y: hidden; }
        .fdl-route-head { align-items: flex-start; flex-wrap: wrap; margin-top: 0; padding-top: 6px; }
        .fdl-route-flag { margin-left: 0; }
    }

@include('contract-flights.partials.passenger-sheet-styles')
</style>
