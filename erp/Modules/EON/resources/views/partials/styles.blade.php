{{--
  EON — the whole visual language, in one place.

  INLINE, NOT A PUBLISHED ASSET, and that is a deliberate choice rather than a
  shortcut. Two reasons:

  1. It ships by uploading a file. A stylesheet under public/eon/ would need
     `php artisan vendor:publish --tag=eon-assets --force` on the server before
     the pages looked right, and an unstated server step is exactly what took
     this site down on 2026-08-10. EON adds no such step.
  2. It cannot leak. This block is rendered only inside EON views, by a person
     who is a super admin, on a /{role}/eon/* URL. Nothing here is ever parsed
     on another company's page.

  Every selector carries the eon- prefix. There is no bare element selector, no
  *, no html/body/:root, and nothing here is added to resources/css/app.css or
  the Vite bundle — those load on all 576 views in the ERP.

  The palette is the ERP's own (blue #2563eb, the same greys and reds the
  oversight screen uses), so EON looks like part of the product rather than a
  visitor.
--}}
<style>
    .eon-wrap { padding: 1.15rem 1.25rem 2.5rem; color: #111827; }

    /* ── Page head ───────────────────────────────────────────────────── */
    .eon-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
    .eon-title { font-size: 1.4rem; font-weight: 700; line-height: 1.15; letter-spacing: -.01em; }
    .eon-title .eon-mark { color: #2563eb; }
    .eon-sub { font-size: .82rem; color: #6b7280; margin-top: .25rem; max-width: 62ch; }

    .eon-tools { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
    .eon-search { position: relative; }
    .eon-search input {
        width: 260px; max-width: 60vw; padding: .5rem .8rem .5rem 2.1rem;
        border: 1px solid #e5e7eb; border-radius: 9px; font-size: .84rem; background: #fff;
    }
    .eon-search input:focus { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
    .eon-search > i { position: absolute; left: .72rem; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: .78rem; }
    .eon-btn {
        display: inline-flex; align-items: center; gap: .4rem; padding: .5rem .85rem;
        border-radius: 9px; font-size: .82rem; font-weight: 600; border: 1px solid #e5e7eb;
        background: #fff; color: #374151; text-decoration: none; cursor: pointer; transition: all .15s ease;
    }
    .eon-btn:hover { border-color: #bfdbfe; color: #1d4ed8; background: #f8fbff; }
    .eon-btn.primary { background: #2563eb; border-color: #2563eb; color: #fff; }
    .eon-btn.primary:hover { background: #1d4ed8; color: #fff; }

    /* ── Cards ───────────────────────────────────────────────────────── */
    .eon-card { background: #fff; border: 1px solid #e9ecef; border-radius: 12px; box-shadow: 0 1.5px 3px rgba(17,21,45,.06); }
    .eon-card + .eon-card { margin-top: .9rem; }
    .eon-card-h { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1rem; border-bottom: 1px solid #f1f3f5; }
    .eon-card-t { font-size: .88rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: .45rem; }
    .eon-card-t i { color: #9ca3af; font-size: .8rem; }
    .eon-card-n { font-size: .72rem; color: #9ca3af; }
    .eon-card-b { padding: 1rem; }
    .eon-card-b.tight { padding: .5rem 0 0; }

    .eon-grid-2 { display: grid; grid-template-columns: 1.55fr 1fr; gap: .9rem; align-items: start; }
    .eon-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: .9rem; align-items: start; }
    @media (max-width: 1100px) { .eon-grid-2, .eon-grid-3 { grid-template-columns: 1fr; } }

    /* ── The pulse strip ─────────────────────────────────────────────── */
    .eon-pulse { display: grid; grid-template-columns: repeat(auto-fit, minmax(118px, 1fr)); gap: .5rem; margin-bottom: .9rem; }
    .eon-tile { background: #fff; border: 1px solid #e9ecef; border-radius: 11px; padding: .7rem .8rem; }
    .eon-tile-l { font-size: .62rem; text-transform: uppercase; letter-spacing: .05em; color: #9ca3af; font-weight: 700; }
    .eon-tile-v { font-size: 1.32rem; font-weight: 700; line-height: 1.2; margin-top: .18rem; }
    .eon-tile-n { font-size: .68rem; color: #9ca3af; margin-top: .1rem; }
    .eon-tile.bad .eon-tile-v { color: #dc2626; }
    .eon-tile.warn .eon-tile-v { color: #b45309; }
    .eon-tile.good .eon-tile-v { color: #059669; }

    .eon-delta { font-size: .7rem; font-weight: 700; }
    .eon-delta.up { color: #059669; }
    .eon-delta.down { color: #dc2626; }
    .eon-delta.flat { color: #9ca3af; }

    /* ── Score, bands, chips ─────────────────────────────────────────── */
    .eon-score { display: flex; align-items: center; gap: .6rem; }
    .eon-score-v { font-size: 1.05rem; font-weight: 800; line-height: 1; }
    .eon-band {
        font-size: .6rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
        padding: .16rem .45rem; border-radius: 5px; white-space: nowrap;
    }
    .eon-band.exemplary { background: #ecfdf5; color: #047857; }
    .eon-band.strong    { background: #eff6ff; color: #1d4ed8; }
    .eon-band.steady    { background: #f5f3ff; color: #6d28d9; }
    .eon-band.watch     { background: #fffbeb; color: #b45309; }
    .eon-band.critical  { background: #fef2f2; color: #b91c1c; }
    .eon-band.unrated   { background: #f3f4f6; color: #6b7280; }

    .eon-chip {
        display: inline-flex; align-items: center; gap: .3rem; font-size: .66rem; font-weight: 600;
        padding: .18rem .45rem; border-radius: 6px; background: #f3f4f6; color: #4b5563; white-space: nowrap;
    }
    .eon-chip.low  { background: #fffbeb; color: #b45309; }
    .eon-chip.med  { background: #f0f9ff; color: #0369a1; }
    .eon-chip.high { background: #ecfdf5; color: #047857; }

    /* The score ring — the one big number on a dossier. */
    .eon-ring { position: relative; width: 108px; height: 108px; flex-shrink: 0; }
    .eon-ring svg { transform: rotate(-90deg); }
    .eon-ring-c { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .eon-ring-v { font-size: 1.85rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
    .eon-ring-l { font-size: .58rem; text-transform: uppercase; letter-spacing: .07em; color: #9ca3af; font-weight: 700; margin-top: .18rem; }

    /* ── Pillars ─────────────────────────────────────────────────────── */
    .eon-pillar + .eon-pillar { margin-top: .85rem; padding-top: .85rem; border-top: 1px dashed #f1f3f5; }
    .eon-pillar-h { display: flex; align-items: baseline; justify-content: space-between; gap: .5rem; }
    .eon-pillar-n { font-size: .82rem; font-weight: 700; }
    .eon-pillar-w { font-size: .66rem; color: #9ca3af; font-weight: 600; }
    .eon-pillar-v { font-size: .92rem; font-weight: 800; }
    .eon-bar { height: 7px; border-radius: 4px; background: #f1f3f5; margin: .4rem 0 .35rem; overflow: hidden; }
    .eon-bar > span { display: block; height: 100%; border-radius: 4px; transition: width .3s ease; }
    .eon-bar > span.strong  { background: #10b981; }
    .eon-bar > span.fair    { background: #3b82f6; }
    .eon-bar > span.weak    { background: #f59e0b; }
    .eon-bar > span.poor    { background: #ef4444; }
    .eon-bar > span.unknown { background: #d1d5db; }
    .eon-pillar-b { font-size: .74rem; color: #6b7280; line-height: 1.5; }

    /* ── Findings ────────────────────────────────────────────────────── */
    .eon-signal { display: flex; gap: .6rem; padding: .6rem .7rem; border-radius: 9px; border: 1px solid #f1f3f5; background: #fcfcfd; }
    .eon-signal + .eon-signal { margin-top: .45rem; }
    .eon-signal > i { font-size: .82rem; margin-top: .1rem; flex-shrink: 0; }
    .eon-signal-t { font-size: .8rem; font-weight: 650; line-height: 1.35; }
    .eon-signal-d { font-size: .74rem; color: #6b7280; line-height: 1.5; margin-top: .18rem; }
    .eon-signal.critical { border-color: #fecaca; background: #fef8f8; }
    .eon-signal.critical > i { color: #dc2626; }
    .eon-signal.watch    { border-color: #fde68a; background: #fffdf5; }
    .eon-signal.watch > i { color: #d97706; }
    .eon-signal.good     { border-color: #bbf7d0; background: #f7fdfa; }
    .eon-signal.good > i { color: #059669; }
    .eon-signal.neutral > i { color: #9ca3af; }

    /* ── Tables ──────────────────────────────────────────────────────── */
    .eon-tw { overflow-x: auto; }
    .eon-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
    .eon-table th {
        text-align: left; font-size: .62rem; text-transform: uppercase; letter-spacing: .05em;
        color: #9ca3af; font-weight: 700; padding: .5rem .6rem; border-bottom: 1px solid #f1f3f5; white-space: nowrap;
    }
    .eon-table th a { color: inherit; text-decoration: none; }
    .eon-table th a:hover { color: #2563eb; }
    .eon-table th.is-sorted a { color: #2563eb; }
    .eon-table td { padding: .55rem .6rem; border-bottom: 1px solid #f6f7f8; vertical-align: middle; }
    .eon-table tbody tr:hover { background: #fafbff; }
    .eon-table td.num, .eon-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .eon-table td.mute { color: #9ca3af; }
    .eon-table td.bad { color: #dc2626; font-weight: 600; }
    .eon-table td.warn { color: #b45309; font-weight: 600; }

    .eon-person { display: flex; align-items: center; gap: .55rem; min-width: 0; }
    .eon-avatar {
        width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; overflow: hidden;
        background: linear-gradient(135deg,#dbeafe,#bfdbfe); color: #1d4ed8;
        font-weight: 700; font-size: .72rem; display: flex; align-items: center; justify-content: center;
    }
    .eon-avatar.lg { width: 54px; height: 54px; font-size: 1.05rem; }
    .eon-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .eon-person-n { font-weight: 600; font-size: .82rem; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .eon-person-m { font-size: .68rem; color: #9ca3af; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .eon-person a { color: inherit; text-decoration: none; }
    .eon-person a:hover .eon-person-n { color: #2563eb; }

    /* ── Charts (inline SVG, no library) ─────────────────────────────── */
    .eon-chart { width: 100%; height: auto; display: block; }
    .eon-axis { font-size: 9px; fill: #9ca3af; }
    .eon-legend { display: flex; gap: .9rem; font-size: .7rem; color: #6b7280; margin-top: .5rem; }
    .eon-legend span { display: inline-flex; align-items: center; gap: .3rem; }
    .eon-legend i { width: 9px; height: 9px; border-radius: 2px; display: inline-block; }

    .eon-spark { display: block; }

    /* Weekly cadence strip. */
    .eon-cadence { display: flex; gap: 3px; align-items: flex-end; }
    .eon-cadence div { flex: 1; border-radius: 2px 2px 0 0; background: #dbeafe; min-height: 3px; }
    .eon-cadence div.zero { background: #f1f3f5; }
    .eon-cadence-l { display: flex; gap: 3px; margin-top: .3rem; font-size: .55rem; color: #cbd5e1; }
    .eon-cadence-l span { flex: 1; text-align: center; }

    /* ── The month-on-month table ────────────────────────────────────── */
    .eon-vs td:first-child { color: #6b7280; font-size: .78rem; }
    .eon-vs td.num { font-weight: 650; }

    /* ── Drawdown ────────────────────────────────────────────────────── */
    .eon-dd { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: .6rem; }
    .eon-dd-i { padding: .55rem .65rem; border-radius: 9px; background: #fafbfc; border: 1px solid #f1f3f5; }
    .eon-dd-l { font-size: .6rem; text-transform: uppercase; letter-spacing: .05em; color: #9ca3af; font-weight: 700; }
    .eon-dd-v { font-size: 1.05rem; font-weight: 700; margin-top: .15rem; }
    .eon-dd-n { font-size: .66rem; color: #9ca3af; }

    /* ── Stars ───────────────────────────────────────────────────────── */
    .eon-stars i { font-size: .72rem; color: #f59e0b; }
    .eon-stars i.off { color: #e5e7eb; }
    .eon-stars.lg i { font-size: 1rem; }

    .eon-starpick { display: inline-flex; gap: .25rem; }
    .eon-starpick input { position: absolute; opacity: 0; width: 0; height: 0; }
    .eon-starpick label { cursor: pointer; font-size: 1.2rem; color: #e5e7eb; line-height: 1; transition: color .12s ease; }
    .eon-starpick label.on { color: #f59e0b; }

    /* ── Review form ─────────────────────────────────────────────────── */
    .eon-field { margin-bottom: .75rem; }
    .eon-label { display: block; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: .3rem; }
    .eon-input, .eon-textarea, .eon-select {
        width: 100%; padding: .5rem .7rem; border: 1px solid #e5e7eb; border-radius: 8px;
        font-size: .82rem; background: #fff; color: #111827; font-family: inherit;
    }
    .eon-textarea { min-height: 76px; resize: vertical; line-height: 1.55; }
    .eon-input:focus, .eon-textarea:focus, .eon-select:focus { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }

    .eon-note { font-size: .72rem; color: #6b7280; line-height: 1.55; }
    .eon-warn {
        font-size: .75rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a;
        border-radius: 9px; padding: .55rem .7rem; line-height: 1.5;
    }
    .eon-flash {
        font-size: .8rem; border-radius: 9px; padding: .6rem .8rem; margin-bottom: .8rem; font-weight: 600;
    }
    .eon-flash.ok  { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .eon-flash.err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    .eon-empty { padding: 1.6rem 1rem; text-align: center; color: #9ca3af; font-size: .8rem; }
    .eon-empty i { font-size: 1.4rem; display: block; margin-bottom: .45rem; color: #d1d5db; }

    /* ── Method note (how the score is built) ────────────────────────── */
    .eon-method { font-size: .74rem; color: #6b7280; line-height: 1.6; }
    .eon-method b { color: #374151; }
    .eon-method ul { margin: .4rem 0 0; padding-left: 1.1rem; }
    .eon-method li { margin-bottom: .18rem; }

    /* A <details> that does not inherit any global disclosure styling. */
    .eon-more > summary { cursor: pointer; font-size: .74rem; color: #2563eb; font-weight: 600; list-style: none; }
    .eon-more > summary::-webkit-details-marker { display: none; }
    .eon-more > summary::before { content: '\f0d7'; font-family: 'Font Awesome 6 Free'; font-weight: 900; margin-right: .35rem; font-size: .7rem; }
    .eon-more[open] > summary::before { content: '\f0d8'; }
    .eon-more > div { margin-top: .5rem; }
</style>
