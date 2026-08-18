{{--
    Wood Art Interiors — the suite shell every module screen extends.

    Extracted the moment there was a second screen (Spaces & Phases): the shell
    must be IDENTICAL across screens or the no-reload navigation gives itself
    away — woodart-nav.js swaps only [data-wa-view], so everything outside it
    (scene, styles, scripts) persists from whichever screen loaded first. If two
    screens shipped different shell CSS, the suite would look different
    depending on where you entered it.

    A child view provides:
      $title / $subtitle             — page-head copy (verbatim from the
                                       reference's TAB_COPY / pageHead calls).
                                       The reference also passes an eyebrow;
                                       this shell deliberately does not render
                                       one (owner, 2026-08-10), so do not
                                       reintroduce it.
      $waModule / $section           — region attributes the nav script carries
      @section('wa-head-actions')    — the buttons on the right of the head
      @section('wa-view')            — the swapped content (NO <script> inside,
                                       see CLAUDE.md)

    Styling: every selector is wap- prefixed; values are transcribed from the
    reference design system, file and line noted per block —
      tokens.css [data-theme="light"]  --bg #e8edf6 · --surface #fff ·
        --surface-2 #f6f8fd · --surface-hi #e5ebf6 · --text #131a2c ·
        --text-dim #454f68 · --text-mute #5a6379 · --border rgba(26,67,191,.12)
      tokens.css :root  --epal-accent #1A43BF · --epal-deep #0A2472 ·
        --epal-navy #051650 · --epal-royal #123499 · radii 7/10/14/18
      config.js         woodart accent #6f9c1c
--}}
@extends('layout.app')

@section('meta-information')
    <title>Wood Art Interiors — {{ $title }}</title>
@endsection

@section('main-content')
{{-- The reference's own typefaces (its index.html loads exactly these): Inter
     for body copy, Sora for card headings, plus Marcellus for the page title
     (owner's call — see .wap-title). Loaded ONLY by Wood Art views —
     this is a per-view asset, not a change to any shared dependency. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@500;600;700;800&family=Marcellus&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('woodart/atmosphere/woodart-scene.css') }}">
<link rel="stylesheet" href="{{ asset('woodart/nav/woodart-nav.css') }}">

<style>
    /* ── host shell trim, Wood Art pages only ─────────────────────────────
       The gap above and below the LIVE BULLETIN bar, cut ~85% on the owner's
       instruction (2026-08-10): 15px + 12px + 18px of dead vertical space
       between the header and the page title, now 2 + 2 + 6.

       Both source rules live in SHARED files — `.header{margin-bottom:15px}`
       in layout/app.blade.php and `.headline-ticker{margin:0 16px 12px}` in
       layout/headline-ticker.blade.php — and every company renders them. They
       are NOT edited. Overriding from here instead is what keeps this change
       off every other company's screens: this stylesheet is emitted inside
       Wood Art's main-content section, so it exists only on Wood Art pages and
       cannot match anywhere else.

       These two selectors are the one place Wood Art CSS is allowed to name
       non-`wa-` elements, and only because of that file-level scoping. The id
       and !important are needed to outrank the shared rules, one of which is
       itself !important. If the whole ERP should be this tight, the fix is to
       change those two shared files instead — ask first. */
    #mainContentWrapper > header.header { margin-bottom: 2px !important; }
    #headline-ticker { margin-bottom: 2px; }

    /* ── persistent shell ─────────────────────────────────────────────────
       Cancels <main class="flex-1 p-3">'s padding so the ERP's own background
       runs edge to edge behind the scene — one surface, not two. */
    .wap-host {
        position: relative;
        margin: -0.75rem;
        width: calc(100% + 1.5rem);
    }

    /* The scene paints into a zero-height sticky layer so it pins to the
       viewport while the page scrolls, contributing nothing to layout. */
    .wap-scene-layer {
        position: sticky;
        top: 0;
        height: 0;
        z-index: 0;
    }

    .wap-scene-anchor {
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 100vh;
        overflow: hidden;
        pointer-events: none;
    }

    /* ── swapped region ──────────────────────────────────────────────────── */
    .wap {
        --wap-accent: #6f9c1c;
        --wap-bg: #e8edf6;
        --wap-surface: #ffffff;
        --wap-surface-2: #f6f8fd;
        --wap-surface-hi: #e5ebf6;
        --wap-text: #131a2c;
        --wap-text-dim: #454f68;
        --wap-text-mute: #5a6379;
        --wap-border: rgba(26, 67, 191, .12);
        --wap-border-strong: rgba(26, 67, 191, .22);
        --wap-border-accent: color-mix(in srgb, var(--wap-accent) 42%, transparent);
        --wap-shadow-card: 0 1px 2px rgba(5,22,80,.05), 0 5px 14px -4px rgba(5,22,80,.08),
                           0 20px 40px -16px rgba(5,22,80,.14);

        color: var(--wap-text);
        /* Reference tokens.css --font-sans: Inter first. */
        font-family: 'Inter', 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 14px;
        /* Side padding is 30px because .wap-tabs bleeds by exactly -30px; keep
           the two in step. Top/bottom trimmed from 26/60, then the top again
           from 18 to 6 as part of the bulletin-bar gap cut (owner 2026-08-10). */
        padding: 6px 30px 36px;
        min-height: calc(100vh - 200px);
        background: transparent;   /* the ERP page background shows through */
        position: relative;
        z-index: 1;                /* above the scene at 0 */
    }

    /* ── page head — reference layout.css .page-head/.page-title/.page-sub ──
       One line: icon · title, sub beneath. The reference's inline eyebrow is
       deliberately not rendered (see the markup below). The sub reveals its
       full sentence on hover via title="". */
    .wap-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 6px;
        flex-wrap: nowrap;
        /* A floor, not padding: the head is naturally taller than this now the
           title is a 28px display face. It only guarantees the tab band lands
           at the same Y on a section whose head happens to be shorter. */
        min-height: 56px;
    }
    .wap-head > *:first-child { min-width: 0; }

    .wap-title {
        /* The reference gives h1-h5 its --font-display (Sora, a geometric
           sans). Departed from here on the owner's instruction (2026-08-10),
           who picked Marcellus from a side-by-side of eleven faces: a Roman
           inscriptional serif — architectural and calm rather than fashion-
           magazine, which suits a fit-out brand.

           Only the PAGE title changes. Card headings stay on Sora so the two
           levels stay distinguishable; making everything a display serif is
           what turns "elegant" into "wedding invitation".

           Marcellus ships ONE weight (400). Do not ask for 600/700 here — the
           browser would synthesise a fake bold by smearing the outlines, which
           wrecks an inscriptional face's thin strokes.

           28px because a serif reads smaller than a geometric sans at the same
           pixel size; and +.01em tracking, since Roman letterforms want air
           between them, the opposite of the -.02em a tight modern sans wants. */
        font-family: 'Marcellus', Georgia, 'Times New Roman', serif;
        font-size: 28px;
        font-weight: 400;
        letter-spacing: .01em;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: nowrap;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        color: var(--wap-text);
    }
    /* Held below the cap height — at a full 26px the glyph competes with the
       title instead of introducing it. */
    .wap-title i { color: var(--wap-accent); font-size: .8em; }

    .wap-sub {
        color: var(--wap-text-dim);
        font-size: 13px;
        line-height: 1.55;
        margin-top: 4px;
        max-width: min(760px, 46vw);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: 21px;
    }

    .wap-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: nowrap;
        flex: 0 0 auto;
    }

    @media (max-width: 720px) {
        .wap-head { flex-wrap: wrap; min-height: 0; align-items: flex-start; margin-bottom: 14px; }
        .wap-sub { min-height: 0; white-space: normal; max-width: none; }
        .wap-actions { flex-wrap: wrap; }
    }

    /* ── buttons — reference base.css .btn / -primary / -ghost ───────────── */
    .wap-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        justify-content: center;
        padding: 9px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 13px;
        background: var(--wap-surface-2);
        color: var(--wap-text);
        border: 1px solid var(--wap-border);
        white-space: nowrap;
        user-select: none;
        cursor: pointer;
        transition: transform .14s cubic-bezier(.16,.84,.44,1), background .24s, border-color .24s, box-shadow .24s;
    }
    .wap-btn:hover { border-color: var(--wap-border-strong); transform: translateY(-1px); }
    .wap-btn:active { transform: translateY(0); }
    .wap-btn i { font-size: 1.05em; }

    .wap-btn-primary {
        background: linear-gradient(160deg, #1A43BF, #0A2472);
        color: #fff;
        border-color: transparent;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.22),
                    0 8px 22px -10px color-mix(in srgb, var(--wap-accent) 70%, transparent);
    }
    .wap-btn-primary:hover {
        filter: brightness(1.07);
        transform: translateY(-1px);
        border-color: transparent;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.28),
                    0 14px 32px -10px color-mix(in srgb, var(--wap-accent) 78%, transparent);
    }

    .wap-btn-ghost { background: transparent; }
    .wap-btn-ghost:hover { background: var(--wap-surface-2); }

    /* Destructive action. Deliberately NOT the primary gradient: removing a job
       should never be the button the eye lands on first. Tone from tokens.css
       --bad #f0506e, darkened for contrast on a light surface. */
    .wap-btn-danger {
        background: rgba(240, 80, 110, .12);
        border-color: rgba(240, 80, 110, .34);
        color: #c22945;
    }
    .wap-btn-danger:hover {
        background: rgba(240, 80, 110, .18);
        border-color: rgba(240, 80, 110, .5);
    }

    /* ── section tabs — reference base.css .tab-underline ─────────────────
       Bleeds to the content edges (-30px = the .wap side padding), frosted
       surface, 1px separators, 3px underline. WRAPS rather than scrolls. */
    .wap-tabs {
        display: flex;
        gap: 0;
        align-items: stretch;
        border-bottom: 1px solid var(--wap-border);
        flex-wrap: wrap;
        /* Tightened on the owner's instruction (2026-08-10) to hand the page
           back to content: was 18px above / 20px below. The 12px below is the
           floor — the band casts a `0 6px 18px` shadow, and less than this
           clips it against the first row of content. Side value stays -30px:
           it must equal .wap's side padding for the full-bleed to line up. */
        margin: 8px -30px 12px;
        padding: 0;
        background: color-mix(in srgb, var(--wap-surface) 62%, transparent);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: 0 6px 18px rgba(7, 12, 30, .10);
    }
    .wap-tab {
        flex: 1 0 auto;
        min-width: 0;
        text-align: center;
        padding: 12px clamp(8px, 1.2vw, 18px) 13px;
        border-bottom: 3px solid transparent;
        margin-bottom: -1px;
        font-size: 13px;
        font-weight: 700;
        color: var(--wap-text-dim);
        white-space: nowrap;
        letter-spacing: .1px;
        border-radius: 0;
        background: none;
        cursor: pointer;
        transition: color .24s, border-color .24s, background .24s;
    }
    /* .wap-tabs-dense — reference base.css .tabs-dense (line 541), for long
       rows: Accounts has ELEVEN sections. Same look, compressed harder so every
       label stays on one line. Opt-in per module ($tabsDense), so the other
       modules' bands are untouched. */
    .wap-tabs-dense .wap-tab {
        padding: 12px clamp(4px, .62vw, 14px) 13px;
        font-size: 12px;
        letter-spacing: 0;
    }

    .wap-tab + .wap-tab { border-left: 1px solid var(--wap-border); }
    .wap-tab:hover { color: var(--wap-text); background: color-mix(in srgb, var(--wap-accent) 9%, transparent); }
    .wap-tab.active { color: var(--wap-accent); border-bottom-color: var(--wap-accent); }

    /* ── KPI strip — reference components.css .kpi-grid / .kpi-card ─────── */
    .wap-kpi-grid {
        --kpi-gap: 12px;
        --kpi-cols: 5;
        --kpi-min: 150px;
        display: grid;
        gap: var(--kpi-gap);
        margin-bottom: 16px;
        grid-template-columns: repeat(auto-fit, minmax(
            max(var(--kpi-min), (100% - (var(--kpi-cols) - 1) * var(--kpi-gap)) / var(--kpi-cols)), 1fr));
    }

    .wap-kpi-card {
        display: flex;
        flex-direction: column;
        background:
            radial-gradient(130% 90% at 90% -30%, color-mix(in srgb, var(--wap-accent) 9%, transparent), transparent 55%),
            linear-gradient(180deg, color-mix(in srgb, var(--wap-surface-hi) 30%, var(--wap-surface)), var(--wap-surface));
        border: 1px solid var(--wap-border);
        border-radius: 18px;
        padding: 15px 16px;
        min-height: 118px;
        position: relative;
        overflow: hidden;
        box-shadow: var(--wap-shadow-card), inset 0 1px 0 rgba(255,255,255,.7);
        transition: transform .24s cubic-bezier(.16,.84,.44,1), box-shadow .24s cubic-bezier(.16,.84,.44,1), border-color .24s;
    }
    .wap-kpi-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 1px;
        opacity: .5;
        background: linear-gradient(90deg, transparent,
            color-mix(in srgb, var(--wap-accent) 55%, transparent) 32%,
            color-mix(in srgb, #7E9AE8 65%, transparent) 68%, transparent);
        transition: opacity .24s;
    }
    .wap-kpi-card:hover {
        transform: translateY(-3px);
        border-color: var(--wap-border-strong);
        /* Reference: hover swaps --shadow-card for the deeper --shadow. */
        box-shadow: 0 2px 4px rgba(5,22,80,.05), 0 20px 42px -18px rgba(5,22,80,.18),
                    inset 0 1px 0 rgba(255,255,255,.7);
    }
    .wap-kpi-card:hover::before { opacity: .95; }

    .wap-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .wap-kpi-label {
        font-size: 12px;
        color: var(--wap-text-dim);
        font-weight: 600;
        letter-spacing: .07em;
        text-transform: uppercase;
    }
    /* Jewel chip — deep royal→abyss gradient, light ink icon. */
    .wap-kpi-ico {
        width: 42px; height: 42px;
        border-radius: 13px;
        display: grid;
        place-items: center;
        font-size: 20px;
        color: #dbe4f8;
        flex: none;
        background: linear-gradient(155deg, #1A43BF 0%, #0A2472 60%, #051650 100%);
        border: 1px solid rgba(255,255,255,.14);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.30),
                    inset 0 -10px 16px -10px rgba(0,0,0,.55),
                    0 8px 18px -8px color-mix(in srgb, #123499 70%, transparent);
    }
    .wap-kpi-value {
        font-size: 32px;
        font-weight: 700;
        letter-spacing: -.03em;
        line-height: 1.02;
        color: var(--wap-text);
        font-variant-numeric: tabular-nums;
    }
    .wap-kpi-foot {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 11px;
        font-size: 12px;
        color: var(--wap-text-dim);
    }

    /* ── section caption — reference components.css .section-label ───────── */
    .wap-caption {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--wap-text-mute);
        margin: 14px 0 10px;   /* was 26/12 — see the .wap-tabs note */
    }
    .wap-kpi-grid + .wap-caption { margin-top: 0; }

    /* ── card — reference components.css .card / .card-head / .card-body ── */
    .wap-card {
        background: linear-gradient(180deg, color-mix(in srgb, var(--wap-surface-hi) 20%, var(--wap-surface)), var(--wap-surface));
        border: 1px solid var(--wap-border);
        border-radius: 18px;
        box-shadow: var(--wap-shadow-card), inset 0 1px 0 rgba(255,255,255,.7);
        position: relative;
        overflow: hidden;
        transition: border-color .24s, box-shadow .24s, transform .24s;
    }
    .wap-card + .wap-card { margin-top: 18px; }
    .wap-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--wap-border);
    }
    .wap-card-head h3 {
        font-family: 'Sora', 'Plus Jakarta Sans', 'Inter', sans-serif;
        font-size: 16px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
        color: var(--wap-text);
    }
    .wap-card-head h3 i { color: var(--wap-accent); }
    .wap-card-sub { font-size: 12px; color: var(--wap-text-mute); font-weight: 400; }
    .wap-card-body { padding: 18px 20px; }

    /* ── empty state — reference components.css .empty-state ─────────────
       Plain and centered, no card chrome. */
    .wap-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 60px 24px;
        color: var(--wap-text-dim);
    }
    .wap-empty i { font-size: 40px; color: var(--wap-text-mute); margin-bottom: 14px; }
    .wap-empty-title { color: var(--wap-text); margin-bottom: 6px; font-size: 16px; font-weight: 600; }
    .wap-empty-sub { font-size: 13px; max-width: 480px; }
    .wap-empty-sub a { color: var(--wap-accent); font-weight: 600; }
    .wap-empty .wap-btn { margin-top: 16px; }

    /* ── info banner — reference components.css .build-banner (line 999) ───
       The reference's own tokens: --r-md 14px, --fs-small 13px, accent at 8%
       over a --border-accent hairline. Used by Workshop's board, which states
       what the columns mean; the reference prints it unconditionally there. */
    .wap-banner {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 22px;
        background: color-mix(in srgb, var(--wap-accent) 8%, transparent);
        border: 1px solid var(--wap-border-accent);
        color: var(--wap-text-dim);
        font-size: 13px;
    }
    .wap-banner i { color: var(--wap-accent); font-size: 18px; }

    /* ── kanban board — reference components.css .kanban / .kb-* (752-773) ─
       Workshop's four states side by side. The COLUMNS are fixed structure, so
       they are markup; only the cards inside are data. Columns scroll
       horizontally rather than squashing — grid-auto-columns holds each at a
       268px floor, the reference's value. */
    .wap-kanban {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(268px, 1fr);
        gap: 16px;
        overflow-x: auto;
        padding-bottom: 12px;
        align-items: start;
    }
    .wap-kb-col {
        background: color-mix(in srgb, var(--wap-surface) 60%, var(--wap-bg));
        border: 1px solid var(--wap-border);
        border-radius: 18px;              /* --r-lg */
        display: flex;
        flex-direction: column;
        min-height: 120px;
    }
    .wap-kb-col-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 13px 15px;
        border-bottom: 1px solid var(--wap-border);
    }
    /* --wap-kb is set per column as an inline style: the status colour is a
       VALUE, not a utility (reference UI-CONTRACT §4.3, view.js:374). */
    .wap-kb-col-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: var(--wap-kb, var(--wap-text-mute));
        flex: none;
    }
    .wap-kb-col-title { font-weight: 700; font-size: 13px; }
    .wap-kb-count {
        margin-left: auto;
        font-size: 11px;
        font-weight: 700;
        color: var(--wap-text-mute);
        background: #eef2f9;              /* tokens.css light --surface-3 */
        padding: 1px 8px;
        border-radius: 999px;
        font-variant-numeric: tabular-nums;
    }
    .wap-kb-list {
        padding: 10px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-height: 40px;
    }
    /* The reference drops a full .empty-state into each empty column — 60px
       padding and a 40px glyph. At the 268px column floor that renders four
       very tall boxes, so the column context gets a compact variant. These
       three rules are this shell's own sizing, not transcribed values: restore
       60px/40px/13px to match the reference exactly. */
    .wap-kb-list .wap-empty { padding: 26px 12px; }
    .wap-kb-list .wap-empty i { font-size: 22px; margin-bottom: 8px; }
    .wap-kb-list .wap-empty-sub { font-size: 12px; }

    /* ── board card — reference components.css .kb-card (760-773) ─────────
       The reference's cards are draggable; ours move by posted arrows, so the
       grab cursor is deliberately absent. */
    .wap-kb-card {
        background: var(--wap-surface);
        border: 1px solid var(--wap-border);
        border-radius: 14px;                /* --r-md */
        padding: 12px 13px;
        box-shadow: 0 1px 2px rgba(5,22,80,.07);
        transition: border-color .2s, box-shadow .2s, transform .2s;
    }
    .wap-kb-card:hover {
        border-color: var(--wap-border-strong);
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(5,22,80,.05), 0 20px 42px -18px rgba(5,22,80,.18);
    }
    .wap-kb-card-title {
        font-weight: 600;
        font-size: 13px;
        line-height: 1.35;
        margin-bottom: 3px;
        color: var(--wap-text);
    }
    .wap-kb-card-sub { font-size: 11px; color: var(--wap-text-mute); font-variant-numeric: tabular-nums; }
    .wap-kb-card-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 10px;
        font-size: 11px;
        color: var(--wap-text-mute);
    }
    /* ── drag states — reference components.css .kb-col.drag-over (754) and
       .kb-card.dragging (766). Only woodart-board.js sets these classes, and
       only inside [data-wa-board]. */
    .wap-kb-card[draggable="true"] { cursor: grab; }
    .wap-kb-card[draggable="true"]:active { cursor: grabbing; }
    .wap-kb-card.wap-dragging { opacity: .45; }
    .wap-kb-col.wap-drag-over {
        border-color: var(--wap-accent);
        box-shadow: 0 0 0 1px var(--wap-border-accent),
                    0 12px 40px -14px color-mix(in srgb, var(--wap-accent) 50%, transparent);
    }
    .wap-kb-col.wap-drag-over .wap-kb-list {
        background: color-mix(in srgb, var(--wap-accent) 7%, transparent);
        border-radius: 0 0 18px 18px;
    }
    /* Briefly marks the card that just landed, so the eye can follow it. */
    .wap-kb-card.wap-landed { animation: waLanded 1.1s ease-out; }
    @keyframes waLanded {
        0%   { box-shadow: 0 0 0 2px var(--wap-accent); }
        100% { box-shadow: 0 1px 2px rgba(5,22,80,.07); }
    }
    /* A save that failed: the card snaps back and says so. */
    .wap-kb-card.wap-reverted { animation: waReverted .9s ease-out; }
    @keyframes waReverted {
        0%, 100% { box-shadow: 0 1px 2px rgba(5,22,80,.07); }
        30%      { box-shadow: 0 0 0 2px #c22945; }
    }

    /* The move controls: two posted arrows, one form each. */
    .wap-kb-move { display: flex; gap: 4px; }
    .wap-kb-move button {
        width: 24px;
        height: 24px;
        display: grid;
        place-items: center;
        border-radius: 7px;
        border: 1px solid var(--wap-border);
        background: var(--wap-surface-2);
        color: var(--wap-text-dim);
        font-size: 11px;
        cursor: pointer;
        transition: background .18s, border-color .18s, color .18s;
    }
    .wap-kb-move button:hover {
        background: color-mix(in srgb, var(--wap-accent) 12%, transparent);
        border-color: var(--wap-border-strong);
        color: var(--wap-text);
    }

    /* ── gallery — the portfolio wall (reference projects/view.js:849-881).
       The thumbnail is GENERATED, not a photo: a 135° gradient from the
       project's type colour to its stage colour, with a large translucent
       easel glyph over it. That is the reference's own device — the business
       has no project photography in the system, and a grey placeholder box
       would say "missing image" where this says "portfolio". --}} */
    .wap-gal-grid {
        display: grid;
        gap: 18px;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    }
    .wap-gal-card {
        display: block;
        background: linear-gradient(180deg, color-mix(in srgb, var(--wap-surface-hi) 20%, var(--wap-surface)), var(--wap-surface));
        border: 1px solid var(--wap-border);
        border-radius: 18px;
        overflow: hidden;
        box-shadow: var(--wap-shadow-card), inset 0 1px 0 rgba(255,255,255,.7);
        transition: transform .24s cubic-bezier(.16,.84,.44,1), border-color .24s, box-shadow .24s;
    }
    .wap-gal-card:hover {
        transform: translateY(-3px);
        border-color: var(--wap-border-strong);
        box-shadow: 0 2px 4px rgba(5,22,80,.05), 0 20px 42px -18px rgba(5,22,80,.18),
                    inset 0 1px 0 rgba(255,255,255,.7);
    }
    .wap-gal-thumb {
        position: relative;
        height: 132px;                      /* the reference's own height */
        display: grid;
        place-items: center;
        overflow: hidden;
    }
    .wap-gal-thumb i {
        font-size: 54px;
        color: rgba(255, 255, 255, .34);    /* translucent, sits in the gradient */
    }
    .wap-gal-type {
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(255, 255, 255, .86);
        color: #131a2c;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }
    .wap-gal-body { padding: 14px 16px 16px; }
    .wap-gal-name {
        font-family: 'Sora', 'Plus Jakarta Sans', 'Inter', sans-serif;
        font-size: 14px;
        font-weight: 700;
        line-height: 1.35;
        color: var(--wap-text);
        display: -webkit-box;
        -webkit-line-clamp: 2;              /* two lines, then ellipsis */
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .wap-gal-sub { font-size: 11px; color: var(--wap-text-mute); margin-top: 4px; }
    .wap-gal-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 12px;
        font-size: 12px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--wap-text-dim);
    }

    /* ── badge — reference base.css .badge / -good / -warn / -bad (414-419) ─
       Tone colours are tokens.css --good #23c17e · --warn #f4b740 ·
       --bad #f0506e, each over its own 14% wash. */
    .wap-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .02em;
        background: #eef2f9;                /* --surface-3, light */
        color: var(--wap-text-dim);
        white-space: nowrap;
    }
    .wap-badge-good { background: rgba(35, 193, 126, .14); color: #16955c; }
    .wap-badge-warn { background: rgba(244, 183, 64, .14); color: #9a6b00; }
    .wap-badge-bad  { background: rgba(240, 80, 110, .14); color: #c22945; }

    /* ── progress — reference components.css .progress (731-733) ─────────── */
    .wap-progress {
        height: 8px;
        border-radius: 999px;
        background: #eef2f9;
        overflow: hidden;
        flex: 1;
    }
    .wap-progress-bar {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--wap-accent),
                    color-mix(in srgb, var(--wap-accent) 60%, #fff));
        transition: width .5s cubic-bezier(.16,.84,.44,1);
    }

    /* ── project cards — Projects' Active portfolio ──────────────────────
       The reference builds these in JS (projects/view.js:686-706): one card
       per fit-out carrying a progress bar, a value/cost/margin row and a
       deadline badge. Card chrome matches .wap-card so the two read as one
       family. */
    .wap-proj-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }
    .wap-proj-card {
        background: linear-gradient(180deg, color-mix(in srgb, var(--wap-surface-hi) 20%, var(--wap-surface)), var(--wap-surface));
        border: 1px solid var(--wap-border);
        border-radius: 18px;
        box-shadow: var(--wap-shadow-card), inset 0 1px 0 rgba(255,255,255,.7);
        padding: 17px 18px;
        transition: transform .24s cubic-bezier(.16,.84,.44,1), border-color .24s, box-shadow .24s;
    }
    .wap-proj-card:hover {
        transform: translateY(-3px);
        border-color: var(--wap-border-strong);
        box-shadow: 0 2px 4px rgba(5,22,80,.05), 0 20px 42px -18px rgba(5,22,80,.18),
                    inset 0 1px 0 rgba(255,255,255,.7);
    }
    .wap-proj-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .wap-proj-name {
        font-family: 'Sora', 'Plus Jakarta Sans', 'Inter', sans-serif;
        font-size: 15px;
        font-weight: 700;
        line-height: 1.3;
        color: var(--wap-text);
    }
    .wap-proj-ext {
        font-size: 11px;
        color: var(--wap-text-mute);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        margin-top: 3px;
    }
    .wap-proj-prog { display: flex; align-items: center; gap: 9px; margin: 15px 0 4px; }
    .wap-proj-pct {
        min-width: 38px;
        text-align: right;
        font-size: 12px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--wap-text-dim);
    }
    .wap-proj-stats { display: flex; gap: 14px; margin-top: 13px; }
    .wap-proj-stat { flex: 1; min-width: 0; }
    .wap-proj-stat-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--wap-text-mute);
    }
    .wap-proj-stat-value {
        font-size: 14px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--wap-text);
        margin-top: 2px;
        white-space: nowrap;
    }
    .wap-proj-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid var(--wap-border);
    }
    .wap-proj-designer {
        font-size: 12px;
        color: var(--wap-text-mute);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    /* Card actions — ALWAYS visible.
       They were hover-only at first, on the theory that it kept the grid calm
       while scanning. In use that just made them undiscoverable: the owner
       asked for a delete option that was already there, because nothing on the
       card said so. An action nobody can find is an action that does not exist.
       They sit muted and lift on hover, which keeps the grid calm without
       hiding anything. */
    .wap-proj-acts {
        display: flex;
        gap: 6px;
        margin-left: auto;
        opacity: .75;
        transition: opacity .18s;
    }
    .wap-proj-card:hover .wap-proj-acts,
    .wap-proj-card:focus-within .wap-proj-acts { opacity: 1; }

    .wap-proj-act {
        display: inline-grid;
        place-items: center;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        font-size: 13px;
        color: var(--wap-text-dim);
        background: var(--wap-surface-2);
        border: 1px solid var(--wap-border);
        transition: background .18s, color .18s, border-color .18s;
    }
    .wap-proj-act:hover {
        background: var(--wap-surface-2);
        border-color: var(--wap-border);
        color: var(--wap-text);
    }
    .wap-proj-act-bad:hover { background: rgba(240,80,110,.12); border-color: rgba(240,80,110,.34); color: #c22945; }

    /* ── forms — reference base.css .field / .input / .select ─────────────
       Wood Art's screens are pages, not modals (no <script> may live inside
       [data-wa-view], CLAUDE.md), so entry forms render inline and post
       normally. These selectors exist only on Wood Art pages. */
    .wap-form-grid {
        display: grid;
        gap: 16px 18px;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    }
    .wap-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .wap-field-wide { grid-column: 1 / -1; }
    .wap-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--wap-text-dim);
    }
    .wap-input {
        width: 100%;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid var(--wap-border);
        background: var(--wap-surface);
        color: var(--wap-text);
        font-family: inherit;
        font-size: 14px;
        transition: border-color .2s, box-shadow .2s;
    }
    .wap-input:focus {
        outline: none;
        border-color: var(--wap-accent);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--wap-accent) 18%, transparent);
    }
    .wap-input[readonly] { background: var(--wap-surface-2); color: var(--wap-text-mute); }
    .wap-hint { font-size: 11px; color: var(--wap-text-mute); }
    .wap-error { font-size: 12px; color: #c22945; font-weight: 600; }
    .wap-input-bad { border-color: #c22945; }
    .wap-form-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
        padding-top: 18px;
        border-top: 1px solid var(--wap-border);
    }

    /* ── distribution list — reference components.css .data-row + .progress.
       The reference draws these two panels as canvas charts; rendered as bar
       rows instead, so no chart library and no script are needed. Each row
       carries its own accent via --wap-dist. */
    .wap-dist { display: flex; flex-direction: column; gap: 14px; }
    .wap-dist-row { display: flex; align-items: center; gap: 12px; }
    .wap-dist-main { flex: 1; min-width: 0; }
    .wap-dist-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--wap-text);
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .wap-dist-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--wap-dist, var(--wap-accent)); flex: none; }
    .wap-dist-track {
        height: 8px;
        border-radius: 999px;
        background: #eef2f9;
        overflow: hidden;
        margin-top: 7px;
    }
    .wap-dist-bar {
        height: 100%;
        border-radius: 999px;
        background: var(--wap-dist, var(--wap-accent));
        transition: width .5s cubic-bezier(.16,.84,.44,1);
    }
    .wap-dist-figs { text-align: right; flex: none; min-width: 86px; }
    .wap-dist-value { font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--wap-text); }
    .wap-dist-share { font-size: 11px; color: var(--wap-text-mute); }

    /* A KPI whose source module does not exist yet. Reads as "unknown", never
       as zero — see ProjectsController::milestones(). */
    .wap-kpi-card.wap-kpi-unknown .wap-kpi-value { color: var(--wap-text-mute); }

    /* ── register table — reference components.css .table / .data-grid.
       Wide tables scroll inside their own wrapper rather than pushing the page
       sideways; .wap-table-wrap is that container. */
    .wap-table-wrap { overflow-x: auto; margin: -18px -20px; padding: 0; }
    .wap-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .wap-table th {
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--wap-text-mute);
        padding: 12px 14px;
        border-bottom: 1px solid var(--wap-border);
        white-space: nowrap;
    }
    .wap-table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--wap-border);
        color: var(--wap-text-dim);
        vertical-align: middle;
    }
    .wap-table tbody tr:last-child td { border-bottom: none; }
    .wap-table tbody tr:hover td { background: var(--wap-surface-2); }
    .wap-table .wap-t-strong { color: var(--wap-text); font-weight: 600; }
    .wap-table .wap-t-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .wap-table .wap-t-bad  { color: #c22945; }
    .wap-table .wap-t-good { color: #16955c; }
    .wap-table th.wap-t-num { text-align: right; }
    /* Row-level actions, same vocabulary as the project cards. */
    .wap-table .wap-t-acts { display: flex; gap: 6px; justify-content: flex-end; }

    /* ── search row — a plain GET form above a board or register ─────────── */
    .wap-search {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .wap-search > i {
        position: absolute;
        margin-left: 12px;
        color: var(--wap-text-mute);
        font-size: 13px;
        pointer-events: none;
    }
    .wap-search .wap-input {
        flex: 1 1 260px;
        min-width: 0;
        max-width: 420px;
        padding-left: 32px;                 /* clears the glyph above */
    }

    /* Flash line after a successful save. */
    .wap-flash {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        margin-bottom: 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        background: rgba(35, 193, 126, .12);
        border: 1px solid rgba(35, 193, 126, .32);
        color: #16955c;
    }
</style>

<div class="wap-host">

    {{-- Persistent — outside the swapped region on purpose. --}}
    <div class="wap-scene-layer" aria-hidden="true">
        <div class="wap-scene-anchor" data-wa-scene></div>
    </div>

    {{-- Swapped wholesale by woodart-nav.js. Keep scripts out of here. --}}
    <div class="wap" data-wa-view data-wa-module="{{ $waModule }}" data-wa-section="{{ $section }}">

        {{-- ── page head — one line: icon · title, sub beneath ──
             The reference prints a "WOODART › SPACES & PHASES" eyebrow inline
             before the icon. Dropped on the owner's instruction (2026-08-10):
             the sidebar and the host's breadcrumb already say where you are, so
             it was the third copy of the same thing. --}}
        <div class="wap-head">
            <div>
                <h1 class="wap-title"><i class="bi bi-{{ $waIcon }}"></i> {{ $title }}</h1>
                <p class="wap-sub" title="{{ $subtitle }}">{{ $subtitle }}</p>
            </div>
            <div class="wap-actions">
                @yield('wa-head-actions')
            </div>
        </div>

        {{-- ── section tabs ── --}}
        {{-- $tabsDense is optional: modules that do not set it render exactly
             as before (?? false), so adding it changed no existing screen. --}}
        <nav class="wap-tabs {{ ($tabsDense ?? false) ? 'wap-tabs-dense' : '' }}">
            @foreach($sections as $key => $label)
                <a class="wap-tab {{ $section === $key ? 'active' : '' }}"
                   href="{{ route($waRoute, ['role' => request()->route('role'), 'section' => $key]) }}">{{ $label }}</a>
            @endforeach
        </nav>

        @yield('wa-view')

    </div>
</div>

{{-- Outside [data-wa-view] on purpose: these load once and survive every
     in-suite navigation. A script placed inside the swapped region would be
     replaced without ever re-running. --}}
<script src="{{ asset('woodart/atmosphere/woodart-scene.js') }}" defer></script>
<script src="{{ asset('woodart/nav/woodart-nav.js') }}" defer></script>
{{-- Stage-board drag. Loaded here, outside [data-wa-view], for the same reason
     as the two above: the nav script replaces that region, so a script inside
     it would never run again after the first navigation. It acts only on
     [data-wa-board], which only Wood Art's stage board emits. --}}
<script src="{{ asset('woodart/board/woodart-board.js') }}" defer></script>
@endsection
