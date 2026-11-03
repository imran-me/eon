{{--
    Financial statements — head, controls and scope line, shared by the four statements.
    Expects: $frTitle $frSub $frRoute $frRole $frMode ('period'|'asof') $frFrom/$frTo or $frAsOf,
             $frCompany (null = whole group) $frCompanyId $frCompanies $frCanPick, $frGeneratedAt.
    Print and CSV keep every filter on screen (company, dates) so paper always matches the page.
--}}
@php
    $frName = 'role.report.' . $frRoute;
    $frKeep = array_filter(request()->only(['company_id', 'date_from', 'date_to', 'as_of', 'month']), fn ($v) => $v !== null && $v !== '');
    if ($frCanPick) { $frKeep['company_id'] = $frCompanyId ?? 'all'; }
    $frUrl = fn (string $suffix = '', array $extra = []) => route($frName . $suffix, ['role' => $frRole] + $extra + $frKeep);
    $frChip = fn (array $q) => route($frName, ['role' => $frRole] + $q + ($frCanPick ? ['company_id' => $frCompanyId ?? 'all'] : []));

    $t = now();
    $periodChips = [
        'Today'        => ['date_from' => $t->toDateString(), 'date_to' => $t->toDateString()],
        'This Month'   => ['date_from' => $t->copy()->startOfMonth()->toDateString(), 'date_to' => $t->toDateString()],
        'Last Month'   => ['date_from' => $t->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'date_to' => $t->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        'This Quarter' => ['date_from' => $t->copy()->startOfQuarter()->toDateString(), 'date_to' => $t->toDateString()],
        'This Year'    => ['date_from' => $t->copy()->startOfYear()->toDateString(), 'date_to' => $t->toDateString()],
        'Last Year'    => ['date_from' => $t->copy()->subYear()->startOfYear()->toDateString(), 'date_to' => $t->copy()->subYear()->endOfYear()->toDateString()],
    ];
    $asofChips = [
        'Today'              => ['as_of' => $t->toDateString()],
        'End of Last Month'  => ['as_of' => $t->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        'End of Last Year'   => ['as_of' => $t->copy()->subYear()->endOfYear()->toDateString()],
    ];
    $monthChips = [
        'This Month'   => ['month' => $t->format('Y-m')],
        'Last Month'   => ['month' => $t->copy()->subMonthNoOverflow()->format('Y-m')],
        '2 Months Ago' => ['month' => $t->copy()->subMonthsNoOverflow(2)->format('Y-m')],
    ];
    $frOn = fn (array $q) => isset($q['month']) ? (($frMonth ?? null) === $q['month']) : ($frMode === 'period'
        ? (($frFrom ?? null) === $q['date_from'] && ($frTo ?? null) === $q['date_to'])
        : (($frAsOf ?? null) === $q['as_of']));
@endphp

<div class="fr-card">
    <div class="fr-head">
        <div>
            <h1>{{ $frTitle }}</h1>
            <div class="fr-sub">{{ $frSub }}</div>
        </div>
        <div class="fr-head-actions no-print">
            @isset($frClassicUrl)<a class="fr-btn" href="{{ $frClassicUrl }}" title="The earlier layout of this report">Classic layout</a>@endisset
            @if($frExcel ?? false)<a class="fr-btn" href="{{ $frUrl('.excel') }}"><i class="fas fa-file-excel"></i> Export Excel</a>@endif
            <a class="fr-btn" href="{{ $frUrl('.export') }}"><i class="fas fa-file-csv"></i> Export CSV</a>
            <a class="fr-btn fr-solid" href="{{ $frUrl('.print') }}" target="_blank" rel="noopener"><i class="fas fa-print"></i> Print / PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route($frName, ['role' => $frRole]) }}" class="fr-controls no-print">
        @if($frCanPick)
        <div class="fr-field"><label for="fr-company">Company</label>
            <select id="fr-company" name="company_id">
                <option value="all" @selected($frCompanyId === null)>All companies (whole group)</option>
                @foreach($frCompanies as $c)<option value="{{ $c->id }}" @selected($frCompanyId === (int) $c->id)>{{ $c->name }}</option>@endforeach
            </select></div>
        @endif

        @if($frMode === 'month')
        <div class="fr-field"><label for="fr-month">Month</label><input id="fr-month" type="month" name="month" value="{{ $frMonth }}"></div>
        <div class="fr-seg" aria-label="Quick month">
            @foreach($monthChips as $label => $q)<a href="{{ $frChip($q) }}" class="{{ $frOn($q) ? 'is-on' : '' }}">{{ $label }}</a>@endforeach
        </div>
        @elseif($frMode === 'period')
        <div class="fr-field"><label for="fr-from">From</label><input id="fr-from" type="date" name="date_from" value="{{ $frFrom }}"></div>
        <div class="fr-field"><label for="fr-to">To</label><input id="fr-to" type="date" name="date_to" value="{{ $frTo }}"></div>
        <div class="fr-seg" aria-label="Quick period">
            @foreach($periodChips as $label => $q)<a href="{{ $frChip($q) }}" class="{{ $frOn($q) ? 'is-on' : '' }}">{{ $label }}</a>@endforeach
        </div>
        @else
        <div class="fr-field"><label for="fr-asof">As of</label><input id="fr-asof" type="date" name="as_of" value="{{ $frAsOf }}"></div>
        <div class="fr-seg" aria-label="Quick date">
            @foreach($asofChips as $label => $q)<a href="{{ $frChip($q) }}" class="{{ $frOn($q) ? 'is-on' : '' }}">{{ $label }}</a>@endforeach
        </div>
        @endif
        <button type="submit" class="fr-go">Apply</button>
    </form>

    <div class="fr-scope">
        <span><i class="fas fa-building"></i> <b>{{ $frCompany?->name ?? 'All companies (whole group)' }}</b></span>
        <span>
            @if($frMode === 'month') {{ \Carbon\Carbon::createFromFormat('Y-m', $frMonth)->format('F Y') }}@if($mr['mtd'] ?? false) (month to date)@endif
            @elseif($frMode === 'period') {{ \Carbon\Carbon::parse($frFrom)->format('d M Y') }} – {{ \Carbon\Carbon::parse($frTo)->format('d M Y') }}
            @else As of {{ \Carbon\Carbon::parse($frAsOf)->format('d M Y') }} @endif
        </span>
        <span style="margin-left:auto; opacity:.8">Generated {{ $frGeneratedAt->format('d M Y H:i') }}</span>
    </div>
    @if($frCompanyId === null)
    <div class="fr-warn">Whole group: every company is added together, so what one company owes another is counted on both sides. Pick one company for its own statement.</div>
    @endif
</div>
