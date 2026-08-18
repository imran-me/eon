@extends('layout.app')
@section('title', 'Monthly Profit Sheet')

@section('main-content')
@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp

<div class="min-h-screen bg-gray-50">

    {{-- ══ PAGE HEADER ══ --}}
    <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-700 px-6 py-5 shadow-lg">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-chart-line text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white leading-tight">Monthly Profit Sheet</h1>
                    <p class="text-xs text-emerald-100 mt-0.5">Ticket &middot; Visa &middot; Flight &middot; File — Revenue vs Cost</p>
                </div>
            </div>
            {{-- Year Selector --}}
            <div class="flex items-center gap-2">
                <label class="text-xs text-emerald-200 font-semibold">Year</label>
                <select id="yearSelect"
                    class="text-sm font-semibold border-0 rounded-xl px-4 py-2 bg-white/20 text-white focus:ring-2 focus:ring-white/40 focus:outline-none cursor-pointer">
                    @foreach($years as $y)
                    <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }} class="text-gray-800">{{ $y }}</option>
                    @endforeach
                </select>
                <button id="viewToggle"
                    class="text-xs font-semibold px-4 py-2 rounded-xl bg-white/20 text-white hover:bg-white/30 transition border border-white/30">
                    <i class="fas fa-table-columns mr-1"></i> <span id="viewLabel">Detailed</span>
                </button>
                <div class="relative" id="printWrapper">
                    <button id="printBtn" onclick="togglePrintPanel()"
                        class="text-xs font-semibold px-4 py-2 rounded-xl bg-white/20 text-white hover:bg-white/30 transition border border-white/30">
                        <i class="fas fa-print mr-1"></i> Print
                    </button>
                    {{-- Print options panel --}}
                    <div id="printPanel" class="hidden absolute right-0 top-full mt-2 bg-white rounded-xl shadow-2xl border border-gray-200 p-4 z-50 w-56">
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Select Modules</p>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 hover:text-violet-600">
                                <input type="checkbox" id="chkTicket" value="ticket" checked class="w-3.5 h-3.5 accent-violet-600">
                                <i class="fas fa-ticket text-violet-500 text-xs w-3"></i> Ticket
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 hover:text-blue-600">
                                <input type="checkbox" id="chkVisa" value="visa" checked class="w-3.5 h-3.5 accent-blue-600">
                                <i class="fas fa-passport text-blue-500 text-xs w-3"></i> Visa
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 hover:text-sky-600">
                                <input type="checkbox" id="chkFlight" value="flight" checked class="w-3.5 h-3.5 accent-sky-600">
                                <i class="fas fa-plane text-sky-500 text-xs w-3"></i> Flight
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 hover:text-amber-600">
                                <input type="checkbox" id="chkFile" value="file" checked class="w-3.5 h-3.5 accent-amber-600">
                                <i class="fas fa-folder-open text-amber-500 text-xs w-3"></i> File
                            </label>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button onclick="closePrintPanel()" class="flex-1 text-xs py-1.5 rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-50">Cancel</button>
                            <button onclick="confirmPrint()" class="flex-1 text-xs py-1.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700">Print</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="px-6 py-5 space-y-5">

        {{-- ══ LOADING STATE ══ --}}
        <div id="loadingState" class="flex items-center justify-center py-20">
            <div class="text-center">
                <i class="fas fa-circle-notch fa-spin text-emerald-500 text-3xl mb-3"></i>
                <p class="text-sm text-gray-500">Loading profit data…</p>
            </div>
        </div>

        {{-- ══ CONTENT (hidden until loaded) ══ --}}
        <div id="profitContent" class="hidden space-y-5">

            {{-- ── Annual Summary Cards ── --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Total Revenue</p>
                    <p id="cardRevenue" class="text-2xl font-bold text-gray-800">—</p>
                    <p class="text-xs text-gray-400 mt-1">All modules combined</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Total Cost</p>
                    <p id="cardCost" class="text-2xl font-bold text-red-500">—</p>
                    <p class="text-xs text-gray-400 mt-1">All modules combined</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-4 bg-gradient-to-br from-emerald-50 to-teal-50">
                    <p class="text-[10px] text-emerald-600 uppercase tracking-wider font-bold mb-1">Net Profit</p>
                    <p id="cardProfit" class="text-2xl font-bold text-emerald-600">—</p>
                    <p class="text-xs text-emerald-500 mt-1">Revenue minus Cost</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Profit Margin</p>
                    <p id="cardMargin" class="text-2xl font-bold text-teal-600">—</p>
                    <p class="text-xs text-gray-400 mt-1">Annual average</p>
                </div>
            </div>

            {{-- ── Module Breakdown Cards ── --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Ticket --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-violet-100 flex items-center justify-center">
                            <i class="fas fa-ticket text-violet-600 text-sm"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700">Ticket</span>
                    </div>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Revenue</span><span id="modTicketRev" class="font-semibold text-gray-700">—</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Cost</span><span id="modTicketCost" class="font-semibold text-red-500">—</span></div>
                        <div class="flex justify-between border-t border-gray-100 pt-1 mt-1"><span class="text-gray-500 font-semibold">Profit</span><span id="modTicketProfit" class="font-bold text-emerald-600">—</span></div>
                    </div>
                </div>
                {{-- Visa --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-passport text-blue-600 text-sm"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700">Visa</span>
                    </div>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Revenue</span><span id="modVisaRev" class="font-semibold text-gray-700">—</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Cost</span><span id="modVisaCost" class="font-semibold text-red-500">—</span></div>
                        <div class="flex justify-between border-t border-gray-100 pt-1 mt-1"><span class="text-gray-500 font-semibold">Profit</span><span id="modVisaProfit" class="font-bold text-emerald-600">—</span></div>
                    </div>
                </div>
                {{-- Flight --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-100 flex items-center justify-center">
                            <i class="fas fa-plane text-sky-600 text-sm"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700">Flight</span>
                    </div>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Revenue</span><span id="modFlightRev" class="font-semibold text-gray-700">—</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Cost</span><span id="modFlightCost" class="font-semibold text-red-500">—</span></div>
                        <div class="flex justify-between border-t border-gray-100 pt-1 mt-1"><span class="text-gray-500 font-semibold">Profit</span><span id="modFlightProfit" class="font-bold text-emerald-600">—</span></div>
                    </div>
                </div>
                {{-- File --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                            <i class="fas fa-folder-open text-amber-600 text-sm"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700">File</span>
                    </div>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Revenue</span><span id="modFileRev" class="font-semibold text-gray-700">—</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Cost</span><span id="modFileCost" class="font-semibold text-red-500">—</span></div>
                        <div class="flex justify-between border-t border-gray-100 pt-1 mt-1"><span class="text-gray-500 font-semibold">Profit</span><span id="modFileProfit" class="font-bold text-emerald-600">—</span></div>
                    </div>
                </div>
            </div>

            {{-- ── Monthly Table ── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-700 flex items-center gap-2">
                            <i class="fas fa-calendar-alt text-emerald-500 text-xs"></i>
                            Monthly Breakdown — <span id="tableYear" class="text-emerald-600"></span>
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">Revenue · Cost · Profit per module per month</p>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-400">
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>Profit</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>Loss</span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs" id="profitTable">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"></tbody>
                        <tfoot id="tableFoot"></tfoot>
                    </table>
                </div>
            </div>

        </div>{{-- /profitContent --}}

    </div>
</div>

@endsection

@section('js')
<script>
const DATA_URL  = "{{ route('role.report.monthly-profit.data',  ['role' => $role]) }}";
const PRINT_URL = "{{ route('role.report.monthly-profit.print', ['role' => $role]) }}";

const fmt = (n) => '৳' + Number(n).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
const fmtP = (n) => (n >= 0 ? '+' : '') + fmt(n);
const pct  = (n) => n + '%';

let profitData = null;
let detailedView = false;

// ── Load ────────────────────────────────────────────────────────────────────
function loadData(year) {
    document.getElementById('loadingState').classList.remove('hidden');
    document.getElementById('profitContent').classList.add('hidden');

    fetch(`${DATA_URL}?year=${year}`)
        .then(r => r.json())
        .then(data => {
            profitData = data;
            render(data);
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('profitContent').classList.remove('hidden');
        })
        .catch(() => {
            document.getElementById('loadingState').innerHTML =
                '<p class="text-red-500 text-sm">Failed to load data. Please try again.</p>';
        });
}

// ── Render ──────────────────────────────────────────────────────────────────
function render(data) {
    const a = data.annual;
    document.getElementById('tableYear').textContent = data.year;

    // Summary cards
    document.getElementById('cardRevenue').textContent = fmt(a.total.revenue);
    document.getElementById('cardCost').textContent    = fmt(a.total.cost);
    document.getElementById('cardProfit').textContent  = fmt(a.total.profit);
    document.getElementById('cardMargin').textContent  = pct(a.total.margin);

    // Module cards
    const mods = ['ticket','visa','flight','file'];
    const ids  = ['Ticket','Visa','Flight','File'];
    mods.forEach((mod, i) => {
        document.getElementById(`mod${ids[i]}Rev`).textContent    = fmt(a[mod].revenue);
        document.getElementById(`mod${ids[i]}Cost`).textContent   = fmt(a[mod].cost);
        document.getElementById(`mod${ids[i]}Profit`).textContent = fmt(a[mod].profit);
        const el = document.getElementById(`mod${ids[i]}Profit`);
        el.className = 'font-bold ' + (a[mod].profit >= 0 ? 'text-emerald-600' : 'text-red-500');
    });

    renderTable(data);
}

// ── Render Table ─────────────────────────────────────────────────────────────
function renderTable(data) {
    const mods   = ['ticket','visa','flight','file'];
    const labels = ['🎫 Ticket','📋 Visa','✈ Flight','📁 File'];
    const colors = {
        ticket: {bg:'bg-violet-50', text:'text-violet-700', head:'bg-violet-600'},
        visa:   {bg:'bg-blue-50',   text:'text-blue-700',   head:'bg-blue-600'},
        flight: {bg:'bg-sky-50',    text:'text-sky-700',    head:'bg-sky-600'},
        file:   {bg:'bg-amber-50',  text:'text-amber-700',  head:'bg-amber-600'},
    };

    // ── Head ──
    let headHtml = '<tr class="bg-gray-800 text-white">';
    headHtml += '<th class="px-4 py-3 text-left font-semibold w-24 sticky left-0 bg-gray-800 z-10">Month</th>';
    if (detailedView) {
        mods.forEach((mod, i) => {
            headHtml += `<th colspan="3" class="px-3 py-3 text-center font-semibold ${colors[mod].head}">${labels[i]}</th>`;
        });
    } else {
        mods.forEach((mod, i) => {
            headHtml += `<th colspan="3" class="px-3 py-3 text-center font-semibold ${colors[mod].head}">${labels[i]}</th>`;
        });
    }
    headHtml += '<th colspan="3" class="px-3 py-3 text-center font-semibold bg-emerald-700">📊 Total</th>';
    headHtml += '</tr>';

    // Sub-header
    headHtml += '<tr class="bg-gray-100 text-gray-500 text-[10px] uppercase tracking-wide">';
    headHtml += '<th class="px-4 py-2 text-left sticky left-0 bg-gray-100 z-10"></th>';
    mods.forEach(() => {
        headHtml += '<th class="px-3 py-2 text-right">Revenue</th>';
        headHtml += '<th class="px-3 py-2 text-right">Cost</th>';
        headHtml += '<th class="px-3 py-2 text-right">Profit</th>';
    });
    headHtml += '<th class="px-3 py-2 text-right">Revenue</th>';
    headHtml += '<th class="px-3 py-2 text-right">Cost</th>';
    headHtml += '<th class="px-3 py-2 text-right font-bold text-emerald-700">Profit</th>';
    headHtml += '</tr>';

    document.getElementById('tableHead').innerHTML = headHtml;

    // ── Body ──
    let bodyHtml = '';
    let totals = { ticket:{r:0,c:0,p:0}, visa:{r:0,c:0,p:0}, flight:{r:0,c:0,p:0}, file:{r:0,c:0,p:0}, total:{r:0,c:0,p:0} };

    data.months.forEach(row => {
        const hasData = row.total.revenue > 0 || row.total.cost > 0;
        const rowBg   = hasData ? '' : 'opacity-40';
        bodyHtml += `<tr class="border-b border-gray-100 hover:bg-emerald-50/40 transition ${rowBg}">`;
        bodyHtml += `<td class="px-4 py-3 font-semibold text-gray-700 sticky left-0 bg-white z-10 whitespace-nowrap">
            <span class="text-xs">${row.month_name}</span>
        </td>`;

        mods.forEach(mod => {
            const d = row[mod];
            totals[mod].r += d.revenue; totals[mod].c += d.cost; totals[mod].p += d.profit;
            const profitCls = d.profit > 0 ? 'text-emerald-600 font-bold' : d.profit < 0 ? 'text-red-500 font-bold' : 'text-gray-400';
            bodyHtml += `<td class="px-3 py-3 text-right text-gray-600">${d.revenue > 0 ? fmt(d.revenue) : '<span class="text-gray-300">—</span>'}</td>`;
            bodyHtml += `<td class="px-3 py-3 text-right text-red-400">${d.cost > 0 ? fmt(d.cost) : '<span class="text-gray-300">—</span>'}</td>`;
            bodyHtml += `<td class="px-3 py-3 text-right ${profitCls}">${(d.revenue > 0 || d.cost > 0) ? fmt(d.profit) : '<span class="text-gray-300">—</span>'}</td>`;
        });

        // Total
        totals.total.r += row.total.revenue;
        totals.total.c += row.total.cost;
        totals.total.p += row.total.profit;
        const tpCls = row.total.profit > 0 ? 'text-emerald-600 font-bold text-sm' : row.total.profit < 0 ? 'text-red-500 font-bold text-sm' : 'text-gray-400';
        bodyHtml += `<td class="px-3 py-3 text-right font-semibold text-gray-700">${row.total.revenue > 0 ? fmt(row.total.revenue) : '<span class="text-gray-300">—</span>'}</td>`;
        bodyHtml += `<td class="px-3 py-3 text-right text-red-500">${row.total.cost > 0 ? fmt(row.total.cost) : '<span class="text-gray-300">—</span>'}</td>`;
        bodyHtml += `<td class="px-3 py-3 text-right ${tpCls}">${(row.total.revenue > 0 || row.total.cost > 0) ? fmt(row.total.profit) : '<span class="text-gray-300">—</span>'}</td>`;
        bodyHtml += '</tr>';
    });

    document.getElementById('tableBody').innerHTML = bodyHtml;

    // ── Foot ──
    let footHtml = '<tr class="bg-gray-800 text-white font-bold text-xs">';
    footHtml += '<td class="px-4 py-3 sticky left-0 bg-gray-800 z-10">TOTAL</td>';
    mods.forEach(mod => {
        footHtml += `<td class="px-3 py-3 text-right">${fmt(totals[mod].r)}</td>`;
        footHtml += `<td class="px-3 py-3 text-right text-red-300">${fmt(totals[mod].c)}</td>`;
        const pc = totals[mod].p;
        footHtml += `<td class="px-3 py-3 text-right ${pc >= 0 ? 'text-emerald-300' : 'text-red-400'}">${fmt(pc)}</td>`;
    });
    footHtml += `<td class="px-3 py-3 text-right">${fmt(totals.total.r)}</td>`;
    footHtml += `<td class="px-3 py-3 text-right text-red-300">${fmt(totals.total.c)}</td>`;
    const tp = totals.total.p;
    footHtml += `<td class="px-3 py-3 text-right text-lg ${tp >= 0 ? 'text-emerald-300' : 'text-red-400'}">${fmt(tp)}</td>`;
    footHtml += '</tr>';
    document.getElementById('tableFoot').innerHTML = footHtml;
}

// ── Events ──────────────────────────────────────────────────────────────────
document.getElementById('yearSelect').addEventListener('change', function() {
    loadData(this.value);
});

document.getElementById('viewToggle').addEventListener('click', function() {
    detailedView = !detailedView;
    document.getElementById('viewLabel').textContent = detailedView ? 'Summary' : 'Detailed';
    if (profitData) renderTable(profitData);
});

// ── Init ─────────────────────────────────────────────────────────────────────
loadData(document.getElementById('yearSelect').value);

// ── Print panel ──────────────────────────────────────────────────────────────
function togglePrintPanel() {
    document.getElementById('printPanel').classList.toggle('hidden');
}
function closePrintPanel() {
    document.getElementById('printPanel').classList.add('hidden');
}
function confirmPrint() {
    const year = document.getElementById('yearSelect').value;
    const types = ['ticket','visa','flight','file']
        .filter(t => document.getElementById('chk' + t.charAt(0).toUpperCase() + t.slice(1)).checked);
    if (types.length === 0) { alert('Please select at least one module.'); return; }
    const params = new URLSearchParams({ year });
    types.forEach(t => params.append('types[]', t));
    window.open(`${PRINT_URL}?${params.toString()}`, '_blank');
    closePrintPanel();
}
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('printWrapper');
    if (wrapper && !wrapper.contains(e.target)) closePrintPanel();
});
</script>

<style>
@media print {
    #sidebar, #mobileMenuButton, .header, .admin-footer { display: none !important; }
    #mainContentWrapper { margin-left: 0 !important; }
    body, .min-h-screen { background: #fff !important; }
    main { padding: 0 !important; }
    .bg-gradient-to-r { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    button, select { display: none !important; }
}
</style>
@endsection
