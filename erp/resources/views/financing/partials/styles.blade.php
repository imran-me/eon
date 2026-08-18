{{--
    Styles for the Capital & Financing desk.

    Every selector is prefixed .fin- and this partial is included only by the
    desk's own screens, so nothing here can reach another page. Emitted once per
    page via @once even though both screens include it.

    ── DESIGN INTENT ────────────────────────────────────────────────────────
    Matched to the payroll Loan Management desk (resources/views/loans/index),
    because the two desks show the same kind of thing and a user moving between
    them should not have to relearn the page. That means:

      · the same KPI card — resources/views/payroll/partials/kpi is INCLUDED
        rather than reimplemented, so the two can never drift
      · the same table: gradient head, bold uppercase grey labels, divide-y rows
      · the same round index chip and initial avatar
      · rounded-2xl cards, grey-200 hairlines, restrained shadow

    Only what payroll has no equivalent of is invented here: the progress bar,
    the principal/interest split bar and the progress ring.
--}}
@once
<style>
    .fin-scope{
        --fin-ink:#111827;
        --fin-body:#374151;
        --fin-mute:#6b7280;
        --fin-faint:#9ca3af;
        --fin-line:#e5e7eb;
        --fin-soft:#f9fafb;
        --fin-accent:#2563eb;      /* payroll's blue */
        --fin-pos:#16a34a;
        --fin-neg:#dc2626;
    }

    /* ── Page header — same shape as the payroll desk's ───────────────── */
    .fin-hero{ display:flex; flex-direction:column; gap:1rem; margin-bottom:1.5rem; }
    @media(min-width:1024px){ .fin-hero{ flex-direction:row; align-items:center; justify-content:space-between; } }
    .fin-hero h2{ margin:0; font-size:1.25rem; font-weight:800; color:#111827; line-height:1.25; }
    .fin-hero h2 i{ color:var(--fin-accent); margin-right:.4rem; }
    .fin-hero p{ margin:0; font-size:.875rem; color:#6b7280; }
    /* Title and blurb on one baseline. flex-wrap, not nowrap, so a long blurb
       drops to a second line rather than squeezing the title or overflowing. */
    .fin-hero-title{ display:flex; align-items:baseline; gap:.65rem; flex-wrap:wrap; min-width:0; }
    .fin-hero-title h2{ white-space:nowrap; }
    /* Separator in the shadcn manner: a 1px rule of FIXED short height, centred
       on the row — not a full-height border, which would grow with the text and
       read as a blockquote bar rather than a divider. */
    .fin-sep{ flex-shrink:0; width:1px; height:1rem; background:#e5e7eb; align-self:center; border-radius:1px; }
    html.dark .fin-sep{ background:#334155; }
    .fin-hero-badge{ display:none; }   /* payroll leads with the title, not a badge */

    /* ── Cards ────────────────────────────────────────────────────────── */
    .fin-card{ background:#fff; border:1px solid var(--fin-line); border-radius:1rem;
        box-shadow:0 1px 2px 0 rgb(0 0 0 / .05); overflow:hidden; }
    .fin-card + .fin-card{ margin-top:1.5rem; }
    .fin-card-head{ display:flex; align-items:center; justify-content:space-between; gap:.75rem;
        padding:1rem 1.25rem; border-bottom:1px solid var(--fin-line); }
    .fin-card-head strong{ font-size:.9rem; color:#111827; font-weight:700; }

    .fin-note{ display:flex; gap:10px; align-items:flex-start; background:#eff6ff;
        border:1px solid #dbeafe; color:#1e40af; border-radius:.75rem;
        padding:.75rem 1rem; font-size:.8rem; margin-bottom:1.5rem; line-height:1.6; }
    .fin-note i{ margin-top:3px; }

    /* ── Buttons — payroll's blue primary ─────────────────────────────── */
    .fin-btn,.fin-btn-primary,.fin-btn-ghost{ display:inline-flex; align-items:center; gap:.5rem;
        padding:.5rem 1rem; border-radius:.5rem; font-size:.875rem; font-weight:600; cursor:pointer;
        border:1px solid transparent; text-decoration:none; transition:background-color .15s, color .15s; }
    .fin-btn-primary{ background:#2563eb; color:#fff; }
    .fin-btn-primary:hover{ background:#1d4ed8; color:#fff; }
    .fin-btn,.fin-btn-ghost{ background:#fff; color:#374151; border-color:#d1d5db; }
    .fin-btn:hover,.fin-btn-ghost:hover{ background:#f9fafb; color:#111827; }

    /* ── Filter bar ───────────────────────────────────────────────────── */
    .fin-filter{ display:flex; gap:.5rem; flex-wrap:wrap; padding:1rem 1.25rem; border-bottom:1px solid var(--fin-line); }
    .fin-filter input[type=search],.fin-filter select{ height:2.375rem; border:1px solid #d1d5db; border-radius:.5rem;
        padding:0 .75rem; font-size:.875rem; color:#111827; background:#fff; outline:none;
        transition:border-color .15s, box-shadow .15s; }
    .fin-filter input[type=search]{ flex:1 1 260px; min-width:180px; }
    .fin-filter input:focus,.fin-filter select:focus{ border-color:#2563eb; box-shadow:0 0 0 3px rgb(37 99 235 / .12); }

    /* ── Table — payroll's gradient head and divided rows ─────────────── */
    .fin-table{ width:100%; border-collapse:separate; border-spacing:0; }
    .fin-table thead tr{ background:linear-gradient(135deg,#f8fafc 0%, #e2e8f0 100%); }
    .fin-table thead th{ padding:.75rem 1rem; font-size:.75rem; font-weight:700; color:#4b5563;
        text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; text-align:left; }
    .fin-table tbody td{ padding:.75rem 1rem; border-top:1px solid var(--fin-line); font-size:.875rem;
        color:#374151; vertical-align:middle; }
    .fin-table tbody tr{ transition:background-color .12s; }
    .fin-table tbody tr:hover{ background:#f9fafb; }
    .fin-strong{ font-weight:600; color:#1f2937; }
    .fin-sub{ font-size:.6875rem; color:#9ca3af; line-height:1.3; margin-top:.125rem; }
    .fin-dim{ color:#9ca3af; }
    .fin-num{ font-variant-numeric:tabular-nums; white-space:nowrap; }
    .fin-empty{ text-align:center; color:#9ca3af; padding:3rem 1rem; font-size:.875rem; }
    .fin-empty i{ display:block; font-size:1.75rem; opacity:.3; margin-bottom:.75rem; }

    /* Row index as a round chip, exactly as the payroll book prints it. */
    .fin-idx{ display:inline-flex; align-items:center; justify-content:center; width:1.5rem; height:1.5rem;
        border-radius:9999px; background:#f3f4f6; color:#4b5563; font-size:.75rem; font-weight:700; }

    /* Initial avatar — round, blue, two letters. */
    .fin-who{ display:flex; align-items:center; gap:.5rem; }
    .fin-ava{ flex-shrink:0; width:2rem; height:2rem; border-radius:9999px; background:#dbeafe; color:#2563eb;
        display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; text-transform:uppercase; }

    /* ── Progress — no payroll equivalent, kept quiet ─────────────────── */
    .fin-bar{ height:.25rem; border-radius:9999px; background:#e5e7eb; overflow:hidden; margin-top:.375rem; min-width:6rem; }
    .fin-bar > i{ display:block; height:100%; border-radius:9999px; background:#2563eb;
        transition:width .45s cubic-bezier(.4,0,.2,1); }
    .fin-bar.is-done > i{ background:#16a34a; }

    .fin-split{ display:flex; height:.25rem; border-radius:9999px; overflow:hidden; background:#e5e7eb;
        min-width:5rem; margin-top:.375rem; }
    .fin-split .p{ background:#2563eb; }
    .fin-split .i{ background:#f59e0b; }

    /* ── Status pills — payroll uses filled chips ─────────────────────── */
    .fin-chip{ display:inline-flex; align-items:center; gap:.25rem; padding:.125rem .625rem; border-radius:9999px;
        font-size:.6875rem; font-weight:700; background:#f3f4f6; color:#4b5563; text-transform:capitalize; }
    .fin-chip-active{ background:#dcfce7; color:#166534; }
    .fin-chip-paid{ background:#dcfce7; color:#166534; }
    .fin-chip-closed{ background:#e0e7ff; color:#3730a3; }
    .fin-chip-written_off{ background:#fee2e2; color:#991b1b; }
    .fin-chip-cancelled{ background:#f3f4f6; color:#6b7280; }
    .fin-chip-due{ background:#fef3c7; color:#92400e; }
    .fin-chip-partial{ background:#ffedd5; color:#9a3412; }
    .fin-chip-waived{ background:#f3f4f6; color:#6b7280; }
    .fin-late{ display:inline-block; margin-left:.375rem; font-size:.625rem; font-weight:700; color:#dc2626;
        text-transform:uppercase; letter-spacing:.03em; }

    /* ── Ring ─────────────────────────────────────────────────────────── */
    .fin-ring{ display:flex; align-items:center; gap:1rem; }
    .fin-ring svg{ transform:rotate(-90deg); flex-shrink:0; }
    .fin-ring .track{ stroke:#e5e7eb; }
    .fin-ring .fill{ stroke:#2563eb; stroke-linecap:round; transition:stroke-dashoffset .6s cubic-bezier(.4,0,.2,1); }
    .fin-ring-label .v{ font-size:1.5rem; font-weight:800; color:#111827; line-height:1.1; font-variant-numeric:tabular-nums; }
    .fin-ring-label .k{ font-size:.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#9ca3af; margin-top:.125rem; }

    .fin-legend{ display:flex; gap:1rem; flex-wrap:wrap; font-size:.6875rem; color:#9ca3af; font-weight:600; }
    .fin-legend span{ display:inline-flex; align-items:center; gap:.375rem; }
    .fin-legend i{ width:.875rem; height:.25rem; border-radius:9999px; display:inline-block; }

    .fin-pager{ padding:1rem 1.25rem; border-top:1px solid var(--fin-line); }

    /* ── Modal ────────────────────────────────────────────────────────── */
    .fin-modal{ display:none; position:fixed; inset:0; background:rgb(17 24 39 / .5); z-index:1000;
        align-items:center; justify-content:center; padding:1rem; }
    .fin-modal-box{ background:#fff; border-radius:1rem; width:100%; max-width:46rem; max-height:92vh;
        overflow-y:auto; box-shadow:0 25px 50px -12px rgb(0 0 0 / .25); animation:finPop .16s ease-out; }
    @keyframes finPop{ from{ opacity:0; transform:translateY(8px); } to{ opacity:1; transform:none; } }
    .fin-modal-head{ padding:1.25rem 1.5rem; border-bottom:1px solid var(--fin-line); }
    .fin-modal-head h3{ margin:0; font-size:1.05rem; font-weight:800; color:#111827; }
    .fin-modal-head p{ margin:.25rem 0 0; font-size:.8rem; color:#6b7280; }
    .fin-modal-body{ padding:1.25rem 1.5rem; }
    .fin-modal-foot{ padding:1rem 1.5rem; border-top:1px solid var(--fin-line); display:flex;
        justify-content:flex-end; gap:.5rem; background:#f9fafb; }

    /* Column gap stays at 1rem — the columns read fine. The ROW gap is split out
       and cut hard, because it was never really 1rem: every .fin-field also
       carries margin-bottom:1rem, so stacked rows inside the grid were paying
       both and sitting 2rem apart. Zeroing the margin inside the grid leaves
       row-gap as the single control, which is what makes the number below mean
       what it says. */
    .fin-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));
        column-gap:1rem; row-gap:.3rem; }
    .fin-field{ margin-bottom:1rem; }
    .fin-grid > .fin-field{ margin-bottom:0; }
    .fin-field label{ display:block; font-size:.75rem; font-weight:700; color:#4b5563; margin-bottom:.375rem; }
    .fin-field label span{ color:#dc2626; }
    .fin-field input,.fin-field select,.fin-field textarea{ width:100%; border:1px solid #d1d5db;
        border-radius:.5rem; padding:.5rem .75rem; font-size:.875rem; color:#111827; background:#fff; outline:none;
        transition:border-color .15s, box-shadow .15s; }
    .fin-field input:focus,.fin-field select:focus,.fin-field textarea:focus{ border-color:#2563eb;
        box-shadow:0 0 0 3px rgb(37 99 235 / .12); }
    .fin-hint{ display:flex; gap:.5rem; align-items:flex-start; font-size:.78rem; color:#6b7280;
        background:#f9fafb; border:1px solid var(--fin-line); border-radius:.5rem; padding:.625rem .75rem;
        margin:0; line-height:1.6; }

    /* ── Segmented choice ─────────────────────────────────────────────────
       For a binary that changes the rest of the form. Two buttons show both
       options and their consequence at once; a dropdown hides the alternative
       behind a click and gives no room to explain either. Radios underneath, so
       it is keyboard reachable and submits without any JavaScript. */
    .fin-seg{ display:grid; grid-template-columns:1fr 1fr; gap:.625rem; }
    .fin-seg-opt{ position:relative; margin:0; cursor:pointer; }
    .fin-seg-opt input{ position:absolute; opacity:0; width:0; height:0; }
    .fin-seg-opt > span{ display:flex; flex-direction:column; gap:.125rem; padding:.75rem .875rem;
        border:1px solid #d1d5db; border-radius:.625rem; background:#fff; transition:border-color .15s, background-color .15s, box-shadow .15s;
        font-size:.875rem; font-weight:600; color:#374151; }
    .fin-seg-opt > span i{ font-size:.8rem; color:#9ca3af; margin-right:.375rem; transition:color .15s; }
    .fin-seg-opt > span small{ font-size:.7rem; font-weight:400; color:#9ca3af; line-height:1.35; }
    .fin-seg-opt:hover > span{ border-color:#9ca3af; background:#f9fafb; }
    .fin-seg-opt input:checked + span{ border-color:#2563eb; background:#eff6ff; color:#1e3a8a;
        box-shadow:0 0 0 3px rgb(37 99 235 / .12); }
    .fin-seg-opt input:checked + span i{ color:#2563eb; }
    .fin-seg-opt input:focus-visible + span{ outline:2px solid #2563eb; outline-offset:2px; }
    @media(max-width:520px){ .fin-seg{ grid-template-columns:1fr; } }

    /* Whose debt it is AND what shape it takes, asked as one grid.
       Two cards — company and personal — each offering fixed or running. The
       two questions are genuinely independent, but they are always answered
       together, and stacking them as separate rows made the form read as four
       decisions instead of one. Four radios in a single group, so exactly one
       combination can be live and the browser enforces it without script. */
    .fin-quad{ display:grid; grid-template-columns:1fr 1fr; gap:.85rem; }

    /* Colour IS the meaning here, not decoration. Blue is money that lands on
       the company's balance sheet; amber is money that never does. Someone who
       learns that once can read the answer from across the desk, and the two
       are never confusable at a glance — which is the whole point, because
       confusing them is the expensive mistake this form exists to prevent. */
    .fin-quad-card{ position:relative; border:1px solid #e5e7eb; border-radius:.75rem; background:#fff;
        overflow:hidden; transition:border-color .18s, box-shadow .18s; }
    .fin-quad-card--co  { --q:#2563eb; --q-soft:rgb(37 99 235 / .13); --q-tint:#eff6ff; }
    .fin-quad-card--pers{ --q:#d97706; --q-soft:rgb(217 119 6 / .13);  --q-tint:#fffbeb; }

    /* A rail down the edge, dim until chosen — the card reads as "available"
       rather than "empty" before anything is picked. */
    .fin-quad-card::before{ content:''; position:absolute; top:0; bottom:0; left:0; width:3px;
        background:var(--q); opacity:.3; transition:opacity .18s; }
    .fin-quad-card:has(input:checked){ border-color:var(--q); box-shadow:0 0 0 3px var(--q-soft); }
    .fin-quad-card:has(input:checked)::before{ opacity:1; }

    .fin-quad-head{ padding:.42rem .75rem .06rem; font-size:.82rem; font-weight:650; color:#374151;
        display:flex; align-items:center; gap:.35rem; }
    .fin-quad-head i{ font-size:.76rem; color:#9ca3af; transition:color .18s; }
    .fin-quad-card:has(input:checked) .fin-quad-head{ color:#111827; }
    .fin-quad-card:has(input:checked) .fin-quad-head i{ color:var(--q); }

    /* Says where the money ends up, in three words, before any choice is made. */
    .fin-quad-tag{ margin-left:auto; font-size:.55rem; font-weight:700; letter-spacing:.04em;
        text-transform:uppercase; padding:.11rem .34rem; border-radius:.28rem;
        background:var(--q-tint); color:var(--q); border:1px solid var(--q-soft); white-space:nowrap; }

    .fin-quad-desc{ margin:0; padding:0 .75rem .42rem; font-size:.655rem; font-weight:400; color:#9ca3af; line-height:1.35; }

    .fin-quad-opts{ display:grid; grid-template-columns:1fr 1fr; border-top:1px solid #f1f5f9; }
    .fin-quad-opt{ position:relative; margin:0; cursor:pointer; }
    .fin-quad-opt + .fin-quad-opt{ border-left:1px solid #f1f5f9; }
    .fin-quad-opt input{ position:absolute; opacity:0; width:0; height:0; }
    .fin-quad-opt > span{ display:flex; flex-direction:column; gap:.05rem; padding:.4rem .65rem .44rem;
        background:#fff; transition:background-color .18s, color .18s;
        font-size:.76rem; font-weight:600; color:#4b5563; }
    .fin-quad-opt > span i{ font-size:.68rem; color:#9ca3af; margin-right:.28rem; transition:color .18s; }
    .fin-quad-opt > span small{ font-size:.615rem; font-weight:400; color:#9ca3af; line-height:1.25; }
    .fin-quad-opt:hover > span{ background:#fafafa; }
    .fin-quad-opt input:checked + span{ background:var(--q-tint); color:#111827; }
    .fin-quad-opt input:checked + span i{ color:var(--q); }
    .fin-quad-opt input:focus-visible + span{ outline:2px solid var(--q); outline-offset:-2px; }

    /* The tick only appears on the live choice. Two cards each showing a
       highlighted half would otherwise read as two answers. */
    .fin-quad-tick{ position:absolute; top:.36rem; right:.45rem; font-size:.58rem; color:var(--q);
        opacity:0; transform:scale(.6); transition:opacity .18s, transform .18s; }
    .fin-quad-opt input:checked ~ .fin-quad-tick{ opacity:1; transform:scale(1); }

    /* What saving will actually DO, in one sentence that changes with the
       choice. The consequence is the thing people get wrong, so it is stated
       before the save rather than discovered after it. */
    .fin-quad-out{ margin:.45rem 0 0; padding:.42rem .6rem; border-radius:.45rem;
        border:1px solid var(--qo-soft,#e5e7eb); background:var(--qo-tint,#f9fafb);
        font-size:.665rem; line-height:1.45; color:#4b5563; display:flex; gap:.4rem; align-items:flex-start; }
    .fin-quad-out i{ color:var(--qo,#9ca3af); margin-top:.1rem; flex:none; font-size:.7rem; }
    .fin-quad-out strong{ color:#111827; font-weight:650; }

    @media(max-width:520px){ .fin-quad{ grid-template-columns:1fr; } }

    @media(max-width:640px){
        .fin-table thead th,.fin-table tbody td{ padding:.625rem .5rem; }
    }

    html.dark .fin-card,html.dark .fin-modal-box{ background:#1e293b; border-color:#334155; }
    html.dark .fin-card-head,html.dark .fin-filter,html.dark .fin-pager{ border-color:#334155; }
    html.dark .fin-card-head strong,html.dark .fin-hero h2{ color:#e2e8f0; }
    html.dark .fin-table thead tr{ background:#0f172a; }
    html.dark .fin-table thead th{ color:#94a3b8; }
    html.dark .fin-table tbody td{ color:#cbd5e1; border-color:#334155; }
    html.dark .fin-table tbody tr:hover{ background:#0f172a; }
    html.dark .fin-strong{ color:#f1f5f9; }
    html.dark .fin-filter input,html.dark .fin-filter select,
    html.dark .fin-field input,html.dark .fin-field select,html.dark .fin-field textarea{
        background:#0f172a; border-color:#334155; color:#e2e8f0; }

    /* An explanation that waits to be asked for.
       Always-open hint text pushes the fields somebody actually came to fill
       below the fold, and after the second reading nobody sees the words
       anyway — so the sentence stays, but folded into the label until wanted. */
    .fin-i-wrap{ position:relative; display:inline-block; }
    .fin-i{ display:inline-flex; align-items:center; justify-content:center;
        width:14px; height:14px; margin-left:.28rem; padding:0; border:0; border-radius:50%;
        background:#e5e7eb; color:#6b7280; cursor:pointer; vertical-align:middle;
        font-size:.57rem; font-weight:700; font-style:normal; line-height:1;
        font-family:inherit; text-transform:none; letter-spacing:0;
        transition:background-color .15s, color .15s; }
    .fin-i:hover{ background:#94a3b8; color:#fff; }
    .fin-i-wrap.is-open .fin-i{ background:#2563eb; color:#fff; }

    /* FIXED, not absolute, and placed by script.
       .fin-modal-box scrolls (overflow-y:auto), which makes it a clipping box:
       an absolutely-positioned popover inside it gets cut off at the modal's
       edge — which is exactly what happened on the right-hand column, where the
       text was sliced mid-word. Fixed positioning leaves that box entirely, so
       the popover can never be clipped by anything, and the script then keeps it
       inside the viewport instead. z-index clears the modal's own 1000.

       No arrow: a fixed box that flips above or shifts sideways to stay on
       screen cannot keep a tail pointing at its button without more machinery
       than a hint deserves. */
    .fin-i-pop{ position:fixed; top:0; left:0; z-index:1200;
        width:min(19rem, calc(100vw - 1.5rem)); padding:.55rem .7rem;
        border:1px solid #e5e7eb; border-radius:.5rem; background:#fff;
        box-shadow:0 16px 34px -12px rgb(15 23 42 / .38);
        font-size:.675rem; font-weight:400; line-height:1.5; color:#4b5563;
        text-transform:none; letter-spacing:0; text-align:left; white-space:normal;
        opacity:0; visibility:hidden; transform:translateY(-4px);
        transition:opacity .14s, transform .14s, visibility .14s; }
    .fin-i-wrap.is-open .fin-i-pop{ opacity:1; visibility:visible; transform:translateY(0); }
    .fin-i-pop strong{ color:#111827; font-weight:650; }

    html.dark .fin-i-pop{ background:#0f172a; border-color:#334155; color:#cbd5e1; }
    html.dark .fin-i-pop strong{ color:#e2e8f0; }
</style>

{{-- Kept beside its own CSS and inside the same @once, so it loads exactly where
     the markup that needs it does — and only on financing pages. --}}
<script>
    (function () {
        var GAP = 6, EDGE = 10;

        function finCloseInfo(except) {
            document.querySelectorAll('.fin-i-wrap.is-open').forEach(function (w) {
                if (w === except) return;
                w.classList.remove('is-open');
                var b = w.querySelector('.fin-i');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        }

        /*
         * Put the box under its button, then pull it back on screen.
         *
         * Measured rather than assumed: the same popover sits in the left column
         * on one field and the right column on another, and the right one runs
         * off the modal unless it is shifted. Height is read the same way so a
         * popover near the bottom flips above its button instead of hanging off.
         *
         * offsetWidth works here because the closed state uses `visibility`, not
         * `display:none` — the box is laid out all along, just not painted.
         */
        function finPlaceInfo(wrap) {
            var btn = wrap.querySelector('.fin-i');
            var pop = wrap.querySelector('.fin-i-pop');
            if (!btn || !pop) return;

            var r = btn.getBoundingClientRect();
            var w = pop.offsetWidth;
            var h = pop.offsetHeight;

            var left = Math.min(r.left, window.innerWidth - w - EDGE);
            if (left < EDGE) left = EDGE;

            // Below by default; above when there is no room below but there is
            // above. Clamped either way so it can never sit off the top.
            var top = r.bottom + GAP;
            if (top + h > window.innerHeight - EDGE) {
                var above = r.top - h - GAP;
                top = above >= EDGE ? above : Math.max(EDGE, window.innerHeight - h - EDGE);
            }

            pop.style.left = left + 'px';
            pop.style.top  = top + 'px';
        }

        // Delegated, so popovers inside a modal that is built later still work.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.fin-i');

            if (!btn) {
                // A click inside the box itself must not dismiss it — people
                // select text out of these.
                if (!e.target.closest('.fin-i-pop')) finCloseInfo(null);
                return;
            }

            // Inside a <form>: without this the button would submit it.
            e.preventDefault();

            var wrap = btn.closest('.fin-i-wrap');
            if (!wrap) return;

            var isOpen = wrap.classList.contains('is-open');
            finCloseInfo(wrap);
            wrap.classList.toggle('is-open', !isOpen);
            btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            if (!isOpen) finPlaceInfo(wrap);
        });

        // Fixed coordinates go stale the moment anything scrolls, and the modal
        // body is itself a scroller — so follow the button rather than leaving a
        // box floating over unrelated content. Capture phase, because scrolling
        // happens on an inner element and does not bubble.
        function finReflow() {
            document.querySelectorAll('.fin-i-wrap.is-open').forEach(finPlaceInfo);
        }
        window.addEventListener('scroll', finReflow, true);
        window.addEventListener('resize', finReflow);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') finCloseInfo(null);
        });
    })();
</script>
@endonce
