{{-- The passenger journey card (owner, 2026-10-03) — CSS only, no <style>
     tag. Included by passenger-journey-assets (Flights & Passengers, drawn
     in Blade by passenger-journey-row) and by the Flight Dashboard (drawn in
     JS by ledgerPassengerRoute()). Change the card here once; change the
     markup in both. Every selector is mj- prefixed. --}}
    /* ── The journey card (owner, 2026-10-03: "make it more creative") ──
       A boarding pass: a stub on the left (progress ring · now · next ·
       flag) torn off by a dashed perforation, the track on the right. Every
       stage draws its own two half-connectors, so the green line ends
       exactly at the current stage whatever the step widths are. */
    .mj-card {
        display: flex; align-items: stretch; gap: 16px; padding: 16px 18px 14px;
        border: 1.5px solid var(--mj-border, #c7d2fe); border-radius: 12px;   /* its row's colour */
        background: linear-gradient(120deg, #f8fafc, #f5f3ff 65%, #f0fdf4);
    }
    .mj-stub { flex: 0 0 186px; display: flex; align-items: center; gap: 12px; padding-right: 16px; border-right: 2px dashed #c7d2fe; min-width: 0; }
    .mj-ring { flex: none; display: grid; place-items: center; width: 46px; height: 46px; border-radius: 50%; background: conic-gradient(#22c55e var(--mj-p), #e2e8f0 0); }
    .mj-ring > span { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; background: #fff; color: #15803d; font-size: 10px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .mj-now { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .mj-card .mj-now small { color: #6366f1; font-size: 8.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .mj-card .mj-now small i { margin-right: 3px; }
    .mj-now b { color: #1e293b; font-size: 11.5px; font-weight: 700; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .mj-next { color: #64748b; font-size: 9px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mj-next i { font-size: 7px; margin: 0 2px; color: #a5b4fc; }
    .mj-flag { align-self: flex-start; display: inline-flex; align-items: center; gap: 4px; margin-top: 2px; padding: 1px 7px; border-radius: 999px; background: #fff1f2; color: #be123c; font-size: 9px; font-weight: 700; }

    .mj-scroll { flex: 1 1 auto; min-width: 0; padding: 4px 4px 0; }   /* no scroll box: labels fit their slots, and the hover bubble may rise over the row */
    .mj-route { display: flex; align-items: flex-start; width: 100%; min-width: 0; margin: 0; padding: 0; list-style: none; }
    .mj-step { position: relative; flex: .8 1 0; min-width: 0; text-align: center; color: #94a3b8; }
    .mj-step.is-focus { flex-grow: 1.2; }
    .mj-step.is-current { flex-grow: 1.9; z-index: 5; }

    /* the line: left half to this stage, right half on to the next */
    .mj-step::before, .mj-step::after { content: ''; position: absolute; top: 14px; height: 2px; z-index: 0; pointer-events: none;
        background: repeating-linear-gradient(90deg, #cbd5e1 0 5px, transparent 5px 9px); }   /* not reached: a dashed flight path */
    .mj-step::before { left: 0; right: 50%; }
    .mj-step::after { left: 50%; right: 0; }
    .mj-step:first-child::before, .mj-step:last-child::after { display: none; }
    .mj-step.is-done::before, .mj-step.is-done::after, .mj-step.is-skipped::before { background: #22c55e; }
    .mj-step.is-skipped::after { background: #22c55e; }
    .mj-step.is-skipped:has(+ .is-next)::after, .mj-step.is-done:has(+ .is-next)::after { background: repeating-linear-gradient(90deg, #cbd5e1 0 5px, transparent 5px 9px); }
    .mj-step.is-current::before {   /* the leg he is on now: flowing into the current stage */
        background: repeating-linear-gradient(90deg, #22c55e 0 6px, #818cf8 6px 12px); background-size: 24px 2px;
        animation: mj-flow 1.2s linear infinite;
    }
    @keyframes mj-flow { to { background-position: 24px 0; } }

    .mj-stage { position: relative; display: flex; flex-direction: column; align-items: center; width: 100%; margin: 0; padding: 0; border: 0; background: none; font: inherit; color: inherit; cursor: pointer; }
    .mj-stage:disabled { cursor: default; }
    .mj-stage:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; border-radius: 6px; }

    /* pins sit on one centre line (30px band) whatever their size */
    .mj-pin {
        position: relative; z-index: 1; display: inline-flex; align-items: center; justify-content: center;
        width: 22px; height: 22px; margin: 4px 0; border: 2px solid #cbd5e1; border-radius: 50%; background: #fff; color: #94a3b8;
        box-shadow: 0 0 0 3px #f8fafc; transition: transform .2s ease, box-shadow .2s ease; box-sizing: border-box;
    }
    .mj-pin i { font-size: 8px; }
    .mj-step.is-done .mj-pin { width: 18px; height: 18px; margin: 6px 0; border-color: #22c55e; background: #22c55e; color: #fff; }
    .mj-step.is-done .mj-pin i { font-size: 8px; }
    .mj-step.is-skipped .mj-pin { width: 14px; height: 14px; margin: 8px 0; border: 2px dashed #86efac; background: #f0fdf4; }
    .mj-step.is-skipped .mj-pin i { display: none; }
    .mj-step.is-hold .mj-pin { border-color: #38bdf8; background: #e0f2fe; color: #0369a1; }
    .mj-step.is-pending .mj-pin { border-color: #cbd5e1; background: #f1f5f9; color: #475569; }
    .mj-step.is-expired .mj-pin { border-color: #fca5a5; background: #fee2e2; color: #b91c1c; }
    .mj-step.is-current .mj-pin {
        width: 30px; height: 30px; margin: 0; border-color: #4f46e5; background: linear-gradient(135deg, #6366f1, #4338ca); color: #fff;
        box-shadow: 0 0 0 4px #e0e7ff, 0 4px 12px rgba(79,70,229,.3); z-index: 3;
        animation: mj-pulse 2s ease-out infinite;
    }
    .mj-step.is-current .mj-pin i { font-size: 11px; }
    @keyframes mj-pulse {
        0%   { box-shadow: 0 0 0 4px #e0e7ff, 0 0 0 4px rgba(99,102,241,.45), 0 4px 12px rgba(79,70,229,.3); }
        100% { box-shadow: 0 0 0 4px #e0e7ff, 0 0 0 14px rgba(99,102,241,0), 0 4px 12px rgba(79,70,229,.3); }
    }

    /* ── Hover (owner, 2026-10-03: "more fun and advanced") ──────────────
       The pin lifts and glows in its own colour, a bubble tells the whole
       stage, the other labels step back, and on an upcoming stage the dashed
       path from "now" up to it lights and flows — a preview of the move. */
    .mj-card { transition: box-shadow .2s ease; }
    :is(.mfp-journey-row, .fdl-route-row):hover .mj-card { box-shadow: 0 6px 18px rgba(79,70,229,.10); }
    .mj-copy { transition: opacity .2s ease; }
    .mj-route:hover .mj-step:not(:hover) .mj-copy { opacity: .5; }
    .mj-stage:hover .mj-pin, .mj-stage:focus-visible .mj-pin { transform: translateY(-3px) scale(1.18); }
    .mj-step.is-done .mj-stage:hover .mj-pin { box-shadow: 0 0 0 4px #bbf7d0, 0 6px 14px rgba(34,197,94,.35); }
    .mj-step.is-next .mj-stage:hover .mj-pin { border-color: #6366f1; color: #4f46e5; box-shadow: 0 0 0 4px #e0e7ff, 0 6px 14px rgba(99,102,241,.3); }
    .mj-step.is-skipped .mj-stage:hover .mj-pin { border-color: #22c55e; }
    .mj-step.is-current .mj-stage:hover .mj-pin { transform: scale(1.1) rotate(-10deg); }
    .mj-stage:hover .mj-copy b { color: #312e81; }
    .mj-step.is-next:has(~ .mj-step:hover)::before, .mj-step.is-next:has(~ .mj-step:hover)::after,
    .mj-step.is-current:has(~ .mj-step:hover)::after, .mj-step.is-next:hover::before {
        background: repeating-linear-gradient(90deg, #818cf8 0 6px, transparent 6px 12px); background-size: 24px 2px;
        animation: mj-flow .7s linear infinite;
    }
    .mj-step.is-next:has(~ .mj-step:hover) .mj-pin { border-color: #a5b4fc; color: #6366f1; }

    /* the bubble */
    .mj-stage::before, .mj-stage::after { position: absolute; left: 50%; pointer-events: none; opacity: 0; transition: opacity .15s ease, transform .15s ease; z-index: 30; }
    .mj-stage::after {
        content: attr(data-tip); bottom: calc(100% + 9px); transform: translate(-50%, 4px);
        width: max-content; max-width: 210px; padding: 6px 10px; border-radius: 8px;
        background: #1e1b4b; color: #fff; font-size: 10px; font-weight: 600; line-height: 1.4; text-align: center; white-space: pre-line;
        box-shadow: 0 10px 24px rgba(30,27,75,.28);
    }
    .mj-stage::before { content: ''; bottom: calc(100% + 3px); transform: translate(-50%, 4px); border: 6px solid transparent; border-top-color: #1e1b4b; border-bottom: 0; }
    .mj-stage:hover::after, .mj-stage:hover::before, .mj-stage:focus-visible::after, .mj-stage:focus-visible::before { opacity: 1; transform: translate(-50%, 0); }
    .mj-step:first-child .mj-stage::after { left: 0; transform: translate(0, 4px); }
    .mj-step:first-child .mj-stage:hover::after { transform: translate(0, 0); }
    .mj-step:last-child .mj-stage::after { left: auto; right: 0; transform: translate(0, 4px); }
    .mj-step:last-child .mj-stage:hover::after { transform: translate(0, 0); }

    @media (prefers-reduced-motion: reduce) {
        .mj-step.is-current .mj-pin, .mj-step::before, .mj-step::after { animation: none !important; }
        .mj-stage .mj-pin { transition: none; }
    }

    .mj-copy { display: block; margin: 9px auto 0; max-width: 100%; padding: 0 2px; line-height: 1.25; }
    .mj-copy b { display: block; color: #64748b; font-size: 8px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mj-route .mj-copy small { display: block; margin-top: 1px; color: #a3adbb; font-size: 7.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* a label never leaves its own slot — wider ones wrap inside it */
    .mj-step.is-focus .mj-copy b { font-size: 8.5px; }
    .mj-step.is-done .mj-copy b { color: #15803d; }
    .mj-route .mj-step.is-done .mj-copy small { color: #86b89a; }
    .mj-step.is-skipped .mj-copy b { color: #94a3b8; font-weight: 500; }
    .mj-step.is-hold .mj-copy b, .mj-route .mj-step.is-hold .mj-copy small { color: #0369a1; }
    .mj-route .mj-step.is-expired .mj-copy small { color: #b91c1c; }
    .mj-step.is-current .mj-copy b { color: #3730a3; font-size: 9.5px; font-weight: 800; white-space: normal; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .mj-route .mj-step.is-current .mj-copy small { color: #4338ca; font-size: 8px; font-weight: 700; }

    /* ✎ on the stub: opens the shared stage editor (partials/stage-editor) */
    .mj-edit { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; margin-left: 6px; padding: 0;
        border: 1px solid #c7d2fe; border-radius: 5px; background: #fff; color: #4f46e5; font-size: 8px; cursor: pointer; vertical-align: 1px;
        transition: background-color .15s ease, color .15s ease, transform .15s ease; }
    .mj-edit:hover, .mj-edit:focus-visible { background: #4f46e5; color: #fff; transform: rotate(-12deg); outline: none; }
