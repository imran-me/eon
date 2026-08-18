{{--
    Expense toolbar — one row of tabs across the whole expense desk.

    Built to the same shape as layout/payroll-tabs.blade.php: the tabs mirror what
    the sidebar used to hide behind a dropdown, with the same permission gates, so
    a user never sees a tab for a page the sidebar would have refused them.

    The sidebar dropdown is gone. Seven pages behind a fold meant two clicks and a
    hunt to reach any of them, and no way to see where you were; a tab row shows
    all seven and marks the current one.

    Self-contained on purpose: it resolves its own $role and prefixes locals with
    expenseTab*, because the including views define their own $role, $datas and
    $companies further down.
--}}
@php
    $expenseTabUser = auth()->user();
    $expenseTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($expenseTabUser)->getRoleNames()->first() ?? '');

    $expenseTabItems = [
        [
            'label'      => 'All Expenses',
            'icon'       => 'fa-list',
            'route'      => 'role.expenses.index',
            'permission' => 'view expense',
            'active'     => ['role.expenses.index'],
        ],
        // Subscriptions, EMI and Renewals share one tab because they share one
        // record: the same fields, the same due-date behaviour, the same payment
        // history. Only the accounting differs at payment time, and the screen
        // separates them with chips rather than with three near-identical pages.
        [
            'label'      => 'Subscriptions',
            'icon'       => 'fa-arrows-rotate',
            'route'      => 'role.expenses.subscriptions',
            'permission' => 'view expense',
            'active'     => ['role.expenses.subscriptions'],
            // PREVIEW GATE. While this screen renders sample data it is shown to
            // super admins only — no other company's staff should meet a tab full
            // of figures that are not theirs. A plain column read, not hasRole():
            // this file renders on five pages for twelve companies, so the check
            // must not query and must not be able to throw. Delete this one line
            // when the screen is real.
            'visible'    => (bool) ($expenseTabUser?->is_super_admin ?? false),
        ],
        // Department, Category and Sub-category are one screen, so one tab. They
        // are one chain and were never independently useful.
        [
            'label'      => 'Classification',
            'icon'       => 'fa-layer-group',
            'route'      => 'role.expense-classification.index',
            'permission' => 'view expense',
            'active'     => [
                'role.expense-classification.index',
                'role.expense-departments.*',
                'role.expense-categories.*',
                'role.expense-subcategories.*',
            ],
        ],
        [
            'label'      => 'Petty Cash',
            'icon'       => 'fa-wallet',
            'route'      => 'role.petty-cash.index',
            'permission' => 'view expense',
            'active'     => ['role.petty-cash.*'],
        ],
        // No Reimbursements tab. What the company owes staff lives on the Petty
        // Cash desk instead, beside what staff are holding for the company: two
        // directions of one relationship, and reading them apart made the whole
        // position impossible to see on one screen.
        [
            'label'      => 'Budget Setup',
            'icon'       => 'fa-bullseye',
            'route'      => 'role.expenses.budget-setup',
            'permission' => 'view expense',
            'active'     => ['role.expenses.budget-setup'],
        ],
        [
            'label'      => 'Expense Report',
            'icon'       => 'fa-chart-line',
            'route'      => 'role.expenses.report',
            'permission' => 'view expense',
            'active'     => ['role.expenses.report'],
        ],
    ];

    $expenseTabLinks = [];
    $expenseTabCurrent = null;

    foreach ($expenseTabItems as $expenseTabItem) {
        // A tab may declare itself not yet visible. The key is OPTIONAL and
        // defaults to visible, so every tab that does not set it behaves exactly
        // as it did before this key existed.
        if (($expenseTabItem['visible'] ?? true) === false) {
            continue;
        }

        if ($expenseTabItem['permission'] && ! $expenseTabUser?->can($expenseTabItem['permission'])) {
            continue;
        }

        // A missing route must never take the whole page down with it — the
        // Petty Cash desk in particular arrived separately.
        try {
            $expenseTabItem['url'] = route($expenseTabItem['route'], ['role' => $expenseTabRole]);
        } catch (\Throwable $e) {
            continue;
        }

        $expenseTabItem['is_active'] = request()->routeIs(...$expenseTabItem['active']);

        if ($expenseTabItem['is_active']) {
            $expenseTabCurrent = $expenseTabItem;
        }

        $expenseTabLinks[] = $expenseTabItem;
    }
@endphp

@if(count($expenseTabLinks) > 1)
<div class="ex-toolbar">
    <nav class="ex-navband" aria-label="Expense sections">
        @foreach($expenseTabLinks as $expenseTabLink)
            <a href="{{ $expenseTabLink['url'] }}"
               class="ex-navtab {{ $expenseTabLink['is_active'] ? 'is-active' : '' }}"
               title="{{ $expenseTabLink['label'] }}"
               @if($expenseTabLink['is_active']) aria-current="page" @endif>
                <i class="fas {{ $expenseTabLink['icon'] }}"></i>
                <span>{{ $expenseTabLink['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>

<style>
    /* Same band as layout/payroll-tabs.blade.php, and deliberately so — two
       sections of the app that navigate the same way should look the same.
       Prefixed .ex- rather than reusing .pr- so a future change to one desk's
       toolbar cannot silently move the other's. */
    .ex-toolbar{margin:0 0 16px;}
    /* Pages that nest their content in a `p-4 md:p-6` wrapper start 36px down
       (main's p-3 + the wrapper's own padding). Pull those back so every expense
       page's band sits at the same height. */
    .p-4 > .ex-toolbar{margin-top:-16px;}
    @media(min-width:768px){ .p-4 > .ex-toolbar{margin-top:-24px;} }

    .ex-navband{display:flex;flex-wrap:wrap;align-items:stretch;gap:0;background:#fff;
        border-bottom:1px solid #e2e8f0;border-radius:10px 10px 0 0;margin-bottom:12px;}
    /* flex:1 0 auto — fill the band when the tabs fit, keep natural width and wrap
       when they don't, so a label is never squashed below its own text. */
    .ex-navtab{flex:1 0 auto;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:7px;
        text-align:center;padding:11px 14px 12px;margin-bottom:-1px;border-bottom:3px solid transparent;
        font-size:12px;font-weight:700;color:#64748b;white-space:nowrap;text-decoration:none;
        transition:color .15s,border-color .15s,background .15s;}
    .ex-navtab + .ex-navtab{border-left:1px solid #e2e8f0;}
    .ex-navtab i{font-size:11px;opacity:.85;}
    .ex-navtab:hover{color:#0f172a;background:rgba(37,99,235,.09);text-decoration:none;}
    .ex-navtab.is-active{color:#2563eb;border-bottom-color:#2563eb;}
    .ex-navtab.is-active i{opacity:1;}

    /* The app's dark shim recolours by class and these set their own hex. */
    html.dark .ex-navband{background:#1e293b;border-bottom-color:#334155;}
    html.dark .ex-navtab{color:#94a3b8;}
    html.dark .ex-navtab + .ex-navtab{border-left-color:#334155;}
    html.dark .ex-navtab:hover{color:#e2e8f0;background:rgba(59,130,246,.14);}
    html.dark .ex-navtab.is-active{color:#60a5fa;border-bottom-color:#60a5fa;}

    @media(max-width:640px){
        .ex-navtab{padding:10px 10px 11px;font-size:11.5px;}
    }
</style>
@endif
