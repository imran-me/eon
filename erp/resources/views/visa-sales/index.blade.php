@extends('layout.app')

@section('meta-information')
    <title>Manage Visa Sales</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--open, .select2-dropdown { z-index: 100001 !important; }
    .swal2-container { z-index: 100000 !important; }
    span[aria-current="page"] span { background-color: #0d9488 !important; color: white; border-color: #0d9488; }
    /* Used in JS-rendered rows in modal application tables */
    .vs-badge { display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px; }
    .badge-paid    { background:#dcfce7;color:#16a34a; }
    .badge-partial { background:#fef9c3;color:#a16207; }
    .badge-pending { background:#fee2e2;color:#dc2626; }
    /* JS toggles .selected on <tr> inside modal application tables */
    .vs-apps-table tr.selected td { background:#ccfbf1; }
    .vs-apps-table tr:hover td { background:#f0fdfa; }
    /* Detail view modal — JS-generated HTML uses these classes */
    .vsd-table th { font-size:10px;font-weight:700;text-transform:uppercase;color:#fff;padding:8px 10px;background:#1d4ed8; }
    .vsd-table td { font-size:12px;color:#374151;padding:9px 10px;border-bottom:1px solid #f3f4f6;vertical-align:top; }
    .vsd-table tr:last-child td { border-bottom:none; }
    .vsd-table tr:hover td { background:#f8fafc; }
</style>
@endsection

@section('main-content')
@php
    use Illuminate\Support\Str;
    $role = Str::slug(auth()->user()->getRoleNames()->first());
    $taka = '৳';
    function fmtMoney($n) { return number_format($n, 0); }
    function fmtL($n) { if($n >= 100000) return number_format($n/100000, 1).'L'; if($n >= 1000) return number_format($n/1000, 1).'K'; return number_format($n, 0); }
@endphp

{{-- Page header --}}
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-teal-50 text-teal-600 flex-shrink-0"><i class="fas fa-file-invoice-dollar"></i></span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Manage Visa Sales</h1>
        <p class="text-xs text-slate-500">Client-wise consolidated vouchers — bundle multiple applications into one invoice</p>
    </div>
    <button type="button" class="vs-create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700">
        <i class="fas fa-plus mr-1"></i> Create Sale
    </button>
</header>

@if(session('success'))
<div class="mb-4 flex gap-3 items-center rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    <i class="fas fa-check-circle text-emerald-500"></i> {{ session('success') }}
</div>
@endif

{{-- Stat cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="rounded-xl p-5 flex flex-col gap-2 bg-gradient-to-br from-blue-50 to-blue-100">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="text-3xl font-black text-blue-800">{{ $stats['total_sales_mtd'] }}</div>
        <div class="text-xs font-bold text-blue-600 uppercase tracking-wide">Total Sales (MTD)</div>
        <div class="text-xs text-blue-400">{{ $sales->total() }} applications bundled</div>
    </div>
    <div class="rounded-xl p-5 flex flex-col gap-2 bg-gradient-to-br from-emerald-50 to-emerald-100">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="text-3xl font-black text-emerald-800">{{ $taka }} {{ fmtL($stats['revenue_mtd']) }}</div>
        <div class="text-xs font-bold text-emerald-600 uppercase tracking-wide">Revenue</div>
        <div class="text-xs text-emerald-400">This month total</div>
    </div>
    <div class="rounded-xl p-5 flex flex-col gap-2 bg-gradient-to-br from-amber-50 to-amber-100">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="text-3xl font-black text-amber-800">{{ $taka }} {{ fmtL($stats['total_due']) }}</div>
        <div class="text-xs font-bold text-amber-600 uppercase tracking-wide">Due / Pending</div>
        <div class="text-xs text-amber-400">Across all invoices</div>
    </div>
    <div class="rounded-xl p-5 flex flex-col gap-2 bg-gradient-to-br from-rose-50 to-rose-100">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-rose-400 to-rose-600 flex items-center justify-center text-white">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="text-3xl font-black text-rose-800">{{ $taka }} {{ fmtL(max(0, $stats['commission'])) }}</div>
        <div class="text-xs font-bold text-rose-600 uppercase tracking-wide">Commission Earned</div>
        <div class="text-xs text-rose-400">Net profit</div>
    </div>
</div>

{{-- Table card with integrated filter --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    {{-- Integrated filter --}}
    <form method="GET" action="{{ route('role.visa-sales.index', ['role' => $role]) }}">
        <div class="flex flex-wrap items-center gap-2 bg-slate-50 px-4 py-3">
            <div class="relative flex-1 min-w-[180px]">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Search invoice, client name..."
                    class="w-full pl-8 rounded-xl border border-slate-300 px-3 py-2 text-xs outline-none focus:border-blue-500">
            </div>
            <input type="month" name="month" value="{{ request('month', now()->format('Y-m')) }}"
                class="rounded-xl border border-slate-300 px-3 py-2 text-xs outline-none focus:border-blue-500">
            <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-xs outline-none focus:border-blue-500">
                <option value="">All Payment Status</option>
                <option value="pending"  {{ request('status')=='pending'  ? 'selected' : '' }}>Pending</option>
                <option value="partial"  {{ request('status')=='partial'  ? 'selected' : '' }}>Partial</option>
                <option value="paid"     {{ request('status')=='paid'     ? 'selected' : '' }}>Paid</option>
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white">Filter</button>
            <a href="{{ route('role.visa-sales.index', ['role' => $role]) }}"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Invoice #</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Client Name</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Applications</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Country / Type</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Date</th>
                    <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Total ({{ $taka }})</th>
                    <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Paid</th>
                    <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Due</th>
                    <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($sales as $sale)
            @php
                $firstProcess = optional($sale->items->first())->visaProcess;
                $country      = optional($firstProcess)->country;
                $visaType     = optional($firstProcess)->visa_type ?? optional(optional($firstProcess)->visaCategory)->name;
                $appIds       = $sale->items->take(3)->map(fn($i) => optional($i->visaProcess)->application_id)->filter()->implode(', ');
                $extra        = max(0, $sale->items->count() - 3);
                $pBadgeClass  = ['paid' => 'bg-emerald-100 text-emerald-700', 'partial' => 'bg-amber-100 text-amber-700', 'pending' => 'bg-red-100 text-red-700'][$sale->status] ?? 'bg-slate-100 text-slate-600';
            @endphp
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    <span class="font-bold text-teal-700 text-sm">{{ $sale->invoice_number }}</span>
                </td>
                <td class="px-4 py-3">
                    <div class="text-sm font-semibold text-slate-800">{{ $sale->client?->name ?? '—' }}</div>
                    <div class="text-xs text-slate-400">{{ $sale->client?->phone }}</div>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2.5 py-1 text-[10px] font-bold text-teal-700">
                        <i class="fas fa-layer-group text-[9px]"></i>
                        {{ $sale->items->count() }} app{{ $sale->items->count() != 1 ? 's' : '' }}
                    </span>
                    <div class="text-[11px] text-slate-400 mt-0.5">{{ $appIds }}{{ $extra > 0 ? ", +{$extra}" : '' }}</div>
                </td>
                <td class="px-4 py-3">
                    @if($country)<div class="text-sm font-medium text-slate-700">{{ $country->name }}</div>@endif
                    <div class="text-xs text-slate-400">{{ $visaType }}{{ $sale->bundle_label ? ' ('.$sale->bundle_label.')' : '' }}</div>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ $sale->voucher_date->format('d M Y') }}</td>
                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">{{ number_format($sale->total_amount) }}</td>
                <td class="px-4 py-3 text-right text-sm font-medium text-emerald-600">{{ number_format($sale->paid_amount) }}</td>
                <td class="px-4 py-3 text-right text-sm {{ $sale->due_amount > 0 ? 'font-semibold text-red-500' : 'text-slate-400' }}">
                    {{ number_format($sale->due_amount) }}
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $pBadgeClass }}">
                        {{ ucfirst($sale->status) }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-1">
                        <button type="button" class="vs-view-btn flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100"
                            data-id="{{ $sale->id }}" title="View Details">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                        <button type="button" class="vs-edit-btn flex h-8 w-8 items-center justify-center rounded-lg border border-violet-200 bg-violet-50 text-violet-600 hover:bg-violet-100"
                            data-id="{{ $sale->id }}" title="Edit Sale">
                            <i class="fas fa-pencil-alt text-xs"></i>
                        </button>
                        @if($sale->status !== 'paid')
                        <button type="button" class="vs-pay-btn flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100"
                            data-id="{{ $sale->id }}"
                            data-invoice="{{ $sale->invoice_number }}"
                            data-due="{{ $sale->due_amount }}"
                            data-total="{{ $sale->total_amount }}"
                            title="Make Payment">
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                        @else
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-50 text-slate-300">
                            <i class="fas fa-check text-xs"></i>
                        </span>
                        @endif
                        <a href="{{ route('role.visa-sales.voucher', ['role' => $role, 'visaSale' => $sale->id]) }}"
                            target="_blank"
                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-teal-200 bg-teal-50 text-teal-600 hover:bg-teal-100" title="Download Voucher">
                            <i class="fas fa-download text-xs"></i>
                        </a>
                        <button type="button" onclick="vsConfirmDelete('{{ $sale->id }}', '{{ addslashes($sale->invoice_number) }}')"
                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-500 hover:bg-red-100"
                            title="Delete">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="p-12 text-center">
                    <i class="fas fa-receipt mb-3 block text-4xl text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-500">No sales found.</p>
                    <button type="button" class="vs-create-btn mt-2 text-teal-600 text-sm underline">Create your first sale</button>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4">{{ $sales->links() }}</div>
</div>

@include('visa-sales.create-modal')
@include('visa-sales.details-modal')
@include('visa-sales.edit-modal')
@include('visa-sales.delete-modal')

{{-- Payment Modal --}}
<div id="pmtModal" class="fixed inset-0 z-[10000] bg-black/50 hidden [&:not(.hidden)]:flex items-center justify-center p-6">
    <div class="w-full max-w-sm bg-white rounded-2xl p-7 shadow-2xl">
        <div class="flex items-center gap-3 mb-5">
            <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                <i class="fas fa-plus"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900">Make Payment</h3>
                <p id="pmtInvoiceLabel" class="text-xs text-slate-400"></p>
            </div>
        </div>
        <div class="rounded-xl bg-slate-50 px-4 py-3 mb-4 flex justify-between text-sm">
            <span class="text-slate-500">Due Amount</span>
            <span class="font-bold text-red-600">{{ $taka }} <span id="pmtDueDisplay">0</span></span>
        </div>
        <form id="pmtForm" method="POST">
            @csrf @method('PATCH')
            <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Amount ({{ $taka }}) <sup class="text-red-500">*</sup></label>
            <input type="number" name="payment_amount" id="pmtAmountInput"
                min="0.01" step="0.01" placeholder="0.00" required
                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 mb-4">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Method</label>
            <select name="payment_method" id="pmtPaymentMethod" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 mb-4" onchange="toggleVisaSaleBankField('pmt')">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="advance">Advance</option>
                <option value="checque">Cheque</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="mobile_banking">Mobile Banking</option>
                <option value="other">Other</option>
            </select>
            <div id="pmt_bank_field" class="mb-4">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Account (Bank)</label>
                <select name="bank_id" id="pmtBankId" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500">
                    <option value="">— Select bank —</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="vsClosePmt()"
                    class="rounded-xl border border-slate-200 text-slate-700 font-semibold text-sm px-4 py-2 hover:bg-slate-50">Cancel</button>
                <button type="submit"
                    class="rounded-xl bg-emerald-600 text-white font-semibold text-sm px-5 py-2 hover:bg-emerald-700 flex items-center gap-2">
                    <i class="fas fa-check text-xs"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const VS_APPS_URL           = "{{ route('role.visa-sales.applications', ['role' => $role]) }}";
const VS_OTHER_SERVICES_URL = "{{ route('role.visa-sales.other-services', ['role' => $role]) }}";
const VS_PMT_BASE  = "{{ url($role . '/visa-sales') }}";
const VS_SHOW_BASE = "{{ url($role . '/visa-sales') }}";

const vsClientsData = @json($clients->keyBy('id'));
const vsAgentsData  = @json($agents->keyBy('id'));
const vsPartyFields = {
    create: { id: '#create_client_id', phone: '#create_client_phone', email: '#create_client_email', client: '#create_select_client', agent: '#create_select_agent' },
    vse:    { id: '#vseClientId',       phone: '#vseClientPhone',       email: '#vseClientEmail',       client: '#vseSelectClient',    agent: '#vseSelectAgent' },
};

// Picking a client locks out the agent dropdown (and vice versa) — only one
// party can be chosen at a time — sets the hidden client_id field that's
// actually submitted, and shows phone/email from that party's record.
// Guarded against re-entrancy since clearing the other <select> re-fires its
// own onchange.
let vsPickingParty = false;
function vsPickParty(prefix, kind, id) {
    if (vsPickingParty) return;
    vsPickingParty = true;

    const fields = vsPartyFields[prefix];
    const otherKind = kind === 'client' ? 'agent' : 'client';

    if (id) {
        $(fields[otherKind]).val('').trigger('change.select2').prop('disabled', true);
    } else {
        $(fields[otherKind]).prop('disabled', false);
    }

    vsPickingParty = false;

    $(fields.id).val(id || '');

    const party = id ? (kind === 'client' ? vsClientsData : vsAgentsData)[id] : null;
    $(fields.phone).val(party ? (party.phone || '') : '');
    $(fields.email).val(party ? (party.email || '') : '');
}

$(document).ready(function () {
    $('.select2').select2();
});

let vsShowUnpaid   = false;
let vsAppData      = [];
let vsSelectedIds  = new Set();
let vsOtherAppData     = [];
let vsOtherSelectedIds = new Set();
let vsTotalAmount  = 0;

function toggleVisaSaleBankField(prefix) {
    const isAdvance = $(`#${prefix}_payment_method, #${prefix}PaymentMethod`).val() === 'advance';
    $(`#${prefix}_bank_field`).toggleClass('hidden', isAdvance);
    if (isAdvance) $(`#${prefix}_bank_id, #${prefix}BankId`).val('');
}

// ─── Modal open/close ─────────────────────────────────────────────────────────
function vsOpenCreate() {
    $('#vsCreateModal').removeClass('hidden');
    $('#vsCreateForm .error-message').addClass('hidden');
    $('#create_client_id').val('');
    $('#create_select_client').val('').prop('disabled', false).trigger('change.select2');
    $('#create_select_agent').val('').prop('disabled', false).trigger('change.select2');
    $('#create_payment_method').val('cash');
    $('#create_bank_id').val('');
    toggleVisaSaleBankField('create');
    vsLoadApplications();
    vsLoadOtherServices();
}
function vsCloseCreate() {
    $('#vsCreateModal').addClass('hidden');
    vsSelectedIds.clear();
    vsAppData = [];
    vsOtherSelectedIds.clear();
    vsOtherAppData = [];
    $('#vsHiddenInputs').empty();
    $('#vsOtherHiddenInputs').empty();
    vsUpdateTotals();
}

// ─── Application loading ──────────────────────────────────────────────────────
function vsLoadApplications() {
    const countryId = $('#vsCountryFilter').val();
    const params = {};
    if (countryId) params.country_id = countryId;
    if (vsShowUnpaid) params.show_unpaid = 1;

    const $tbody = $('#vsAppsBody');
    $tbody.html('<tr><td colspan="6" class="text-center py-4 text-slate-400 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i> Loading...</td></tr>');

    $.ajax({
        url: VS_APPS_URL,
        method: 'GET',
        data: params,
        success: function(data) {
            vsAppData = data.applications;
            if (!countryId && data.countries.length) {
                const $sel = $('#vsCountryFilter');
                if ($sel.find('option').length <= 1) {
                    data.countries.forEach(c => $sel.append(new Option(c.name, c.id)));
                }
            }
            vsRenderApps();
        },
        error: function() {
            $tbody.html('<tr><td colspan="6" class="text-center py-4 text-red-400 text-xs">Failed to load applications.</td></tr>');
        }
    });
}

function vsRenderApps() {
    const $tbody = $('#vsAppsBody');
    if (!vsAppData.length) {
        $tbody.html('<tr><td colspan="6" class="text-center py-6 text-slate-400 text-xs">No available applications.</td></tr>');
        return;
    }
    $tbody.html(vsAppData.map(app => {
        const checked  = vsSelectedIds.has(app.id) ? 'checked' : '';
        const selClass = vsSelectedIds.has(app.id) ? 'selected' : '';
        const statusBadge = app.status === 'paid'
            ? '<span class="vs-badge badge-paid">Paid</span>'
            : app.status === 'partial'
            ? '<span class="vs-badge badge-partial">Partial</span>'
            : '<span class="vs-badge badge-pending">Pending</span>';
        return `<tr class="${selClass}" id="app-row-${app.id}" onclick="vsToggleApp(${app.id}, ${app.sale_price})">
            <td class="px-2.5 py-2"><input type="checkbox" ${checked} onclick="event.stopPropagation(); vsToggleApp(${app.id}, ${app.sale_price})"></td>
            <td class="px-2.5 py-2 font-mono font-bold text-teal-700">${app.app_id}</td>
            <td class="px-2.5 py-2">${app.applicant}</td>
            <td class="px-2.5 py-2">${app.country}<br><span class="text-slate-400" style="font-size:10px">${app.visa_type}</span></td>
            <td class="px-2.5 py-2 font-semibold text-right">${Number(app.sale_price).toLocaleString()}</td>
            <td class="px-2.5 py-2">${statusBadge}</td>
        </tr>`;
    }).join(''));
}

function vsToggleApp(id, price) {
    if (vsSelectedIds.has(id)) vsSelectedIds.delete(id);
    else vsSelectedIds.add(id);
    vsRenderApps();
    vsUpdateTotals();
}

function vsToggleAllApps(chk) {
    vsAppData.forEach(app => {
        if (chk.checked) vsSelectedIds.add(app.id);
        else vsSelectedIds.delete(app.id);
    });
    vsRenderApps();
    vsUpdateTotals();
}

// ─── Other services loading ───────────────────────────────────────────────────
function vsLoadOtherServices() {
    const $tbody = $('#vsOtherBody');
    $tbody.html('<tr><td colspan="6" class="text-center py-4 text-slate-400 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i> Loading...</td></tr>');

    $.ajax({
        url: VS_OTHER_SERVICES_URL,
        method: 'GET',
        success: function(data) {
            vsOtherAppData = data.services;
            vsRenderOtherServices();
        },
        error: function() {
            $tbody.html('<tr><td colspan="6" class="text-center py-4 text-red-400 text-xs">Failed to load other services.</td></tr>');
        }
    });
}

function vsRenderOtherServices() {
    const $tbody = $('#vsOtherBody');
    if (!vsOtherAppData.length) {
        $tbody.html('<tr><td colspan="6" class="text-center py-6 text-slate-400 text-xs">No billable other services available.</td></tr>');
        return;
    }
    $tbody.html(vsOtherAppData.map(svc => {
        const checked  = vsOtherSelectedIds.has(svc.id) ? 'checked' : '';
        const selClass = vsOtherSelectedIds.has(svc.id) ? 'selected' : '';
        return `<tr class="${selClass}" id="other-row-${svc.id}" onclick="vsToggleOtherService(${svc.id})">
            <td class="px-2.5 py-2"><input type="checkbox" ${checked} onclick="event.stopPropagation(); vsToggleOtherService(${svc.id})"></td>
            <td class="px-2.5 py-2 font-mono font-bold text-teal-700">${svc.service_code}</td>
            <td class="px-2.5 py-2">${svc.applicant}</td>
            <td class="px-2.5 py-2"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:${svc.service_bg};color:${svc.service_color};">${svc.service_type}</span></td>
            <td class="px-2.5 py-2 font-semibold text-right">${Number(svc.sale_price).toLocaleString()}</td>
            <td class="px-2.5 py-2">${svc.status}</td>
        </tr>`;
    }).join(''));
}

function vsToggleOtherService(id) {
    if (vsOtherSelectedIds.has(id)) vsOtherSelectedIds.delete(id);
    else vsOtherSelectedIds.add(id);
    vsRenderOtherServices();
    vsUpdateTotals();
}

function vsToggleAllOther(chk) {
    vsOtherAppData.forEach(svc => {
        if (chk.checked) vsOtherSelectedIds.add(svc.id);
        else vsOtherSelectedIds.delete(svc.id);
    });
    vsRenderOtherServices();
    vsUpdateTotals();
}

function vsUpdateTotals() {
    let total = 0;
    vsAppData.forEach(app => { if (vsSelectedIds.has(app.id)) total += parseFloat(app.sale_price) || 0; });

    let otherTotal = 0;
    vsOtherAppData.forEach(svc => { if (vsOtherSelectedIds.has(svc.id)) otherTotal += parseFloat(svc.sale_price) || 0; });

    vsTotalAmount = total + otherTotal;

    $('#vsSelectedCount').text(vsSelectedIds.size + ' application' + (vsSelectedIds.size !== 1 ? 's' : '') + ' selected');
    $('#vsGrandTotal').text(Number(total).toLocaleString());
    $('#vsOtherSelectedCount').text(vsOtherSelectedIds.size + ' other service' + (vsOtherSelectedIds.size !== 1 ? 's' : '') + ' selected');
    $('#vsOtherSubtotal').text(Number(otherTotal).toLocaleString());
    $('#vsTotalDisplay').val(Number(vsTotalAmount).toLocaleString());

    const $wrap = $('#vsHiddenInputs').empty();
    vsSelectedIds.forEach(id => $wrap.append($('<input>').attr({ type: 'hidden', name: 'visa_process_ids[]', value: id })));

    const $otherWrap = $('#vsOtherHiddenInputs').empty();
    vsOtherSelectedIds.forEach(id => $otherWrap.append($('<input>').attr({ type: 'hidden', name: 'other_service_ids[]', value: id })));

    vsCalcDue();
}

function vsCalcDue() {
    const paid = parseFloat($('#vsPaidInput').val()) || 0;
    $('#vsDueDisplay').val(Number(Math.max(0, vsTotalAmount - paid)).toLocaleString());
}

function vsToggleUnpaid() {
    vsShowUnpaid = !vsShowUnpaid;
    $('#vsShowUnpaidBtn')
        .toggleClass('bg-teal-100', vsShowUnpaid)
        .toggleClass('text-teal-700', vsShowUnpaid)
        .toggleClass('border-teal-300', vsShowUnpaid);
    vsLoadApplications();
}

// ─── Payment modal ────────────────────────────────────────────────────────────
function vsOpenPayment(id, invoice, due, total) {
    $('#pmtInvoiceLabel').text(invoice);
    $('#pmtDueDisplay').text(Number(due).toLocaleString());
    $('#pmtAmountInput').attr('max', due).val('');
    $('#pmtPaymentMethod').val('cash');
    $('#pmtBankId').val('');
    toggleVisaSaleBankField('pmt');
    $('#pmtForm').attr('action', VS_PMT_BASE + '/' + id + '/payment');
    $('#pmtModal').removeClass('hidden');
}
function vsClosePmt() {
    $('#pmtModal').addClass('hidden');
}

// ─── Edit sale modal ──────────────────────────────────────────────────────────
let vseCurrentId    = null;
let vseShowUnpaid   = false;
let vseAppData      = [];
let vseSelectedIds  = new Set();
let vseOtherAppData     = [];
let vseOtherSelectedIds = new Set();
let vseTotalAmount  = 0;

function vseOpen(id) {
    vseCurrentId = id;
    vseSelectedIds.clear();
    vseAppData = [];
    vseOtherSelectedIds.clear();
    vseOtherAppData = [];

    $('#vseModal').removeClass('hidden');
    $('#vseForm .error-message').addClass('hidden');
    $('#vseClientId').val('');
    $('#vseSelectClient').val('').prop('disabled', false).trigger('change.select2');
    $('#vseSelectAgent').val('').prop('disabled', false).trigger('change.select2');
    $('#vseModalSubtitle').text('Loading…');
    $('#vseAppsBody').html('<tr><td colspan="6" class="text-center py-4 text-slate-400 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i> Loading…</td></tr>');

    $.ajax({
        url: VS_SHOW_BASE + '/' + id + '/edit',
        method: 'GET',
        dataType: 'json',
        success: function(d) {
            $('#vseInvoiceNo').val(d.invoice_number);
            $('#vseClientId').val(d.client_id || '');
            const isCustomer = !!vsClientsData[d.client_id];
            $('#vseSelectClient').val(isCustomer ? d.client_id : '').prop('disabled', !!d.client_id && !isCustomer).trigger('change.select2');
            $('#vseSelectAgent').val(!isCustomer ? (d.client_id || '') : '').prop('disabled', !!d.client_id && isCustomer).trigger('change.select2');
            $('#vseClientPhone').val(d.client_phone || '');
            $('#vseClientEmail').val(d.client_email || '');
            $('#vseSendVia').val(d.send_via);
            $('#vseBundleLabel').val(d.bundle_label);
            $('#vseVoucherDate').val(d.voucher_date);
            $('#vseReceivableDate').val(d.receivable_date);
            $('#vseIssuedBy').val(d.issued_by);
            $('#vsePaidInput').val(d.paid_amount);
            $('#vsePaymentMethod').val(d.payment_method || 'cash');
            $('#vseBankId').val(d.bank_id || '');
            toggleVisaSaleBankField('vse');
            $('#vseNotes').val(d.notes);
            $('#vseSubmit').data('action', VS_SHOW_BASE + '/' + id);

            d.visa_process_ids.forEach(pid => vseSelectedIds.add(pid));
            (d.other_service_ids || []).forEach(sid => vseOtherSelectedIds.add(sid));

            $('#vseModalSubtitle').text('Editing ' + d.invoice_number);
            vseLoadApps();
            vseLoadOtherServices();
        },
        error: function() {
            $('#vseAppsBody').html('<tr><td colspan="6" class="text-center py-4 text-red-400 text-xs">Failed to load sale data.</td></tr>');
        }
    });
}

function vseClose() {
    $('#vseModal').addClass('hidden');
    vseCurrentId = null;
    vseSelectedIds.clear();
    vseAppData = [];
    vseOtherSelectedIds.clear();
    vseOtherAppData = [];
    vseShowUnpaid = false;
    $('#vseHiddenInputs').empty();
    $('#vseOtherHiddenInputs').empty();
    $('#vseCountryFilter').find('option:not(:first)').remove();
    vseUpdateTotals();
}

function vseLoadApps() {
    const countryId = $('#vseCountryFilter').val();
    const params = {};
    if (countryId) params.country_id = countryId;
    if (vseShowUnpaid) params.show_unpaid = 1;
    if (vseCurrentId) params.edit_sale_id = vseCurrentId;

    const $tbody = $('#vseAppsBody');
    $tbody.html('<tr><td colspan="6" class="text-center py-4 text-slate-400 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i> Loading…</td></tr>');

    $.ajax({
        url: VS_APPS_URL,
        method: 'GET',
        data: params,
        success: function(data) {
            vseAppData = data.applications;
            if (!countryId && data.countries.length) {
                const $sel = $('#vseCountryFilter');
                if ($sel.find('option').length <= 1) {
                    data.countries.forEach(c => $sel.append(new Option(c.name, c.id)));
                }
            }
            vseRenderApps();
            vseUpdateTotals();
        },
        error: function() {
            $tbody.html('<tr><td colspan="6" class="text-center py-4 text-red-400 text-xs">Failed to load applications.</td></tr>');
        }
    });
}

function vseRenderApps() {
    const $tbody = $('#vseAppsBody');
    if (!vseAppData.length) {
        $tbody.html('<tr><td colspan="6" class="text-center py-6 text-slate-400 text-xs">No available applications.</td></tr>');
        return;
    }
    $tbody.html(vseAppData.map(app => {
        const checked   = vseSelectedIds.has(app.id) ? 'checked' : '';
        const selClass  = vseSelectedIds.has(app.id) ? 'selected' : '';
        const badge = app.status === 'paid'
            ? '<span class="vs-badge badge-paid">Paid</span>'
            : app.status === 'partial'
            ? '<span class="vs-badge badge-partial">Partial</span>'
            : '<span class="vs-badge badge-pending">Pending</span>';
        return `<tr class="${selClass}" id="vse-row-${app.id}" onclick="vseToggleApp(${app.id})">
            <td class="px-2.5 py-2"><input type="checkbox" ${checked} onclick="event.stopPropagation(); vseToggleApp(${app.id})"></td>
            <td class="px-2.5 py-2 font-mono font-bold text-violet-700">${app.app_id}</td>
            <td class="px-2.5 py-2">${app.applicant}</td>
            <td class="px-2.5 py-2">${app.country}<br><span class="text-slate-400" style="font-size:10px">${app.visa_type}</span></td>
            <td class="px-2.5 py-2 font-semibold text-right">${Number(app.sale_price).toLocaleString()}</td>
            <td class="px-2.5 py-2">${badge}</td>
        </tr>`;
    }).join(''));
}

function vseToggleApp(id) {
    if (vseSelectedIds.has(id)) vseSelectedIds.delete(id);
    else vseSelectedIds.add(id);
    vseRenderApps();
    vseUpdateTotals();
}

function vseToggleAll(chk) {
    vseAppData.forEach(app => { if (chk.checked) vseSelectedIds.add(app.id); else vseSelectedIds.delete(app.id); });
    vseRenderApps();
    vseUpdateTotals();
}

function vseLoadOtherServices() {
    const $tbody = $('#vseOtherBody');
    $tbody.html('<tr><td colspan="6" class="text-center py-4 text-slate-400 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i> Loading…</td></tr>');

    $.ajax({
        url: VS_OTHER_SERVICES_URL,
        method: 'GET',
        data: vseCurrentId ? { edit_sale_id: vseCurrentId } : {},
        success: function(data) {
            vseOtherAppData = data.services;
            vseRenderOtherServices();
            vseUpdateTotals();
        },
        error: function() {
            $tbody.html('<tr><td colspan="6" class="text-center py-4 text-red-400 text-xs">Failed to load other services.</td></tr>');
        }
    });
}

function vseRenderOtherServices() {
    const $tbody = $('#vseOtherBody');
    if (!vseOtherAppData.length) {
        $tbody.html('<tr><td colspan="6" class="text-center py-6 text-slate-400 text-xs">No billable other services available.</td></tr>');
        return;
    }
    $tbody.html(vseOtherAppData.map(svc => {
        const checked  = vseOtherSelectedIds.has(svc.id) ? 'checked' : '';
        const selClass = vseOtherSelectedIds.has(svc.id) ? 'selected' : '';
        return `<tr class="${selClass}" id="vse-other-row-${svc.id}" onclick="vseToggleOtherService(${svc.id})">
            <td class="px-2.5 py-2"><input type="checkbox" ${checked} onclick="event.stopPropagation(); vseToggleOtherService(${svc.id})"></td>
            <td class="px-2.5 py-2 font-mono font-bold text-violet-700">${svc.service_code}</td>
            <td class="px-2.5 py-2">${svc.applicant}</td>
            <td class="px-2.5 py-2"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:${svc.service_bg};color:${svc.service_color};">${svc.service_type}</span></td>
            <td class="px-2.5 py-2 font-semibold text-right">${Number(svc.sale_price).toLocaleString()}</td>
            <td class="px-2.5 py-2">${svc.status}</td>
        </tr>`;
    }).join(''));
}

function vseToggleOtherService(id) {
    if (vseOtherSelectedIds.has(id)) vseOtherSelectedIds.delete(id);
    else vseOtherSelectedIds.add(id);
    vseRenderOtherServices();
    vseUpdateTotals();
}

function vseToggleAllOther(chk) {
    vseOtherAppData.forEach(svc => {
        if (chk.checked) vseOtherSelectedIds.add(svc.id);
        else vseOtherSelectedIds.delete(svc.id);
    });
    vseRenderOtherServices();
    vseUpdateTotals();
}

function vseUpdateTotals() {
    let total = 0;
    vseAppData.forEach(app => { if (vseSelectedIds.has(app.id)) total += parseFloat(app.sale_price) || 0; });

    let otherTotal = 0;
    vseOtherAppData.forEach(svc => { if (vseOtherSelectedIds.has(svc.id)) otherTotal += parseFloat(svc.sale_price) || 0; });

    vseTotalAmount = total + otherTotal;

    $('#vseSelectedCount').text(vseSelectedIds.size + ' application' + (vseSelectedIds.size !== 1 ? 's' : '') + ' selected');
    $('#vseGrandTotal').text(Number(total).toLocaleString());
    $('#vseOtherSelectedCount').text(vseOtherSelectedIds.size + ' other service' + (vseOtherSelectedIds.size !== 1 ? 's' : '') + ' selected');
    $('#vseOtherSubtotal').text(Number(otherTotal).toLocaleString());
    $('#vseTotalDisplay').val(Number(vseTotalAmount).toLocaleString());

    const $wrap = $('#vseHiddenInputs').empty();
    vseSelectedIds.forEach(id => $wrap.append($('<input>').attr({ type: 'hidden', name: 'visa_process_ids[]', value: id })));

    const $otherWrap = $('#vseOtherHiddenInputs').empty();
    vseOtherSelectedIds.forEach(id => $otherWrap.append($('<input>').attr({ type: 'hidden', name: 'other_service_ids[]', value: id })));

    vseCalcDue();
}

function vseCalcDue() {
    const paid = parseFloat($('#vsePaidInput').val()) || 0;
    $('#vseDueDisplay').val(Number(Math.max(0, vseTotalAmount - paid)).toLocaleString());
}

function vseToggleUnpaid() {
    vseShowUnpaid = !vseShowUnpaid;
    $('#vseShowUnpaidBtn')
        .toggleClass('bg-violet-100', vseShowUnpaid)
        .toggleClass('text-violet-700', vseShowUnpaid)
        .toggleClass('border-violet-300', vseShowUnpaid);
    vseLoadApps();
}

// ─── Detail view modal ────────────────────────────────────────────────────────
const STATUS_COLORS = {
    paid:    { bg: '#dcfce7', color: '#16a34a' },
    partial: { bg: '#fef9c3', color: '#a16207' },
    pending: { bg: '#fee2e2', color: '#dc2626' },
};
const APP_STATUS_COLORS = {
    approved:    { bg: '#dcfce7', color: '#16a34a' },
    pending:     { bg: '#fef9c3', color: '#a16207' },
    rejected:    { bg: '#fee2e2', color: '#dc2626' },
    delivered:   { bg: '#ede9fe', color: '#7c3aed' },
    in_embassy:  { bg: '#dbeafe', color: '#1d4ed8' },
    received:    { bg: '#ccfbf1', color: '#0f766e' },
    in_progress: { bg: '#dbeafe', color: '#1d4ed8' },
    done:        { bg: '#dcfce7', color: '#16a34a' },
    overdue:     { bg: '#fee2e2', color: '#dc2626' },
    cancelled:   { bg: '#f1f5f9', color: '#64748b' },
};

function vsShowDetail(id) {
    $('#vsdTitle').text('Loading…');
    $('#vsdSubtitle').text('');
    $('#vsdBody').html('<div class="text-center py-12 text-slate-400"><i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading...</div>');
    $('#vsDetailModal').removeClass('hidden');

    $.ajax({
        url: VS_SHOW_BASE + '/' + id,
        method: 'GET',
        dataType: 'json',
        success: function(d) {
        $('#vsdTitle').text('Voucher ' + d.invoice_number + ' — ' + d.client_name);
        $('#vsdSubtitle').text(d.bundle_label || 'Visa Sale Invoice');

        const stClr = STATUS_COLORS[d.status] || STATUS_COLORS.pending;

        const itemRows = d.items.map((item, i) => {
            const asClr = APP_STATUS_COLORS[item.status] || APP_STATUS_COLORS.pending;
            return `<tr>
                <td style="text-align:center;font-weight:700;color:#6b7280;">${i+1}</td>
                <td>
                    <span style="font-family:monospace;font-weight:700;color:#1d4ed8;">${item.app_id || '—'}</span><br>
                    <span style="font-size:13px;font-weight:600;color:#1f2937;">${item.applicant}</span>
                    ${item.passport_no ? '<div style="font-size:10px;color:#9ca3af;">PP: ' + item.passport_no + '</div>' : ''}
                </td>
                <td>${item.country}</td>
                <td>${item.visa_type}</td>
                <td style="text-align:right;font-family:monospace;font-weight:600;">৳ ${Number(item.sale_price).toLocaleString()}</td>
                <td style="text-align:center;">
                    <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:${asClr.bg};color:${asClr.color};">
                        ${item.status.charAt(0).toUpperCase() + item.status.slice(1)}
                    </span>
                </td>
            </tr>`;
        }).join('');

        $('#vsdBody').html(`
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px;">
                <div style="background:#f8fafc;border-radius:10px;padding:10px 14px;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;">Client</div>
                    <div style="font-size:14px;font-weight:700;color:#1f2937;margin-top:2px;">${d.client_name}</div>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:10px 14px;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;">Phone</div>
                    <div style="font-size:14px;font-weight:700;color:#1f2937;margin-top:2px;">${d.client_phone}</div>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:10px 14px;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;">Date</div>
                    <div style="font-size:14px;font-weight:700;color:#1f2937;margin-top:2px;">${d.voucher_date}</div>
                </div>
            </div>

            <div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:18px;">
                <table class="vsd-table" style="width:100%;border-collapse:collapse;">
                    <thead><tr>
                        <th style="width:36px">#</th>
                        <th>App ID / Applicant</th>
                        <th>Country</th>
                        <th>Visa Type</th>
                        <th style="text-align:right">Sale Price</th>
                        <th style="text-align:center">Status</th>
                    </tr></thead>
                    <tbody>${itemRows}</tbody>
                </table>
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px;">
                <div style="border-radius:12px;padding:14px 16px;text-align:center;background:#eff6ff;">
                    <div style="font-size:22px;font-weight:800;color:#1d4ed8;">${d.items.length}</div>
                    <div style="font-size:10px;font-weight:700;color:#60a5fa;text-transform:uppercase;">Applications</div>
                </div>
                <div style="border-radius:12px;padding:14px 16px;text-align:center;background:#f0fdf4;">
                    <div style="font-size:16px;font-weight:800;color:#16a34a;">৳ ${Number(d.total_amount).toLocaleString()}</div>
                    <div style="font-size:10px;font-weight:700;color:#4ade80;text-transform:uppercase;">Grand Total</div>
                </div>
                <div style="border-radius:12px;padding:14px 16px;text-align:center;background:#ecfdf5;">
                    <div style="font-size:16px;font-weight:800;color:#059669;">৳ ${Number(d.paid_amount).toLocaleString()}</div>
                    <div style="font-size:10px;font-weight:700;color:#34d399;text-transform:uppercase;">Paid</div>
                </div>
                <div style="border-radius:12px;padding:14px 16px;text-align:center;background:${d.due_amount > 0 ? '#fff1f2' : '#f0fdf4'};">
                    <div style="font-size:16px;font-weight:800;color:${d.due_amount > 0 ? '#dc2626' : '#16a34a'};">৳ ${Number(d.due_amount).toLocaleString()}</div>
                    <div style="font-size:10px;font-weight:700;color:${d.due_amount > 0 ? '#f87171' : '#4ade80'};text-transform:uppercase;">Due</div>
                </div>
            </div>

            <div style="text-align:center;margin-bottom:16px;">
                <span style="display:inline-block;padding:5px 18px;border-radius:20px;font-weight:700;font-size:13px;background:${stClr.bg};color:${stClr.color};">
                    Payment Status: ${d.status.charAt(0).toUpperCase() + d.status.slice(1)}
                </span>
            </div>

            ${d.notes ? `<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;font-size:12px;color:#92400e;margin-bottom:16px;"><strong>Note:</strong> ${d.notes}</div>` : ''}

            ${cmtHtml('visa_sale', d.id)}

            <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;margin-top:16px;">
                <button onclick="vsCloseDetail()"
                    class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50">
                    Close
                </button>
                <button type="button" onclick="vsEmailVoucher(${d.id}, '${(d.client_email || '').replace(/'/g, "\\'")}')"
                    ${d.client_email ? '' : 'disabled title="No client email on file"'}
                    class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-semibold text-sm hover:bg-emerald-700 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-paper-plane text-xs"></i> Email Voucher
                </button>
                <a href="${VS_SHOW_BASE}/${d.id}/voucher" target="_blank"
                    class="px-5 py-2 rounded-xl bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700 flex items-center gap-2" style="text-decoration:none;">
                    <i class="fas fa-print text-xs"></i> Print Voucher
                </a>
            </div>
        `);
        loadComments('visa_sale', d.id);
        },
        error: function() {
            $('#vsdBody').html('<div class="text-center py-12 text-red-400"><i class="fas fa-exclamation-circle text-2xl mb-2 block"></i>Failed to load details.</div>');
        }
    });
}

function vsCloseDetail() {
    $('#vsDetailModal').addClass('hidden');
}

function vsEmailVoucher(id, email) {
    if (!email) {
        Swal.fire({ icon: 'warning', title: 'No Email', text: 'This client has no email address on file.' });
        return;
    }
    Swal.fire({
        title: 'Send Voucher?',
        text: 'Email the voucher PDF to ' + email + '?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, send it',
    }).then((result) => {
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'Sending…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        $.ajax({
            url: VS_SHOW_BASE + '/' + id + '/email',
            method: 'POST',
            data: { email: email },
            success: function(response) {
                Swal.fire({ icon: response.success ? 'success' : 'error', title: response.success ? 'Sent!' : 'Failed', text: response.message });
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to send the voucher email.' });
            }
        });
    });
}

// Overlay click to close
$('#vsCreateModal').on('click', function(e) { if (e.target === this) vsCloseCreate(); });
$('#pmtModal').on('click', function(e) { if (e.target === this) vsClosePmt(); });
$('#vsDetailModal').on('click', function(e) { if (e.target === this) vsCloseDetail(); });
$('#vseModal').on('click', function(e) { if (e.target === this) vseClose(); });
$('#vsDeleteModal').on('click', function(e) { if (e.target === this) $(this).addClass('hidden'); });

// ─── Form validation ──────────────────────────────────────────────────────────
function validateCreateSaleForm() {
    let isValid = true;
    $('#vsCreateForm .error-message').addClass('hidden');
    if (!$('#create_client_id').val()) { $('#create_client_id_msg').removeClass('hidden'); isValid = false; }
    if (vsSelectedIds.size === 0 && vsOtherSelectedIds.size === 0) { $('#create_apps_msg').removeClass('hidden'); isValid = false; }
    if (!$('#create_voucher_date').val()) { $('#create_voucher_date_msg').removeClass('hidden'); isValid = false; }
    return isValid;
}

function validateEditSaleForm() {
    let isValid = true;
    $('#vseForm .error-message').addClass('hidden');
    if (!$('#vseClientId').val()) { $('#edit_client_id_msg').removeClass('hidden'); isValid = false; }
    if (vseSelectedIds.size === 0 && vseOtherSelectedIds.size === 0) { $('#edit_apps_msg').removeClass('hidden'); isValid = false; }
    if (!$('#vseVoucherDate').val()) { $('#edit_voucher_date_msg').removeClass('hidden'); isValid = false; }
    return isValid;
}

// ─── AJAX CRUD ────────────────────────────────────────────────────────────────
function vsConfirmDelete(id, invoiceLabel) {
    $('#vsDeleteName').text(invoiceLabel);
    $('#vsConfirmDeleteBtn').data('item-id', id).data('action', VS_SHOW_BASE + '/' + id);
    $('#vsDeleteModal').removeClass('hidden');
}

$(document).ready(function() {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    function ajaxErrorMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;
        if (errors) return Object.values(errors).flat()[0];
        return xhr.responseJSON?.message || fallback;
    }

    $('.vs-create-btn').on('click', function() { vsOpenCreate(); });

    $(document).on('click', '.vs-view-btn', function() { vsShowDetail($(this).data('id')); });
    $(document).on('click', '.vs-edit-btn', function() { vseOpen($(this).data('id')); });
    $(document).on('click', '.vs-pay-btn', function() {
        vsOpenPayment($(this).data('id'), $(this).data('invoice'), $(this).data('due'), $(this).data('total'));
    });

    // Create submit
    $('#vsCreateSubmit').click(function(e) {
        e.preventDefault();
        if (!validateCreateSaleForm()) return;
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving…');
        $.ajax({
            url: $('#vsCreateForm').attr('action'),
            method: 'POST',
            data: new FormData($('#vsCreateForm')[0]),
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({ icon: 'success', title: 'Done', text: response.message || 'Sale created!', timer: 1500, showConfirmButton: false });
                    vsCloseCreate();
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to create sale.' });
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: ajaxErrorMessage(xhr, 'Failed to create sale.') });
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save text-xs"></i> Save & Generate Voucher');
            }
        });
    });

    // Edit submit
    $('#vseSubmit').click(function(e) {
        e.preventDefault();
        if (!validateEditSaleForm()) return;
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Updating…');
        $.ajax({
            url: $(this).data('action'),
            method: 'POST',
            data: new FormData($('#vseForm')[0]),
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({ icon: 'success', title: 'Done', text: response.message || 'Sale updated!', timer: 1500, showConfirmButton: false });
                    vseClose();
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to update sale.' });
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: ajaxErrorMessage(xhr, 'Failed to update sale.') });
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save text-xs"></i> Update Sale');
            }
        });
    });

    // Delete confirm
    $('#vsConfirmDeleteBtn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).text('Deleting…');
        $.ajax({
            url: btn.data('action'),
            method: 'DELETE',
            data: { item_id: btn.data('item-id') },
            success: function(response) {
                if (response.success) {
                    Swal.fire({ icon: 'success', title: 'Done', text: response.message || 'Deleted!', timer: 1200, showConfirmButton: false });
                    $('#vsDeleteModal').addClass('hidden');
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to delete.' });
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: ajaxErrorMessage(xhr, 'Failed to delete.') });
            },
            complete: function() {
                btn.prop('disabled', false).text('Delete');
            }
        });
    });

    $(document).on('click', '.modal-close-vs-delete', function() {
        $('#vsDeleteModal').addClass('hidden');
    });
});
</script>
@include('components.comment-panel')
@endsection
