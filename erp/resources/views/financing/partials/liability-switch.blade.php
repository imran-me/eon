{{--
    Which KIND of liability is on screen.

    Only cross-company is built. The rest are declared here deliberately: the
    owner's plan is for this desk to hold several kinds of internal liability,
    and naming them now means the next one is added as a chip on this switch
    rather than as a whole new screen somewhere else.

    They are <button disabled>, not links. A chip that navigates to an empty
    page reads as a broken feature; one that says "soon" reads as a plan. No
    route is referenced for them either, so nothing here can throw on a server
    whose route cache predates them.

    Expects: $role
--}}
@php
    $liaTypes = [
        [
            'key'   => 'cross-company',
            'label' => 'Cross-Company',
            'icon'  => 'fa-right-left',
            'hint'  => 'One company’s expense paid from another’s bank account',
            'ready' => true,
        ],
        [
            'key'   => 'vendor',
            'label' => 'Vendor Dues',
            'icon'  => 'fa-truck-field',
            'hint'  => 'Bills received and not yet paid',
            'ready' => false,
        ],
        [
            'key'   => 'staff',
            'label' => 'Staff Claims',
            'icon'  => 'fa-user-clock',
            'hint'  => 'Money staff spent that the company has not paid back',
            'ready' => false,
        ],
        [
            'key'   => 'statutory',
            'label' => 'Statutory',
            'icon'  => 'fa-landmark',
            'hint'  => 'VAT, AIT and other dues to the government',
            'ready' => false,
        ],
        [
            'key'   => 'advance',
            'label' => 'Customer Advances',
            'icon'  => 'fa-hand-holding-dollar',
            'hint'  => 'Money taken for work not yet delivered',
            'ready' => false,
        ],
    ];

    // Whitelisted, and anything unknown falls back to the one kind that works —
    // a hand-typed ?type= must not render a blank desk.
    $liaActive = request()->query('type');
    $liaReady  = collect($liaTypes)->where('ready', true)->pluck('key')->all();
    $liaActive = in_array($liaActive, $liaReady, true) ? $liaActive : 'cross-company';
@endphp

<div class="lia-switch" role="group" aria-label="Kind of liability">
    @foreach($liaTypes as $t)
        @if($t['ready'])
            <a href="{{ route('role.financing.index', ['role' => $role, 'book' => 'liability', 'type' => $t['key']]) }}"
               class="lia-chip {{ $liaActive === $t['key'] ? 'is-on' : '' }}"
               title="{{ $t['hint'] }}"
               @if($liaActive === $t['key']) aria-current="page" @endif>
                <i class="fas {{ $t['icon'] }}"></i>
                <span>{{ $t['label'] }}</span>
            </a>
        @else
            <button type="button" class="lia-chip is-soon" disabled
                    title="{{ $t['hint'] }} — not built yet">
                <i class="fas {{ $t['icon'] }}"></i>
                <span>{{ $t['label'] }}</span>
                <em>soon</em>
            </button>
        @endif
    @endforeach
</div>

<style>
    /* Scoped to this switch. Prefixed .lia- so it cannot reach the .fin- desk
       around it or any other company's screens.

       Geometry, colour and the filled-active treatment are taken from
       layout/payroll-tabs.blade.php's .pr-switcher / .pr-cobtn — the company
       switcher on the Payroll toolbar. Two switchers doing the same job on two
       desks of the same ERP should be the same control, so the numbers below
       are copied from it rather than chosen again: 6px gap, 6px/11px padding,
       7px radius, 11.5px/600 text, #cbd5e1 outline, and the #2563eb→#1d4ed8
       fill on the active one.

       Not @extend-ed or shared: .pr-* is defined inside the payroll toolbar's
       own <style> and only loads on payroll pages. Reaching for it here would
       mean lifting that CSS somewhere global, which is a change to every
       company's pages to make one desk tidier. */
    .lia-switch {
        display: flex;
        flex-wrap: wrap;
        flex-shrink: 0;
        gap: 4px;
    }
    /* A size down from the payroll switcher's 6px/11px · 11.5px. This bar sits
       inline with the page title rather than alone on a toolbar row, and five
       chips at the full size crowded it — the payroll one carries the same
       proportions at its own 640px breakpoint. */
    .lia-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        flex: 0 0 auto;
        padding: 4px 8px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: transparent;
        font-size: 10.5px;
        font-weight: 600;
        line-height: 1.35;
        color: #0f172a;
        text-decoration: none;
        white-space: nowrap;
        user-select: none;
        cursor: pointer;
        transition: transform .1s ease-out, background .15s, border-color .15s, box-shadow .15s;
    }
    .lia-chip i { font-size: 9.5px; opacity: .7; }
    .lia-chip:hover { border-color: #94a3b8; transform: translateY(-1px); text-decoration: none; color: #0f172a; }
    .lia-chip:active { transform: translateY(0); }

    /* The one that is on takes the filled primary treatment. */
    .lia-chip.is-on {
        background: linear-gradient(160deg, #2563eb, #1d4ed8);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 1px 5px rgb(37 99 235 / .26);
    }
    .lia-chip.is-on i { opacity: 1; }
    .lia-chip.is-on:hover { color: #fff; transform: none; }

    /* Not built yet. Dimmed and inert, and saying so — cursor:not-allowed on a
       disabled button is the browser's own vocabulary for "this does nothing".
       The outline goes dashed so it reads as a placeholder even before the
       "soon" badge is noticed, and it must not lift on hover like a live one. */
    .lia-chip.is-soon {
        color: #94a3b8;
        border-style: dashed;
        border-color: #e2e8f0;
        cursor: not-allowed;
    }
    .lia-chip.is-soon:hover { border-color: #e2e8f0; color: #94a3b8; transform: none; }
    .lia-chip.is-soon em {
        font-style: normal;
        font-size: 8.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #94a3b8;
        background: #f1f5f9;
        border-radius: 999px;
        padding: 1px 4px;
    }

    /* Narrow screens: the switch drops under the title rather than squeezing
       it, and scrolls sideways instead of wrapping into three ragged rows. */
    @media (max-width: 900px) {
        .lia-switch {
            width: 100%;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    }

    /* The app's dark shim recolours by class and these set their own hex, so
       they need explicit overrides — same three rules the payroll switcher
       carries, for the same reason. The active chip keeps its blue fill. */
    html.dark .lia-chip { color: #cbd5e1; border-color: #475569; }
    html.dark .lia-chip:hover { border-color: #64748b; color: #e2e8f0; }
    html.dark .lia-chip.is-on { color: #fff; border-color: transparent; }
    html.dark .lia-chip.is-soon { color: #64748b; border-color: #334155; }
    html.dark .lia-chip.is-soon:hover { border-color: #334155; color: #64748b; }
    html.dark .lia-chip.is-soon em { background: #1e293b; color: #64748b; }
</style>
