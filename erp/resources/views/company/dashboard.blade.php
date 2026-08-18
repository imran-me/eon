@extends('layout.app')

@section('meta-information')
    <title>{{ $company->name }} — Dashboard</title>
@endsection

@section('main-content')
<div class="p-4 md:p-6 space-y-6">

    {{-- ── Header ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            @php
                $initials = collect(explode(' ', $company->name))
                    ->filter()->map(fn($w) => strtoupper($w[0]))->take(2)->implode('');
                $colors = ['#3b82f6','#8b5cf6','#06b6d4','#f59e0b','#10b981','#f43f5e','#6366f1','#a16207'];
                $color  = $colors[$company->id % count($colors)];
            @endphp
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-lg shrink-0"
                 style="background:{{ $color }};">{{ $initials }}</div>
            <div>
                <h1 class="text-xl font-extrabold text-gray-900 leading-tight">{{ $company->name }}</h1>
                <p class="text-sm text-gray-500">Company Dashboard</p>
            </div>
        </div>

        {{-- Period filter --}}
        <form method="GET" action="{{ request()->url() }}" class="flex items-center gap-2">
            <label class="text-sm text-gray-500 font-medium">Period:</label>
            <input type="month" name="period" value="{{ $period }}"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit"
                    class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Filter
            </button>
        </form>
    </div>

    {{-- ── Yesterday Snapshot — quick daily P&L (Sales/Purchase/Expense only) ── --}}
    <div class="rounded-2xl border-2 border-blue-100 bg-gradient-to-br from-blue-50 to-white p-5">
        <h2 class="text-sm font-bold text-gray-700 flex items-center gap-2 mb-3">
            <i class="fas fa-calendar-day text-blue-600"></i> Yesterday — {{ \Carbon\Carbon::parse($yesterday)->format('d M Y') }}
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-gray-500 font-medium">Sales</p>
                <p class="text-lg font-extrabold text-gray-900">৳ {{ number_format($yesterdaySales, 0) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Purchase</p>
                <p class="text-lg font-extrabold text-gray-900">৳ {{ number_format($yesterdayPurchase, 0) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Expense</p>
                <p class="text-lg font-extrabold text-gray-900">৳ {{ number_format($yesterdayExpense, 0) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Yesterday's P&L</p>
                <p class="text-lg font-extrabold {{ $yesterdayPL >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $yesterdayPL >= 0 ? '' : '-' }}৳ {{ number_format(abs($yesterdayPL), 0) }}
                </p>
            </div>
        </div>
    </div>

    @if($isTravel)
    {{-- ── Sales by Type — Travel's 4 revenue lines ── --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
        <h2 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-plane-departure text-blue-600"></i> Sales by Type — {{ \Carbon\Carbon::create()->month($selectedMonth)->format('F') }} {{ $selectedYear }}
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($salesByType as $type)
            <div class="rounded-xl border border-gray-100 p-3">
                <p class="text-xs text-gray-500 font-medium">{{ $type['label'] }}</p>
                <p class="text-lg font-extrabold text-gray-900 mt-1">{{ number_format($type['count']) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">৳ {{ number_format($type['amount'], 0) }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Stat Cards Row 1: Financial ── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">

        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background:#dbeafe;color:#2563eb;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Total Revenue</p>
                <p class="text-xl font-extrabold text-gray-900 mt-0.5">৳ {{ number_format($totalSales, 0) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ \Carbon\Carbon::create()->month($selectedMonth)->format('F') }} {{ $selectedYear }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background:#fef3c7;color:#d97706;">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Total Purchase</p>
                <p class="text-xl font-extrabold text-gray-900 mt-0.5">৳ {{ number_format($totalPurchase, 0) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ \Carbon\Carbon::create()->month($selectedMonth)->format('F') }} {{ $selectedYear }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background:#fee2e2;color:#dc2626;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Total Expense</p>
                <p class="text-xl font-extrabold text-gray-900 mt-0.5">৳ {{ number_format($totalExpense, 0) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Rent ৳{{ number_format($totalRent, 0) }} · Other ৳{{ number_format($otherExpenses, 0) }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background:#ccfbf1;color:#0f766e;">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Salary Paid</p>
                <p class="text-xl font-extrabold text-gray-900 mt-0.5">৳ {{ number_format($totalSalaryPaid, 0) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ \Carbon\Carbon::create()->month($selectedMonth)->format('F') }} {{ $selectedYear }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background:{{ $netProfit >= 0 ? '#dcfce7' : '#fee2e2' }};color:{{ $netProfit >= 0 ? '#16a34a' : '#dc2626' }};">
                <i class="fas fa-{{ $netProfit >= 0 ? 'arrow-trend-up' : 'arrow-trend-down' }}"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Net Profit / Loss</p>
                <p class="text-xl font-extrabold mt-0.5 {{ $netProfit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $netProfit >= 0 ? '' : '-' }}৳ {{ number_format(abs($netProfit), 0) }}
                </p>
                <p class="text-xs text-gray-400 mt-0.5">Revenue − Purchase − Salary − Rent − Other Expenses</p>
            </div>
        </div>

    </div>

    {{-- ── Stat Cards Row 2: Bank / HR ── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">

        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background:#ede9fe;color:#7c3aed;">
                <i class="fas fa-building-columns"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Bank Balance</p>
                <p class="text-xl font-extrabold text-gray-900 mt-0.5">৳ {{ number_format($bankBalance, 0) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Across {{ $bankCount }} bank{{ $bankCount != 1 ? 's' : '' }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background:#e0f2fe;color:#0369a1;">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Employees</p>
                <p class="text-xl font-extrabold text-gray-900 mt-0.5">{{ number_format($totalEmployees) }}</p>
                <p class="text-xs text-green-600 font-medium mt-0.5">Active</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm col-span-2 md:col-span-1">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background:#f0fdf4;color:#16a34a;">
                <i class="fas fa-percent"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Profit Margin</p>
                @php
                    $margin = $totalSales > 0 ? round(($netProfit / $totalSales) * 100, 1) : 0;
                @endphp
                <p class="text-xl font-extrabold mt-0.5 {{ $margin >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $margin }}%
                </p>
                <p class="text-xs text-gray-400 mt-0.5">Of total revenue</p>
            </div>
        </div>

    </div>

    {{-- ── Charts Row ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Revenue vs Purchase vs Expense vs Salary Bar Chart --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-gray-700">Monthly Overview — {{ $selectedYear }}</h2>
                <div class="flex items-center gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#2563eb;"></span> Revenue</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#f59e0b;"></span> Purchase</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#ef4444;"></span> Expense</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block" style="background:#0f766e;"></span> Salary</span>
                </div>
            </div>
            <canvas id="monthlyChart" style="max-height:220px;"></canvas>
        </div>

        {{-- Financial Breakdown Doughnut --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-bold text-gray-700 mb-4">Financial Breakdown</h2>
            <canvas id="breakdownChart" style="max-height:180px;"></canvas>
            <div class="mt-4 space-y-2">
                @php $total3 = $totalSales + $totalPurchase + $totalExpense + $totalSalaryPaid; @endphp
                <div class="flex items-center justify-between text-xs text-gray-600">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm inline-block" style="background:#2563eb;"></span>Revenue</span>
                    <span class="font-semibold">{{ $total3 > 0 ? round(($totalSales/$total3)*100,1) : 0 }}%</span>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-600">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm inline-block" style="background:#f59e0b;"></span>Purchase</span>
                    <span class="font-semibold">{{ $total3 > 0 ? round(($totalPurchase/$total3)*100,1) : 0 }}%</span>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-600">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm inline-block" style="background:#ef4444;"></span>Expense</span>
                    <span class="font-semibold">{{ $total3 > 0 ? round(($totalExpense/$total3)*100,1) : 0 }}%</span>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-600">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm inline-block" style="background:#0f766e;"></span>Salary</span>
                    <span class="font-semibold">{{ $total3 > 0 ? round(($totalSalaryPaid/$total3)*100,1) : 0 }}%</span>
                </div>
            </div>
        </div>

    </div>

    {{-- ── CRM Pipeline & Ongoing Projects ── --}}
    @canany(['view lead manager', 'view deal', 'view lead project', 'view all lead project'])
    @php
        $pipelineStageLabels = ['new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','proposal_sent'=>'Quoted','negotiation'=>'Negotiation'];
        $pipelineStageBadge  = ['new'=>'#dbeafe|#1d4ed8','contacted'=>'#e0e7ff|#4338ca','qualified'=>'#fef9c3|#a16207','proposal_sent'=>'#ede9fe|#6d28d9','negotiation'=>'#ffedd5|#c2410c'];
    @endphp
    <div>
        <h2 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-funnel-dollar text-blue-600"></i> CRM Pipeline &amp; Ongoing Projects
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Pipeline Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div style="background:linear-gradient(135deg,#1e40af,#3b82f6);padding:16px 20px;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider" style="color:#bfdbfe;">Pipeline Projects</p>
                            <p class="text-3xl font-black text-white mt-1 leading-none">{{ $pipelineCount }}</p>
                            <p class="text-xs mt-1" style="color:#bfdbfe;">Active leads assigned to {{ $company->name }}</p>
                        </div>
                        <div class="text-4xl opacity-25">📋</div>
                    </div>
                    @if($pipelineByStage->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        @foreach($pipelineStageLabels as $slug => $label)
                            @if(($pipelineByStage[$slug] ?? 0) > 0)
                                @php [$bg, $txt] = explode('|', $pipelineStageBadge[$slug]); @endphp
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full" style="background:{{ $bg }};color:{{ $txt }};">
                                    {{ $label }}: {{ $pipelineByStage[$slug] }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                    @endif
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($pipelineLeads as $pl)
                        @php $sc = $pipelineStageBadge[$pl->status] ?? '#f1f5f9|#475569'; [$sbg,$stxt] = explode('|',$sc); @endphp
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $pl->name }}</p>
                                <p class="text-xs text-gray-400">{{ $pl->assignedEmployee->name ?? '—' }}</p>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full" style="background:{{ $sbg }};color:{{ $stxt }};">
                                {{ $pipelineStageLabels[$pl->status] ?? ucfirst($pl->status) }}
                            </span>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400 text-sm">কোনো active pipeline lead নেই</div>
                    @endforelse
                    @if($pipelineCount > 5)
                    <div class="px-4 py-2.5">
                        <a href="{{ route('role.lead-manager.index', ['role' => request()->route('role')]) }}"
                           class="text-sm font-semibold text-blue-600 hover:text-blue-700">
                            সব {{ $pipelineCount }} টি দেখুন →
                        </a>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Ongoing Projects Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div style="background:linear-gradient(135deg,#0f766e,#2dd4bf);padding:16px 20px;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider" style="color:#99f6e4;">Ongoing Projects</p>
                            <p class="text-3xl font-black text-white mt-1 leading-none">{{ $ongoingCount }}</p>
                            <p class="text-xs mt-1" style="color:#99f6e4;">Won leads converted to projects</p>
                        </div>
                        <div class="text-4xl opacity-25">🚀</div>
                    </div>
                    @php
                        $totalWon = \App\Models\Lead::where('status','won')
                            ->whereHas('assignedEmployee', fn($q) => $q->where('company_id', $company->id))
                            ->count();
                        $convRate = $totalWon > 0 ? round(($ongoingCount / $totalWon) * 100) : 0;
                    @endphp
                    <div class="mt-3 flex items-center gap-2 rounded-lg px-3 py-2" style="background:rgba(255,255,255,.15);">
                        <span class="text-xs font-bold text-white">CRM → Project conversion rate:</span>
                        <span class="text-sm font-black text-white">{{ $convRate }}%</span>
                        <div class="flex-1 rounded-full overflow-hidden h-1.5" style="background:rgba(255,255,255,.25);">
                            <div class="h-1.5 rounded-full bg-white" style="width:{{ $convRate }}%;"></div>
                        </div>
                    </div>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($ongoingProjects as $op)
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $op->project_name }}</p>
                                <p class="text-xs text-gray-400">
                                    Lead: {{ $op->lead->name ?? '—' }}
                                    @if($op->customer) · {{ $op->customer->name }} @endif
                                </p>
                            </div>
                            <a href="{{ route('role.projects.show', ['role' => request()->route('role'), 'project' => $op->id]) }}"
                               class="text-xs font-bold px-2.5 py-1 rounded-full" style="background:#ccfbf1;color:#0f766e;">
                                Ongoing →
                            </a>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400 text-sm">কোনো ongoing project নেই</div>
                    @endforelse
                    @if($ongoingCount > 5)
                    <div class="px-4 py-2.5">
                        <a href="{{ route('role.projects.index', ['role' => request()->route('role')]) }}"
                           class="text-sm font-semibold text-teal-600 hover:text-teal-700">
                            সব {{ $ongoingCount }} টি দেখুন →
                        </a>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    @endcanany

    @if($showVisaReports)
    {{-- Visa reports from CRM dashboard --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-passport text-purple-600"></i> Visa Process Funnel
                </h2>
                <a href="{{ route('role.lead-manager.index', ['role' => request()->route('role'), 'lead_type' => 'visa']) }}"
                   class="text-xs font-semibold text-purple-600 hover:text-purple-700">View Leads</a>
            </div>
            @php
                $funnelColors = [
                    'Applications' => '#3b82f6',
                    'Docs Collected' => '#6366f1',
                    'Complete' => '#8b5cf6',
                    'Submitted' => '#f59e0b',
                    'Approved (Won)' => '#10b981',
                    'Rejected (Lost)' => '#ef4444',
                ];
                $maxFunnel = max(max(array_values($visaFunnelData ?: [0])), 1);
            @endphp
            <div class="space-y-3">
                @foreach($visaFunnelData as $label => $count)
                    @php
                        $fclr = $funnelColors[$label] ?? '#94a3b8';
                        $fpct = max(round($count / $maxFunnel * 100), 4);
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs font-semibold text-gray-600 mb-1">
                            <span>{{ $label }}</span>
                            <span style="color:{{ $fclr }}">{{ number_format($count) }}</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-2.5 rounded-full" style="width:{{ $fpct }}%;background:{{ $fclr }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 pt-4 border-t border-gray-100">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">Payment Status</p>
                <div class="space-y-2">
                    @foreach(['pending'=>['Pending','#f59e0b'],'partial'=>['Partial Paid','#3b82f6'],'paid'=>['Fully Paid','#22c55e']] as $key=>[$label,$color])
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 text-gray-600">
                                <span class="w-2 h-2 rounded-full inline-block" style="background:{{ $color }};"></span>
                                {{ $label }}
                            </span>
                            <span class="font-extrabold" style="color:{{ $color }};">{{ number_format($visaPayment[$key] ?? 0) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-globe-asia text-purple-600"></i> Visa Live Applications
                </h2>
                <span class="text-xs text-gray-400">Active visa leads</span>
            </div>
            @php
                $statusColors = [
                    'new'=>['#e2e8f0','#475569'],
                    'contacted'=>['#e0e7ff','#4338ca'],
                    'qualified'=>['#fef9c3','#a16207'],
                    'proposal_sent'=>['#f3e8ff','#7e22ce'],
                    'negotiation'=>['#ffedd5','#c2410c'],
                    'won'=>['#dcfce7','#15803d'],
                    'lost'=>['#fee2e2','#b91c1c'],
                ];
                $docColors = [
                    'pending'=>['#fef9c3','#a16207'],
                    'collected'=>['#dbeafe','#1d4ed8'],
                    'complete'=>['#e0e7ff','#4338ca'],
                    'submitted'=>['#d1fae5','#065f46'],
                ];
            @endphp
            <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
                @forelse($visaLeads as $lead)
                    @php
                        $sc = $statusColors[$lead->status] ?? ['#f1f5f9','#475569'];
                        $dc = $lead->visa ? ($docColors[$lead->visa->document_status] ?? ['#f1f5f9','#475569']) : ['#f1f5f9','#475569'];
                    @endphp
                    <div class="px-5 py-3 flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ $lead->name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5"><i class="fas fa-phone text-[10px]"></i> {{ $lead->phone }}</p>
                            @if($lead->visa)
                                <p class="text-xs font-semibold text-purple-700 mt-1">
                                    {{ $lead->visa->country->name ?? '-' }} - {{ ucfirst($lead->visa->visa_type) }}
                                </p>
                            @endif
                        </div>
                        <div class="text-right shrink-0 space-y-1">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background:{{ $sc[0] }};color:{{ $sc[1] }};">
                                {{ ucfirst(str_replace('_', ' ', $lead->status)) }}
                            </span>
                            @if($lead->visa)
                                <div>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background:{{ $dc[0] }};color:{{ $dc[1] }};">
                                        Docs: {{ ucfirst($lead->visa->document_status) }}
                                    </span>
                                </div>
                            @endif
                            @if($lead->assignedEmployee)
                                <p class="text-xs text-gray-400">{{ $lead->assignedEmployee->name }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-gray-400 text-sm">No active visa applications.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if($missingDocLeads->count() > 0)
    <div class="bg-white rounded-2xl border border-red-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-red-50 flex items-center justify-between bg-red-50/60">
            <h2 class="text-sm font-bold text-red-700 flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> Missing Mandatory Documents
                <span class="text-xs bg-red-600 text-white px-2 py-0.5 rounded-full">{{ $missingDocLeads->count() }}</span>
            </h2>
            <a href="{{ route('role.lead-manager.index', ['role' => request()->route('role'), 'lead_type' => 'visa']) }}"
               class="text-xs font-semibold text-red-700 hover:text-red-800">View All Visa Leads</a>
        </div>
        <div class="divide-y divide-red-50 max-h-80 overflow-y-auto">
            @foreach($missingDocLeads as $lead)
                <div class="px-5 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800">{{ $lead->name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $lead->phone }}</p>
                            @if($lead->visa)
                                <p class="text-xs font-semibold text-purple-700 mt-1">
                                    {{ $lead->visa->country->name ?? '-' }} - {{ ucfirst($lead->visa->visa_type) }}
                                </p>
                            @endif
                        </div>
                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-bold shrink-0">
                            {{ $lead->visaDocuments->count() }} missing
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach($lead->visaDocuments as $doc)
                            <span class="text-xs bg-red-50 text-red-700 border border-red-100 px-2 py-0.5 rounded-full">
                                {{ $doc->document_name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
    @endif

    @if($showEcommerceReports)
    {{-- Ecommerce sales and purchase reports --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-store text-blue-600"></i> Ecommerce Sales Report
                </h2>
                <a href="{{ route('role.sales.index', ['role' => request()->route('role'), 'company_id' => $company->id]) }}"
                   class="text-xs font-semibold text-blue-600 hover:text-blue-700">View Sales</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 p-5 border-b border-gray-100">
                <div>
                    <p class="text-xs text-gray-500 font-medium">Orders</p>
                    <p class="text-lg font-extrabold text-gray-900">{{ number_format($ecommerceSalesReport['orders'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Sales</p>
                    <p class="text-lg font-extrabold text-blue-600">৳ {{ number_format($ecommerceSalesReport['total'] ?? 0, 0) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Collected</p>
                    <p class="text-lg font-extrabold text-green-600">৳ {{ number_format($ecommerceSalesReport['paid'] ?? 0, 0) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Due</p>
                    <p class="text-lg font-extrabold text-red-600">৳ {{ number_format($ecommerceSalesReport['due'] ?? 0, 0) }}</p>
                </div>
            </div>
            <div class="px-5 py-4 border-b border-gray-100">
                <div class="flex flex-wrap gap-2">
                    @foreach(['paid' => ['Paid', '#dcfce7', '#15803d'], 'partial' => ['Partial', '#fef3c7', '#b45309'], 'due' => ['Due', '#fee2e2', '#b91c1c']] as $status => [$label, $bg, $fg])
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="background:{{ $bg }};color:{{ $fg }};">
                            {{ $label }}: {{ number_format($ecommerceSalesByStatus[$status] ?? 0) }}
                        </span>
                    @endforeach
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                        Avg: ৳ {{ number_format($ecommerceSalesReport['average'] ?? 0, 0) }}
                    </span>
                </div>
            </div>
            <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                @forelse($recentEcommerceSales as $sale)
                    @php
                        $status = strtolower((string) $sale->payment_status);
                        $badge = match($status) {
                            'paid' => 'background:#dcfce7;color:#15803d;',
                            'partial' => 'background:#fef3c7;color:#b45309;',
                            'due' => 'background:#fee2e2;color:#b91c1c;',
                            default => 'background:#f3f4f6;color:#6b7280;',
                        };
                    @endphp
                    <div class="px-5 py-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ $sale->invoice_no }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $sale->customer?->name ?? 'Walk-in Customer' }} - {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-extrabold text-gray-900">৳ {{ number_format($sale->total_amount, 0) }}</p>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="{{ $badge }}">{{ ucfirst($status ?: 'unknown') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-gray-400 text-sm">No ecommerce sales found for this period.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-truck-ramp-box text-amber-600"></i> Ecommerce Purchase Report
                </h2>
                <a href="{{ route('role.purchases.index', ['role' => request()->route('role'), 'company_id' => $company->id]) }}"
                   class="text-xs font-semibold text-amber-600 hover:text-amber-700">View Purchases</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 p-5 border-b border-gray-100">
                <div>
                    <p class="text-xs text-gray-500 font-medium">Orders</p>
                    <p class="text-lg font-extrabold text-gray-900">{{ number_format($ecommercePurchaseReport['orders'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Purchase</p>
                    <p class="text-lg font-extrabold text-amber-600">৳ {{ number_format($ecommercePurchaseReport['total'] ?? 0, 0) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Paid</p>
                    <p class="text-lg font-extrabold text-green-600">৳ {{ number_format($ecommercePurchaseReport['paid'] ?? 0, 0) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Due</p>
                    <p class="text-lg font-extrabold text-red-600">৳ {{ number_format($ecommercePurchaseReport['due'] ?? 0, 0) }}</p>
                </div>
            </div>
            <div class="px-5 py-4 border-b border-gray-100">
                <div class="flex flex-wrap gap-2">
                    @foreach(['paid' => ['Paid', '#dcfce7', '#15803d'], 'partial' => ['Partial', '#fef3c7', '#b45309'], 'due' => ['Due', '#fee2e2', '#b91c1c']] as $status => [$label, $bg, $fg])
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="background:{{ $bg }};color:{{ $fg }};">
                            {{ $label }}: {{ number_format($ecommercePurchaseByStatus[$status] ?? 0) }}
                        </span>
                    @endforeach
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                        Avg: ৳ {{ number_format($ecommercePurchaseReport['average'] ?? 0, 0) }}
                    </span>
                </div>
            </div>
            <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                @forelse($recentEcommercePurchases as $purchase)
                    @php
                        $status = strtolower((string) $purchase->payment_status);
                        $badge = match($status) {
                            'paid' => 'background:#dcfce7;color:#15803d;',
                            'partial' => 'background:#fef3c7;color:#b45309;',
                            'due' => 'background:#fee2e2;color:#b91c1c;',
                            default => 'background:#f3f4f6;color:#6b7280;',
                        };
                    @endphp
                    <div class="px-5 py-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ $purchase->purchase_no }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $purchase->supplier?->name ?? 'Supplier' }} - {{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y') }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-extrabold text-gray-900">৳ {{ number_format($purchase->total_amount, 0) }}</p>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="{{ $badge }}">{{ ucfirst($status ?: 'unknown') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-gray-400 text-sm">No ecommerce purchases found for this period.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-700">Top Selling Products</h2>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($topSellingProducts as $product)
                    <div class="px-5 py-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ $product->product_name }}</p>
                            <p class="text-xs text-gray-400">{{ $product->sku ?? 'No SKU' }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-extrabold text-blue-600">{{ number_format($product->quantity) }} pcs</p>
                            <p class="text-xs text-gray-500">৳ {{ number_format($product->total, 0) }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">No product sales found.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-700">Top Purchased Products</h2>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($topPurchasedProducts as $product)
                    <div class="px-5 py-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ $product->product_name }}</p>
                            <p class="text-xs text-gray-400">{{ $product->sku ?? 'No SKU' }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-extrabold text-amber-600">{{ number_format($product->quantity) }} pcs</p>
                            <p class="text-xs text-gray-500">৳ {{ number_format($product->total, 0) }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">No purchased products found.</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    {{-- ── Payment Schedules ── --}}
    @can('view payment schedule')
    @php
        $schedTodayUrl  = request()->fullUrlWithQuery(['schedule_filter' => 'today']);
        $sched7DaysUrl  = request()->fullUrlWithQuery(['schedule_filter' => '7days']);
        $schedRouteUrl  = route('role.payment-schedules.index', ['role' => request()->route('role')]);
        $totalPayable    = $companyPayable->sum('amount');
        $totalReceivable = $companyReceivable->sum('amount');
        $overduePayable  = $companyPayable->where('status', 'overdue')->count();
        $overdueReceivable = $companyReceivable->where('status', 'overdue')->count();
    @endphp

    {{-- Quick-glance summary strip — always visible above the fold --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-red-500 to-red-600 px-5 py-4 shadow-md">
            <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>
            <p class="text-xs font-semibold uppercase tracking-widest text-red-100">Payable</p>
            <p class="mt-1 text-2xl font-black text-white">৳ {{ number_format($totalPayable, 0) }}</p>
            <div class="mt-1 flex items-center gap-2">
                <span class="text-xs text-red-200">{{ $companyPayable->count() }} item(s)</span>
                @if($overduePayable > 0)
                <span class="animate-pulse rounded-full bg-white/25 px-2 py-0.5 text-xs font-bold text-white">
                    {{ $overduePayable }} overdue
                </span>
                @endif
            </div>
            <i class="fas fa-arrow-up absolute bottom-3 right-5 text-3xl text-white/20"></i>
        </div>
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 px-5 py-4 shadow-md">
            <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-100">Receivable</p>
            <p class="mt-1 text-2xl font-black text-white">৳ {{ number_format($totalReceivable, 0) }}</p>
            <div class="mt-1 flex items-center gap-2">
                <span class="text-xs text-emerald-200">{{ $companyReceivable->count() }} item(s)</span>
                @if($overdueReceivable > 0)
                <span class="animate-pulse rounded-full bg-white/25 px-2 py-0.5 text-xs font-bold text-white">
                    {{ $overdueReceivable }} overdue
                </span>
                @endif
            </div>
            <i class="fas fa-arrow-down absolute bottom-3 right-5 text-3xl text-white/20"></i>
        </div>
    </div>

    {{-- Detailed schedule list --}}
    <div class="rounded-2xl border-2 border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-5 py-3">
            <h2 class="flex items-center gap-2 text-sm font-bold text-gray-700">
                <i class="fas fa-calendar-alt text-indigo-600"></i> Payment Schedules
                <span class="text-xs font-normal text-gray-400">
                    @if($scheduleFilter === '7days')
                        {{ \Carbon\Carbon::today()->format('d M') }} – {{ \Carbon\Carbon::today()->addDays(6)->format('d M Y') }}
                    @else
                        {{ \Carbon\Carbon::today()->format('d M Y') }}
                    @endif
                </span>
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ $schedTodayUrl }}" class="no-underline rounded-full border px-3 py-1 text-xs font-semibold transition-colors {{ $scheduleFilter !== '7days' ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 bg-white text-gray-500 hover:border-indigo-300' }}">Today</a>
                <a href="{{ $sched7DaysUrl }}" class="no-underline rounded-full border px-3 py-1 text-xs font-semibold transition-colors {{ $scheduleFilter === '7days' ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 bg-white text-gray-500 hover:border-indigo-300' }}">7 Days</a>
                <a href="{{ $schedRouteUrl }}" class="no-underline rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-100">View All</a>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">

            {{-- Payable --}}
            <div>
                <div class="flex items-center justify-between border-b-2 border-red-400 bg-red-50 px-5 py-2.5">
                    <span class="flex items-center gap-2 text-sm font-extrabold text-red-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white text-xs"><i class="fas fa-arrow-up"></i></span>
                        Payable
                    </span>
                    <span class="rounded-lg bg-red-500 px-3 py-0.5 text-sm font-black text-white">৳ {{ number_format($totalPayable, 0) }}</span>
                </div>
                <div class="max-h-64 divide-y divide-gray-50 overflow-y-auto">
                    @forelse($companyPayable as $sched)
                    @php $isOverdue = $sched->status === 'overdue'; @endphp
                    <div class="flex items-center justify-between gap-4 border-l-4 {{ $isOverdue ? 'border-l-red-500 bg-red-50/40' : 'border-l-transparent' }} px-4 py-3 transition-colors hover:bg-red-50/30">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-gray-800">{{ $sched->party_name ?? '—' }}</p>
                            <p class="mt-0.5 text-xs text-gray-400">
                                {{ \Carbon\Carbon::parse($sched->scheduled_date)->format('d M Y') }}{{ $sched->source_label ? ' · '.$sched->source_label : '' }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-extrabold text-red-600">৳ {{ number_format($sched->amount, 0) }}</p>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $sched->status === 'overdue'  ? 'bg-red-100 text-red-700' :
                                   ($sched->status === 'approved' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700') }}">
                                @if($isOverdue)<i class="fas fa-exclamation-circle mr-0.5"></i>@endif
                                {{ ucfirst($sched->status) }}
                            </span>
                        </div>
                    </div>
                    @empty
                    <div class="px-5 py-8 text-center text-sm text-gray-400">No payable schedules.</div>
                    @endforelse
                </div>
            </div>

            {{-- Receivable --}}
            <div>
                <div class="flex items-center justify-between border-b-2 border-emerald-400 bg-emerald-50 px-5 py-2.5">
                    <span class="flex items-center gap-2 text-sm font-extrabold text-emerald-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-white text-xs"><i class="fas fa-arrow-down"></i></span>
                        Receivable
                    </span>
                    <span class="rounded-lg bg-emerald-500 px-3 py-0.5 text-sm font-black text-white">৳ {{ number_format($totalReceivable, 0) }}</span>
                </div>
                <div class="max-h-64 divide-y divide-gray-50 overflow-y-auto">
                    @forelse($companyReceivable as $sched)
                    @php $isOverdue = $sched->status === 'overdue'; @endphp
                    <div class="flex items-center justify-between gap-4 border-l-4 {{ $isOverdue ? 'border-l-red-500 bg-red-50/40' : 'border-l-transparent' }} px-4 py-3 transition-colors hover:bg-emerald-50/30">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-gray-800">{{ $sched->party_name ?? '—' }}</p>
                            <p class="mt-0.5 text-xs text-gray-400">
                                {{ \Carbon\Carbon::parse($sched->scheduled_date)->format('d M Y') }}{{ $sched->source_label ? ' · '.$sched->source_label : '' }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-extrabold text-emerald-600">৳ {{ number_format($sched->amount, 0) }}</p>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $sched->status === 'overdue'  ? 'bg-red-100 text-red-700' :
                                   ($sched->status === 'approved' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700') }}">
                                @if($isOverdue)<i class="fas fa-exclamation-circle mr-0.5"></i>@endif
                                {{ ucfirst($sched->status) }}
                            </span>
                        </div>
                    </div>
                    @empty
                    <div class="px-5 py-8 text-center text-sm text-gray-400">No receivable schedules.</div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    @endcan

    {{-- ── Recent Transactions ── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-700">Recent Transactions</h2>
            <span class="text-xs text-gray-400">Latest activity</span>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($recentTransactions as $tx)
            @php
                $isSale = $tx->type === 'sale';
                $status = strtolower((string)$tx->status);
                $dotColor = match($status) {
                    'paid' => '#22c55e',
                    'partial' => '#f59e0b',
                    'unpaid', 'due' => '#ef4444',
                    'expense' => '#6366f1',
                    default => '#94a3b8',
                };
                $badgeBg = match($status) {
                    'paid' => 'background:rgba(34,197,94,.12);color:#16a34a;',
                    'partial' => 'background:rgba(245,158,11,.12);color:#d97706;',
                    'unpaid', 'due' => 'background:rgba(239,68,68,.12);color:#dc2626;',
                    'expense' => 'background:rgba(99,102,241,.12);color:#4f46e5;',
                    default => 'background:#f3f4f6;color:#6b7280;',
                };
                $badgeLabel = match($status) {
                    'paid' => 'Paid',
                    'partial' => 'Partial',
                    'unpaid' => 'Unpaid',
                    'due' => 'Due',
                    'expense' => 'Expense',
                    default => ucfirst($status),
                };
            @endphp
            <div class="flex items-center gap-3 px-5 py-3">
                <div class="w-2 h-2 rounded-full shrink-0" style="background:{{ $dotColor }};"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-700 font-medium truncate">{{ $tx->label }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($tx->date)->format('d M Y') }}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-sm font-bold text-gray-900">৳ {{ number_format($tx->amount, 0) }}</p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="{{ $badgeBg }}">{{ $badgeLabel }}</span>
                </div>
            </div>
            @empty
            <div class="px-5 py-8 text-center text-gray-400 text-sm">
                No transactions found for this period.
            </div>
            @endforelse
        </div>
    </div>

</div>

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels   = @json($chartLabels);
    const revenue  = @json($chartRevenue);
    const purchase = @json($chartPurchase);
    const expense  = @json($chartExpense);
    const salary   = @json($chartSalaryPaid);

    // Bar chart
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Revenue',  data: revenue,  backgroundColor: '#2563eb', borderRadius: 4 },
                { label: 'Purchase', data: purchase, backgroundColor: '#f59e0b', borderRadius: 4 },
                { label: 'Expense',  data: expense,  backgroundColor: '#ef4444', borderRadius: 4 },
                { label: 'Salary Paid', data: salary, backgroundColor: '#0f766e', borderRadius: 4 },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: {
                    grid: { color: '#f3f4f6' },
                    ticks: {
                        font: { size: 11 },
                        callback: v => '৳' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v)
                    }
                }
            }
        }
    });

    // Doughnut chart
    const total = revenue.reduce((a,b)=>a+b,0)
                + purchase.reduce((a,b)=>a+b,0)
                + expense.reduce((a,b)=>a+b,0)
                + salary.reduce((a,b)=>a+b,0);

    new Chart(document.getElementById('breakdownChart'), {
        type: 'doughnut',
        data: {
            labels: ['Revenue','Purchase','Expense','Salary'],
            datasets: [{
                data: [
                    revenue.reduce((a,b)=>a+b,0),
                    purchase.reduce((a,b)=>a+b,0),
                    expense.reduce((a,b)=>a+b,0),
                    salary.reduce((a,b)=>a+b,0),
                ],
                backgroundColor: ['#2563eb','#f59e0b','#ef4444','#0f766e'],
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ৳' + ctx.parsed.toLocaleString()
                    }
                }
            }
        }
    });
})();
</script>
@endsection

@endsection
