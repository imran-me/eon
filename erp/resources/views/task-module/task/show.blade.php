@extends('layout.app')

@section('main-content')
@php
    $role = Str::slug(auth()->user()->getRoleNames()->first());
@endphp

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">

{{--
  Work-item progress bars — transcribed from the reference build's .progress /
  .kb-prog / .progress-bar.fluid (platform/design-system/css/components.css).

  Kept in this view rather than resources/css/app.css on purpose: app.css is the
  shared bundle every one of the twelve companies loads on every page, and a
  keyframe plus four rules do not belong there for one screen's benefit. Every
  selector is prefixed tp- so it cannot reach anything else even here.
--}}
<style>
    .tp-track { height: 8px; border-radius: 999px; background: #e5e7eb; overflow: hidden; }
    .tp-track.tp-lg { height: 12px; }
    .tp-fill {
        height: 100%; border-radius: 999px;
        background: linear-gradient(90deg, #2563eb, #60a5fa);
        transition: width .45s cubic-bezier(.22,.61,.36,1);
    }
    .tp-pct { font-size: 11px; font-weight: 700; color: #6b7280; font-variant-numeric: tabular-nums; }

    /* The drawer's bar: renders at 0 and is pushed to its real width one frame
       later, so it fills every time a task is opened. */
    .tp-fill.tp-fluid { position: relative; overflow: hidden; transition: width 1.1s cubic-bezier(.22,.61,.36,1); }
    .tp-fill.tp-fluid::after {
        content: ''; position: absolute; inset: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.4), transparent);
        transform: translateX(-100%);
        animation: tpShimmer 2.2s ease-in-out infinite;
    }
    @keyframes tpShimmer { 0% { transform: translateX(-100%); } 60%, 100% { transform: translateX(220%); } }

    /* A bar that fills and shimmers is motion; honour the OS setting. */
    @media (prefers-reduced-motion: reduce) {
        .tp-fill { transition: none; }
        .tp-fill.tp-fluid::after { animation: none; }
    }

    /* ── The card's progress dial ──────────────────────────────────────────
       Style 06 "Ring", transcribed from the owner's task-card-20-styles
       comparison page and measured onto this card: an SVG dial, two circles
       with pathLength=100 so the percentage IS the dash offset, round caps,
       and the number sitting in the well in grey. It replaces the full-width
       bar that used to run under the title, and hands the card back the row
       that bar was spending. The DRAWER keeps its bar — there is width to
       spend there and only one task to read.

       Every number here is derived, not chosen by eye:
         32px    the reference's ring, and 1.4x the 23px line box of the title
                 it sits beside — present without shouting.
         3px     the reference's stroke. An 8px one (the first attempt) read as
                 a doughnut, not an instrument.
         10px    the card's own padding. Insetting the dial by the same amount
                 puts it on the card's grid: its top and right edges line up
                 with the content box, not with an arbitrary offset.
         r=14.5  what makes a 3px stroke meet the 32-unit viewBox exactly — the
                 stroke straddles the path, so it runs r13 → r16. Change either
                 and the other has to follow.

       Colour comes from --kbc-strong, the column accent already painting the
       card's left rail: the reference's status-colour rule in our palette —
       to-do blue, in-progress yellow, done green. Nothing to keep in sync
       across two languages.

       A sibling of the title, not a child: the title is clamped to two lines
       with overflow:hidden, which cut a dial carried inside it. The title
       sits beside it in .tk-head, which reserves the lane. */
    .tp-ring {
        /* Its own lane at the right of the title row — a flex item, not an
           absolutely positioned one.

           It was absolute, centred in the card's body with a measured offset,
           and that worked only while every card was exactly 150px tall. Now
           that a card is as tall as its content, no fixed offset can be right
           for both a 115px card and a 131px one — the dial landed on the chip
           row of the short ones. As a flex item in .tk-head it is centred
           against the title itself, whatever height that title happens to be,
           and the row reserves its lane so nothing can run underneath it. */
        flex: none;
        width: 36px; height: 36px;
        /* Dropped 15px below where the row centres it (owner's call). position
           is relative, not a margin, so the dial keeps its slot in the row and
           nothing else on the card moves with it. */
        position: relative; top: 15px;
        /* Floating: a wide soft cast for the height and a tight one at the
           contact point, the same two-layer trick the card itself uses.

           box-shadow, NOT filter: drop-shadow() pushes the whole SVG through a
           raster surface, and at 90% browser zoom that surface is resampled —
           which is what made the band's edges and the percentage look soft.
           border-radius makes the shadow follow the circle rather than the
           element's square box. */
        border-radius: 50%;
        box-shadow: 0 4px 5px rgba(30, 58, 138, .28), 0 1px 1px rgba(30, 58, 138, .16);
        /* No rotation here any more — see .tp-bg / .tp-fg. The element keeps a
           transform only for the hover lift. */
        transform: scale(1);
        transition: transform .25s cubic-bezier(.22,.8,.28,1), opacity .18s ease;
    }
    /* The My Tasks list draws a different card: its ⋯ is permanent and owns the
       corner, and nothing there is clamped. So the dial floats into the title
       and the text wraps around it, inside the pr-10 lane that card already
       reserves. Same dial, same rules, one property different. */
    .tp-ring.tp-inline {
        position: static; float: right;
        margin: 1px 0 2px 10px;
    }

    /* Stroke is 5 of the 36-unit box — the reference's proportion, a fat band
       rather than a hairline — and r=15.5 is what makes it meet the edge: the
       stroke straddles the path, so it runs r13 → r18. Round caps, as there. */
    .tp-ring circle {
        fill: none; stroke-width: 5; stroke-linecap: round;
        /* Twelve o'clock start, applied to the geometry rather than to the
           element, so the number inside is never rotated and back again. */
        transform: rotate(-90deg);
        transform-box: view-box; transform-origin: center;
    }

    /* The groove. A cool blue-grey rather than neutral grey, so the track reads
       as the unfilled part of the SAME object as the arc instead of a separate
       grey ring behind it. */
    .tp-ring .tp-bg { stroke: #dde5f3; transition: stroke .25s ease; }

    /* The plate, and the hairline that ends it. Without that line the plate's
       pale edge ran straight into the track's pale edge and the well had no
       bottom. */
    .tp-ring .tp-plate { stroke: none; }
    .tp-ring .tp-well { fill: none; stroke: rgba(9, 32, 84, .10); stroke-width: .8; }

    .tp-ring .tp-bg-shade { fill: none; }
    .tp-ring .tp-fg {
        /* stroke is a gradient, set per element — an SVG paint server has to be
           referenced by id, and each dial carries its own so two on screen
           cannot collide. The three passes overlap by a hair so no white
           hairline can show between them. */
        stroke-dasharray: 100 100;
        stroke-dashoffset: calc(100 - var(--tp-p, 0));
        /* Draws itself in when the board paints, and slides — never jumps — to
           the new value when a sub-item is ticked. */
        animation: tpDraw .8s cubic-bezier(.22,.8,.28,1);
        transition: stroke-dashoffset .45s cubic-bezier(.22,.8,.28,1);
    }
    .tp-ring .tp-pct {
        font-size: 10px; font-weight: 700; fill: #35415a;
        font-variant-numeric: tabular-nums; letter-spacing: -.03em;
        text-rendering: geometricPrecision;
        transition: fill .25s ease;
    }
    /* 100% is the one value that will not fit "100%" at 10px in a 26px well, so
       that one drops a size rather than the label being dropped. */
    .tp-ring.tp-full .tp-pct { font-size: 8.5px; }

    /* Hover, the reference's own: the arc redraws itself. tpRedraw is a second
       set of identical frames rather than a reuse of tpDraw — re-declaring a
       running animation's name does not restart it, a different name does.
       The dial also lifts 8% and its track deepens, so the whole thing reads as
       one gesture rather than a loop playing on its own. */
    .group:hover .tp-ring { transform: scale(1.08); }
    .group:hover .tp-ring .tp-bg { stroke: #dfe3ee; }
    .group:hover .tp-ring .tp-pct { fill: #141729; }
    .group:hover .tp-ring .tp-fg { animation: tpRedraw .8s cubic-bezier(.22,.8,.28,1); }

    @keyframes tpDraw   { from { stroke-dashoffset: 100; } }
    @keyframes tpRedraw { from { stroke-dashoffset: 100; } }

    /* The ⋯ button wants this exact corner, and dots over a dial read as
       neither — so the dial steps aside for it, the same way the ribbon
       already does. Only while the cursor is on the button itself: the menu
       reveals on its own 28px, not on the whole card, so this costs the dial
       nothing while you are merely reading the card. */
    [data-role="card-menu"]:hover ~ .tk-head .tp-ring { opacity: 0; transform: scale(.92); }

    @media (prefers-reduced-motion: reduce) {
        .tp-ring, .tp-ring .tp-fg, .tp-ring .tp-bg, .tp-ring .tp-pct { animation: none; transition: none; }
        .group:hover .tp-ring { transform: scale(1); }
    }
    /* ── Card footer ───────────────────────────────────────────────────────
       The bottom strip of a Kanban card — who and what on the left, what you
       can do on the right — cut off from the task itself by a full-bleed rule.

       The -12px sides cancel the card's p-3 so the rule reaches both edges,
       and the 12px padding puts the content back where it was: only the line
       runs wider, nothing moves sideways.

       Written here rather than as utilities because of the hover sweep below,
       which no utility expresses — and for the same reason as the tp- rules
       above: app.css is the bundle all twelve companies load on every page,
       and this is one screen. */
    /* 30px, stated as a height rather than added up from padding: 4px gap, then
       a 26px strip (the 1px rule + a 25px row). Every card's footer is that
       strip, whatever is in it — a card with three labels and one with none end
       at the same line, because the height is not the content's to decide.

       25px is the floor: the move arrows and the sub-work chip are 24px boxes
       and clip below that. */
    .tk-foot {
        position: relative;
        flex: 0 0 auto;
        height: 26px;
        margin: 4px calc(-1 * var(--tk-pad, 10px)) 0;
        padding: 0 var(--tk-pad, 10px);
        /* #eceff1, not #f1f3f5: the separator was 14 steps off white and read as
           a smudge rather than a rule. Each channel's distance from white is
           35% longer here — the line the owner asked for, still quiet enough
           not to cut the card in two. */
        border-top: 1px solid #eceff1;
    }

    /* The footer runs to the card's BOTTOM EDGE, not to the top of the card's
       bottom padding.

       This is what made the row look high no matter how well its own contents
       were centred. The strip you actually see is the separator down to the
       card's inner edge — 36px. The footer element was only 26 of that, with the
       card's 10px padding left empty underneath, so a row perfectly centred
       inside the footer still printed 4.5px above the centre of the strip
       (measured: items on 92, strip centre 96.5). Cancelling the padding with a
       negative margin and taking the height back makes the two the same box, and
       the flex centring lands where the eye expects.

       Not on an expanded card: the sub-work panel renders BELOW the footer, and
       a negative margin there would pull the footer over the top of it. */
    .tk-card:not(.tk-expanded) .tk-foot {
        height: calc(26px + var(--tk-pad, 10px));
        margin-bottom: calc(-1 * var(--tk-pad, 10px));
    }

    /* Every control in the footer on ONE centre line.

       The two dropdown toggles on the left are an inline-block wrapper around an
       inline-flex button, so their vertical position came off the text baseline
       — and a baseline depends on what is inside the button. The assignee's 14px
       icon and the label's 12px one therefore sat a pixel apart (measured: mid
       12.5 against 13.5, with the count chip and arrows on 13.5). Making the
       wrapper a flex box takes the baseline out of the equation: the row centres
       every item itself, and they all land on the same line.

       height:24px on top of that gives every control in the row the SAME box,
       not just the same centre. It changes nothing at 100% zoom — but the board
       is worked at 90%, where a 20px box and a 24px box round to device pixels
       differently and the two halves of the row drift apart by one. Same box,
       same rounding, at any zoom. */
    .tk-foot .relative > .inline-block { display: flex; align-items: center; height: 24px; }

    /* Row 1's shrink order, now that the priority carries its word.

       Measured: priority + both date stamps is 253.7px of the 258 available on
       a High card, and 265 on a Medium one — the longest word. The row cannot
       hold all three at their full width in that one case, so this says which
       one gives: the priority chip never shrinks, and a date ends in an ellipsis
       instead of being cut mid-glyph by the group's overflow:hidden.

       The 11px it is short by lives in the seconds of the two date stamps
       ("19:04:00 16/08/26"), which are the only spendable pixels on the row —
       that is the owner's call to make, not mine, so for now the date gives. */
    .tk-meta .relative:has([id^="priority-icon-"]) { flex: none; }
    .tk-meta [id^="startdate-label-"],
    .tk-meta [id^="duedate-label-"] {
        display: block; min-width: 0;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    /* A flex item will not shrink below its content unless every box between the
       row and the text says it may — the wrapper and the button included, not
       just the label. */
    /* Dropped 20px, the dial now hangs over the chip row's right end — measured,
       a card with both date stamps ran its due date underneath it. The row keeps
       the same lane the title does, so nothing can pass behind the dial. */
    .tk-card:has(.tp-ring) .tk-meta { padding-right: 50px; }

    .tk-meta .relative,
    .tk-meta .relative * { min-width: 0; }
    /* The toggle is not itself a flex item of the row, so shrinking its wrapper
       does nothing until the button is told it may not exceed it. */
    .tk-meta .relative > button { max-width: 100%; }

    /* Hover: colour runs left→right along the rule, in the card's own accent —
       the same --kbc-strong the left rail is painted with, read off the column
       exactly as .kbc-col .task-item does. One card cannot show two colours,
       and a card that changes column brings its new colour with it with no JS
       on any of the four move paths. #2563eb is the fallback for anywhere a
       card is rendered outside a .kbc-col wrapper (My Tasks).

       Painted by an overlay sitting ON the grey border, not by recolouring the
       border itself — the resting line is never removed and has no width of
       its own to change, so the footer cannot shift by a pixel between the
       hovered and unhovered card. scaleX on a 1px strip is a compositor-only
       animation; it costs nothing on a column of thirty cards. */
    .tk-foot::after {
        content: '';
        position: absolute;
        top: -1px; left: 0; right: 0;
        height: 1px;
        background: var(--kbc-strong, #2563eb);
        transform: scaleX(0);
        transform-origin: left center;
        transition: transform .45s cubic-bezier(.22,.61,.36,1);
    }
    .task-item:hover .tk-foot::after { transform: scaleX(1); }

    /* Labels never wrap inside the footer. The strip is a fixed 26px, so a
       second row of badges would be sliced through the middle rather than
       growing the card. They run on one line and the cluster clips at the
       avatar side's overflow-hidden; the full list is in the toggle's tooltip
       and in the drawer.

       Scoped to .tk-foot on purpose: the same badge markup renders on the My
       Tasks cards, which sit in an auto-height grid where wrapping is right. */
    .tk-foot [data-role="dropdown-label"] { flex-wrap: nowrap; }
    /* …and no badge folds its own text either: a shrunk badge would wrap
       "Finance Team" onto two lines and get sliced by the same 26px. */
    .tk-foot [data-role="dropdown-label"] > * { flex: 0 0 auto; white-space: nowrap; }

    @media (prefers-reduced-motion: reduce) {
        .tk-foot::after { transition: none; }
    }

    /* The sub-work ring in the footer. Same pair the bar under the title
       counts; stroke-dashoffset is written by task-subtask.js on every change,
       so the ring, the bar and the "2/3" can never disagree. */
    .tk-ring-fill { transition: stroke-dashoffset .45s cubic-bezier(.22,.61,.36,1); }
    @media (prefers-reduced-motion: reduce) {
        .tk-ring-fill { transition: none; }
    }

    /* ── Card footprint ────────────────────────────────────────────────────
       A card is 283 × 150 CSS px. The board is worked at 90% browser zoom,
       where that renders as 255 × 135 on screen — the size it is designed to.

       Every dimension of the card is stated in px against that one pair, so
       the zoom scales the card as a unit and its proportions never change:
       80% → 227 × 120, 90% → 255 × 135, 100% → 283 × 150. Nothing here is a
       percentage of the viewport, and nothing reflows at a breakpoint; a
       narrower window shows fewer columns, never a differently-shaped card.

       The two numbers are the only thing to edit. The column derives its width
       from them: card + the column's own p-3 (12px a side) + the list's p-1
       (4px a side) + 12px of scrollbar gutter, reserved with scrollbar-gutter
       so a column that scrolls takes the width out of the gutter and not out
       of the cards. 12+4+12+4 = 44. */
    .kbc-col {
        --tk-card-w: 283px;
        /* The floor a card cannot go below — a one-line title, the chip row and
           the footer come to about this. Cards grow past it on their own. */
        --tk-card-min-h: 112px;
        --tk-pad: 10px;
        width: calc(var(--tk-card-w) + 44px);
    }
    .kbc-col .task-list { scrollbar-gutter: stable; }

    /* Height is a FLOOR, not a fixed box (owner, 2026-08-25). It was fixed at
       150px so every collapsed card was the same size; the cost was that a task
       with a one-line title and no dates held the same 150px as a full one and
       read as half empty. A card is now as tall as what is on it, and no
       shorter than --tk-card-min-h.

       Width stays fixed — that is what keeps the columns aligned. Only the
       vertical goes elastic.

       The title is still clamped to two lines with an ellipsis rather than
       being allowed to run: past two lines a card is a wall of text, and the
       column loses its rhythm in the other direction. So the range is bounded
       at both ends — roughly 112px for a bare card, ~130px for a full one. */
    .tk-card {
        width: var(--tk-card-w, 283px);
        min-height: var(--tk-card-min-h, 112px);
        padding: var(--tk-pad, 10px);
        display: flex;
        flex-direction: column;
    }

    /* Elevation, in two steps. Both shadows are two-layer — a cast for the
       height and a tight dark one for the contact edge — which is what makes a
       card look lifted OFF the column rather than merely outlined; a single blur
       at this size reads as a smudge.

       At rest the card sits just clear of the board. On hover it rises and the
       cast opens up, so the whole column stays calm and only the card under the
       cursor comes forward.

       Tuned from the reference build's --sh1 / --sh2 (owner, 2026-08-25) over
       three passes: +15% float and sharper (y and alpha x1.15, blur x0.85, the
       hover lift 3px → 3.5px), then the RESTING shadow alone +20%, then +50%
       more on top of that.

       The middle pass was invisible, and worth recording why: measured off the
       rendered pixels, it moved the darkest row under a card from 208 to 204
       against a 243 column — four levels out of 255. A shadow on this board is
       dark-on-GREY, not dark-on-white, so it starts with roughly a third of the
       contrast it would have on a white page and small multipliers vanish into
       it. The +50% pass is what made it read: darkest row 180, i.e. 63 levels
       of contrast against 39 before, reaching ~11px instead of ~9.

       Float is the drop and the ink; sharp is the blur, and it has not moved
       since the first pass. The shadow falls further and reads darker through
       the SAME 7px radius — deeper, not hazier. Hover is untouched, and still a
       clear step up from rest: 14px against 6.3px.

       Two classes so these beat the shadow-sm / hover:shadow-md utilities still
       in the card's markup, which stay as the fallback for a card rendered
       outside a column wrapper. */
    .kbc-col .tk-card {
        box-shadow: 0 6.3px 7px -2px rgba(17, 21, 45, .22), 0 1.8px 2.5px rgba(17, 21, 45, .105);
        transition: top .3s cubic-bezier(.22,.8,.28,1),
                    box-shadow .3s cubic-bezier(.22,.8,.28,1),
                    border-color .3s cubic-bezier(.22,.8,.28,1);
    }
    /* The lift is `top`, NOT `transform` — and that is not a style choice.
       A transformed element becomes the containing block for every
       position:fixed descendant, so while a card was hovered the dropdown
       panels (priority, dates, assignees, labels — all fixed, all positioned
       in viewport coordinates) were re-based onto the card and drew ~290px
       away. Clicking a control did nothing visible until the cursor left the
       card and the transform went away. `top` on an already-relative element
       moves it the same distance and creates no containing block. */
    .kbc-col .tk-card:hover {
        top: -3.5px;
        box-shadow: 0 14px 19px -12px rgba(17, 21, 45, .35), 0 3.5px 6px -3px rgba(17, 21, 45, .12);
    }
    /* Dragging already moves the card; a hover lift on top of Sortable's own
       transform would fight it. */
    .kbc-col .tk-card.sortable-chosen:hover,
    .kbc-col .tk-card.sortable-ghost:hover { top: 0; }

    @media (prefers-reduced-motion: reduce) {
        .kbc-col .tk-card { transition: box-shadow .3s ease; }
        .kbc-col .tk-card:hover { top: 0; }
    }

    /* Expanded, a card is not a standard card any more — the sub-work list has
       to be able to grow it, and the fixed height would otherwise cut the list
       off at whatever fell below 150px. .tk-expanded is written by
       toggleCardSubtasks on the same click that opens the panel. */
    .tk-card.tk-expanded { height: auto; }

    /* Two lines, then an ellipsis. -webkit-box is the only clamp that ends the
       cut line with "…" rather than slicing it mid-letter. Type size and
       leading stay on the element as utilities; this rule owns only the shape. */
    .tk-title {
        flex: 0 1 auto;
        /* Exactly two lines of the title's own type, and never a pixel more.
           The cap is not decoration: -webkit-line-clamp ends line two with an
           ellipsis but still PAINTS what follows, so a box any taller than two
           lines shows a sliver of line three under the "…". In em, so it keeps
           following the type size if that ever changes. */
        max-height: calc(2 * 1.375em);
        /* …and never less than the dial, which floats inside this box: 28px of
           ring plus its 3px of margin. A one-line title would otherwise clip
           the bottom off it. */
        min-height: 31px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Everything below the title is pinned to the foot of the card: whatever a
       short title does not use falls between the title and this row, not
       between the footer and the card's bottom edge. That is what keeps the
       chips and the footer on the same line across a whole column. */
    .tk-meta { margin-top: auto; }

    /* The title row: the title, and the dial beside it when there is one.

       The dial cannot live INSIDE .tk-title — that box is clamped with
       overflow:hidden and cut it, taking the title's second line with it. So it
       is the title's SIBLING here, and the row does two jobs no padding could:
       it reserves the dial's lane, and it centres the dial against the title
       whatever height the title is. */
    .tk-head { display: flex; align-items: center; gap: 10px; }
    /* min-width:0 is what lets a flex item shrink below its content and clamp;
       without it the title would push the dial off the card's edge. */
    .tk-head .tk-title { flex: 1 1 auto; min-width: 0; }

    /* ── Kanban column accents ─────────────────────────────────────────────
       Every colour below comes from four CSS variables set on the column
       wrapper; not one of these rules names a colour itself. That is the whole
       trick: a card is only MOVED in the DOM when it changes column — by the
       drag, the ‹ › arrows, the state dropdown or the drawer — and nothing
       repaints it afterwards. Reading the accent off the ancestor column means
       the card's rail is already the new column's colour the instant it lands.
       Painting the colour onto the card instead would need JS on all four move
       paths and would go stale the first time one was missed.

       Prefixed kbc- and kept in this view for the same reason as the tp- rules
       above: app.css is the bundle all twelve companies load on every page, and
       this is one screen. */
    /* The card's left rail, and nothing else — the column keeps its grey shell.
       Two classes beats the one-class utilities already on the card
       (border-gray-200, hover:border-blue-400), so the card's own markup needs no
       per-column change.

       Hover takes ALL FOUR sides to the same accent, not just the left rail. It
       used to leave the other three on Tailwind's blue-400, which put a blue
       outline around a purple-railed card and read as a mistake rather than a
       highlight — one card, one colour. It is the same --kbc-strong the rail and
       the footer sweep already use, so a card carries its colour with it when it
       changes column, with no JS on any of the move paths.

       Specificity does the switching: .kbc-col .task-item:hover is three
       classes against hover:border-blue-400's two, so the utility stays in the
       markup as the fallback for a card rendered outside a .kbc-col wrapper. */
    /* The rail is wider and painted --kbc-deep: the accent with every channel
       at 90%. Ten percent deeper than it was, and one device pixel wider.

       Width is 4px rather than the 3.3px that "10% more" literally asks for
       because a border cannot be a fraction of a device pixel: Chrome floored
       3.3px straight back to 3px (measured — getComputedStyle returned "3px"),
       so the rail would not have changed at all. 4px is the next width that
       actually paints.

       Hover then takes the other three sides to --kbc-soft, the same accent at
       80% alpha. At full strength all four sides shouted as loudly as the rail
       and the card looked outlined rather than lifted; at 80% the rail stays
       the strongest edge on the card and the rest reads as a highlight. The
       rail keeps its own deep colour on hover — hence the second declaration,
       which would otherwise be overwritten by the shorthand above it. */
    .kbc-col .task-item {
        border-left: 4px solid var(--kbc-deep, var(--kbc-strong));
    }
    .kbc-col .task-item:hover {
        border-color: var(--kbc-soft, var(--kbc-strong));
        border-left-color: var(--kbc-deep, var(--kbc-strong));
    }

    /* == EMPLOYEE TASK OVERSIGHT ==========================================
       Every rule below is prefixed eto- and every one of them is inert unless
       the oversight bar or a verdict mark is actually on the page. Nothing here
       can reach a card, column or drawer on an ordinary board. */

    .eto-bar {
        display: flex; align-items: center; gap: .85rem;
        padding: .6rem .9rem; margin-bottom: .9rem;
        border-radius: 11px;
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        box-shadow: 0 6px 16px -8px rgba(30,58,138,.55);
    }
    .eto-bar-back {
        display: inline-flex; align-items: center; justify-content: center;
        width: 30px; height: 30px; flex-shrink: 0;
        border-radius: 8px; color: #fff; text-decoration: none;
        background: rgba(255,255,255,.14);
        transition: background .15s ease;
    }
    .eto-bar-back:hover { background: rgba(255,255,255,.26); color: #fff; }

    .eto-bar-who { display: flex; align-items: center; gap: .6rem; min-width: 0; flex: 1; }
    .eto-bar-avatar {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; overflow: hidden;
        background: rgba(255,255,255,.2); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .85rem;
        box-shadow: 0 0 0 2px rgba(255,255,255,.28);
    }
    .eto-bar-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .eto-bar-name { font-weight: 700; font-size: .92rem; line-height: 1.2;
                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .eto-bar-sub  { font-size: .7rem; opacity: .82; margin-top: .05rem;
                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .eto-bar-switch-toggle {
        background: rgba(255,255,255,.16); color: #fff;
        transition: background .15s ease;
    }
    .eto-bar-switch-toggle:hover { background: rgba(255,255,255,.28); }
    .eto-bar-mini {
        width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0; overflow: hidden;
        background: #e0e7ff; color: #3730a3;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .62rem; font-weight: 700;
    }
    .eto-bar-mini img { width: 100%; height: 100%; object-fit: cover; }

    /* -- The switch-employee panel ---------------------------------------- */
    .eto-sw { position: relative; flex-shrink: 0; }
    .eto-sw-panel {
        position: absolute; top: calc(100% + 8px); right: 0; z-index: 60;
        width: 340px; max-width: 90vw;
        background: #fff; color: #111827;
        border: 1px solid #e5e7eb; border-radius: 12px;
        box-shadow: 0 18px 40px -12px rgba(17,21,45,.38), 0 4px 10px -4px rgba(17,21,45,.16);
        overflow: hidden;
    }

    .eto-sw-search { position: relative; padding: 10px; border-bottom: 1px solid #f1f3f5; background: #fff; }
    .eto-sw-search > svg {
        position: absolute; left: 20px; top: 50%; transform: translateY(-50%);
        width: 13px; height: 13px; color: #9ca3af; pointer-events: none;
    }
    .eto-sw-search input {
        width: 100%; padding: 7px 26px 7px 30px;
        border: 1px solid #e5e7eb; border-radius: 8px;
        font-size: .8rem; color: #111827; background: #f9fafb;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .eto-sw-search input:focus {
        outline: none; background: #fff; border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    }
    #etoSwClear {
        position: absolute; right: 18px; top: 50%; transform: translateY(-50%);
        border: 0; background: none; cursor: pointer;
        font-size: 1rem; line-height: 1; color: #9ca3af; padding: 0 2px;
    }
    #etoSwClear:hover { color: #4b5563; }

    .eto-sw-list { max-height: 340px; overflow-y: auto; padding-bottom: 4px; scrollbar-width: thin; }

    .eto-sw-group-head {
        position: sticky; top: 0; z-index: 1;
        display: flex; align-items: center; justify-content: space-between; gap: .5rem;
        padding: 6px 12px;
        background: #f8fafc; border-bottom: 1px solid #eef2f6;
        font-size: .62rem; font-weight: 800; letter-spacing: .07em;
        text-transform: uppercase; color: #64748b;
    }
    .eto-sw-count {
        background: #e2e8f0; color: #475569;
        border-radius: 999px; padding: 0 6px;
        font-size: .6rem; font-weight: 700; letter-spacing: 0;
    }

    .eto-sw-row {
        display: flex; align-items: center; gap: .55rem;
        padding: 7px 12px; text-decoration: none; color: inherit;
        transition: background .12s ease;
    }
    .eto-sw-row:hover, .eto-sw-row.is-active { background: #eff6ff; }
    .eto-sw-row.is-current { background: #f1f5f9; }
    .eto-sw-row-main { min-width: 0; flex: 1; display: flex; flex-direction: column; }
    .eto-sw-row-name {
        font-size: .8rem; font-weight: 600; color: #111827; line-height: 1.25;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .eto-sw-row-sub {
        font-size: .66rem; color: #94a3b8; line-height: 1.3;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .eto-sw-tick { width: 13px; height: 13px; color: #2563eb; flex-shrink: 0; }

    .eto-sw-empty { padding: 22px 12px; text-align: center; font-size: .78rem; color: #94a3b8; }

    /* -- The verdict marks on a card --------------------------------------
       These are NOT super-admin-only. A lock the employee cannot see is a lock
       that only produces a baffling "you cannot delete this" later, so the mark
       shows on every board and only the CONTROLS are restricted. */
    .eto-mark {
        display: inline-flex; align-items: center; gap: .18rem;
        font-size: 9.5px; font-weight: 700; line-height: 1;
        padding: 2px 4px; border-radius: 4px; white-space: nowrap;
    }
    .eto-mark.flag { background: #fee2e2; color: #b91c1c; }
    .eto-mark.lock { background: #fef3c7; color: #92400e; }
    .eto-mark.rate { background: #eef2ff; color: #4338ca; }
    .eto-mark svg { width: 8.5px; height: 8.5px; }

    /* A flagged card is meant to be findable without reading it. The rail
       overrides the column colour, which is exactly the point. */
    .kbc-col .tk-card.eto-flagged { border-left-color: #dc2626 !important; }
    .kbc-col .tk-card.eto-flagged:hover { border-left-color: #b91c1c !important; }
    .kbc-col .tk-card.eto-locked:not(.eto-flagged) { border-left-color: #d97706 !important; }

    /* -- The drawer's evaluation panel ----------------------------------- */
    .eto-panel {
        margin-top: 1.1rem; border: 1px solid #e5e7eb; border-radius: 10px;
        overflow: hidden; background: #fff;
    }
    .eto-panel-head {
        display: flex; align-items: center; gap: .5rem;
        padding: .6rem .8rem; background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        font-size: .78rem; font-weight: 700; color: #374151;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .eto-panel-body { padding: .85rem .8rem; }

    .eto-row { display: flex; align-items: flex-start; gap: .7rem; padding: .55rem 0; }
    .eto-row + .eto-row { border-top: 1px solid #f3f4f6; }
    .eto-row-label {
        width: 108px; flex-shrink: 0; font-size: .78rem; color: #4b5563;
        display: flex; align-items: center; gap: .35rem; padding-top: .15rem;
    }
    .eto-row-body { flex: 1; min-width: 0; }

    .eto-switch { display: inline-flex; align-items: center; gap: .5rem; cursor: pointer; user-select: none; }
    .eto-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
    .eto-switch-track {
        width: 36px; height: 20px; border-radius: 999px; background: #d1d5db;
        position: relative; transition: background .18s ease; flex-shrink: 0;
    }
    .eto-switch-track::after {
        content: ""; position: absolute; top: 2px; left: 2px;
        width: 16px; height: 16px; border-radius: 50%; background: #fff;
        box-shadow: 0 1px 2px rgba(0,0,0,.25);
        transition: transform .18s ease;
    }
    .eto-switch input:checked + .eto-switch-track { background: #dc2626; }
    .eto-switch input:checked + .eto-switch-track::after { transform: translateX(16px); }
    .eto-switch.lock input:checked + .eto-switch-track { background: #d97706; }
    .eto-switch-text { font-size: .78rem; color: #374151; font-weight: 600; }
    .eto-switch input:disabled + .eto-switch-track { opacity: .5; cursor: not-allowed; }

    .eto-stars-input { display: inline-flex; align-items: center; gap: .12rem; }
    .eto-star {
        background: none; border: 0; padding: 1px; cursor: pointer; line-height: 0;
        color: #e5e7eb; transition: color .12s ease, transform .12s ease;
    }
    .eto-star svg { width: 21px; height: 21px; }
    .eto-star.on { color: #f59e0b; }
    .eto-stars-input:hover .eto-star { color: #e5e7eb; }
    .eto-stars-input:hover .eto-star.hot { color: #fbbf24; }
    .eto-star:hover { transform: scale(1.12); }
    .eto-star[disabled] { cursor: default; }
    .eto-star[disabled]:hover { transform: none; }
    .eto-rating-score { font-size: .78rem; font-weight: 700; color: #374151; margin-left: .4rem; }
    .eto-clear-rating {
        background: none; border: 0; cursor: pointer; padding: 0 .2rem;
        font-size: .68rem; color: #9ca3af; text-decoration: underline;
    }
    .eto-clear-rating:hover { color: #dc2626; }

    .eto-note {
        width: 100%; margin-top: .4rem; padding: .45rem .55rem;
        border: 1px solid #e5e7eb; border-radius: 7px;
        font-size: .78rem; color: #374151; resize: vertical; min-height: 34px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .eto-note:focus { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
    .eto-note[readonly] { background: #f9fafb; color: #6b7280; }

    .eto-stamp { font-size: .66rem; color: #9ca3af; margin-top: .25rem; }

    .eto-locked-banner {
        display: flex; align-items: flex-start; gap: .5rem;
        padding: .55rem .7rem; border-radius: 8px;
        background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
        font-size: .78rem; line-height: 1.35;
    }

    .eto-saving { opacity: .55; pointer-events: none; }

    /* The Evaluation button's dot, and the flash the panel gives when you
       arrive there — without it a smooth scroll on a long drawer leaves you
       wondering which of the boxes you were sent to. */
    .eto-btn-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #dc2626; flex-shrink: 0;
        box-shadow: 0 0 0 2px rgba(220,38,38,.18);
    }
    .eto-btn-dot.rated { background: #4338ca; box-shadow: 0 0 0 2px rgba(67,56,202,.18); }

    @keyframes eto-flash {
        0%   { box-shadow: 0 0 0 0 rgba(37,99,235,0); }
        18%  { box-shadow: 0 0 0 4px rgba(37,99,235,.30); }
        70%  { box-shadow: 0 0 0 4px rgba(37,99,235,.22); }
        100% { box-shadow: 0 0 0 0 rgba(37,99,235,0); }
    }
    .eto-panel.eto-flash { animation: eto-flash 1.25s ease; }

    @media (prefers-reduced-motion: reduce) {
        .eto-panel.eto-flash { animation: none; }
    }

    /* Tailwind's .hidden is display:none at one class of specificity, and this
       block is loaded AFTER the bundle — so every eto- rule that sets display
       would quietly beat it and the JS toggle would do nothing. Caught in the
       browser: the read-only banner rendered as an empty yellow strip on a
       super admin's drawer. Anything here that is display:something AND gets
       .hidden toggled on it has to say so explicitly. */
    .eto-row.hidden,
    .eto-locked-banner.hidden,
    .eto-note.hidden,
    .eto-mark.hidden,
    .eto-switch.hidden,
    .eto-stars-input.hidden { display: none; }

</style>


{{-- ══ EMPLOYEE TASK OVERSIGHT BAR ═══════════════════════════════════════════
     Only ever drawn when a super admin opened this board through Employee Task
     Oversight. For every other visitor $oversightFor is not set and this whole
     block renders nothing at all — the board below is byte-identical to what it
     was before oversight existed.

     It says whose board this is, because everything under it is somebody else's
     work and acting on the wrong person's board is the one mistake this screen
     makes easy.
  ═══════════════════════════════════════════════════════════════════════════ --}}
@isset($oversightFor)
<div class="eto-bar">
    <a href="{{ route('role.task-oversight.index', ['role' => $role]) }}" class="eto-bar-back" title="Back to the employee list">
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    </a>

    <div class="eto-bar-who">
        <div class="eto-bar-avatar">
            @if($oversightFor->image)
                <img src="{{ asset($oversightFor->image) }}" alt="{{ $oversightFor->name }}">
            @else
                {{ mb_strtoupper(mb_substr($oversightFor->name, 0, 1)) }}
            @endif
        </div>
        <div style="min-width:0;">
            <div class="eto-bar-name">{{ $oversightFor->name }}</div>
            <div class="eto-bar-sub">Viewing their task board · you are not on this board</div>
        </div>
    </div>

    {{-- Jump to somebody else without going back to the picker.

         Purpose-built rather than an <x-ui.dropdown>: that component filters on
         its items' own text, and every row here is an <a> wrapping an avatar,
         so its search matched nothing. This one groups by company and searches
         everything a person is actually looked up by — name, employee ID,
         email, phone, company, department, designation, role — off one
         data-search blob per row, which is why typing filters instantly with no
         request. --}}
    @isset($oversightGroups)
    <div class="eto-sw" id="etoSw">
        <button type="button" class="eto-bar-switch-toggle flex items-center gap-2 px-3 py-1.5 rounded-lg cursor-pointer" id="etoSwBtn">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 16a6 6 0 1112 0v1H2v-1z"/></svg>
            <span class="text-xs font-semibold">Switch employee</span>
            <svg class="w-3 h-3 opacity-70" viewBox="0 0 20 20" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 8l4 4 4-4"/></svg>
        </button>

        <div class="eto-sw-panel" id="etoSwPanel" style="display:none">
            <div class="eto-sw-search">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                <input type="text" id="etoSwQ" autocomplete="off" spellcheck="false"
                       placeholder="Name, ID, company, department, designation…">
                <button type="button" id="etoSwClear" title="Clear" style="display:none">&times;</button>
            </div>

            <div class="eto-sw-list" id="etoSwList">
                @foreach($oversightGroups as $companyName => $group)
                    <div class="eto-sw-group" data-eto-group>
                        <div class="eto-sw-group-head">
                            <span>{{ $companyName }}</span>
                            <span class="eto-sw-count" data-eto-count>{{ $group->count() }}</span>
                        </div>

                        @foreach($group as $p)
                            @php
                                $dept   = $p->profile->department->name ?? null;
                                $desig  = $p->profile->designation->name ?? null;
                                $comp   = $p->company->name ?? null;
                                $compS  = $p->company->short_name ?? null;
                                $roleNm = $p->roles->pluck('name')->implode(' ');
                                /* One lowercase haystack per row — the filter does a single
                                   indexOf against this instead of walking the DOM for each
                                   field, which is what keeps 100+ rows filtering per keystroke. */
                                $blob = Str::lower(collect([
                                    $p->name, $p->employee_id_no, $p->email, $p->phone,
                                    $comp, $compS, $dept, $desig, $roleNm,
                                ])->filter()->implode(' '));
                                $sub = collect([$p->employee_id_no, $desig, $dept])->filter()->implode(' · ');
                            @endphp
                            <a class="eto-sw-row {{ $p->id == $oversightFor->id ? 'is-current' : '' }}"
                               href="{{ route('role.task-oversight.employee', ['role' => $role, 'employee' => $p->id]) }}"
                               data-eto-row data-search="{{ $blob }}">
                                <span class="eto-bar-mini">
                                    @if($p->image)
                                        <img src="{{ asset($p->image) }}" alt="">
                                    @else
                                        {{ mb_strtoupper(mb_substr($p->name, 0, 1)) }}
                                    @endif
                                </span>
                                <span class="eto-sw-row-main">
                                    <span class="eto-sw-row-name">{{ $p->name }}</span>
                                    @if($sub)
                                        <span class="eto-sw-row-sub">{{ $sub }}</span>
                                    @endif
                                </span>
                                @if($p->id == $oversightFor->id)
                                    <svg class="eto-sw-tick" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endforeach

                <div class="eto-sw-empty" id="etoSwEmpty" style="display:none">
                    Nobody matches that.
                </div>
            </div>
        </div>
    </div>
    @endisset
</div>
@endisset

<div class="h-full flex flex-col">
    <div class="flex justify-between items-center mb-4">
        @if(isset($viewMode) && $viewMode === 'my-tasks')
            <div>
                <h1 class="text-2xl font-bold text-gray-800">My Tasks</h1>
                <p class="text-sm text-gray-500 mt-0.5">Tasks assigned to or created by you in the last 30 days.</p>
            </div>
        @else
            <div class="flex items-center gap-3">
                <label class="text-sm font-semibold text-gray-700">Board</label>

                <div class="relative">
                    <x-ui.dropdown
                        type="boards"
                        context="header"
                        width="w-64"
                        toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                        panelClass="max-h-80 overflow-hidden">
                        <x-slot:toggle>
                            <button type="button" class="flex items-center gap-2">
                                <span class="text-sm text-gray-700">{{ $board->name ?? 'Select a board' }}</span>
                                <svg class="w-3 h-3 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 8l4 4 4-4"/></svg>
                            </button>
                        </x-slot:toggle>

                        @foreach($boards as $b)
                            <x-ui.dropdown-item value="{{ $b->id }}" :selected="$b->id == $board->id">
                                {{-- In oversight the switcher walks the EMPLOYEE's boards and stays
                                     on their board, which is what "navigate like the employee"
                                     means; everywhere else it is unchanged. --}}
                                <a href="{{ isset($oversightFor) ? route('role.task-oversight.board', ['role' => $role, 'employee' => $oversightFor->id, 'board' => $b->id]) : route('role.tasks.board', ['board' => $b->id, 'role' => $role]) }}" class="block w-full text-left px-3 py-2 text-sm text-gray-700">{{ $b->name }}</a>
                            </x-ui.dropdown-item>
                        @endforeach
                    </x-ui.dropdown>
                </div>

                <div id="board-hint" class="ml-2 hidden text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded flex items-center gap-2 cursor-pointer">
                    <span>Click to select a board</span>
                    <button id="board-hint-close" class="text-blue-400 font-semibold">&times;</button>
                </div>

                {{-- Done-column period picker, moved up beside the board switcher (owner,
                     2026-10-05: "move this here"). It still filters only the done column(s). --}}
                @if(!empty($doneMeta))
                    <div class="kbc-bar">
                        <span class="kbc-bar-lbl">Done</span>
                        @include('task-module.task.partials.board-done-filter')
                    </div>
                @endif
            </div>

            <script>
                (function(){
                    try {
                        var key = 'epal_seen_board_hint_v1';
                        var seen = localStorage.getItem(key);
                        if(!seen){
                            var hint = document.getElementById('board-hint');
                            if(hint) hint.classList.remove('hidden');
                        }
                        var close = document.getElementById('board-hint-close');
                        if(close){
                            close.addEventListener('click', function(e){
                                e.stopPropagation(); e.preventDefault();
                                localStorage.setItem(key, '1');
                                var hint = document.getElementById('board-hint'); if(hint) hint.classList.add('hidden');
                            });
                        }
                        var hintDiv = document.getElementById('board-hint');
                        if(hintDiv){
                            hintDiv.addEventListener('click', function(){
                                localStorage.setItem(key, '1');
                                hintDiv.classList.add('hidden');
                            });
                        }
                    } catch (e) { console.error(e); }
                })();
            </script>
        @endif
    </div>

    @if(isset($viewMode) && $viewMode === 'my-tasks')
    {{-- ── MY TASKS: flat cross-board list ──────────────────────────────── --}}
    <div class="flex-1 overflow-y-auto pb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3 items-start">
        @forelse($tasks as $task)
        @php
            $selectedUsers = $task->users ?? collect();
            $count = $selectedUsers->count();
            $maxAvatars = ($count <= 3) ? $count : 2;
            $extra = ($count > 3) ? ($count - 2) : 0;
            $selectedCsv = $selectedUsers->pluck('id')->implode(',');
            $labelCount = $task->labels->count();
            $labelCsv = $task->labels->pluck('id')->implode(',');
            $taskBoardColumns = $task->board ? $task->board->columns : collect();

            /* Progress, same dial the board cards carry. Counted off the loaded
               relation rather than withCount: myTasks() already eager-loads
               subtasks, so this costs no query. */
            $tpTot  = $task->subtasks->count();
            $tpDone = $task->subtasks->whereNotNull('completed_at')->count();
            $tpPct  = $tpTot ? (int) round($tpDone / $tpTot * 100) : 0;
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all group relative"
             data-id="{{ $task->id }}" style="overflow: visible;">

            {{-- 3-Dot Menu --}}
            <div class="absolute top-2 right-2 z-10">
                <button onclick="event.stopPropagation(); toggleDropdown('task-menu-{{ $task->id }}')"
                        class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/>
                    </svg>
                </button>
                <div id="task-menu-{{ $task->id }}" class="hidden absolute right-0 top-full mt-1 w-40 bg-white rounded-lg shadow-xl border border-gray-200 z-50 py-1">
                    <button onclick='event.stopPropagation(); openEditModal({{ $task->id }}, @json($task->title), @json($task->description), @json($task->users->pluck("id")->toArray()), @json($task->start_date), @json($task->due_date), @json($task->priority), {{ $task->column_id }}, @json($task->labels->pluck("id")->toArray()), {{ $task->board_id }})'
                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/>
                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/>
                        </svg>
                        Edit
                    </button>
                    <button onclick="event.stopPropagation(); deleteTask({{ $task->id }})"
                            class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Delete
                    </button>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="p-3 pr-10">
                {{-- Board badge --}}
                <div class="mb-1.5">
                    <span class="inline-flex items-center gap-1 text-[10px] font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2 4a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H3a1 1 0 01-1-1V4z"/>
                            <path fill-rule="evenodd" d="M2 9a1 1 0 011-1h6a1 1 0 011 1v7a1 1 0 01-1 1H3a1 1 0 01-1-1V9zm10 0a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1V9z" clip-rule="evenodd"/>
                        </svg>
                        {{ $task->board->name ?? 'Unknown Board' }}
                    </span>
                </div>

                {{-- Task Title --}}
                <p class="font-semibold text-gray-900 text-[16px] leading-snug mb-2 cursor-pointer hover:text-blue-600 transition"
                   onclick='openTaskDrawer({{ $task->id }}, @json($task->title), @json($task->description), @json($task->users->pluck("id")), @json($task->start_date), @json($task->due_date), @json($task->priority), @json($task->column_id), @json($task->labels->pluck("id")), @json($task->creator ? $task->creator->name : "Unknown"), {{ $task->board_id }})'>
                    {{-- The same dial as the board's, floated into the title rather
                         than pinned to the corner: this card's ⋯ button is always
                         visible and owns that corner, and the body's pr-10 already
                         holds the lane the dial needs. Nothing is clamped here, so
                         the title wraps around it. --}}
                    @if($tpTot > 0)
                        <svg class="tp-ring tp-inline {{ $tpPct >= 100 ? 'tp-full' : '' }}" data-task-progress="{{ $task->id }}"
                             viewBox="0 0 36 36" style="--tp-p: {{ $tpPct }}"
                             role="img"
                             aria-label="{{ $tpDone }} of {{ $tpTot }} sub-work items done ({{ $tpPct }}%)">
                            <title>{{ $tpDone }}/{{ $tpTot }} sub-work items done</title>
                            {{-- Baby blue → sky → blue → dark blue, along the band. The id carries
                                 the task id because a gradient is referenced by id, and every card
                                 on the board is drawing one of these. --}}
                            <defs>
                                {{-- Colour ALONG the band: baby blue → sky → blue → dark blue. --}}
                                <linearGradient id="tpGrad-{{ $task->id }}" x1="0" y1="1" x2="1" y2="0">
                                    <stop offset="0%"   stop-color="#5cbcff"></stop>
                                    <stop offset="38%"  stop-color="#2a88f2"></stop>
                                    <stop offset="72%"  stop-color="#1857c6"></stop>
                                    <stop offset="100%" stop-color="#0f2f74"></stop>
                                </linearGradient>
                                {{-- Shading ACROSS the band, and the reason this reads as a tube: a
                                     radial gradient in user space varies with RADIUS, so its stops
                                     land on the band's inner edge, its crown and its outer edge.
                                     Offsets are r/18 — .72 is the inner edge at r13, 1.0 the outer at
                                     r18. Alpha only, so the colour underneath keeps its hue. --}}
                                <radialGradient id="tpTube-{{ $task->id }}" gradientUnits="userSpaceOnUse"
                                                cx="18" cy="18" r="18">
                                    <stop offset=".72" stop-color="#08204f" stop-opacity=".40"></stop>
                                    <stop offset=".80" stop-color="#08204f" stop-opacity=".06"></stop>
                                    <stop offset=".86" stop-color="#ffffff" stop-opacity=".34"></stop>
                                    <stop offset=".92" stop-color="#ffffff" stop-opacity=".46"></stop>
                                    <stop offset="1"   stop-color="#08204f" stop-opacity=".26"></stop>
                                </radialGradient>
                                {{-- The same idea inverted for the groove: shaded where the band would
                                     cast into it, lit at the outer lip. --}}
                                <radialGradient id="tpGroove-{{ $task->id }}" gradientUnits="userSpaceOnUse"
                                                cx="18" cy="18" r="18">
                                    <stop offset=".72" stop-color="#0a2450" stop-opacity=".16"></stop>
                                    <stop offset=".84" stop-color="#0a2450" stop-opacity=".05"></stop>
                                    <stop offset="1"   stop-color="#ffffff" stop-opacity=".85"></stop>
                                </radialGradient>
                                {{-- The dished plate. Tinted, never white: a white plate on a white
                                     card is a hole, which is what made the dial look half-there. --}}
                                <radialGradient id="tpPlate-{{ $task->id }}" cx="36%" cy="28%" r="80%">
                                    <stop offset="0%"   stop-color="#fbfdff"></stop>
                                    <stop offset="62%"  stop-color="#edf2fa"></stop>
                                    <stop offset="100%" stop-color="#d7e1f2"></stop>
                                </radialGradient>
                            </defs>
                            <circle class="tp-plate" cx="18" cy="18" r="12.7"
                                    style="fill: url(#tpPlate-{{ $task->id }})"></circle>
                            <circle class="tp-well" cx="18" cy="18" r="13.1"></circle>
                            <circle class="tp-bg" cx="18" cy="18" r="15.5" pathLength="100"></circle>
                            <circle class="tp-bg-shade" cx="18" cy="18" r="15.5" pathLength="100"
                                    style="stroke: url(#tpGroove-{{ $task->id }})"></circle>
                            <circle class="tp-fg" cx="18" cy="18" r="15.5" pathLength="100"
                                    style="stroke: url(#tpGrad-{{ $task->id }})"></circle>
                            <circle class="tp-fg tp-fg-tube" cx="18" cy="18" r="15.5" pathLength="100"
                                    style="stroke: url(#tpTube-{{ $task->id }})"></circle>
                            <text class="tp-pct" x="18" y="21.6" text-anchor="middle">{{ $tpPct }}%</text>
                        </svg>
                    @endif
                    {{ $task->title }}
                </p>

                {{-- Inline dropdowns --}}
                <div class="flex items-center flex-wrap gap-1 overflow-visible">

                    {{-- 1. State --}}
                    <x-ui.dropdown
                        type="state"
                        context="card"
                        :task-id="$task->id"
                        :search="true"
                        :multi="false"
                        :selected="(string)$task->column_id"
                        width="w-44"
                    >
                        <x-slot:toggle>
                            <button type="button" class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium hover:opacity-80 transition
                            @switch($task->column->position ?? 1)
                                @case(1) bg-gray-100 text-gray-600 @break
                                @case(2) bg-blue-100 text-blue-600 @break
                                @case(3) bg-amber-100 text-amber-600 @break
                                @case(4) bg-emerald-100 text-emerald-600 @break
                                @case(5) bg-red-100 text-red-600 @break
                                @default bg-gray-100 text-gray-600
                            @endswitch">
                                <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>
                                <span data-role="dropdown-label">{{ $task->column->name ?? 'None' }}</span>
                            </button>
                        </x-slot:toggle>
                        @foreach($taskBoardColumns as $col)
                            <x-ui.dropdown-item value="{{ $col->id }}" :selected="$task->column_id == $col->id" :attrs="['data-position' => $col->pivot->position ?? 1]">
                                @switch($col->pivot->position ?? 1)
                                    @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                                    @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                                    @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                                    @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                                    @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                                    @default <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>
                                @endswitch
                                <span>{{ $col->name }}</span>
                            </x-ui.dropdown-item>
                        @endforeach
                    </x-ui.dropdown>

                    {{-- 2. Priority --}}
                    <x-ui.dropdown
                        type="priority"
                        context="card"
                        :task-id="$task->id"
                        :search="true"
                        :multi="false"
                        :selected="(string)($task->priority ?? '')"
                        width="w-32"
                    >
                        <x-slot:toggle>
                            <button type="button" class="p-1.5 rounded hover:bg-gray-100 transition" title="Priority: {{ $task->priority ? ucfirst($task->priority) : 'None' }}">
                                <i id="priority-icon-{{ $task->id }}" class="fa-solid fa-signal
                                    @if($task->priority === 'high') text-red-500
                                    @elseif($task->priority === 'medium') text-yellow-500
                                    @elseif($task->priority === 'low') text-blue-500
                                    @else text-gray-400 @endif"></i>
                            </button>
                        </x-slot:toggle>
                        <x-ui.dropdown-item value="high" :selected="$task->priority==='high'">
                            <i class="fa-solid fa-signal text-red-500"></i> <span>High</span>
                        </x-ui.dropdown-item>
                        <x-ui.dropdown-item value="medium" :selected="$task->priority==='medium'">
                            <i class="fa-solid fa-signal text-yellow-500"></i> <span>Medium</span>
                        </x-ui.dropdown-item>
                        <x-ui.dropdown-item value="low" :selected="$task->priority==='low'">
                            <i class="fa-solid fa-signal text-blue-500"></i> <span>Low</span>
                        </x-ui.dropdown-item>
                        <x-ui.dropdown-item value="" :selected="!$task->priority">
                            <i class="fa-solid fa-minus text-gray-400"></i> <span>None</span>
                        </x-ui.dropdown-item>
                    </x-ui.dropdown>

                    {{-- 3. Start Date --}}
                    <div class="relative overflow-visible">
                        <button onclick="event.stopPropagation(); toggleDropdown('startdate-{{ $task->id }}')"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[11px] text-gray-600"
                                title="Start Date">
                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <path d="M16 2v4M8 2v4M3 10h18"></path>
                            </svg>
                            <span id="startdate-label-{{ $task->id }}">{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('H:i:s d/m/y') : '' }}</span>
                            @if($task->start_date)
                                <span onclick="event.stopPropagation(); updateTaskField({{ $task->id }}, 'start_date', '', null);" class="text-gray-400 hover:text-red-500 ml-0.5">&times;</span>
                            @endif
                        </button>
                        <div id="startdate-{{ $task->id }}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start Date</label>
                            <input type="datetime-local" value="{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('Y-m-d\TH:i') : '' }}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose({{ $task->id }}, 'start_date', this.value, null);">
                            <div class="flex gap-2 mt-2">
                                <button onclick="event.stopPropagation(); setQuickStartDate({{ $task->id }}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                <button onclick="event.stopPropagation(); setQuickStartDate({{ $task->id }}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Due Date --}}
                    <div class="relative overflow-visible">
                        <button onclick="event.stopPropagation(); toggleDropdown('duedate-{{ $task->id }}')"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[11px] text-gray-600"
                                title="Due Date">
                            <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <path d="M16 2v4M8 2v4M3 10h18"></path>
                            </svg>
                            <span id="duedate-label-{{ $task->id }}">{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('H:i:s d/m/y') : '' }}</span>
                            @if($task->due_date)
                                <span onclick="event.stopPropagation(); updateTaskField({{ $task->id }}, 'due_date', '', null);" class="text-gray-400 hover:text-red-500 ml-0.5">&times;</span>
                            @endif
                        </button>
                        <div id="duedate-{{ $task->id }}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                            <input type="datetime-local" value="{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('Y-m-d\TH:i') : '' }}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose({{ $task->id }}, 'due_date', this.value, null);">
                            <div class="flex gap-2 mt-2">
                                <button onclick="event.stopPropagation(); setQuickDueDate({{ $task->id }}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                <button onclick="event.stopPropagation(); setQuickDueDate({{ $task->id }}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                            </div>
                        </div>
                    </div>

                    {{-- 5. Assignees --}}
                    <x-ui.dropdown
                        type="assignees"
                        context="card"
                        :task-id="$task->id"
                        :search="true"
                        :multi="true"
                        :selected="$selectedCsv"
                        width="w-44"
                    >
                        <x-slot:toggle>
                            <button type="button" class="cursor-pointer inline-flex items-center">
                                @if($count)
                                    <div class="flex items-center">
                                        <div class="flex -space-x-2" data-role="dropdown-label">
                                            @foreach($selectedUsers->take($maxAvatars) as $u)
                                                <div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center ring-2 ring-white" title="{{ $u->name }}">
                                                    @if($u->image)
                                                        <img src="{{ asset($u->image) }}" alt="{{ $u->name }}" class="w-full h-full object-cover rounded-full">
                                                    @else
                                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        @if($extra > 0)
                                            <span class="ml-1 text-[10px] font-semibold text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded-full">+{{ $extra }}</span>
                                        @endif
                                    </div>
                                @else
                                    <div class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white" title="Unassigned">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                @endif
                            </button>
                        </x-slot:toggle>
                        @foreach($users as $user)
                            @php $isSelected = $selectedUsers->contains('id', $user->id); @endphp
                            <x-ui.dropdown-item value="{{ $user->id }}" :selected="$isSelected" :attrs="['data-name' => $user->name, 'data-image' => $user->image ? asset($user->image) : '']" class="{{ $isSelected ? 'bg-blue-50' : '' }}">
                                <div class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">
                                    @if($user->image)
                                        <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                                    @else
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    @endif
                                </div>
                                <span>{{ $user->name }}</span>
                            </x-ui.dropdown-item>
                        @endforeach
                    </x-ui.dropdown>

                    {{-- 6. Labels --}}
                    <x-ui.dropdown
                        type="labels"
                        context="card"
                        :task-id="$task->id"
                        :multi="true"
                        :search="true"
                        :selected="$labelCsv"
                        width="w-48"
                    >
                        <x-slot:toggle>
                            <button type="button" class="cursor-pointer inline-flex items-center" title="Labels: {{ $task->labels->pluck('name')->join(', ') ?: 'None' }}">
                                @if($labelCount == 0)
                                    <div class="w-5 h-5 rounded bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white">
                                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                                        </svg>
                                    </div>
                                @elseif($labelCount <= 3)
                                    <div class="flex flex-wrap gap-1" data-role="dropdown-label">
                                        @foreach($task->labels as $label)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium {{ $label->color_details['badge'] }} {{ $label->color_details['text'] }}" style="background: {{ $label->color_details['gradient'] }}">
                                                {{ Str::limit($label->name, 12) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 text-[11px] text-gray-600" data-role="dropdown-label">
                                        <svg class="w-3.5 h-3.5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                                        </svg>
                                        <span>{{ $labelCount }} Labels</span>
                                    </div>
                                @endif
                            </button>
                        </x-slot:toggle>
                        @if(isset($labels) && $labels->count() > 0)
                            @foreach($labels as $label)
                                <x-ui.dropdown-item value="{{ $label->id }}" :selected="$task->labels?->contains($label->id)" class="{{ $task->labels->contains($label->id) ? 'bg-blue-50' : '' }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                                    <span class="w-3 h-3 rounded-sm" style="background: {{ $label->color_details['gradient'] ?? '#999' }}"></span>
                                    <span class="flex-1 truncate">{{ $label->name }}</span>
                                </x-ui.dropdown-item>
                            @endforeach
                        @else
                            <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
                        @endif
                    </x-ui.dropdown>

                </div>
            </div>
        </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center h-64 text-center">
                <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-500">No tasks found</h3>
                <p class="text-sm text-gray-400 mt-1">You have no tasks assigned to or created by you in the last 30 days.</p>
            </div>
        @endforelse

        </div>

        @if(method_exists($tasks, 'links') && $tasks->hasPages())
            <div class="mt-4">
                {{ $tasks->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
    @else
    {{-- ── BOARD: Kanban view ────────────────────────────────────────────── --}}
    <style>
        /* Done-column period picker + infinite-scroll placeholders. kbc- prefixed. */
        .kbc-filter { display: flex; flex-direction: column; gap: 6px; padding: 0 8px 8px; }
        .kbc-filter-select, .kbc-filter-date {
            width: 100%; box-sizing: border-box; font-size: 11px; color: #475569;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 6px;
        }
        .kbc-filter-dates { display: flex; flex-wrap: wrap; gap: 4px; }
        .kbc-filter-dates[hidden] { display: none; }
        .kbc-filter-dates .kbc-filter-date { flex: 1 1 45%; min-width: 0; }
        .kbc-filter-apply {
            flex: 1 1 100%; font-size: 11px; font-weight: 600; color: #fff;
            background: #2563eb; border-radius: 6px; padding: 4px 0;
        }
        /* Toolbar copy: one line beside the board switcher. */
        .kbc-bar { display: flex; align-items: center; gap: 6px; margin-left: 4px; }
        .kbc-bar-lbl { font-size: 12px; font-weight: 600; color: #374151; }
        .kbc-bar .kbc-filter { flex-direction: row; align-items: center; padding: 0; }
        .kbc-bar .kbc-filter-select, .kbc-bar .kbc-filter-date { width: auto; height: 38px; font-size: 13px; padding: 0 10px; border-color: #d1d5db; border-radius: 6px; }
        .kbc-bar .kbc-filter-dates { flex-wrap: nowrap; align-items: center; }
        .kbc-bar .kbc-filter-apply { flex: none; height: 38px; font-size: 12px; padding: 0 14px; }
        .kbc-more { padding: 4px 0 8px; }
        .kbc-more-msg { text-align: center; font-size: 11px; color: #94a3b8; padding: 6px 0; }
        .kbc-more-msg:empty { display: none; }
        .kbc-more-retry {
            display: block; width: 100%; font-size: 11px; font-weight: 600; color: #dc2626;
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 6px 0;
        }
        .kbc-more-retry[hidden], .kbc-skel-wrap[hidden] { display: none; }
        .kbc-skel-wrap { display: flex; flex-direction: column; gap: 12px; }
        /* Same footprint as a real card so nothing jumps when cards arrive. */
        .kbc-skel {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;
            display: flex; flex-direction: column; gap: 8px; height: 84px; box-sizing: border-box;
        }
        .kbc-skel-line, .kbc-skel-dot {
            border-radius: 4px; background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
            background-size: 200% 100%; animation: kbc-shimmer 1.3s linear infinite;
        }
        .kbc-skel-line { height: 10px; }
        .kbc-skel-row { display: flex; gap: 6px; margin-top: auto; }
        .kbc-skel-dot { width: 18px; height: 18px; border-radius: 50%; }
        @keyframes kbc-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        @media (prefers-reduced-motion: reduce) { .kbc-skel-line, .kbc-skel-dot { animation: none; } }
    </style>
    @php
        /* Column accent — the one colour each column lends to the left edge of
           every card sitting in it. Nothing else on the board is coloured by
           this: the column shell, its title, its count and its + button are all
           untouched greys, by request.

           Resolved from the column's NAME first, not from columns.color: the
           owner asked for a fixed reading of the board — progress yellow, done
           green, cancelled red, to-do blue, note purple — and the stored colours
           did not match it (TO DO was saved green, DONE blue). Matching on the
           name gives a board the right colours without anyone editing rows
           first, on every board, including boards created later.

           A column named none of those falls back to whatever colour it was
           given in Manage ▸ Columns, and then to slate — so a custom column is
           still styled, just in the colour its owner chose. */
        $kbcPalette = [
            'blue'   => '#2563eb',
            'yellow' => '#eab308',
            'green'  => '#16a34a',
            'red'    => '#dc2626',
            'purple' => '#7c3aed',
            'gray'   => '#64748b',
            'indigo' => '#4f46e5',
            'pink'   => '#db2777',
            'orange' => '#ea580c',
            'teal'   => '#0d9488',
        ];

        $kbcAccent = function ($col) use ($kbcPalette) {
            /* Letters only, so "TO DO", "To-Do" and "todo" all read the same. */
            $name = preg_replace('/[^a-z]/', '', strtolower($col->name ?? ''));

            $byName = null;
            if (Str::contains($name, ['cancel', 'reject', 'dropped']))               $byName = 'red';
            elseif (Str::contains($name, ['done', 'complete', 'finish', 'closed']))  $byName = 'green';
            elseif (Str::contains($name, ['progress', 'doing', 'ongoing', 'wip']))   $byName = 'yellow';
            elseif (Str::contains($name, ['todo', 'pending', 'backlog', 'queue']))   $byName = 'blue';
            elseif (Str::contains($name, ['note', 'idea']))                          $byName = 'purple';

            $slug = $byName ?? strtolower($col->color ?? '');

            return $kbcPalette[$slug] ?? $kbcPalette['gray'];
        };

        /* Two shades derived from that one accent, so a column still carries a
           single colour and nothing has to be kept in step by hand:
             deep — every channel at 90%, for the card's left rail;
             soft — the same accent at 80% alpha, for the other three sides on
                    hover. Alpha rather than a lighter hex because the card's
                    white background sits behind its own border, so 80% lands on
                    white and stays right whatever the accent is. */
        $kbcDeep = function (string $hex): string {
            [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
            return sprintf('#%02x%02x%02x', (int) round($r * .9), (int) round($g * .9), (int) round($b * .9));
        };

        $kbcSoft = function (string $hex): string {
            [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
            return "rgba({$r}, {$g}, {$b}, .8)";
        };

    @endphp
    <div class="flex gap-4 overflow-x-auto flex-1 items-start">

        @foreach($board->board_columns as $column)
        @php $kbc = $kbcAccent($column->column); @endphp
        <div class="kbc-col flex-shrink-0 bg-gray-100 rounded-xl p-3 shadow-sm border border-gray-200 flex flex-col" data-column-id="{{ $column->id }}" style="height: calc(100vh - 240px); overflow: visible; --kbc-strong: {{ $kbc }}; --kbc-deep: {{ $kbcDeep($kbc) }}; --kbc-soft: {{ $kbcSoft($kbc) }};">
                <div class="flex justify-between items-center mb-4 px-2">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-gray-700 uppercase text-xs tracking-widest">{{ $column->column->name }}</h3>
                        @php $kbcLazy = ($doneMeta ?? [])[$column->column->id] ?? null; @endphp
                        <span class="bg-gray-200 text-gray-600 text-[10px] px-2 py-0.5 rounded-full font-bold">{{ $kbcLazy ? $kbcLazy['total'] : $column->column->tasks->count() }}</span>
                    </div>
                    <button onclick="openCreateModal({{ $column->column->id }})" class="text-blue-600 hover:text-blue-800 transition p-1">
                        <i class="fas fa-plus-circle fa-lg"></i>
                    </button>
                </div>
                <div class="task-list space-y-3 p-1 rounded-lg overflow-y-auto flex-1" data-column="{{ $column->column->id }}" style="scrollbar-width: thin; scrollbar-color: #cbd5e0 #f7fafc; overflow-x: visible;">
                    @foreach($column->column->tasks as $task)
                        @include('task-module.task.partials.board-card')
                    @endforeach
                    @if($kbcLazy)
                        @include('task-module.task.partials.board-more')
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif
</div>

<div id="taskModal" class="fixed inset-0 bg-gray-900/60 hidden items-center justify-center z-[55] backdrop-blur-sm p-4">
    <div class="bg-white w-full max-w-2xl rounded-xl shadow-2xl" style="overflow: visible;">
        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 id="modalTitle" class="text-lg font-semibold text-gray-900">Create new work item</h2>
            <button onclick="closeTaskModal()" class="p-1 rounded-full hover:bg-gray-100 text-gray-500">
                <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 space-y-5" style="max-height: calc(100vh - 200px); overflow-y: auto; overflow-x: visible;">
            <input type="hidden" id="task_id">
            <input type="hidden" id="column_id">
            <input type="hidden" id="priority">

            <input type="hidden" id="assigned_users" value="[]">
            <input type="hidden" id="label_ids" value="[]">

            {{-- Title --}}
            <div>
                <input id="title" type="text"
                       class="w-full border border-gray-300 px-3 py-2.5 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                       placeholder="Title" aria-describedby="title_error"
                       oninput="clearTaskFieldError('title')">
                <p id="title_error" class="hidden text-xs text-red-600 mt-1 flex items-center gap-1" role="alert">
                    <i class="fas fa-exclamation-circle"></i><span></span>
                </p>
            </div>

            {{-- Description --}}
            <div>
                <textarea id="description" rows="4"
                          placeholder="Click to add description"
                          class="w-full border border-gray-300 px-3 py-2.5 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm resize-none"></textarea>
            </div>

            {{-- Properties Row --}}
            <div class="flex flex-wrap gap-3" style="overflow: visible;">
                {{-- State/Column --}}
                <x-ui.dropdown
                    type="state"
                    context="modal"
                    :multi="false"
                    :search="true"
                    width="w-64"
                    :selected="old('column_id', '')"
                    panelClass="max-h-80 overflow-hidden"
                    toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                    <x-slot:toggle>
                        <button type="button" class="flex items-center gap-2 w-full">
                        <span class="text-sm text-gray-700" data-role="dropdown-label">State</span>

                        <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                        </button>
                    </x-slot:toggle>

                    @if(!isset($viewMode) || $viewMode !== 'my-tasks')
                    @foreach($board->columns as $column)
                        <x-ui.dropdown-item value="{{ $column->id }}" :selected="false">
                        @switch($column->position ?? 1)
                            @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                            @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                            @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                            @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                            @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                        @endswitch
                        <span class="text-gray-700">{{ $column->name }}</span>
                        </x-ui.dropdown-item>
                    @endforeach
                    @endif
                    {{-- In my-tasks mode, items are populated dynamically by JS --}}
                    </x-ui.dropdown>

                {{-- Priority --}}
                <x-ui.dropdown
                    type="priority"
                    context="modal"
                    :multi="false"
                    :search="true"
                    width="w-64"
                    :selected="old('priority','')"
                    toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                    <x-slot:toggle>
                        <button type="button" class="flex items-center gap-2 w-full">
                            <span class="text-sm text-gray-700" data-role="dropdown-label">Priority</span>

                            <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </x-slot:toggle>

                    <x-ui.dropdown-item value="high" :selected="false">
                        <i class="fa-solid fa-signal text-red-500"></i> <span>High</span>
                    </x-ui.dropdown-item>

                    <x-ui.dropdown-item value="medium" :selected="false">
                        <i class="fa-solid fa-signal text-yellow-500"></i> <span>Medium</span>
                    </x-ui.dropdown-item>

                    <x-ui.dropdown-item value="low" :selected="false">
                        <i class="fa-solid fa-signal text-blue-500"></i> <span>Low</span>
                    </x-ui.dropdown-item>

                    <x-ui.dropdown-item value="" :selected="false">
                        <i class="fa-solid fa-minus text-gray-400"></i> <span>None</span>
                    </x-ui.dropdown-item>

                </x-ui.dropdown>

                {{-- Assignees --}}
                <x-ui.dropdown
                    type="assignees"
                    context="modal"
                    :multi="true"
                    :search="true"
                    width="w-64"
                    :selected="''"
                    toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                    <x-slot:toggle>
                        <button type="button" class="flex items-center gap-2 w-full">
                            <span class="text-sm text-gray-700" data-role="dropdown-label">Assignees</span>

                            <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </x-slot:toggle>

                    @foreach($users as $user)
                        <x-ui.dropdown-item value="{{ $user->id }}" :attrs="['data-name' => $user->name, 'data-image' => $user->image ? asset($user->image) : '']">
                            <div class="w-7 h-7 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">
                                @if($user->image)
                                    <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                                @else
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                @endif
                            </div>
                            <span class="text-gray-700">{{ $user->name }}</span>
                        </x-ui.dropdown-item>
                    @endforeach
                </x-ui.dropdown>

                {{-- Labels --}}
                <x-ui.dropdown
                    type="labels"
                    context="modal"
                    :multi="true"
                    :search="true"
                    width="w-64"
                    :selected="''"
                    toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                    <x-slot:toggle>
                        <button type="button" class="flex items-center gap-2 w-full">
                        <span class="text-sm text-gray-700" data-role="dropdown-label">Labels</span>

                        <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                        </button>
                    </x-slot:toggle>

                    @if(isset($labels) && $labels->count() > 0)
                        @foreach($labels as $label)
                            <x-ui.dropdown-item value="{{ $label->id }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                                <span class="w-4 h-4 rounded-sm flex-shrink-0" style="background: {{ $label->color_details['gradient'] }}"></span>
                                <span class="text-gray-700">{{ $label->name }}</span>
                            </x-ui.dropdown-item>
                        @endforeach
                    @else
                        <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
                    @endif
                </x-ui.dropdown>

                {{-- Start Date --}}
                <div class="relative">
                    <button type="button" id="modalStartDateButton" onclick="toggleDropdown('modalStartDatePicker')" class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer text-sm">
                        <svg class="w-4 h-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                        </svg>
                        <span id="modalStartDateDisplay" class="text-gray-700">Add start date</span>
                    </button>
                    <div id="modalStartDatePicker" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start Date</label>
                        <input type="datetime-local" id="start_date" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateModalDate('start_date')">
                        <div class="flex gap-2 mt-2">
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('start_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('start_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('start_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                        </div>
                    </div>
                </div>

                {{-- Due Date --}}
                <div class="relative">
                    <button type="button" id="modalDueDateButton" onclick="toggleDropdown('modalDueDatePicker')" class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer text-sm">
                        <svg class="w-4 h-4 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                        </svg>
                        <span id="modalDueDateDisplay" class="text-gray-700">Add due date</span>
                    </button>
                    <div id="modalDueDatePicker" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                        <input type="datetime-local" id="due_date" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateModalDate('due_date')">
                        <div class="flex gap-2 mt-2">
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('due_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('due_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('due_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" id="create_more" class="rounded">
                <span>Create more</span>
            </label>
            <div class="flex gap-2">
                <button onclick="closeTaskModal()" class="px-4 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 text-gray-700">Discard</button>
                <button id="taskSaveBtn" onclick="saveTask()" class="px-6 py-2 text-sm rounded-md bg-blue-600 hover:bg-blue-700 text-white font-semibold">Save</button>
            </div>
        </div>
    </div>
</div>


{{-- MOVED OUT OF #taskDrawer (2026-08-24).

     This modal used to sit inside the drawer's scrolling body. The drawer is
     display:none while closed, so a descendant cannot be shown however many
     classes JS strips off it — openCreateSubtaskModal removed .hidden, the
     element reported display:flex, visibility:visible, z-index 55, and still
     measured 0x0 because its ancestor was hidden. It only ever appeared to
     work because the only way to open it was a button inside the open drawer.

     The board card's + button opens it with no drawer behind it, so it has to
     live at section level. Keep it here. --}}
{{-- ───────────── Create sub-task modal ───────────── --}}
<div id="createSubtaskBackdrop" class="fixed inset-0 bg-black/30 hidden z-50" onclick="closeCreateSubtaskModal()"></div>
<div id="createSubtaskModal" class="fixed inset-0 hidden z-[55] items-center justify-center px-4" aria-modal="true" role="dialog">
  <div class="bg-white w-full max-w-2xl rounded-xl shadow-2xl" style="overflow: visible;">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-900">Create sub-work item</h3>
      <button onclick="closeCreateSubtaskModal()" class="p-1 rounded-full hover:bg-gray-100 text-gray-500">
        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
      </button>
    </div>

    <div class="px-6 py-5 space-y-5" style="max-height: calc(100vh - 200px); overflow-y: auto; overflow-x: visible;">
      <input type="hidden" id="createSubtaskParentId">
      <input type="hidden" id="createSubtaskColumnId">
      <input type="hidden" id="createSubtaskPriority">
      <input type="hidden" id="createSubtaskAssignedUsers" value="[]">
      <input type="hidden" id="createSubtaskLabelIds" value="[]">

      {{-- Title --}}
      <div>
        <input id="createSubtaskTitle" type="text" placeholder="Sub-task title"
               class="w-full border border-gray-300 px-3 py-2.5 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
      </div>

      {{-- Description --}}
      <div>
        <textarea id="createSubtaskDescription" rows="4"
                  placeholder="Click to add description"
                  class="w-full border border-gray-300 px-3 py-2.5 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm resize-none"></textarea>
      </div>

      {{-- Properties Row --}}
      <div class="flex flex-wrap gap-3" style="overflow: visible;">
        {{-- State/Column --}}
        <x-ui.dropdown
            type="state"
            context="subtask"
            :multi="false"
            :search="true"
            width="w-64"
            :selected="''"
            panelClass="max-h-80 overflow-hidden"
            toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
            >
            <x-slot:toggle>
                <button type="button" class="flex items-center gap-2 w-full">
                <span class="text-sm text-gray-700" data-role="dropdown-label">State</span>
                <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                </button>
            </x-slot:toggle>

            @if(isset($board))
            @foreach($board->columns as $column)
                <x-ui.dropdown-item value="{{ $column->id }}" :selected="false">
                @switch($column->position ?? 1)
                    @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                    @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                    @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                    @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                    @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                @endswitch
                <span class="text-gray-700">{{ $column->name }}</span>
                </x-ui.dropdown-item>
            @endforeach
            @endif
            </x-ui.dropdown>

        {{-- Priority --}}
        <x-ui.dropdown
            type="priority"
            context="subtask"
            :multi="false"
            :search="true"
            width="w-64"
            :selected="''"
            toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
            >
            <x-slot:toggle>
                <button type="button" class="flex items-center gap-2 w-full">
                    <span class="text-sm text-gray-700" data-role="dropdown-label">Priority</span>
                    <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </x-slot:toggle>

            <x-ui.dropdown-item value="high" :selected="false">
                <i class="fa-solid fa-signal text-red-500"></i> <span>High</span>
            </x-ui.dropdown-item>
            <x-ui.dropdown-item value="medium" :selected="false">
                <i class="fa-solid fa-signal text-yellow-500"></i> <span>Medium</span>
            </x-ui.dropdown-item>
            <x-ui.dropdown-item value="low" :selected="false">
                <i class="fa-solid fa-signal text-blue-500"></i> <span>Low</span>
            </x-ui.dropdown-item>
            <x-ui.dropdown-item value="" :selected="false">
                <i class="fa-solid fa-minus text-gray-400"></i> <span>None</span>
            </x-ui.dropdown-item>
        </x-ui.dropdown>

        {{-- Assignees --}}
        <x-ui.dropdown
            type="assignees"
            context="subtask"
            :multi="true"
            :search="true"
            width="w-64"
            :selected="''"
            toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
            >
            <x-slot:toggle>
                <button type="button" class="flex items-center gap-2 w-full">
                    <span class="text-sm text-gray-700" data-role="dropdown-label">Assignees</span>
                    <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </x-slot:toggle>

            @if(isset($users))
                @foreach($users as $user)
                    <x-ui.dropdown-item value="{{ $user->id }}" :attrs="['data-name' => $user->name, 'data-image' => $user->image ? asset($user->image) : '']">
                        <div class="w-7 h-7 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">
                            @if($user->image)
                                <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                            @else
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            @endif
                        </div>
                        <span class="text-gray-700">{{ $user->name }}</span>
                    </x-ui.dropdown-item>
                @endforeach
            @endif
        </x-ui.dropdown>

        {{-- Labels --}}
        <x-ui.dropdown
            type="labels"
            context="subtask"
            :multi="true"
            :search="true"
            width="w-64"
            :selected="''"
            toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
            >
            <x-slot:toggle>
                <button type="button" class="flex items-center gap-2 w-full">
                <span class="text-sm text-gray-700" data-role="dropdown-label">Labels</span>
                <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                </button>
            </x-slot:toggle>

            @if(isset($labels) && $labels->count() > 0)
                @foreach($labels as $label)
                    <x-ui.dropdown-item value="{{ $label->id }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                        <span class="w-4 h-4 rounded-sm flex-shrink-0" style="background: {{ $label->color_details['gradient'] }}"></span>
                        <span class="text-gray-700">{{ $label->name }}</span>
                    </x-ui.dropdown-item>
                @endforeach
            @else
                <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
            @endif
        </x-ui.dropdown>

        {{-- Start Date --}}
        <div class="relative">
            <button type="button" id="createSubtaskStartDateButton" onclick="toggleDropdown('createSubtaskStartDatePicker')" class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer text-sm">
                <svg class="w-4 h-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                </svg>
                <span id="createSubtaskStartDateDisplay" class="text-gray-700">Add start date</span>
            </button>
            <div id="createSubtaskStartDatePicker" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start Date</label>
                <input type="datetime-local" id="createSubtaskStartDate" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateSubtaskDate('start_date')">
                <div class="flex gap-2 mt-2">
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('start_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('start_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('start_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                </div>
            </div>
        </div>

        {{-- Due Date --}}
        <div class="relative">
            <button type="button" id="createSubtaskDueDateButton" onclick="toggleDropdown('createSubtaskDueDatePicker')" class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer text-sm">
                <svg class="w-4 h-4 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                </svg>
                <span id="createSubtaskDueDateDisplay" class="text-gray-700">Add due date</span>
            </button>
            <div id="createSubtaskDueDatePicker" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                <input type="datetime-local" id="createSubtaskDueDate" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateSubtaskDate('due_date')">
                <div class="flex gap-2 mt-2">
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('due_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('due_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('due_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                </div>
            </div>
        </div>
      </div>
    </div>

    {{-- Footer --}}
    <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" id="createSubtaskCreateMore" class="rounded">
            <span>Create more</span>
        </label>
        <div class="flex gap-2">
            <button onclick="closeCreateSubtaskModal()" class="px-4 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 text-gray-700">Discard</button>
            <button onclick="submitCreateSubtask()" class="px-6 py-2 text-sm rounded-md bg-blue-600 hover:bg-blue-700 text-white font-semibold">Create</button>
        </div>
    </div>
  </div>
</div>

<div id="taskDrawerBackdrop" class="fixed inset-0 bg-black/30 hidden z-40" onclick="closeTaskDrawer()"></div>
<div id="taskDrawer" class="fixed top-0 right-0 h-full w-full sm:w-[720px] bg-white shadow-2xl border-l border-gray-200 hidden z-50">
  <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
    <div class="flex items-center gap-2">
      <button class="p-2 rounded hover:bg-gray-100" onclick="closeTaskDrawer()" title="Back">
        <!-- back arrow -->
        <svg class="w-4 h-4 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 111.414 1.414L5.414 9H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
        </svg>
      </button>

    </div>

    <div class="flex items-center gap-2">
      <div class="relative">
        <button class="p-2 rounded hover:bg-gray-100" title="More" onclick="event.stopPropagation(); toggleDropdown('drawer-more-menu')">
          <svg class="w-5 h-5 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm8 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
        </button>

        {{-- Dropdown Menu --}}
        <div id="drawer-more-menu" class="hidden absolute right-0 top-full mt-1 w-40 bg-white rounded-lg shadow-xl border border-gray-200 z-50 py-1">
          <button onclick="event.stopPropagation(); copyTask(currentDrawerTaskId)" 
                  class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
              <path d="M4 3a1 1 0 00-1 1v11a1 1 0 001 1h5v2H4a3 3 0 01-3-3V4a3 3 0 013-3h11a3 3 0 013 3v5h-2V4a1 1 0 00-1-1H4z"/>
              <path d="M9 9a1 1 0 011-1h5.586l-2.293-2.293a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L15.586 10H10a1 1 0 01-1-1z"/>
            </svg>
            Duplicate
          </button>
          <button onclick="event.stopPropagation(); deleteTask(currentDrawerTaskId)" 
                  class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            Delete
          </button>
        </div>
      </div>

    </div>
  </div>

  <div class="h-[calc(100%-56px)] overflow-y-auto">
    <div class="px-6 py-6">

      <!-- Task Code -->
      <div class="text-xs text-gray-500 font-medium" id="drawerTaskCode"></div>

      <!-- Title -->
      <div class="mt-1 text-2xl font-semibold text-gray-900" id="drawerTaskTitle"></div>

      <!-- Description -->
      <div class="mt-4">
        <div class="w-full">
          <textarea
            rows="5"
            class="w-full min-w-0 resize-none border min-w-0 resize-none border-gray-200 p-2 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm"
            id="drawerTaskDescription"
            placeholder="Write description...">
          </textarea>
        </div>

        <!-- last edited -->
        <div class="mt-4 flex items-center justify-end text-xs text-gray-500">
          <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 8.414V5a1 1 0 10-2 0v6a1 1 0 00.293.707l3 3a1 1 0 001.414-1.414L11 10.414z"/>
            </svg>
            <span id="drawerLastEdited"></span>
          </span>
        </div>

      {{-- Overall progress. Hidden until the task actually has sub-work items,
           so a plain task's drawer looks exactly as it did before. The fill is
           painted one frame after the width is known (paintTaskProgress in
           task-subtask.js) so it animates up from zero on every open. --}}
      <div id="drawerTaskProgress" class="hidden items-center gap-3 mt-4">
          <div class="tp-track tp-lg flex-1">
              <div class="tp-fill tp-fluid" style="width: 0%"></div>
          </div>
          <strong class="text-sm text-gray-700 tabular-nums" data-role="tp-pct">0%</strong>
      </div>
      </div>

      <!-- Action buttons -->
      <div class="mt-6 flex flex-wrap gap-2">
        {{-- Add sub-work item dropdown --}}
        <div class="relative" id="addSubworkWrapper">
            <button onclick="event.stopPropagation(); toggleDropdown('addSubworkMenu')"
                    class="px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zm0 8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zm6-8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2zm0 8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"/>
                </svg>
                Add sub-work item
                <svg class="w-3 h-3 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
            </button>
            <div id="addSubworkMenu" class="hidden absolute left-0 top-full mt-1 w-44 bg-white rounded-lg shadow-xl border border-gray-200 z-50 py-1">
                <button onclick="closeAllDropdowns(); openCreateSubtaskModal(currentDrawerTaskId)"
                        class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                    Create new
                </button>
                <button onclick="closeAllDropdowns(); openAddParentModal(currentDrawerTaskId, true)"
                        class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                    Add existing
                </button>
            </div>
        </div>

        <button class="px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm flex items-center gap-2" onclick="openAddLinkModal()">
          <span class="text-gray-600">🔗</span> Add link
        </button>
        <button class="px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm flex items-center gap-2"
                onclick="document.getElementById('attachmentInput').click()">
            📎 Attach
        </button>
        <input type="file" id="attachmentInput" name="attachment" class="hidden" multiple accept="*/*" onchange="handleAttachmentUpload(event)">

        {{-- Task report — the open task printed as a PDF: properties, description,
             sub-work items with their banked time, links, attachments, activity.
             Route::has() guard so a server with a stale route cache renders the
             board without the button instead of throwing while rendering it. --}}
        @if(Route::has('role.tasks.report'))
        <button type="button" id="drawerTaskReportBtn"
                class="px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm flex items-center gap-2"
                onclick="downloadTaskReport()"
                title="Download this task as a PDF report">
            <svg class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
            <span data-label>Task report</span>
        </button>
        @endif

        {{-- Evaluation — jumps to the Task Evaluation panel further down the
             Properties tab, switching tabs first if you are on Activity.

             Hidden by inline style rather than the `hidden` class: the button
             needs `display:flex` when shown, and .hidden vs .flex are two
             Tailwind utilities of equal specificity whose winner depends on
             their order in the bundle. An inline display has no such argument.

             It reveals itself only when the panel it points at is actually on
             screen — always for a super admin, and for an employee only once
             there is a verdict to read. A button that scrolls to nothing is
             worse than no button. --}}
        @if(Route::has('role.tasks.oversight.show'))
        <button type="button" id="drawerEvaluationBtn"
                class="px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm items-center gap-2"
                style="display:none"
                title="Jump to the restriction, red flag and rating">
            <svg class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.226 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span>Evaluation</span>
            {{-- A dot only when there is something to see, so the button reads
                 as "this task has been judged" without being opened. --}}
            <span id="drawerEvaluationDot" class="eto-btn-dot" style="display:none"></span>
        </button>
        @endif
      </div>

      {{-- Sub-work items section --}}
      <div id="drawerSubtasksSection" class="mt-5">
          <div class="border border-gray-200 rounded-md overflow-hidden">
              <div class="flex items-center justify-between px-3 py-2 bg-white">
                  <button class="flex items-center gap-2 text-sm font-medium text-gray-800"
                          onclick="toggleSection('drawerSubtasksBody')">
                      <svg id="drawerSubtasksCaret" class="w-4 h-4 text-gray-500 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                      </svg>
                      <span>Sub-work items</span>
                      <span id="drawerSubtasksCount" class="text-gray-500 font-normal ml-1"></span>
                  </button>
                  <div class="flex items-center gap-2">
                  {{-- Total time banked across the sub-work items below. Hidden
                       until something has actually been tracked, so a task that
                       nobody times looks exactly as it did before. --}}
                  <span id="drawerSubtasksTracked" class="hidden items-center gap-1 px-2 py-0.5 rounded-full border border-gray-200 bg-gray-50 text-[11px] font-medium text-gray-600 whitespace-nowrap">
                      <svg class="w-3 h-3 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.5 2.5a1 1 0 001.414-1.414L11 9.586V6z" clip-rule="evenodd"/></svg>
                      <span class="tracked-text"></span>
                  </span>
                  <div id="drawerSubtasksProgress" class="hidden items-center gap-1.5">
                      <svg class="w-4 h-4 -rotate-90" viewBox="0 0 16 16">
                          <circle cx="8" cy="8" r="6" fill="none" stroke="#e5e7eb" stroke-width="2.5"></circle>
                          <circle class="subtask-ring" cx="8" cy="8" r="6" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="37.7" stroke-dashoffset="37.7"></circle>
                      </svg>
                      <span class="subtask-pct text-xs font-medium text-gray-600"></span>
                  </div>
                  </div>
              </div>
              <div id="drawerSubtasksBody" class="hidden border-t border-gray-200">
                  <div id="drawerSubtasksList" class="divide-y divide-gray-100"></div>
              </div>
          </div>
      </div>

    <div class="mt-5 space-y-4">
        <!-- LINKS -->
        <div class="border border-gray-200 rounded-md overflow-hidden">
            <!-- header -->
            <div class="flex items-center justify-between px-3 py-2 bg-white">
                <button class="flex items-center gap-2 text-sm font-medium text-gray-800"
                        onclick="toggleSection('drawerLinksBody')">
                    <!-- caret -->
                    <svg id="drawerLinksCaret" class="w-4 h-4 text-gray-500 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                    <span>Links</span>
                    <span class="text-gray-500 font-normal" id="drawerLinksCount">1</span>
                </button>

                <button class="p-1.5 rounded hover:bg-gray-100" title="Add link" onclick="openAddLinkModal()">
                    <svg class="w-4 h-4 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/>
                    </svg>
                </button>
            </div>

            <!-- body -->
            <div id="drawerLinksBody" class="hidden border-t border-gray-200">
                <!-- list -->
                <div class="p-2 space-y-2" id="drawerLinksList">

                    <!-- single link row -->
                    <div class="flex items-center justify-between gap-3 px-3 py-2 rounded-md border border-gray-200 hover:bg-gray-50">
                        <div class="flex items-center gap-2 min-w-0">
                            <!-- link icon -->
                            <svg class="w-4 h-4 text-gray-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M12.586 2.586a2 2 0 012.828 0l2 2a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0 1 1 0 011.414-1.414.5.5 0 00.707 0l5-5a.5.5 0 000-.707l-2-2a.5.5 0 00-.707 0l-2 2A1 1 0 019.172 4l2.414-2.414z"/>
                            <path d="M7.414 7.414a2 2 0 012.828 0 1 1 0 11-1.414 1.414.5.5 0 00-.707 0l-5 5a.5.5 0 000 .707l2 2a.5.5 0 00.707 0l2-2A1 1 0 019.828 16l-2.414 2.414a2 2 0 01-2.828 0l-2-2a2 2 0 010-2.828l5-5z"/>
                            </svg>

                            <a href="#"
                            class="text-sm text-gray-800 hover:text-blue-600 truncate"
                            title="epal it solutions">
                            epal it solutions
                            </a>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-gray-500">4 minutes ago</span>

                            <!-- copy -->
                            <button class="p-1.5 rounded hover:bg-gray-100" title="Copy link">
                                <svg class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M7 7a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H9a2 2 0 01-2-2V7z"/>
                                    <path d="M5 3a2 2 0 00-2 2v8a2 2 0 002 2h1a1 1 0 100-2H5V5h8v1a1 1 0 102 0V5a2 2 0 00-2-2H5z"/>
                                </svg>
                            </button>

                            <!-- menu -->
                            <button class="p-1.5 rounded hover:bg-gray-100" title="More">
                                <svg class="w-5 h-5 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm8 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ATTACHMENTS -->
            <div class="border border-gray-200 rounded-md overflow-hidden">
                <!-- header -->
                <div class="flex items-center justify-between px-3 py-2 bg-white">
                    <button class="flex items-center gap-2 text-sm font-medium text-gray-800"
                            onclick="toggleSection('drawerAttachmentsBody')">
                        <svg id="drawerAttachmentsCaret" class="w-4 h-4 text-gray-500 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                        <span>Attachments</span>
                        <span class="text-gray-500 font-normal" id="drawerAttachmentsCount">2</span>
                    </button>

                    <button class="p-1.5 rounded hover:bg-gray-100" title="Add attachment" onclick="document.getElementById('attachmentInput').click()">
                        <svg class="w-4 h-4 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/>
                        </svg>
                    </button>
                </div>

                <!-- body -->
                <div id="drawerAttachmentsBody" class="hidden border-t border-gray-200">
                    <div class="p-2 space-y-2" id="drawerAttachmentsList">
                        {{-- dynamic attachment image will append here --}}
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mt-8 border-b border-gray-200">
                <div class="flex gap-6">
                <button id="tabPropertiesBtn"
                        class="py-3 text-sm font-medium border-b-2 border-blue-600 text-blue-600"
                        onclick="showDrawerTab('properties')">
                    Properties
                </button>
                <button id="tabActivityBtn"
                        class="py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-800"
                        onclick="showDrawerTab('activity')">
                    Activity
                </button>
                </div>
            </div>

            <!-- Properties -->
            <div id="tabProperties" class="mt-5">
                <div class="grid grid-cols-1 gap-4 pb-96">

                {{-- Assignees --}}
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>👥</span> Assignees
                    </div>

                    <div class="col-span-8">
                        <x-ui.dropdown
                            type="assignees"
                            context="drawer"
                            :multi="true"
                            :search="true"
                            width="w-64"
                            :selected="''"
                            toggleClass="w-full flex items-center gap-2 px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm"
                        >
                            <x-slot:toggle>
                                <button type="button" class="w-full flex items-center gap-2">
                                    <span class="text-gray-700" data-role="dropdown-label">Select assignees</span>
                                    <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </x-slot:toggle>

                            @foreach($users as $user)
                                <x-ui.dropdown-item value="{{ $user->id }}" :attrs="['data-name' => $user->name, 'data-image' => $user->image? asset($user->image) : '']">
                                    <div class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-[10px] text-white font-semibold">
                                        @if($user->image)
                                            <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                                        @else
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <span class="text-gray-700">{{ $user->name }}</span>
                                </x-ui.dropdown-item>
                            @endforeach
                        </x-ui.dropdown>
                    </div>
                </div>

                {{-- State/Column --}}
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>◯</span> State
                    </div>

                    <div class="col-span-8">
                        <x-ui.dropdown
                            type="state"
                            context="drawer"
                            :multi="false"
                            :search="true"
                            width="w-64"
                            :selected="''"
                            toggleClass="w-full flex items-center gap-2 px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm"
                        >
                            <x-slot:toggle>
                                <button type="button" class="w-full flex items-center gap-2">
                                    <span class="text-gray-700" data-role="dropdown-label">Select state</span>
                                    <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </x-slot:toggle>

                            @if(!isset($viewMode) || $viewMode !== 'my-tasks')
                            @foreach($board->columns as $col)
                                <x-ui.dropdown-item value="{{ $col->id }}" :attrs="['data-name' => $col->name]">
                                    @switch($col->position ?? 1)
                                        @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                                        @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                                        @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                                        @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                                        @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                                    @endswitch
                                    <span class="text-gray-700">{{ $col->name }}</span>
                                </x-ui.dropdown-item>
                            @endforeach
                            @endif
                            {{-- In my-tasks mode, items are populated dynamically by JS --}}
                        </x-ui.dropdown>
                    </div>
                </div>


                {{-- Priority --}}
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>📶</span> Priority
                    </div>

                    <div class="col-span-8">
                        <x-ui.dropdown
                            type="priority"
                            context="drawer"
                            :multi="false"
                            :search="false"
                            width="w-64"
                            :selected="''"
                            toggleClass="w-full flex items-center gap-2 px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm"
                        >
                            <x-slot:toggle>
                                <button type="button" class="w-full flex items-center gap-2">
                                    <span class="text-gray-700" data-role="dropdown-label">None</span>
                                    <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </x-slot:toggle>

                            <x-ui.dropdown-item value="high">
                                <i class="fa-solid fa-signal text-red-500"></i>
                                <span class="text-gray-700">High</span>
                            </x-ui.dropdown-item>

                            <x-ui.dropdown-item value="medium">
                                <i class="fa-solid fa-signal text-yellow-500"></i>
                                <span class="text-gray-700">Medium</span>
                            </x-ui.dropdown-item>

                            <x-ui.dropdown-item value="low">
                                <i class="fa-solid fa-signal text-blue-500"></i>
                                <span class="text-gray-700">Low</span>
                            </x-ui.dropdown-item>

                            <x-ui.dropdown-item value="">
                                <i class="fa-solid fa-minus text-gray-400"></i>
                                <span class="text-gray-700">None</span>
                            </x-ui.dropdown-item>
                        </x-ui.dropdown>
                    </div>
                </div>

                <!-- Row: Parent -->
                <div id="drawerParentRow" class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zm0 8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zm6-8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2zm0 8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"/></svg>
                        Parent
                    </div>
                    <div class="col-span-8">
                        <div id="drawerParentValue" class="px-3 py-2 text-sm text-gray-800">
                            {{-- Populated by JS --}}
                        </div>
                    </div>
                </div>

                <!-- Row: Created by -->
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>🧑</span> Created by
                    </div>
                    <div class="col-span-8">
                        <div class="px-3 py-2 text-sm text-gray-800">
                            <span id="drawerCreatedBy">Loading...</span>
                        </div>
                    </div>
                </div>

                <!-- Row: Start date -->
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                    <span>📅</span> Start date
                    </div>
                    <div class="col-span-8 relative">
                        <button onclick="toggleDropdown('drawerStartDatePicker')" class="w-full text-left px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm" id="drawerStartDateButton">
                            <span id="drawerStartDateDisplay">Add start date</span>
                        </button>
                        <div id="drawerStartDatePicker" class="drawer-dropdown hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start  Date</label>
                            <input type="datetime-local" id="drawerStartDateInput" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateDrawerDate(currentDrawerTaskId, 'start_date')">
                            <div class="flex gap-2 mt-2">
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'start_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'start_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'start_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row: Due date -->
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                    <span>🗓️</span> Due date
                    </div>
                    <div class="col-span-8 relative">
                        <button onclick="toggleDropdown('drawerDueDatePicker')" class="w-full text-left px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm" id="drawerDueDateButton">
                            <span id="drawerDueDateDisplay">Add due date</span>
                        </button>
                        <div id="drawerDueDatePicker" class="drawer-dropdown hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                            <input type="datetime-local" id="drawerDueDateInput" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateDrawerDate(currentDrawerTaskId, 'due_date')">
                            <div class="flex gap-2 mt-2">
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'due_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'due_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'due_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row: Modules -->
                {{-- <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>▦</span> Modules
                    </div>
                    <div class="col-span-8">
                        <div class="px-3 py-2 text-sm text-gray-500" id="drawerModules">No module</div>
                    </div>
                </div> --}}

                <!-- Row: Cycle -->
                {{-- <div class="grid grid-cols-12 items-center gap-3">
                        <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                            <span>⟳</span> Cycle
                        </div>
                        <div class="col-span-8">
                            <div class="px-3 py-2 text-sm text-gray-500" id="drawerCycle">No cycle</div>
                        </div>
                </div> --}}

                <!-- Row: Parent -->
                {{-- <div class="grid grid-cols-12 items-center gap-3">
                        <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                            <span>↥</span> Parent
                        </div>
                        <div class="col-span-8">
                            <button class="w-full text-left px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm">
                                <span class="text-gray-500" id="drawerParent">Add parent work item</span>
                            </button>
                        </div>
                    </div> --}}

                <!-- Row: Labels -->
                {{-- Labels --}}
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>🏷️</span> Labels
                    </div>

                    <div class="col-span-8">
                        <x-ui.dropdown
                            type="labels"
                            context="drawer"
                            :multi="true"
                            :search="true"
                            width="w-64"
                            :selected="''"
                            toggleClass="w-full flex items-center gap-2 px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm"
                        >
                            <x-slot:toggle>
                                <button type="button" class="w-full flex items-center gap-2">
                                    <span class="text-gray-700" data-role="dropdown-label">Select labels</span>
                                    <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </x-slot:toggle>

                            @if(isset($labels) && $labels->count() > 0)
                                @foreach($labels as $label)
                                    <x-ui.dropdown-item value="{{ $label->id }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                                        <span class="w-3.5 h-3.5 rounded-sm" style="background: {{ $label->color_details['gradient'] }}"></span>
                                        <span class="text-gray-700">{{ $label->name }}</span>
                                    </x-ui.dropdown-item>
                                @endforeach
                            @else
                                <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
                            @endif
                        </x-ui.dropdown>
                    </div>
                </div>

                {{-- ══ TASK EVALUATION ═══════════════════════════════════════════════
                     The three oversight controls: restrict, red-flag, rate.

                     Rendered for everybody but filled in by JS, which is also what
                     decides whether the controls are live: the server answers
                     can_manage on GET /tasks/{id}/oversight and only a super admin
                     gets true. An employee sees the same panel read-only, and only
                     when there is actually a verdict to read — a task nobody has
                     acted on shows them nothing at all.

                     Starts hidden and stays hidden if the fetch fails, so a drawer
                     opens exactly as it did before whatever happens here. --}}
                <div id="etoPanel" class="eto-panel hidden">
                    <div class="eto-panel-head">
                        <svg class="w-4 h-4" style="color:#6b7280;" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.226 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>Task evaluation</span>
                        <span id="etoPanelHint" style="margin-left:auto;font-weight:500;text-transform:none;letter-spacing:0;color:#9ca3af;font-size:.68rem;"></span>
                    </div>

                    <div class="eto-panel-body">
                        <div id="etoReadonlyBanner" class="eto-locked-banner hidden" style="margin-bottom:.7rem;">
                            <svg class="w-4 h-4" style="flex-shrink:0;margin-top:1px;" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            <span id="etoReadonlyText"></span>
                        </div>

                        {{-- Restriction --}}
                        <div class="eto-row" id="etoRowLock">
                            <div class="eto-row-label">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                Restriction
                            </div>
                            <div class="eto-row-body">
                                <label class="eto-switch lock">
                                    <input type="checkbox" id="etoLock">
                                    <span class="eto-switch-track"></span>
                                    <span class="eto-switch-text" id="etoLockText">Not restricted</span>
                                </label>
                                <textarea id="etoLockNote" class="eto-note hidden" rows="2" maxlength="500"
                                          placeholder="Why is this locked? (optional, the employee sees it)"></textarea>
                                <div class="eto-stamp" id="etoLockStamp"></div>
                            </div>
                        </div>

                        {{-- Red flag --}}
                        <div class="eto-row" id="etoRowFlag">
                            <div class="eto-row-label">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 2a1 1 0 011-1h12l-3 4 3 4H5v9a1 1 0 11-2 0V2z"/></svg>
                                Red flag
                            </div>
                            <div class="eto-row-body">
                                <label class="eto-switch">
                                    <input type="checkbox" id="etoFlag">
                                    <span class="eto-switch-track"></span>
                                    <span class="eto-switch-text" id="etoFlagText">Not flagged</span>
                                </label>
                                <textarea id="etoFlagNote" class="eto-note hidden" rows="2" maxlength="500"
                                          placeholder="What is wrong with this one? (optional)"></textarea>
                                <div class="eto-stamp" id="etoFlagStamp"></div>
                            </div>
                        </div>

                        {{-- Rating --}}
                        <div class="eto-row" id="etoRowRate">
                            <div class="eto-row-label">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.29 3.98a1 1 0 00.95.69h4.18c.97 0 1.37 1.24.59 1.81l-3.39 2.46a1 1 0 00-.36 1.12l1.3 3.98c.3.92-.76 1.69-1.54 1.12l-3.39-2.46a1 1 0 00-1.18 0l-3.39 2.46c-.78.57-1.84-.2-1.54-1.12l1.3-3.98a1 1 0 00-.36-1.12L2.02 9.41c-.78-.57-.38-1.81.59-1.81H6.8a1 1 0 00.95-.69l1.3-3.98z"/></svg>
                                Rating
                            </div>
                            <div class="eto-row-body">
                                <div style="display:flex;align-items:center;gap:.2rem;flex-wrap:wrap;">
                                    <div class="eto-stars-input" id="etoStars"></div>
                                    <span class="eto-rating-score" id="etoScore"></span>
                                    <button type="button" class="eto-clear-rating hidden" id="etoClearRating">clear</button>
                                </div>
                                <textarea id="etoRateNote" class="eto-note hidden" rows="2" maxlength="2000"
                                          placeholder="Remark on this work (optional)"></textarea>
                                <div class="eto-stamp" id="etoRateStamp"></div>
                            </div>
                        </div>
                    </div>
                </div>

                </div>
            </div>

            <!-- Activity -->
            <div id="tabActivity" class="mt-5 hidden">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" />
                    </svg>
                    <div class="text-lg font-semibold text-gray-900">Feedback &amp; Communication</div>
                </div>

                <!-- Timeline list -->
                <div class="mt-4 space-y-4" id="drawerActivityList">
                    <div class="text-center py-8 text-gray-400 text-sm">
                        Loading activities...
                    </div>
                </div>

                <!-- Comment box -->
                <div class="mt-6">
                    <label for="drawerCommentInput" class="block text-sm font-medium text-gray-700 mb-2">
                        Add a comment (as {{ ucfirst($role) }})
                    </label>
                    <textarea id="drawerCommentInput"
                            class="w-full border border-gray-200 rounded-lg p-3 text-sm focus:ring-2 focus:ring-purple-200 focus:border-purple-300 resize-none"
                            rows="3"
                            placeholder="e.g. I reviewed this task — please prioritise the marketing proposal instead."></textarea>
                    <div class="mt-2 flex justify-end">
                        <button onclick="submitComment()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium">
                            Comment
                        </button>
                    </div>
                </div>

            </div>

        </div>
    </div>


{{-- ───────────── Add existing / set parent modal ───────────── --}}
<div id="addParentBackdrop" class="fixed inset-0 bg-black/30 hidden z-50" onclick="closeAddParentModal()"></div>
<div id="addParentModal" class="fixed inset-0 hidden z-50 items-center justify-center px-4" aria-modal="true" role="dialog">
  <div class="w-full max-w-lg bg-white rounded-lg shadow-xl border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-900" id="addParentModalTitle">Add existing work item</h3>
      <button onclick="closeAddParentModal()" class="p-1 rounded hover:bg-gray-100">
        <svg class="w-5 h-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
      </button>
    </div>
    <div class="px-6 py-4">
      <input type="hidden" id="addParentTaskId">
      <input type="hidden" id="addParentMode"> {{-- "subtask" or "parent" --}}
      <div class="relative mb-3">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
        <input type="text" id="addParentSearchInput" placeholder="Type to search"
               class="w-full border border-gray-200 rounded-md pl-9 pr-3 py-2 text-sm focus:outline-none focus:border-blue-400"
               oninput="searchParentTasks(this.value)">
      </div>
      <div class="flex items-center justify-between mb-2">
        <span id="addParentSelectedCount" class="text-xs text-gray-500">No work items selected</span>
        <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
          <span>Workspace level</span>
          <span class="relative inline-block w-9 h-5">
            <input type="checkbox" id="addParentWorkspaceLevel" class="peer sr-only" onchange="searchParentTasks(document.getElementById('addParentSearchInput').value)">
            <span class="absolute inset-0 rounded-full bg-gray-300 peer-checked:bg-blue-600 transition-colors"></span>
            <span class="absolute left-0.5 top-0.5 w-4 h-4 rounded-full bg-white transition-transform peer-checked:translate-x-4"></span>
          </span>
        </label>
      </div>
      <div id="addParentSearchResults" class="max-h-60 overflow-y-auto divide-y divide-gray-100 border border-gray-200 rounded-md">
        <p class="px-3 py-4 text-sm text-gray-400 text-center">Type to search tasks...</p>
      </div>
    </div>
    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-2">
      <button onclick="closeAddParentModal()" class="px-4 py-2 text-sm text-gray-700 border border-gray-200 rounded-md hover:bg-gray-50">Cancel</button>
      <button id="addSelectedWorkItemsBtn" onclick="addSelectedWorkItems()" disabled
              class="hidden px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
        Add selected work items
      </button>
    </div>
  </div>
</div>

<div id="addLinkBackdrop" class="fixed inset-0 bg-black/30 hidden z-50" onclick="closeAddLinkModal()"></div>
<div id="addLinkModal" class="fixed inset-0 hidden z-50 flex items-center justify-center px-4" aria-modal="true" role="dialog">
  <div class="w-full max-w-2xl bg-white rounded-lg shadow-xl border border-gray-200">
    <!-- Header -->
    <div class="px-6 py-4">
      <h3 class="text-lg font-semibold text-gray-900">Add link</h3>
    </div>

    <!-- Body -->
    <div class="px-6 pb-5 space-y-4">
      <!-- URL -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
        <input id="linkUrl"
               type="url"
               placeholder="Type or paste a URL"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
        <p id="linkUrlError" class="mt-1 text-xs text-red-600 hidden">Please enter a valid URL.</p>
      </div>

      <!-- Display title -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Display title</label>
        <div class="text-xs text-gray-500 -mt-1 mb-1">Optional</div>
        <input id="linkTitle" type="text" placeholder="What you'd like to see this link as"
        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
      </div>

      <!-- Hidden: store task id -->
      <input type="hidden" id="linkTaskId" value="">
    </div>

    <!-- Footer -->
    <div class="px-6 py-4 flex items-center justify-end gap-2 border-t border-gray-200">
        <button type="button" class="px-4 py-2 text-sm rounded border border-gray-300 hover:bg-gray-50" onclick="closeAddLinkModal()">
            Cancel
        </button>

        <button type="button" class="px-4 py-2 text-sm rounded bg-blue-600 text-white hover:bg-blue-700" id="linkbutton" onclick="submitAddLink()">
            Add Link
        </button>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    // ── Server-side config passed to JS modules ──
    window.__boardConfig = {
        role: @json($role),
        @if(isset($viewMode) && $viewMode === 'my-tasks')
        boardId: null,
        workspaceId: null,
        projectId: null,
        viewMode: 'my-tasks',
        boardColumnsMap: @json($boardColumnsMap),
        columns: [],
        @else
        boardId: {{ $board->id }},
        workspaceId: {{ $board->workspace_id }},
        projectId: {{ $board->project_id }},
        viewMode: 'board',
        boardColumnsMap: {},
        columns: @json($board->columns->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'position' => $c->position ?? 1])->values()),
        @endif
        users: @json(($users ?? collect())->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'image' => $u->image ? asset($u->image) : null])->values()),
        moveUrl: "{{ route('role.tasks.move', $role) }}",
        attachmentUploadUrl: "{{ route('role.tasks.attachments', $role) }}",
        linkStoreUrl: "{{ route('role.tasks.links.store', $role) }}",
    };

    // ── Task report (PDF) ──
    // Downloads whatever task the drawer currently has open. Fetched as a blob
    // rather than sent through location.href so a failure surfaces as a message
    // instead of a silently empty download. Kept in the page rather than in the
    // JS bundle: it needs no state of its own, and the drawer already publishes
    // the open task's id on window (board-show.js).
    @if(Route::has('role.tasks.report'))
    window.downloadTaskReport = async function () {
        const taskId = window.currentDrawerTaskId;
        if (!taskId) return;

        const btn      = document.getElementById('drawerTaskReportBtn');
        const label    = btn ? btn.querySelector('[data-label]') : null;
        const original = label ? label.textContent : '';
        // opacity-60 is toggled from here rather than via a disabled: variant —
        // that variant is not in the built CSS, and this page must not need a
        // Tailwind rebuild to look right.
        if (btn)   { btn.disabled = true; btn.classList.add('opacity-60'); }
        if (label) label.textContent = 'Preparing…';

        try {
            const url = @json(route('role.tasks.report', ['role' => $role, 'task' => '__TASK__']))
                .replace('__TASK__', taskId);

            const res = await fetch(url, { headers: { 'Accept': 'application/pdf' } });
            if (!res.ok) throw new Error('The server returned ' + res.status + '.');

            const disposition = res.headers.get('Content-Disposition') || '';
            const named       = disposition.match(/filename="?([^";]+)"?/i);
            const blob        = await res.blob();
            const objectUrl   = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = objectUrl;
            a.download = named ? named[1] : ('TASK-' + taskId + '_report.pdf');
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(objectUrl);
        } catch (e) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Could not build the report',
                    text: e.message,
                    confirmButtonColor: '#4f46e5',
                });
            } else {
                alert('Could not build the report. ' + e.message);
            }
        } finally {
            if (btn)   { btn.disabled = false; btn.classList.remove('opacity-60'); }
            if (label) label.textContent = original;
        }
    };
    @endif
</script>
{{-- ══ TASK EVALUATION — the drawer panel's behaviour ═══════════════════════
     Inline rather than in resources/js/task/, deliberately: it needs no build
     step, so this feature ships by uploading a view. The board's own JS is a
     Vite bundle and adding to it would mean a rebuild and a new manifest on a
     server that has no npm.

     It touches nothing outside #etoPanel and it wraps window.openTaskDrawer
     rather than editing it, so with the panel absent — an older cached view, a
     failed fetch — the drawer opens exactly as it always did.
  ═══════════════════════════════════════════════════════════════════════════ --}}
@if(Route::has('role.tasks.oversight.show'))
<script>
(function () {
    'use strict';

    var ROLE  = @json($role);
    var STAR  = '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.29 3.98a1 1 0 00.95.69h4.18c.97 0 1.37 1.24.59 1.81l-3.39 2.46a1 1 0 00-.36 1.12l1.3 3.98c.3.92-.76 1.69-1.54 1.12l-3.39-2.46a1 1 0 00-1.18 0l-3.39 2.46c-.78.57-1.84-.2-1.54-1.12l1.3-3.98a1 1 0 00-.36-1.12L2.02 9.41c-.78-.57-.38-1.81.59-1.81H6.8a1 1 0 00.95-.69l1.3-3.98z"/></svg>';

    var taskId = null;      // which task the panel is showing
    var canManage = false;  // super admin?
    var rating = null;      // stars currently set
    var busy = false;

    function $(id) { return document.getElementById(id); }
    function token() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    /* ── Painting ────────────────────────────────────────────────────── */

    function buildStars() {
        var host = $('etoStars');
        if (!host || host.children.length) return;
        for (var i = 1; i <= 5; i++) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'eto-star';
            b.dataset.value = String(i);
            b.title = i + ' of 5';
            b.innerHTML = STAR;
            host.appendChild(b);
        }
        host.addEventListener('click', function (e) {
            var b = e.target.closest('.eto-star');
            if (!b || !canManage) return;
            var v = parseInt(b.dataset.value, 10);
            // Clicking the star already set clears the rating — the same
            // gesture that gave it takes it back.
            save({ rating: v === rating ? null : v, rating_note: $('etoRateNote').value || null });
        });
        // Hover preview, which is the whole reason a star row beats a number.
        host.addEventListener('mouseover', function (e) {
            var b = e.target.closest('.eto-star');
            if (!b || !canManage) return;
            var v = parseInt(b.dataset.value, 10);
            Array.prototype.forEach.call(host.children, function (c, i) {
                c.classList.toggle('hot', i < v);
            });
        });
        host.addEventListener('mouseleave', function () {
            Array.prototype.forEach.call(host.children, function (c) { c.classList.remove('hot'); });
        });
    }

    function paint(v) {
        var panel = $('etoPanel');
        if (!panel) return;

        // Nothing said and nothing I may say — show the employee nothing at all
        // rather than an empty panel about a judgement that was never made.
        if (!v && !canManage) { panel.classList.add('hidden'); showButton(false); return; }
        panel.classList.remove('hidden');
        showButton(true, v);

        v = v || {};
        rating = v.rating || null;

        $('etoPanelHint').textContent = canManage ? '' : 'Set by the Super Admin';
        $('etoReadonlyBanner').classList.toggle('hidden', !(v.is_restricted && !canManage));
        if (v.is_restricted && !canManage) {
            $('etoReadonlyText').innerHTML = 'This task is <strong>restricted</strong> — you cannot edit or delete it.'
                + (v.restriction_note ? '<br>' + escapeHtml(v.restriction_note) : '')
                + (v.restricted_by ? '<br><span style="opacity:.75">Locked by ' + escapeHtml(v.restricted_by)
                    + (v.restricted_at ? ' · ' + escapeHtml(v.restricted_at) : '') + '</span>' : '');
        }

        // Restriction
        var lock = $('etoLock');
        lock.checked  = !!v.is_restricted;
        lock.disabled = !canManage;
        $('etoLockText').textContent = v.is_restricted ? 'Restricted — cannot be edited or deleted' : 'Not restricted';
        note('etoLockNote', v.restriction_note, v.is_restricted);
        stamp('etoLockStamp', v.is_restricted, v.restricted_by, v.restricted_at, 'Locked by');

        // Red flag
        var flag = $('etoFlag');
        flag.checked  = !!v.red_flag;
        flag.disabled = !canManage;
        $('etoFlagText').textContent = v.red_flag ? 'Red-flagged' : 'Not flagged';
        note('etoFlagNote', v.red_flag_note, v.red_flag);
        stamp('etoFlagStamp', v.red_flag, v.red_flagged_by, v.red_flagged_at, 'Flagged by');

        // Rating
        var stars = $('etoStars');
        Array.prototype.forEach.call(stars.children, function (c, i) {
            c.classList.toggle('on', rating !== null && i < rating);
            c.disabled = !canManage;
        });
        $('etoScore').textContent = rating ? rating + '/5' : '';
        $('etoClearRating').classList.toggle('hidden', !(rating && canManage));
        note('etoRateNote', v.rating_note, rating !== null);
        stamp('etoRateStamp', rating !== null, v.rated_by, v.rated_at, 'Rated by');

        // Rows with nothing in them are noise on a read-only panel.
        if (!canManage) {
            $('etoRowLock').classList.toggle('hidden', !v.is_restricted);
            $('etoRowFlag').classList.toggle('hidden', !v.red_flag);
            $('etoRowRate').classList.toggle('hidden', rating === null);
        } else {
            $('etoRowLock').classList.remove('hidden');
            $('etoRowFlag').classList.remove('hidden');
            $('etoRowRate').classList.remove('hidden');
        }
    }

    /* The Evaluation button in the drawer's action row. Inline display, not the
       `hidden` class — see the note on the button itself. */
    function showButton(on, v) {
        var b = $('drawerEvaluationBtn');
        if (!b) return;
        b.style.display = on ? 'flex' : 'none';

        var dot = $('drawerEvaluationDot');
        if (!dot) return;
        var flagged = !!(v && (v.red_flag || v.is_restricted));
        var rated   = !!(v && v.rating);
        dot.style.display = (flagged || rated) ? 'block' : 'none';
        dot.classList.toggle('rated', rated && !flagged);
        b.title = flagged
            ? 'This task is flagged or restricted — open the evaluation'
            : (rated ? 'Rated ' + v.rating + '/5 — open the evaluation'
                     : 'Jump to the restriction, red flag and rating');
    }

    /* Jump to the panel. It lives inside the Properties tab, so a click from
       the Activity tab has to switch tab first or it would scroll to something
       that is display:none. */
    function jumpToPanel() {
        var panel = $('etoPanel');
        if (!panel || panel.classList.contains('hidden')) return;

        var props = document.getElementById('tabProperties');
        if (props && props.classList.contains('hidden') && typeof window.showDrawerTab === 'function') {
            window.showDrawerTab('properties');
        }

        // One frame for the tab swap to lay out before measuring the scroll.
        requestAnimationFrame(function () {
            panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
            panel.classList.remove('eto-flash');
            void panel.offsetWidth;          // restart the animation
            panel.classList.add('eto-flash');
            setTimeout(function () { panel.classList.remove('eto-flash'); }, 1400);
        });
    }

    function note(id, text, show) {
        var el = $(id);
        el.value = text || '';
        el.readOnly = !canManage;
        // A note with nothing in it is only worth screen space to the person
        // who can write one.
        el.classList.toggle('hidden', !(show && (canManage || text)));
    }

    function stamp(id, on, who, when, verb) {
        var el = $(id);
        el.textContent = (on && who) ? (verb + ' ' + who + (when ? ' · ' + when : '')) : '';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ── Talking to the server ───────────────────────────────────────── */

    function load(id) {
        taskId = id;
        var panel = $('etoPanel');
        if (!panel) return;
        panel.classList.add('hidden');
        showButton(false);

        fetch('/' + ROLE + '/tasks/' + id + '/oversight', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token() },
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.success || taskId !== id) return;   // a newer task won the race
                canManage = !!d.can_manage;
                buildStars();
                paint(d.verdict);
            })
            .catch(function () { /* the drawer opens regardless */ });
    }

    function save(payload) {
        if (!taskId || !canManage || busy) return;
        busy = true;
        var body = $('etoPanel').querySelector('.eto-panel-body');
        body.classList.add('eto-saving');

        var id = taskId;
        fetch('/' + ROLE + '/tasks/' + id + '/oversight', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token(),
            },
            body: JSON.stringify(payload),
        })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (d) {
                if (!d || !d.success) {
                    if (window.Swal) {
                        window.Swal.fire({ icon: 'error', title: 'Could not save',
                            text: (d && d.message) || 'Please try again.', confirmButtonColor: '#4f46e5' });
                    }
                    return;
                }
                if (taskId === id) paint(d.verdict);
                repaintCard(id, d.verdict);
            })
            .finally(function () {
                busy = false;
                body.classList.remove('eto-saving');
            });
    }

    /* ── The card behind the drawer ──────────────────────────────────── */

    /* The board is rendered server-side, so without this the marks would only
       appear on the next full load. Rebuilt from the same shapes the blade
       emits so the two cannot drift apart visually. */
    function repaintCard(id, v) {
        var card = document.querySelector('.tk-card[data-id="' + id + '"]');
        if (!card) return;

        card.classList.toggle('eto-flagged', !!(v && v.red_flag));
        card.classList.toggle('eto-locked', !!(v && v.is_restricted));

        var row = card.querySelector('[data-role="eto-marks"]');
        var bits = [];

        if (v && v.red_flag) {
            bits.push('<span class="eto-mark flag" title="Red-flagged by the Super Admin"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 2a1 1 0 011-1h12l-3 4 3 4H5v9a1 1 0 11-2 0V2z"/></svg>FLAG</span>');
        }
        if (v && v.is_restricted) {
            bits.push('<span class="eto-mark lock" title="Restricted by the Super Admin"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>LOCKED</span>');
        }
        if (v && v.rating) {
            bits.push('<span class="eto-mark rate" title="Rated ' + v.rating + ' out of 5"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.29 3.98a1 1 0 00.95.69h4.18c.97 0 1.37 1.24.59 1.81l-3.39 2.46a1 1 0 00-.36 1.12l1.3 3.98c.3.92-.76 1.69-1.54 1.12l-3.39-2.46a1 1 0 00-1.18 0l-3.39 2.46c-.78.57-1.84-.2-1.54-1.12l1.3-3.98a1 1 0 00-.36-1.12L2.02 9.41c-.78-.57-.38-1.81.59-1.81H6.8a1 1 0 00.95-.69l1.3-3.98z"/></svg>' + v.rating + '/5</span>');
        }

        if (!bits.length) { if (row) row.remove(); return; }

        if (!row) {
            row = document.createElement('div');
            row.className = 'flex items-center gap-1 mb-1 flex-wrap';
            row.setAttribute('data-role', 'eto-marks');
            var head = card.querySelector('.tk-head');
            if (head && head.parentNode) head.parentNode.insertBefore(row, head);
            else return;
        }
        row.innerHTML = bits.join('');
    }

    /* ── Wiring ──────────────────────────────────────────────────────── */

    function wire() {
        if (!$('etoPanel')) return;

        $('etoLock').addEventListener('change', function () {
            save({ is_restricted: this.checked ? 1 : 0, restriction_note: $('etoLockNote').value || null });
        });
        $('etoFlag').addEventListener('change', function () {
            save({ red_flag: this.checked ? 1 : 0, red_flag_note: $('etoFlagNote').value || null });
        });
        $('etoClearRating').addEventListener('click', function () {
            save({ rating: null });
        });

        var jump = $('drawerEvaluationBtn');
        if (jump) jump.addEventListener('click', jumpToPanel);

        // Notes save when you leave the box, not on every keystroke.
        bindNote('etoLockNote', function (t) { return { is_restricted: $('etoLock').checked ? 1 : 0, restriction_note: t || null }; });
        bindNote('etoFlagNote', function (t) { return { red_flag: $('etoFlag').checked ? 1 : 0, red_flag_note: t || null }; });
        bindNote('etoRateNote', function (t) { return { rating_note: t || null }; });

        // Wrapping, not editing: with this script absent the drawer behaves
        // exactly as before, and a thrown error in here cannot stop it opening.
        var open = window.openTaskDrawer;
        if (typeof open === 'function') {
            window.openTaskDrawer = function (id) {
                var out = open.apply(this, arguments);
                try { load(id); } catch (e) { /* the drawer is already open */ }
                return out;
            };
        }

        var close = window.closeTaskDrawer;
        if (typeof close === 'function') {
            window.closeTaskDrawer = function () {
                taskId = null;
                var p = $('etoPanel');
                if (p) p.classList.add('hidden');
                showButton(false);
                return close.apply(this, arguments);
            };
        }
    }

    function bindNote(id, build) {
        var el = $(id);
        var last = el.value;
        el.addEventListener('blur', function () {
            if (!canManage || el.value === last) return;
            last = el.value;
            save(build(el.value));
        });
    }

    /* ── The switch-employee panel ───────────────────────────────────────
       Its own thing, not a shared dropdown: it groups by company and filters
       on a per-row data-search blob covering name, employee ID, email, phone,
       company, department, designation and role. Everything is already in the
       DOM, so typing costs one indexOf per row and no request. */

    function wireSwitcher() {
        var wrap  = $('etoSw');
        var btn   = $('etoSwBtn');
        var panel = $('etoSwPanel');
        var input = $('etoSwQ');
        var list  = $('etoSwList');
        if (!wrap || !btn || !panel || !input || !list) return;

        var rows   = Array.prototype.slice.call(list.querySelectorAll('[data-eto-row]'));
        var groups = Array.prototype.slice.call(list.querySelectorAll('[data-eto-group]'));
        var empty  = $('etoSwEmpty');
        var clear  = $('etoSwClear');
        var active = -1;   // keyboard cursor into the visible rows

        function visible() {
            return rows.filter(function (r) { return r.style.display !== 'none'; });
        }

        function setActive(i) {
            var vis = visible();
            rows.forEach(function (r) { r.classList.remove('is-active'); });
            if (!vis.length) { active = -1; return; }
            active = Math.max(0, Math.min(i, vis.length - 1));
            vis[active].classList.add('is-active');
            vis[active].scrollIntoView({ block: 'nearest' });
        }

        function filter() {
            var q = input.value.trim().toLowerCase();
            clear.style.display = q ? 'block' : 'none';

            // Every word must appear somewhere, so "epal it rubel" narrows
            // rather than widening the way a single substring would.
            var words = q ? q.split(/\s+/) : [];
            var shown = 0;

            rows.forEach(function (r) {
                var hay = r.dataset.search || '';
                var ok = true;
                for (var i = 0; i < words.length; i++) {
                    if (hay.indexOf(words[i]) === -1) { ok = false; break; }
                }
                r.style.display = ok ? 'flex' : 'none';
                if (ok) shown++;
            });

            // A company header with nothing under it is worse than no header.
            groups.forEach(function (g) {
                var live = g.querySelectorAll('[data-eto-row]');
                var count = 0;
                Array.prototype.forEach.call(live, function (r) { if (r.style.display !== 'none') count++; });
                g.style.display = count ? 'block' : 'none';
                var badge = g.querySelector('[data-eto-count]');
                if (badge) badge.textContent = String(count);
            });

            empty.style.display = shown ? 'none' : 'block';
            setActive(0);
        }

        function open() {
            panel.style.display = 'block';
            btn.setAttribute('aria-expanded', 'true');
            // The board's dropdowns close on scroll; this one is a menu the
            // user is reading, so it only closes on Esc, outside click, or a
            // choice.
            if (typeof window.closeAllDropdowns === 'function') window.closeAllDropdowns();
            input.value = '';
            filter();
            setTimeout(function () { input.focus({ preventScroll: true }); }, 30);
        }

        function close() {
            panel.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.style.display === 'block' ? close() : open();
        });

        panel.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () {
            if (panel.style.display === 'block') close();
        });

        input.addEventListener('input', filter);
        clear.addEventListener('click', function () { input.value = ''; filter(); input.focus(); });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape')    { e.preventDefault(); close(); btn.focus(); return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); return; }
            if (e.key === 'ArrowUp')   { e.preventDefault(); setActive(active - 1); return; }
            if (e.key === 'Enter') {
                e.preventDefault();
                var vis = visible();
                if (vis[active < 0 ? 0 : active]) vis[active < 0 ? 0 : active].click();
            }
        });

        list.addEventListener('mousemove', function (e) {
            var r = e.target.closest('[data-eto-row]');
            if (!r) return;
            var i = visible().indexOf(r);
            if (i > -1 && i !== active) setActive(i);
        });
    }

    // The board's own JS is a module and modules run before DOMContentLoaded,
    // so window.openTaskDrawer is already there by the time this fires.
    function boot() { wire(); wireSwitcher(); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endif

{{-- "My Tasks" state-dropdown-per-board logic lives in resources/js/pages/board-show.js
     (runs at runtime when window.__boardConfig.viewMode === 'my-tasks'). --}}


@endsection
