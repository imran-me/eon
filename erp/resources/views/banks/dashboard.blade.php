@extends('layout.app')

@section('meta-information')
    <title>Manage Bank Accounts</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 40px !important; border: 1.5px solid #e2e8f0 !important; border-radius: 8px !important;
        display: flex; align-items: center; padding: 0 11px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px !important; padding: 0 !important; font-size: 0.85rem; color: #1e293b; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px !important; }
    .select2-container--default .select2-selection--single:focus,
    .select2-container--default.select2-container--focus .select2-selection--single { border-color: #6366f1 !important; }
    .select2-dropdown { border-color: #e2e8f0 !important; border-radius: 8px !important; overflow: hidden; }
    .select2-search--dropdown .select2-search__field { border-radius: 6px !important; border: 1px solid #e2e8f0 !important; }
    #txnModal .beneficiary-dropdown { width: 300px !important; max-width: calc(100vw - 32px); border-radius: 5px !important; box-shadow: 0 8px 20px rgba(15,23,42,.12); }
    #txnModal .beneficiary-dropdown .select2-search { padding: 8px; border-bottom: 1px solid #e2e8f0; }
    #txnModal .beneficiary-dropdown .select2-search__field { width: 100% !important; margin: 0; padding: 6px 8px; font-size: 12px; }
    /* The cap goes on the OUTER list only (the direct child of .select2-results,
       the one that scrolls). Each optgroup's nested <ul> carries the same
       .select2-results__options class; capping it too left every group 220px
       tall with overflow visible, so the Vendors' 24 rows painted straight
       over the Agents' — the interleaved list the owner saw on 2026-09-15. */
    #txnModal .beneficiary-dropdown .select2-results > .select2-results__options { max-height: 220px !important; }
    #txnModal .beneficiary-dropdown .select2-results__options--nested { max-height: none !important; }
    #txnModal .beneficiary-dropdown .select2-results__group { padding: 7px 10px 3px; font-size: 11px; font-weight: 700; color: #334155; background: #f8fafc; }
    #txnModal .beneficiary-dropdown .select2-results__option--selectable { display: block; height: 32px !important; min-height: 32px; padding: 0 10px !important; font-size: 12px; line-height: normal !important; }
    #txnModal .beneficiary-dropdown .select2-results__option--highlighted { background: #2563eb !important; color: #fff !important; }
    .beneficiary-option-row { display: flex; align-items: center; gap: 8px; height: 32px; width: 100%; min-width: 0; }
    .beneficiary-option-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .beneficiary-option-id { flex: none; font-size: 10px; opacity: .72; white-space: nowrap; }
    #txnModal .select2-selection__rendered { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
@endsection

@section('main-content')
<style>
    .bank-dash-banner {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 55%, #7c3aed 100%);
        border-radius: 16px;
        /* Was 1.5rem 1.75rem. Trimmed with the type and tiles below to take the
           banner's height down roughly a quarter without crowding it. Trimmed
           again when the left side became its own panel, so that panel's
           padding does not add the height back. */
        padding: 0.55rem 0.9rem;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
    }
    /* Two soft blooms — one off the top-right, one under the left corner — so
       the panel reads as lit rather than as a flat gradient. Decoration only:
       no layout box, no pointer target, no height. */
    .bank-dash-banner::before {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background:
            radial-gradient(120% 150% at 88% -25%, rgba(255,255,255,0.20), transparent 58%),
            radial-gradient(90% 130% at -5% 125%, rgba(255,255,255,0.12), transparent 55%);
    }
    .bank-dash-banner > * { position: relative; z-index: 1; }
    .bank-dash-banner h2 { font-size: 1.1rem; font-weight: 700; margin: 0; letter-spacing: -0.01em; line-height: 1.25; }
    .bank-dash-banner p { font-size: 0.82rem; opacity: 0.85; margin: 2px 0 0; }

    /* Banner is two blocks on ONE line: the title on the left, the two figures
       on the right. It carried a third — four columns of account names — which
       came out on the owner's instruction; the rules for it (.bank-dash-names
       and its three breakpoints) went with it rather than being left behind as
       dead weight.

       The KPI pair is now the translucent panel. It used to be the title AND
       the figures inside one panel, with the figures on a second line beneath
       the heading; that stacking is what made the banner three lines tall. Side
       by side they fit on one, and the panel still marks them as the thing to
       read. */
    .bank-dash-head {
        display: flex; align-items: center; gap: 11px;
        min-width: 0;
    }
    .bank-dash-stats {
        display: flex; align-items: center; gap: 14px;
        flex-shrink: 0;
        background: rgba(255,255,255,0.10);
        border: 1px solid rgba(255,255,255,0.16);
        border-radius: 12px;
        padding: 7px 14px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.12);
    }
    .bank-dash-banner .icon-box {
        width: 38px; height: 38px; border-radius: 11px;
        background: rgba(255,255,255,0.16);
        border: 1px solid rgba(255,255,255,0.22);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
    }
    /* No box of their own — the panel is the box. Left-aligned under the title
       so both figures and the heading share one edge, split by a hairline. */
    .bank-dash-stat {
        background: none;
        border: 0;
        border-radius: 0;
        padding: 0;
        text-align: left;
        min-width: 0;
    }
    .bank-dash-stat + .bank-dash-stat {
        border-left: 1px solid rgba(255,255,255,0.22);
        padding-left: 12px;
    }
    /* tabular-nums keeps the balance from reflowing as digits change when you
       click between companies. */
    /* nowrap on both: selecting a company swaps the label for '<Company>
       Balance', and a two-line label would put the banner's height back. */
    .bank-dash-stat .val { font-size: 1.05rem; font-weight: 700; line-height: 1.25; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .bank-dash-stat .lbl { font-size: 0.58rem; opacity: 0.78; text-transform: uppercase; letter-spacing: 0.07em; line-height: 1.35; white-space: nowrap; }

    .company-pick-card:hover { box-shadow: 0 4px 14px rgba(79,70,229,0.15); border-color: #c7d2fe; }
    .company-pick-card.is-active { border-color: #6366f1; box-shadow: 0 4px 14px rgba(79,70,229,0.18); }


    /* Account card — rebuilt 2026-08-19 to the owner's sketch.
       The card answers four questions in the order they get asked, and each
       one owns a band of its own so the eye never has to hunt:

         1  WHO       logo, bank name, whose account it is
         2  WHICH     A/C number on a ruled line, branch, kind of account
         3  HOW MUCH  the balance, in the currency it is held in
         4  ACTIVITY  lifetime entries, and what moved today

       The build before this one interleaved those four, so a balance sat
       beside an account number at nearly the same weight and neither read
       first. Only the balance is loud now; everything else is deliberately
       quiet, which is what makes the balance readable at a glance across a
       grid of thirteen.

       SIZE IS A CONSTRAINT, NOT AN OUTCOME (owner, 2026-08-19): about 200px
       wide, five to a row on a 1366px window. Every type size below is set to
       that width — do not enlarge one without re-checking that ৳2,580,239.00
       and a 17-digit Islami Bank account number still fit on one line each.
       That budget is also why the card face carries no last-movement date and
       no full flow figures: they are in the tooltips, which cost no width.

       TWO COLOURS, TWO MEANINGS, and they must not be swapped:
         --bac-tint    the BANK's brand. Used for one thing only — the monogram
                       plate that stands in when we have no logo file.
         --bac-co      the OWNING COMPANY's accent, already muted controller-side
                       (see companyRail()). This is the card's identity colour:
                       the left rail, the corner glow, the hover shadow, the
                       share bar. Green for Wood Art, blue for Travels, violet
                       for IT — the same accents the sidebar rail paints.
       Both arrive inline per card, so this sheet never needs touching to add a
       bank or a company. --bac-co-rgb is the same colour as "r, g, b" so every
       tint below can be plain rgba() and need no color-mix() support.

       The rail, the hover lift, the sweep and their easing are transcribed from
       the reference build's .bank-card (platform/design-system/css/
       components.css) — same geometry, same 4px→6px rail, same .7s glaze. */
    /* TWO STATES, ONE CARD (owner, 2026-08-19).
       At rest every card is the brief: mark, concern, bank, balance — the four
       things worth scanning a wall of thirteen for. Hover expands it in place
       into the full card documented below, which is why none of that styling
       was deleted: it IS the hover state.

       THE MECHANICS — rewritten 2026-08-19 after the first attempt covered its
       neighbours ("doesn't block other small cards … makes its space while
       hovering"):

       The card is IN FLOW, not floated over the grid, and it opens by animating
       `grid-template-rows` from 0fr to 1fr on the block being revealed. Two
       things fall out of that, and both are the point:
         - the row it lives in grows with it, and every row below slides down.
           Nothing is ever covered, because nothing is ever overlapped.
         - 1fr is the content's OWN height, so there is no max-height guess to
           tune. A guessed max-height also ruins the easing: the curve is spent
           on pixels that do not exist, so the open finishes early and stops
           dead. This lands exactly on the content and eases all the way in.

       There is deliberately NO transform: scale() on the card. That was the
       first version's "zoom" and it is precisely what pushed the card over its
       neighbours — a transform does not take part in layout, so the space it
       needs is never reserved. The growth itself is the zoom now.

       Easing is cubic-bezier(.22, 1, .36, 1) — quick to leave, long to settle,
       which is what makes an expanding panel feel like it was already on its
       way rather than like it started when you asked.

       .bac-brief and .bac-full are both in the DOM. The mark sits at the same
       offset in both, so it does not move through the change — only what is
       around it does.

       The inner *-in wrappers are required by the 0fr/1fr technique: the
       overflow:hidden that hides the collapsing content has to live on a child
       of the grid, not on the grid itself. */
    .bank-acc-card {
        --bac-tint: #6366f1;
        --bac-co: #64748b;
        --bac-co-rgb: 100, 116, 139;
        --bac-ease: cubic-bezier(.22, 1, .36, 1);
        position: relative; overflow: hidden;
        /* A real hairline, not the near-white this was — the owner asked for a
           1px border in a shadow black. Low-alpha ink rather than a flat grey,
           so it darkens with whatever it sits on. */
        border: 1px solid rgba(15,23,42,0.20); border-radius: 14px;
        /* The company's colour breathed into the top-right corner. Part of the
           background rather than a pseudo-element, because both pseudos are
           spoken for below (rail, sweep). */
        background:
            radial-gradient(135% 95% at 100% 0%, rgba(var(--bac-co-rgb), 0.09), transparent 56%),
            #fff;
        /* No bottom padding: the activity strip IS the card's bottom edge.
           The extra left padding clears the rail. */
        padding: 5px 9px 0 15px;
        box-shadow: 0 1px 2px rgba(7,12,30,0.06), 0 3px 10px -5px rgba(7,12,30,0.10);
        cursor: pointer;
        transform-origin: center top;
        /* Opens slowly and settles; closes briskly. The close is quick on purpose:
           the second row is released the instant the pointer leaves, so a slow
           collapse would leave the card hanging over its neighbours. */
        transition: transform .3s var(--bac-ease), box-shadow .3s var(--bac-ease), border-color .24s ease;
    }
    /* HOW IT TAKES ITS SPACE, second attempt (owner: "making many white space
       … other cards will take that space").
       Rows are a FIXED 84px — one brief — and an opening card claims a second
       one with grid-row: span 2. It borrows the row below in its own column
       instead of making its own row taller, so the four cards beside it are
       not dragged up to a height they do not need and no gap opens under them.
       The cards after it shift on by one cell, which is the only movement in
       the grid.
       The expanded card measures 178px and two rows plus the 12px gutter come
       to 182px — that is not a coincidence, it is the budget the row height is
       set from. The brief is padded to exactly one row so no dead space shows
       under it. Change a type size or the mark and BOTH have to be re-measured:
       too short a row and the open card spills past its cell, too tall and
       every resting card floats in a gap.

       The scale is what makes it read as a magnification rather than an
       unrolling, and 3% is deliberately small: 6px of width against a 12px
       gutter, so it can never reach a neighbour the way the first version's
       3.5%-plus-lift did. */
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover {
        grid-row: span 2;
        z-index: 5;
        transform: scale(1.03);
        border-color: rgba(var(--bac-co-rgb), 0.5);
        box-shadow: 0 20px 42px -24px rgba(var(--bac-co-rgb), 0.5),
                    0 6px 16px -10px rgba(7,12,30,0.18);
    }
    /* THE BOTTOM ROW OPENS UPWARDS (owner, 2026-08-20).
       Every other card claims the row beneath it, and that row exists. The
       bottom row has none, so `grid-row: span 2` invents one: the grid gets 97px
       taller and everything under it — the ledger panel, the rest of the page —
       drops the instant the pointer touches a bottom card, then springs back
       when it leaves. Nothing below a card should move because of that card.

       So a bottom card takes the cell ABOVE its own instead, and it takes it
       properly — by being placed there, not by hanging over it. The first
       attempt let the card overflow upward out of its cell and the owner
       rejected it on sight: "its covering up the above card". A card that opens
       over its neighbour has not made room, it has hidden it.

       The placement is explicit and spans two rows, from the row above down
       through its own. Being definitely-placed takes it out of auto-placement,
       so every auto-placed card after it now flows around the two cells it
       holds: the card that was above it steps into the next cell, that one
       steps into the next, and the card at the end of a row wraps down to the
       one below — exactly the shuffle the owner described. Same movement the
       grid already makes when a middle card opens, so the two read alike.

       A completely FULL bottom row is deliberately NOT marked and opens downward
       like any other card. Moving up needs one spare cell in the grid — an open
       card holds two and gives back one — and a full bottom row has none, so the
       shuffle would only push the last card onto a new row. The grid gains a row
       either way; it gains it more quietly by opening below than by rearranging
       every card after the one under the pointer. Owner's call, 2026-08-20.

       align-self:end, against the grid's align-items:start, is what makes the
       open seamless rather than a leap. The moment the pointer lands, the card's
       area doubles upward while the card itself is still one row tall; pinned to
       the BOTTOM of that area it is exactly where it already was, and grows up
       into the space that was just reserved for it. Aligned to start it would
       instead jump a whole row up and then unroll downward.
       It is set at rest too, not only on hover: a resting card is one row tall
       to the pixel so it sits in the same place either way, align-self cannot be
       transitioned, and markLastRowCards() needs every card measuring from its
       row's top edge — which is why it strips this class before it reads.

       --bac-col / --bac-row are written by markLastRowCards(), because auto-fill
       decides the column count from the window width and nothing in the DOM
       records where a card landed. A grid only one row tall is left alone: there
       is no row above to move into, and growing up would put the card through
       the toolbar.

       transform-origin moves to the bottom edge for the same reason as the rest
       of this: with the inherited center-top origin the 1.03 scale would hang
       past the bottom of the reserved area, into the panel below. */
    .bank-acc-card.bac-up { align-self: end; transform-origin: center bottom; }
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card.bac-up:hover {
        grid-column: var(--bac-col);
        grid-row: var(--bac-row) / span 2;
    }
    .bank-acc-card.is-overdrawn { border-color: rgba(190,18,60,0.35); }

    /* ONE CARD AT A TIME, AND IT IS THE ONE YOU POINTED AT (owner, 2026-08-22:
       "why i can not hover on it?? it bliks continiously").

       Opening a card re-flows the grid — that is the whole design, the cards
       after it step along one cell to make room. But the pointer does not move
       with them, so the card that slides UNDER the pointer takes the hover next,
       and that card opens into ITS own cell, which slides the first one back,
       which hands the hover back... Two cards trade places under a still mouse
       for as long as it rests there, several times a second. Measured in
       headless Chrome on a 13-card, 5-column grid: 19 of the 180 ways of moving
       from one card to another ended in that loop, every one of them landing on
       the bottom row — which is the row the owner could not hover.

       The loop is hover deciding layout deciding hover. This cuts the second
       half: while a card is open, its neighbours cannot be hovered at all, so a
       re-flow can never reassign the hover. Moving off the open card leaves
       nothing hovered, the grid falls back to its resting layout, and only then
       does whatever is genuinely under the pointer open. The card that opens is
       always the one whose RESTING cell holds the pointer, which is the one the
       eye picked.

       It settles rather than oscillates, provably: an open card is taller and
       wider than its resting self and grows from an edge it keeps (downward from
       its top, or upward from its bottom for .bac-up), so its open box always
       CONTAINS its resting box. A card can therefore never open its way out from
       under the pointer, and there is no second state to fall back into.

       :has() is Chrome 105 / Firefox 121 / Safari 15.4. Anywhere older the rule
       is dropped whole and the grid behaves exactly as it did before — the bug
       is not fixed there, but nothing new breaks either. */
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded):has(.bank-acc-card:hover) .bank-acc-card:not(:hover) {
        pointer-events: none;
    }

    /* THREE VIEW MODES, one button (cardHoverBtn / cycleCardHoverMode(),
       owner ask, 2026-09-10 — "sometime i need and sometime i don't", then
       "every card will be large ... like hover effect size"):
         hover — the original behaviour. Brief by default, a card expands
                 under the pointer, its neighbours cannot steal the hover
                 mid-reflow (the :has() rule below). Default.
         off   — the expand is switched off outright. Every card stays the
                 small brief tile, hovering does nothing. For when the
                 pointer is only passing through on its way to the ledger
                 below and the growing card is noise, not help.
         all   — every card shows its full detail AT ONCE, permanently, with
                 no hover needed. This is a different mechanism from the
                 other two, not just both of them combined — see the
                 .all-expanded block below.
       Every rule above that keys off `:hover` is scoped under
       `#bankCardsGrid:not(.no-hover-fx):not(.all-expanded)`, so cardHoverBtn
       turns it off — for EITHER of the other two modes — by adding one class
       to the grid, without any of those rules needing its own disabled
       state. The click that opens the ledger (bankCardHtml's onclick) is
       untouched throughout, because nothing here is pointer-events. */

    /* Mode "all". Reusing the hover mechanism's grid-row: span 2 trick here
       would mean EVERY card borrowing a row at once, which only makes sense
       when one card opens at a time under a still pointer (the whole reason
       .bac-up / markLastRowCards() exist — see the giant comment above
       .bank-acc-card:hover). With every card the same expanded height
       already, there is nothing to borrow: grid-auto-rows: auto simply sizes
       each row to its content, so .bac-up and the JS that feeds it are
       correctly never consulted in this mode. */
    #bankCardsGrid.all-expanded { grid-auto-rows: auto; }
    #bankCardsGrid.all-expanded .bank-acc-card .bac-brief { grid-template-rows: 0fr; opacity: 0; }
    #bankCardsGrid.all-expanded .bank-acc-card .bac-full { grid-template-rows: 1fr; opacity: 1; }
    #bankCardsGrid.all-expanded .bank-acc-card .bac-full-in { transform: scale(1); }

    /* Card order toggle. Sized to the "All Companies" button beside it so the
       two read as one control strip, and tinted indigo while the non-default
       order is active — without that, nothing on screen says the cards are in a
       different order than usual. */
    .card-sort-btn {
        flex: 0 0 auto; width: 30px; height: 30px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid #e2e8f0; background: #fff; color: #64748b;
        border-radius: 8px; cursor: pointer; font-size: 0.82rem;
        transition: background .15s, color .15s, border-color .15s;
    }
    .card-sort-btn:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
    .card-sort-btn.is-alt { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
    .card-sort-btn.is-alt:hover { background: #e0e7ff; }

    /* Both states animate on grid-template-rows. The one being hidden closes on
       the same curve as the one being shown opens, so the card never jumps in
       height at the handover. The brief fades out ahead of the full fading in
       (no delay out, 0.1s delay in) — a crossfade with both at half opacity
       reads as a smear, a handover reads as a change of mind. */
    .bac-brief, .bac-full {
        display: grid; grid-template-rows: 1fr;
        transition: grid-template-rows .26s var(--bac-ease), opacity .18s ease;
    }
    /* Overflow and nothing else. These are the grid ITEMS, and a grid item at
       0fr has a zero-height content box but still lays out its own padding and
       border — so any vertical padding here leaks into the OTHER state's height.
       All vertical spacing therefore lives one level in, on .bac-brief-row and
       .bac-head, where the overflow clips it away with the rest. */
    .bac-brief-in, .bac-full-in { overflow: hidden; min-height: 0; }

    .bac-brief { opacity: 1; }
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover .bac-brief { grid-template-rows: 0fr; opacity: 0; transition-duration: .4s, .12s; }
    /* padding-top is head-room for the company badge, which overhangs the mark
       by 5px; padding-bottom is the shim that makes the resting card exactly one
       row tall. Re-measure both if the mark or the type moves.

       The 2026-08-19 balance change is deliberately NOT in here: it applies to
       the hover state only (.bac-bal .amt), so the resting row's 12+1+17+2+20 =
       52px type column and this 13px shim are unchanged. */
    .bac-brief-row { display: flex; align-items: flex-start; gap: 10px; padding: 7px 0 13px; }
    .bb-id   { flex: 1; min-width: 0; }
    .bb-co   { font-size: 9.5px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase;
               color: #6b7686; line-height: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bb-name { font-size: 13.8px; font-weight: 700; letter-spacing: -0.015em; color: #1a2333; line-height: 17px;
               margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bb-bal  { font-size: 16px; font-weight: 700; letter-spacing: -0.025em; color: #111827; line-height: 20px;
               margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap;
               overflow: hidden; text-overflow: clip; }
    .bb-bal .cur { font-size: 8px; font-weight: 700; letter-spacing: 0.07em; color: #aab3c0; margin-left: 4px; }
    .bank-acc-card.is-overdrawn .bb-bal { color: #dc2626; }

    /* The full card — everything below, revealed on hover.
       The negative margins on the inner pull it out to the card's own edges and
       the matching padding puts its children back where they belong. That is
       what lets .bac-strip keep its full-bleed margins: the overflow:hidden the
       height animation needs now clips at the card's edge, not inside it. */
    .bac-full { grid-template-rows: 0fr; opacity: 0; }
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover .bac-full { grid-template-rows: 1fr; opacity: 1; transition-duration: .4s, .26s; transition-delay: 0s, .1s; }
    /* The detail does not just appear, it comes UP to size — the small scale is
       what separates a zoom from a blind being drawn. */
    .bac-full-in { transform: scale(.965); transform-origin: center top; transition: transform .4s var(--bac-ease); }
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover .bac-full-in { transform: scale(1); }
    .bac-full-in { margin: 0 -9px 0 -15px; padding: 0 9px 0 15px; }

    /* The company rail down the left edge — what makes "all of Wood Art" a
       thing the eye finds without reading a word. Thickens on hover. */
    .bank-acc-card::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; z-index: 2;
        background: linear-gradient(180deg, var(--bac-co) 62%, rgba(var(--bac-co-rgb), 0.55));
        transition: width .28s cubic-bezier(.2,.7,.2,1);
    }
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover::before { width: 7px; }

    /* The glaze: a soft light that sweeps across the face once on hover. Skewed
       and slow (.7s) so it reads as light moving over a surface, not as an
       animation. pointer-events:none — it passes over the content, and must
       never eat the click that opens the ledger. */
    .bank-acc-card::after {
        content: ''; position: absolute; top: 0; left: -75%; width: 62%; height: 100%;
        z-index: 3; pointer-events: none; transform: skewX(-18deg);
        background: linear-gradient(105deg, transparent 12%, rgba(255,255,255,0.30) 42%,
                    rgba(255,255,255,0.58) 50%, rgba(255,255,255,0.30) 58%, transparent 88%);
        transition: left .7s ease;
    }
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover::after { left: 145%; }

    /* The four bands sit above the card's background and below the glaze. */
    .bac-head, .bac-acct, .bac-bal, .bac-strip { position: relative; z-index: 1; }

    /* Nobody asked for a card that jumps. */
    @media (prefers-reduced-motion: reduce) {
        .bank-acc-card, .bank-acc-card::before, .bank-acc-card::after,
        .bank-acc-card .bac-logo, .bac-brief, .bac-full { transition: none; }
        /* Matched to #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover's own
           specificity (the no-hover-fx gate added an ID selector to that rule),
           not just .bank-acc-card:hover — otherwise the gated rule's transform
           would outrank this reduced-motion override regardless of which comes
           later in the sheet, and turning hover-fx back on would un-silence the
           motion this media query exists to suppress. */
        #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover { transform: none; }
        #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover::after { left: -75%; }
    }

    /* The bank's mark, sitting DIRECTLY on the card — no plate.
       It used to carry a white fill, a hairline border and a shadow, which at
       this size read as a button you could press (owner, 2026-08-19: "it seems
       in a button"). The marks are already legible on white, so the chrome was
       only ever costing them room. 52x45 with object-fit:contain, which is what
       lets a square mark (bKash) and a wide wordmark (Dhaka Bank) sit in the
       same grid without either being distorted.

       The MONOGRAM fallback keeps a plate, and must: initials need a filled
       shape to sit on. It is also what renders if a logo file 404s, so a
       missing mark can never show a broken image. */
    .bac-logo {
        position: relative; width: 58px; height: 58px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        transition: transform .28s cubic-bezier(.2,.7,.2,1);
    }
    /* The mark rises a little further than the card does, which is what reads
       as depth rather than as the whole card sliding. */
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover .bac-logo { transform: translateY(-1px) scale(1.05); }
    .bac-logo img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
    .bac-logo .bac-mono { display: none; font-size: 17px; font-weight: 800; letter-spacing: 0.01em; color: #fff; }
    .bac-logo.is-mono { background: var(--bac-tint); border-radius: 12px; }
    .bac-logo.is-mono img { display: none; }
    .bac-logo.is-mono .bac-mono { display: block; }

    /* Which concern owns this account, as the glyph its tile wears in the
       sidebar's company switcher — the plane for Travels, the tree for Wood
       Art, the hexagon for Group. Same company_icon() call, so the badge and
       the switcher can never disagree about a company's mark or its colour.

       Pinned to the MARK's top-right corner, not the card's, and that is a
       width decision rather than a taste one. "DUTCH BANGLA" measures 108.7px
       at the size the owner asked for (13.8px/800); a badge in the card's
       corner leaves the name 90px and clips it to "DUTCH BANG…". Riding the
       mark, the badge costs the name nothing and it still reads as the
       top-right of the card's identity block. It is also exactly where the
       reference build puts it (.bank-card-coico in components.css).

       White fill and a tinted ring rather than a solid disc: it sits ON the
       bank's logo, and a solid one would look like part of the mark. */
    .bac-coico {
        position: absolute; top: -5px; right: -5px; z-index: 2;
        width: 18px; height: 18px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: #fff; border: 1px solid rgba(var(--bac-co-rgb), 0.32);
        color: var(--bac-co); font-size: 9.5px; line-height: 1;
        box-shadow: 0 1px 3px rgba(7,12,30,0.13);
        transition: border-color .2s, box-shadow .2s;
    }
    #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) .bank-acc-card:hover .bac-coico {
        border-color: rgba(var(--bac-co-rgb), 0.55);
        box-shadow: 0 2px 6px rgba(var(--bac-co-rgb), 0.35);
    }

    /* Band 1 — who, and nothing else. The whole row past the plate belongs to
       the two names, which at the sizes the owner asked for (2026-08-19: bank
       name +20% and bold, company name +20% and bold) need every pixel of it.
       The trend pill used to sit here and cost them 41px — which was the
       difference between "DUTCH BANG…" and "DUTCH BANGLA". It moved down to
       the balance line, where there was room going spare. */
    .bac-head { display: flex; align-items: center; gap: 7px; padding-top: 7px; }
    .bac-id   { flex: 1; min-width: 0; }   /* without min-width the ellipsis never fires in a flex row */
    .bank-acc-card .name { font-size: 13.5px; font-weight: 700; color: #1a2333; line-height: 17px;
                           letter-spacing: -0.02em;
                           white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bank-acc-card .sub  { font-size: 11px; font-weight: 600; color: #5c6775; line-height: 13px;
                           letter-spacing: 0.01em; margin-top: 1px;
                           white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bac-dot { display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: #f59e0b; margin-right: 4px; vertical-align: 1px; }

    /* Band 2 — which account, at which branch. The dotted leader is the
       sketch's row of dots: it ties the number to the branch at the far right
       the way a printed statement does, and costs no height, being one border.
       The account type (savings/current/fixed) was tried on this line too and
       taken off again — three items plus a leader could not be fitted in 178px
       without eating into the account number, which is the whole point of the
       line. It is in the tooltip. */
    .bac-acct { display: flex; align-items: center; gap: 5px; margin-top: 10px; }
    .bac-acct .k    { font-size: 8px; font-weight: 700; letter-spacing: 0.09em; color: #b9c2cf; flex-shrink: 0; }
    .bac-acct .v    { flex: 0 1 auto; font-size: 10.5px; font-weight: 600; color: #566173; letter-spacing: 0.02em;
                      font-variant-numeric: tabular-nums; min-width: 0;
                      white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bac-acct .lead { flex: 1 1 8px; min-width: 6px; height: 0; border-bottom: 1px dotted #dbe2ec; }
    /* The branch carries a shrink factor far above the number's 1, so on a card
       too narrow for both it is the branch that gives way — a half-shown
       account number is worse than a half-shown branch, and the tooltips carry
       both in full. */
    .bac-acct .br   { flex: 0 200 auto; font-size: 8.5px; font-weight: 600; color: #6b7686; letter-spacing: 0.02em;
                      min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Band 3 — how much. The only loud thing on the card, and the one line
       that may never be clipped: it takes its width first (flex 0 0 auto) and
       the age beside it gives way instead. */
    .bac-bal { display: flex; align-items: baseline; justify-content: space-between; gap: 6px; margin: 9px 0 11px; }
    .bac-bal .money { flex: 0 0 auto; white-space: nowrap; }
    /* margin-left:auto packs the age and the trend pill together against the
       right edge while the balance stays hard left. */
    .bac-when { flex: 0 100 auto; min-width: 0; margin-left: auto; font-size: 9px; font-weight: 500;
                color: #9aa4b2; line-height: 13px; white-space: nowrap; overflow: hidden; text-align: right; }
    .bac-bal .amt { font-size: 22.95px; font-weight: 700; line-height: 28.35px; color: #111827;
                    letter-spacing: -0.03em; font-variant-numeric: tabular-nums; }
    .bac-bal .cur { font-size: 8px; font-weight: 700; letter-spacing: 0.07em; color: #b3bcc8; margin-left: 4px; }
    .bank-acc-card.is-overdrawn .bac-bal .amt { color: #dc2626; }
    .bac-od  { font-size: 7.5px; font-weight: 800; letter-spacing: 0.03em; background: #fee2e2; color: #b91c1c;
               border-radius: 20px; padding: 1px 4px; margin-left: 4px; vertical-align: 2px; }

    /* 14-day net movement, at the end of the balance line. Distinct from the strip at the foot,
       which is today only; the tooltip on each says which window it means and
       carries the exact figures. No ৳ inside it — every account on this page
       is in taka, and the symbol cost the bank name four characters. */
    .bac-flow { flex-shrink: 0; font-size: 8.5px; font-weight: 700; line-height: 14px;
                padding: 1px 5px; border-radius: 999px; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .bac-flow.up   { background: #ecfdf5; color: #15803d; }
    .bac-flow.down { background: #fef2f2; color: #b91c1c; }
    .bac-flow.idle { background: #f6f8fb; color: #b9c2cf; }

    /* Band 4 — activity. Full-bleed (negative margins reach past the card's
       padding) so it reads as the card's base rather than as a box floating
       inside one. Three cells, hairline-divided, exactly as sketched. */
    .bac-strip {
        display: grid; grid-template-columns: repeat(3, 1fr);
        margin: 0 -9px 0 -15px; border-top: 1px solid #eef1f6;
        background: linear-gradient(180deg, #fbfcfe, #f6f8fc);
        border-radius: 0 0 13px 13px;
    }
    .bac-strip .cell { padding: 6px 5px 8px; text-align: center; border-left: 1px solid #eef1f6; min-width: 0; }
    .bac-strip .cell:first-child { border-left: 0; text-align: left;  padding-left: 15px; }
    .bac-strip .cell:last-child  { text-align: right; padding-right: 9px; }
    .bac-strip .cv { font-size: 11px; font-weight: 700; line-height: 14px; color: #475569;
                     font-variant-numeric: tabular-nums;
                     white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* Charcoal, not the near-white these were. At 7.5px a #aab3c0 label is
       decoration; the owner has to be able to read which cell is which
       (2026-08-19: "a bit charcoal black … not pitch dark black"), so they sit
       at #4b5563 — dark enough to read, still clearly subordinate to the
       figure above them. */
    .bac-strip .cl { font-size: 7.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
                     color: #6b7686; line-height: 11px; margin-top: 1px; }
    .bac-strip .in  .cv { color: #15803d; }
    .bac-strip .out .cv { color: #b91c1c; }
    /* A zero today is a fact worth showing, but it must not draw the eye the
       way a real figure does. */
    .bac-strip .cell.is-zero .cv { color: #ccd3dd; }

    /* There is no .bac-share rule any more, and that is on purpose.
       Share-of-this-view was drawn as a 3px meter along the card's top edge.
       It read as free information on paper and as a defect on screen: on the
       two largest accounts the fill was wide and dark enough to look like a
       stray rule rather than a bar, and the card's 14px corner sliced its left
       end into a wedge. The figure survives in the card's hover title, which
       costs no pixels and cannot be mistaken for a rendering fault. */

    .bank-btn {
        display: flex; align-items: center; justify-content: center; gap: 4px;
        padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600;
        border: none; cursor: pointer; text-decoration: none;
    }
    .bank-btn-statement { background: #eef2ff; color: #4338ca; }
    .bank-btn-statement:hover { background: #c7d2fe; }
    .bank-btn-deposit { background: #dcfce7; color: #15803d; }
    .bank-btn-deposit:hover { background: #bbf7d0; }
    .bank-btn-withdraw { background: #fee2e2; color: #b91c1c; }
    .bank-btn-withdraw:hover { background: #fecaca; }
    .bank-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    .txn-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.83rem; }
    /* The headings carry the header card's colour instead of the page's grey
       (owner, 2026-08-20: "I want it to make a bit colored"). Indigo at its
       lightest is enough to tie the table to the band above without competing
       with the figures in it, and it READS better, not worse: #4338ca on #eef2ff
       is 8.6:1 against the 4.6:1 of the #64748b-on-#f8fafc it replaces.
       No side or top border any more — the table sits inside .ca-panel, whose
       own border draws the edges, and the filter bar above supplies the divider.
       The deeper cells are the same 13px the statement's headings use. */
    .txn-table thead th {
        background: #eef2ff; color: #4338ca; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.04em; font-size: 0.7rem; padding: 13px 12px;
        white-space: nowrap;
        border-bottom: 1px solid #c7d2fe;
    }

    /* Sortable columns. The arrow is hidden until hover on an inactive column
       and stays lit on the active one, so the header shows which column the
       table is ordered by without six arrows competing for attention. */
    /* Scoped to the two bank tables rather than a bare th[data-sort], so the
       rule can only ever reach markup on this page. */
    .txn-table thead th[data-sort],
    #ledgerArea thead th[data-sort] { cursor: pointer; user-select: none; transition: background .15s, color .15s; }
    /* Two inks, because the two headers now sit on different grounds: the
       landing table's is indigo-tinted, the statement's is still grey. */
    .txn-table thead th[data-sort]:hover { background: #e0e7ff; color: #3730a3; }
    #ledgerArea thead th[data-sort]:hover { background: #f1f5f9; color: #475569; }
    .txn-table thead th .th-sort,
    #ledgerArea thead th .th-sort { margin-left: 5px; font-size: 0.62rem; opacity: 0; transition: opacity .15s; }
    .txn-table thead th[data-sort]:hover .th-sort,
    #ledgerArea thead th[data-sort]:hover .th-sort { opacity: 0.45; }
    .txn-table thead th.is-active-sort,
    #ledgerArea thead th.is-active-sort { color: #4f46e5; }
    .txn-table thead th.is-active-sort .th-sort,
    #ledgerArea thead th.is-active-sort .th-sort { opacity: 1; color: #4f46e5; }

    /* ── The statement's filter bar ─────────────────────────────────────────
       Sits directly on top of the column headings and shares their background,
       so the two read as one taller heading block rather than as a toolbar that
       happens to be nearby. Everything is scoped under #ledgerArea, so none of
       it can reach the recent-activity table on the landing view.

       The column headings themselves also breathe more (see the padding rule at
       the foot of this block) — the owner asked for about half again the height,
       and that is the bar plus the deeper cells together. */
    .lf-bar {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 11px 14px; background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .lf-ledger-toolbar { align-items: center; gap: 10px; }
    .lf-toolbar-top, .lf-toolbar-bottom { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; width: 100%; }
    .lf-toolbar-bottom { display: contents; }
    .lf-ledger-toolbar #lfSearchWrap { flex: 1 1 320px; min-width: 260px; max-width: 440px; }
    .lf-view-controls { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .lf-view-select { min-height: 36px; padding: 7px 30px 7px 12px; border: 1px solid #dbe2ec; border-radius: 9px; background-color: white; color: #334155; font-size: 12px; font-weight: 600; }
    .lf-view-help { color: #94a3b8; background: transparent; border: 0; padding: 5px; cursor: help; }
    .lf-history-btn { display: inline-flex; align-items: center; gap: 7px; min-height: 36px; padding: 7px 12px; border: 1px solid #ddd6fe; border-radius: 9px; background: #f5f3ff; color: #6d28d9; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .lf-history-btn:hover { background: #ede9fe; }
    .lf-history-btn span { font-size: 11px; }
    .lf-toolbar-bottom .lf-dates { flex-wrap: wrap; }
    @media (max-width: 640px) {
        .lf-view-controls { width: 100%; }
        .lf-view-select { flex: 1; min-width: 0; }
        .lf-toolbar-bottom { gap: 10px; }
    }
    .lf-search { position: relative; flex: 1 1 260px; min-width: 200px; }
    .lf-search > i {
        position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 12px; pointer-events: none;
    }
    .lf-search input {
        width: 100%; height: 34px; padding: 0 30px 0 30px;
        border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;
        font-size: 12.5px; color: #1e293b; outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .lf-search input::placeholder { color: #a3adbb; }
    .lf-search input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    /* Clear button. Hidden until there is something to clear, so it never sits
       there inviting a click that would do nothing. */
    .lf-clear {
        position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
        border: 0; background: none; padding: 4px; cursor: pointer;
        color: #cbd5e1; font-size: 12px; line-height: 1; display: none;
    }
    .lf-clear:hover { color: #64748b; }
    .lf-search.has-q .lf-clear { display: block; }

    /* Segmented range buttons. One border around the group and hairlines
       between, so five buttons read as one control. */
    /* flex:0 0 auto — the group must never shrink. With overflow:hidden on it,
       shrinking does not compress the buttons, it slices the first one off, and
       "Today" quietly disappeared. The search field gives way instead, and the
       bar wraps to a second line before anything is lost. */
    .lf-seg { display: flex; flex: 0 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; }
    .lf-seg button {
        border: 0; border-left: 1px solid #e2e8f0; background: none;
        padding: 0 11px; height: 34px; cursor: pointer;
        font-size: 11.5px; font-weight: 600; color: #64748b; white-space: nowrap;
        transition: background .15s, color .15s;
    }
    .lf-seg button:first-child { border-left: 0; }
    .lf-seg button:hover { background: #f1f5f9; color: #334155; }
    .lf-seg button.is-on { background: #eef2ff; color: #4338ca; font-weight: 700; }

    .lf-dates { display: flex; flex: 0 0 auto; align-items: center; gap: 6px; }
    .lf-dates.is-open input[type="date"] { border-color: #c7d2fe; background: #f5f6ff; }
    .lf-dates input[type="date"] {
        height: 34px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0 8px;
        font-size: 12px; color: #334155; background: #fff; outline: none;
    }
    .lf-dates input[type="date"]:focus { border-color: #6366f1; }
    .lf-dates span { font-size: 11px; color: #94a3b8; }

    /* A dd/mm/yyyy box and the real calendar, side by side in one border.
       <input type="date"> prints its value in the BROWSER's locale — mm/dd/yyyy
       on an en-US Chrome — and nothing on the page can change that: not lang=,
       not a format attribute. So the half you read and type into is a text
       field, and the date input sits beside it with its printed value hidden and
       ONLY its calendar button showing.

       That button is Chrome's own picker indicator, not a drawing of one: it is
       visible, it is what you click, and it opens the native calendar with no
       showPicker() call and no picker library. The first cut hid the whole input
       at opacity 0 behind a FontAwesome glyph — an icon with nothing live behind
       it, which is how the picker went missing. The control you can see has to
       BE the control. */
    .lf-date {
        position: relative; display: inline-flex; align-items: center;
        height: 34px; padding-right: 5px; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 8px;
        transition: border-color .15s, background .15s;
    }
    .lf-date:focus-within { border-color: #6366f1; }
    .lf-dates input.lf-date-text {
        width: 88px; height: 32px; padding: 0 2px 0 9px;
        border: 0; background: none; outline: none;
        font-size: 12px; color: #334155;
    }
    /* A date that will not parse holds still and says so, rather than being
       silently swallowed and leaving the statement on its old period. */
    .lf-date.is-bad { border-color: #fca5a5; background: #fef2f2; }
    .lf-dates input.lf-date-text.is-bad { color: #b91c1c; }
    .lf-dates input.lf-date-native {
        width: 22px; height: 22px; flex: 0 0 auto;
        margin: 0; padding: 0; border: 0; background: none;
        color: transparent; overflow: hidden; cursor: pointer;
    }
    /* Everything except the calendar button is taken out of the input, so what
       is left IS the button: 22px of live picker indicator. */
    .lf-date-native::-webkit-datetime-edit,
    .lf-date-native::-webkit-inner-spin-button,
    .lf-date-native::-webkit-clear-button { display: none; }
    .lf-date-native::-webkit-calendar-picker-indicator {
        width: 22px; height: 22px; margin: 0; padding: 0;
        opacity: 0.5; cursor: pointer; transition: opacity .15s;
    }
    .lf-date:hover .lf-date-native::-webkit-calendar-picker-indicator,
    .lf-date-native:focus::-webkit-calendar-picker-indicator { opacity: 0.9; }
    .lf-dates.is-open .lf-date { border-color: #c7d2fe; background: #f5f6ff; }
    .lf-go {
        height: 34px; padding: 0 12px; border: 0; border-radius: 8px; cursor: pointer;
        background: #4f46e5; color: #fff; font-size: 11.5px; font-weight: 700;
    }
    .lf-go:hover { background: #4338ca; }
    .lf-date-reset {
        display: none; height: 34px; padding: 0 10px; border: 1px solid #fecaca; border-radius: 8px;
        align-items: center; gap: 5px; background: #fff; color: #dc2626; cursor: pointer;
        font-size: 11px; font-weight: 700; white-space: nowrap;
    }
    .lf-dates.is-open .lf-date-reset { display: inline-flex; }
    .lf-date-reset:hover { background: #fef2f2; }
    .lf-print {
        height: 34px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 8px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        background: #fff; color: #475569; font-size: 11.5px; font-weight: 700;
        transition: background .15s, color .15s, border-color .15s;
    }
    .lf-print:hover { background: #f8fafc; color: #334155; border-color: #cbd5e1; text-decoration: none; }
    .lf-print i { font-size: 11px; }

    /* Says what the search did. Only rendered while a query is live, because a
       count of everything is not information. */
    .lf-count {
        flex-shrink: 0; font-size: 11px; font-weight: 700; color: #4338ca;
        background: #eef2ff; border-radius: 20px; padding: 4px 10px; white-space: nowrap;
    }
    .lf-count.is-none { color: #b91c1c; background: #fef2f2; }

    /* The statement's pager. Only drawn when the period holds more rows than
       one page — see paintLedgerPager(). Same slate/indigo language as the
       range buttons above the table, so the two read as one control surface. */
    .lf-pager {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; flex-wrap: wrap; padding: 12px 16px; border-top: 1px solid #f1f5f9;
    }
    .lf-pager-info { font-size: 11.5px; font-weight: 600; color: #64748b; }
    .lf-pager-nav { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
    .lf-pager-nav button {
        min-width: 32px; height: 32px; padding: 0 9px;
        border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;
        font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer;
        transition: background .15s, color .15s, border-color .15s;
    }
    .lf-pager-nav button:hover:not(:disabled) { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
    .lf-pager-nav button.is-on { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; font-weight: 800; }
    .lf-pager-nav button:disabled { opacity: .4; cursor: default; }
    .lf-pager-gap { padding: 0 2px; color: #94a3b8; font-size: 12px; }

    /* Half again the height the owner asked for is this plus the bar above. */
    #ledgerArea thead th { padding-top: 14px; padding-bottom: 14px; }

    /* Nine columns. Left to auto layout the browser grows the table past the
       card and scrolls it sideways over the money columns. Fixed layout
       instead: every column gets a share of the width, prose wraps, figures
       never do, and the whole thing fits a 1365px laptop with the sidebar
       open. The Source column's share went to Description and Beneficiary
       when it was hidden (2026-09-15). */
    #ledgerArea table { table-layout: fixed; width: 100%; }
    /* The # column is as narrow as three digits allow (owner, 2026-09-15 —
       it was taking twice the room the number needs); its share went to
       Description. It widens again only while rows are being picked, when
       the checkbox appears beside the number and needs the space back. */
    #ledgerArea thead th:nth-child(1)  { width: 3%; padding-right: 2px; }   /* #  */
    #ledgerArea tbody td:nth-child(1)  { padding-right: 2px; }
    #ledgerArea.is-picking thead th:nth-child(1) { width: 5%; }   /* tick + # */
    #ledgerArea.is-picking thead th:nth-child(4) { width: 19%; }
    #ledgerArea thead th:nth-child(2)  { width: 9%; }     /* date          */
    #ledgerArea thead th:nth-child(3)  { width: 9%; }     /* reference     */
    #ledgerArea thead th:nth-child(4)  { width: 21%; }    /* description   */
    #ledgerArea thead th:nth-child(5)  { width: 16%; }    /* beneficiary   */
    #ledgerArea thead th:nth-child(6)  { width: 10.5%; }  /* debit         */
    #ledgerArea thead th:nth-child(7)  { width: 10.5%; }  /* credit        */
    #ledgerArea thead th:nth-child(8)  { width: 13.5%; }  /* balance       */
    #ledgerArea thead th:nth-child(9)  { width: 7.5%; }   /* action        */
    #ledgerArea thead th, #ledgerArea tbody td { padding-left: 8px; padding-right: 8px; overflow-wrap: anywhere; }
    /* Cells start at the top, so a row's first lines — the date, the
       reference, the description, the beneficiary's name — sit on one line
       whatever each cell stacks beneath. Middle alignment put a two-line
       date beside a four-line beneficiary and nothing lined up with anything. */
    #ledgerArea tbody td { vertical-align: top; }
    /* The reference chip breaks at its dashes — it is a code, and a second
       line of it costs less than a scrollbar over the money columns. */
    #ledgerArea tbody td:nth-child(3) span { white-space: normal; }
    /* Money never breaks between the figure and its Dr/Cr. */
    #ledgerArea tbody td:nth-child(6),
    #ledgerArea tbody td:nth-child(7),
    #ledgerArea tbody td:nth-child(8) { white-space: nowrap; }
    /* Row picking for print. A tick box per row and one in the heading; the
       Print button reads the ticks. The strip under the bar appears only
       while something is ticked, and offers the two shortcuts a person
       actually wants: widen to the whole day, or start over. */
    .txn-pick { width: 34px; text-align: center !important; padding-left: 10px !important; padding-right: 4px !important; }
    /* Hidden until Print → "Selected transactions" switches the table into
       picking mode — the boxes are a step in printing, not part of the
       table's everyday face. is-picking goes on the feed's <table> and on
       #ledgerArea. */
    .txn-table .txn-pick { display: none; }
    .txn-table.is-picking .txn-pick { display: table-cell; }
    #ledgerArea input.lf-pick-all { display: none; }
    #ledgerArea.is-picking input.lf-pick-all { display: inline-block; }
    .is-picking tbody tr { cursor: pointer; }
    .lf-sel-cancel { background: #fff !important; color: #64748b !important; border-color: #e2e8f0 !important; }

    /* The transaction number, under the description on every table. Quiet
       on purpose — it is there to be quoted and searched, not read first. */
    .txn-no { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.68rem; color: #a3adbb; letter-spacing: 0.02em; margin-top: 2px; }
    /* A beneficiary that was booked against a real party — the code under the
       name (EP-AG-001) opens that party's ledger. The code alone, not the name:
       the ID is the key, the name is just what you read. See .bn-party-link. */
    /* The name keeps its size and takes one line: what does not fit is cut to
       "…" and the whole of it is on the tooltip (owner, 2026-09-15).
       width:0 + min-width:100% makes the row contribute nothing to its
       column's minimum width, so in the Recent Transactions table (auto
       layout) an unbreakable name cannot widen its column the way a plain
       block would — measured: a 464px name in a 1000px table took the column
       to 668px without this. That column then needs a share of its own or it
       collapses to the pill (104px measured); the 18% below is that share. */
    .bn-name-row  { width: 0; min-width: 100%; }
    .txn-table thead th[data-sort="beneficiary"] { width: 18%; }
    .bn-name-line { display: flex; align-items: center; min-width: 0; font-weight: 600; color: #1e293b; }
    .bn-name      { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bn-name-line .bn-pill { flex: none; margin-left: 6px; }
    /* Indigo at rest so the code reads as the clickable thing in the cell now
       that the name is not; underlined only on hover, because a permanent
       underline on every row of a busy table is noise. */
    .bn-party-link { color: #4f46e5; font-weight: 700; text-decoration: none; cursor: pointer; }
    .bn-party-link:hover { color: #3730a3; text-decoration: underline; }
    .bn-party-link:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; border-radius: 3px; }
    .bn-party-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: 0.02em; }

    .txn-pick input, .lf-pick-all {
        width: 15px; height: 15px; margin: 0; vertical-align: middle; cursor: pointer; accent-color: #4f46e5;
    }
    .lf-sel {
        display: none; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 7px 14px; background: #eef2ff; border-bottom: 1px solid #e0e7ff;
        font-size: 11.5px; color: #3730a3; font-weight: 600;
    }
    .lf-sel.is-on { display: flex; }
    .lf-sel .lf-sel-n { font-weight: 800; }
    .lf-sel button {
        height: 26px; padding: 0 10px; border: 1px solid #c7d2fe; border-radius: 6px; cursor: pointer;
        background: #fff; color: #4338ca; font-size: 11px; font-weight: 700;
    }
    .lf-sel button:hover { background: #f5f6ff; border-color: #a5b4fc; }
    .lf-sel .lf-sel-print { background: #4f46e5; color: #fff; border-color: #4f46e5; text-decoration: none;
        height: 26px; padding: 0 12px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
    .lf-sel .lf-sel-print:hover { background: #4338ca; }
    .lf-print.has-sel { border-color: #4f46e5; color: #4f46e5; background: #f5f6ff; }
    tr.is-picked td { background: #f5f6ff !important; }
    /* The row a ?entry= link landed on — see landOnEntry(). Amber, so it is
       not mistaken for a picked row. */
    #ledgerBody tr.is-landed td { background: #fef3c7 !important; }


    .txn-table tbody td { padding: 11px 12px; border-bottom: 1px solid #b8c4d4; color: #334155; }
    .bank-ledger-table tbody tr.border-b { border-bottom-color: #b8c4d4; }
    .txn-table tbody tr:last-child td { border-bottom: none; }
    /* Green = money arriving, red = money leaving.
       A bank account is an asset, so a DEBIT increases it (a deposit, a
       reversal of a withdrawal) and a CREDIT decreases it (a withdrawal, a
       payment out). The colours used to run the other way, which made a
       withdrawal read as a gain and a deposit as a loss — and contradicted the
       In/Out badge sitting in the same row, which has always been green for
       `debit > 0`. Amount and badge now say the same thing. */
    .txn-amount-debit  { color: #16a34a; font-weight: 600; }
    .txn-amount-credit { color: #dc2626; font-weight: 600; }

    /* Tiny pencil beside a description. Hidden until the row is hovered so it
       never competes with the text, and only rendered on rows the server will
       actually accept an edit for. */
    /* Sits AFTER the text (owner, 2026-09-15). It used to sit before it, with
       its box reserved while invisible so the text would not jump on hover —
       but only editable rows carry the button, so those rows started 16px
       to the right of the ones that do not, and the column read as ragged.
       Trailing, the text always starts at the column edge. */
    .desc-edit {
        border: 0; background: none; padding: 0; margin-left: 6px;
        display: inline-block; width: 9px; text-align: left;};
s{margin-top: 2px;
              padding-left: 16px; /* = .desc-edit width + margin, so it lines up under the description text */ }}{margin-top: 2px; }
        color: #cbd5e1; font-size: 0.62rem; line-height: 1; cursor: pointer;
        opacity: 0; transition: opacity .15s, color .15s;
        vertical-align: middle;
    }
    tr:hover .desc-edit { opacity: 1; }
    .desc-edit:hover { color: #4f46e5; }
    .desc-edit:focus-visible { opacity: 1; outline: 2px solid #6366f1; outline-offset: 2px; }

    /* ── The landing view's header ──────────────────────────────────────────
       Almost nothing, and that is the point. #placeholderArea now wears the
       statement's own .ledger-header, .ledger-meta and lf- controls, so all the
       gradient, the lighting, the type scale, the rail and the filter bar are
       already defined above and shared. Only what genuinely differs is here.

       The three blocks it replaced — .txn-head, .txn-search, .txn-kpi — went
       with the white strip they bounded. Nothing else referenced them. */

    /* The mark plate, standing in for .ledger-avatar. There is no bank to show a
       logo for, so it holds a glyph on the same translucent white the company
       badge beside it uses, at the weight the 92px logo plate would have had. */
    .ca-mark {
        flex: 0 0 56px; width: 56px; height: 56px; border-radius: 18px;
        display: grid; place-items: center; font-size: 1.32rem; color: #fff;
        background: rgba(255,255,255,0.16);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.30), inset 0 1px 0 rgba(255,255,255,0.38);
    }
    /* A step under #ledgerBankName's 1.42rem: this title is a section heading,
       not the name of the thing you opened, and it should not out-shout the
       statement header you get after clicking a row. */
    .ca-title {
        font-size: 1.24rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.15;
        margin: 0; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ledger-head-top .ca-title { text-shadow: 0 2px 5px rgba(2,6,23,0.34); }
    /* Left-aligned, per the owner, 2026-08-20. .lh-zone sets justify-content to
       center for its own column direction — vertical centring — but .lh-zone-id
       turns the flow sideways, so on this card the same rule was centring the
       mark and the title HORIZONTALLY in a zone far wider than they are. The
       statement never showed it: a 92px logo beside a full bank name fills that
       zone, leaving nothing to centre. Scoped to .ca-header so the statement's
       own header is untouched either way. */
    .ca-header .lh-zone-id { justify-content: flex-start; }
    /* Sized to hold "৳299,511.23 Dr" on one line without taking width the title
       needs — the figures here run to nine digits plus the tag. */
    .ca-header .lh-zone-bal { flex: 0 1 210px; }
    /* The period is a date range, which is running text — at .lh-fig's full size
       and weight it read as a figure and competed with the money in the rail. */
    .ca-header .ca-period { font-size: 0.98rem; font-weight: 700; }
    /* The line under it normally just states the window. When a typed date has
       been pulled back to that window it is reporting a CHANGE the user did not
       make, so it stops looking like a caption: amber, on its own tinted chip,
       the way a correction has to be if it is going to be noticed at all. */
    .ca-header .ca-note-warn {
        display: inline-block; margin-top: 3px; padding: 1px 8px; border-radius: 999px;
        font-weight: 700; color: #fff8e1;
        background: rgba(180,83,9,0.55);
        box-shadow: inset 0 0 0 1px rgba(253,230,138,0.55);
    }

    /* The table's own panel, replacing the plain white .portal-card the whole
       landing view used to be. Same edge as the statement's table container, so
       the filter bar joins straight onto the column headings. */
    .ca-panel {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
        overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    }

    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.45);
        backdrop-filter: blur(3px); z-index: 1000; align-items: center; justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    /* THE FOOTER STAYS REACHABLE ON A SHORT SCREEN.
       This box had no max-height and .modal-overlay does not scroll, so a modal
       taller than the window was centred and then clipped at BOTH ends by the
       box's own overflow:hidden — Save included. It fitted only because the form
       was short; adding the charge/VAT block took it past a 768px laptop at 100%
       zoom, and any screen at 110%. Capping the box and letting the BODY scroll
       keeps the header and the buttons pinned where they are, rather than making
       the whole page scroll to reach them. */
    .modal-box {
        background: #fff; border-radius: 16px; box-shadow: 0 24px 48px rgba(0,0,0,0.18);
        width: 100%; max-width: 460px; overflow: hidden;
        max-height: calc(100vh - 2rem);
        display: flex; flex-direction: column;
    }
    /* The form is the middle link: body and footer are ITS children, so it has
       to carry the column too or the body has nothing to grow against.
       min-height:0 is what actually lets it shrink — a flex item defaults to
       min-height:auto and refuses to go below its content, which is exactly how
       an overflowing modal ends up clipped instead of scrolled. */
    .modal-box > form { display: flex; flex-direction: column; min-height: 0; }
    .modal-box .modal-body { overflow-y: auto; min-height: 0; }
    .modal-header { position:relative; background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); padding: 1.1rem 1.4rem; color: #fff; }
    .modal-header h3 { font-size: 1rem; font-weight: 700; margin: 0; }
    .modal-header p  { font-size: 0.78rem; opacity: 0.8; margin: 2px 0 0; }
    .txn-modal-close { position:absolute; top:14px; right:16px; width:34px; height:34px; border:0; border-radius:9px; background:rgba(255,255,255,.18); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:background .15s ease, transform .15s ease; }
    .txn-modal-close:hover { background:rgba(255,255,255,.3); transform:scale(1.04); }
    .txn-modal-close:focus-visible { outline:2px solid #fff; outline-offset:2px; }
    .modal-body  { padding: 1.4rem; }
    .modal-footer { padding: 1rem 1.4rem; background: #f8fafc; display: flex; justify-content: flex-end; gap: 0.5rem; border-top: 1px solid #e2e8f0; }
    .form-group { margin-bottom: 0.85rem; }
    .form-group label { display: block; font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 4px; }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 8px 11px;
        font-size: 0.85rem; color: #1e293b; background: #fff; box-sizing: border-box;
    }
    .form-group .required { color: #ef4444; }
    /* The charge/VAT block: set apart from the fields above it so it reads as
       "extras", and cheap to skip when there are none. */
    .txn-cut {
        border: 1px dashed #cbd5e1; border-radius: 10px;
        padding: 0.75rem 0.85rem 0.1rem; margin-bottom: 0.85rem; background: #f8fafc;
    }
    .txn-cut-head {
        font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.6rem;
    }
    .txn-cut-head span { font-weight: 500; text-transform: none; letter-spacing: 0; color: #94a3b8; }
    .txn-cut-row { display: grid; grid-template-columns: 1fr 1.6fr; gap: 0.6rem; }
    /* THE DEPOSIT / WITHDRAW BOX (owner, 2026-09-14): wider, and fields in
       pairs, so the whole form is on screen at once on a laptop instead of
       being scrolled through one box at a time. Amount sits with Date, the
       person with the purpose, and the two bank-cut rows go side by side.
       Below 640px the pairs stack back into one column. */
    #txnModal .modal-box { max-width: 720px; }
    #txnModal .modal-body { padding: 1.25rem 1.5rem 0.75rem; }
    .txn-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
    .txn-cut-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
    .txn-cut-grid .txn-cut-row { grid-template-columns: 1fr 1.4fr; }
    #txnModal .form-group label { font-size: 0.8rem; }
    #txnModal .form-group input, #txnModal .form-group select { padding: 9px 12px; font-size: 0.88rem; }
    #txnModal .form-group input:focus, #txnModal .form-group select:focus {
        outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.14);
    }
    .txn-via, .txn-pair.txn-triple { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0 1rem; }
    /* Hour : minute AM/PM in one box-shaped row, sized like the inputs beside it. */
    .txn-time { display: grid; grid-template-columns: 1fr auto 1fr 1.1fr; gap: 0 4px; align-items: center; }
    .txn-time select { padding-left: 8px !important; padding-right: 4px !important; text-align: center; }
    .txn-time-colon { color: #94a3b8; font-weight: 700; }
    @media (max-width: 640px) { .txn-via, .txn-pair.txn-triple { grid-template-columns: 1fr; } }
    .txn-section {
        font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
        color: #94a3b8; margin: 0.25rem 0 0.6rem; padding-bottom: 0.35rem; border-bottom: 1px solid #f1f5f9;
    }
    @media (max-width: 640px) {
        .txn-pair, .txn-cut-grid { grid-template-columns: 1fr; }
    }
    .txn-cut-total {
        font-size: 0.76rem; font-weight: 700; color: #b91c1c;
        border-top: 1px solid #e2e8f0; padding: 0.5rem 0 0.6rem; margin-top: 0.15rem;
    }
    .txn-cut-total.is-in { color: #15803d; }
    .btn-cancel { padding: 8px 20px; border-radius: 8px; border: none; background: #e2e8f0; color: #475569; font-weight: 600; cursor: pointer; }
    .btn-submit { padding: 8px 22px; border-radius: 8px; border: none; background: #4f46e5; color: #fff; font-weight: 700; cursor: pointer; }
    .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
    .alert-success { border-left: 4px solid #10b981; background: #f0fdf4; color: #166534; padding: 10px 14px; border-radius: 8px; font-weight: 500; font-size: 0.88rem; margin-bottom: 1rem; }
    .alert-error   { border-left: 4px solid #ef4444; background: #fef2f2; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem; }

    /* Search & filter bar */
    .bank-filter-bar { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; }
    .bank-filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .bank-search-wrap { position: relative; flex: 1; min-width: 260px; }
    .bank-search-wrap input {
        width: 100%; padding: 10px 14px 10px 36px; border: 1px solid #e2e8f0; border-radius: 10px;
        font-size: 0.85rem; outline: none; background: #f8fafc;
    }
    .bank-search-wrap input:focus { border-color: #6366f1; background: #fff; }
    .bank-search-wrap i.fa-search { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem; }
    .bank-filter-bar select, .bank-filter-bar input[type=date] {
        padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; background: #f8fafc; outline: none;
    }
    .bank-filter-bar select:focus, .bank-filter-bar input[type=date]:focus { border-color: #6366f1; background: #fff; }
    .bank-load-btn {
        padding: 10px 20px; border-radius: 10px; border: none; background: #4f46e5; color: #fff;
        font-weight: 700; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 6px;
    }
    .bank-load-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .bank-dropdown {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        max-height: 280px; overflow-y: auto; z-index: 40;
    }
    .bank-dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
    .bank-dropdown-item:last-child { border-bottom: none; }
    .bank-dropdown-item:hover { background: #eef2ff; }
    .bank-dropdown-avatar {
        width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg,#6366f1,#4f46e5);
        color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
    }
    .selected-bank-chip {
        display: inline-flex; align-items: center; gap: 8px; background: #eef2ff; color: #4338ca;
        padding: 6px 12px; border-radius: 99px; font-size: 0.8rem; font-weight: 600; margin-top: 10px;
    }
    .selected-bank-chip button { background: none; border: none; color: #4338ca; cursor: pointer; font-size: 0.85rem; }

    /* Ledger panel */
    /* Ledger header — the account card ------------------------------------
       One card carries the whole account: who it is, what moved last, and every
       figure the statement below is built from. The four summary tiles that used
       to sit under it are the right-hand half of band 2, so the eye reads the
       account once instead of hopping between a header and four boxes.

       Band 1 (WHO)      mark + name + branch + A/C | company + balance | last movement | actions
       Band 2 (HOW MUCH) an inset rail: today in, today out, count, then
                         opening -> debit -> credit -> closing, left to right in
                         the order the money actually flows.

       Everything here is scoped under .ledger-header or an lh- prefix. No bare
       element selectors, so nothing leaks to the rest of the dashboard.

       The depth is four cheap tricks and no extra assets: a three-stop gradient
       instead of two, a corner light with an opposite-corner shade over it,
       hairlines that fade out at both ends rather than hard 1px rules, and one
       clear weight step between every label and its value. */
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
       number — but only a step. At 55% it read as a speck of lint rather than a
       currency mark. Same for the Dr/Cr tag. */
    .lh-fig .cur { font-size: 0.8em; font-weight: 700; opacity: 0.85; margin-right: 2px; }
    .lh-fig .tag { font-size: 0.66em; font-weight: 800; letter-spacing: 0.06em; opacity: 0.85; margin-left: 5px; }

    /* ── Band 1 ─────────────────────────────────────────────────────────── */
    .ledger-head-top { display: flex; flex-wrap: wrap; align-items: stretch; row-gap: 16px; }
    /* Everything on the gradient gets a soft dark shadow. It is what separates
       the glyph from the colour behind it — without it the lighter weights below
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
       baked-in white box, so nothing shows a grey square — but three of them are
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
    /* Current / Savings / Cash — a chip rather than more running text, so the
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

    /* Latest transaction. The direction is a pill rather than the word "In" —
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

    /* ── Band 2 — the figures rail ──────────────────────────────────────── */
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
       clipped "OPENING BALA…" and "TOTAL CREDIT (o…". */
    .ledger-meta > div { position: relative; flex: 1 1 auto; min-width: 0; padding: 0 0.85rem; }
    /* The cell separator is indigo, not slate: it is the card's own colour, so
       the rail reads as part of the card rather than a plain table dropped onto
       it. Still 1px — the weight was never the problem.

       It is SOLID and full height here, unlike every other hairline on this card.
       Those fade out at both ends, which works on the indigo where they only have
       to suggest an edge. In the rail they have to actually divide seven columns
       of numbers, and a rule that is 14% opaque at the ends paints about half its
       own height — next to a wide figure it vanished, which is exactly what got
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

    /* Money in is green, money out is red — literally the same two inks the
       statement table's Debit and Credit columns use, so the rail and the columns
       under it can never be read as two different things. */
    .ledger-meta > div.is-in  .lh-fig, .ledger-meta > div.is-in  .lh-lbl { color: #15803d; }
    .ledger-meta > div.is-out .lh-fig, .ledger-meta > div.is-out .lh-lbl { color: #b91c1c; }
    /* A day with no movement drops the colour but NOT the contrast — a zero you
       cannot read is worse than a zero that is merely quiet. */
    /* Only the figure greys out. The label was going to #94a3b8, which is
       2.48:1 on white — the same mistake as before, in a new palette. It keeps
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

    .placeholder-box { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
</style>

@php
    $roleSlug = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
    $typeIcons = [
        'bank' => 'fa-university',
        'mobile_banking' => 'fa-mobile-alt',
        'digital_wallet' => 'fa-wallet',
        'cash' => 'fa-money-bill-wave',
    ];
    $typeLabels = [
        'bank' => ['deposit' => 'Deposit', 'withdraw' => 'Withdraw'],
        'mobile_banking' => ['deposit' => 'Cash In', 'withdraw' => 'Cashout'],
        'digital_wallet' => ['deposit' => 'Cash In', 'withdraw' => 'Cashout'],
        'cash' => ['deposit' => 'Cash In', 'withdraw' => 'Cash Out'],
    ];
@endphp

@include('layout.bank-tabs')

@if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert-error">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert-error">{{ $errors->first() }}</div>
@endif

{{-- One row: what this page is on the left, the two figures on the right.

     The account names used to fill the right half — four columns of them,
     thirteen lines. They came out on the owner's instruction (2026-08-19: "i do
     not need the banks, i just need the two kpi only"), and rightly: every name
     was repeated on a card a few pixels below, so the block was a table of
     contents for a list already on screen. Losing it took the banner from three
     lines to one and gave the cards roughly 60px more of the fold. --}}
<div class="bank-dash-banner">
    <div class="bank-dash-head">
        <div class="icon-box"><i class="fas fa-landmark"></i></div>
        <h2>Manage Bank Accounts</h2>
    </div>

    <div class="bank-dash-stats">
        <div class="bank-dash-stat">
            <div class="val" id="bannerBalanceStat">৳{{ number_format($totalBalance, 2) }}</div>
            <div class="lbl" id="bannerBalanceLabel">Total Balance</div>
        </div>
        <div class="bank-dash-stat">
            <div class="val" id="bannerAccountsStat">{{ $banks->count() }}</div>
            <div class="lbl">Accounts</div>
        </div>
    </div>
</div>

{{-- Company summary strip — click a card to see that company's own recent
     activity and have the banner stats reflect just that company. --}}
@if($bankGroups->isNotEmpty())
<div id="companyPicker" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
    @foreach($bankGroups as $companyName => $companyBanks)
        <div class="company-pick-card" data-company-name="{{ $companyName }}"
            onclick="selectCompany({{ json_encode($companyName) }})"
            style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.04);cursor:pointer;transition:box-shadow .15s,border-color .15s;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:36px;height:36px;border-radius:9px;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:0.95rem;flex-shrink:0;">
                    <i class="fas fa-building"></i>
                </div>
                <div style="font-weight:700;color:#1e293b;font-size:0.95rem;">{{ $companyName }}</div>
            </div>
            <div style="font-size:1.2rem;font-weight:700;color:#1e293b;margin-bottom:2px;">৳{{ number_format($companyBanks->where('type', '!=', 'credit_card')->sum('journal_balance'), 2) }}</div>
            <div style="font-size:0.72rem;color:#94a3b8;">
                {{ $companyBanks->count() }} account{{ $companyBanks->count() === 1 ? '' : 's' }} <i class="fas fa-chevron-right" style="margin-left:4px;"></i> View
            </div>
        </div>
    @endforeach
</div>
@endif

{{-- Bank cards for the selected company — replaces the company picker above
     once a company is chosen; "All Companies" returns to the picker. --}}
<div id="companyBankCards" style="display:none;margin-bottom:1.5rem;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:0.85rem;">
        <button type="button" onclick="showCompanyPicker()"
            style="border:1px solid #e2e8f0;background:#fff;color:#475569;border-radius:8px;padding:6px 12px;font-size:0.78rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-arrow-left"></i> All Companies
        </button>
        {{-- Card order toggle. Two states only, so it is a button and not a menu:
             the icon shows the order the cards are IN, the tooltip says what a
             click will change it to. Written by paintCardSortBtn(). --}}
        <button type="button" id="cardSortBtn" class="card-sort-btn" onclick="toggleCardSort()" title="" aria-label="">
            <i class="fas fa-arrow-down-a-z" id="cardSortIcon"></i>
        </button>
        <h3 style="font-size:0.95rem;font-weight:700;color:#1e293b;margin:0;" id="bankCardsCompanyName">—</h3>
        {{-- Card view mode: hover-to-expand / always compact / always
             expanded. Pushed to the far end of the bar with margin-left:auto
             — a per-browser preference (owner: "sometime i need and sometime
             i don't"), remembered in localStorage so it does not reset every
             visit. Written by paintCardHoverBtn(). --}}
        <button type="button" id="cardHoverBtn" class="card-sort-btn" style="margin-left:auto;" onclick="cycleCardHoverMode()" title="" aria-label="">
            <i class="fas fa-expand" id="cardHoverIcon"></i>
        </button>
    </div>
    <div id="bankCardsGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.75rem;align-items:start;grid-auto-rows:85px;"></div>
</div>

{{-- Search & filter bar — hidden; navigation now happens entirely through the
     company cards and clicking a transaction row. Left in the DOM (not removed)
     because loadLedger()/resolveDates() still read these elements when a row
     click triggers a full ledger load. --}}
<div class="bank-filter-bar" style="display:none;">
    <div class="bank-filter-row">
        <div class="bank-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="bankSearch" placeholder="Search bank, account number, wallet...">
            <div id="bankDropdown" class="bank-dropdown" style="display:none;"></div>
        </div>
        <select id="companyFilter" onchange="onCompanyFilterChange()">
            <option value="">All Companies</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
        {{-- All Time is the selected option, not This Year: opening an account
             shows its whole ledger, newest first, and nothing narrower until the
             reader asks for it. This select is the period loadLedger() actually
             reads — the visible range buttons write into it — so the default
             here IS the default period. --}}
        <select id="datePreset">
            <option value="this_year">This Year</option>
            <option value="this_month">This Month</option>
            <option value="last_3">Last 3 Months</option>
            <option value="last_6">Last 6 Months</option>
            <option value="all_time" selected>All Time</option>
            <option value="custom">Custom Range</option>
        </select>
        <input type="date" id="dateFrom" style="display:none;">
        <input type="date" id="dateTo" style="display:none;">
        <button type="button" class="bank-load-btn" id="loadBtn" disabled onclick="loadLedger()">
            <i class="fas fa-sync-alt" id="loadIcon"></i> Load
        </button>
    </div>
    <div id="selectedBankChip" class="selected-bank-chip" style="display:none;">
        <i class="fas fa-university"></i> <span id="chipBankName"></span>
        <button type="button" onclick="clearSelectedBank()"><i class="fas fa-times"></i></button>
    </div>
</div>

{{-- Company-scoped recent activity — shown until a specific bank's full
     ledger is loaded. Content is rendered client-side from COMPANY_DATA so
     switching companies (via the cards above) needs no round trip. --}}
{{-- THE LANDING VIEW — no bank selected yet.
     Owner, 2026-08-20: the plain white heading strip was to be coloured "like"
     the statement's, and to carry the same KPIs, search and filter. So it IS the
     statement's header — .ledger-header, its lighting, its two bands, its type
     scale — with the identity zone removed, because there is nothing to identify
     until a bank is picked: no mark, no A/C, no Deposit/Withdraw. Reused rather
     than copied on purpose; two hand-kept copies of that card would drift. --}}
<div id="placeholderArea">
    <div class="ledger-header ca-header">
        <div class="ledger-head-top">
            <div class="lh-zone lh-zone-id">
                <span class="ca-mark"><i class="fas fa-right-left"></i></span>
                <div class="lh-idcol">
                    <h2 class="ca-title" id="recentTxnTitle">Recent Transactions</h2>
                    <div class="lh-line" id="recentTxnSubtitle">Last 14 days. Click any row to open that account's full ledger.</div>
                </div>
            </div>

            {{-- What every account in this company adds up to right now — the
                 company-wide answer to the statement's Closing Balance. Taken
                 from the server's total_balance, NOT from the last row's running
                 figure: the feed hides the petty-cash pot's movements, so its
                 final running balance is one account short of the truth. --}}
            <div class="lh-zone lh-zone-bal">
                <span class="lh-lbl">Balance <span class="lh-qual">(closing)</span></span>
                <span class="lh-fig" id="caBalance">—</span>
            </div>

            <div class="lh-zone lh-zone-co">
                <span class="lh-lbl">Company</span>
                <div class="lh-co" style="margin-top:6px;">
                    <span class="ci"><i class="bi bi-briefcase-fill"></i></span>
                    <span class="lh-co-name" id="caCompanyName">—</span>
                </div>
            </div>

            {{-- What the table is actually showing. The statement puts the last
                 transaction here; on the landing view the equivalent question is
                 "which days am I looking at", and the answer changes under the
                 filter bar below, so it needs to be on screen. --}}
            <div class="lh-zone lh-zone-last">
                <span class="lh-lbl">Showing</span>
                <span class="lh-fig ca-period" id="caPeriodLabel">Last 14 days</span>
                <div class="lh-last-when" id="caPeriodNote">This feed holds the last 14 days only</div>
            </div>
        </div>

        {{-- Band 2, the same rail the statement carries. Two different scopes
             sit in it, exactly as they do there: the first two cells report
             TODAY whatever the filter says, and the middle three report the
             PERIOD on screen. Net Change is the server's own 14-day figure and
             is deliberately not recomputed here — it counts the petty-cash pot's
             legs, which the feed below hides, so a client-side sum of these rows
             would quietly disagree with the banner at the top of the page. --}}
        <div class="ledger-meta">
            <div class="is-in">
                <span class="lh-lbl"><i class="fas fa-arrow-down"></i>Received Today</span>
                <span class="lh-fig" id="todayInVal">—</span>
            </div>
            <div class="is-out">
                <span class="lh-lbl"><i class="fas fa-arrow-up"></i>Paid Today</span>
                <span class="lh-fig" id="todayOutVal">—</span>
            </div>
            <div>
                <span class="lh-lbl"><i class="fas fa-list-ul"></i>Transactions</span>
                <span class="lh-fig" id="caTxnCount">—</span>
            </div>
            <div class="is-in">
                <span class="lh-lbl"><i class="fas fa-arrow-down"></i>Total Debit <span class="lh-qual">(in)</span></span>
                <span class="lh-fig" id="caTotalDebit">—</span>
            </div>
            <div class="is-out">
                <span class="lh-lbl"><i class="fas fa-arrow-up"></i>Total Credit <span class="lh-qual">(out)</span></span>
                <span class="lh-fig" id="caTotalCredit">—</span>
            </div>
            <div class="is-close" id="netChangeStat">
                <span class="lh-lbl"><i class="fas fa-flag-checkered"></i>Net Change (14 Days)</span>
                <span class="lh-fig" id="netChangeVal">—</span>
            </div>
        </div>
    </div>

    <div class="ca-panel">
        {{-- The filter bar, built from the statement's own controls — but every
             one of them works on the rows already in the browser.

             The statement's bar re-asks the server because its Opening Balance
             and totals are properties of the period and only the server can
             recompute them. Nothing here is: this feed is a fixed last-14-days
             set with no opening balance, and each row's company balance is a
             point in time, so narrowing the period cannot make a printed figure
             wrong. There is also no company-wide statement endpoint to ask.

             Which makes 14 days a hard ceiling, so the control refuses to
             promise more than it can show: the pickers carry min/max, the last
             segment says "All 14 Days", and a typed date outside the window is
             pulled back to it with a note in the header rather than silently
             returning nothing. --}}
        <div class="lf-bar">
            <div class="lf-search" id="txnSearchWrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="txnSearch" autocomplete="off"
                       placeholder="Search bank, description, TXN no, date or amount…" aria-label="Search transactions">
                <button type="button" class="lf-clear" id="txnClear" title="Clear search"><i class="fas fa-xmark"></i></button>
            </div>

            {{-- No "Last Month": the feed does not hold one. A button that
                 silently means the same as All is a lie the user only catches by
                 counting rows. --}}
            <div class="lf-seg" id="caSeg">
                <button type="button" data-range="today">Today</button>
                <button type="button" data-range="yesterday">Yesterday</button>
                <button type="button" data-range="week">Last Week</button>
                <button type="button" data-range="all">All 14 Days</button>
            </div>

            <div class="lf-dates" id="caDates">
                {{-- Typed half and picker half of one control, the same shape the
                     statement uses — see the .lf-date block in the sheet. --}}
                <span class="lf-date">
                    <input type="text" id="caFromText" class="lf-date-text" placeholder="dd/mm/yyyy"
                           inputmode="numeric" maxlength="10" autocomplete="off" aria-label="From date">
                    <input type="date" id="caFrom" class="lf-date-native" aria-label="Pick the from date" title="Pick a date">
                </span>
                <span>to</span>
                <span class="lf-date">
                    <input type="text" id="caToText" class="lf-date-text" placeholder="dd/mm/yyyy"
                           inputmode="numeric" maxlength="10" autocomplete="off" aria-label="To date">
                    <input type="date" id="caTo" class="lf-date-native" aria-label="Pick the to date" title="Pick a date">
                </span>
                <button type="button" class="lf-go" onclick="applyCompanyRange('custom')">Apply</button>
                <button type="button" class="lf-date-reset" onclick="applyCompanyRange('all')" title="Clear date filter">
                    <i class="fas fa-xmark"></i> Reset
                </button>
            </div>

            {{-- Prints what the table shows: the period on the buttons, or
                 the ticked rows when there are any. The href is rebuilt by
                 paintCompanySelection() every time either changes. --}}
            <a href="#" id="caPrint" target="_blank" class="lf-print" title="Print — today, a day, a date range, or the ticked rows"><i class="fas fa-print"></i> Print</a>

            <span class="lf-count" id="caCount" style="display:none;"></span>
        </div>

        <div class="lf-sel" id="caSel">
            <span><i class="fas fa-hand-pointer" style="margin-right:4px;"></i>Tick the transactions to print — <span class="lf-sel-n" id="caSelN">0</span> selected</span>
            <button type="button" onclick="pickWholeDay('ca')" title="Tick every row on the same day(s) as the ticked ones">Select whole day</button>
            <button type="button" onclick="clearPicks('ca')">Clear</button>
            <a href="#" id="caSelPrint" target="_blank" class="lf-sel-print"><i class="fas fa-print"></i> Print selected</a>
            <button type="button" class="lf-sel-cancel" onclick="stopPicking('ca')">Cancel</button>
        </div>

        <div style="overflow-x:auto;">
            <table class="txn-table">
                <thead>
                    {{-- data-sort marks a column sortable; the arrow is filled in by
                         paintTxnSortIndicators(). Date descending is the default. --}}
                    <tr>
                        <th class="txn-pick"><input type="checkbox" class="lf-pick-all" id="caPickAll" title="Tick every row shown" aria-label="Select all shown"></th>
                        <th data-sort="date">Date <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th data-sort="bank">Bank <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th data-sort="description">Description <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th data-sort="beneficiary">Beneficiary <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th style="text-align:right;" data-sort="debit">Debit <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th style="text-align:right;" data-sort="credit">Credit <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th style="text-align:right;" data-sort="balance">Company Balance <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                    </tr>
                </thead>
                <tbody id="recentTxnBody"></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Inline ledger panel — populated via AJAX once a bank is loaded --}}
<div id="ledgerArea" style="display:none;">
    <div class="ledger-header">
        {{-- Band 1 — WHO. Three zones split by hairlines, then the actions.
             Every value is written by renderLedger(); the markup only holds the
             shape and the labels. --}}
        <div class="ledger-head-top">

            <div class="lh-zone lh-zone-id">
                {{-- The mark plate. All three layers are always in the DOM and
                     renderLedger() flips between them, so a missing or 404'd logo
                     falls back to the brand's initials — never a broken image,
                     never an empty box. The <i> is the account-type glyph, used
                     for cash, which has no mark to print and no brand worth
                     initialling. --}}
                <span class="ledger-avatar is-mono" id="ledgerAvatar">
                    {{-- No src= until renderLedger() sets one: an empty src resolves
                         to the page URL and fetches the dashboard back as an image. --}}
                    <img id="ledgerBankLogo" alt="" onerror="this.closest('.ledger-avatar').classList.add('is-mono');">
                    <span class="lv-mono" id="ledgerBankInitials"></span>
                    <i class="fas fa-university" id="ledgerBankIcon"></i>
                </span>
                <div class="lh-idcol">
                    <h2 id="ledgerBankName">—</h2>
                    <div class="lh-line" id="lineBranch"><i class="fas fa-location-dot"></i><span id="infoBranch">—</span></div>
                    <div class="lh-line"><span class="lh-k">A/C</span><span class="lh-ac" id="infoAccountNumber">—</span><span class="lh-type" id="infoAccountType">—</span></div>
                </div>
            </div>

            <div class="lh-zone lh-zone-co">
                <div>
                    <span class="lh-lbl">Company</span>
                    <div class="lh-co" style="margin-top:6px;">
                        <span class="ci" id="companyIconPlate"><i class="bi bi-briefcase-fill" id="companyIcon"></i></span>
                        <span class="lh-co-name" id="infoCompany">—</span>
                    </div>
                </div>
                <div>
                    {{-- "Current" and not "Closing": this is the balance on the
                         account today, all time. Closing Balance in the rail below
                         is the selected period's. --}}
                    <span class="lh-lbl">Current Balance</span>
                    <span class="lh-fig" id="infoBalance">—</span>
                </div>
            </div>

            <div class="lh-zone lh-zone-last">
                <span class="lh-lbl">Latest Transaction</span>
                {{-- Direction is a coloured pill, not the word "In" — the arrow
                     and the colour read faster than a label, and it leaves the
                     line free for the amount, which is what you came to see. --}}
                <div class="lh-last-row">
                    <span class="lh-pill lh-pill-none" id="lastTxnPill"><i class="fas fa-minus"></i><span id="lastTxnDir">None</span></span>
                    <span class="lh-last-amt" id="lastTxnAmount">—</span>
                </div>
                <div class="lh-last-note" id="lastTxnPurpose">—</div>
                <div class="lh-last-when" id="lastTxnTime">—</div>
            </div>

            <div class="lh-actions">
                <button type="button" class="bank-btn bank-btn-deposit" id="depositBtn" onclick="openTxnModal('deposit')">
                    <i class="fas fa-plus"></i> <span id="depositLabel">Deposit</span>
                </button>
                <button type="button" class="bank-btn bank-btn-withdraw" id="withdrawBtn" onclick="openTxnModal('withdraw')">
                    <i class="fas fa-minus"></i> <span id="withdrawLabel">Withdraw</span>
                </button>
                <a href="#" id="printStatementLink" target="_blank" class="bank-btn bank-btn-statement">
                    <i class="fas fa-print"></i> Print
                </a>
                <a href="#" id="reconcileLink" class="bank-btn bank-btn-statement">
                    <i class="fas fa-scale-balanced"></i> Reconcile
                </a>
            </div>
        </div>

        {{-- Band 2 — HOW MUCH. The last four cells are the summary tiles that
             used to sit in their own row under this card; they read left to right
             in the order the money flows, ending on the closing balance the
             statement below opens with. Debit/Credit keep the "(in)"/"(out)"
             qualifier the table's own column headers use, so the rail and the
             columns can never be read as two different things. --}}
        <div class="ledger-meta">
            <div class="is-in">
                <span class="lh-lbl"><i class="fas fa-arrow-down"></i>Received Today</span>
                <span class="lh-fig" id="infoTodayIn">—</span>
            </div>
            <div class="is-out">
                <span class="lh-lbl"><i class="fas fa-arrow-up"></i>Paid Today</span>
                <span class="lh-fig" id="infoTodayOut">—</span>
            </div>
            <div>
                <span class="lh-lbl"><i class="fas fa-list-ul"></i>Transactions</span>
                <span class="lh-fig" id="infoTxnCount">—</span>
            </div>
            <div>
                <span class="lh-lbl"><i class="fas fa-flag"></i>Opening Balance</span>
                <span class="lh-fig" id="summOpening">—</span>
            </div>
            <div class="is-in">
                <span class="lh-lbl"><i class="fas fa-arrow-down"></i>Total Debit <span class="lh-qual">(in)</span></span>
                <span class="lh-fig" id="summDebit">—</span>
            </div>
            <div class="is-out">
                <span class="lh-lbl"><i class="fas fa-arrow-up"></i>Total Credit <span class="lh-qual">(out)</span></span>
                <span class="lh-fig" id="summCredit">—</span>
            </div>
            <div class="is-close">
                <span class="lh-lbl"><i class="fas fa-flag-checkered"></i>Closing Balance</span>
                <span class="lh-fig" id="summClosing">—</span>
            </div>
        </div>
    </div>


    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
        {{-- The statement's filter bar. Two controls that work differently on
             purpose, and the difference matters:

             SEARCH is client-side over the rows already on screen. It is a
             find-in-page, so it is instant and needs no round trip.

             The DATE RANGE re-asks the server, because this table is not just
             rows: it opens on an Opening Balance and closes on totals and a
             Closing Balance, and those are properties of the PERIOD. Filtering
             the rows in the browser would leave all four describing a period
             that is no longer on screen — a statement that does not add up. The
             server recomputes them for the range, which is the only way they
             stay true. --}}
        <div class="lf-bar lf-ledger-toolbar">
            <div class="lf-view-controls">
            <select id="lfTransactionView" aria-label="Transaction view" onchange="LEDGER_PAGE=1; paintLedgerRows()" class="lf-view-select">
                <option value="active">Active transactions</option>
                <option value="all">All transactions</option>
                <option value="reversed">Reversed transactions</option>
            </select>
            <button type="button" class="lf-view-help" aria-label="About transaction views" title="Active view hides complete cancelled pairs within this period, including reversals posted on another day. Historical running balances, totals and full-statement print include all postings. A single side of a pair remains visible when its counterpart is outside the date range."><i class="fas fa-circle-info"></i></button>
            <button type="button" onclick="openLedgerReversalHistory()" class="lf-history-btn"><i class="fas fa-rotate-left"></i> Reversal history <span id="lfReversalCount"></span></button>
            </div>
            <div class="lf-toolbar-bottom">
            <div class="lf-search" id="lfSearchWrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="lfSearch" autocomplete="off"
                       placeholder="Search transactions, reference or amount…">
                <button type="button" class="lf-clear" id="lfClear" title="Clear search"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="lf-seg" id="lfSeg">
                <button type="button" data-range="today">Today</button>
                <button type="button" data-range="yesterday">Yesterday</button>
                <button type="button" data-range="week">Last Week</button>
                <button type="button" data-range="month">Last Month</button>
                <button type="button" data-range="all">All</button>
            </div>

            <div class="lf-dates" id="lfDates">
                {{-- Typed half and picker half of one control — see the .lf-date
                     block in the sheet for why the date input is stripped down to
                     its calendar button. The ids stay on the date inputs, so
                     everything that already reads lfFrom/lfTo keeps reading a
                     real Y-m-d value. --}}
                <span class="lf-date">
                    <input type="text" id="lfFromText" class="lf-date-text" placeholder="dd/mm/yyyy"
                           inputmode="numeric" maxlength="10" autocomplete="off" aria-label="From date">
                    <input type="date" id="lfFrom" class="lf-date-native" aria-label="Pick the from date" title="Pick a date">
                </span>
                <span>to</span>
                <span class="lf-date">
                    <input type="text" id="lfToText" class="lf-date-text" placeholder="dd/mm/yyyy"
                           inputmode="numeric" maxlength="10" autocomplete="off" aria-label="To date">
                    <input type="date" id="lfTo" class="lf-date-native" aria-label="Pick the to date" title="Pick a date">
                </span>
                <button type="button" class="lf-go" onclick="applyLedgerRange('custom')">Apply</button>
                <button type="button" class="lf-date-reset" onclick="applyLedgerRange('all')" title="Clear date filter">
                    <i class="fas fa-xmark"></i> Reset
                </button>
                <a href="#" id="lfPrint" target="_blank" class="lf-print" title="Print — today, a day, a date range, or the ticked rows"><i class="fas fa-print"></i> Print</a>
            </div>

            </div>
            <span class="lf-count" id="lfCount" style="display:none;"></span>
        </div>

        <div class="lf-sel" id="lfSel">
            <span><i class="fas fa-hand-pointer" style="margin-right:4px;"></i>Tick the transactions to print — <span class="lf-sel-n" id="lfSelN">0</span> selected</span>
            <button type="button" onclick="pickWholeDay('lf')" title="Tick every row on the same day(s) as the ticked ones">Select whole day</button>
            <button type="button" onclick="clearPicks('lf')">Clear</button>
            <a href="#" id="lfSelPrint" target="_blank" class="lf-sel-print"><i class="fas fa-print"></i> Print selected</a>
            <button type="button" class="lf-sel-cancel" onclick="stopPicking('lf')">Cancel</button>
        </div>

        <div style="overflow-x:auto;">
            <table class="bank-ledger-table w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        {{-- # and Action are not sortable: one is a row counter,
                             the other holds buttons. --}}
                        {{-- The # column doubles as the tick column, so the
                             statement keeps its shape and its colspans. --}}
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200 whitespace-nowrap"><input type="checkbox" class="lf-pick-all" id="lfPickAll" title="Tick every row shown" aria-label="Select all shown"> #</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="date">Date <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="reference">Reference <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        {{-- No Source column (owner, 2026-09-15). The value is still
                             stored, still searchable and still on the print sheet;
                             it just stopped earning its width here, where nearly
                             every row says "Bank Transaction". --}}
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="description">Description / Note <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="beneficiary">Beneficiary <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="debit">Debit <span class="normal-case font-normal tracking-normal opacity-70">(in)</span> <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="credit">Credit <span class="normal-case font-normal tracking-normal opacity-70">(out)</span> <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200" data-sort="balance">Balance <span class="th-sort"><i class="fas fa-sort"></i></span></th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Action</th>
                    </tr>
                </thead>
                <tbody id="ledgerBody"></tbody>
            </table>
        </div>
        {{-- Filled by paintLedgerPager(); hidden whenever the period fits on a
             single page, so a small account looks exactly as it always did. --}}
        <div id="ledgerPager" class="lf-pager" style="display:none;">
            <span class="lf-pager-info" id="lfPagerInfo"></span>
            <div class="lf-pager-nav" id="lfPagerNav"></div>
        </div>
        <div id="ledgerEmpty" class="placeholder-box" style="display:none;">
            <i class="fas fa-inbox text-4xl mb-3 block"></i>
            <p>No transactions found for this account in the selected period.</p>
        </div>
    </div>
</div>

{{-- Deposit/Withdraw Modal --}}
<div class="modal-overlay" id="txnModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="txnModalTitle">Record Transaction</h3>
            <p id="txnModalSubtitle">—</p>
            <button type="button" class="txn-modal-close" onclick="closeTxnModal()" aria-label="Close transaction modal" title="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <form id="txnForm">
            <input type="hidden" name="type" id="txn_type">
            <div class="modal-body">
                <div class="txn-pair txn-triple">
                    <div class="form-group">
                        <label>Amount <span class="required">*</span></label>
                        <input type="number" name="amount" id="txn_amount" step="0.01" min="0.01" required placeholder="0.00" autofocus>
                    </div>
                    <div class="form-group">
                        <label>Date <span class="required">*</span></label>
                        <input type="date" name="date" id="txn_date" required value="{{ date('Y-m-d') }}">
                    </div>
                    {{-- Hour · minute · AM/PM as three plain controls rather than
                         <input type="time">: the native box shows "--:-- --" and
                         only reveals AM/PM once something is typed, and the
                         owner wants it visible from the start. Composed into
                         H:i by txnTimeValue() for the server. --}}
                    <div class="form-group">
                        <label>Custom Time <span class="required">*</span></label>
                        <div class="txn-time">
                            <select id="txn_hour" aria-label="Hour" required>
                                <option value="">hh</option>
                                @for($h = 1; $h <= 12; $h++)<option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>@endfor
                            </select>
                            <span class="txn-time-colon">:</span>
                            <select id="txn_minute" aria-label="Minute" required>
                                <option value="">mm</option>
                                @for($m = 0; $m < 60; $m++)<option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>@endfor
                            </select>
                            <select id="txn_ampm" aria-label="AM or PM">
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                        <p style="font-size:0.72rem;color:#94a3b8;margin-top:4px;">Required transaction time; this is the only time shown in the bank ledger.</p>
                    </div>
                </div>
                <div class="txn-pair">
                    <div class="form-group">
                        <label>Contra Account <span class="required">*</span></label>
                        <select name="contra_account_id" id="txn_contra_account" class="select2" style="width:100%;" required>
                            <option value="">-- Select Account --</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" data-account-id="{{ $account->id }}">{{ $account->name }} ({{ $account->code }})</option>
                            @endforeach
                        </select>
                        <p style="font-size:0.72rem;color:#94a3b8;margin-top:4px;">This account's own ledger account is hidden here — picking it would cancel itself out.</p>
                    </div>
                    <div class="form-group">
                        <label>Reference No.</label>
                        <input type="text" name="reference" id="txn_reference" placeholder="Optional">
                    </div>
                </div>
                {{-- Two boxes, not one remark: the name goes to its own
                     column on every table and print, the purpose becomes the
                     tail of the description ("Deposit to X — purpose"). --}}
                <div class="txn-section">Who &amp; what for</div>
                <div class="txn-pair">
                    <div class="form-group">
                        <label>Beneficiary / Person Name</label>
                        <select id="txn_beneficiary_party" style="width:100%;">
                            <option value=""></option>
                            @foreach(['Agent', 'Vendor', 'Customer'] as $kind)
                                <optgroup label="{{ $kind }}s">
                                    @foreach($beneficiaryParties->where('kind', $kind) as $party)
                                        <option value="{{ $party['id'] }}" data-name="{{ $party['name'] }}" data-display-id="{{ $party['display_id'] }}" data-party-kind="{{ strtolower($kind) }}">{{ $party['name'] }} — {{ $party['display_id'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <input type="hidden" id="txn_beneficiary">
                        <small id="txn_beneficiary_id" style="display:block;color:#94a3b8;margin-top:3px;"></small>
                    </div>
                    <div class="form-group">
                        <label>Purpose</label>
                        <select name="purpose" id="txn_purpose" style="width:100%;">
                            <option value=""></option>
                            <option value="Ticket Payment">Ticket Payment</option>
                            <option value="Flight Payment">Flight Payment</option>
                            <option value="Visa Payment">Visa Payment</option>
                            <option value="File Payment">File Payment</option>
                            <option value="Others">Others</option>
                        </select>
                        <small style="display:block;color:#94a3b8;margin-top:4px;">Choose the reason for this transaction.</small>
                    </div>
                    <div class="form-group" id="txnCustomPurposeWrap" hidden>
                        <label for="txn_custom_purpose">Specify purpose <span class="required">*</span></label>
                        <input type="text" id="txn_custom_purpose" maxlength="255" placeholder="Type the transaction purpose">
                        <small style="display:block;color:#94a3b8;margin-top:4px;">Required when Purpose is Others.</small>
                    </div>
                </div>
                <div class="form-group" id="txnPartyActionWrap" hidden>
                    <label>Party Transaction Type <span class="required">*</span></label>
                    <select id="txn_party_action" style="width:100%;"></select>
                    <p id="txnPartyActionHelp" style="font-size:0.72rem;color:#64748b;margin-top:4px;"></p>
                </div>
                <div class="form-group" id="txnInvoiceAllocationWrap" hidden>
                    <label>Payment Allocation</label>
                    <select id="txn_allocation_mode" style="width:100%;">
                        <option value="unallocated" selected>Advance / Unallocated Payment</option>
                        <option value="auto">Auto Allocate — Oldest First</option>
                        <option value="manual">Manual Allocation</option>
                    </select>
                    <div id="txnAllocationTable" style="margin-top:8px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;"></div>
                    <p id="txnInvoiceAllocationHelp" style="font-size:0.72rem;color:#94a3b8;margin-top:4px;">Only unpaid invoices belonging to the selected party are shown.</p>
                </div>

                {{-- How the other side paid or was paid. Cash needs nothing
                     more; a bank or a cheque asks for the bank and the
                     account / cheque number, which then sit on one line under
                     the name in the Beneficiary column ("ABCD BANK: 1234567"),
                     the way our account number sits under the bank. --}}
                <div class="txn-via">
                    <div class="form-group">
                        <label id="txnViaLabel">Received via</label>
                        <select name="pay_method" id="txn_pay_method">
                            <option value="">— Not recorded —</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="form-group" id="txnPayBankWrap" hidden>
                        <label>Their bank</label>
                        <input type="text" name="pay_bank" id="txn_pay_bank" maxlength="150" placeholder="e.g. DUTCH BANGLA BANK" autocomplete="off">
                    </div>
                    <div class="form-group" id="txnPayRefWrap" hidden>
                        <label id="txnPayRefLabel">Account no.</label>
                        <input type="text" name="pay_ref" id="txn_pay_ref" maxlength="100" placeholder="Account number" autocomplete="off">
                    </div>
                </div>

                {{-- WHAT THE BANK TAKES FOR ITSELF (owner, 2026-08-23).

                     A withdrawal rarely costs only the withdrawal. The bank helps
                     itself to a service charge and the VAT on that charge, and
                     until now there was nowhere to say so — the entry balanced by
                     pretending the fee did not happen, and the account drifted
                     from its real statement by exactly the fee.

                     Both are optional and both start empty: leave them alone and
                     this form posts the same two-leg entry it always has.

                     Each carries its OWN account rather than a hardcoded one.
                     A service charge is an expense (8510 Bank Charges, offered
                     below as the default), but VAT is only an expense to a
                     company that cannot reclaim it — one that can posts it to
                     VAT Rebate / Current Account instead. That is the
                     accountant's call per transaction, not a decision to bake
                     into the code. --}}
                @php
                    // firstWhere, not a query: $accounts is already loaded, and a
                    // chart with no 8510 simply opens with nothing preselected
                    // rather than erroring.
                    $defaultChargeAccount = $accounts->firstWhere('code', '8510');
                @endphp
                <div class="txn-cut">
                    <div class="txn-cut-head">Bank charge &amp; VAT <span>optional — deducted from this account</span></div>

                    <div class="txn-cut-grid">
                    <div class="txn-cut-row">
                        <div class="form-group">
                            <label>Bank Charge</label>
                            <input type="number" name="charge_amount" id="txn_charge_amount" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Charge posts to</label>
                            <select name="charge_account_id" id="txn_charge_account">
                                <option value="">-- Select Account --</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ $defaultChargeAccount && $defaultChargeAccount->id === $account->id ? 'selected' : '' }}>{{ $account->name }} ({{ $account->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="txn-cut-row">
                        <div class="form-group">
                            <label>VAT</label>
                            <input type="number" name="vat_amount" id="txn_vat_amount" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>VAT posts to</label>
                            <select name="vat_account_id" id="txn_vat_account">
                                <option value="">-- Select Account --</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ $defaultChargeAccount && $defaultChargeAccount->id === $account->id ? 'selected' : '' }}>{{ $account->name }} ({{ $account->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    </div>

                    {{-- The figure the account actually moves by. The bank leg is
                         posted net, so this is the number that will appear on the
                         statement — say it here rather than letting it be a
                         surprise afterwards. --}}
                    <div class="txn-cut-total" id="txnCutTotal" hidden></div>
                </div>


            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeTxnModal()">Cancel</button>
                <button type="submit" class="btn-submit" id="txnSubmitBtn">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const SEARCH_URL      = "{{ route('role.banks.search', ['role' => $roleSlug]) }}";
const LOAD_URL        = "{{ route('role.banks.load',   ['role' => $roleSlug]) }}";
const STATEMENT_PDF_BASE = "{{ url($roleSlug . '/banks') }}";
const TXN_BASE         = "{{ url($roleSlug . '/banks') }}";
const BANK_UNPAID_INVOICES_URL = @json(route('role.party-statement.unpaid-invoices', ['role' => $roleSlug]));
const BANK_AR_ACCOUNT_ID = @json(optional($accounts->firstWhere('code', config('accounts.accounts_receivable')))->id);
const BANK_AP_ACCOUNT_ID = @json(optional($accounts->firstWhere('code', config('accounts.accounts_payable')))->id);
const BANK_CUSTOMER_ADVANCE_ACCOUNT_ID = @json(optional($accounts->firstWhere('code', config('accounts.customer_advance')))->id);
const BANK_SUPPLIER_ADVANCE_ACCOUNT_ID = @json(optional($accounts->firstWhere('code', config('accounts.supplier_advance')))->id);
const REVERSE_BASE      = "{{ url($roleSlug . '/banks/journal') }}";
const CSRF              = "{{ csrf_token() }}";
const BANK_LOGO_BASE     = "{{ asset('bank-logos') }}";
// company name -> rank, straight from companies.order. Drives the card grid's
// "sort by company" toggle; see CARD_SORTS.
const COMPANY_ORDER      = @json($companyOrder ?? []);
const DASHBOARD_DATA_URL = "{{ route('role.banks.dashboard.data', ['role' => $roleSlug]) }}";

// Where a beneficiary's name and code lead: that party's own ledger, opened on
// arrival by Party Statement's ?party_id= deep link.
//
// Route::has() rather than a bare route(): if the name is ever missing — a
// stale route cache, a half-finished deploy — this must fall back to the plain
// text the cell printed before, not throw while rendering the bank dashboard.
const PARTY_STATEMENT_URL = @json(\Illuminate\Support\Facades\Route::has('role.party-statement.index')
    ? route('role.party-statement.index', ['role' => $roleSlug])
    : null);
const OWNER_LEDGER_URL = @json(route('role.financing.index', ['role' => $roleSlug, 'book' => 'capital']));
const EMPLOYEE_LEDGER_BASE = @json(url($roleSlug . '/payroll/employee-ledger'));

// `let`, not `const`: refreshDashboardInPlace() swaps the whole array after a
// cash entry so the banner, cards and activity feed re-render from fresh server
// figures without a page load.
let COMPANY_DATA = @json($companiesData);
const BENEFICIARY_PARTIES = @json($beneficiaryParties);
const BENEFICIARY_PARTY_BY_ID = Object.fromEntries(BENEFICIARY_PARTIES.map(p => [String(p.id), p]));
const BENEFICIARY_OWNERS = @json($beneficiaryOwners);
const BENEFICIARY_OWNER_BY_ID = Object.fromEntries(BENEFICIARY_OWNERS.map(p => [String(p.id), p]));
const BENEFICIARY_EMPLOYEES = @json($beneficiaryEmployees ?? []);
const BENEFICIARY_EMPLOYEE_BY_ID = Object.fromEntries(BENEFICIARY_EMPLOYEES.map(p => [String(p.id), p]));
let activeCompanyName = null;

const typeLabels = {
    bank:            { deposit: 'Deposit',  withdraw: 'Withdraw' },
    mobile_banking:  { deposit: 'Cash In',  withdraw: 'Cashout' },
    digital_wallet:  { deposit: 'Cash In',  withdraw: 'Cashout' },
    cash:            { deposit: 'Cash In',  withdraw: 'Cash Out' },
};
const typeIcons = {
    bank:            'fa-university',
    mobile_banking:  'fa-mobile-alt',
    digital_wallet:  'fa-wallet',
    cash:            'fa-money-bill-wave',
};

let selectedBank  = null;
let searchTimer   = null;

// ── Formatters ───────────────────────────────────────────────────────────────
function fmtMoney(v) { return Number(Math.abs(v)).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtDate(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); }
// "2026-08-20" -> "20/08/2026", the format every date column on this page reads
// in. String surgery, never new Date(): a bare Y-m-d is parsed as UTC, so a Date
// would print the day before for anyone in a negative offset.
function fmtDMY(d) {
    const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : (d || '');
}
// "2026-08-18 13:54:47" -> "1:54 PM". Read straight off the string rather than
// through Date(), which would treat the server's local timestamp as UTC in some
// browsers and shift the clock by the offset.
function fmtClock(ts) {
    const m = String(ts || '').match(/(\d{2}):(\d{2})/);
    if (!m) return '';
    let h = parseInt(m[1], 10);
    const suffix = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${m[2]} ${suffix}`;
}
// Only the deliberately entered transaction time is displayed. created_at is
// server audit metadata and is never presented as the bank movement's time.
function customTimeLine(entryTime) {
    return entryTime
        ? `<div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;">${fmtClock(entryTime)}</div>`
        : '';
}

// ── Date preset ──────────────────────────────────────────────────────────────
function resolveDates() {
    const preset = document.getElementById('datePreset').value;
    const now = new Date();
    let from = '', to = now.toISOString().split('T')[0];
    if      (preset === 'this_year')  from = now.getFullYear() + '-01-01';
    else if (preset === 'this_month') from = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-01';
    else if (preset === 'last_3')     { const d = new Date(now); d.setMonth(d.getMonth()-3); from = d.toISOString().split('T')[0]; }
    else if (preset === 'last_6')     { const d = new Date(now); d.setMonth(d.getMonth()-6); from = d.toISOString().split('T')[0]; }
    else if (preset === 'all_time')   { from = ''; to = ''; }
    else { from = document.getElementById('dateFrom').value; to = document.getElementById('dateTo').value; }
    return { from, to };
}
document.getElementById('datePreset').addEventListener('change', function () {
    const isCustom = this.value === 'custom';
    document.getElementById('dateFrom').style.display = isCustom ? 'inline-block' : 'none';
    document.getElementById('dateTo').style.display   = isCustom ? 'inline-block' : 'none';
});

// ── Company cards drive the whole landing view ────────────────────────────────
// preserveLedger: re-render this company's figures but leave whatever ledger is
// open exactly as it is. Used by the in-place refresh after a cash entry —
// switching companies should still close the ledger, but merely restating the
// same company's numbers must not, or every entry would shut the statement the
// user is working in.
function selectCompany(companyName, preserveLedger = false) {
    const company = COMPANY_DATA.find(c => c.name === companyName);
    if (!company) return;

    activeCompanyName = companyName;
    sessionStorage.setItem('activeBankCompany', companyName);

    document.querySelectorAll('.company-pick-card').forEach(el => {
        el.classList.toggle('is-active', el.dataset.companyName === companyName);
    });

    document.getElementById('bannerBalanceStat').textContent = (company.total_balance < 0 ? '-৳' : '৳') + fmtMoney(company.total_balance);
    document.getElementById('bannerBalanceLabel').textContent = companyName + ' Balance';
    document.getElementById('bannerAccountsStat').textContent = company.account_count;

    // The company name moved out of the title and into its own zone on the
    // header card, beside the badge — the same place the statement prints it.
    document.getElementById('caCompanyName').textContent = companyName;

    // Dr / Cr rather than a bare minus, the same tag the statement's balances
    // and every balance in the table below carry: a bank account is an asset, so
    // money in it is a debit balance and an overdraft is a credit one.
    const bal = Number(company.total_balance) || 0;
    document.getElementById('caBalance').innerHTML =
        (bal < 0 ? '−' : '') + '<span class="cur">৳</span>' + fmtMoney(bal)
        + '<span class="tag">' + (bal < 0 ? 'Cr' : 'Dr') + '</span>';
    document.getElementById('recentTxnSubtitle').textContent =
        'Last 14 days · ' + company.account_count + ' account' + (company.account_count === 1 ? '' : 's') + '. Click any row to open its full ledger.';

    // Net change is now a cell in the figures rail, so it is inked with the
    // rail's own classes instead of an inline colour: green up, red down, grey
    // for a fortnight that netted to nothing. .is-close (its right-hand anchor
    // slot) tints amber by default and every one of these is declared after it,
    // so whichever lands here wins.
    const net = Number(company.net_change) || 0;
    const netStat = document.getElementById('netChangeStat');
    netStat.classList.toggle('is-in',   net > 0);
    netStat.classList.toggle('is-out',  net < 0);
    netStat.classList.toggle('is-zero', net === 0);
    document.getElementById('netChangeVal').innerHTML =
        (net > 0 ? '+' : net < 0 ? '−' : '') + '<span class="cur">৳</span>' + fmtMoney(net);

    // Ticks belong to one company's feed; switching drops them, restating
    // the same company (a cash entry refreshing the page) keeps them.
    if (!preserveLedger) { CA_PICKS.clear(); PICKING.ca = false; const h = pickingHost('ca'); if (h) h.classList.remove('is-picking'); }
    renderRecentTransactions(company.transactions);

    document.getElementById('bankCardsCompanyName').textContent = companyName + ' Accounts';
    renderBankCards(company.banks);
    document.getElementById('companyPicker').style.display = 'none';
    document.getElementById('companyBankCards').style.display = 'block';

    // Switching companies means whatever bank ledger was open no longer applies.
    if (!preserveLedger) {
        document.getElementById('ledgerArea').style.display = 'none';
        document.getElementById('placeholderArea').style.display = 'block';
    }
}

// ── Refresh after a cash entry, without a page load ──────────────────────────
// Re-pulls the same payload the page was rendered from and re-runs the existing
// render functions, so the banner, bank cards and activity feed show real server
// figures rather than numbers patched arithmetically on the client. The open
// ledger reloads too, and the scroll position is restored around the re-render.
//
// Falls back to a reload if the fetch fails — but remembers where the user was
// first, so even the fallback comes back to the same account and scroll offset.
function refreshDashboardInPlace() {
    const scrollY = window.scrollY;
    const openBankId = selectedBank ? selectedBank.id : null;
    const openLedgerPage = LEDGER_PAGE;

    return fetch(DASHBOARD_DATA_URL, { headers: { 'Accept': 'application/json' } })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            if (!data || !Array.isArray(data.companies)) return Promise.reject();

            COMPANY_DATA = data.companies;

            // The active company may have been renamed away or dropped; fall
            // back rather than leaving the page showing stale figures.
            const stillThere = COMPANY_DATA.find(c => c.name === activeCompanyName);
            selectCompany(stillThere ? activeCompanyName : (COMPANY_DATA[0] || {}).name, true);

            // Re-point selectedBank at the refreshed card so its balance and
            // last-transaction date are current, then restate the ledger.
            if (openBankId) {
                const company = COMPANY_DATA.find(c => c.name === activeCompanyName);
                const fresh = (company ? company.banks : []).find(b => b.id === openBankId);
                if (fresh) selectedBank = Object.assign({}, selectedBank, fresh);
                return loadLedger({ preservePage: true, page: openLedgerPage, preserveScroll: true })
                    .then(() => window.scrollTo({ top: scrollY }));
            }

            window.scrollTo({ top: scrollY });
        })
        .catch(() => {
            rememberPosition();
            window.location.reload();
        });
}

// ── Position memory, for the reloads that remain ─────────────────────────────
// Reversals and the refresh fallback still reload. Storing the open account and
// scroll offset means the page comes back where it was instead of at the top
// with the ledger closed. sessionStorage, so it dies with the tab.
function rememberPosition() {
    try {
        sessionStorage.setItem('bankDashPos', JSON.stringify({
            bankId: selectedBank ? selectedBank.id : null,
            company: activeCompanyName,
            scrollY: window.scrollY,
        }));
    } catch (e) { /* private mode — losing the position is not worth an error */ }
}

function restorePosition() {
    let pos = null;
    try {
        const raw = sessionStorage.getItem('bankDashPos');
        sessionStorage.removeItem('bankDashPos');
        if (raw) pos = JSON.parse(raw);
    } catch (e) { return; }
    if (!pos) return;

    if (pos.bankId) {
        const company = COMPANY_DATA.find(c => c.name === (pos.company || activeCompanyName));
        const bank = (company ? company.banks : []).find(b => b.id === pos.bankId);
        // Re-open the ledger first, then scroll — the ledger area is display:none
        // until it loads, so scrolling before it exists would land short.
        if (bank) {
            selectBank(bank);
            loadLedger();
        }
    }

    if (pos.scrollY) {
        // Two frames: one for the card/ledger DOM to be laid out, one for the
        // browser to have a scrollable height to honour.
        requestAnimationFrame(() => requestAnimationFrame(() => window.scrollTo({ top: pos.scrollY })));
    }
}

function showCompanyPicker() {
    document.getElementById('companyBankCards').style.display = 'none';
    document.getElementById('companyPicker').style.display = 'grid';
}

// One account, at a glance — three text rows and a hairline:
//
//   EPAL IT SOLUTIONS ···················· ▲ ৳12.4k   ← whose it is · 14-day net
//   [▤] BRACK BANK (IT)                               ← the account
//       EPAL IT SOLUTIONS · Shantinagar
//   ৳187.38 ······················· ••••4821 · 2d ago ← balance · A/C · last move
//   ▔▔▔▔▔▔▔▔▔ share of the money in this view ▔▔▔▔▔▔▔
//
// `share` is 0..1 and is handed in by renderBankCards, because a single card
// cannot know what it is a share OF. Exact figures live in the tooltips: at
// 9.5px the chip has to be scannable, not precise.
// Which logo and which colour a bank name earns.
//
// Matched on the NAME, not on an id, because that is the only thing the accounts
// share: the same bank is filed as "DUTCH BANGLA (Travels)", "DUTCH BANGLA (IT)"
// and "DUTCH BANGLA (Emi Agro)" — and BRAC is spelt "BRACK" on one of them, which
// /brac/ still catches. First match wins, so the specific patterns go first.
//
// Logo files live in public/bank-logos/<slug>.png. To add a bank: drop its mark
// in that folder and add one line here — no CSS, no template change. If the file
// is missing the plate falls back to initials on `color`, so a wrong guess here
// degrades to a tidy monogram rather than a broken image.
// Cash pots are matched against their OWN table, never against the bank one —
// an account called "OFFICE CASH (CITY)" must not come out wearing City Bank's
// logo. Same contract as BANK_BRANDS: drop <slug>.png in public/bank-logos/ and
// add one line. A missing file degrades to the wallet/safe glyph, not a broken
// image, so an entry can be added before its artwork exists.
const CASH_BRANDS = [
    { slug: 'petty-cash',  color: '#dc2626', re: /petty\s*-?\s*cash/i },
    { slug: 'office-cash', color: '#475569', re: /office\s*-?\s*cash/i },
];

const BANK_BRANDS = [
    { slug: 'dutch-bangla', color: '#005CA9', re: /dutch\s*-?\s*bangla|\bdbbl\b/i },
    { slug: 'islami',       color: '#006A4E', re: /islami/i },
    { slug: 'brac',         color: '#006CB5', re: /brac/i },
    { slug: 'city',         color: '#ED1C24', re: /city\s*bank/i },
    { slug: 'dhaka',        color: '#0B4EA2', re: /dhaka\s*bank/i },
    { slug: 'bkash',        color: '#E2136E', re: /b\s*-?\s*kash/i },
    // The group's Visa card, which is issued on Dhaka Bank but is filed under a
    // name that says the instrument rather than the issuer: "CREDIT CARD (BOSS)".
    // Last on purpose. First match wins, so a card that DOES name its bank —
    // "CITY BANK CREDIT CARD" — still comes out wearing that bank's own mark, and
    // only a card with no issuer in its name falls through to this one.
    { slug: 'visa-dhaka',   color: '#0B4EA2', re: /credit\s*-?\s*card/i },
];

// Colours for everything BANK_BRANDS does not know, so an unbranded account
// still gets a plate that looks deliberate.
const TYPE_COLORS = { bank: '#4f46e5', mobile_banking: '#db2777', digital_wallet: '#db2777', cash: '#ea580c' };

function bankBrand(bank) {
    const name = String(bank.name || '');
    // Which table, never both: the cash/bank split is what stops a cash pot from
    // matching a bank's name pattern and vice versa.
    const table = bank.type === 'cash' ? CASH_BRANDS : BANK_BRANDS;
    const hit = table.find(b => b.re.test(name));
    if (hit) return { slug: hit.slug, color: hit.color };
    return { slug: null, color: TYPE_COLORS[bank.type] || TYPE_COLORS.bank };
}

// "DUTCH BANGLA (Emi Agro)" is really two facts: which bank, and which of this
// group's accounts at it. The logo says the first, so the card prints the second
// on its own line instead of repeating the bank's name in full.
//   "DUTCH BANGLA (Emi Agro)" -> DUTCH BANGLA + Emi Agro
//   "THE CITY BANK- Travels"  -> CITY BANK    + Travels
//   "OFFICE CASH"             -> OFFICE CASH  + (nothing)
function splitBankName(name) {
    const n = String(name || '').trim();
    const paren = n.match(/^(.*?)\s*\(([^)]+)\)\s*$/);
    if (paren) return { brand: tidyBankName(paren[1]), label: paren[2].trim() };
    const dash = n.match(/^(.*?)\s*[-–]\s+(.+)$/);
    if (dash) return { brand: tidyBankName(dash[1]), label: dash[2].trim() };
    return { brand: tidyBankName(n), label: '' };
}

// Legal-form noise, dropped from the name printed on the card.
//
// The card gives the name about 90px, which is twelve characters — and "DHAKA
// BANK PLC" and "THE CITY BANK" both spend three or four of them on words
// nobody says out loud. Trimming them is what lets those two names print in
// full instead of as "DHAKA BA…" and "THE CITY BA…". The registered name is
// unchanged in the database, in the card's own hover title, and everywhere
// else on the page.
//
// Falls back to the original if trimming would leave nothing, so an account
// literally named "Ltd" still shows something.
function tidyBankName(s) {
    const n = String(s || '').trim();
    const cut = n.replace(/^the\s+/i, '').replace(/[\s,]+(?:plc|ltd|limited)\.?$/i, '').trim();
    return cut || n;
}

function initials(name) {
    return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2)
        .map(w => w[0]).join('').toUpperCase() || '?';
}

// One account, in four bands (see .bank-acc-card in the sheet above):
//
//   [ logo ]  BRAC BANK
//             EPAL TRAVELS
//   A/C 2312091053 ·· Tejgaon · CURRENT
//   ৳2,580,239.00 BDT          ▲ ৳1.7L
//   ┌────────┬──────────┬───────────┐
//   │   28   │  ৳1.3L   │  ৳34,000  │
//   │  TXN   │ TODAY IN │ TODAY OUT │
//   └────────┴──────────┴───────────┘
//
// `share` is 0..1 and is handed in by renderBankCards, because a single card
// cannot know what it is a share OF. Exact figures live in the tooltips: at
// 8–11px the chips have to be scannable, not precise — and on a 200px card the
// tooltip is the only place a full figure fits.
function bankCardHtml(bank, share) {
    const brand   = bankBrand(bank);
    const parts   = splitBankName(bank.name);
    const balance = Number(bank.balance) || 0;

    // The plate: the real mark where we have one, initials on the brand colour
    // where we do not — and the same initials take over if the file ever 404s.
    // The concern's own glyph, riding the mark's top-right corner. Whitelisted
    // to a Bootstrap Icons class before it is written into markup — it arrives
    // as a string that ends up inside a `class` attribute, which is not a place
    // to trust an arbitrary value. Anything unexpected renders no badge rather
    // than something odd.
    const coGlyph = /^bi bi-[a-z0-9-]+$/.test(String(bank.company_glyph || ''))
        ? `<div class="bac-coico" title="${esc(bank.company_name || 'Company')}"><i class="${bank.company_glyph}"></i></div>`
        : '';

    const plate = brand.slug
        ? `<div class="bac-logo"><span class="bac-mono">${esc(initials(parts.brand))}</span>` +
          `<img src="${BANK_LOGO_BASE}/${brand.slug}.png" alt="${esc(parts.brand)}" loading="lazy"` +
          ` onerror="this.parentNode.classList.add('is-mono')">${coGlyph}</div>`
        : `<div class="bac-logo is-mono"><span class="bac-mono">${esc(initials(parts.brand))}</span>${coGlyph}</div>`;

    // The dashboard query only returns active banks, so this normally renders
    // nothing — it exists so an inactive one could never pass as live.
    const dot = bank.status === false ? '<span class="bac-dot" title="Inactive account"></span>' : '';

    const flowIn  = Number(bank.flow_in)    || 0;
    const flowOut = Number(bank.flow_out)   || 0;
    const flowN   = Number(bank.flow_count) || 0;
    const flowNet = flowIn - flowOut;
    let flowCls = 'idle', flowTxt = 'quiet';
    if (flowN && flowNet > 0)      { flowCls = 'up';   flowTxt = '▲' + fmtShort(flowNet, false); }
    else if (flowN && flowNet < 0) { flowCls = 'down'; flowTxt = '▼' + fmtShort(flowNet, false); }
    else if (flowN)                { flowTxt = '±0'; }
    // Kept raw as well as escaped: an overdrawn card drops the pill and folds
    // this into the age's tooltip instead, and escaping it twice would leave
    // "&amp;amp;" in an account name that has an ampersand.
    const flowText  = flowN
        ? `last 14 days · in ৳${fmtMoney(flowIn)} · out ৳${fmtMoney(flowOut)} · ${flowN} entr${flowN === 1 ? 'y' : 'ies'}`
        : 'nothing booked here in the last 14 days';
    const flowTitle = esc(flowText);

    // BAND 1 — who. Which account at this bank, whose it is, and which branch.
    // "PETTY CASH (GROUP)" filed under EPAL GROUP would otherwise read
    // "GROUP · EPAL GROUP". Where the account's own label is already a word in
    // the owner's name, the owner alone says it. Word-boundary matched, so the
    // "IT" account keeps its label under a company called CAPITAL TRUST.
    //
    // The concern's SHORT name where it has one — at 11.4px bold in a 125px
    // column, "EPAL TRAVELS & CONSULTANCY" clips mid-word while its own
    // short_name, "EPAL TRAVELS", does not. Falls back to the full name, and
    // the full name is in the line's tooltip either way.
    const owner = bank.company_short || bank.section || bank.account_name || '';
    const bits  = [parts.label, owner].filter(Boolean);
    if (bits.length === 2 &&
        new RegExp('\\b' + bits[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i').test(bits[1])) {
        bits.shift();
    }
    // Still two things on one line? Then the house prefix goes, because at 11.4px
    // bold "Emi Agro · EPAL GROUP" runs about five pixels past the column and
    // clips to "EPAL GR…" — and "EPAL" is on all but one of the concerns, so it
    // is the one word on that line carrying no information.
    //
    // Only when the line is carrying two things. A company standing alone keeps
    // its name in full, which is what stops the IT house's card from reading
    // just "IT". The full name stays in the line's tooltip either way.
    if (bits.length === 2) {
        bits[1] = bits[1].replace(/^epal\s+/i, '').trim() || bits[1];
    }
    const sub      = esc(bits.join(' · '));
    const subTitle = esc([bank.account_name, bank.branch_name, bank.section].filter(Boolean).join(' · '));

    // The branch rides on the A/C line rather than up here, because that is the
    // pairing a cheque book prints and the pairing someone cross-checking a
    // deposit slip reads together. Up here it made a third bullet on a line
    // already carrying the account label and the owning company, and all three
    // ellipsised. Dropped when it only repeats what the line above already says.
    // Dropped again on the long-account-number accounts (Islami Bank's are 17
    // digits): flex could shrink the branch away, but only after the number had
    // already given up characters of its own, and a half-printed account number
    // is worse than no branch. The cut-off is a count, not a flex rule, so the
    // outcome is the same on every screen width.
    const branch = String(bank.branch_name || '').trim();
    const showBranch = branch
        && !bits.some(b => b.toLowerCase() === branch.toLowerCase())
        && String(bank.account_number || '').length <= 13;

    // BAND 2 — which account. Printed in full, as sketched: this page is behind
    // a login and the number is what someone cross-checking a deposit slip has
    // in front of them. The dotted leader runs from it to the branch.
    const acct     = bank.account_number ? String(bank.account_number) : '—';
    const acctTyp  = String(bank.account_type || '').trim();
    const acctitle = esc([
        bank.account_number ? 'A/C ' + bank.account_number : 'no account number on file',
        acctTyp ? acctTyp + ' account' : '',
        branch ? branch + ' branch' : '',
    ].filter(Boolean).join(' · '));

    // BAND 3 — how much, and when it last moved.
    //
    // last_movement is the newest journal entry actually posted to this account.
    // The banks.last_transaction_date column is only the fallback: it is written
    // by the bank forms alone, so an account fed by journals or transfers still
    // reads "never used" there while its ledger fills up.
    // The age is abbreviated on the card face — "1d" not "yesterday" — because
    // this sits on the balance line and every character it takes is a character
    // the balance may not have. The unabbreviated form and the real date are in
    // the tooltip.
    const moved     = bank.last_movement || bank.last_transaction_date;
    const whenTxt   = moved ? lastMoved(moved).replace(/^yesterday$/, '1d').replace(/ ago$/, '') : '—';

    // Is there room on the balance's line for anything beside it?
    //
    // Both states ask this and both answer it by counting, not by letting flex
    // shrink something: flex would hand back two pixels and render "1" — a "1d"
    // with its d sheared off — or a "BD" with its T gone. A line that cannot
    // hold all of its items is better holding fewer whole ones.
    //   full card  the age chip beside the balance
    //   brief      the BDT suffix after it
    // Ten characters is where ৳1,815,281.00 stops fitting in the brief's 116px
    // column, measured.
    const shortBalance = fmtMoney(balance).length <= 10;
    const showWhen     = shortBalance;
    // The trend pill is dropped from an overdrawn card: the OVERDRAWN chip
    // takes that end of the line, and the two together overflowed and got
    // sheared off by the card's clip. An account in the red is not asking to be
    // read for its fortnight's drift, so the pill's figures fold into the age's
    // tooltip and nothing is lost.
    const whenTitle = esc([
        moved ? `last movement ${fmtDate(moved)} (${lastMoved(moved)})` : 'no movement on record',
        balance < 0 ? flowText : '',
    ].filter(Boolean).join(' · '));

    // BAND 4 — activity. Lifetime entry count, then the day's two figures. A day
    // with nothing on it prints ৳0 greyed rather than going blank, so the three
    // cells always line up across the grid and an empty day is legible as one.
    const txnN     = Number(bank.txn_count)  || 0;
    const todayIn  = Number(bank.today_in)   || 0;
    const todayOut = Number(bank.today_out)  || 0;

    // The owning company's colour, prepared server-side (companyRail()). An
    // older payload, or a bank whose company row has gone, simply leaves the
    // sheet's neutral slate in place — a card must never render colourless
    // because one field is missing.
    const co = /^#[0-9a-f]{6}$/i.test(String(bank.company_ink || ''))
        ? `--bac-co:${bank.company_ink};--bac-co-rgb:${esc(bank.company_ink_rgb)};`
        : '';

    // Names the company outright, because the rail only says its colour — and
    // carries this account's share of the money on screen, which used to be a
    // meter along the card's top edge and looked like a fault (see the note
    // where .bac-share used to be defined).
    const pctOfView = Math.max(0, Math.min(1, Number(share) || 0)) * 100;
    const cardTitle = esc([
        bank.company_name,
        `open ${bank.name}'s full ledger`,
        `${pctOfView.toFixed(1)}% of the balance shown here`,
    ].filter(Boolean).join(' · '));

    // The brief prints the concern ABOVE the bank, which is the opposite of the
    // full card's head. Deliberate, and the owner's sketch: at rest you are
    // asking "whose money, and how much" and only then which bank it is at;
    // opened, you are already inside one account and the bank leads.
    //
    // `plate` is the same string in both states and lands at the same offset,
    // so the mark is the one thing that does not move as the card opens — the
    // change reads as detail arriving around it rather than a card swapping.
    const brief = `<div class="bac-brief"><div class="bac-brief-in"><div class="bac-brief-row">
            ${plate}
            <div class="bb-id">
                <div class="bb-co" title="${esc(bank.company_name || '')}">${esc(owner) || '&nbsp;'}</div>
                <div class="bb-name">${dot}${esc(parts.brand)}</div>
                <div class="bb-bal">${balance < 0 ? '-' : ''}৳${fmtMoney(balance)}${shortBalance ? '<span class="cur">BDT</span>' : ''}</div>
            </div>
        </div></div></div>`;

    return `<div class="bank-acc-card type-${bank.type}${balance < 0 ? ' is-overdrawn' : ''}" style="--bac-tint:${brand.color};${co}" title="${cardTitle}" onclick='openBankFromRow(${JSON.stringify(bank)})'>
        ${brief}
        <div class="bac-full"><div class="bac-full-in">
        <div class="bac-head">
            ${plate}
            <div class="bac-id">
                <div class="name">${dot}${esc(parts.brand)}</div>
                <div class="sub" title="${subTitle}">${sub || '&nbsp;'}</div>
            </div>
        </div>
        <div class="bac-acct" title="${acctitle}">
            <span class="k">A/C</span>
            <span class="v">${esc(acct)}</span>
            <span class="lead"></span>
            ${showBranch ? `<span class="br">${esc(branch)}</span>` : ''}
        </div>
        <div class="bac-bal">
            ${balanceHtml(bank.balance)}
            ${showWhen ? `<div class="bac-when" title="${whenTitle}">${esc(whenTxt)}</div>` : ''}
            ${balance < 0 ? '' : `<div class="bac-flow ${flowCls}" title="${flowTitle}">${flowTxt}</div>`}
        </div>
        <div class="bac-strip">
            <div class="cell${txnN ? '' : ' is-zero'}" title="${txnN} ledger entr${txnN === 1 ? 'y' : 'ies'} on this account, all time">
                <div class="cv">${txnN.toLocaleString('en-US')}</div><div class="cl">TXN</div>
            </div>
            <div class="cell in${todayIn ? '' : ' is-zero'}" title="Received today: ৳${fmtMoney(todayIn)}">
                <div class="cv">৳${fmtShortPlain(todayIn)}</div><div class="cl">Today In</div>
            </div>
            <div class="cell out${todayOut ? '' : ' is-zero'}" title="Paid out today: ৳${fmtMoney(todayOut)}">
                <div class="cv">৳${fmtShortPlain(todayOut)}</div><div class="cl">Today Out</div>
            </div>
        </div>
        </div></div>
    </div>`;
}

// One stray double quote in an account name would close a title="" attribute
// early and take the rest of the card's markup with it, so everything
// user-entered goes through here — as text and as attribute value alike.
function esc(v) {
    return String(v === null || v === undefined ? '' : v)
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Money for a 10px pill. Lakh and crore rather than millions, because that is how
// the figure gets read out loud in the office; the tooltip carries the exact
// number for anyone who needs the paisa.
// `symbol` is opt-out for the bank card's flow pill, which sits in a 200px row
// beside the bank's name: every account on this page is in taka, so the ৳ there
// buys nothing and costs the name four characters.
function fmtShort(v, symbol = true) {
    const n = Math.abs(Number(v) || 0);
    const s = symbol ? '৳' : '';
    if (n >= 1e7) return s + (n / 1e7).toFixed(n >= 1e8 ? 0 : 1) + 'Cr';
    if (n >= 1e5) return s + (n / 1e5).toFixed(n >= 1e6 ? 0 : 1) + 'L';
    if (n >= 1e3) return s + (n / 1e3).toFixed(n >= 1e4 ? 0 : 1) + 'k';
    return s + n.toFixed(0);
}

// Today's in/out for the activity strip. Unlike fmtShort() this keeps whole
// taka readable in full up to a lakh — a day's takings are usually four or five
// digits and "৳12k" would be throwing away the exact figure the strip exists to
// show. The ৳ is supplied by the caller, and the exact amount is in the tooltip.
function fmtShortPlain(v) {
    const n = Math.abs(Number(v) || 0);
    if (n >= 1e7) return (n / 1e7).toFixed(n >= 1e8 ? 0 : 1) + 'Cr';
    if (n >= 1e5) return (n / 1e5).toFixed(n >= 1e6 ? 0 : 1) + 'L';
    return Math.round(n).toLocaleString('en-US');
}

// "9d ago" answers the question a wall of cards is actually scanned for — which
// account has gone quiet — faster than "15 Aug" does. The real date is one hover
// away.
//
// Sliced to the date part and parsed as LOCAL midnight: `new Date('2026-08-15')`
// is parsed as UTC, which at UTC+6 lands on the 14th at 6pm and reports a day
// that has not happened yet as "1d ago".
function lastMoved(d) {
    if (!d) return 'never used';
    const then = new Date(String(d).slice(0, 10) + 'T00:00:00');
    if (isNaN(then.getTime())) return '';
    const now  = new Date();
    const days = Math.round((new Date(now.getFullYear(), now.getMonth(), now.getDate()) - then) / 86400000);
    if (days <= 0)  return 'today';
    if (days === 1) return 'yesterday';
    if (days < 30)  return days + 'd ago';
    if (days < 365) return Math.round(days / 30) + 'mo ago';
    return Math.round(days / 365) + 'y ago';
}

// An overdrawn account must not look like a healthy one.
//
// fmtMoney() runs Math.abs(), so a balance of -100 rendered as "৳100.00" — every
// other place on this page compensates by adding its own sign or Dr/Cr, but the
// card did not. Office Cash sat at -100.00 showing "৳100.00" on 2026-08-13 and
// read as perfectly fine.
function balanceHtml(v) {
    const n = Number(v) || 0;
    if (n < 0) {
        return `<div class="money"><span class="amt">-৳${fmtMoney(n)}</span><span class="bac-od">OVERDRAWN</span></div>`;
    }
    return `<div class="money"><span class="amt">৳${fmtMoney(n)}</span><span class="cur">BDT</span></div>`;
}

// Kinds of account, in the order the owner reads them (2026-08-19): "First
// will be all bKash cards, then Cash cards, then Group's, then…" — the wallets
// first, the cash tins next, the bank accounts last.
//
// The money you can move this minute comes first and the money that needs a
// banking day comes last, which is why this beats company as the primary sort:
// "how much cash can I lay hands on right now" is one glance across the top of
// the grid rather than a hunt through four company blocks.
//
// Anything unrecognised falls to the END rather than the front, so a new
// `type` cannot silently take over the top of the grid before anyone has
// decided where it belongs.
const KIND_RANK = { mobile_banking: 0, digital_wallet: 0, cash: 1, bank: 2 };

// What "same bank" means to the sorter: the brand slug where we recognise one,
// so "BRAC BANK (Travels)" and "BRACK BANK (IT)" — one of which is misspelt in
// the data — still sort next to each other. Unrecognised banks fall back to
// their own tidied name.
function sortKeyForBank(bank) {
    return bankBrand(bank).slug || splitBankName(bank.name).brand.toLowerCase();
}

// Which company a card belongs to. `section` is only set in the "Epal Group"
// umbrella, where cards from every concern share one grid; inside a single
// company it is absent and company_name stands in — so the comparator works in
// both views without knowing which one it is looking at.
function cardCompany(bank) {
    return String(bank.section || bank.company_name || '');
}

// Rank from the server's COMPANY_ORDER (companies.order). Anything unranked
// sorts last rather than first, so a newly added company cannot silently jump
// to the head of the grid.
function cardCompanyRank(bank) {
    const r = COMPANY_ORDER[cardCompany(bank)];
    return r === undefined ? 9999 : r;
}

// The two orders the toggle switches between. Both are the same four
// comparators re-prioritised — nothing is sorted on in one mode and ignored in
// the other, which is what keeps the two views feeling like the same grid.
//
//   bank (default)  kind, then brand, then company, then name.
//                   Every bKash together, then every cash tin, then the bank
//                   accounts with all of one brand side by side regardless of
//                   which concern owns them.
//   company         company, then kind, then brand, then name.
//                   One concern's accounts in a block, in the rail's own order.
const CARD_SORTS = {
    bank:    ['kind', 'brand', 'company', 'name'],
    company: ['company', 'kind', 'brand', 'name'],
};

const CARD_CMP = {
    kind:    (a, b) => (KIND_RANK[a.type] ?? 8) - (KIND_RANK[b.type] ?? 8),
    brand:   (a, b) => sortKeyForBank(a).localeCompare(sortKeyForBank(b)),
    company: (a, b) => cardCompanyRank(a) - cardCompanyRank(b)
                    || cardCompany(a).localeCompare(cardCompany(b)),
    name:    (a, b) => String(a.name || '').localeCompare(String(b.name || '')),
};

// Current order, and the last set of cards drawn — held so the toggle can
// re-sort without another round trip.
let CARD_SORT  = 'bank';
let CARD_BANKS = [];

// Card view mode — 'hover' (expand under the pointer), 'off' (stay compact,
// never expand) or 'all' (every card expanded at once, no pointer needed).
// A browser-local preference, not account data — it says nothing about the
// money, only how this one screen reacts to a mouse on this one machine, so
// localStorage is the right place for it rather than anything sent to the
// server.
const CARD_HOVER_MODES = ['hover', 'off', 'all'];
let CARD_HOVER_MODE = 'hover';
try {
    const saved = localStorage.getItem('bankCardHoverMode');
    if (CARD_HOVER_MODES.includes(saved)) {
        CARD_HOVER_MODE = saved;
    } else if (localStorage.getItem('bankCardHoverFx') === 'off') {
        // One-time migration of the original two-state toggle (on/off) this
        // replaced, so nobody who already switched it off loses that choice.
        CARD_HOVER_MODE = 'off';
    }
} catch (e) {}

function sortBankCards(banks) {
    const chain = (CARD_SORTS[CARD_SORT] || CARD_SORTS.bank).map(k => CARD_CMP[k]);
    return [...banks].sort((a, b) => {
        for (const cmp of chain) { const r = cmp(a, b); if (r) return r; }
        return 0;
    });
}

function paintCardSortBtn() {
    const btn = document.getElementById('cardSortBtn');
    const ico = document.getElementById('cardSortIcon');
    if (!btn || !ico) return;
    const byCompany = CARD_SORT === 'company';
    ico.className = byCompany ? 'fas fa-building' : 'fas fa-arrow-down-a-z';
    btn.classList.toggle('is-alt', byCompany);
    const label = byCompany
        ? 'Sorted by company — click to sort by bank'
        : 'Sorted by bank — click to sort by company';
    btn.title = label;
    btn.setAttribute('aria-label', label);
}

// Every hover rule in the stylesheet above is scoped under
// #bankCardsGrid:not(.no-hover-fx):not(.all-expanded) — toggling that one class on the grid is
// the whole mechanism. Nothing per-card, nothing to re-render.
const CARD_HOVER_ICONS = {
    hover: 'fas fa-expand',
    off:   'fas fa-compress',
    all:   'fas fa-table-cells-large',
};
const CARD_HOVER_LABELS = {
    hover: 'Cards expand on hover — click to keep them always compact',
    off:   'Cards always compact — click to expand every card at once',
    all:   'Every card expanded — click to go back to hover-to-expand',
};

function paintCardHoverBtn() {
    const btn  = document.getElementById('cardHoverBtn');
    const ico  = document.getElementById('cardHoverIcon');
    const grid = document.getElementById('bankCardsGrid');
    if (!btn || !ico) return;
    ico.className = CARD_HOVER_ICONS[CARD_HOVER_MODE] || CARD_HOVER_ICONS.hover;
    btn.classList.toggle('is-alt', CARD_HOVER_MODE !== 'hover');
    const label = CARD_HOVER_LABELS[CARD_HOVER_MODE] || CARD_HOVER_LABELS.hover;
    btn.title = label;
    btn.setAttribute('aria-label', label);
    if (grid) {
        grid.classList.toggle('no-hover-fx', CARD_HOVER_MODE === 'off');
        grid.classList.toggle('all-expanded', CARD_HOVER_MODE === 'all');
    }
}

function cycleCardHoverMode() {
    const next = CARD_HOVER_MODES[(CARD_HOVER_MODES.indexOf(CARD_HOVER_MODE) + 1) % CARD_HOVER_MODES.length];
    CARD_HOVER_MODE = next;
    try { localStorage.setItem('bankCardHoverMode', CARD_HOVER_MODE); } catch (e) {}
    paintCardHoverBtn();
}

function toggleCardSort() {
    CARD_SORT = CARD_SORT === 'bank' ? 'company' : 'bank';
    paintCardSortBtn();
    if (CARD_BANKS.length) renderBankCards(CARD_BANKS);
}

function renderBankCards(banks) {
    const grid = document.getElementById('bankCardsGrid');
    CARD_BANKS = banks;
    paintCardSortBtn();
    paintCardHoverBtn();
    if (!banks.length) {
        grid.innerHTML = '<div style="color:#94a3b8;font-size:0.85rem;padding:1rem;">No accounts under this company.</div>';
        return;
    }

    // Reading order, owner's rule (2026-08-19): brand beats company in the
    // default, so all of one bank's accounts sit together whoever owns them —
    // "First All Bkash, then All Cash, then all Brac Bank". The toggle beside
    // the title swaps to company-led. Both live in CARD_SORTS above.
    //
    // Still ONE continuous grid, not a grid per group: a 2-card block would
    // otherwise leave the rest of its row empty before the next one starts.
    const sorted = sortBankCards(banks);

    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = 'repeat(auto-fill,minmax(200px,1fr))';
    // Cards in a row must NOT stretch to the height of whichever one is open —
    // they stay their own height at the top of the row while it grows.
    // Rows are fixed at one brief tall, so an opening card can claim a second
    // one (grid-row: span 2) instead of stretching the row it shares with four
    // others — which is what left a band of white under every one of them.
    //
    // Mode "all" is the exception: an inline style always wins over the
    // #bankCardsGrid.all-expanded CSS rule (a class selector cannot outrank
    // it, !important games aside), so the fixed 85px has to be overridden
    // right here or every card would render full-height content squeezed
    // into a one-brief-tall row.
    grid.style.gridAutoRows = CARD_HOVER_MODE === 'all' ? 'auto' : '85px';
    grid.style.alignItems = 'start';
    grid.style.gap = '0.75rem';
    // Denominator for the share bar. Only positive balances count, so one
    // overdrawn account cannot shrink the pot and push every other card's share
    // over 100%.
    const pot = sorted.reduce((sum, b) => sum + Math.max(0, Number(b.balance) || 0), 0);
    grid.innerHTML = sorted
        .map(b => bankCardHtml(b, pot > 0 ? Math.max(0, Number(b.balance) || 0) / pot : 0))
        .join('');
    // Measured, not painted: see markLastRowCards. One frame later, because
    // selectCompany() fills this grid while it is still display:none and only
    // shows it on the next line — offsetTop is 0 for every card until then.
    requestAnimationFrame(markLastRowCards);
}

// Which cards are on the BOTTOM row, and where each of them sits — the two
// numbers the .bac-up hover rule needs to place a card into the cell above its
// own instead of claiming one below that does not exist.
//
// This cannot be a CSS rule. The columns come from auto-fill, so how many cards
// share the last row — and which column any card is in — changes with the window
// width and with the number of accounts the company has; :nth-last-child would
// be right at one width only. Nothing in the DOM records where a card landed,
// so the boxes themselves are the only source: cards sharing an offsetTop are a
// row, cards sharing an offsetLeft are a column, both 1-based to match grid
// lines.
//
// Read while nothing is hovered. An open card has already taken a second row
// and pushed the cards after it along, so the rows read mid-hover are not the
// rows the next hover will happen in.
//
// A single-row grid is left unmarked on purpose: there is no row above to move
// into, so those cards keep the ordinary downward open.
function markLastRowCards() {
    const grid = document.getElementById('bankCardsGrid');
    if (!grid) return;
    const cards = Array.from(grid.querySelectorAll('.bank-acc-card'));
    if (!cards.length) return;
    if (grid.querySelector('.bank-acc-card:hover')) return;

    // Strip the marks BEFORE measuring. .bac-up carries align-self:end, which
    // measures a card from the BOTTOM of its row — two cards of unequal height
    // in the same row would then report different offsetTops and stop reading as
    // one row. Cleared first, every card is flush with its row's top edge and
    // the grouping below is exact.
    cards.forEach(c => c.classList.remove('bac-up'));

    const uniq = v => [...new Set(v)].sort((a, b) => a - b);
    const rows = uniq(cards.map(c => c.offsetTop));
    const cols = uniq(cards.map(c => c.offsetLeft));
    if (rows.length < 2) return;
    const lastTop = rows[rows.length - 1];

    // A FULL bottom row keeps the ordinary downward open (owner, 2026-08-20).
    // Moving up needs one spare cell somewhere in the grid: the open card holds
    // two and gives back one. With the bottom row full there is no spare, so the
    // shuffle would push the last card onto a new row — the grid grows by a row
    // either way, and it grows more quietly by simply opening below than by
    // rearranging every card after the one you are pointing at.
    if (cards.filter(c => c.offsetTop === lastTop).length === cols.length) return;

    cards.forEach(c => {
        if (c.offsetTop !== lastTop) {
            c.style.removeProperty('--bac-col');
            c.style.removeProperty('--bac-row');
            return;
        }
        // Grid lines are 1-based, so the last row starts at line rows.length —
        // and the card opens from the line one above that, spanning back down
        // over its own cell.
        c.style.setProperty('--bac-col', String(cols.indexOf(c.offsetLeft) + 1));
        c.style.setProperty('--bac-row', String(rows.length - 1));
        c.classList.add('bac-up');
    });
}

// THE HEADER CARD FITS ITS ROW, AT ANY WINDOW OR BROWSER ZOOM (owner, 2026-08-22).
//
// The band is drawn to a fixed shape: identity 280 + company 195 + last
// transaction 250 + three buttons, about 1140px with the zone padding. Whether
// that fits depends on the window AND on the browser's own zoom — at 90% a
// 1365px window reports 1517 CSS px and the row is whole; at 100% it reports
// 1365 and the buttons drop to a second line; at 110% it is tighter again. A
// CSS breakpoint cannot see the difference between those cases, which is why
// fixing one width kept breaking another.
//
// So the card is measured, not guessed. It is laid out once at natural size to
// read what one row would need, and scaled by exactly the shortfall — the
// owner's own instruction: the 90% design, at whatever size makes it fit. Every
// proportion inside is the tuned one; nothing is re-flowed, nothing truncates
// that did not truncate before.
//
// Floor of 0.78: below that the card would be small enough to read as a
// different component, and the stylesheet's own two-row branch is the better
// answer. Never scales UP — 1.0 is the drawn size.
// Below this the card would read as a different component, and the sheet's
// own two-row branch is the better answer.
const FIT_FLOOR = 0.78;

function fitLedgerHeader() {
    try {
        const card = document.querySelector('#ledgerArea .ledger-header');
        // offsetParent is null while the statement is hidden; measuring then
        // returns zeroes and would scale the card to its floor.
        if (!card || card.offsetParent === null) return;
        const row = card.querySelector('.ledger-head-top');
        const actions = card.querySelector('.lh-actions');
        const first = card.querySelector('.lh-zone-id');
        if (!row || !actions || !first) return;

        // The one question that matters: have the buttons fallen off the first
        // line? Reading the two boxes' tops answers it for whatever the window,
        // the browser zoom and the account's name length happen to be, which is
        // what no CSS breakpoint could do.
        const broken = () => actions.getBoundingClientRect().top
                           - first.getBoundingClientRect().top > 8;

        card.style.zoom = '';
        if (!broken()) return;                // fits as drawn — leave it alone

        const avail = row.clientWidth;
        if (!avail) return;

        // What one unbroken row would take. max-content stops the zones
        // shrinking into the answer, so this is the shape as drawn — and it
        // slightly overstates the need, which the step-up below gives back.
        const wrap = row.style.flexWrap, width = row.style.width;
        row.style.flexWrap = 'nowrap';
        row.style.width = 'max-content';
        const needed = row.scrollWidth;
        row.style.flexWrap = wrap;
        row.style.width = width;
        if (!needed) return;

        let z = Math.max(FIT_FLOOR, Math.floor((avail / needed) * 100) / 100);
        card.style.zoom = z;

        // Hand back the over-shrink a point at a time and stop at the last size
        // that still holds one row: the card ends up as large as it can be, not
        // as small as the first estimate.
        for (let i = 0; i < 10 && z < 1; i++) {
            const test = Math.min(1, Math.round((z + 0.01) * 100) / 100);
            card.style.zoom = test;
            if (broken()) { card.style.zoom = z; break; }
            z = test;
        }
        if (z >= 1) card.style.zoom = '';
    } catch (e) {
        // Layout reads can throw in odd states; the card then keeps the
        // stylesheet's own behaviour, which is what it had before this existed.
    }
}

// The last row moves when the column count does. Re-measured after the resize
// settles rather than during it — this reads layout, and the marks are only
// consulted on the next hover anyway.
let cardRowTimer = null;
window.addEventListener('resize', () => {
    clearTimeout(cardRowTimer);
    cardRowTimer = setTimeout(() => { markLastRowCards(); fitLedgerHeader(); }, 150);
});

// The last set handed to the table, held so the search box can re-filter it
// without another round trip. Every caller still uses renderRecentTransactions.
let RECENT_TXNS = [];

// Sort state for the table. Date descending is the default, so the newest
// transaction is always the first row.
let TXN_SORT = { key: 'date', dir: 'desc' };

// What "date" means to the sorter, for both bank tables.
//
// It cannot be `date` alone. That field is 'YYYY-MM-DD' — journal_entries.date
// is a DATE column with no clock time — so every entry booked today compares as
// EQUAL. Array.sort is stable, so a wall of equal keys keeps the order it
// arrived in, which is the server's chronological one: nine of today's rows
// stayed oldest-first while the header arrow pointed down.
//
// recorded_at is the entry's created_at, 'YYYY-MM-DD HH:MM:SS' — the very clock
// time already printed under each date — and entry_id settles anything booked
// inside the same second. Together the key is unique, so "latest first" now
// means the latest of today too, not just the latest day.
//
// Compared as one string, not parsed: every part is fixed-width, so plain
// lexicographic order IS chronological order, and the id is zero-padded so it
// compares numerically rather than as '9' > '10'.
function txnInstant(t) {
    // entry_time is a deliberate correction (see editDate()) — someone is
    // saying "this really happened at this clock time on its (corrected)
    // date," which should outrank recorded_at, which only ever says when the
    // row was TYPED in. Must match the same COALESCE the server sorts by in
    // buildBankStatementRows(), or a row's position here would disagree with
    // the running balance printed against it.
    const clock = t.entry_time ? `${t.date || ''} ${t.entry_time}` : (t.recorded_at || '');
    return (t.date || '') + '|' + clock + '|'
         + String(t.entry_id || '').padStart(12, '0');
}

const TXN_SORT_VALUES = {
    date:        t => txnInstant(t),
    bank:        t => ((t.bank && t.bank.name) || '').toLowerCase(),
    description: t => (t.description || '').toLowerCase(),
    beneficiary: t => beneficiaryOf(t).name.toLowerCase(),
    debit:       t => Number(t.debit) || 0,
    credit:      t => Number(t.credit) || 0,
    balance:     t => Number(t.company_balance_after) || 0,
};

// Shared by both bank tables. slice() first — sort mutates, and the stored list
// must keep the order the server sent, which is chronological and the order the
// running balance was computed in.
//
// That arrival order is still the tiebreak for the money and text columns:
// Array.sort is stable, so rows with an equal key hold their sequence. The date
// column deliberately no longer leans on it — txnInstant() returns a unique key,
// because a stable tie there is precisely what kept today's entries oldest-first
// under a descending arrow.
function sortRows(list, state, values) {
    const pick = values[state.key];
    if (!pick) return list;
    const dir = state.dir === 'asc' ? 1 : -1;

    return list.slice().sort((a, b) => {
        const av = pick(a), bv = pick(b);
        if (av < bv) return -dir;
        if (av > bv) return  dir;
        return 0;
    });
}

function sortTxns(list) { return sortRows(list, TXN_SORT, TXN_SORT_VALUES); }

// Fills in the header arrows for whichever table `root` selects.
function paintSortIndicators(root, state) {
    document.querySelectorAll(`${root} thead th[data-sort]`).forEach(th => {
        const active = th.dataset.sort === state.key;
        th.classList.toggle('is-active-sort', active);
        const icon = th.querySelector('.th-sort i');
        if (!icon) return;
        icon.className = 'fas ' + (active ? (state.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort');
    });
}

// Wires a table's headers: click to sort by that column, click again to flip.
function bindSortHeaders(root, state, onChange) {
    document.querySelectorAll(`${root} thead th[data-sort]`).forEach(th => {
        th.addEventListener('click', () => {
            const key = th.dataset.sort;
            if (state.key === key) {
                state.dir = state.dir === 'asc' ? 'desc' : 'asc';
            } else {
                state.key = key;
                // Text reads better A→Z; dates and money read better biggest first.
                state.dir = TEXT_SORT_KEYS.includes(key) ? 'asc' : 'desc';
            }
            paintSortIndicators(root, state);
            onChange();
        });
    });
    paintSortIndicators(root, state);
}

const TEXT_SORT_KEYS = ['bank', 'description', 'beneficiary', 'reference', 'source'];

function paintTxnSortIndicators() { paintSortIndicators('.txn-table', TXN_SORT); }

// One repaint for every control on this panel — range buttons, dates, search,
// column sort, an edited description. Anything that skips it leaves the figures
// rail or the count pill describing a table that is no longer on screen.
//
// Period, then search, then sort, and that order matters to more than the rows:
// the rail is computed off the PERIOD set, one step before the search runs, so
// searching narrows what you read without quietly rewriting the totals beneath
// it. Same rule the statement follows.
function repaintRecentTransactions() {
    const inPeriod = rangeTxns(RECENT_TXNS);
    updateCompanyKpis(inPeriod);
    const rows = sortTxns(filterTxns(inPeriod, txnSearchTerm()));
    paintCompanyCount(inPeriod.length, rows.length);
    paintRecentTransactions(rows);
    paintCompanySelection();
}

// ── Picking rows for print ──────────────────────────────────────────────────
// Two tables, one mechanism. 'ca' is the Recent Transactions feed, whose rows
// are journal LEGS (item_id) because a transfer between two of the group's
// banks shows as two rows; 'lf' is the statement, whose rows are one leg per
// entry so the entry id is enough. Each keeps its own Set; the Print button
// of each table reads its own Set and nothing else.
//
// Three ways to print, and they compose: the period buttons narrow what is
// shown; the ticks pick from what is shown; "Select whole day" widens the
// ticks to every row on the ticked days. With no ticks, Print prints the
// period — so the day/range case needs no ticking at all.
const CA_PICKS = new Set();
const LF_PICKS = new Set();
const PICKS = { ca: CA_PICKS, lf: LF_PICKS };

function togglePick(which, id, on) {
    const set = PICKS[which];
    if (on) set.add(id); else set.delete(id);
    const tbody = document.getElementById(which === 'ca' ? 'recentTxnBody' : 'ledgerBody');
    const attr  = which === 'ca' ? 'item-id' : 'entry-id';
    const tr = tbody && tbody.querySelector(`tr[data-${attr}="${id}"]`);
    if (tr) tr.classList.toggle('is-picked', on);
    (which === 'ca' ? paintCompanySelection : paintLedgerSelection)();
}

// Rows currently on screen for a table, as [id, date] pairs — read from the
// DOM so "what is shown" is literally what is shown, search and range included.
//
// The statement is the exception, because it pages: its rows are read from the
// data instead, so "what is shown" stays the whole search result and not the
// fifty rows that happen to be drawn. Ticking "select all" on page 1 and
// printing has to mean the statement you are looking at, not page 1 of it.
function shownPicks(which) {
    if (which === 'lf') {
        return ledgerRowsInView()
            .map(r => [Number(r.entry_id), r.date])
            .filter(([id]) => id > 0);
    }
    const tbody = document.getElementById(which === 'ca' ? 'recentTxnBody' : 'ledgerBody');
    const attr  = which === 'ca' ? 'itemId' : 'entryId';
    return Array.from(tbody ? tbody.querySelectorAll('tr[data-date]') : [])
        .map(tr => [Number(tr.dataset[attr]), tr.dataset.date])
        .filter(([id]) => id > 0);
}

function pickAllShown(which, on) {
    const set = PICKS[which];
    shownPicks(which).forEach(([id]) => on ? set.add(id) : set.delete(id));
    (which === 'ca' ? repaintRecentTransactions : paintLedgerRows)();
}

// Every row that shares a day with a ticked row. Reads the full list, not the
// screen: a search may be hiding the rest of that day, and "whole day" means
// the whole day.
function pickWholeDay(which) {
    const set = PICKS[which];
    if (!set.size) return;
    if (which === 'ca') {
        const days = new Set(RECENT_TXNS.filter(t => set.has(t.item_id)).map(t => t.date));
        RECENT_TXNS.forEach(t => { if (days.has(t.date)) set.add(t.item_id); });
        repaintRecentTransactions();
    } else if (LEDGER_DATA) {
        const rows = LEDGER_DATA.transactions;
        const days = new Set(rows.filter(r => set.has(r.entry_id)).map(r => r.date));
        rows.forEach(r => { if (days.has(r.date)) set.add(r.entry_id); });
        paintLedgerRows();
    }
}

function clearPicks(which) {
    PICKS[which].clear();
    (which === 'ca' ? repaintRecentTransactions : paintLedgerRows)();
}

// Picking mode: the tick boxes and the strip exist only while it is on. It
// is entered from Print → "Selected transactions" and left by Cancel, by a
// company switch, or by opening another statement.
const PICKING = { ca: false, lf: false };

function pickingHost(which) {
    if (which === 'lf') return document.getElementById('ledgerArea');
    const tbody = document.getElementById('recentTxnBody');
    return tbody ? tbody.closest('table') : null;
}

function startPicking(which) {
    PICKING[which] = true;
    const host = pickingHost(which);
    if (host) host.classList.add('is-picking');
    (which === 'ca' ? repaintRecentTransactions : paintLedgerRows)();
    // Bring the table under the eye, strip first.
    const strip = document.getElementById(which + 'Sel');
    if (strip && strip.scrollIntoView) strip.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

function stopPicking(which) {
    PICKING[which] = false;
    PICKS[which].clear();
    const host = pickingHost(which);
    if (host) host.classList.remove('is-picking');
    (which === 'ca' ? repaintRecentTransactions : paintLedgerRows)();
}

// While picking, a click anywhere on a row toggles its box — the box is
// small and the row is the natural target. Returns true when it handled
// the click, so the row's usual action (opening the ledger) is skipped.
function rowPickClick(tr, which, ev) {
    if (!PICKING[which]) return false;
    // The row's own controls (tick box, pencil, Reverse, date) keep their
    // meaning; only a click on the row body toggles.
    const t = ev && ev.target;
    if (t && t.closest && t.closest('button, a, input, .desc-edit')) return true;
    const box = tr.querySelector('input[type=checkbox]');
    if (box) { box.checked = !box.checked; box.dispatchEvent(new Event('change')); }
    return true;
}

// The strip, the head tick and the Print links for one table.
function paintSelectionStrip(which, n, printHref, selHref) {
    const strip = document.getElementById(which + 'Sel');
    const count = document.getElementById(which + 'SelN');
    const all   = document.getElementById(which + 'PickAll');
    if (strip) strip.classList.toggle('is-on', PICKING[which]);
    if (count) count.textContent = String(n);
    if (all) {
        const shown = shownPicks(which);
        const set = PICKS[which];
        const picked = shown.filter(([id]) => set.has(id)).length;
        all.checked = shown.length > 0 && picked === shown.length;
        all.indeterminate = picked > 0 && picked < shown.length;
    }
    const sel = document.getElementById(which + 'SelPrint');
    if (sel) {
        sel.href = n ? selHref : '#';
        sel.style.opacity = n ? '' : '.5';
        sel.style.pointerEvents = n ? '' : 'none';
    }
}

// One URL builder per table. `from`/`to` are Y-m-d or blank; `ids` are the
// ticked rows (journal legs for the feed, entries for the statement). Ids
// win over dates on the server, so a selection prints the same whatever the
// period boxes say.
function printUrl(which, { from = '', to = '', ids = [] } = {}) {
    const params = new URLSearchParams();
    if (which === 'ca') {
        const company = COMPANY_DATA.find(c => c.name === activeCompanyName);
        if (!company) return null;
        params.set('banks', (company.banks || []).map(b => b.id).join(','));
        params.set('company', company.name);
        if (company.company_id) params.set('company_id', company.company_id);
        if (from) params.set('date_from', from);
        if (to)   params.set('date_to', to);
        if (ids.length) params.set('items', ids.join(','));
        return `${STATEMENT_PDF_BASE}/dashboard/print?${params}`;
    }
    if (!LEDGER_DATA) return null;
    if (from) params.set('date_from', from);
    if (to)   params.set('date_to', to);
    if (ids.length) params.set('entries', ids.join(','));
    return `${STATEMENT_PDF_BASE}/${LEDGER_DATA.bank.id}/statement/pdf?${params}`;
}

// The period the table is showing right now, as dates the server accepts.
// The feed's "All 14 Days" is sent as explicit dates so the printout is the
// same fourteen days the screen holds.
function shownPeriod(which) {
    if (which === 'ca') {
        const w = txnWindow();
        return { from: TXN_FROM || w.from, to: TXN_TO || w.to };
    }
    return { from: (LEDGER_DATA && LEDGER_DATA.date_from) || '', to: (LEDGER_DATA && LEDGER_DATA.date_to) || '' };
}

// Recent Transactions: the Print button opens the chooser; the strip's
// "Print selected" is the shortcut straight to the ticked rows.
function paintCompanySelection() {
    const link = document.getElementById('caPrint');
    if (!link || !COMPANY_DATA.find(c => c.name === activeCompanyName)) return;
    const n = CA_PICKS.size;
    const p = shownPeriod('ca');
    link.href = printUrl('ca', n ? { ids: Array.from(CA_PICKS) } : p) || '#';
    link.onclick = e => { e.preventDefault(); openPrintDialog('ca'); };
    link.classList.toggle('has-sel', n > 0);
    link.innerHTML = `<i class="fas fa-print"></i> ${n ? `Print (${n} ticked)` : 'Print'}`;
    paintSelectionStrip('ca', n, printUrl('ca', p), printUrl('ca', { ids: Array.from(CA_PICKS) }));
}

// Statement: both Print buttons (card header, filter bar) open the chooser.
function paintLedgerSelection() {
    if (!LEDGER_DATA) return;
    const n = LF_PICKS.size;
    const p = shownPeriod('lf');
    const href = printUrl('lf', n ? { ids: Array.from(LF_PICKS) } : p) || '#';
    ['printStatementLink', 'lfPrint'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.href = href;
        el.onclick = e => { e.preventDefault(); openPrintDialog('lf'); };
        el.classList.toggle('has-sel', n > 0);
        if (id === 'lfPrint') el.innerHTML = `<i class="fas fa-print"></i> ${n ? `Print (${n} ticked)` : 'Print'}`;
    });
    paintSelectionStrip('lf', n, printUrl('lf', p), printUrl('lf', { ids: Array.from(LF_PICKS) }));
}

// ── The print chooser ───────────────────────────────────────────────────────
// Print asks what to print rather than guessing: today, yesterday, one day,
// a date range, the ticked rows, or exactly what the table is showing. The
// day/range choices are not limited to the feed's fourteen days — the sheet
// is built server-side from the whole ledger.
function openPrintDialog(which) {
    const picks   = PICKS[which];
    const n       = picks.size;
    const period  = shownPeriod(which);
    const today   = ymdLocal(new Date());
    const yday    = (() => { const d = new Date(); d.setDate(d.getDate() - 1); return ymdLocal(d); })();
    const shownLabel = period.from && period.to
        ? (period.from === period.to ? fmtDMY(period.from) : `${fmtDMY(period.from)} – ${fmtDMY(period.to)}`)
        : (period.from ? `from ${fmtDMY(period.from)}` : period.to ? `up to ${fmtDMY(period.to)}` : 'all time');
    const initial = n ? 'selected' : 'shown';

    const opt = (value, label, hint, extra = '', disabled = false) => `
        <label class="pd-opt${disabled ? ' is-off' : ''}">
            <input type="radio" name="pdMode" value="${value}" ${value === initial ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
            <span class="pd-body">
                <span class="pd-label">${label}</span>
                ${hint ? `<span class="pd-hint">${hint}</span>` : ''}
                ${extra}
            </span>
        </label>`;
    const dateBox = (id, value) => `<input type="date" id="${id}" value="${value}" class="pd-date" onfocus="this.closest('.pd-opt').querySelector('input[type=radio]').checked = true">`;

    Swal.fire({
        title: 'Print transactions',
        html: `
            <style>
                .pd-list { display:flex; flex-direction:column; gap:6px; text-align:left; }
                .pd-opt { display:flex; gap:10px; align-items:flex-start; padding:9px 11px; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer; }
                .pd-opt:has(input:checked) { border-color:#6366f1; background:#f5f6ff; }
                .pd-opt.is-off { opacity:.55; cursor:not-allowed; }
                .pd-opt input[type=radio] { margin-top:3px; accent-color:#4f46e5; }
                .pd-body { display:flex; flex-direction:column; gap:3px; flex:1; }
                .pd-label { font-size:13px; font-weight:700; color:#1e293b; }
                .pd-hint { font-size:11px; color:#64748b; }
                .pd-dates { display:flex; gap:8px; align-items:center; margin-top:4px; font-size:11px; color:#64748b; }
                .pd-date { padding:5px 8px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px; color:#1e293b; }
            </style>
            <div class="pd-list">
                ${opt('today', 'Today', fmtDMY(today))}
                ${opt('yesterday', 'Yesterday', fmtDMY(yday))}
                ${opt('day', 'A specific day', '', `<div class="pd-dates">${dateBox('pdDay', today)}</div>`)}
                ${opt('range', 'A date range', '', `<div class="pd-dates">${dateBox('pdFrom', period.from || today)} <span>to</span> ${dateBox('pdTo', period.to || today)}</div>`)}
                ${opt('selected', n ? `Selected transactions (${n})` : 'Selected transactions',
                      n ? 'Only the rows you ticked in the table' : 'Tick the rows you want in the table')}
                ${opt('shown', 'What the table shows now', shownLabel)}
            </div>`,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-print"></i> Print',
        confirmButtonColor: '#4f46e5',
        focusConfirm: false,
        width: 440,
        // With nothing ticked yet, choosing "Selected transactions" IS the
        // action: the chooser closes on the click and the table switches to
        // picking mode — no second press of Print needed.
        didOpen: () => {
            const radio = document.querySelector('input[name=pdMode][value=selected]');
            if (radio && !n) {
                radio.addEventListener('change', () => { if (radio.checked) { Swal.close(); startPicking(which); } });
            }
        },
        preConfirm: () => {
            const mode = (document.querySelector('input[name=pdMode]:checked') || {}).value;
            let from = '', to = '', ids = [];
            if      (mode === 'today')     { from = to = today; }
            else if (mode === 'yesterday') { from = to = yday; }
            else if (mode === 'day') {
                from = to = document.getElementById('pdDay').value;
                if (!from) { Swal.showValidationMessage('Pick the day.'); return false; }
            }
            else if (mode === 'range') {
                from = document.getElementById('pdFrom').value;
                to   = document.getElementById('pdTo').value;
                if (!from && !to) { Swal.showValidationMessage('Pick a start or an end date.'); return false; }
                if (from && to && from > to) { const t = from; from = to; to = t; }
            }
            else if (mode === 'selected') { ids = Array.from(picks); }
            else                          { from = period.from; to = period.to; }
            return { mode, from, to, ids };
        },
    }).then(res => {
        if (!res.isConfirmed) return;
        // "Selected" with nothing ticked yet is a request to start ticking,
        // not to print an empty sheet.
        if (res.value.mode === 'selected' && !res.value.ids.length) {
            startPicking(which);
            return;
        }
        const url = printUrl(which, res.value);
        if (url) window.open(url, '_blank');
    });
}
document.getElementById('caPickAll').addEventListener('change', function () { pickAllShown('ca', this.checked); });
document.getElementById('lfPickAll').addEventListener('change', function () { pickAllShown('lf', this.checked); });

function renderRecentTransactions(transactions) {
    RECENT_TXNS = Array.isArray(transactions) ? transactions : [];
    updateTodayFlow(RECENT_TXNS);
    repaintRecentTransactions();
}

function txnSearchTerm() {
    const el = document.getElementById('txnSearch');
    return el ? el.value.trim().toLowerCase() : '';
}

// ── The landing table's period filter ───────────────────────────────────────
// It filters rows already in the browser, where the statement's bar re-asks the
// server. That is not a shortcut: the statement's Opening Balance and totals are
// properties of the period and only the server can recompute them, whereas this
// feed has no opening balance and each row's company balance is a point in time,
// so narrowing the period cannot make a printed figure wrong. There is also no
// company-wide statement endpoint to ask — dashboardPayload() serves a fixed
// last-14-days set, deliberately (see the note on dashboardData()).
//
// Which makes the 14 days a ceiling this control must not pretend past. It
// carries min/max on the pickers, its last segment says "All 14 Days" rather
// than the statement's "Last Month", and a date typed outside the window is
// pulled back to the edge with a note in the header — never silently answered
// with an empty table.
let TXN_RANGE = 'all';
let TXN_FROM  = '';
let TXN_TO    = '';

// Local Y-m-d. NOT toISOString(), which converts to UTC first and hands back
// yesterday for anything before 6am in Dhaka — the row dates it is compared
// against are plain calendar days with no zone at all.
function ymdLocal(d) {
    const pad = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
}

// The window the server actually sent: dashboardPayload() keeps entries dated on
// or after now()->subDays(14). Same arithmetic, so the bound the picker offers
// and the bound the data has cannot drift apart.
function txnWindow() {
    const from = new Date();
    from.setDate(from.getDate() - 14);
    return { from: ymdLocal(from), to: ymdLocal(new Date()) };
}

// Plain string comparison: every date here is a fixed-width 'YYYY-MM-DD', so
// lexicographic order IS calendar order and no parsing is needed.
function rangeTxns(list) {
    if (!TXN_FROM && !TXN_TO) return list;
    return list.filter(t => (!TXN_FROM || t.date >= TXN_FROM) && (!TXN_TO || t.date <= TXN_TO));
}

function applyCompanyRange(key) {
    const w = txnWindow();
    const back = n => { const d = new Date(); d.setDate(d.getDate() - n); return ymdLocal(d); };
    let from = '', to = '', clamped = false;

    if      (key === 'today')     { from = to = ymdLocal(new Date()); }
    else if (key === 'yesterday') { from = to = back(1); }
    else if (key === 'week')      { from = back(6); to = ymdLocal(new Date()); }   // rolling 7 days, today included
    else if (key === 'all')       { from = ''; to = ''; }
    else if (key === 'custom') {
        from = document.getElementById('caFrom').value;
        to   = document.getElementById('caTo').value;
        if (!from && !to) {
            key = 'all';
        }
        // One date on its own means THAT DAY, the same shape as the Today and
        // Yesterday buttons; fill the second box and it becomes a range. Dates
        // that arrive backwards are swapped rather than answered with nothing.
        if (key === 'custom') {
            if      (from && !to) to   = from;
            else if (to && !from) from = to;
            else if (from > to)   { const t = from; from = to; to = t; }

            // Pulled to the window instead of honoured and returned empty. A typed
            // (or pasted) date can still land outside it even though the pickers
            // will not offer one.
            if (from < w.from) { from = w.from; clamped = true; }
            if (to   > w.to)   { to   = w.to;   clamped = true; }
        }
    }

    if (key !== 'custom') setCompanyDateBoxes('', '');

    TXN_RANGE = key;
    TXN_FROM  = from;
    TXN_TO    = to;
    paintCompanyRangeButtons();
    paintCompanyPeriod(clamped);
    repaintRecentTransactions();
}

// "Showing" on the header card. It is the one place that says what the table is,
// so it has to be written by every path that changes the range.
function paintCompanyPeriod(clamped) {
    const label = document.getElementById('caPeriodLabel');
    const note  = document.getElementById('caPeriodNote');
    if (!label || !note) return;

    const names = { today: 'Today', yesterday: 'Yesterday', week: 'Last 7 days', all: 'Last 14 days' };
    label.textContent = TXN_RANGE === 'custom'
        ? (TXN_FROM === TXN_TO ? fmtDMY(TXN_FROM) : fmtDMY(TXN_FROM) + ' – ' + fmtDMY(TXN_TO))
        : (names[TXN_RANGE] || 'Last 14 days');

    note.textContent = clamped
        ? 'Pulled back — this feed holds the last 14 days only'
        : 'This feed holds the last 14 days only';
    note.classList.toggle('ca-note-warn', !!clamped);
}

// The visible text box and the hidden date input are two halves of one control
// and must never disagree, so nothing sets one without the other. Same contract
// as setLedgerDateBoxes(), on this bar's ids.
function setCompanyDateBoxes(from, to) {
    [['caFrom', 'caFromText', from], ['caTo', 'caToText', to]].forEach(([nativeId, textId, ymd]) => {
        const native = document.getElementById(nativeId);
        const text   = document.getElementById(textId);
        if (native) native.value = ymd || '';
        if (text) { text.value = ymd ? fmtDMY(ymd) : ''; markLedgerDateBad(text, false); }
    });
}

function paintCompanyRangeButtons() {
    document.querySelectorAll('#caSeg button').forEach(b => {
        b.classList.toggle('is-on', b.dataset.range === TXN_RANGE);
    });
    // Nothing in the segment carries data-range="custom", so a custom range
    // lights none of them and the two date boxes go indigo instead — that tint
    // is what says the dates on screen are the ones the table was built from.
    document.getElementById('caDates').classList.toggle('is-open', TXN_RANGE === 'custom');
}

// "12 of 87 rows" beside the search box. Only while a query is live — a count of
// everything tells you nothing you cannot already see.
function paintCompanyCount(total, shown) {
    const el = document.getElementById('caCount');
    if (!el) return;
    const searching = txnSearchTerm() !== '';
    el.style.display = searching ? '' : 'none';
    if (!searching) return;
    el.classList.toggle('is-none', shown === 0);
    el.textContent = shown === 0
        ? 'No match in ' + total + ' row' + (total === 1 ? '' : 's')
        : shown + ' of ' + total + ' row' + (total === 1 ? '' : 's');
}

// One place for the row date, so the Today figures below and the DATE column
// can never disagree about which calendar day a transaction falls on.
function shortTxnDate(d) {
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

function filterTxns(list, term) {
    if (!term) return list;
    return list.filter(t => {
        const bank = t.bank || {};
        return [bank.name, bank.account_number, bank.company_name,
        t.description, beneficiaryOf(t).name, paidViaLine(t), t.txn_no, shortTxnDate(t.date), t.recorded_at, t.debit, t.credit]

            .some(v => v !== null && v !== undefined && String(v).toLowerCase().includes(term));
    });
}

// Today's movement across the same 14-day set the table was given. Debit is
// money into the account (the row's 'In' badge), credit is money out.
// Computed from the full set, never the filtered one — these report the day.
function updateTodayFlow(list) {
    const dayKey = d => new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const today = dayKey(new Date());
    let todayIn = 0, todayOut = 0;

    list.forEach(t => {
        if (dayKey(t.date) !== today) return;
        todayIn  += Number(t.debit)  || 0;
        todayOut += Number(t.credit) || 0;
    });

    setRailMoney('todayInVal',  todayIn);
    setRailMoney('todayOutVal', todayOut);
}

// ── The figures rail ────────────────────────────────────────────────────────
// A rail cell is a label and a figure, and the ink lives on the cell: .is-in
// green, .is-out red, .is-zero grey for a quantity that is nothing. A zero that
// still carries its colour reads as movement at a glance, which is why the grey
// matters — see the note on .ledger-meta .is-zero in the sheet.
function setRailMoney(id, v) {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = '<span class="cur">৳</span>' + fmtMoney(v);
    el.parentElement.classList.toggle('is-zero', !Number(v));
}

// Transactions, total debit and total credit for the PERIOD on screen — not for
// the search, and not for the fixed 14 days. They are sums of the rows the table
// is showing, so they move with the range buttons and with nothing else.
function updateCompanyKpis(list) {
    let debit = 0, credit = 0;
    list.forEach(t => { debit += Number(t.debit) || 0; credit += Number(t.credit) || 0; });

    const count = document.getElementById('caTxnCount');
    if (count) {
        count.textContent = String(list.length);
        count.parentElement.classList.toggle('is-zero', list.length === 0);
    }
    setRailMoney('caTotalDebit',  debit);
    setRailMoney('caTotalCredit', credit);
}

// Who the money went to, read off the description's tail — the part the
// user actually typed or the account the transfer landed in:
//   "Bank transfer — ISLAMI BANK (Travels) → OFFICE CASH"   → OFFICE CASH
//   "Deposit to DUTCH BANGLA (Travels) — AS SOHEL VAI"       → AS SOHEL VAI
//   "Direct ticket purchase — XYZ123"                        → XYZ123
// Derived client-side (not server-side) so the pencil's remark edit updates
// this column in the same breath as the description. A description with no
// separator falls back to the server's party / counter-account.
//
// Also returns `head`: the description with that tail taken out, which is
// what the DESCRIPTION cell shows so the name is not printed twice. Display
// only — t.description itself is untouched, so search, the pencil dialog
// and the ledger still see the full text.
function beneficiaryOf(t) {
    const full = String(t.description || '');
    // The name typed on the deposit/withdraw form — its own column since the
    // 2026-09-14 migration — beats anything parsed out of the text.
    if (t.beneficiary_person) {
        return { name: String(t.beneficiary_person), kind: t.beneficiary_kind || null, head: full };
    }
    // Trailing bookkeeping suffixes the forms append after the remark; they
    // are kept on the description, not moved into the beneficiary.
    let suffix = '';
    const d = full.replace(/\s*(\(incl\.[^)]*\)|including charge\/VAT)\s*$/i, m => { suffix = m; return ''; });
    let cut = -1, sepLen = 0;
    if (d.includes('→')) {
        cut = d.lastIndexOf('→'); sepLen = 1;
    } else if (d.includes(' — ')) {
        cut = d.lastIndexOf(' — '); sepLen = 3;
    }
    let tail = cut >= 0 ? d.slice(cut + sepLen).trim() : '';
    if (tail) {
        let head = d.slice(0, cut).trim();
        // A typed remark of "NAME - purpose" (also – / or :): only the name
        // is the beneficiary; the purpose stays on the description. A colon
        // splits anywhere; dash, en dash and slash need a space on at least
        // one side, so "Abdul-Karim" stays one name. Transfer tails (→) are
        // account names and are never split.
        const m = sepLen === 3 && (tail.match(/^(.+?)\s*:\s*(.+)$/) || tail.match(/^(.+?)(?:\s+[-–\/]\s*|\s*[-–\/]\s+)(.+)$/));
        if (m) { tail = m[1].trim(); head += ' — ' + m[2].trim(); }
        return { name: tail, kind: null, head: (head + suffix).trim() };
    }
    return { name: t.beneficiary || '', kind: t.beneficiary_kind, head: full };
}

// The DESCRIPTION cell, with the row's own bank taken out: the Bank column
// already says which account, so "Withdrawal from DUTCH BANGLA (Travels) —
// Ticket" repeats itself. What is left is the purpose ("Ticket"), or,
// when none was typed, just the kind of movement ("Withdrawal"), shown
// muted so a bare cell still reads. Suffixes the forms add stay
// ("Withdrawal (incl. charge 50.00)"). Anything not in one of our own
// three shapes is shown as it is.
function briefDescription(head, bankName) {
    const m = String(head || '').match(/^(Deposit to|Withdrawal from|Bank transfer —)\s+(.*)$/);
    if (!m) return { text: head || '', muted: false };
    const type = m[1] === 'Bank transfer —' ? 'Bank transfer' : m[1].split(' ')[0];
    let rest = m[2].trim();
    if (bankName && rest.startsWith(bankName)) {
        rest = rest.slice(bankName.length).trim();
    } else if (bankName) {
        return { text: head, muted: false };   // some other account's line — say so in full
    }
    if (rest.startsWith('—')) return { text: rest.slice(1).trim() || type, muted: !rest.slice(1).trim() };
    return { text: rest ? `${type} ${rest}` : type, muted: !rest };
}
// The note line under a statement row. Our own deposit/withdraw entries
// copy the remark into the note, so with a Beneficiary column and the purpose
// now in the description the note would print the same words a third time.
// Shown only when it says something the row does not already say.
function noteWorthShowing(row) {
    const note = String(row.note || '').trim().toLowerCase();
    if (!note) return false;
    const benef = beneficiaryOf(row).name.toLowerCase();
    const said  = (String(row.description || '') + ' ' + benef).toLowerCase();
    return !said.includes(note) && !(benef && note.includes(benef));
}
function briefCell(text, muted) {
    return muted ? `<span style="color:#94a3b8;">${text}</span>` : text;
}
function sourceBadge(row) {
    const badges = {
        expense: ['Expense', '#fef3c7', '#92400e'],
        financing_capital: ['Capital', '#ede9fe', '#6d28d9'],
    };
    const badge = badges[row.source];
    return badge ? `<span style="display:inline-flex;align-items:center;margin-right:6px;padding:1px 6px;border-radius:999px;background:${badge[1]};color:${badge[2]};font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.03em;">${badge[0]}</span>` : '';
}

// A named party from the server gets its kind underneath (Customer /
// Supplier / Agent / Vendor / Employee) so a person and an account with the
// same name are not confused. Escaped: the text is typed by users.
const BENEFICIARY_KIND_LABEL = {
    customer: 'Customer', supplier: 'Supplier', agent: 'Agent',
    vendor: 'Vendor', employee: 'Employee', others: 'Party',
};
// "Cash", "Bank · DUTCH BANGLA · 1234567", "Cheque · CITY BANK · No. 00123"
// — JS twin of BankControllerV2::paidViaLine(); keep the two in step.
function paidViaLine(row) {
    const m = row.beneficiary_method;
    if (!m) return '';
    const parts = [{ cash: 'Cash', bank: 'Bank', cheque: 'Cheque' }[m] || m];
    if (row.beneficiary_bank) parts.push(row.beneficiary_bank);
    if (row.beneficiary_ref)  parts.push((m === 'cheque' ? 'No. ' : '') + row.beneficiary_ref);
    return parts.join(' · ');
}
// The Beneficiary cell, built to the same drawing as the Bank cell beside it:
//
//     Rafi Bhai  [Bank]                 name + a small pill, like the bank's
//     Sonar Bank Bangladesh: 555666999  In/Out badge; their bank and number
//                                       on one line where our account line sits.
//
// Cash gets just the pill. A name resolved from the entry (no payment
// detail recorded) shows its kind — Employee, Customer — on the second line
// instead. One renderer for both tables, so they cannot drift apart.
const VIA_PILL = {
    cash:   ['Cash',   '#dcfce7', '#15803d'],
    bank:   ['Bank',   '#dbeafe', '#1e40af'],
    cheque: ['Cheque', '#fef3c7', '#92400e'],
};
function beneficiaryBlock(row) {
    const b = beneficiaryOf(row);
    if (!b.name) return '';
    const m    = row.beneficiary_method;
    const pill = VIA_PILL[m];
    const sub  = 'font-size:0.7rem;color:#94a3b8;margin-top:1px;';
    // The party this entry was actually booked against, when one was picked on
    // the form. Only then is there a code to link: beneficiary_party_id is a
    // real users.id, so the ledger it opens is provably the right person's.
    //
    // A name with no id behind it gets no code and no link, deliberately. Most
    // rows on file predate the party picker and their beneficiary is text
    // parsed out of the description — matching those by name would eventually
    // open the wrong "MD RAHMAN"'s statement, and a confidently wrong ledger is
    // worse than no link at all.
    const employee = b.kind === 'employee'
        ? (BENEFICIARY_EMPLOYEE_BY_ID[String(row.beneficiary_party_id)] || null)
        : null;
    const owner = employee ? null : (BENEFICIARY_OWNER_BY_ID[String(row.beneficiary_party_id)] || null);
    const party = employee || owner || (PARTY_STATEMENT_URL ? BENEFICIARY_PARTY_BY_ID[String(row.beneficiary_party_id)] : null);
    const partyHref = employee
        ? `${EMPLOYEE_LEDGER_BASE}/${encodeURIComponent(employee.id)}`
        : (owner
            ? `${OWNER_LEDGER_URL}&owner_id=${encodeURIComponent(owner.id)}`
            : (party ? `${PARTY_STATEMENT_URL}?party_id=${encodeURIComponent(party.id)}${row.company_id ? `&company=${encodeURIComponent(row.company_id)}` : ''}` : null));

    // Same tab (owner, 2026-09-15): the code should TAKE you to the party's
    // ledger. It first opened a new tab, which read as the link doing nothing.
    // Ctrl/middle-click still opens it beside the statement.
    //
    // stopPropagation because the row underneath is a click target in both
    // tables — it ticks the print box in the statement and opens a bank's
    // ledger in the feed. Without it, one click does two things.
    //
    // The CODE is the link, not the name (owner, 2026-09-15): the name is what
    // you read, the ID is the key — it is what identifies the party, and it is
    // what carries you to their statement. The name stays plain text.
    //
    // One line for the name, however long — see .bn-name in the styles. The
    // full name rides on the title, so a cut one is a hover away.
    const displayName = `<span class="bn-name" title="${esc(b.name)}">${esc(b.name)}</span>`;
    let html = `<div class="bn-name-row"><div class="bn-name-line">${displayName}${pill
        ? `<span class="bn-pill" style="display:inline-flex;align-items:center;font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;background:${pill[1]};color:${pill[2]};">${pill[0]}</span>`
        : ''}</div></div>`;
    if (party) {
        html += `<div style="${sub}">${esc(party.kind)} ID: <a href="${partyHref}"
              class="bn-party-link bn-party-code"
              title="Open ${esc(party.name)}'s ledger"
              onclick="event.stopPropagation();">${esc(party.display_id)}</a></div>`;
    }
    if (pill) {
        // Bank and number on ONE line, "ABCD BANK: 1234567890" (owner,
        // 2026-09-15) — two lines made the cell taller than the bank cell
        // beside it. Cheques keep their label so the number is not read as
        // an account: "CITY BANK: Cheque No. 00123".
        const ref = row.beneficiary_ref ? (m === 'cheque' ? 'Cheque No. ' : '') + row.beneficiary_ref : '';
        const via = [row.beneficiary_bank, ref].filter(Boolean).join(': ');
        if (via) html += `<div style="${sub}white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${esc(via)}">${esc(via)}</div>`;
    } else if (BENEFICIARY_KIND_LABEL[b.kind]) {
        html += `<div style="${sub}">${BENEFICIARY_KIND_LABEL[b.kind]}</div>`;
    }
    return html;
}
function beneficiaryCell(t) {
    const html = beneficiaryBlock(t);
    return html ? `<td>${html}</td>` : '<td style="color:#cbd5e1;">—</td>';
}
function ledgerBeneficiaryCell(row) {
    return beneficiaryBlock(row) || '<span class="text-gray-300">—</span>';
}

function paintRecentTransactions(transactions) {
    const tbody = document.getElementById('recentTxnBody');
    if (!transactions.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:1.5rem;">'
            + (txnSearchTerm() ? 'No transactions match your search.'
               : TXN_RANGE === 'all' ? 'No transactions in the last 14 days.'
               : 'No transactions in the selected period.')
            + '</td></tr>';
        return;
    }

    tbody.innerHTML = transactions.map(t => {
        const bank = t.bank;
        const balCls = t.company_balance_after < 0 ? '#dc2626' : '#1d4ed8';
        const rowAttrs = bank
            ? `style="cursor:pointer;" title="Open ${bank.name}'s full ledger" onclick='if (rowPickClick(this, "ca", event)) return; openBankFromRow(${JSON.stringify(bank)})'`
            : '';
        const timeLine = customTimeLine(t.entry_time);


        // Quick-scan in/out badge next to the bank name — easier for a
        // non-accountant to read at a glance than separate Debit/Credit cells.
        const isIn = t.debit > 0;
        const flowBadge = `<span style="display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:6px;background:${isIn ? '#dcfce7' : '#fee2e2'};color:${isIn ? '#15803d' : '#b91c1c'};">
            <i class="fas fa-arrow-${isIn ? 'down' : 'up'}"></i> ${isIn ? 'In' : 'Out'}
        </span>`;

        // That specific bank's own opening → closing balance for THIS row —
        // shown small, under the account/company line, so row height doesn't grow.
        const openCls  = t.bank_balance_before < 0 ? '#dc2626' : '#1d4ed8';
        const closeCls = t.bank_balance_after < 0 ? '#dc2626' : '#1d4ed8';
        const bankBalLine = bank ? `<div style="font-size:0.7rem;color:#94a3b8;margin-top:1px;">
            Opening: <span style="color:${openCls};font-weight:600;">৳${fmtMoney(t.bank_balance_before)} ${t.bank_balance_before < 0 ? 'Cr' : 'Dr'}</span>
            &nbsp;·&nbsp; Closing: <span style="color:${closeCls};font-weight:600;">৳${fmtMoney(t.bank_balance_after)} ${t.bank_balance_after < 0 ? 'Cr' : 'Dr'}</span>
        </div>` : '';

        return `<tr ${rowAttrs} class="${CA_PICKS.has(t.item_id) ? 'is-picked' : ''}" data-item-id="${t.item_id}" data-date="${t.date}">
            {{-- stopPropagation: the row itself opens the ledger. --}}
            <td class="txn-pick" onclick="event.stopPropagation()"><input type="checkbox" ${CA_PICKS.has(t.item_id) ? 'checked' : ''} onchange="togglePick('ca', ${t.item_id}, this.checked)" aria-label="Select this transaction"></td>
            <td style="white-space:nowrap;">${fmtDMY(t.date)}${timeLine}</td>

            <td>
                <div style="font-weight:600;color:#1e293b;">${bank ? bank.name : '—'}${flowBadge}</div>
                ${bank ? `<div style="font-size:0.7rem;color:#94a3b8;margin-top:1px;">${bank.account_number || ''}${bank.company_name ? ' · ' + bank.company_name : ''}</div>` : ''}
                ${bankBalLine}
            </td>
            {{-- stopPropagation: the whole row is a click target that opens the
                 bank's ledger, and the pencil must not trigger it. --}}
            {{-- The beneficiary's name is cut out of the text here — it has its
                 own column now. beneficiaryOf() keeps t.description whole. --}}
            <td>
                ${sourceBadge(t)}${(b => briefCell(b.text, b.muted))(briefDescription(beneficiaryOf(t).head, bank ? bank.name : ''))}${descEditButton(t)}
                ${t.txn_no ? `<div class="txn-no">${t.txn_no}</div>` : ''}
            </td>
            ${beneficiaryCell(t)}
            <td style="text-align:right;">${t.debit > 0 ? `<span class="txn-amount-debit">৳${fmtMoney(t.debit)}</span>` : '—'}</td>
            <td style="text-align:right;">${t.credit > 0 ? `<span class="txn-amount-credit">৳${fmtMoney(t.credit)}</span>` : '—'}</td>
            <td style="text-align:right;">
                <span style="font-family:monospace;font-weight:700;color:${balCls};">৳${fmtMoney(t.company_balance_after)} <small>${t.company_balance_after < 0 ? 'Cr' : 'Dr'}</small></span>
            </td>
        </tr>`;
    }).join('');
}

// ── Bank search ────────────────────────────────────────────────────────────────
document.getElementById('bankSearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 1) { closeDropdown(); return; }
    searchTimer = setTimeout(() => doSearch(q), 280);
});
function doSearch(q) {
    const companyId = document.getElementById('companyFilter').value;
    const params = new URLSearchParams({ q: q || '' });
    if (companyId) params.set('company_id', companyId);
    fetch(`${SEARCH_URL}?${params}`).then(r => r.json()).then(renderDropdown);
}
function renderDropdown(banks) {
    const dd = document.getElementById('bankDropdown');
    if (!banks.length) {
        dd.innerHTML = '<div style="padding:14px;color:#94a3b8;font-size:0.8rem;">No bank accounts found</div>';
        dd.style.display = 'block'; return;
    }
    dd.innerHTML = banks.map(b => `
        <div class="bank-dropdown-item" onclick='selectBank(${JSON.stringify(b)})'>
            <div class="bank-dropdown-avatar">${b.initials}</div>
            <div style="flex:1;min-width:0;">
                <p style="font-size:0.85rem;font-weight:600;color:#1e293b;margin:0;">${b.name}</p>
                <p style="font-size:0.72rem;color:#94a3b8;margin:0;">${b.account_number || ''} ${b.company_name ? '· ' + b.company_name : ''}</p>
            </div>
            <div style="font-size:0.78rem;font-weight:700;color:${b.balance >= 0 ? '#1d4ed8' : '#dc2626'};">৳${fmtMoney(b.balance)}</div>
        </div>
    `).join('');
    dd.style.display = 'block';
}
function closeDropdown() { document.getElementById('bankDropdown').style.display = 'none'; }
function selectBank(b) {
    selectedBank = b;
    document.getElementById('bankSearch').value = b.name;
    document.getElementById('chipBankName').textContent = b.name;
    document.getElementById('selectedBankChip').style.display = 'inline-flex';
    document.getElementById('loadBtn').disabled = false;
    closeDropdown();
}
function clearSelectedBank() {
    selectedBank = null;
    document.getElementById('bankSearch').value = '';
    document.getElementById('selectedBankChip').style.display = 'none';
    document.getElementById('loadBtn').disabled = true;
    document.getElementById('ledgerArea').style.display = 'none';
    document.getElementById('placeholderArea').style.display = 'block';
}

// ── Click a row in "Recent Bank Transactions" to jump straight into that
// account's full ledger — no need to type-search first.
function openBankFromRow(b) {
    selectBank(b);
    resetLedgerFilters();
    loadLedger();
}

// Every account opens the same way: its WHOLE ledger, newest first, no search,
// "All" lit. Nothing about the account you were just looking at follows you into
// the next one.
//
// This has to clear the HIDDEN bar (#datePreset, #dateFrom, #dateTo), because
// that is what loadLedger() reads through resolveDates(). Clearing only the
// visible boxes was the bug the owner hit on 2026-08-20: pick Today on one bank,
// open another, and the request still carried today's dates while no range
// button was lit — an empty statement with nothing on screen explaining why.
// The visible bar and the hidden one are the same filter and must be reset in
// one place, which is here.
//
// The sort is reset with them, by mutating LEDGER_SORT rather than replacing it:
// bindSortHeaders() closed over this exact object, so a new one would leave the
// headers driving a state nothing reads.
function resetLedgerFilters() {
    LEDGER_Q = '';
    LEDGER_RANGE = 'all';
    LEDGER_PAGE = 1;

    const s = document.getElementById('lfSearch');
    if (s) {
        s.value = '';
        document.getElementById('lfSearchWrap').classList.remove('has-q');
    }
    setLedgerDateBoxes('', '');

    document.getElementById('datePreset').value = 'all_time';
    document.getElementById('dateFrom').value   = '';
    document.getElementById('dateTo').value     = '';

    LEDGER_SORT.key = 'date';
    LEDGER_SORT.dir = 'desc';
    paintSortIndicators('#ledgerArea', LEDGER_SORT);

    paintLedgerRangeButtons();
}
document.addEventListener('click', e => {
    if (!e.target.closest('#bankSearch') && !e.target.closest('#bankDropdown')) closeDropdown();
});

// ── Load ledger ──────────────────────────────────────────────────────────────
function loadLedger(options = {}) {
    if (!selectedBank) return Promise.resolve();
    const { from, to } = resolveDates();
    const icon = document.getElementById('loadIcon');
    icon.classList.add('fa-spin');
    document.getElementById('loadBtn').disabled = true;

    const params = new URLSearchParams({ bank_id: selectedBank.id });
    if (from) params.set('from', from);
    if (to)   params.set('to', to);

    return fetch(`${LOAD_URL}?${params}`).then(r => r.json())
        .then(data => renderLedger(data, options))
        .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Failed to load statement.' }))
        .finally(() => { icon.classList.remove('fa-spin'); document.getElementById('loadBtn').disabled = false; });
}

function renderLedger(data, options = {}) {
    const { bank, opening_balance, total_debit, total_credit, closing_balance, transactions, date_from, date_to } = data;

    const $id = id => document.getElementById(id);
    // Set text and keep the untruncated value on hover — every line in this card
    // clips rather than wraps, so the tooltip is the only way back to the full
    // string. Pass html:true when the value carries its own ৳/Dr markup.
    const put = (id, text, tip, html) => {
        const el = $id(id);
        if (!el) return;
        if (html) el.innerHTML = text; else el.textContent = text;
        el.title = tip !== undefined ? tip : el.textContent;
    };
    // ৳ and the Dr/Cr tag are punctuation around the number, not part of it, so
    // they are marked up separately and set smaller and dimmer by the sheet.
    // Everything that prints money on this card goes through here, which is what
    // keeps the seven figures in the rail looking like one set.
    const money = v => `<span class="cur">৳</span>${fmtMoney(v)}`;
    const signed = v => money(v) + `<span class="tag">${v < 0 ? 'Cr' : 'Dr'}</span>`;
    const plain  = v => '৳' + fmtMoney(v) + (v < 0 ? ' Cr' : ' Dr');   // for title=

    document.getElementById('ledgerBankName').textContent = bank.name;
    // The account holder, the account's type and the period on screen have no
    // slot in the owner's layout, so they ride on the title's tooltip rather
    // than being dropped from the page altogether.
    const typeText = (bank.type || '').replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
                   + (bank.account_type ? ' · ' + bank.account_type.charAt(0).toUpperCase() + bank.account_type.slice(1) : '');
    // Either bound may be blank — the server treats a missing one as "no bound",
    // so say so rather than printing an em dash where a date should be.
    const periodText = (date_from && date_to && date_from === date_to) ? fmtDate(date_from)
                     : (date_from && date_to) ? (fmtDate(date_from) + ' → ' + fmtDate(date_to))
                     : date_from ? (fmtDate(date_from) + ' onwards')
                     : date_to   ? ('up to ' + fmtDate(date_to))
                     : 'All Time';
    document.getElementById('ledgerBankName').title =
        [bank.name, bank.account_name, typeText, periodText].filter(Boolean).join(' · ');

    // ── Zone 1: the mark ─────────────────────────────────────────────────────
    // Same BANK_BRANDS table the account cards match on, so an account opens
    // wearing the mark it was listed under. Three states, in falling order: the
    // real logo, the brand's initials, or the type glyph for cash. The <img>
    // onerror in the markup demotes a 404 to the middle state on its own.
    const brand  = bankBrand(bank);
    const parts  = splitBankName(bank.name);
    const plate  = $id('ledgerAvatar');
    const plateI = $id('ledgerBankLogo');

    $id('ledgerBankIcon').className = 'fas ' + (typeIcons[bank.type] || typeIcons.bank);
    $id('ledgerBankInitials').textContent = initials(parts.brand);
    // Set for every cash pot, not just the unbranded ones. .is-glyph only takes
    // effect while .is-mono is also on, so a cash account whose logo file loads
    // shows the logo, and one whose file is missing falls back to the wallet or
    // safe glyph rather than to a meaningless two-letter monogram.
    plate.classList.toggle('is-glyph', bank.type === 'cash');

    if (brand.slug) {
        plate.classList.add('is-mono');           // stay on the fallback until it decodes
        plateI.alt = parts.brand;
        plateI.onload = () => plate.classList.remove('is-mono');
        plateI.src = `${BANK_LOGO_BASE}/${brand.slug}.png`;
        if (plateI.complete && plateI.naturalWidth) plate.classList.remove('is-mono');
    } else {
        plate.classList.add('is-mono');
        plateI.removeAttribute('src');
    }

    // A branch we do not hold would leave a line reading "—" under the name,
    // which is noise; the line is hidden outright instead.
    put('infoBranch', bank.branch_name || '');
    $id('lineBranch').style.display = bank.branch_name ? '' : 'none';
    put('infoAccountNumber', bank.account_number || 'Not on file');
    // The chip beside the number: the account's own type where it has one
    // (Current, Savings), otherwise the instrument it is (Bank, Cash, Mobile
    // Banking) so the chip is never blank. Hidden only if we have neither.
    const acType = bank.account_type
        ? bank.account_type.charAt(0).toUpperCase() + bank.account_type.slice(1)
        : (bank.type || '').replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
    put('infoAccountType', acType, acType ? 'Account type · ' + acType : '');
    $id('infoAccountType').style.display = acType ? '' : 'none';

    // ── Zone 2: company and the account's live balance ───────────────────────
    // The glyph is the sidebar rail's own, so the same company wears the same
    // mark in both places. A company with no icon resolved still gets the
    // neutral briefcase, never an empty square.
    put('infoCompany', bank.company_name || 'Unassigned');
    $id('companyIcon').className = bank.company_icon || 'bi bi-briefcase-fill';

    // All time, NOT the period on screen — that one is Closing Balance in the
    // rail below. The two differ whenever a date filter is set.
    const liveBalance = Number(bank.balance) || 0;
    put('infoBalance', signed(liveBalance),
        `Balance on this account today: ${plain(liveBalance)} — the closing balance in the rail is for ${periodText}`, true);

    // ── Zone 3: the latest movement ──────────────────────────────────────────
    // All time, not period-scoped: "latest" means the latest, even when you are
    // looking at January.
    const lt      = data.last_txn;
    const pillEl  = $id('lastTxnPill');
    const amtEl   = $id('lastTxnAmount');
    pillEl.className = 'lh-pill ' + (lt ? (lt.direction === 'in' ? 'lh-pill-in' : 'lh-pill-out') : 'lh-pill-none');
    amtEl.className  = 'lh-last-amt ' + (lt ? (lt.direction === 'in' ? 'lh-amt-in' : 'lh-amt-out') : '');

    if (lt) {
        const isIn = lt.direction === 'in';
        pillEl.querySelector('i').className = isIn ? 'fas fa-arrow-down' : 'fas fa-arrow-up';
        put('lastTxnDir', isIn ? 'Received' : 'Paid Out');
        put('lastTxnAmount', money(lt.amount), '৳' + fmtMoney(lt.amount), true);
        put('lastTxnPurpose', lt.purpose || 'No description recorded');
        // The entry's own date is the accounting day; created_at is when it was
        // typed. A back-dated entry makes them disagree, so the line prints the
        // accounting day and the tooltip carries both.
        put('lastTxnTime', fmtDate(lt.date) + (lt.time ? ' · ' + fmtClock(lt.time) : ''),
            'Dated ' + fmtDate(lt.date) + (lt.time ? ' · recorded ' + lt.time : ''));
    } else {
        pillEl.querySelector('i').className = 'fas fa-minus';
        put('lastTxnDir', 'None');
        put('lastTxnAmount', '—', 'No entries on this account yet');
        put('lastTxnPurpose', 'No transactions yet');
        put('lastTxnTime', '—');
    }

    // ── Band 2: the figures rail ─────────────────────────────────────────────
    // Today's movement is counted server-side on the account rather than summed
    // off these rows, so a period that ends before today still reports the day
    // correctly instead of showing zero.
    const todayIn  = Number(data.today_in)  || 0;
    const todayOut = Number(data.today_out) || 0;
    put('infoTodayIn',  money(todayIn),  'Received today · ' + '৳' + fmtMoney(todayIn), true);
    put('infoTodayOut', money(todayOut), 'Paid out today · ' + '৳' + fmtMoney(todayOut), true);
    $id('infoTodayIn').closest('div').classList.toggle('is-zero', todayIn === 0);
    $id('infoTodayOut').closest('div').classList.toggle('is-zero', todayOut === 0);

    // The account's whole life. Where the selected period holds fewer, the
    // tooltip says so rather than letting the rail look like it disagrees with
    // the rows beneath it.
    const txnTotal = (data.txn_total != null) ? data.txn_total : transactions.length;
    put('infoTxnCount', txnTotal, txnTotal === transactions.length
        ? `${txnTotal} entries on this account`
        : `${txnTotal} entries on this account · ${transactions.length} in ${periodText}`);

    put('summOpening', signed(opening_balance), 'Balance carried into ' + periodText + ': ' + plain(opening_balance), true);
    put('summDebit',   money(total_debit),  'Total received in ' + periodText + ' · ৳' + fmtMoney(total_debit),  true);
    put('summCredit',  money(total_credit), 'Total paid out in ' + periodText + ' · ৳' + fmtMoney(total_credit), true);
    put('summClosing', signed(closing_balance), 'Balance at the end of ' + periodText + ': ' + plain(closing_balance), true);

    const labels = typeLabels[bank.type] || typeLabels.bank;
    document.getElementById('depositLabel').textContent  = labels.deposit;
    document.getElementById('withdrawLabel').textContent = labels.withdraw;

    // A fresh statement (other bank, or other period) drops the ticks: an id
    // picked on one period means nothing on another, and the print links are
    // rebuilt by paintLedgerSelection() once LEDGER_DATA is set below.
    if (!LEDGER_DATA || LEDGER_DATA.bank.id !== bank.id
        || LEDGER_DATA.date_from !== date_from || LEDGER_DATA.date_to !== date_to) {
        LF_PICKS.clear();
        PICKING.lf = false;
        document.getElementById('ledgerArea').classList.remove('is-picking');
    }

    const reconcileEl = $id('reconcileLink');
    if (reconcileEl) reconcileEl.href = `${STATEMENT_PDF_BASE}/${bank.id}/reconciliation`;

    LEDGER_DATA = data;
    // A different account or period starts at page one. Refreshing this same
    // statement after a correction keeps the page being reviewed; the pager
    // clamps it if moving the entry reduced the total number of pages.
    LEDGER_PAGE = options.preservePage ? Number(options.page || LEDGER_PAGE) : 1;
    paintLedgerRows();
    if (PENDING_ENTRY) { const e = PENDING_ENTRY; PENDING_ENTRY = null; landOnEntry(e); }

    document.getElementById('ledgerArea').style.display = 'block';
    document.getElementById('placeholderArea').style.display = 'none';

    // Visible at last, so it can be measured: the bank name and the last
    // transaction's note both change width with the account.
    fitLedgerHeader();

    if (!options.preserveScroll) scrollToLedger();
}

// Bring the statement into view after a bank is opened — it renders below the
// account cards, so without this you land on the same screen you clicked from
// and have to scroll to find what you asked for.
//
// The page scrolls on the window, and layout/header.blade.php is sticky at the
// top, so the offset is measured from the header itself rather than hardcoded:
// if that bar ever changes height, this follows it instead of tucking the
// statement underneath it.
function scrollToLedger() {
    const area = document.getElementById('ledgerArea');
    if (!area) return;

    const header = document.querySelector('header.header');
    const offset = (header ? header.offsetHeight : 0) + 12;
    const top    = area.getBoundingClientRect().top + window.pageYOffset - offset;

    // Honour the OS "reduce motion" setting — the same jump, without the glide.
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    window.scrollTo({ top: Math.max(top, 0), behavior: reduced ? 'auto' : 'smooth' });
}

// The ledger's sort state. Newest first, the house default for every
// transaction table — the entry you just posted is the one you came to see, and
// you should not have to scroll a thousand rows to find it. The statement still
// reads as a statement: paintLedgerRows() flips the Closing/Opening rows to
// match, so the balance the list starts from is always the one printed above it.
// Click a header to change it.
let LEDGER_SORT = { key: 'date', dir: 'desc' };
let LEDGER_DATA = null;

// The statement pages at fifty rows. An account opens on its WHOLE history
// (resetLedgerFilters lights "All"), and the busiest account in the group has
// years of it — drawing every row at once is what made the tab hang.
//
// Measured in Chrome with this table's own row markup: 250 rows took 2.1s to
// draw, 1,000 took 31.6s and 2,000 took 132s, because the old loop appended
// with `tbody.innerHTML +=` and each append re-serialised and re-parsed every
// row already placed. One page of fifty is ~4ms.
//
// Paging is done here, not on the server, deliberately: Opening/Closing, the
// two period totals, the running balance, the search box and a print selection
// all describe the WHOLE period, and every one of them would need a second
// round trip — or a second definition — if the browser only held one page.
const LEDGER_PER_PAGE = 50;
let LEDGER_PAGE = 1;

// The statement's filter bar.
//   LEDGER_Q      lower-cased search text, '' when idle
//   LEDGER_RANGE  which range button is lit, so it survives the reload each one
//                 triggers (the table is redrawn from scratch every time)
let LEDGER_Q = '';
let LEDGER_RANGE = 'all';

// One searchable string per row, built once and kept on the row.
//
// Every column the table prints goes in, plus each amount TWICE: as
// "170,000.00" and as "170000.00". Someone reading a bank SMS types the digits
// without separators, and matching only the formatted form would silently fail
// on exactly the search people actually run.
//
// Cached on the row object because paintLedgerRows re-runs on every keystroke
// and every sort, and a statement can carry hundreds of rows.
function rowHaystack(r) {
    if (r._hay !== undefined) return r._hay;

    const money = v => {
        const n = Number(v) || 0;
        if (!n) return '';
        const s = fmtMoney(n);
        return s + ' ' + s.replace(/,/g, '');
    };

    // Only what the row actually SHOWS. Direction synonyms were tried here
    // ("debit in received deposit" on any money-in row) and taken out again:
    // searching "deposit" then returned every debit, including one described
    // "CRTR/BA MOTIJHEEL BD", which reads as a broken search rather than a
    // clever one. The two status words stay because the Action column prints
    // them, so they are on screen and fair to search.
    //
    // `source` goes in twice: the table prints "bank transaction" with the
    // underscore swapped for a space, so both forms have to match what the eye
    // can see.
    const source = String(r.source || '');

    r._hay = [
        r.date, fmtDMY(r.date), r.recorded_at, r.reference, source, source.replace(/_/g, ' '),
        r.description, r.note, beneficiaryOf(r).name, paidViaLine(r), r.txn_no,
        money(r.debit), money(r.credit), money(r.balance),
        r.is_reversal ? 'reversal' : '',
        r.is_reversed ? 'reversed' : '',
    ].filter(Boolean).join(' ').toLowerCase();

    return r._hay;
}

// "12 of 87 rows" beside the search box. Hidden when idle — a count of
// everything tells you nothing you cannot already see.
function paintLedgerCount(searching, shown, total) {
    const el = document.getElementById('lfCount');
    if (!el) return;
    el.style.display = searching ? '' : 'none';
    if (!searching) return;
    el.classList.toggle('is-none', shown === 0);
    el.textContent = shown === 0
        ? 'No match in ' + total + ' row' + (total === 1 ? '' : 's')
        : shown + ' of ' + total + ' row' + (total === 1 ? '' : 's');
}

// The six range buttons. Each one re-asks the server rather than hiding rows,
// because Opening Balance, the two totals and Closing Balance are the period's
// and only the server can recompute them — see the note on the bar's markup.
//
// It drives the ORIGINAL hidden filter bar's inputs and then calls loadLedger(),
// so there is still exactly one code path that fetches a statement. A second
// one would be a second place for the date maths to drift.
function applyLedgerRange(key) {
    const pad = n => String(n).padStart(2, '0');
    const ymd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const today = new Date();
    const back = n => { const d = new Date(today); d.setDate(d.getDate() - n); return ymd(d); };

    let from = '', to = '';
    if      (key === 'today')     { from = to = ymd(today); }
    else if (key === 'yesterday') { from = to = back(1); }
    else if (key === 'week')      { from = back(6);  to = ymd(today); }   // rolling 7 days, today included
    else if (key === 'month')     { from = back(29); to = ymd(today); }   // rolling 30 days
    else if (key === 'custom') {
        from = document.getElementById('lfFrom').value;
        to   = document.getElementById('lfTo').value;
        if (!from && !to) {
            key = 'all';
        }
        // One date on its own means THAT DAY, not everything from there onwards
        // — the same shape as the Today/Yesterday buttons, which both set the
        // two bounds to one date. Fill the second box and it becomes a range.
        // Swap them if they arrive backwards rather than returning an empty
        // statement and looking broken.
        if (key === 'custom') {
            if      (from && !to) to   = from;
            else if (to && !from) from = to;
            else if (from > to)   { const t = from; from = to; to = t; }
        }
    }

    LEDGER_RANGE = key;
    paintLedgerRangeButtons();

    document.getElementById('datePreset').value = (key === 'all') ? 'all_time' : 'custom';
    document.getElementById('dateFrom').value = from;
    document.getElementById('dateTo').value   = to;

    // Show the bounds the statement was actually built from: a lone date read
    // as one day says so in both boxes rather than leaving the second empty and
    // reading like an open-ended range. Setting .value from script fires no
    // change event, so this cannot loop back into the handler that calls here.
    setLedgerDateBoxes(from, to);

    loadLedger();
}

// The visible text box and the hidden date input are two halves of one control
// and must never disagree, so nothing sets one without the other.
function setLedgerDateBoxes(from, to) {
    [['lfFrom', 'lfFromText', from], ['lfTo', 'lfToText', to]].forEach(([nativeId, textId, ymd]) => {
        const native = document.getElementById(nativeId);
        const text   = document.getElementById(textId);
        if (native) native.value = ymd || '';
        if (text) { text.value = ymd ? fmtDMY(ymd) : ''; markLedgerDateBad(text, false); }
    });
}

// The red state sits on the wrapper — it owns the border and the fill now — and
// on the text itself, so both halves of the control agree something is wrong.
function markLedgerDateBad(text, bad) {
    text.classList.toggle('is-bad', bad);
    const wrap = text.closest('.lf-date');
    if (wrap) wrap.classList.toggle('is-bad', bad);
}

// dd/mm/yyyy in, Y-m-d out — the form the date input and the server both speak.
// Y-m-d is taken as-is because that is what a paste from elsewhere in the ERP
// looks like. Returns '' for an empty box and null for something unreadable, and
// the caller has to tell those apart: empty is a cleared bound, null is a typo.
function parseLedgerDate(str) {
    const t = String(str || '').trim();
    if (!t) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(t)) return t;
    const m = t.match(/^(\d{1,2})\D(\d{1,2})\D(\d{4})$/) || t.match(/^(\d{2})(\d{2})(\d{4})$/);
    if (!m) return null;
    const d = m[1].padStart(2, '0'), mo = m[2].padStart(2, '0');
    if (+d < 1 || +d > 31 || +mo < 1 || +mo > 12) return null;
    return m[3] + '-' + mo + '-' + d;
}

function paintLedgerRangeButtons() {
    document.querySelectorAll('#lfSeg button').forEach(b => {
        b.classList.toggle('is-on', b.dataset.range === LEDGER_RANGE);
    });
    // Nothing in the segment carries data-range="custom" any more, so a custom
    // range lights none of them. That is the point of the tint: the two date
    // inputs go indigo instead, which is what tells you the dates on screen are
    // the ones the statement was actually built from.
    document.getElementById('lfDates').classList.toggle('is-open', LEDGER_RANGE === 'custom');
}

const LEDGER_SORT_VALUES = {
    // Day, then clock time, then id — see txnInstant(). The day alone ties every
    // entry booked today and leaves them oldest-first under a descending arrow.
    date:        r => txnInstant(r),
    reference:   r => (r.reference || '').toLowerCase(),
    source:      r => (r.source || '').toLowerCase(),
    description: r => (r.description || '').toLowerCase(),
    beneficiary: r => beneficiaryOf(r).name.toLowerCase(),
    debit:       r => Number(r.debit) || 0,
    credit:      r => Number(r.credit) || 0,
    balance:     r => Number(r.balance) || 0,
};

// JSON for a single-quoted HTML attribute. JSON.stringify escapes double quotes
// but not single ones, so a remark containing an apostrophe would otherwise end
// the attribute early.
function attrJson(v) { return JSON.stringify(v).replace(/'/g, '&#39;'); }

// Push an edited remark into every copy of that transaction the page holds, so
// the recent-activity feed and an open ledger can never disagree.
//
// COMPANY_DATA owns the row objects RECENT_TXNS points at — renderRecentTransactions
// stores the array by reference — so patching here also survives switching
// companies and back without a reload.
//
// Every matching row is updated, not just the first: a deposit whose contra
// account is another bank in the same company produces two rows for one entry.
function applyRemarksEverywhere(entryId, description, note, beneficiary, beneficiaryPartyId, via) {
    const patch = list => {
        if (!Array.isArray(list)) return;
        list.forEach(r => {
            if (r.entry_id === entryId) {
                r.description = description;
                r.note        = note;
                if (beneficiary !== undefined) {
                    r.beneficiary_person = beneficiary || null;
                    r.beneficiary_party_id = beneficiaryPartyId || null;
                }
                if (via) { r.beneficiary_method = via.method || null; r.beneficiary_bank = via.bank || null; r.beneficiary_ref = via.ref || null; }
                delete r._hay;   // the statement's search cache
            }
        });
    };

    COMPANY_DATA.forEach(c => patch(c.transactions));
    if (LEDGER_DATA) patch(LEDGER_DATA.transactions);

    repaintRecentTransactions();
    paintLedgerRows();
}

// Inline edit controls were consolidated into the statement Action column.
// Keep this renderer empty because both statement and recent-feed descriptions
// share it; correction now starts from one predictable pencil per ledger row.
function descEditButton() { return ''; }

async function openRowCorrection(row) {
    if (!row || !row.entry_id) return;
    if ((row.source === 'bank_transaction' && row.edit_scope === 'description')
        || (row.source === 'schedule_payment_settlement' && /^ADHOC-(PAY|RCV)-PAID-/.test(row.reference || ''))) {
        editTransactionAmount(row.entry_id);
        return;
    }
    let details;
    try {
        const response = await fetch(`${REVERSE_BASE}/${row.entry_id}/correction`, { headers:{'Accept':'application/json'} });
        details = await response.json();
        if (!response.ok || !details.success) throw new Error(details.message || 'Could not load the journal posting.');
    } catch (error) {
        Swal.fire({icon:'error', title:'Unable to review transaction', text:error.message || 'Network error.'});
        return;
    }
    const flow = Number(row.debit) > 0 ? 'Money in' : 'Money out';
    const money = value => `<span style="font-family:Arial,sans-serif">৳</span>${fmtMoney(value || 0)}`;
    const journalRows = details.journal_items.map(item => `<tr><td style="padding:8px;border-bottom:1px solid #e2e8f0"><b>${esc(item.account)}</b>${item.note ? `<div style="font-size:10px;color:#64748b;margin-top:2px">${esc(item.note)}</div>` : ''}</td><td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;color:#15803d">${item.debit > 0 ? money(item.debit) : '—'}</td><td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;color:#dc2626">${item.credit > 0 ? money(item.credit) : '—'}</td></tr>`).join('');
    const audit = details.audit_history || [];
    const auditRows = audit.length ? audit.map(item => `<div style="padding:9px 0;border-bottom:1px solid #e2e8f0"><div style="display:flex;justify-content:space-between;gap:10px"><b>${esc(item.type)} · ${esc(item.editor || 'Unknown user')}</b><span style="color:#64748b">${new Date(item.at).toLocaleString('en-GB')}</span></div><div style="margin-top:4px">${esc(item.summary)}</div>${item.reason ? `<div style="margin-top:3px;color:#64748b"><b>Reason:</b> ${esc(item.reason)}</div>` : ''}</div>`).join('') : '<div style="padding:10px;text-align:center;color:#64748b">No corrections have been made yet.</div>';
    openJournalCorrectionWorkspace(row, details, {flow, money, journalRows, auditRows, auditCount:audit.length});
}

async function openJournalCorrectionWorkspace(row, details, rendered) {
    const transfer = details.transfer;
    const salaryPayment = details.salary_payment;
    const ownerDrawing = details.owner_drawing;
    const style = `<style>
        .tcw{text-align:left;color:#0f172a}.tcw *{box-sizing:border-box}.tcw-panel{animation:tcwIn .18s ease}.tcw-back{display:inline-flex;align-items:center;gap:7px;border:0;background:transparent;color:#2563eb;font-size:12px;font-weight:800;padding:0 0 13px;cursor:pointer}.tcw-heading{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;margin-bottom:15px;border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(135deg,#eff6ff,#fff)}.tcw-heading.danger{border-color:#fecaca;background:linear-gradient(135deg,#fff1f2,#fff)}.tcw-icon{width:38px;height:38px;flex:0 0 38px;display:grid;place-items:center;border-radius:10px;background:#dbeafe;color:#1d4ed8}.tcw-heading.danger .tcw-icon{background:#fee2e2;color:#b91c1c}.tcw-heading h3{margin:0 0 4px;font-size:16px}.tcw-heading p{margin:0;color:#64748b;font-size:11px;line-height:1.45}.tcw-card{padding:16px;border:1px solid #dbe4ef;border-radius:12px;background:#fff}.tcw-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.tcw-field.full{grid-column:1/-1}.tcw-field label,.tcw-reason label{display:block;margin-bottom:6px;color:#334155;font-size:10px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}.tcw-field input,.tcw-field textarea,.tcw-reason textarea,.tcw-confirm input{width:100%;min-height:43px;padding:9px 11px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#0f172a;font:inherit;font-size:12px;outline:none}.tcw-field textarea,.tcw-reason textarea{min-height:84px;resize:vertical}.tcw-field input:focus,.tcw-field textarea:focus,.tcw-reason textarea:focus,.tcw-confirm input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}.tcw-reason{margin-top:14px;padding:13px;border:1px solid #fde68a;border-radius:11px;background:#fffbeb}.tcw-reason-head{display:flex;justify-content:space-between;gap:8px}.tcw-reason-head small{font-size:10px;color:#92400e}.tcw-alert{display:flex;gap:9px;padding:11px 13px;margin-bottom:14px;border:1px solid #fecaca;border-radius:10px;background:#fff1f2;color:#991b1b;font-size:11px;line-height:1.5}.tcw-confirm{margin-top:14px;padding:13px;border:1px dashed #fca5a5;border-radius:11px;background:#fff7f7}.tcw-confirm label{display:flex;justify-content:space-between;color:#991b1b;font-size:11px;font-weight:800;margin-bottom:7px}.tcw-confirm code{padding:3px 7px;border-radius:5px;background:#fee2e2}.tcw-swal{border-radius:15px!important;padding:18px 20px 16px!important}.tcw-swal .swal2-html-container{margin:0!important}.tcw-swal .swal2-actions{width:100%;justify-content:flex-end;margin:16px 0 0!important;padding-top:14px;border-top:1px solid #e2e8f0}.tcw-swal .swal2-confirm,.tcw-swal .swal2-cancel{min-height:40px;border-radius:8px!important;padding:8px 18px!important;font-size:12px!important;font-weight:800!important}.tcw-swal .swal2-confirm{background:#2563eb!important}.tcw-swal .swal2-confirm.tcw-danger{background:#dc2626!important}.tcw-swal .swal2-cancel{background:#64748b!important}@keyframes tcwIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}@media(max-width:640px){.tcw-grid{grid-template-columns:1fr}.tcw-reason-head{display:block}.tcw-swal .swal2-confirm,.tcw-swal .swal2-cancel{flex:1}}
        .corr-choice{min-height:62px;display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:4px;padding:10px 12px;border:1px solid #dbe4ef;border-radius:9px;background:#fff;color:#334155;text-align:left;font:inherit;cursor:pointer;transition:border-color .15s,box-shadow .15s,transform .15s}.corr-choice:hover{border-color:#93c5fd;box-shadow:0 5px 14px rgba(37,99,235,.09);transform:translateY(-1px)}.corr-choice b{font-size:12px}.corr-choice small{display:block;color:#64748b;font-size:10px}
        .tcw-swal .swal2-close{top:10px!important;right:10px!important;width:32px!important;height:32px!important;border-radius:8px!important;color:#64748b!important;font-size:25px!important;transition:background .15s,color .15s}.tcw-swal .swal2-close:hover{background:#f1f5f9!important;color:#0f172a!important}
        .tcw-record{overflow:hidden;border:1px solid #dbe4ef;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.05)}.tcw-record-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-bottom:1px solid #dbeafe;background:linear-gradient(120deg,#eff6ff,#f8fafc)}.tcw-record-head>div{display:flex;align-items:center;gap:10px}.tcw-record-head i{width:34px;height:34px;display:grid;place-items:center;border-radius:9px;background:#dbeafe;color:#1d4ed8}.tcw-record-head b{display:block;font-size:13px}.tcw-record-head small{display:block;margin-top:2px;color:#64748b;font-size:10px}.tcw-record-badge{padding:4px 9px;border-radius:999px;background:#dcfce7;color:#166534;font-size:9px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.tcw-route{display:grid;grid-template-columns:minmax(0,1fr) 150px minmax(0,1fr);align-items:stretch;padding:18px 16px 16px}.tcw-bank{padding:13px 14px;border-radius:11px}.tcw-bank.from{border:1px solid #fecaca;background:#fff7f7}.tcw-bank.to{border:1px solid #a7f3d0;background:#ecfdf5}.tcw-bank small{display:flex;align-items:center;gap:6px;color:#64748b;font-size:9px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.tcw-bank small:before{content:'';width:7px;height:7px;border-radius:50%;background:#ef4444}.tcw-bank.to small:before{background:#10b981}.tcw-bank b{display:block;margin-top:6px;font-size:13px;line-height:1.35}.tcw-route-flow{display:flex;flex-direction:column;align-items:center;justify-content:center;color:#2563eb}.tcw-route-flow b{font-size:15px}.tcw-route-flow span{display:flex;align-items:center;width:100%;gap:7px;margin-top:5px}.tcw-route-flow span:before,.tcw-route-flow span:after{content:'';height:1px;flex:1;background:#bfdbfe}.tcw-breakdown{display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;background:#f8fafc}.tcw-breakdown>div{padding:12px 14px;border-right:1px solid #e2e8f0}.tcw-breakdown>div:last-child{border-right:0;background:#eff6ff}.tcw-breakdown small{display:block;color:#64748b;font-size:9px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.tcw-breakdown b{display:block;margin-top:5px;font-size:13px}.tcw-record-meta{display:grid;grid-template-columns:150px 1fr;gap:0}.tcw-record-meta>div{padding:12px 15px}.tcw-record-meta>div:first-child{border-right:1px solid #e2e8f0}.tcw-record-meta small{display:block;color:#64748b;font-size:9px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.tcw-record-meta span{display:block;margin-top:4px;font-size:11px;color:#334155}@media(max-width:640px){.tcw-route{grid-template-columns:1fr;padding:12px}.tcw-route-flow{padding:10px}.tcw-route-flow span{width:45%}.tcw-breakdown{grid-template-columns:1fr 1fr}.tcw-breakdown>div:nth-child(2){border-right:0}.tcw-breakdown>div{border-bottom:1px solid #e2e8f0}.tcw-record-meta{grid-template-columns:1fr}.tcw-record-meta>div:first-child{border-right:0;border-bottom:1px solid #e2e8f0}}
        .tcw-money-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:13px}.tcw-span-12{grid-column:span 12}.tcw-span-4{grid-column:span 4}.tcw-span-8{grid-column:span 8}.tcw-money{position:relative}.tcw-money:before{content:'৳';position:absolute;left:12px;top:50%;z-index:1;transform:translateY(-50%);color:#0f3b67;font-size:18px;font-weight:800;pointer-events:none}.tcw-money input{padding-left:31px}.tcw-field select{width:100%;min-height:43px;padding:9px 11px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#0f172a;font:inherit;font-size:12px;outline:none}.tcw-field select:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}@media(max-width:640px){.tcw-money-grid>*{grid-column:span 12}}
    </style>`;
    const back = '<button type="button" class="tcw-back"><i class="fas fa-arrow-left"></i> Back to overview</button>';
    const heading = (icon,title,copy,danger=false) => `<div class="tcw-heading${danger?' danger':''}"><span class="tcw-icon"><i class="fas ${icon}"></i></span><div><h3>${title}</h3><p>${copy}</p></div></div>`;
    const reason = (id,placeholder) => `<div class="tcw-reason"><div class="tcw-reason-head"><label for="${id}">Correction reason *</label><small>Saved permanently in audit history</small></div><textarea id="${id}" maxlength="500" placeholder="${placeholder}"></textarea></div>`;
    const transferAccountOptions = selected => { const fallback=details.accounts?.find(a=>String(a.code)==='8510')?.id||''; const chosen=selected||fallback; return '<option value="">— Select account —</option>'+(details.accounts||[]).map(a=>`<option value="${a.id}" ${String(a.id)===String(chosen)?'selected':''}>${esc(a.code)} — ${esc(a.name)}</option>`).join(''); };
    let action = null;
    const result = await Swal.fire({
        title:'', width:880, customClass:{popup:'tcw-swal'}, showCloseButton:true, closeButtonAriaLabel:'Close correction workspace', showCancelButton:true, showConfirmButton:false, cancelButtonText:'Close', allowOutsideClick:() => !Swal.isLoading(), allowEscapeKey:() => !Swal.isLoading(),
        html:`${style}<div class="tcw"><div style="font-size:10px;font-weight:900;letter-spacing:.12em;color:#2563eb;text-transform:uppercase">${transfer?'Transfer':'Journal'} correction workspace</div><h2 style="font-size:20px;margin:4px 0">${esc(details.transaction.reference)}</h2><div style="color:#64748b;font-size:11px">${esc(details.transaction.description)}</div>
        <div id="tcwOverview"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;margin:14px 0">${[['Date',esc(details.transaction.date||'—')],['Server time',esc(details.transaction.recorded_at||'—')],['Flow',rendered.flow],['Debit',rendered.money(row.debit)],['Credit',rendered.money(row.credit)],...(transfer?[['From bank',esc(transfer.from_bank||'—')],['To bank',esc(transfer.to_bank||'—')]]:[])].map(([l,v])=>`<div style="padding:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px"><small style="display:block;color:#64748b;font-weight:800;text-transform:uppercase">${l}</small><b style="display:block;margin-top:4px;font-size:11px">${v}</b></div>`).join('')}</div>
        <div style="border:1px solid #dbe4ef;border-radius:10px;overflow:hidden"><div style="padding:9px 11px;background:#f1f5f9;font-size:11px;font-weight:800">Current journal posting</div><table style="width:100%;border-collapse:collapse;font-size:11px"><thead><tr style="color:#64748b"><th style="padding:7px;text-align:left">ACCOUNT</th><th style="padding:7px;text-align:right">DEBIT</th><th style="padding:7px;text-align:right">CREDIT</th></tr></thead><tbody>${rendered.journalRows}</tbody><tfoot><tr style="font-weight:800"><td style="padding:8px">Balanced total</td><td style="padding:8px;text-align:right">${rendered.money(details.transaction.total_debit)}</td><td style="padding:8px;text-align:right">${rendered.money(details.transaction.total_credit)}</td></tr></tfoot></table></div>
        <details style="margin-top:9px;border:1px solid #dbe4ef;border-radius:9px"><summary style="cursor:pointer;padding:10px 12px;font-size:11px;font-weight:800">Details history (${rendered.auditCount})</summary><div style="padding:0 12px;font-size:11px">${rendered.auditRows}</div></details>${transfer?'<div style="padding:10px;margin-top:10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:9px;color:#9a3412;font-size:11px"><b>Wrong destination bank?</b> Reverse this transfer, then create the corrected transfer. Never change only one bank leg.</div>':''}
        <div style="margin-top:11px;padding:12px;background:#f8fafc;border:1px solid #dbe4ef;border-radius:11px"><div style="font-size:9px;font-weight:900;letter-spacing:.1em;color:#64748b;text-transform:uppercase;margin-bottom:8px">Choose a correction</div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:8px"><button type="button" data-tcw="details" class="corr-choice"><b><i class="fas fa-align-left"></i> Details</b><small>Purpose / note</small></button>${row.can_edit_date?'<button type="button" data-tcw="date" class="corr-choice"><b><i class="fas fa-calendar-day"></i> Date & time</b><small>Correct posting order</small></button>':''}${salaryPayment?'<button type="button" data-tcw="salaryCharge" class="corr-choice" style="color:#1d4ed8"><b><i class="fas fa-receipt"></i> Bank charge</b><small>Adjust salary payment charge</small></button>':''}${ownerDrawing?'<button type="button" data-tcw="drawingAmount" class="corr-choice" style="color:#1d4ed8"><b><i class="fas fa-wallet"></i> Amount & bank charge</b><small>Correct owner drawing</small></button>':''}${transfer?'<button type="button" data-tcw="amount" class="corr-choice" style="color:#1d4ed8"><b><i class="fas fa-scale-balanced"></i> Amount & charges</b><small>Reverse and replace safely</small></button><button type="button" data-tcw="record" class="corr-choice" style="color:#1d4ed8"><b><i class="fas fa-file-lines"></i> Transfer record</b><small>View source record here</small></button>':''}${row.can_reverse?`<button type="button" data-tcw="reverse" class="corr-choice" style="border-color:#fecaca;color:#b91c1c"><b><i class="fas fa-rotate-left"></i> Reverse</b><small>${transfer?'Cancel both bank legs':'Post opposite entry'}</small></button>`:''}</div></div></div>
        <div id="tcwDetails" class="tcw-panel" style="display:none">${back}${heading('fa-align-left',`Correct ${transfer?'transfer':'journal'} details`,`Update the purpose or note without changing ${transfer?'either bank or any amount':'the accounting amount'}.`)}<div class="tcw-card"><div class="tcw-field full"><label for="tcwNote">Purpose / note</label><textarea id="tcwNote" maxlength="255">${esc(row.note||transfer?.remarks||'')}</textarea></div>${reason('tcwDetailsReason',`Explain why the ${transfer?'transfer':'journal'} details are being corrected`)}</div></div>
        <div id="tcwDate" class="tcw-panel" style="display:none">${back}${heading('fa-calendar-check','Correct date & time',`Change the posting order${transfer?'; both bank legs remain linked to the same journal':''}. <b>Server time</b> (when this entry was saved): ${esc(details.transaction.recorded_at||'—')}.`)}<div class="tcw-card"><div class="tcw-grid"><div class="tcw-field"><label for="tcwDateInput">Posting date *</label><input id="tcwDateInput" type="date" value="${esc(details.transaction.date||'')}"></div><div class="tcw-field"><label for="tcwTime">Posting time</label><input id="tcwTime" type="time" value="${esc(details.transaction.entry_time||'')}"></div></div>${reason('tcwDateReason',`Explain why the ${transfer?'transfer':'journal'} date or time must change`)}</div></div>
        ${transfer?`<div id="tcwAmount" class="tcw-panel" style="display:none">${back}${heading('fa-scale-balanced','Correct amount & charges','The original transfer will be reversed and a corrected replacement posted automatically.')}<div class="tcw-alert" style="border-color:#fed7aa;background:#fff7ed;color:#9a3412"><i class="fas fa-shield-halved"></i><span>Both bank balances and all journal lines will stay balanced. The original remains visible in audit history.</span></div><div class="tcw-card"><div class="tcw-money-grid"><div class="tcw-field tcw-span-12"><label>Transfer amount *</label><div class="tcw-money"><input id="tcwAmountInput" type="number" min=".01" step=".01" value="${transfer.amount}"></div></div><div class="tcw-field tcw-span-4"><label>Bank charge</label><div class="tcw-money"><input id="tcwCharge" type="number" min="0" step=".01" value="${transfer.charge_amount}"></div></div><div class="tcw-field tcw-span-8"><label>Charge account</label><select id="tcwChargeAccount">${transferAccountOptions(transfer.charge_account_id)}</select></div><div class="tcw-field tcw-span-4"><label>VAT</label><div class="tcw-money"><input id="tcwVat" type="number" min="0" step=".01" value="${transfer.vat_amount}"></div></div><div class="tcw-field tcw-span-8"><label>VAT account</label><select id="tcwVatAccount">${transferAccountOptions(transfer.vat_account_id)}</select></div></div>${reason('tcwAmountReason','Explain why the transfer amount or charges must change')}<div class="tcw-confirm"><label><span>Confirm replacement *</span><span>Type <code>CONFIRM</code></span></label><input id="tcwAmountConfirm" autocomplete="off" placeholder="Type CONFIRM to continue"></div></div></div>`:''}
        ${salaryPayment?`<div id="tcwSalaryCharge" class="tcw-panel" style="display:none">${back}${heading('fa-receipt','Correct salary bank charge','The salary amount and payroll allocation stay unchanged; only the bank charge posting is adjusted.')}<div class="tcw-alert" style="border-color:#fed7aa;background:#fff7ed;color:#9a3412"><i class="fas fa-scale-balanced"></i><span>The bank credit and expense debit update together. Old and new values remain in correction history.</span></div><div class="tcw-card"><div class="tcw-money-grid"><div class="tcw-field tcw-span-12"><label>Salary payment</label><div class="tcw-money"><input type="number" value="${salaryPayment.amount}" disabled></div></div><div class="tcw-field tcw-span-4"><label>Bank charge</label><div class="tcw-money"><input id="tcwSalaryChargeAmount" type="number" min="0" step=".01" value="${salaryPayment.charge_amount}"></div></div><div class="tcw-field tcw-span-8"><label>Charge account</label><select id="tcwSalaryChargeAccount">${transferAccountOptions(salaryPayment.charge_account_id)}</select></div></div>${reason('tcwSalaryChargeReason','Explain why this salary payment bank charge is being corrected')}<div class="tcw-confirm"><label><span>Final confirmation *</span><span>Type <code>CONFIRM</code></span></label><input id="tcwSalaryChargeConfirm" autocomplete="off" placeholder="Type CONFIRM to continue"></div></div></div>`:''}
        ${ownerDrawing?`<div id="tcwDrawingAmount" class="tcw-panel" style="display:none">${back}${heading('fa-wallet','Correct owner drawing','Update the drawing principal and its bank charge while preserving a permanent audit trail.')}<div class="tcw-alert" style="border-color:#fed7aa;background:#fff7ed;color:#9a3412"><i class="fas fa-scale-balanced"></i><span>Drawing debit, charge expense and bank credit update together. The journal must remain balanced.</span></div><div class="tcw-card"><div class="tcw-money-grid"><div class="tcw-field tcw-span-12"><label>Drawing amount *</label><div class="tcw-money"><input id="tcwDrawingAmountInput" type="number" min=".01" step=".01" value="${ownerDrawing.amount}"></div></div><div class="tcw-field tcw-span-4"><label>Bank charge</label><div class="tcw-money"><input id="tcwDrawingCharge" type="number" min="0" step=".01" value="${ownerDrawing.charge_amount}"></div></div><div class="tcw-field tcw-span-8"><label>Charge account</label><select id="tcwDrawingChargeAccount">${transferAccountOptions(ownerDrawing.charge_account_id)}</select></div></div>${reason('tcwDrawingReason','Explain why this owner drawing amount or bank charge must change')}<div class="tcw-confirm"><label><span>Final confirmation *</span><span>Type <code>CONFIRM</code></span></label><input id="tcwDrawingConfirm" autocomplete="off" placeholder="Type CONFIRM to continue"></div></div></div>`:''}
        ${transfer?`<div id="tcwRecord" class="tcw-panel" style="display:none">${back}<div class="tcw-record"><div class="tcw-record-head"><div><i class="fas fa-building-columns"></i><span><b>Bank transfer record</b><small>Read-only source information</small></span></div><span class="tcw-record-badge">Posted</span></div><div class="tcw-route"><div class="tcw-bank from"><small>Source bank</small><b>${esc(transfer.from_bank||'—')}</b></div><div class="tcw-route-flow"><b>${rendered.money(transfer.amount)}</b><span><i class="fas fa-arrow-right"></i></span></div><div class="tcw-bank to"><small>Destination bank</small><b>${esc(transfer.to_bank||'—')}</b></div></div><div class="tcw-breakdown"><div><small>Transfer amount</small><b>${rendered.money(transfer.amount)}</b></div><div><small>Bank charge</small><b>${rendered.money(transfer.charge_amount)}</b></div><div><small>VAT</small><b>${rendered.money(transfer.vat_amount)}</b></div><div><small>Total source debit</small><b>${rendered.money(Number(transfer.amount||0)+Number(transfer.charge_amount||0)+Number(transfer.vat_amount||0))}</b></div></div><div class="tcw-record-meta"><div><small>Payment date</small><span>${esc(details.transaction.date||'—')}</span></div><div><small>Remarks</small><span>${esc(transfer.remarks||'No remarks recorded')}</span></div></div></div></div>`:''}
        <div id="tcwReverse" class="tcw-panel" style="display:none">${back}${heading('fa-triangle-exclamation',`Reverse this ${transfer?'bank transfer':'journal entry'}`,transfer?'This cancels both bank legs with an equal-and-opposite journal.':'This posts an equal-and-opposite journal while preserving the original entry.',true)}<div class="tcw-card"><div class="tcw-alert"><i class="fas fa-triangle-exclamation"></i><span>${transfer?'<b>Correct procedure for a wrong bank:</b> reverse this transfer first, then use “Create corrected transfer”.':'<b>Permanent accounting action:</b> the original and reversal will both remain visible for audit.'}</span></div>${reason('tcwReverseReason',transfer?'Example: Wrong destination bank selected':'Explain why this journal must be reversed')}<div class="tcw-confirm"><label for="tcwConfirm"><span>Confirm reversal *</span><span>Type <code>CONFIRM</code></span></label><input id="tcwConfirm" autocomplete="off" placeholder="Type CONFIRM to reverse"></div></div></div></div>`,
        didOpen:()=>{const overview=document.getElementById('tcwOverview'),confirm=Swal.getConfirmButton();const open=name=>{action=name;overview.style.display='none';document.querySelectorAll('.tcw-panel').forEach(p=>p.style.display='none');document.getElementById(`tcw${name[0].toUpperCase()+name.slice(1)}`).style.display='block';confirm.style.display=name==='record'?'none':'inline-flex';confirm.textContent=name==='reverse'?'Review reversal':'Review correction';confirm.classList.toggle('tcw-danger',name==='reverse');Swal.resetValidationMessage();};document.querySelectorAll('[data-tcw]').forEach(button=>button.addEventListener('click',()=>open(button.dataset.tcw)));document.querySelectorAll('.tcw-back').forEach(button=>button.addEventListener('click',()=>{action=null;document.querySelectorAll('.tcw-panel').forEach(p=>p.style.display='none');overview.style.display='block';confirm.style.display='none';confirm.classList.remove('tcw-danger');Swal.resetValidationMessage();}));},
        preConfirm:async()=>{let url,payload;if(action==='details'){const correctionReason=document.getElementById('tcwDetailsReason').value.trim();if(correctionReason.length<5){Swal.showValidationMessage('Enter a correction reason of at least 5 characters.');return false;}url=`${REVERSE_BASE}/${row.entry_id}/remarks`;payload={remarks:document.getElementById('tcwNote').value.trim(),correction_reason:correctionReason};}else if(action==='date'){const newDate=document.getElementById('tcwDateInput').value,time=document.getElementById('tcwTime').value,reasonText=document.getElementById('tcwDateReason').value.trim();if(!newDate||reasonText.length<5){Swal.showValidationMessage('Posting date and a correction reason are required.');return false;}url=`${REVERSE_BASE}/${row.entry_id}/date`;payload={new_date:newDate,time:time||null,reason:reasonText};}else if(action==='amount'){const reasonText=document.getElementById('tcwAmountReason').value.trim();if(reasonText.length<5||document.getElementById('tcwAmountConfirm').value!=='CONFIRM'){Swal.showValidationMessage('Enter a reason and type CONFIRM exactly.');return false;}url=`${REVERSE_BASE}/${row.entry_id}/transfer-correction`;payload={amount:document.getElementById('tcwAmountInput').value,charge_amount:document.getElementById('tcwCharge').value||0,charge_account_id:document.getElementById('tcwChargeAccount').value||null,vat_amount:document.getElementById('tcwVat').value||0,vat_account_id:document.getElementById('tcwVatAccount').value||null,reason:reasonText,confirmation:'CONFIRM'};}else if(action==='salaryCharge'){const reasonText=document.getElementById('tcwSalaryChargeReason').value.trim(),confirmation=document.getElementById('tcwSalaryChargeConfirm').value;if(reasonText.length<5||confirmation!=='CONFIRM'){Swal.showValidationMessage('Enter a reason and type CONFIRM exactly.');return false;}url=`${REVERSE_BASE}/${row.entry_id}/salary-bank-charge`;payload={charge_amount:document.getElementById('tcwSalaryChargeAmount').value||0,charge_account_id:document.getElementById('tcwSalaryChargeAccount').value||null,reason:reasonText,confirmation:'CONFIRM'};}else if(action==='drawingAmount'){const reasonText=document.getElementById('tcwDrawingReason').value.trim(),confirmation=document.getElementById('tcwDrawingConfirm').value;if(reasonText.length<5||confirmation!=='CONFIRM'){Swal.showValidationMessage('Enter a reason and type CONFIRM exactly.');return false;}url=`${REVERSE_BASE}/${row.entry_id}/owner-drawing-correction`;payload={amount:document.getElementById('tcwDrawingAmountInput').value,charge_amount:document.getElementById('tcwDrawingCharge').value||0,charge_account_id:document.getElementById('tcwDrawingChargeAccount').value||null,reason:reasonText,confirmation:'CONFIRM'};}else if(action==='reverse'){const reasonText=document.getElementById('tcwReverseReason').value.trim(),confirmation=document.getElementById('tcwConfirm').value;if(reasonText.length<5||confirmation!=='CONFIRM'){Swal.showValidationMessage('Enter a reason and type CONFIRM exactly.');return false;}url=`${REVERSE_BASE}/${row.entry_id}/reverse`;payload={date:details.transaction.date,reason:reasonText};}else{return false;}try{const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)});const data=await response.json();if(!response.ok||data.success===false)throw new Error(data.message||'Correction failed.');return{...data,action};}catch(error){Swal.showValidationMessage(error.message||'Network error.');return false;}},showLoaderOnConfirm:true
    });
    if(!result.isConfirmed)return;
    if(result.value.action==='reverse'){refreshDashboardInPlace();if(transfer){const choice=await Swal.fire({icon:'success',title:'Transfer reversed',text:'Both bank legs were cancelled. Post the corrected transfer when ready.',showCancelButton:true,cancelButtonText:'Stay here',confirmButtonText:'Create corrected transfer',confirmButtonColor:'#2563eb'});if(choice.isConfirmed)window.location.href=transfer.create_url;}else{Swal.fire({toast:true,position:'top-end',icon:'success',title:result.value.message||'Entry reversed',timer:2200,showConfirmButton:false});}return;}
    if(result.value.action==='details')applyRemarksEverywhere(row.entry_id,result.value.description,result.value.note);
    else refreshDashboardInPlace();
    Swal.fire({toast:true,position:'top-end',icon:'success',title:result.value.message||'Correction saved',timer:2200,showConfirmButton:false});
}

async function editTransactionAmount(entryId) {
    let details;
    try {
        const response = await fetch(`${REVERSE_BASE}/${entryId}/amount`, {headers:{Accept:'application/json'}});
        details = await response.json();
        if (!response.ok || !details.success) throw new Error(details.message || 'Could not load transaction details.');
    } catch (error) {
        Swal.fire({icon:'error', title:'Unable to open correction workspace', text:error.message || 'Network error.'});
        return;
    }

    const money = value => `<span style="font-family:Arial,sans-serif">৳</span>${fmtMoney(value || 0)}`;
    const correctionLocked = false;
    const defaultPostingAccount = details.accounts.find(account => String(account.code || '').trim() === '8510');
    const accountOptions = selected => {
        const effectiveAccountId = selected || defaultPostingAccount?.id || '';
        return '<option value="">— Select account —</option>' + details.accounts.map(account =>
            `<option value="${account.id}" ${String(effectiveAccountId) === String(account.id) ? 'selected' : ''}>${esc(account.code)} — ${esc(account.name)}</option>`).join('');
    };
    const partyOptions = ['Agent','Vendor','Customer'].map(kind => {
        const options = BENEFICIARY_PARTIES.filter(p => p.kind === kind).map(p => `<option value="${p.id}" ${String(details.transaction.beneficiary_party_id || '') === String(p.id) ? 'selected' : ''}>${esc(p.name)} — ${esc(p.display_id)}</option>`).join('');
        return options ? `<optgroup label="${kind}s">${options}</optgroup>` : '';
    }).join('');
    const journalRows = details.journal_items.map(item => `<tr><td style="padding:8px;border-bottom:1px solid #e2e8f0"><b>${esc(item.account)}</b>${item.note ? `<div style="font-size:10px;color:#64748b">${esc(item.note)}</div>` : ''}</td><td style="padding:8px;text-align:right;border-bottom:1px solid #e2e8f0;color:#15803d">${item.debit > 0 ? money(item.debit) : '—'}</td><td style="padding:8px;text-align:right;border-bottom:1px solid #e2e8f0;color:#dc2626">${item.credit > 0 ? money(item.credit) : '—'}</td></tr>`).join('');
    const audit = details.audit_history || [];
    const auditRows = audit.length ? audit.map(item => `<div style="padding:9px 0;border-bottom:1px solid #e2e8f0"><div style="display:flex;justify-content:space-between;gap:10px"><b>${esc(item.type)} · ${esc(item.editor || 'Unknown user')}</b><span style="color:#64748b">${new Date(item.at).toLocaleString('en-GB')}</span></div><div style="margin-top:3px">${esc(item.summary)}</div>${item.reason ? `<div style="color:#64748b;margin-top:3px"><b>Reason:</b> ${esc(item.reason)}</div>` : ''}</div>`).join('') : '<div style="padding:12px;text-align:center;color:#64748b">No corrections have been made yet.</div>';
    const backButton = '<button type="button" class="corrBack"><i class="fas fa-arrow-left"></i><span>Back to overview</span></button>';
    const reasonField = (id, placeholder) => `<div class="corr-reason"><div class="corr-field-heading"><label for="${id}">Correction reason <span>*</span></label><small>Required for the permanent audit trail</small></div><textarea id="${id}" rows="3" maxlength="500" placeholder="${placeholder}"></textarea></div>`;
    const panelHeading = (icon, title, copy, tone = 'blue') => `<div class="corr-panel-heading corr-panel-heading--${tone}"><span class="corr-panel-icon"><i class="fas ${icon}"></i></span><div><h3>${title}</h3><p>${copy}</p></div></div>`;
    const workspaceCss = `<style>
        .corr-workspace{font-family:inherit;text-align:left;color:#0f172a}.corr-workspace *{box-sizing:border-box}.corrPanel{animation:corrFade .18s ease}
        .corrBack{display:inline-flex;align-items:center;gap:8px;border:0;background:transparent;color:#2563eb;font-size:12px;font-weight:800;padding:2px 0 14px;cursor:pointer}.corrBack:hover{color:#1d4ed8;transform:translateX(-2px)}
        .corr-panel-heading{display:flex;align-items:flex-start;gap:12px;margin-bottom:18px;padding:14px 16px;border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(135deg,#eff6ff,#f8fbff)}.corr-panel-heading--amber{border-color:#fed7aa;background:linear-gradient(135deg,#fff7ed,#fff)}.corr-panel-heading--red{border-color:#fecaca;background:linear-gradient(135deg,#fff1f2,#fff)}
        .corr-panel-icon{flex:0 0 38px;width:38px;height:38px;display:grid;place-items:center;border-radius:10px;background:#dbeafe;color:#1d4ed8;font-size:15px}.corr-panel-heading--amber .corr-panel-icon{background:#ffedd5;color:#c2410c}.corr-panel-heading--red .corr-panel-icon{background:#fee2e2;color:#b91c1c}.corr-panel-heading h3{margin:0 0 4px;font-size:16px;line-height:1.25}.corr-panel-heading p{margin:0;color:#64748b;font-size:11px;line-height:1.5}.corr-panel-heading--red h3,.corr-panel-heading--red p{color:#991b1b}
        .corr-form-card{padding:16px;border:1px solid #dbe4ef;border-radius:12px;background:#fff;box-shadow:0 5px 18px rgba(15,23,42,.04)}.corr-form-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:13px}.corr-col-12{grid-column:span 12}.corr-col-8{grid-column:span 8}.corr-col-6{grid-column:span 6}.corr-col-4{grid-column:span 4}
        .corr-field label,.corr-field-heading label{display:block;color:#334155;font-size:10px;font-weight:900;letter-spacing:.055em;text-transform:uppercase;margin-bottom:6px}.corr-field label span,.corr-field-heading label span{color:#dc2626}.corr-field input,.corr-field select,.corr-field textarea,.corr-reason textarea,.corr-confirm input{width:100%;min-height:43px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;padding:9px 11px;color:#0f172a;font:inherit;font-size:12px;outline:none;transition:border-color .15s,box-shadow .15s}.corr-field textarea,.corr-reason textarea{min-height:82px;resize:vertical}.corr-field input:focus,.corr-field select:focus,.corr-field textarea:focus,.corr-reason textarea:focus,.corr-confirm input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
        .corr-reason{margin-top:14px;padding:13px;border:1px solid #fde68a;border-radius:11px;background:#fffbeb}.corr-field-heading{display:flex;align-items:center;justify-content:space-between;gap:10px}.corr-field-heading small{color:#92400e;font-size:10px}.corr-reason textarea{border-color:#fcd34d;background:#fff}.corr-impact{display:flex;gap:10px;align-items:flex-start;margin-bottom:14px;padding:11px 13px;border:1px solid #fed7aa;border-radius:10px;background:#fff7ed;color:#9a3412;font-size:11px;line-height:1.5}.corr-impact i{margin-top:2px}.corr-money-input{position:relative}.corr-money-input:before{content:'৳';position:absolute;left:12px;top:50%;transform:translateY(-50%);font-weight:800;color:#475569}.corr-money-input input{padding-left:29px}
        .corr-confirm{margin-top:14px;padding:13px;border:1px dashed #fca5a5;border-radius:11px;background:#fff7f7}.corr-confirm label{display:flex;align-items:center;justify-content:space-between;color:#991b1b;font-size:11px;font-weight:800;margin-bottom:7px}.corr-confirm label code{padding:3px 7px;border-radius:5px;background:#fee2e2;color:#991b1b}.corr-danger-card{padding:16px;border:1px solid #fecaca;border-radius:12px;background:#fffafa}
        .corr-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.corr-action{min-height:62px;display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:4px;padding:10px 12px;border:1px solid #dbe4ef;border-radius:9px;background:#fff;color:#334155;text-align:left;font:inherit;cursor:pointer;transition:border-color .15s,box-shadow .15s,transform .15s}.corr-action:hover{border-color:#93c5fd;box-shadow:0 5px 14px rgba(37,99,235,.09);transform:translateY(-1px)}.corr-action b{font-size:12px}.corr-action small{display:block;color:#64748b;font-size:10px}.corr-action--posting{color:#1d4ed8}.corr-action--danger{border-color:#fecaca;color:#b91c1c}.corr-action--danger small{color:#dc2626}.corr-action--locked{border-color:#fed7aa;background:#fff7ed;color:#9a3412;cursor:not-allowed}.corr-action--locked small{color:#b45309}
        .corr-swal .swal2-close{top:10px!important;right:10px!important;width:32px!important;height:32px!important;border-radius:8px!important;color:#64748b!important;font-size:25px!important;transition:background .15s,color .15s}.corr-swal .swal2-close:hover{background:#f1f5f9!important;color:#0f172a!important}
        .swal2-popup.corr-swal{border-radius:15px!important;padding:18px 20px 16px!important}.corr-swal .swal2-html-container{margin:0!important;overflow:visible}.corr-swal .swal2-actions{width:100%;justify-content:flex-end;margin:16px 0 0!important;padding-top:14px;border-top:1px solid #e2e8f0}.corr-swal .swal2-confirm,.corr-swal .swal2-cancel{min-height:40px;border-radius:8px!important;padding:8px 18px!important;font-size:12px!important;font-weight:800!important}.corr-swal .swal2-confirm{background:#2563eb!important}.corr-swal .swal2-confirm.corr-confirm-danger{background:#dc2626!important}.corr-swal .swal2-cancel{background:#64748b!important}.corr-swal .swal2-validation-message{border-radius:8px;font-size:11px}
        .corr-swal .select2-container{width:100%!important}.corr-swal .select2-container .select2-selection--single{height:43px!important;min-height:43px!important;border:1px solid #cbd5e1!important;border-radius:9px!important;background:#fff!important}.corr-swal .select2-container--default .select2-selection--single .select2-selection__rendered{height:41px!important;line-height:41px!important;padding:0 38px 0 11px!important;color:#0f172a!important;font-size:12px!important}.corr-swal .select2-container--default .select2-selection--single .select2-selection__arrow{height:41px!important;right:7px!important}.corr-swal .select2-container--open .select2-selection--single,.corr-swal .select2-container--focus .select2-selection--single{border-color:#3b82f6!important;box-shadow:0 0 0 3px rgba(59,130,246,.12)!important}.corr-beneficiary-dropdown{border:1px solid #cbd5e1!important;border-radius:9px!important;box-shadow:0 12px 26px rgba(15,23,42,.14)!important;overflow:hidden}.corr-beneficiary-dropdown .select2-search--dropdown{padding:9px!important;background:#f8fafc!important;border-bottom:1px solid #e2e8f0!important}.corr-beneficiary-dropdown .select2-search__field{height:36px!important;padding:7px 10px!important;border:1px solid #cbd5e1!important;border-radius:7px!important;font-size:12px!important;outline:none!important}.corr-beneficiary-dropdown .select2-results__options{max-height:250px!important}.corr-beneficiary-dropdown .select2-results__group{padding:8px 11px 4px!important;background:#f8fafc!important;color:#475569!important;font-size:10px!important;font-weight:900!important;text-transform:uppercase!important;letter-spacing:.05em!important}.corr-beneficiary-dropdown .select2-results__options--nested{max-height:none!important}.corr-beneficiary-dropdown .select2-results__option{padding:8px 11px!important;font-size:12px!important}.corr-beneficiary-dropdown .select2-results__option--highlighted{background:#2563eb!important;color:#fff!important}
        @keyframes corrFade{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}@media(max-width:680px){.corr-col-8,.corr-col-6,.corr-col-4{grid-column:span 12}.corr-actions{grid-template-columns:1fr 1fr}.corr-field-heading{align-items:flex-start;flex-direction:column;gap:0}.corr-swal .swal2-actions{justify-content:stretch}.corr-swal .swal2-confirm,.corr-swal .swal2-cancel{flex:1}.corr-panel-heading{padding:12px}}
    </style>`;

    let activeAction = null;
    const result = await Swal.fire({
        title:'', width:880, customClass:{popup:'corr-swal'}, showCloseButton:true, closeButtonAriaLabel:'Close correction workspace', showCancelButton:true, showConfirmButton:false, cancelButtonText:'Close', allowOutsideClick:() => !Swal.isLoading(), allowEscapeKey:() => !Swal.isLoading(),
        html:`${workspaceCss}<div class="corr-workspace">
            <div style="display:flex;justify-content:space-between;gap:14px"><div><div style="font-size:10px;font-weight:800;letter-spacing:.12em;color:#2563eb;text-transform:uppercase">Transaction correction workspace</div><h2 style="font-size:20px;margin:4px 0">${esc(details.transaction.reference)}</h2><div style="font-size:11px;color:#64748b">${esc(details.transaction.description)}</div></div><span style="height:max-content;padding:5px 10px;border-radius:999px;background:${details.type === 'deposit' ? '#dcfce7' : '#fee2e2'};color:${details.type === 'deposit' ? '#166534' : '#991b1b'};font-size:9px;font-weight:800;text-transform:uppercase">${details.type}</span></div>
            <div id="corrOverview">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(125px,1fr));gap:7px;margin:13px 0">${[['Principal',money(details.amount)],['Charge',money(details.charge_amount)],['VAT',money(details.vat_amount)],['Bank',esc(details.transaction.bank)],['Beneficiary',esc(details.transaction.beneficiary)],['Date',esc(details.transaction.date)],['Server time',esc(details.transaction.recorded_at||'—')]].map(([l,v]) => `<div style="padding:9px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px"><small style="display:block;color:#64748b;font-weight:800;text-transform:uppercase">${l}</small><b style="display:block;margin-top:3px;font-size:11px">${v}</b></div>`).join('')}</div>
                <div style="border:1px solid #dbe4ef;border-radius:10px;overflow:hidden"><div style="padding:9px 11px;background:#f1f5f9;font-size:11px;font-weight:800">Current journal posting</div><table style="width:100%;border-collapse:collapse;font-size:11px"><thead><tr style="color:#64748b"><th style="padding:7px;text-align:left">ACCOUNT</th><th style="padding:7px;text-align:right">DEBIT</th><th style="padding:7px;text-align:right">CREDIT</th></tr></thead><tbody>${journalRows}</tbody><tfoot><tr style="font-weight:800"><td style="padding:8px">Balanced total</td><td style="padding:8px;text-align:right">${money(details.transaction.total_debit)}</td><td style="padding:8px;text-align:right">${money(details.transaction.total_credit)}</td></tr></tfoot></table></div>
                <details style="margin-top:9px;border:1px solid #dbe4ef;border-radius:9px"><summary style="cursor:pointer;padding:10px 12px;font-size:11px;font-weight:800">Details history (${audit.length})</summary><div style="padding:0 12px;font-size:11px">${auditRows}</div></details>
                <div style="margin-top:11px;padding:12px;background:#f8fafc;border:1px solid #dbe4ef;border-radius:11px"><div style="font-size:9px;font-weight:900;letter-spacing:.1em;color:#64748b;text-transform:uppercase;margin-bottom:8px">Choose a correction</div><div class="corr-actions">
                    <button type="button" data-corr="details" class="corr-action"><b><i class="fas fa-align-left"></i> Details</b><small>Party, note and payment info</small></button>
                    <button type="button" data-corr="date" class="corr-action"><b><i class="fas fa-calendar-day"></i> Date & time</b><small>Correct posting order</small></button>
                    ${correctionLocked ? '<div class="corr-action corr-action--locked"><b><i class="fas fa-lock"></i> Posting locked</b><small>Allocated payment</small></div>' : '<button type="button" data-corr="amount" class="corr-action corr-action--posting"><b><i class="fas fa-scale-balanced"></i> Amount & posting</b><small>Amount, charge and VAT</small></button>'}
                    ${details.is_schedule ? '' : '<button type="button" data-corr="reverse" class="corr-action corr-action--danger"><b><i class="fas fa-rotate-left"></i> Reverse</b><small>Post opposite entry</small></button>'}
                </div></div>
            </div>
            <div id="corrPanelDetails" class="corrPanel" style="display:none">${backButton}${panelHeading('fa-address-card','Correct transaction details','Update the linked party, purpose and payment reference. Accounting amounts remain unchanged.')}<div class="corr-form-card"><div class="corr-form-grid"><div class="corr-field corr-col-12"><label for="corrParty">Beneficiary</label><select id="corrParty"><option value="">Unknown / unlinked</option>${!details.transaction.beneficiary_party_id && details.transaction.beneficiary !== 'Not recorded' ? `<option value="custom" selected>${esc(details.transaction.beneficiary)} — unlinked name</option>` : ''}${partyOptions}</select></div><div class="corr-field corr-col-12"><label for="corrNote">Purpose / note</label><input id="corrNote" value="${esc(details.transaction.note || '')}" maxlength="255"></div><div id="corrPayMethodField" class="corr-field corr-col-4"><label for="corrPayMethod">Paid / received via</label><select id="corrPayMethod"><option value="">Not recorded</option><option value="cash" ${details.transaction.beneficiary_method==='cash'?'selected':''}>Cash</option><option value="bank" ${details.transaction.beneficiary_method==='bank'?'selected':''}>Bank transfer</option><option value="cheque" ${details.transaction.beneficiary_method==='cheque'?'selected':''}>Cheque</option></select></div><div id="corrPayBankField" class="corr-field corr-col-4"><label for="corrPayBank">Their bank</label><input id="corrPayBank" value="${esc(details.transaction.beneficiary_bank || '')}" maxlength="150" placeholder="Bank name"></div><div id="corrPayRefField" class="corr-field corr-col-4"><label id="corrPayRefLabel" for="corrPayRef">Account reference</label><input id="corrPayRef" value="${esc(details.transaction.beneficiary_ref || '')}" maxlength="100" placeholder="Account number / reference"></div></div>${reasonField('corrDetailsReason','Explain why these transaction details are being corrected')}</div></div>
            <div id="corrPanelDate" class="corrPanel" style="display:none">${backButton}${panelHeading('fa-calendar-check','Correct date & time',`Change the accounting timeline while preserving the original value in correction history. <b>Server time</b> (when this entry was saved): ${esc(details.transaction.recorded_at||'—')}.`)}<div class="corr-form-card"><div class="corr-form-grid"><div class="corr-field corr-col-6"><label for="corrDate">Posting date <span>*</span></label><input id="corrDate" type="date" value="${esc(details.transaction.raw_date)}"></div><div class="corr-field corr-col-6"><label for="corrTime">Posting time <span>*</span></label><input id="corrTime" type="time" value="${esc(details.transaction.entry_time || '')}"></div></div>${reasonField('corrDateReason','Explain why the posting date or time must change')}</div></div>
            <div id="corrPanelAmount" class="corrPanel" style="display:none">${backButton}${panelHeading('fa-scale-balanced','Correct amount & posting','Review every financial value carefully before replacing the accounting entry.','amber')}<div class="corr-impact"><i class="fas fa-circle-info"></i><span><b>Accounting impact:</b> bank balance, journal lines and the linked advance ledger will update together. The previous values remain in audit history.</span></div><div class="corr-form-card"><div class="corr-form-grid"><div class="corr-field corr-col-12"><label for="corrAmount">Principal amount <span>*</span></label><div class="corr-money-input"><input id="corrAmount" type="number" min=".01" step=".01" value="${details.amount}"></div></div><div class="corr-field corr-col-4"><label for="corrCharge">Bank charge</label><div class="corr-money-input"><input id="corrCharge" type="number" min="0" step=".01" value="${details.charge_amount}"></div></div><div class="corr-field corr-col-8"><label for="corrChargeAccount">Charge account</label><select id="corrChargeAccount">${accountOptions(details.charge_account_id)}</select></div><div class="corr-field corr-col-4"><label for="corrVat">VAT</label><div class="corr-money-input"><input id="corrVat" type="number" min="0" step=".01" value="${details.vat_amount}"></div></div><div class="corr-field corr-col-8"><label for="corrVatAccount">VAT account</label><select id="corrVatAccount">${accountOptions(details.vat_account_id)}</select></div></div>${reasonField('corrAmountReason','Explain why these financial values must change')}<div class="corr-confirm"><label for="corrAmountConfirm"><span>Final confirmation <b>*</b></span><span>Type <code>CONFIRM</code></span></label><input id="corrAmountConfirm" placeholder="Type CONFIRM to continue" autocomplete="off"></div></div></div>
            <div id="corrPanelReverse" class="corrPanel" style="display:none">${backButton}${panelHeading('fa-triangle-exclamation','Reverse entire transaction','Use this only when the original transaction should no longer affect any ledger or balance.','red')}<div class="corr-danger-card"><div class="corr-impact" style="border-color:#fecaca;background:#fff1f2;color:#991b1b"><i class="fas fa-rotate-left"></i><span><b>Permanent accounting action:</b> an equal-and-opposite journal will be posted. The original transaction and this reversal remain permanently visible.</span></div>${reasonField('corrReverseReason','Explain why this entire transaction must be reversed')}<div class="corr-confirm"><label for="corrReverseConfirm"><span>Confirm reversal <b>*</b></span><span>Type <code>CONFIRM</code></span></label><input id="corrReverseConfirm" placeholder="Type CONFIRM to reverse" autocomplete="off"></div></div></div>
        </div>`,
        didOpen: () => {
            const confirm = Swal.getConfirmButton();
            const overview = document.getElementById('corrOverview');
            const paymentMethod = document.getElementById('corrPayMethod');
            const syncPaymentDetailFields = () => {
                const method = paymentMethod.value;
                const showDetails = method === 'bank' || method === 'cheque';
                document.getElementById('corrPayMethodField').classList.toggle('corr-col-12', !showDetails);
                document.getElementById('corrPayMethodField').classList.toggle('corr-col-4', showDetails);
                document.getElementById('corrPayBankField').hidden = !showDetails;
                document.getElementById('corrPayRefField').hidden = !showDetails;
                document.getElementById('corrPayRefLabel').textContent = method === 'cheque' ? 'Cheque number' : 'Account reference';
                document.getElementById('corrPayRef').placeholder = method === 'cheque' ? 'Cheque number' : 'Account number / reference';
            };
            paymentMethod.addEventListener('change', syncPaymentDetailFields);
            syncPaymentDetailFields();
            const initialiseBeneficiarySearch = () => {
                const picker = $('#corrParty');
                if (!picker.length || picker.hasClass('select2-hidden-accessible')) return;
                picker.select2({
                    dropdownParent: $(Swal.getPopup()),
                    dropdownCssClass: 'corr-beneficiary-dropdown',
                    width: '100%',
                    minimumResultsForSearch: 0,
                    placeholder: 'Search agent, vendor, or customer',
                });
            };
            const openPanel = action => {
                activeAction = action; overview.style.display='none'; document.querySelectorAll('.corrPanel').forEach(p => p.style.display='none');
                document.getElementById(`corrPanel${action[0].toUpperCase()+action.slice(1)}`).style.display='block';
                if (action === 'details') initialiseBeneficiarySearch();
                confirm.style.display='inline-flex';
                confirm.textContent = action === 'reverse' ? 'Review reversal' : action === 'amount' ? 'Review accounting changes' : 'Review correction';
                confirm.classList.toggle('corr-confirm-danger', action === 'reverse');
                Swal.resetValidationMessage();
            };
            document.querySelectorAll('[data-corr]').forEach(button => button.addEventListener('click', () => openPanel(button.dataset.corr)));
            document.querySelectorAll('.corrBack').forEach(button => button.addEventListener('click', () => {activeAction=null; document.querySelectorAll('.corrPanel').forEach(p => p.style.display='none'); overview.style.display='block'; confirm.style.display='none'; confirm.classList.remove('corr-confirm-danger'); Swal.resetValidationMessage();}));
        },
        preConfirm: async () => {
            if (!activeAction) return false;
            let url, payload;
            if (activeAction === 'details') {
                const reason=document.getElementById('corrDetailsReason').value.trim(); if(reason.length<5){Swal.showValidationMessage('Enter a correction reason of at least 5 characters.');return false;}
                const partyId=document.getElementById('corrParty').value; const party=BENEFICIARY_PARTY_BY_ID[String(partyId)];
                const payMethod=document.getElementById('corrPayMethod').value; const hasPayDetails=payMethod==='bank'||payMethod==='cheque';
                url=`${REVERSE_BASE}/${entryId}/remarks`; payload={remarks:document.getElementById('corrNote').value.trim(),beneficiary:partyId==='custom'?details.transaction.beneficiary:(party?.name||'Unknown'),beneficiary_party_id:/^\d+$/.test(partyId)?partyId:null,pay_method:payMethod||null,pay_bank:hasPayDetails?document.getElementById('corrPayBank').value.trim():null,pay_ref:hasPayDetails?document.getElementById('corrPayRef').value.trim():null,correction_reason:reason};
            } else if (activeAction === 'date') {
                const reason=document.getElementById('corrDateReason').value.trim(), date=document.getElementById('corrDate').value, time=document.getElementById('corrTime').value;
                if(!date||!time||reason.length<5){Swal.showValidationMessage('Date, time and a correction reason are required.');return false;} url=`${REVERSE_BASE}/${entryId}/date`; payload={new_date:date,time,reason};
            } else if (activeAction === 'amount') {
                const reason=document.getElementById('corrAmountReason').value.trim(); if(reason.length<5||document.getElementById('corrAmountConfirm').value!=='CONFIRM'){Swal.showValidationMessage('Enter a reason and type CONFIRM exactly.');return false;}
                payload={amount:document.getElementById('corrAmount').value,charge_amount:document.getElementById('corrCharge').value||0,charge_account_id:document.getElementById('corrChargeAccount').value||null,vat_amount:document.getElementById('corrVat').value||0,vat_account_id:document.getElementById('corrVatAccount').value||null,reason,confirmation:'CONFIRM'}; url=`${REVERSE_BASE}/${entryId}/amount`;
            } else {
                const reason=document.getElementById('corrReverseReason').value.trim(); if(reason.length<5||document.getElementById('corrReverseConfirm').value!=='CONFIRM'){Swal.showValidationMessage('Enter a reason and type CONFIRM exactly.');return false;} url=`${REVERSE_BASE}/${entryId}/reverse`; payload={date:details.transaction.raw_date,reason};
            }
            try { const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)}); const data=await response.json(); if(!response.ok||data.success===false) throw new Error(data.message||'Correction failed.'); return data; }
            catch(error){Swal.showValidationMessage(error.message||'Network error.');return false;}
        },
        showLoaderOnConfirm:true,
    });
    if (result.isConfirmed) {
        Swal.fire({toast:true,position:'top-end',icon:'success',title:result.value.message||'Correction saved',timer:2400,showConfirmButton:false});
        refreshDashboardInPlace();
    }
}

// Edit the text on a transaction. Numeric corrections use the calculator action
// above so their journal, bank/party balances and audit trail stay synchronized.
// For our own deposit/withdraw entries the server rebuilds "Deposit to <bank>"
// around the new remark. For everything else — party payments, transfers,
// reversals — only the note is written, because their descriptions are derived
// from records this screen does not own. The server decides which; scope here
// only sets the wording.
function editRemarks(entryId, currentNote, scope, currentBeneficiary, currentPartyId, via) {
    const editsDescription = scope === 'description';
    via = via || { method: '', bank: '', ref: '' };
    const box = (id, label, value, max, ph) => `
        <label style="font-size:12px;color:#64748b;display:block;margin:8px 0 4px;text-align:left;">${label}</label>
        <input type="text" id="${id}" value="${esc(value || '')}" maxlength="${max}" placeholder="${ph}" autocomplete="off"
               style="width:100%;padding:7px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">`;
    const reasonBox = `<label style="font-size:12px;color:#64748b;display:block;margin:10px 0 4px;text-align:left;">Correction reason *</label>
        <textarea id="swalDetailReason" rows="2" maxlength="500" placeholder="Why is this correction required?" style="width:100%;padding:7px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;resize:vertical"></textarea>`;
    const partyOptions = ['Agent', 'Vendor', 'Customer'].map(kind => {
        const options = BENEFICIARY_PARTIES.filter(p => p.kind === kind).map(p =>
            `<option value="${p.id}" ${String(currentPartyId || '') === String(p.id) ? 'selected' : ''}>${esc(p.name)} — ${esc(p.display_id)}</option>`
        ).join('');
        return options ? `<optgroup label="${kind}s">${options}</optgroup>` : '';
    }).join('');
    const currentIsCustom = !currentPartyId && currentBeneficiary
        && String(currentBeneficiary).toLowerCase() !== 'unknown';
    const beneficiaryPicker = `
        <label style="font-size:12px;color:#64748b;display:block;margin:8px 0 4px;text-align:left;">Beneficiary / Person Name</label>
        <select id="swalBeneficiaryParty" style="width:100%;padding:7px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">
            <option value="">Unknown / not confirmed</option>
            ${currentIsCustom ? `<option value="custom" selected>${esc(currentBeneficiary)} — unlinked name</option>` : ''}
            ${partyOptions}
        </select>
        <small style="display:block;color:#64748b;margin-top:4px;text-align:left;">Confirming a party links this existing transaction to that party ledger; it does not post the cash twice.</small>`;

    // Our own deposit/withdraw entry: the name and the purpose are edited as
    // the two things they are. Anything else only has a note to edit.
    const dialog = editsDescription
        ? {
            title: 'Edit transaction text',
            html: beneficiaryPicker
                + box('swalPurpose', 'Purpose', currentNote, 255, 'What it is for')
                + `<label style="font-size:12px;color:#64748b;display:block;margin:8px 0 4px;text-align:left;">Paid / received via</label>
                   <select id="swalVia" style="width:100%;padding:7px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;"
                           onchange="const d = this.value === 'bank' || this.value === 'cheque'; document.getElementById('swalViaDetail').hidden = !d; document.getElementById('swalViaRefLabel').textContent = this.value === 'cheque' ? 'Cheque no.' : 'Account no.';">
                       <option value="" ${!via.method ? 'selected' : ''}>— Not recorded —</option>
                       <option value="cash" ${via.method === 'cash' ? 'selected' : ''}>Cash</option>
                       <option value="bank" ${via.method === 'bank' ? 'selected' : ''}>Bank transfer</option>
                       <option value="cheque" ${via.method === 'cheque' ? 'selected' : ''}>Cheque</option>
                   </select>
                   <div id="swalViaDetail" ${via.method === 'bank' || via.method === 'cheque' ? '' : 'hidden'} style="display:grid;grid-template-columns:1fr 1fr;gap:0 10px;">
                       <div>${box('swalViaBank', 'Their bank', via.bank, 150, 'Bank name')}</div>
                       <div><label id="swalViaRefLabel" style="font-size:12px;color:#64748b;display:block;margin:8px 0 4px;text-align:left;">${via.method === 'cheque' ? 'Cheque no.' : 'Account no.'}</label>
                            <input type="text" id="swalViaRef" value="${esc(via.ref)}" maxlength="100" autocomplete="off" style="width:100%;padding:7px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;"></div>
                   </div>${reasonBox}`,
            footer: '"Deposit to …" is generated and stays as it is; the purpose becomes its tail.',
            width: 520,
            didOpen: popup => {
                $('#swalBeneficiaryParty').select2({
                    dropdownParent: $(popup),
                    width: '100%',
                    placeholder: 'Unknown / search agent, vendor, or customer',
                });
            },
            preConfirm: () => {
                const correctionReason = document.getElementById('swalDetailReason').value.trim();
                if (correctionReason.length < 5) { Swal.showValidationMessage('Enter a correction reason of at least 5 characters.'); return false; }
                return {
                beneficiary: document.getElementById('swalBeneficiaryParty').value === 'custom'
                    ? currentBeneficiary
                    : (document.getElementById('swalBeneficiaryParty').value
                        ? BENEFICIARY_PARTY_BY_ID[String(document.getElementById('swalBeneficiaryParty').value)]?.name || ''
                        : 'Unknown'),
                beneficiary_party_id: /^\d+$/.test(document.getElementById('swalBeneficiaryParty').value)
                    ? document.getElementById('swalBeneficiaryParty').value : null,
                remarks:     document.getElementById('swalPurpose').value.trim(),
                pay_method:  document.getElementById('swalVia').value || null,
                pay_bank:    document.getElementById('swalViaBank').value.trim(),
                pay_ref:     document.getElementById('swalViaRef').value.trim(),
                correction_reason: correctionReason,
                };
            },
        }
        : {
            title: 'Edit note',
            html: box('swalPurpose', 'Note', currentNote, 255, 'Optional note') + reasonBox,
            footer: 'This entry\'s description comes from another record, so only the note below it changes.',
            preConfirm: () => {
                const correctionReason = document.getElementById('swalDetailReason').value.trim();
                if (correctionReason.length < 5) { Swal.showValidationMessage('Enter a correction reason of at least 5 characters.'); return false; }
                return { remarks:document.getElementById('swalPurpose').value.trim(), correction_reason:correctionReason };
            },
        };

    Swal.fire(Object.assign({
        showCancelButton: true,
        confirmButtonText: 'Save',
        confirmButtonColor: '#4f46e5',
        focusConfirm: false,
    }, dialog)).then(res => {
        if (!res.isConfirmed) return;

        fetch(`${REVERSE_BASE}/${entryId}/remarks`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify(res.value),
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not update remarks.' });
                return;
            }

            applyRemarksEverywhere(entryId, data.description, data.note, editsDescription ? data.beneficiary : undefined,
                editsDescription ? data.beneficiary_party_id : undefined,
                editsDescription ? { method: data.pay_method, bank: data.pay_bank, ref: data.pay_ref } : undefined);
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' }));
    });
}

// ── Correct a transaction's date ──────────────────────────────────────────
// The one edit here that keeps a history trail (journal_entry_date_edits) —
// see updateJournalDate(). Moving a date can reorder rows and shift every
// balance between the old and new date, so on success this does a full
// refreshDashboardInPlace() rather than editRemarks()'s lightweight in-place
// patch, which only ever touches text and never reorders anything.
function editDate(entryId, currentDate, isReversalLinked, currentTime, customTimeRequired) {
    const warning = isReversalLinked
        ? `<p style="font-size:11px;color:#b45309;margin-top:8px;text-align:left;"><i class="fas fa-triangle-exclamation"></i> This entry has a reversal linked to it — moving its date may change the order they appear in relative to each other.</p>`
        : '';

    Swal.fire({
        title: 'Edit transaction date',
        html: `
            <div style="text-align:left;">
                <p style="font-size:11px;color:#475569;margin-bottom:10px;background:#f1f5f9;padding:8px;border-radius:6px;">
                    <strong>Date</strong> moves this entry to a different day — change this to fix a wrong day.<br>
                    <strong>Time</strong> only reorders it among entries already on the <em>same</em> day — changing time alone can never move it to another day.
                </p>
                <div style="display:flex;gap:10px;margin-bottom:12px;">
                    <div style="flex:1;">
                        <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px;">New date</label>
                        <input type="date" id="swalNewDate" value="${currentDate || ''}" style="width:100%;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px;">Custom time${customTimeRequired ? ' *' : ' (optional)'}</label>
                        <input type="time" id="swalNewTime" value="${currentTime || ''}" style="width:100%;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;">
                    </div>
                </div>
                <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px;">Correction reason *</label>
                <textarea id="swalDateReason" maxlength="500" rows="2" placeholder="e.g. Entered on the wrong day, moving back to when it actually happened" style="width:100%;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;resize:vertical;"></textarea>
                <p style="font-size:11px;color:#94a3b8;margin-top:8px;">Time only decides the order this row shows in among other entries on the same day — it never changes any balance. Leave it blank unless you want this row pinned to a precise spot.</p>
                ${warning}
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Save',
        confirmButtonColor: '#4f46e5',
        focusConfirm: false,
        preConfirm: () => {
            const newDate = document.getElementById('swalNewDate').value;
            if (!newDate) { Swal.showValidationMessage('Pick a date.'); return false; }
            const newTime = document.getElementById('swalNewTime').value;
            if (customTimeRequired && !newTime) { Swal.showValidationMessage('Custom time is required for deposits and withdrawals.'); return false; }
            const reason = document.getElementById('swalDateReason').value.trim();
            if (reason.length < 5) { Swal.showValidationMessage('Enter a correction reason of at least 5 characters.'); return false; }
            return {
                new_date: newDate,
                // null, not '' — the server's date_format:H:i rule rejects an
                // empty string outright; nullable only short-circuits on null.
                time: newTime || null,
                reason,
            };
        },
    }).then(res => {
        if (!res.isConfirmed) return;

        fetch(`${REVERSE_BASE}/${entryId}/date`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify(res.value),
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not update date.' });
                return;
            }

            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: data.message || 'Date updated', timer: 1800, showConfirmButton: false,
            });
            refreshDashboardInPlace();
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' }));
    });
}

// Every row the statement is currently showing, across all its pages: the
// period the server returned, narrowed by the search, in the column order the
// headers are set to. The page is a slice of this and nothing else.
//
// This is also what "shown" means to the tick boxes — see shownPicks() — so
// "select all" still means the whole search result and a print selection can
// span pages.
function ledgerRowsInView() {
    if (!LEDGER_DATA) return [];
    const rows = LEDGER_Q !== ''
        ? LEDGER_DATA.transactions.filter(r => rowHaystack(r).includes(LEDGER_Q))
        : LEDGER_DATA.transactions;
    const mode = document.getElementById('lfTransactionView').value;
    const hidden = ledgerNeutralReversalIds();
    return sortRows(rows.filter(row => mode === 'all' || (mode === 'reversed'
        ? row.is_reversal || row.is_reversed
        : !hidden.has(row.entry_id))), LEDGER_SORT, LEDGER_SORT_VALUES);
}

// Hide complete net-zero pairs within the loaded period, regardless of date.
// Keep a lone side visible when its counterpart falls outside the period.
// The displayed running balances remain the original historical balances.
function ledgerNeutralReversalIds() {
    const hidden = new Set();
    const rows = LEDGER_DATA?.transactions || [];
    const byId = new Map(rows.map(row => [Number(row.entry_id), row]));
    rows.filter(row => row.is_reversal).forEach(reversal => {
        const original = byId.get(Number(reversal.reversed_entry_id));
        if (original
            && Math.abs(Number(original.debit) - Number(original.credit)
                + Number(reversal.debit) - Number(reversal.credit)) < .005) {
            hidden.add(original.entry_id);
            hidden.add(reversal.entry_id);
        }
    });
    return hidden;
}

function openLedgerReversalHistory() {
    if (!LEDGER_DATA) return;
    const rows = LEDGER_DATA.transactions;
    const byId = new Map(rows.map(row => [Number(row.entry_id), row]));
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const reversals = rows.filter(row => row.is_reversal);
    const cards = reversals.map(reversal => {
        const original = byId.get(Number(reversal.reversed_entry_id));
        const line = (row, label) => `<div style="margin:10px 0"><b>${label} · ${escape(row.date)}</b><br>${escape(row.reference)}<br>${escape(row.description || row.note)}<br>In ${fmtMoney(row.debit)} · Out ${fmtMoney(row.credit)}</div>`;
        return `<details style="border:1px solid #ddd6fe;border-radius:10px;padding:12px;margin-bottom:10px;text-align:left"><summary style="cursor:pointer">${escape(original?.reference || 'Original #'+reversal.reversed_entry_id)} — ${fmtMoney(Math.max(Number(reversal.debit), Number(reversal.credit)))}</summary>${original ? line(original, 'Original') : '<p>Original transaction is outside this period.</p>'}${line(reversal, 'Reversal')}${original ? `<b>Net effect: ${fmtMoney(Number(original.debit)-Number(original.credit)+Number(reversal.debit)-Number(reversal.credit))}</b>` : ''}</details>`;
    }).join('');
    Swal.fire({title:'Reversal history', width:760,
        html:`<div style="max-height:65vh;overflow:auto">${cards || 'No reversal entries in this period.'}</div>`,
        confirmButtonText:'View all transactions', showCancelButton:true, cancelButtonText:'Close'
    }).then(result => {
        if (result.isConfirmed) {
            document.getElementById('lfTransactionView').value = 'all';
            LEDGER_PAGE = 1;
            paintLedgerRows();
        }
    });
}

function paintLedgerRows() {
    if (!LEDGER_DATA) return;
    const { opening_balance, total_debit, total_credit, closing_balance, date_from } = LEDGER_DATA;

    // The search narrows the rows and nothing else. Opening/Closing and the two
    // totals are the PERIOD's, so while a query is live they are withheld rather
    // than shown against a subset they do not describe — see searching below.
    const searching  = LEDGER_Q !== '';
    const transactions = ledgerRowsInView();
    document.getElementById('lfReversalCount').textContent = '· ' + LEDGER_DATA.transactions.filter(row => row.is_reversal).length;

    // Clamped rather than trusted: the row count shrinks under a search or an
    // edit, and page 9 of a list that now has two pages must land on page 2,
    // not on an empty table.
    const pageCount = Math.max(1, Math.ceil(transactions.length / LEDGER_PER_PAGE));
    LEDGER_PAGE = Math.min(Math.max(1, LEDGER_PAGE), pageCount);
    const offset   = (LEDGER_PAGE - 1) * LEDGER_PER_PAGE;
    const pageRows = transactions.slice(offset, offset + LEDGER_PER_PAGE);

    paintLedgerCount(searching, transactions.length, LEDGER_DATA.transactions.length);
    paintLedgerSelection();
    paintLedgerPager(transactions.length, pageCount, offset, pageRows.length);

    const tbody = document.getElementById('ledgerBody');
    tbody.innerHTML = '';

    if (searching && !transactions.length) {
        document.getElementById('ledgerEmpty').style.display = 'block';
        return;
    }

    if (!transactions.length && opening_balance === 0) {
        document.getElementById('ledgerEmpty').style.display = 'block';
    } else {
        document.getElementById('ledgerEmpty').style.display = 'none';

        {{-- Opening and Closing are written outside the loop, so they stay
             pinned to the ends whatever the rows are sorted by. Which end each
             one takes follows the date direction: the balance a reader meets
             first has to be the one the rows below it start from, so on the
             newest-first default Closing sits on top and Opening at the foot.
             Any other sort column has no chronology to honour, so those keep the
             statement's natural Opening-top / Closing-bottom frame. --}}
        const openingRow = `
        <tr style="background:#eff6ff;color:#1d4ed8;font-style:italic;font-size:0.75rem;border-bottom:1px solid #b8c4d4;">
            <td class="px-4 py-2.5">—</td>
            {{-- date_from arrives Y-m-d from the date input; run through the same
                 formatter as the rows below so this column reads one way. --}}
            <td class="px-4 py-2.5">${date_from ? fmtDMY(date_from) : 'B/F'}</td>
            <td class="px-4 py-2.5" colspan="3"><i class="fas fa-arrow-right mr-1"></i>Opening Balance</td>
            <td class="px-4 py-2.5 text-right">—</td>
            <td class="px-4 py-2.5 text-right">—</td>
            <td class="px-4 py-2.5 text-right font-mono font-bold">${fmtMoney(opening_balance)} ${opening_balance < 0 ? 'Cr' : 'Dr'}</td>
            <td class="px-4 py-2.5"></td>
        </tr>`;

        const closingRow = `
        <tr style="background:#fffbeb;font-weight:700;font-size:0.85rem;">
            <td class="px-4 py-3" colspan="5"><i class="fas fa-flag-checkered mr-1"></i>Closing Balance</td>
            <td class="px-4 py-3 text-right font-mono text-green-700">${fmtMoney(total_debit)}</td>
            <td class="px-4 py-3 text-right font-mono text-red-600">${fmtMoney(total_credit)}</td>
            <td class="px-4 py-3 text-right font-mono ${closing_balance < 0 ? 'text-red-600' : 'text-blue-700'}">${fmtMoney(closing_balance)} ${closing_balance < 0 ? 'Cr' : 'Dr'}</td>
            <td class="px-4 py-3"></td>
        </tr>`;

        const newestFirst = LEDGER_SORT.key === 'date' && LEDGER_SORT.dir === 'desc';

        // The whole page is built as strings and written ONCE, at the foot of
        // this function. It used to be appended a row at a time with
        // `tbody.innerHTML +=`, which re-serialises and re-parses every row
        // already in the table on each append — quadratic, and the reason a
        // large account froze the tab. See LEDGER_PER_PAGE for the numbers.
        const html = [];

        // Withheld while searching: both describe the whole period, and printing
        // them above a filtered subset would invite someone to read the subset as
        // adding up to them.
        //
        // With pages they keep their meaning by staying at the ENDS of the
        // statement, not of the page: the balance the rows start from heads the
        // first page, the balance they arrive at closes the last. Printing
        // either one on every page would put a period total against fifty rows
        // that do not add up to it.
        if (!searching && LEDGER_PAGE === 1) html.push(newestFirst ? closingRow : openingRow);

        pageRows.forEach((row, i) => {
            const balCls = row.balance < 0 ? 'text-red-600' : 'text-blue-700';

            let actionCell;
            if (row.is_reversal) {
                actionCell = `<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#ede9fe;color:#6d28d9;">Reversal</span>`;
            } else if (row.is_reversed) {
                actionCell = `<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#f1f5f9;color:#64748b;">Reversed</span>`;
            } else if (row.can_reverse || row.can_edit_date || row.edit_scope) {
                actionCell = `<button type="button" onclick='event.stopPropagation(); openRowCorrection(${attrJson(row)})'
                    title="Review and correct this transaction" aria-label="Review and correct this transaction"
                    style="width:30px;height:30px;border-radius:8px;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;font-size:12px;cursor:pointer;display:inline-grid;place-items:center;">
                    <i class="fas fa-pen"></i></button>`;
            } else {
                actionCell = '';
            }

            html.push(`
            <tr class="border-b border-gray-100 hover:bg-gray-50 ${LF_PICKS.has(row.entry_id) ? 'is-picked' : ''}" data-entry-id="${row.entry_id}" data-date="${row.date || ''}" onclick="rowPickClick(this, 'lf', event)">
                {{-- Numbered by position in the whole statement, not in the
                     page: row 51 is row 51 whichever page it is read on. --}}
                <td class="px-4 py-2.5 text-gray-400 whitespace-nowrap"><input type="checkbox" class="lf-pick-all" ${LF_PICKS.has(row.entry_id) ? 'checked' : ''} onchange="togglePick('lf', ${row.entry_id}, this.checked)" aria-label="Select this transaction"> ${offset + i + 1}</td>
                <td class="px-4 py-2.5 whitespace-nowrap text-gray-700">${row.date ? fmtDMY(row.date) : '—'}${customTimeLine(row.entry_time)}</td>
                <td class="px-4 py-2.5">${row.reference ? `<span class="font-mono bg-gray-100 border border-gray-200 rounded px-2 py-0.5 text-xs text-gray-600">${row.reference}</span>` : '<span class="font-mono text-gray-300">—</span>'}</td>
                <td class="px-4 py-2.5">
                    <div class="text-sm text-gray-800">${sourceBadge(row)}${(b => briefCell(b.text.slice(0,60), b.muted))(briefDescription(beneficiaryOf(row).head, LEDGER_DATA.bank ? LEDGER_DATA.bank.name : ''))}${descEditButton(row)}</div>
                    ${row.txn_no ? `<div class="txn-no">${row.txn_no}</div>` : ''}
                    ${noteWorthShowing(row) ? `<div class="text-xs text-gray-400 mt-0.5"><i class="fas fa-note-sticky mr-1"></i>${row.note}</div>` : ''}
                </td>
                {{-- Same split as the Recent Transactions table: the name comes
                     off the description's tail and stands in its own column. --}}
                <td class="px-4 py-2.5">${ledgerBeneficiaryCell(row)}</td>
                {{-- Same colour language as the Recent Transactions table above:
                     debit green (money in), credit red (money out). This drawer
                     opens from a click on that table, so the two must agree. --}}
                <td class="px-4 py-2.5 text-right">${row.debit > 0 ? `<span class="font-mono font-semibold text-green-700">${fmtMoney(row.debit)}</span>` : '<span class="font-mono text-gray-300">—</span>'}</td>
                <td class="px-4 py-2.5 text-right">${row.credit > 0 ? `<span class="font-mono font-semibold text-red-600">${fmtMoney(row.credit)}</span>` : '<span class="font-mono text-gray-300">—</span>'}</td>
                <td class="px-4 py-2.5 text-right"><span class="font-mono text-sm font-bold ${balCls}">${fmtMoney(row.balance)} ${row.balance < 0 ? 'Cr' : 'Dr'}</span></td>
                <td class="px-4 py-2.5 text-center">${actionCell}</td>
            </tr>`);
        });

        if (!searching && LEDGER_PAGE === pageCount) html.push(newestFirst ? openingRow : closingRow);

        tbody.innerHTML = html.join('');
    }
}

// The pager under the table. Drawn only when the period does not fit on one
// page — a short statement keeps the shape it has always had, with nothing
// below it.
//
// The number strip is windowed: first and last are always reachable, the
// current page sits in a run of neighbours, and the gap between is an ellipsis
// rather than four hundred buttons.
function paintLedgerPager(total, pageCount, offset, drawn) {
    const box = document.getElementById('ledgerPager');
    if (!box) return;

    if (pageCount <= 1) { box.style.display = 'none'; return; }
    box.style.display = '';

    const info = document.getElementById('lfPagerInfo');
    if (info) {
        const first = drawn ? offset + 1 : 0;
        const last  = offset + drawn;
        info.textContent = `Showing ${first}–${last} of ${total} transaction${total === 1 ? '' : 's'}`
                         + `  ·  Page ${LEDGER_PAGE} of ${pageCount}`;
    }

    const nav = document.getElementById('lfPagerNav');
    if (!nav) return;

    const WINDOW = 2;                       // neighbours either side of current
    const wanted = new Set([1, pageCount]);
    for (let p = LEDGER_PAGE - WINDOW; p <= LEDGER_PAGE + WINDOW; p++) {
        if (p >= 1 && p <= pageCount) wanted.add(p);
    }
    const pages = [...wanted].sort((a, b) => a - b);

    const parts = [];
    const step = (label, page, disabled, title) =>
        `<button type="button" title="${title}" ${disabled ? 'disabled' : ''} onclick="goLedgerPage(${page})">${label}</button>`;

    parts.push(step('<i class="fas fa-angle-double-left"></i>', 1, LEDGER_PAGE === 1, 'First page'));
    parts.push(step('<i class="fas fa-angle-left"></i>', LEDGER_PAGE - 1, LEDGER_PAGE === 1, 'Previous page'));

    let prev = 0;
    pages.forEach(p => {
        if (prev && p - prev > 1) parts.push('<span class="lf-pager-gap">…</span>');
        parts.push(`<button type="button" class="${p === LEDGER_PAGE ? 'is-on' : ''}" onclick="goLedgerPage(${p})">${p}</button>`);
        prev = p;
    });

    parts.push(step('<i class="fas fa-angle-right"></i>', LEDGER_PAGE + 1, LEDGER_PAGE === pageCount, 'Next page'));
    parts.push(step('<i class="fas fa-angle-double-right"></i>', pageCount, LEDGER_PAGE === pageCount, 'Last page'));

    nav.innerHTML = parts.join('');
}

// Turn to a page. The table's head is brought back under the eye only when it
// has scrolled off — paging from a pager that is already in view should not
// yank the screen around.
function goLedgerPage(page) {
    if (!LEDGER_DATA) return;
    LEDGER_PAGE = page;
    paintLedgerRows();

    const table = document.getElementById('ledgerBody');
    const head  = table && table.closest('table');
    if (!head) return;
    const header = document.querySelector('header.header');
    const offset = (header ? header.offsetHeight : 0) + 12;
    const top    = head.getBoundingClientRect().top;
    if (top < offset) {
        const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: Math.max(top + window.pageYOffset - offset, 0), behavior: reduced ? 'auto' : 'smooth' });
    }
}

// ── Reverse a mistaken deposit/withdraw transaction ───────────────────────────
// Posts an equal-and-opposite journal entry instead of deleting the original,
// so the mistake and its correction both stay visible in the ledger.
//
// Always posted on the ORIGINAL entry's own date, not today's — every real
// case so far has been correcting an old data-entry error, and a reversal
// dated "today" would leave every day between the mistake and today
// double-counting it until someone noticed and backdated it by hand. No
// picker: there has never been a case that wanted a different date, so
// asking every time would just be a click nobody uses.
function reverseTransaction(entryId, originalDate, correctionUrl = null) {
    Swal.fire({
        title: 'Reverse this transaction?',
        html: `
            <p style="text-align:left;font-size:0.9rem;color:#475569;">This posts an equal-and-opposite entry to cancel it out, dated ${originalDate ? fmtDMY(originalDate) : 'the same day'} — right next to the mistake. The original stays in the audit trail.</p>
            <label style="display:block;text-align:left;font-size:12px;font-weight:700;color:#475569;margin:10px 0 4px">Correction reason *</label>
            <textarea id="swalReverseReason" rows="3" maxlength="500" placeholder="Example: Wrong destination bank selected" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:7px;resize:vertical"></textarea>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reverse it',
        confirmButtonColor: '#dc2626',
        preConfirm: () => {
            const reason = document.getElementById('swalReverseReason').value.trim();
            if (reason.length < 5) { Swal.showValidationMessage('Enter a correction reason of at least 5 characters.'); return false; }
            return {reason};
        },
    }).then((result) => {
        if (!result.isConfirmed) return;

        fetch(`${REVERSE_BASE}/${entryId}/reverse`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ date: originalDate, reason:result.value.reason }),
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (correctionUrl) {
                    Swal.fire({icon:'success', title:'Transfer reversed', text:'The original remains in history. You can now post the corrected transfer.',
                        showCancelButton:true, cancelButtonText:'Stay here', confirmButtonText:'Create corrected transfer', confirmButtonColor:'#2563eb'})
                        .then(choice => { if (choice.isConfirmed) window.location.href = correctionUrl; });
                } else {
                    Swal.fire({toast:true, position:'top-end', icon:'success', title:res.message || 'Reversed', timer:1800, showConfirmButton:false});
                }
                // A reversal touches BOTH sides' totals, so the same in-place
                // refresh applies — it re-pulls every company's figures, not
                // just the open one.
                refreshDashboardInPlace();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to reverse transaction.' });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' }));
    });
}

// ── Deposit / Withdraw modal (AJAX) ───────────────────────────────────────────
// select2 for the Contra Account field — searchable, and dynamically excludes
// the currently selected bank's own linked account (picking it would post both
// ledger legs to the same account and cancel itself out).
let excludedContraAccountId = null;
function contraAccountMatcher(params, data) {
    if (!data.id) return $.trim(params.term) === '' ? data : null;
    if (excludedContraAccountId && data.element && String(data.element.getAttribute('data-account-id')) === String(excludedContraAccountId)) {
        return null;
    }
    if ($.trim(params.term) === '') return data;
    return data.text.toUpperCase().indexOf(params.term.toUpperCase()) > -1 ? data : null;
}
$(document).ready(function () {
    $('#txn_contra_account').select2({
        width: '100%',
        placeholder: '-- Select Account --',
        allowClear: true,
        dropdownParent: $('#txnModal .modal-box'),
        matcher: contraAccountMatcher,
    });
    $('#txn_beneficiary_party').select2({
        width: '100%',
        placeholder: 'Search agent, vendor, or customer',
        allowClear: true,
        dropdownParent: $('#txnModal .modal-box'),
        dropdownCssClass: 'beneficiary-dropdown',
        tags: true,
        createTag: function (params) {
            const name = $.trim(params.term);
            if (!name || name.length > 150) return null;
            return { id: 'custom:' + name, text: name, newBeneficiary: true };
        },
        templateResult: function (data) {
            if (data.children || !data.id) return data.text;
            const name = data.element?.dataset.name || data.text;
            const id = data.element?.dataset.displayId;
            const item = $('<span class="beneficiary-option-row"></span>').append($('<span class="beneficiary-option-name"></span>').text(data.newBeneficiary ? 'Use "' + name + '"' : name));
            if (id) item.append($('<span class="beneficiary-option-id"></span>').text(id));
            if (data.newBeneficiary) item.append($('<span class="beneficiary-option-id"></span>').text('New name, no ID'));
            return item;
        },
        templateSelection: function (data) {
            return data.element?.dataset.name || data.text;
        },
    }).on('change', function () {
        const option = this.selectedOptions[0];
        const isCustom = option?.value.startsWith('custom:');
        document.getElementById('txn_beneficiary').value = isCustom ? option.text : (option?.dataset.name || '');
        document.getElementById('txn_beneficiary_id').textContent = option?.dataset.displayId ? 'ID: ' + option.dataset.displayId : '';
        configureBankPartyAction(!isCustom ? option : null);
    });
    $('#txn_purpose').select2({
        width: '100%',
        placeholder: 'Choose a purpose',
        allowClear: true,
        dropdownParent: $('#txnModal .modal-box'),
    }).on('change', paintTxnPurpose);
});

function paintTxnPurpose() {
    const isOther = document.getElementById('txn_purpose').value === 'Others';
    const wrap = document.getElementById('txnCustomPurposeWrap');
    const input = document.getElementById('txn_custom_purpose');

    wrap.hidden = !isOther;
    input.required = isOther;
    if (!isOther) input.value = '';
}

function txnPurposeValue() {
    const selected = document.getElementById('txn_purpose').value;

    return selected === 'Others'
        ? document.getElementById('txn_custom_purpose').value.trim()
        : selected;
}
document.getElementById('txn_custom_purpose').addEventListener('input', function () {
    this.setCustomValidity('');
});

let bankAllocationDocs = [];
function configureBankPartyAction(option) {
    const wrap = document.getElementById('txnPartyActionWrap');
    const action = document.getElementById('txn_party_action');
    const help = document.getElementById('txnPartyActionHelp');
    const partyId = option?.value || null;
    const kind = option?.dataset.partyKind || '';
    const isDeposit = document.getElementById('txn_type').value === 'deposit';
    bankAllocationDocs = [];

    if (!partyId) {
        wrap.hidden = true;
        action.innerHTML = '';
        document.getElementById('txnInvoiceAllocationWrap').hidden = true;
        return;
    }

    wrap.hidden = false;
    if (isDeposit && kind === 'vendor') {
        action.innerHTML = '<option value="vendor_refund">Receive Vendor Refund (Cash In)</option>';
    } else if (isDeposit) {
        action.innerHTML = '<option value="payment">Receive Payment / Advance (Cash In)</option>';
    } else if (kind === 'vendor') {
        action.innerHTML = '<option value="payment">Pay Vendor / Supplier Advance (Cash Out)</option>';
    } else {
        action.innerHTML = '<option value="advance_refund">Return Advance (Cash Out)</option>'
            + '<option value="service_refund">Service Refund (Cash Out)</option>';
    }
    applyBankPartyAction();
}

function applyBankPartyAction() {
    const action = document.getElementById('txn_party_action').value;
    const partyId = document.getElementById('txn_beneficiary_party').value;
    const allocationWrap = document.getElementById('txnInvoiceAllocationWrap');
    const help = document.getElementById('txnPartyActionHelp');

    if (action === 'advance_refund') {
        allocationWrap.hidden = true;
        help.textContent = 'Dr Advance from Customers → Cr selected bank/cash; party ledger Dr.';
        if (BANK_CUSTOMER_ADVANCE_ACCOUNT_ID) $('#txn_contra_account').val(String(BANK_CUSTOMER_ADVANCE_ACCOUNT_ID)).trigger('change');
    } else if (action === 'service_refund') {
        allocationWrap.hidden = true;
        help.textContent = 'Dr Accounts Receivable → Cr selected bank/cash; party ledger Dr.';
        if (BANK_AR_ACCOUNT_ID) $('#txn_contra_account').val(String(BANK_AR_ACCOUNT_ID)).trigger('change');
    } else if (action === 'vendor_refund') {
        allocationWrap.hidden = true;
        help.textContent = 'Dr selected bank/cash → Cr Accounts Payable; vendor ledger Dr.';
        if (BANK_AP_ACCOUNT_ID) $('#txn_contra_account').val(String(BANK_AP_ACCOUNT_ID)).trigger('change');
    } else {
        help.textContent = 'Choose allocation below; unallocated posts to the matching advance account.';
        loadBankPartyInvoices(partyId);
    }
}
document.getElementById('txn_party_action')?.addEventListener('change', applyBankPartyAction);

function renderBankAllocations() {
    const mode = document.getElementById('txn_allocation_mode').value;
    let remaining = Number(document.getElementById('txn_amount').value) || 0;
    const rows = bankAllocationDocs.map((doc, index) => {
        const autoAmount = mode === 'auto' ? Math.min(remaining, Number(doc.due)) : 0;
        remaining = Math.max(0, remaining - autoAmount);
        const amountControl = mode === 'manual'
            ? `<input type="number" class="txn-allocation-amount" data-key="${doc.value}" min="0" max="${doc.due}" step="0.01" value="" style="width:100%;text-align:right;padding:7px;border:1px solid #e2e8f0;border-radius:7px;">`
            : `<div style="text-align:right;font-size:.86rem;font-weight:700;color:${autoAmount > 0 ? '#059669' : '#cbd5e1'};">${autoAmount > 0 ? '৳' + Number(autoAmount).toLocaleString() : '—'}</div>`;
        return `<div style="display:grid;grid-template-columns:1fr 120px;gap:8px;align-items:center;padding:8px 10px;${index ? 'border-top:1px solid #f1f5f9;' : ''}">
            <div><strong style="font-size:.76rem;color:#334155;">${doc.number} · ${doc.service}</strong><br><small style="color:#94a3b8;">Due ৳${Number(doc.due).toLocaleString()}</small></div>
            ${amountControl}
        </div>`;
    }).join('');
    document.getElementById('txnAllocationTable').innerHTML = mode === 'unallocated'
        ? '<div style="padding:10px;color:#64748b;font-size:.76rem;">Advance / general payment; no document will be settled.</div>'
        : (rows || '<div style="padding:10px;color:#d97706;font-size:.76rem;">No matching unpaid document found.</div>');
    document.getElementById('txnInvoiceAllocationHelp').textContent = mode === 'manual'
        ? 'Allocated total must equal the transaction amount.' : (mode === 'auto' ? 'Oldest documents are settled first; linked schedules update too.' : 'No invoice or bill will be settled.');
    const isDeposit = document.getElementById('txn_type').value === 'deposit';
    const controlId = mode === 'unallocated'
        ? (isDeposit ? BANK_CUSTOMER_ADVANCE_ACCOUNT_ID : BANK_SUPPLIER_ADVANCE_ACCOUNT_ID)
        : (isDeposit ? BANK_AR_ACCOUNT_ID : BANK_AP_ACCOUNT_ID);
    if (controlId) $('#txn_contra_account').val(String(controlId)).trigger('change');
}

function loadBankPartyInvoices(partyId) {
    const wrap = document.getElementById('txnInvoiceAllocationWrap');
    bankAllocationDocs = [];
    const canAllocate = !!partyId;
    wrap.hidden = !canAllocate;
    if (!canAllocate) return;
    // A newly selected party is an advance by default. The operator must
    // explicitly choose Auto or Manual before any invoice can be settled.
    document.getElementById('txn_allocation_mode').value = 'unallocated';
    renderBankAllocations();
    fetch(`${BANK_UNPAID_INVOICES_URL}?party_id=${encodeURIComponent(partyId)}`, { headers: { 'Accept': 'application/json' } })
        .then(response => response.json())
        .then(data => {
            const side = document.getElementById('txn_type').value === 'deposit' ? 'sale' : 'payable';
            bankAllocationDocs = (data.invoices || []).filter(doc => doc.side === side);
            renderBankAllocations();
        });
}
document.getElementById('txn_allocation_mode')?.addEventListener('change', renderBankAllocations);
document.getElementById('txn_amount')?.addEventListener('input', () => {
    if (document.getElementById('txn_allocation_mode').value === 'auto') renderBankAllocations();
});

function openTxnModal(type) {
    if (!selectedBank) return;
    const label = (typeLabels[selectedBank.type] || typeLabels.bank)[type];
    document.getElementById('txn_type').value = type;
    document.getElementById('txnModalTitle').innerText = label + ' — ' + selectedBank.name;
    document.getElementById('txnModalSubtitle').innerText = type === 'deposit' ? 'Add money to this account' : 'Remove money from this account';
    document.getElementById('txnSubmitBtn').textContent = label;
    document.getElementById('txnSubmitBtn').style.background = type === 'deposit' ? '#16a34a' : '#dc2626';
    document.getElementById('txnForm').reset();
    $('#txn_purpose').val(null).trigger('change');
    document.getElementById('txn_amount').removeAttribute('max');
    $('#txn_beneficiary_party').val(null).trigger('change');
    $('#txn_beneficiary_party option[value^="custom:"]').remove();
    document.getElementById('txn_type').value = type;
    document.getElementById('txnViaLabel').textContent = type === 'deposit' ? 'Received via' : 'Paid via';
    paintPayVia();
    document.getElementById('txn_date').value = new Date().toISOString().split('T')[0];

    // Time is required and used to be left blank, forcing every deposit/
    // withdraw to be typed by hand even though "now" is right almost always.
    // Defaulted to the moment the modal opens; still fully editable for a
    // transaction that happened earlier.
    const now = new Date();
    const hour24 = now.getHours();
    document.getElementById('txn_hour').value  = String(((hour24 % 12) || 12));
    document.getElementById('txn_minute').value = String(now.getMinutes());
    document.getElementById('txn_ampm').value   = hour24 < 12 ? 'AM' : 'PM';

    // Reference No. was left for the person to type, so most rows went in
    // blank and the server silently fell back to 'BANK-{TYPE}-{YmdHis}' at
    // save time — a reference that existed but nobody ever saw. Pre-filling
    // that exact same shape here means what you see before Cash In/Out is
    // what actually gets saved; still a normal editable field for a real PNR
    // or slip number.
    const pad2 = n => String(n).padStart(2, '0');
    const ymdhis = now.getFullYear() + pad2(now.getMonth() + 1) + pad2(now.getDate())
        + pad2(now.getHours()) + pad2(now.getMinutes()) + pad2(now.getSeconds());
    document.getElementById('txn_reference').value = 'BANK-' + type.toUpperCase() + '-' + ymdhis;

    excludedContraAccountId = selectedBank.account_id;
    $('#txn_contra_account').val(null).trigger('change');

    // form.reset() puts the two account selects back to their Blade-rendered
    // selected option, which is the point — 8510 is the default every time the
    // modal opens, not just the first time.
    updateTxnCutTotal();

    document.getElementById('txnModal').classList.add('active');
}

// WHAT THE ACCOUNT ACTUALLY MOVES BY.
//
// The bank leg is posted net — amount plus the cut on a withdrawal, amount minus
// it on a deposit — so the statement will not show the number typed into Amount.
// This says so before the save rather than after, and stays hidden entirely
// while there is no cut to explain.
function updateTxnCutTotal() {
    const el = document.getElementById('txnCutTotal');
    if (!el) return;

    const num = id => {
        const v = parseFloat(document.getElementById(id).value);
        return isNaN(v) || v < 0 ? 0 : v;
    };
    const amount = num('txn_amount');
    const cut    = num('txn_charge_amount') + num('txn_vat_amount');

    if (cut <= 0) { el.hidden = true; return; }

    const isDeposit = document.getElementById('txn_type').value === 'deposit';
    const net = isDeposit ? amount - cut : amount + cut;
    const fmt = n => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    el.hidden = false;
    el.classList.toggle('is-in', isDeposit && net >= 0);
    el.textContent = (isDeposit
        ? (net >= 0 ? 'Into this account: ৳' : 'Out of this account: ৳')
        : 'Out of this account: ৳') + fmt(Math.abs(net))
        + '  (' + fmt(amount) + (isDeposit ? ' − ' : ' + ') + fmt(cut) + ' charge/VAT)';
}
['txn_amount', 'txn_charge_amount', 'txn_vat_amount'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', updateTxnCutTotal);
});
// Bank and cheque want a bank name and a number; cash wants neither. The
// number's label follows the method. Also called on open, after the reset.
function paintPayVia() {
    const m = document.getElementById('txn_pay_method').value;
    const wantsDetail = m === 'bank' || m === 'cheque';
    document.getElementById('txnPayBankWrap').hidden = !wantsDetail;
    document.getElementById('txnPayRefWrap').hidden  = !wantsDetail;
    document.getElementById('txnPayRefLabel').textContent = m === 'cheque' ? 'Cheque no.' : 'Account no.';
    document.getElementById('txn_pay_ref').placeholder    = m === 'cheque' ? 'Cheque number' : 'Account number';
    if (!wantsDetail) {
        document.getElementById('txn_pay_bank').value = '';
        document.getElementById('txn_pay_ref').value  = '';
    }
}
document.getElementById('txn_pay_method').addEventListener('change', paintPayVia);

// "hh · mm · AM/PM" → "H:i" for the server's date_format:H:i rule, or
// null when no hour was picked (an unpicked minute counts as :00).
function txnTimeValue() {
    const h = document.getElementById('txn_hour').value;
    if (!h) return null;
    const m    = document.getElementById('txn_minute').value || '0';
    const ampm = document.getElementById('txn_ampm').value;
    let hour = Number(h) % 12;
    if (ampm === 'PM') hour += 12;
    return String(hour).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

function closeTxnModal() { document.getElementById('txnModal').classList.remove('active'); }
document.getElementById('txnModal').addEventListener('click', function(e) { if (e.target === this) closeTxnModal(); });

document.getElementById('txnForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!selectedBank) return;
    const purpose = txnPurposeValue();
    const customPurpose = document.getElementById('txn_custom_purpose');
    if (document.getElementById('txn_purpose').value === 'Others' && !purpose) {
        customPurpose.setCustomValidity('Please type the transaction purpose.');
        customPurpose.reportValidity();
        customPurpose.focus();
        return;
    }
    customPurpose.setCustomValidity('');
    const btn = document.getElementById('txnSubmitBtn');
    const originalLabel = btn.textContent;
    btn.disabled = true; btn.textContent = 'Saving…';

    fetch(`${TXN_BASE}/${selectedBank.id}/transaction`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            type:               document.getElementById('txn_type').value,
            amount:              document.getElementById('txn_amount').value,
            date:                document.getElementById('txn_date').value,
            time:                txnTimeValue(),
            contra_account_id:   document.getElementById('txn_contra_account').value,
            reference:           document.getElementById('txn_reference').value,
            beneficiary:         document.getElementById('txn_beneficiary').value,
            beneficiary_party_id: document.getElementById('txn_beneficiary_party').value.startsWith('custom:')
                ? null : (document.getElementById('txn_beneficiary_party').value || null),
            purpose:             purpose,
            pay_method:          document.getElementById('txn_pay_method').value || null,
            pay_bank:            document.getElementById('txn_pay_bank').value,
            pay_ref:             document.getElementById('txn_pay_ref').value,
            allocation_mode:     document.getElementById('txnInvoiceAllocationWrap').hidden ? 'unallocated' : document.getElementById('txn_allocation_mode').value,
            transaction_kind:    document.getElementById('txnPartyActionWrap').hidden ? 'payment' : (document.getElementById('txn_party_action').value || 'payment'),
            invoice_allocations: [...document.querySelectorAll('.txn-allocation-amount')].map(input => ({ key: input.dataset.key, amount: input.value || 0 })),
            charge_amount:       document.getElementById('txn_charge_amount').value || 0,
            charge_account_id:   document.getElementById('txn_charge_account').value || null,
            vat_amount:          document.getElementById('txn_vat_amount').value || 0,
            vat_account_id:      document.getElementById('txn_vat_account').value || null,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeTxnModal();
            // Toast, not a blocking dialog, and no reload: filing five cash
            // entries in a row should be five quick saves, not five page loads
            // that each drop you at the top of the page with the ledger shut.
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: res.message || 'Saved', timer: 1800, showConfirmButton: false,
            });
            refreshDashboardInPlace();
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message || 'Failed to record transaction.' });
        }
    })
    .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Network error.' }))
    .finally(() => { btn.disabled = false; btn.textContent = originalLabel; });
});

// The landing panel's filter bar. Search re-filters what is already loaded and
// so does the range, so both stay correct whichever company is selected and
// neither ever refetches.
(function initCompanyFilter() {
    const box  = document.getElementById('txnSearch');
    const wrap = document.getElementById('txnSearchWrap');
    if (!box) return;

    const run = () => {
        wrap.classList.toggle('has-q', box.value.trim() !== '');
        repaintRecentTransactions();
    };
    box.addEventListener('input', run);
    // Escape clears, which is what every other search field on the web does.
    box.addEventListener('keydown', e => { if (e.key === 'Escape') { box.value = ''; run(); } });
    document.getElementById('txnClear').addEventListener('click', () => { box.value = ''; run(); box.focus(); });

    // The pickers are bounded to the days the feed actually holds, so the
    // calendar simply will not offer a date this table could not answer.
    const w = txnWindow();
    ['caFrom', 'caTo'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.min = w.from; el.max = w.to; }
    });

    document.querySelectorAll('#caSeg button').forEach(b => {
        b.addEventListener('click', () => applyCompanyRange(b.dataset.range));
    });

    // Picking a date applies it straight away — one date shows that day, then
    // filling the second box widens it to a range. Apply still works for anyone
    // who prefers the button. Both boxes empty is left alone: that is someone
    // mid-edit, not a request for an empty table.
    const applyIfAnyDate = () => {
        if (document.getElementById('caFrom').value || document.getElementById('caTo').value) {
            applyCompanyRange('custom');
        }
    };
    document.querySelectorAll('#caFrom, #caTo').forEach(el => el.addEventListener('change', applyIfAnyDate));

    // A click on the wrapper's own padding — the few pixels either side of the
    // calendar button — opens the picker too, so a near miss still works.
    // Guarded: showPicker() throws without a user gesture, and a dead click is a
    // better failure than an uncaught error in a filter bar.
    document.querySelectorAll('#caDates .lf-date').forEach(wrapEl => {
        wrapEl.addEventListener('click', e => {
            if (e.target !== wrapEl) return;
            const native = wrapEl.querySelector('.lf-date-native');
            if (native && native.showPicker) { try { native.showPicker(); } catch (_) {} }
        });
    });

    // The typed half. change fires on blur, so tabbing out of a box applies it
    // exactly as Enter does, and a date that will not parse holds on screen in
    // red rather than being swallowed.
    [['caFromText', 'caFrom'], ['caToText', 'caTo']].forEach(([textId, nativeId]) => {
        const text = document.getElementById(textId);
        if (!text) return;
        const commit = () => {
            const ymd = parseLedgerDate(text.value);
            if (ymd === null) { markLedgerDateBad(text, true); return; }
            markLedgerDateBad(text, false);
            document.getElementById(nativeId).value = ymd;
            text.value = ymd ? fmtDMY(ymd) : '';
            applyIfAnyDate();
        };
        text.addEventListener('change', commit);
        text.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); commit(); } });
    });

    paintCompanyRangeButtons();
    paintCompanyPeriod(false);
})();

// Click a column to sort by it; click it again to flip the direction. Both bank
// tables are bound the same way — the ledger repaints from LEDGER_DATA, so
// sorting it never refetches the statement.
(function initSortableTables() {
    bindSortHeaders('.txn-table', TXN_SORT, repaintRecentTransactions);
    // Re-sorting reorders the whole statement, so the rows under page 6 are not
    // the rows that were under it a moment ago. Back to the top of the new
    // order, which is the row the click was asking to see.
    bindSortHeaders('#ledgerArea', LEDGER_SORT, () => { LEDGER_PAGE = 1; paintLedgerRows(); });

    // ── the statement's filter bar ──────────────────────────────────────────
    const lfSearch = document.getElementById('lfSearch');
    const lfWrap   = document.getElementById('lfSearchWrap');

    if (lfSearch) {
        // Debounced: paintLedgerRows redraws every row, and on a long statement
        // doing that per keystroke is what makes a search box feel heavy.
        let t = null;
        const run = () => {
            LEDGER_Q = lfSearch.value.trim().toLowerCase();
            lfWrap.classList.toggle('has-q', LEDGER_Q !== '');
            // A new query is a new list; reading it from page 6 would show an
            // empty-looking result for a search that in fact matched plenty.
            LEDGER_PAGE = 1;
            paintLedgerRows();
        };
        lfSearch.addEventListener('input', () => { clearTimeout(t); t = setTimeout(run, 140); });
        // Escape clears, which is what every other search field on the web does.
        lfSearch.addEventListener('keydown', e => {
            if (e.key === 'Escape') { lfSearch.value = ''; clearTimeout(t); run(); }
        });
        document.getElementById('lfClear').addEventListener('click', () => {
            lfSearch.value = '';
            clearTimeout(t);
            run();
            lfSearch.focus();
        });
    }

    document.querySelectorAll('#lfSeg button').forEach(b => {
        b.addEventListener('click', () => {
            applyLedgerRange(b.dataset.range);
        });
    });

    // Picking a date applies it straight away — one date shows that day, then
    // filling the second box widens it to a range. Apply still works for anyone
    // who prefers the button. Both boxes empty is left alone: that is someone
    // mid-edit, not a request for an empty statement.
    const applyIfAnyDate = () => {
        const f = document.getElementById('lfFrom').value;
        const t = document.getElementById('lfTo').value;
        if (f || t) applyLedgerRange('custom');
    };

    // The picker half.
    document.querySelectorAll('#lfFrom, #lfTo').forEach(el => {
        el.addEventListener('change', applyIfAnyDate);
    });

    // A click on the wrapper's own padding — the few pixels either side of the
    // calendar button — opens the picker as well, so a near miss still works.
    // Guarded: showPicker() is Chrome 99+/Firefox 101+/Safari 16+ and throws if
    // it is called without a user gesture, and a dead click is a better failure
    // than an uncaught error in a filter bar.
    document.querySelectorAll('#lfDates .lf-date').forEach(wrap => {
        wrap.addEventListener('click', e => {
            if (e.target !== wrap) return;
            const native = wrap.querySelector('.lf-date-native');
            if (native && native.showPicker) { try { native.showPicker(); } catch (_) {} }
        });
    });

    // The typed half. change fires on blur, so tabbing out of a box applies it
    // exactly as Enter does.
    [['lfFromText', 'lfFrom'], ['lfToText', 'lfTo']].forEach(([textId, nativeId]) => {
        const text = document.getElementById(textId);
        if (!text) return;
        const commit = () => {
            const ymd = parseLedgerDate(text.value);
            if (ymd === null) { markLedgerDateBad(text, true); return; }   // a typo holds on screen
            markLedgerDateBad(text, false);
            document.getElementById(nativeId).value = ymd;
            text.value = ymd ? fmtDMY(ymd) : '';
            applyIfAnyDate();
        };
        text.addEventListener('change', commit);
        text.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); commit(); } });
    });
})();

// The journal entry a ?entry= link asked for, honoured once by renderLedger()
// when the statement it belongs to has loaded, then cleared. Declared ABOVE the
// landing IIFE below: openFromQuery() runs inside it, and a `let` further down
// the script would still be in its temporal dead zone at that moment.
let PENDING_ENTRY = null;

// ── Landing state: default to Epal Group, or whichever company was last
// selected this session (so a deposit/reversal reload doesn't bounce you back).
(function initDefaultCompany() {
    if (!COMPANY_DATA.length) return;
    const remembered = sessionStorage.getItem('activeBankCompany');
    const fallback = COMPANY_DATA.find(c => c.name.toLowerCase() === 'epal group') || COMPANY_DATA[0];
    const initial = (remembered && COMPANY_DATA.find(c => c.name === remembered)) ? remembered : fallback.name;
    selectCompany(initial);
    // Re-open whatever account was on screen before a reload, and scroll back to
    // it. A no-op on a normal visit — nothing is stored unless something was
    // about to reload the page.
    restorePosition();
    // ?bank=&entry= — arrive on one account's ledger, landed on one entry. The
    // Party Statement's voucher links come in this way. A no-op without the
    // params, and after restorePosition() so an explicit link wins over memory.
    openFromQuery();
})();

function openFromQuery() {
    let q;
    try { q = new URLSearchParams(window.location.search); } catch (e) { return; }
    const bankId = Number(q.get('bank'));
    if (!bankId) return;
    for (const company of COMPANY_DATA) {
        const bank = (company.banks || []).find(b => Number(b.id) === bankId);
        if (!bank) continue;
        selectCompany(company.name);
        selectBank(bank);
        PENDING_ENTRY = Number(q.get('entry')) || null;
        loadLedger();
        return;
    }
}

// Turn to the page the entry is on, mark its row and bring it under the eye.
// Runs after the statement has painted; the search box is left alone so the
// row is seen in its place among its neighbours, not as a one-row result.
function landOnEntry(entryId) {
    const idx = ledgerRowsInView().findIndex(r => Number(r.entry_id) === Number(entryId));
    if (idx < 0) return;
    LEDGER_PAGE = Math.floor(idx / LEDGER_PER_PAGE) + 1;
    paintLedgerRows();
    const tr = document.querySelector(`#ledgerBody tr[data-entry-id="${entryId}"]`);
    if (!tr) return;
    tr.classList.add('is-landed');
    // After renderLedger()'s own scrollToLedger() has had its frame.
    setTimeout(() => tr.scrollIntoView({ block: 'center', behavior: 'smooth' }), 250);
}
</script>
@endsection
