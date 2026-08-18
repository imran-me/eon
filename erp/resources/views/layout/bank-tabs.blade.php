{{--
    Bank toolbar — one row of tabs across the whole bank desk.

    Same job as layout/expense-tabs.blade.php: the tabs mirror what the sidebar
    used to hide behind a "Manage Banks" dropdown, with the same permission gate
    ('view bank', exactly as the sidebar group used), so a user never sees a tab
    for a page the sidebar would have refused them.

    The sidebar dropdown is gone. Three pages behind a fold meant two clicks and
    no sense of where you were; a tab row shows all three and marks the current
    one. Drawn as the expense desk's band, deliberately identical to it: two
    sections of the app that navigate the same way should look the same.

    Self-contained on purpose: it resolves its own $role and prefixes locals with
    bankTab*, because the including views define their own $role, $roleSlug,
    $datas and $banks further down.
--}}
@php
    $bankTabUser = auth()->user();
    $bankTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($bankTabUser)->getRoleNames()->first() ?? '');

    $bankTabItems = [
        [
            'label'      => 'Accounts Dashboard',
            'icon'       => 'fa-chart-pie',
            'route'      => 'role.banks.dashboard',
            'permission' => 'view bank',
            'active'     => ['role.banks.dashboard'],
        ],
        [
            'label'      => 'All Banks',
            'icon'       => 'fa-list',
            'route'      => 'role.banks.index',
            'permission' => 'view bank',
            // The statement / show / edit screens are drill-downs of the list,
            // so the list stays lit while you are inside one.
            'active'     => [
                'role.banks.index',
                'role.banks.show',
                'role.banks.edit',
                'role.banks.create',
                'role.banks.statement',
            ],
        ],
        [
            'label'      => 'Bank Transfers',
            'icon'       => 'fa-exchange-alt',
            'route'      => 'role.bank_transfers.index',
            'permission' => 'view bank',
            'active'     => ['role.bank_transfers.*'],
        ],
    ];

    $bankTabLinks = [];
    $bankTabCurrent = null;

    foreach ($bankTabItems as $bankTabItem) {
        if ($bankTabItem['permission'] && ! $bankTabUser?->can($bankTabItem['permission'])) {
            continue;
        }

        // A missing route must never take the whole page down with it.
        try {
            $bankTabItem['url'] = route($bankTabItem['route'], ['role' => $bankTabRole]);
        } catch (\Throwable $e) {
            continue;
        }

        $bankTabItem['is_active'] = request()->routeIs(...$bankTabItem['active']);

        if ($bankTabItem['is_active']) {
            $bankTabCurrent = $bankTabItem;
        }

        $bankTabLinks[] = $bankTabItem;
    }
@endphp

@if(count($bankTabLinks) > 1)
<div class="bk-toolbar">
    <nav class="bk-navband" aria-label="Bank sections">
        @foreach($bankTabLinks as $bankTabLink)
            <a href="{{ $bankTabLink['url'] }}"
               class="bk-navtab {{ $bankTabLink['is_active'] ? 'is-active' : '' }}"
               title="{{ $bankTabLink['label'] }}"
               @if($bankTabLink['is_active']) aria-current="page" @endif>
                <i class="fas {{ $bankTabLink['icon'] }}"></i>
                <span>{{ $bankTabLink['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>

<style>
    /* Same band as layout/expense-tabs.blade.php, and deliberately so — two
       sections of the app that navigate the same way should look the same.
       Prefixed .bk- rather than reusing .ex-/.pr- so a future change to one
       desk's toolbar cannot silently move the others'. */
    .bk-toolbar{margin:0 0 16px;}
    /* Pages that nest their content in a `p-4 md:p-6` wrapper start 36px down
       (main's p-3 + the wrapper's own padding). Pull those back so every bank
       page's toolbar sits at the same height. */
    .p-4 > .bk-toolbar{margin-top:-16px;}
    @media(min-width:768px){ .p-4 > .bk-toolbar{margin-top:-24px;} }

    .bk-navband{display:flex;flex-wrap:wrap;align-items:stretch;gap:0;background:#fff;
        border-bottom:1px solid #e2e8f0;border-radius:10px 10px 0 0;margin-bottom:12px;}
    /* flex:1 0 auto — fill the band when the tabs fit, keep natural width and wrap
       when they don't, so a label is never squashed below its own text. */
    .bk-navtab{flex:1 0 auto;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:7px;
        text-align:center;padding:11px 14px 12px;margin-bottom:-1px;border-bottom:3px solid transparent;
        font-size:12px;font-weight:700;color:#64748b;white-space:nowrap;text-decoration:none;
        transition:color .15s,border-color .15s,background .15s;}
    .bk-navtab + .bk-navtab{border-left:1px solid #e2e8f0;}
    .bk-navtab i{font-size:11px;opacity:.85;}
    .bk-navtab:hover{color:#0f172a;background:rgba(37,99,235,.09);text-decoration:none;}
    .bk-navtab.is-active{color:#2563eb;border-bottom-color:#2563eb;}
    .bk-navtab.is-active i{opacity:1;}

    /* The app's dark shim recolours by class and these set their own hex. */
    html.dark .bk-navband{background:#1e293b;border-bottom-color:#334155;}
    html.dark .bk-navtab{color:#94a3b8;}
    html.dark .bk-navtab + .bk-navtab{border-left-color:#334155;}
    html.dark .bk-navtab:hover{color:#e2e8f0;background:rgba(59,130,246,.14);}
    html.dark .bk-navtab.is-active{color:#60a5fa;border-bottom-color:#60a5fa;}

    @media(max-width:640px){
        .bk-navtab{padding:10px 10px 11px;font-size:11.5px;}
    }
</style>
@endif
