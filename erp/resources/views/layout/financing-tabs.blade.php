{{--
    Capital & Financing toolbar — the three books of the loan desk.

    Same shape as layout/bank-tabs.blade.php, and deliberately so: two desks
    that navigate the same way should look the same. Prefixed .fin- rather than
    reusing .bk- so a change to one desk's toolbar cannot silently move the
    other's.

    Unlike the bank desk these are not separate routes — one screen renders all
    three books and switches on ?book=. The tab is therefore a link to the same
    route with a different query, and "active" is read from the $book the
    controller resolved, never from the raw request.

    Self-contained on purpose: it resolves its own $role, because the including
    views define their own further down.
--}}
@php
    $finTabUser = auth()->user();
    $finTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($finTabUser)->getRoleNames()->first() ?? '');

    $finTabBooks = [
        [
            'key'   => 'borrowed',
            'label' => 'Loans We Took',
            'icon'  => 'fa-building-columns',
            'hint'  => 'Bank loans, car and equipment EMI, working capital',
        ],
        [
            'key'   => 'lent',
            'label' => 'Loans We Gave',
            'icon'  => 'fa-hand-holding-dollar',
            'hint'  => 'Money lent out, and services taken now and paid monthly',
        ],
        [
            'key'   => 'employee',
            'label' => 'Employee Loans',
            'icon'  => 'fa-user-shield',
            'hint'  => 'Read-only mirror of the payroll loan desk',
        ],
        [
            'key'   => 'capital',
            'label' => 'Capital & Drawings',
            'icon'  => 'fa-sack-dollar',
            'hint'  => 'Money the owner puts in, and profit taken out',
        ],
        [
            'key'   => 'categories',
            'label' => 'Categories',
            'icon'  => 'fa-sliders',
            'hint'  => 'Set up loan categories and sub-categories',
        ],
    ];

    // A missing route must never take the page down with it — the desk may be
    // deployed before its routes are cached on the server.
    $finTabHasRoute = \Illuminate\Support\Facades\Route::has('role.financing.index');
@endphp

@if($finTabHasRoute)
<div class="fin-toolbar">
    <nav class="fin-navband" aria-label="Financing books">
        @foreach($finTabBooks as $finTabBook)
            @php
                $finTabActive = ($book ?? 'borrowed') === $finTabBook['key'];
                $finTabUrl = route('role.financing.index', ['role' => $finTabRole, 'book' => $finTabBook['key']]);
            @endphp
            <a href="{{ $finTabUrl }}"
               class="fin-navtab {{ $finTabActive ? 'is-active' : '' }}"
               title="{{ $finTabBook['hint'] }}"
               @if($finTabActive) aria-current="page" @endif>
                <i class="fas {{ $finTabBook['icon'] }}"></i>
                <span>{{ $finTabBook['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>

<style>
    .fin-toolbar{margin:0 0 16px;}
    .p-4 > .fin-toolbar{margin-top:-16px;}
    @media(min-width:768px){ .p-4 > .fin-toolbar{margin-top:-24px;} }

    .fin-navband{display:flex;flex-wrap:wrap;align-items:stretch;gap:0;background:#fff;
        border-bottom:1px solid #e2e8f0;border-radius:10px 10px 0 0;margin-bottom:12px;}
    .fin-navtab{flex:1 0 auto;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:7px;
        text-align:center;padding:11px 14px 12px;margin-bottom:-1px;border-bottom:3px solid transparent;
        font-size:12px;font-weight:700;color:#64748b;white-space:nowrap;text-decoration:none;
        transition:color .15s,border-color .15s,background .15s;}
    .fin-navtab + .fin-navtab{border-left:1px solid #e2e8f0;}
    .fin-navtab i{font-size:11px;opacity:.85;}
    .fin-navtab:hover{color:#0f172a;background:rgba(37,99,235,.09);text-decoration:none;}
    .fin-navtab.is-active{color:#2563eb;border-bottom-color:#2563eb;}
    .fin-navtab.is-active i{opacity:1;}

    html.dark .fin-navband{background:#1e293b;border-bottom-color:#334155;}
    html.dark .fin-navtab{color:#94a3b8;}
    html.dark .fin-navtab + .fin-navtab{border-left-color:#334155;}
    html.dark .fin-navtab:hover{color:#e2e8f0;background:rgba(59,130,246,.14);}
    html.dark .fin-navtab.is-active{color:#60a5fa;border-bottom-color:#60a5fa;}

    @media(max-width:640px){
        .fin-navtab{padding:10px 10px 11px;font-size:11.5px;}
    }
</style>
@endif
