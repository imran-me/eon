{{--
    Payroll toolbar — one row: section tabs on the left, company filter on the right.

    Included at the top of each Payroll index view. The tabs mirror the sidebar's
    Payroll group exactly (same six destinations, same permission gates), so a
    user never sees a tab for a page the sidebar would have hidden.

    The company chips are a payroll-wide context, not a per-page filter: picking a
    company reloads the current page scoped to it, and every tab link carries the
    choice forward. That way "show me Woodart" survives moving between Salary
    Manage, Loans and Reports instead of resetting on each hop.

    Chips only appear when the current page can actually honour company_id AND the
    user is allowed to see more than their own company — a filter that silently
    does nothing is worse than no filter.

    Self-contained on purpose: it resolves its own $role and prefixes locals with
    payrollTab*, because including views define their own $role/$tabs further down
    (report/payroll/index.blade.php in particular).
--}}
@php
    $payrollTabUser = auth()->user();
    $payrollTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($payrollTabUser)->getRoleNames()->first() ?? '');

    $payrollTabItems = [
        [
            'label'      => 'Salary Manage',
            'icon'       => 'fa-list',
            'route'      => 'role.employee-salaries.index',
            'permission' => 'view salary',
            'view_all'   => 'view all salary',
            'active'     => ['role.employee-salaries.*'],
        ],
        [
            'label'      => 'Payslip',
            'icon'       => 'fa-file-lines',
            'route'      => 'role.payslips.index',
            'permission' => 'view payslip',
            'view_all'   => 'view all payslip',
            'active'     => ['role.payslips.*'],
        ],
        [
            'label'      => 'Loan Management',
            'icon'       => 'fa-hand-holding-dollar',
            'route'      => 'role.loans.index',
            'permission' => 'view loan',
            'view_all'   => 'view all loan',
            'active'     => ['role.loans.*'],
        ],
        [
            'label'      => 'Advance Salary',
            'icon'       => 'fa-sack-dollar',
            'route'      => 'role.advance-salaries.index',
            'permission' => 'view advance salary',
            'view_all'   => 'view all advance salary',
            'active'     => ['role.advance-salaries.*'],
        ],
        [
            'label'      => 'Payroll Reports',
            'icon'       => 'fa-chart-pie',
            // The sidebar gates this one on 'view salary', not a report-specific
            // permission — match it, or the tab shows to users the sidebar hides.
            //
            // Lands on the overview: "what do we owe and where did the money go"
            // is what someone opens Reports to ask. The five filter-driven tabs
            // are a click away from there, and both pages match the `active`
            // patterns below so the tab highlights on either.
            'route'      => 'role.report.payroll.overview',
            'permission' => 'view salary',
            'view_all'   => 'view all salary',
            'active'     => ['role.report.payroll', 'role.report.payroll.*'],
        ],
        [
            'label'      => 'Salary Template',
            'icon'       => 'fa-file-invoice-dollar',
            'route'      => 'role.salary-templates.index',
            'permission' => 'view salary template',
            'view_all'   => 'view all salary template',
            'active'     => ['role.salary-templates.*'],
        ],
    ];

    $payrollTabCompanyId = request()->filled('company_id') ? (int) request()->company_id : null;
    $payrollTabLinks = [];
    $payrollTabCurrent = null;

    foreach ($payrollTabItems as $payrollTabItem) {
        if ($payrollTabItem['permission'] && ! $payrollTabUser?->can($payrollTabItem['permission'])) {
            continue;
        }

        // A missing route must never take the whole page down with it.
        try {
            $payrollTabItem['url'] = route($payrollTabItem['route'], array_filter([
                'role' => $payrollTabRole,
                'company_id' => $payrollTabCompanyId,
            ]));
        } catch (\Throwable $e) {
            continue;
        }

        $payrollTabItem['is_active'] = request()->routeIs(...$payrollTabItem['active']);

        if ($payrollTabItem['is_active']) {
            $payrollTabCurrent = $payrollTabItem;
        }

        $payrollTabLinks[] = $payrollTabItem;
    }

    // Only render on a page the toolbar actually describes. salary-templates.index
    // is not exclusive to Salary Template — CommissionController@index and
    // SaleRecordController@index render the same view — so without this the bar
    // would appear on /commissions and /sales-records with no tab highlighted.
    // Keying off the active route (not the view name) also protects any future
    // controller that reuses one of these views.
    $payrollTabShow = (bool) $payrollTabCurrent;

    // Chips need a page that filters on company_id and a user allowed to see all
    // of them. Outside that, showing chips would be a control that does nothing.
    $payrollTabCompanies = collect();

    if ($payrollTabCurrent && $payrollTabUser?->can($payrollTabCurrent['view_all'])) {
        $payrollTabCompanies = \App\Models\Company::where('status', 1)
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);

        // Sister concerns nearly all lead with the group name ("EPAL GROUP",
        // "EPAL TRAVELS", …). Repeated across a row of chips that word carries no
        // information and costs ~40px each. Strip any leading word shared by two
        // or more companies; the full name stays in the chip's tooltip.
        $payrollTabLabels = $payrollTabCompanies->mapWithKeys(fn ($c) => [
            $c->id => trim($c->short_name ?: $c->name),
        ]);

        $payrollTabSharedWords = $payrollTabLabels
            ->map(fn ($label) => \Illuminate\Support\Str::upper(strtok($label, ' ')))
            ->countBy()
            ->filter(fn ($count) => $count > 1)
            ->keys();

        $payrollTabStrip = function ($text) use ($payrollTabSharedWords) {
            $text = trim((string) $text);
            $first = \Illuminate\Support\Str::upper(strtok($text, ' '));

            if (! $payrollTabSharedWords->contains($first)) {
                return $text;
            }

            $rest = trim(\Illuminate\Support\Str::after($text, ' '));

            // Single-word names have nothing left after the prefix, so they keep
            // their full label rather than rendering as a blank chip.
            return ($rest === '' || $rest === $text) ? $text : $rest;
        };

        $payrollTabCompanies = $payrollTabCompanies->map(function ($company) use ($payrollTabLabels, $payrollTabStrip) {
            $label = $payrollTabStrip($payrollTabLabels[$company->id]);

            // "EPAL IT" (a short_name) strips down to a bare "IT", which reads as
            // noise next to GROUP and PROPERTIES. When the abbreviation collapses
            // that far, fall back to the full name — "EPAL IT SOLUTIONS" strips to
            // "IT SOLUTIONS", which still fits and actually names the concern.
            if (mb_strlen($label) < 4) {
                $fromName = $payrollTabStrip($company->name);

                if (mb_strlen($fromName) > mb_strlen($label)) {
                    $label = $fromName;
                }
            }

            $company->chip_label = $label;

            return $company;
        });
    }

    // Keep the rest of the querystring (period, search, status…) when switching company.
    $payrollTabChipUrl = function ($companyId) {
        $params = array_merge(request()->route()->parameters(), request()->query());
        unset($params['page']);

        if ($companyId) {
            $params['company_id'] = $companyId;
        } else {
            unset($params['company_id']);
        }

        try {
            return route(request()->route()->getName(), $params);
        } catch (\Throwable $e) {
            return request()->fullUrlWithQuery(['company_id' => $companyId]);
        }
    };
@endphp

@if($payrollTabShow && count($payrollTabLinks) > 1)
<div class="pr-toolbar">
    {{-- Section nav, styled after the reference project's .tab-underline.tabs-dense
         band: buttons share the row, thin dividers between them, and the active
         one is marked by a blue label + underline rather than a filled pill. --}}
    <nav class="pr-navband" aria-label="Payroll sections">
        @foreach($payrollTabLinks as $payrollTabLink)
            <a href="{{ $payrollTabLink['url'] }}"
               class="pr-navtab {{ $payrollTabLink['is_active'] ? 'is-active' : '' }}"
               title="{{ $payrollTabLink['label'] }}"
               @if($payrollTabLink['is_active']) aria-current="page" @endif>
                <i class="fas {{ $payrollTabLink['icon'] }}"></i>
                <span>{{ $payrollTabLink['label'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Company switcher, styled after the reference's .scroll-row of
         .btn.btn-sm.btn-outline buttons — the selected one swaps to the filled
         btn-primary look. Wraps to a second line instead of scrolling. --}}
    @if($payrollTabCompanies->count() > 1)
        <div class="pr-switcher" role="group" aria-label="Filter by company">
            <a href="{{ $payrollTabChipUrl(null) }}"
               class="pr-cobtn {{ $payrollTabCompanyId ? '' : 'is-active' }}">
                <i class="fas fa-layer-group"></i> All Companies
            </a>
            @foreach($payrollTabCompanies as $payrollTabCompany)
                <a href="{{ $payrollTabChipUrl($payrollTabCompany->id) }}"
                   class="pr-cobtn {{ $payrollTabCompanyId === $payrollTabCompany->id ? 'is-active' : '' }}"
                   title="{{ $payrollTabCompany->name }}">
                    {{ $payrollTabCompany->chip_label }}
                </a>
            @endforeach
        </div>
    @endif
</div>

<style>
    /* Ported from the reference project's design system:
         nav band = .tab-underline.tabs-dense          (base.css:522-541)
         switcher = .scroll-row + .btn.btn-sm.btn-outline / .btn-primary
                                                       (base.css:41-64, 546)
       Its CSS variables are translated to this app's palette:
         --accent #2563eb · --border #e2e8f0 · --border-strong #cbd5e1
         --text   #0f172a · --text-dim #64748b */
    .pr-toolbar{margin:0 0 16px;}
    /* Salary Manage and Payroll Reports nest their content in a `p-4 md:p-6`
       wrapper, so the band sat 36px down (main's p-3 + the wrapper's own padding)
       while the other four pages sat at 12px. Pull those two back up by exactly
       the wrapper's padding so all six start at the same place. */
    .p-4 > .pr-toolbar{margin-top:-16px;}
    @media(min-width:768px){ .p-4 > .pr-toolbar{margin-top:-24px;} }

    /* ── section nav band ── */
    .pr-navband{display:flex;flex-wrap:wrap;align-items:stretch;gap:0;background:#fff;
        border-bottom:1px solid #e2e8f0;border-radius:10px 10px 0 0;margin-bottom:12px;}
    /* flex:1 0 auto — fill the band when the tabs fit, keep natural width and wrap
       when they don't, so a label is never squashed below its own text. */
    .pr-navtab{flex:1 0 auto;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:7px;
        text-align:center;padding:11px 14px 12px;margin-bottom:-1px;border-bottom:3px solid transparent;
        font-size:12px;font-weight:700;color:#64748b;white-space:nowrap;text-decoration:none;
        transition:color .15s,border-color .15s,background .15s;}
    .pr-navtab + .pr-navtab{border-left:1px solid #e2e8f0;}
    .pr-navtab i{font-size:11px;opacity:.85;}
    .pr-navtab:hover{color:#0f172a;background:rgba(37,99,235,.09);text-decoration:none;}
    .pr-navtab.is-active{color:#2563eb;border-bottom-color:#2563eb;}
    .pr-navtab.is-active i{opacity:1;}

    /* ── company switcher ── */
    /* Right-aligned under the nav band. Kept on its own row rather than beside
       the band: the two together need ~1630px, so sharing a row would put the
       switcher back into horizontal scrolling at every normal desktop width. */
    .pr-switcher{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px;}
    .pr-cobtn{display:inline-flex;align-items:center;justify-content:center;gap:6px;flex:0 0 auto;
        padding:6px 11px;border-radius:7px;font-size:11.5px;font-weight:600;color:#0f172a;
        background:transparent;border:1px solid #cbd5e1;white-space:nowrap;text-decoration:none;user-select:none;
        transition:transform .1s ease-out,background .15s,border-color .15s,box-shadow .15s;}
    .pr-cobtn i{font-size:10.5px;opacity:.7;}
    .pr-cobtn:hover{border-color:#94a3b8;transform:translateY(-1px);text-decoration:none;color:#0f172a;}
    .pr-cobtn:active{transform:translateY(0);}
    /* the selected concern takes the filled btn-primary treatment */
    .pr-cobtn.is-active{background:linear-gradient(160deg,#2563eb,#1d4ed8);color:#fff;border-color:transparent;
        box-shadow:0 2px 8px rgba(37,99,235,.28);}
    .pr-cobtn.is-active i{opacity:1;}
    .pr-cobtn.is-active:hover{color:#fff;}

    /* The app's dark shim recolours by class (html.dark .bg-white{…}) and these set
       their own hex, so they need explicit overrides. */
    html.dark .pr-navband{background:#1e293b;border-bottom-color:#334155;}
    html.dark .pr-navtab{color:#94a3b8;}
    html.dark .pr-navtab + .pr-navtab{border-left-color:#334155;}
    html.dark .pr-navtab:hover{color:#e2e8f0;background:rgba(59,130,246,.14);}
    html.dark .pr-navtab.is-active{color:#60a5fa;border-bottom-color:#60a5fa;}
    html.dark .pr-cobtn{color:#cbd5e1;border-color:#475569;}
    html.dark .pr-cobtn:hover{border-color:#64748b;color:#e2e8f0;}
    html.dark .pr-cobtn.is-active{color:#fff;}

    @media(max-width:640px){
        .pr-navtab{padding:10px 10px 11px;font-size:11.5px;}
        .pr-cobtn{padding:5px 9px;font-size:11px;}
    }
</style>
@endif
