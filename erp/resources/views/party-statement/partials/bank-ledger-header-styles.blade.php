    .ledger-header {
        position: relative; overflow: hidden; isolation: isolate;
        border-radius: 20px; color: #fff;
        padding: 1.35rem 1.6rem 1.2rem; margin-bottom: 1.25rem;
        background: linear-gradient(135deg, #4338ca 0%, #5b4ce0 45%, #6d28d9 100%);
        box-shadow:
            0 26px 50px -30px rgba(67,56,202,0.95),
            0 2px 6px -2px rgba(15,23,42,0.28),
            inset 0 1px 0 rgba(255,255,255,0.22);
        /* Segoe UI Variable first: on Windows it is the only face here that ships
           real tabular figures, which is what keeps a column of amounts from
           dancing as the digits change. Everything after it is a fallback. */
        font-family: "Segoe UI Variable Text", "Segoe UI", ui-sans-serif, system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
        font-variant-numeric: tabular-nums;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    /* Lit from the top-right, shaded at the bottom-left, with a soft bloom behind
       the mark. The gradient beneath is untouched; this only gives the light
       somewhere to come from. */
    .ledger-header::before {
        content: ''; position: absolute; inset: 0; z-index: 0; pointer-events: none;
        background:
            radial-gradient(90% 160% at 100% 0, rgba(255,255,255,0.24), rgba(255,255,255,0) 52%),
            radial-gradient(70% 120% at 0 0, rgba(255,255,255,0.14), rgba(255,255,255,0) 45%),
            radial-gradient(90% 130% at 0 100%, rgba(2,6,23,0.28), rgba(2,6,23,0) 62%);
    }
    .ledger-head-top, .ledger-meta { position: relative; z-index: 1; }

    /* Shared type scale. One label style and one figure style across both bands
       is most of what makes the card read as a single object.

       Band 1 sits on a saturated gradient and band 2 on white, so they need
       different treatment for the same text to read: the .ledger-head-top block
       further down lifts band 1 off its background with a shadow and a lighter
       weight, and .ledger-meta re-inks band 2 for white. */
    .lh-lbl {
        display: block; font-size: 10.5px; font-weight: 800; line-height: 1.4;
        text-transform: uppercase; letter-spacing: 0.085em;
        color: rgba(255,255,255,0.88); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .lh-lbl i { font-size: 9.5px; margin-right: 4px; opacity: 1; }
    .lh-lbl .lh-qual { letter-spacing: 0.04em; text-transform: none; opacity: 0.8; font-weight: 700; }
    .lh-fig {
        display: block; margin-top: 2px; font-size: 1.16rem; font-weight: 800;
        letter-spacing: -0.014em; line-height: 1.22; color: #fff;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    /* The taka sign is punctuation, not a digit, so it stays a step behind the
       number â€” but only a step. At 55% it read as a speck of lint rather than a
       currency mark. Same for the Dr/Cr tag. */
    .lh-fig .cur { font-size: 0.8em; font-weight: 700; opacity: 0.85; margin-right: 2px; }
    .lh-fig .tag { font-size: 0.66em; font-weight: 800; letter-spacing: 0.06em; opacity: 0.85; margin-left: 5px; }

    /* â”€â”€ Band 1 â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .ledger-head-top { display: flex; flex-wrap: wrap; align-items: stretch; row-gap: 16px; }
    /* Everything on the gradient gets a soft dark shadow. It is what separates
       the glyph from the colour behind it â€” without it the lighter weights below
       would read as washed out rather than crisp. */
    .ledger-head-top .lh-lbl,
    .ledger-head-top .lh-fig,
    .ledger-head-top .lh-line,
    .ledger-head-top .lh-last-note,
    .ledger-head-top .lh-last-when,
    .ledger-head-top .lh-co-name,
    .ledger-head-top #ledgerBankName { text-shadow: 0 1px 2px rgba(2,6,23,0.38); }
    .ledger-head-top #ledgerBankName { text-shadow: 0 2px 5px rgba(2,6,23,0.34); }
    /* 800 at 10.5px closes up its own counters on a gradient. 700 with the shadow
       above is both lighter and easier to read than 800 was. */
    .ledger-head-top .lh-lbl { font-weight: 700; letter-spacing: 0.07em; color: rgba(255,255,255,0.96); }
    .lh-zone { position: relative; min-width: 0; padding: 0 1.45rem; display: flex; flex-direction: column; justify-content: center; gap: 5px; }
    .lh-zone:first-child { padding-left: 0; }
    /* A hairline that fades at both ends reads as light on an edge, not as a
       table border drawn down the card. */
    .lh-zone + .lh-zone::before {
        content: ''; position: absolute; left: 0; top: 3px; bottom: 3px; width: 1px;
        background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0.36) 48%, rgba(255,255,255,0));
    }
    /* The identity zone takes the elastic share; the other two size to content,
       and the buttons are pushed to the far edge by the auto margin. */
    .lh-zone-id { flex: 1 1 280px; flex-direction: row; align-items: center; gap: 1rem; }
    .lh-zone-co { flex: 0 1 195px; }
    .lh-zone-last { flex: 0 1 250px; }
    .lh-actions { margin-left: auto; padding-left: 1.45rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    /* Scoped to this card's action row, so no other .bank-btn on the page moves.
       The lift is what keeps three buttons from reading as flat stickers laid on
       the gradient. */
    .lh-actions .bank-btn {
        box-shadow: 0 10px 18px -11px rgba(2,6,23,0.85), inset 0 1px 0 rgba(255,255,255,0.45);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .lh-actions .bank-btn:hover { transform: translateY(-1px); box-shadow: 0 13px 22px -11px rgba(2,6,23,0.9), inset 0 1px 0 rgba(255,255,255,0.5); }
    .lh-actions .bank-btn:active { transform: translateY(0); }

    /* The mark plate: an open 2px white frame, no fill, so the card's gradient
       runs behind the logo. Owner's call, 2026-08-19.

       Worth knowing if a mark ever looks weak here: none of the six files carry a
       baked-in white box, so nothing shows a grey square â€” but three of them are
       dark ink (bKash 1.28:1, Dhaka Bank 1.44:1, Islami 1.81:1 against the
       gradient) and lean on the plate for separation. If one reads muddy, giving
       .ledger-avatar a background again is the one-line fix. */
    .ledger-avatar {
        flex: 0 0 92px; width: 92px; height: 92px;
        box-sizing: border-box;
        display: grid; place-items: center; padding: 3px; overflow: visible;
        background: transparent; border: 0;
        /* drop-shadow, NOT box-shadow. The element has no background, so a
           box-shadow would paint a floating rectangle behind nothing; drop-shadow
           follows the logo's own alpha and lifts the mark itself off the card.
           It also buys dark logos a little separation from the gradient. */
        filter: drop-shadow(0 6px 11px rgba(2,6,23,0.45));
    }
    /* No logo on file: initials or the type glyph, no frame, sized to hold the
       same visual weight a real mark would. Both are white and inherit the
       drop-shadow above, so they read straight off the gradient. */
    .ledger-avatar.is-mono { padding: 6px; }
    /* The marks on file run from 0.79 (Islami, tall) to 1.85 (Dhaka, wide) and
       each must fill the plate on its long side without ever being cropped or
       stretched. width/height 100% + object-fit: contain does exactly that.
       Do NOT go back to max-width/max-height here: those only cap a mark, they
       never grow one, so anything smaller than the box drew at its own pixel
       size and looked lost inside it. */
    .ledger-avatar img { width: 100%; height: 100%; object-fit: contain; display: block; }
    .ledger-avatar .lv-mono { display: none; font-size: 1.75rem; font-weight: 800; letter-spacing: 0.01em; color: #fff; }
    .ledger-avatar > i { display: none; font-size: 2.2rem; color: #fff; }
    .ledger-avatar.is-mono img { display: none; }
    .ledger-avatar.is-mono .lv-mono { display: block; }
    /* Cash has no printed mark and no brand worth initialling, so renderLedger()
       sets .is-glyph and the type icon shows instead of a meaningless monogram. */
    .ledger-avatar.is-mono.is-glyph .lv-mono { display: none; }
    .ledger-avatar.is-mono.is-glyph > i { display: block; }

    .lh-idcol { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
    #ledgerBankName { font-size: 1.42rem; font-weight: 800; letter-spacing: -0.021em; line-height: 1.12; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* Clip, never wrap: a wrapped line would push its zone taller than the band
       it sits in. renderLedger() mirrors every value into title=, so a clipped
       one is still readable on hover. */
    .lh-line { font-size: 0.83rem; font-weight: 500; line-height: 1.32; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lh-line i { font-size: 0.72rem; width: 12px; margin-right: 3px; opacity: 1; }
    .lh-line .lh-k { font-weight: 700; color: rgba(255,255,255,0.8); letter-spacing: 0.05em; font-size: 0.73rem; margin-right: 5px; }
    /* An account number is read digit by digit, so it gets the tabular face. */
    .lh-ac { font-family: ui-monospace, SFMono-Regular, "Cascadia Mono", Menlo, Consolas, monospace; font-size: 0.82rem; font-weight: 500; letter-spacing: 0.03em; color: #ffffff; }
    /* Current / Savings / Cash â€” a chip rather than more running text, so the
       eye can tell it apart from the digits it sits beside at a glance. */
    .lh-type {
        display: inline-block; margin-left: 7px; padding: 1px 8px; border-radius: 999px;
        font-size: 0.67rem; font-weight: 700; letter-spacing: 0.045em; text-transform: uppercase;
        color: #ffffff; background: rgba(255,255,255,0.20);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.28);
        vertical-align: 1px;
    }

    /* The company badge. The plate is the card's own translucent white rather
       than the company's accent: half the accents in the rail are dark navies
       that turn to mud on an indigo card, and a badge nobody can read is worse
       than a badge with no brand colour in it. */
    .lh-co { display: flex; align-items: center; gap: 9px; min-width: 0; }
    .lh-co .ci {
        flex: 0 0 26px; width: 26px; height: 26px; border-radius: 9px;
        display: grid; place-items: center; font-size: 13px; color: #fff;
        background: rgba(255,255,255,0.18);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.30), inset 0 1px 0 rgba(255,255,255,0.35);
    }
    .lh-co-name { font-size: 0.94rem; font-weight: 700; letter-spacing: -0.004em; color: #fff; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Latest transaction. The direction is a pill rather than the word "In" â€”
       colour and arrow say it faster than a label can, and it leaves the line
       free for the amount, which is what you actually came to read. */
    .lh-last-row { display: flex; align-items: center; gap: 9px; min-width: 0; }
    .lh-pill {
        flex: 0 0 auto; display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px 3px 8px; border-radius: 999px;
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.01em; white-space: nowrap;
    }
    .lh-pill i { font-size: 0.62rem; }
    .lh-pill-in  { background: rgba(4,47,32,0.55);    color: #34f5a0; box-shadow: inset 0 0 0 1px rgba(52,245,160,0.55); }
    .lh-pill-out { background: rgba(62,8,22,0.55);    color: #ffa8b6; box-shadow: inset 0 0 0 1px rgba(255,168,182,0.55); }
    .lh-pill-none{ background: rgba(2,6,23,0.35);     color: rgba(255,255,255,0.82); box-shadow: inset 0 0 0 1px rgba(255,255,255,0.28); }
    .lh-last-amt { font-size: 1.04rem; font-weight: 800; letter-spacing: -0.014em; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lh-last-amt .cur { font-size: 0.8em; font-weight: 700; opacity: 0.85; margin-right: 2px; }
    .lh-amt-in  { color: #34f5a0; }
    .lh-amt-out { color: #ffa8b6; }
    .lh-last-note { font-size: 0.82rem; font-weight: 500; line-height: 1.3; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lh-last-when { font-size: 0.76rem; font-weight: 500; line-height: 1.3; color: rgba(255,255,255,0.9); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* â”€â”€ Band 2 â€” the figures rail â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    /* An inset well rather than a bare row: it separates the numbers from the
       identity above without another hard rule across the card, and it is what
       carries the four tiles that used to live in their own row. */
    .ledger-meta {
        display: flex; flex-wrap: wrap; row-gap: 0.8rem;
        margin-top: 1.15rem; padding: 0.8rem 0.3rem;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f6f8fc 100%);
        /* A short, soft drop plus a hairline rim: enough to lift the panel off
           the gradient, not enough to look like it is hovering. */
        box-shadow:
            0 10px 22px -12px rgba(2,6,23,0.55),
            0 2px 4px -2px rgba(2,6,23,0.22),
            inset 0 0 0 1px rgba(15,23,42,0.06),
            inset 0 1px 0 #ffffff;
    }
    /* flex-basis auto, not a fixed 118px: the labels differ in width by more than
       two to one ("TRANSACTIONS" vs "TOTAL CREDIT (out)"), so each cell takes
       what its own label needs and the slack is shared out. A fixed basis is what
       clipped "OPENING BALAâ€¦" and "TOTAL CREDIT (oâ€¦". */
    .ledger-meta > div { position: relative; flex: 1 1 auto; min-width: 0; padding: 0 0.85rem; }
    /* The cell separator is indigo, not slate: it is the card's own colour, so
       the rail reads as part of the card rather than a plain table dropped onto
       it. Still 1px â€” the weight was never the problem.

       It is SOLID and full height here, unlike every other hairline on this card.
       Those fade out at both ends, which works on the indigo where they only have
       to suggest an edge. In the rail they have to actually divide seven columns
       of numbers, and a rule that is 14% opaque at the ends paints about half its
       own height â€” next to a wide figure it vanished, which is exactly what got
       reported as "missing separators". Do not put the fade back. */
    .ledger-meta > div + div::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 1px;
        background: rgba(79,70,229,0.70);
    }
    /* Ink for the white panel. Scoped to .ledger-meta so band 1 keeps its own
       white-on-indigo scale untouched. */
    .ledger-meta .lh-lbl { font-size: 9.5px; letter-spacing: 0.06em; color: #64748b; }
    .ledger-meta .lh-lbl i { opacity: 1; }
    .ledger-meta .lh-fig { color: #0f172a; }
    .ledger-meta .lh-fig .cur { opacity: 0.5; font-weight: 700; }
    .ledger-meta .lh-fig .tag { opacity: 0.55; }
    /* Closing is the figure the whole card builds towards, so it anchors the
       right edge and reads a size larger than the four that feed it. */
    .ledger-meta > div.is-close { text-align: right; flex: 1.2 1 auto; }
    .ledger-meta > div.is-close .lh-fig { font-size: 1.34rem; color: #b45309; }
    .ledger-meta > div.is-close .lh-lbl { color: #b45309; opacity: 0.85; }

    /* Money in is green, money out is red â€” literally the same two inks the
       statement table's Debit and Credit columns use, so the rail and the columns
       under it can never be read as two different things. */
    .ledger-meta > div.is-in  .lh-fig, .ledger-meta > div.is-in  .lh-lbl { color: #15803d; }
    .ledger-meta > div.is-out .lh-fig, .ledger-meta > div.is-out .lh-lbl { color: #b91c1c; }
    /* A day with no movement drops the colour but NOT the contrast â€” a zero you
       cannot read is worse than a zero that is merely quiet. */
    /* Only the figure greys out. The label was going to #94a3b8, which is
       2.48:1 on white â€” the same mistake as before, in a new palette. It keeps
       the standard #64748b (4.6:1) instead. */
    .ledger-meta > div.is-zero .lh-fig { color: #64748b; }

    /* Under ~1180px the three identity zones stop fitting beside the buttons, so
       the card reflows to two rows instead of squeezing the last transaction
       down to an ellipsis. */
    @media (max-width: 1180px) {
        .lh-zone-co, .lh-zone-last { flex: 1 1 220px; }
        .lh-actions { padding-left: 0; margin-left: auto; }
        /* The landing card carries a fourth zone and no buttons, so its balance
           wraps onto the second row with the other two rather than squeezing
           the title down to an ellipsis beside it. */
        .ca-header .lh-zone-bal { flex: 1 1 220px; }
    }
    @media (max-width: 720px) {
        .ledger-header { padding: 1.15rem 1.15rem 1rem; }
        .lh-zone { padding: 0; }
        .lh-zone + .lh-zone::before { display: none; }
        .lh-actions { padding-left: 0; }
        .ledger-meta > div { flex: 1 1 44%; }
    }


