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
    #vsCreateModal .vs-create-shell { max-height:calc(100vh - 2rem); }
    #vsCreateModal .vs-create-body { scrollbar-width:thin; scrollbar-color:#94a3b8 #f1f5f9; }
    #vsCreateModal .vs-create-section { margin-top:1.25rem; padding-top:1.25rem; border-top:1px solid #e2e8f0; }
    #vsCreateModal .vs-create-section:first-of-type { margin-top:0; padding-top:0; border-top:0; }
    #vsCreateModal input:not([type="checkbox"]), #vsCreateModal select, #vsCreateModal textarea { min-height:42px; border-radius:10px; transition:border-color .15s,box-shadow .15s,background .15s; }
    #vsCreateModal input:not([type="checkbox"]):focus, #vsCreateModal select:focus, #vsCreateModal textarea:focus { border-color:#14b8a6 !important; box-shadow:0 0 0 3px rgba(20,184,166,.12); }
    #vsCreateModal label { line-height:1.25rem; }
    #vsCreateModal .select2-container .select2-selection--single { height:42px;border-radius:10px;border-color:#cbd5e1;display:flex;align-items:center; }
    #vsCreateModal .select2-container .select2-selection--single .select2-selection__rendered { padding-left:12px;line-height:40px; }
    #vsCreateModal .select2-container .select2-selection--single .select2-selection__arrow { height:40px; }
    #vseModal .vs-edit-shell { max-height:calc(100vh - 2rem); }
    #vseModal .vs-edit-body { scrollbar-width:thin; scrollbar-color:#94a3b8 #f1f5f9; }
    #vseModal .vs-edit-section { margin-top:1.25rem; padding-top:1.25rem; border-top:1px solid #e2e8f0; }
    #vseModal .vs-edit-section:first-of-type { margin-top:0; padding-top:0; border-top:0; }
    #vseModal input:not([type="checkbox"]), #vseModal select, #vseModal textarea { min-height:42px; border-radius:10px; transition:border-color .15s,box-shadow .15s,background .15s; }
    #vseModal input:not([type="checkbox"]):focus, #vseModal select:focus, #vseModal textarea:focus { border-color:#8b5cf6 !important; box-shadow:0 0 0 3px rgba(139,92,246,.12); }
    #vseModal label { line-height:1.25rem; }
    #vseModal .select2-container .select2-selection--single { height:42px;border-radius:10px;border-color:#cbd5e1;display:flex;align-items:center; }
    #vseModal .select2-container .select2-selection--single .select2-selection__rendered { padding-left:12px;line-height:40px; }
    #vseModal .select2-container .select2-selection--single .select2-selection__arrow { height:40px; }
</style>
@endsection

@section('main-content')
@include('layout.visa-processing-tabs')
@php
    use Illuminate\Support\Str;
    $role = Str::slug(auth()->user()->getRoleNames()->first());
    $taka = '৳';
    // Guarded: a top-level function in a view fatals ("Cannot redeclare") the
    // moment the compiled file is evaluated twice in one process — which the
    // view engine does when it retries after a lost compile race.
    if (! function_exists('fmtMoney')) {
        function fmtMoney($n) { return number_format($n, 0); }
    }
    if (! function_exists('fmtL')) {
        function fmtL($n) { if($n >= 100000) return number_format($n/100000, 1).'L'; if($n >= 1000) return number_format($n/1000, 1).'K'; return number_format($n, 0); }
    }
@endphp

@if(session('success'))
<div class="mb-4 flex gap-3 items-center rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    <i class="fas fa-check-circle text-emerald-500"></i> {{ session('success') }}
</div>
@endif

@include('visa-processing.partials.page-top', [
    'vxTiles' => [
        ['Sales (MTD)', $stats['total_sales_mtd'], $sales->total() . ' applications bundled', 'fa-clipboard-list', 'blue', null],
        ['Revenue', $taka . ' ' . fmtL($stats['revenue_mtd']), 'This month', 'fa-money-bill-wave', 'green', null],
        ['Due / Pending', $taka . ' ' . fmtL($stats['total_due']), 'Across all invoices', 'fa-hourglass-half', 'amber', null],
        ['Commission', $taka . ' ' . fmtL(max(0, $stats['commission'])), 'Net profit', 'fa-chart-line', 'violet', null],
    ],
    'vxIcon' => 'fa-file-invoice-dollar', 'vxTitle' => 'Manage Visa Sales',
    'vxSub' => 'Client-wise consolidated vouchers — bundle multiple applications into one invoice',
    'vxAction' => '<button type="button" class="vs-create-btn vx-btn"><i class="fas fa-plus"></i> Create Sale</button>',
])

{{-- Table card with integrated filter --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    {{-- Integrated filter --}}
    <form method="GET" action="{{ route('role.visa-sales.index', ['role' => $role]) }}">
        <div class="vx-filter flex flex-wrap items-center gap-2 bg-slate-50 px-4 py-3">
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
                    <th class="px-2 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500" style="width:1%;white-space:nowrap">Action</th>
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
                $saleVendors  = $sale->items->map(fn($i) => optional($i->visaProcess)->vendor)->filter()->unique('id')->values();
                $partyLedgerUrl = route('role.party-statement.index', ['role' => $role]);
                $clientIsAgent = $sale->client?->roles?->contains('name', 'agent') ?? false;
                $clientPartyCode = $sale->client
                    ? ($clientIsAgent ? 'EP-AG-' : 'EP-CU-').str_pad((string) $sale->client->id, 3, '0', STR_PAD_LEFT)
                    : null;
                $pBadgeClass  = ['paid' => 'bg-emerald-100 text-emerald-700', 'partial' => 'bg-amber-100 text-amber-700', 'pending' => 'bg-red-100 text-red-700'][$sale->status] ?? 'bg-slate-100 text-slate-600';
            @endphp
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    <span class="font-bold text-teal-700 text-sm">{{ $sale->invoice_number }}</span>
                </td>
                <td class="px-4 py-3">
                    @if($sale->client)
                        <div class="text-sm font-semibold text-slate-800">{{ $sale->client->name }}</div>
                        <div class="text-xs text-slate-400">{{ $sale->client->phone }}</div>
                        <div class="mt-0.5 text-[10px] text-slate-400">
                            {{ $clientIsAgent ? 'Agent' : 'Customer' }} ID:
                            <a href="{{ $partyLedgerUrl }}?party_id={{ $sale->client->id }}"
                                class="font-semibold text-blue-700 hover:underline"
                                title="Open {{ $sale->client->name }}'s ledger">{{ $clientPartyCode }}</a>
                        </div>
                    @else
                        <div class="text-sm font-semibold text-slate-800">—</div>
                    @endif
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
                    @if($saleVendors->isNotEmpty())
                        <div class="mt-1 text-[11px] text-slate-500">
                            <i class="fas fa-building mr-1 text-slate-400"></i>Vendor:
                            @foreach($saleVendors as $vendor)
                                <span class="font-semibold text-slate-700">{{ $vendor->name }}</span>{{ !$loop->last ? ', ' : '' }}
                            @endforeach
                        </div>
                        <div class="text-[10px] text-slate-400">
                            @foreach($saleVendors as $vendor)
                                Vendor ID:
                                <a href="{{ $partyLedgerUrl }}?party_id={{ $vendor->id }}"
                                    class="font-semibold text-violet-700 hover:underline"
                                    title="Open {{ $vendor->name }}'s ledger">EP-VN-{{ str_pad((string) $vendor->id, 3, '0', STR_PAD_LEFT) }}</a>{{ !$loop->last ? ' · ' : '' }}
                            @endforeach
                        </div>
                    @endif
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
                <td class="px-2 py-3">
                    <div style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
                        <button type="button" class="vs-view-btn flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100"
                            data-id="{{ $sale->id }}" title="View Details" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                            <i class="fas fa-eye text-xs" style="font-size:11px;margin:0"></i>
                        </button>
                        <button type="button" class="vs-edit-btn flex h-8 w-8 items-center justify-center rounded-lg border border-violet-200 bg-violet-50 text-violet-600 hover:bg-violet-100"
                            data-id="{{ $sale->id }}" title="Edit Sale" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                            <i class="fas fa-pencil-alt text-xs" style="font-size:11px;margin:0"></i>
                        </button>
                        @if($sale->status !== 'paid')
                        <button type="button" class="vs-pay-btn flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100"
                            data-id="{{ $sale->id }}"
                            data-invoice="{{ $sale->invoice_number }}"
                            data-due="{{ $sale->due_amount }}"
                            data-total="{{ $sale->total_amount }}"
                            title="Make Payment" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                            <i class="fas fa-plus text-xs" style="font-size:11px;margin:0"></i>
                        </button>
                        @else
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-50 text-slate-300" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                            <i class="fas fa-check text-xs" style="font-size:11px;margin:0"></i>
                        </span>
                        @endif
                        <a href="{{ route('role.visa-sales.voucher', ['role' => $role, 'visaSale' => $sale->id]) }}"
                            target="_blank"
                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-teal-200 bg-teal-50 text-teal-600 hover:bg-teal-100" title="Download Voucher" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                            <i class="fas fa-download text-xs" style="font-size:11px;margin:0"></i>
                        </a>
                        <button type="button" onclick="vsConfirmDelete('{{ $sale->id }}', '{{ addslashes($sale->invoice_number) }}')"
                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-500 hover:bg-red-100"
                            title="Delete" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                            <i class="fas fa-trash text-xs" style="font-size:11px;margin:0"></i>
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
<div id="pmtModal" class="fixed inset-0 z-[10000] bg-black/50 hidden [&:not(.hidden)]:flex items-center justify-center p-4">
    <div class="w-full max-w-2xl bg-white rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center gap-3 mb-5">
            <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                <i class="fas fa-money-bill-transfer"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900">Receive Visa Payment</h3>
                <p id="pmtInvoiceLabel" class="text-xs text-slate-400"></p>
            </div>
        </div>
        <div class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 mb-4 flex justify-between text-sm">
            <span class="text-slate-600 font-medium">Current Due</span>
            <span class="font-bold text-red-600">{{ $taka }} <span id="pmtDueDisplay">0</span></span>
        </div>
        <form id="pmtForm" method="POST">
            @csrf @method('PATCH')
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-slate-500"><i class="fas fa-sack-dollar mr-1"></i> Payment Details</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Amount <span class="text-red-500">*</span></label><input type="number" name="payment_amount" id="pmtAmountInput" min="0.01" step="0.01" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Payment Date <span class="text-red-500">*</span></label><input type="date" name="payment_date" id="pmtPaymentDate" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Custom Time <span class="text-red-500">*</span></label><input type="hidden" name="transaction_time" id="pmtTransactionTime"><div class="flex items-center gap-1"><select id="pmtHour" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-1 py-2 text-sm">@for($h=1;$h<=12;$h++)<option value="{{ $h }}">{{ str_pad($h,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><b>:</b><select id="pmtMinute" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-1 py-2 text-sm">@for($m=0;$m<60;$m++)<option value="{{ $m }}">{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><select id="pmtPeriod" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-1 py-2 text-sm"><option>AM</option><option>PM</option></select></div></div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-slate-500"><i class="fas fa-building-columns mr-1"></i> Money Route</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Received Via <span class="text-red-500">*</span></label><select name="payment_method" id="pmtPaymentMethod" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_banking">Mobile Banking</option><option value="cheque">Cheque</option><option value="card">Card</option><option value="other">Other</option></select></div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Company Account (money received into) <span class="text-red-500">*</span></label><select name="bank_id" id="pmtBankId" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">— Select account —</option>@include('partials.bank-options', ['banks' => $banks, 'selected' => null])</select></div>
                    <div id="pmtPayerFields" class="hidden sm:col-span-2 rounded-lg border border-blue-200 bg-blue-50/60 p-3">
                        <p id="pmtPayerTitle" class="mb-2 text-[11px] font-bold uppercase tracking-wide text-blue-700"></p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><label id="pmtPayerBankLabel" class="block text-xs font-semibold text-slate-600 mb-1"></label><input type="text" name="payer_bank" id="pmtPayerBank" maxlength="150" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></div>
                            <div><label id="pmtPayerReferenceLabel" class="block text-xs font-semibold text-slate-600 mb-1"></label><input type="text" name="payer_reference" id="pmtPayerReference" maxlength="100" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></div>
                        </div>
                    </div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Reference No. / TRNX ID</label><input type="text" name="reference" id="pmtReference" maxlength="191" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Note (optional)</label><input type="text" name="notes" maxlength="500" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></div>
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-5">
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
    $('#create_payment_instruction_bank_id').val('');
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
const visaPaymentAccountTypes = {
    cash: ['cash'],
    bank_transfer: ['bank'],
    cheque: ['bank'],
    card: ['bank'],
    mobile_banking: ['mobile_banking', 'digital_wallet'],
};

const visaPaymentPayerCopy = {
    bank_transfer: {
        title: '<i class="fas fa-building-columns mr-1"></i> Customer / Agent paid from',
        bankLabel: 'Bank Name', bankPlaceholder: 'e.g. Dutch-Bangla Bank',
        referenceLabel: 'Account Number', referencePlaceholder: 'Customer/agent account number',
    },
    cheque: {
        title: '<i class="fas fa-money-check mr-1"></i> Cheque details',
        bankLabel: 'Bank Name (cheque drawn on)', bankPlaceholder: 'e.g. Dutch-Bangla Bank',
        referenceLabel: 'Cheque No.', referencePlaceholder: 'Cheque number',
    },
    mobile_banking: {
        title: '<i class="fas fa-mobile-screen-button mr-1"></i> Customer / Agent paid from',
        bankLabel: 'Provider / Service', bankPlaceholder: 'e.g. bKash, Nagad or Rocket',
        referenceLabel: 'Mobile Account Number', referencePlaceholder: 'Customer/agent mobile account number',
    },
};

function filterVisaPaymentAccounts() {
    const method = document.getElementById('pmtPaymentMethod')?.value;
    const accountSelect = document.getElementById('pmtBankId');
    if (!accountSelect) return;
    const wantedTypes = visaPaymentAccountTypes[method] || null;
    let firstVisible = null;
    [...accountSelect.options].forEach(function(option) {
        if (!option.value) return;
        const hidden = !!wantedTypes && !wantedTypes.includes(option.dataset.type || '');
        option.hidden = hidden;
        option.disabled = hidden;
        if (!hidden && !firstVisible) firstVisible = option;
    });
    accountSelect.querySelectorAll('optgroup').forEach(function(group) {
        const visible = [...group.querySelectorAll('option')].some(option => !option.hidden);
        group.hidden = !visible;
        group.disabled = !visible;
    });
    if (accountSelect.selectedOptions[0]?.hidden || accountSelect.selectedOptions[0]?.disabled) accountSelect.value = '';
    if (method === 'cash' && firstVisible) {
        const officeCash = [...accountSelect.options].find(option => !option.hidden && /office\s*cash/i.test(option.textContent));
        accountSelect.value = (officeCash || firstVisible).value;
    }
}

function syncVisaPaymentRouteFields() {
    const method = document.getElementById('pmtPaymentMethod')?.value;
    const copy = visaPaymentPayerCopy[method];
    const wrapper = document.getElementById('pmtPayerFields');
    const bank = document.getElementById('pmtPayerBank');
    const reference = document.getElementById('pmtPayerReference');
    wrapper?.classList.toggle('hidden', !copy);
    if (copy) {
        document.getElementById('pmtPayerTitle').innerHTML = copy.title;
        document.getElementById('pmtPayerBankLabel').innerHTML = copy.bankLabel + ' <span class="text-red-500">*</span>';
        document.getElementById('pmtPayerReferenceLabel').innerHTML = copy.referenceLabel + ' <span class="text-red-500">*</span>';
        bank.placeholder = copy.bankPlaceholder;
        reference.placeholder = copy.referencePlaceholder;
    }
    [bank, reference].forEach(function(field) {
        if (!field) return;
        field.required = !!copy;
        field.disabled = !copy;
        if (!copy) field.value = '';
    });
    filterVisaPaymentAccounts();
}

document.getElementById('pmtPaymentMethod')?.addEventListener('change', syncVisaPaymentRouteFields);

function vsOpenPayment(id, invoice, due, total) {
    $('#pmtInvoiceLabel').text(invoice);
    $('#pmtDueDisplay').text(Number(due).toLocaleString());
    $('#pmtAmountInput').attr('max', due).val(Number(due).toFixed(2).replace(/\.00$/, ''));
    $('#pmtPaymentMethod').val('cash');
    $('#pmtBankId').val('');
    $('#pmtPayerBank, #pmtPayerReference').val('');
    syncVisaPaymentRouteFields();
    const now = new Date();
    const hour24 = now.getHours();
    $('#pmtHour').val(hour24 % 12 || 12);
    $('#pmtMinute').val(now.getMinutes());
    $('#pmtPeriod').val(hour24 >= 12 ? 'PM' : 'AM');
    $('#pmtPaymentDate').val([now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-'));
    $('#pmtReference').val(invoice + '-PAY-' + now.toISOString().replace(/\D/g, '').slice(0, 14));
    $('#pmtForm').attr('action', VS_PMT_BASE + '/' + id + '/payment');
    $('#pmtModal').removeClass('hidden');
}
function vsClosePmt() {
    $('#pmtModal').addClass('hidden');
}

$('#pmtForm').on('submit', function () {
    let hour = Number($('#pmtHour').val()) || 12;
    const minute = String($('#pmtMinute').val()).padStart(2, '0');
    const period = $('#pmtPeriod').val();
    if (period === 'PM' && hour !== 12) hour += 12;
    if (period === 'AM' && hour === 12) hour = 0;
    $('#pmtTransactionTime').val(String(hour).padStart(2, '0') + ':' + minute);
});

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
            $('#vsePaymentInstructionBankId').val(d.payment_instruction_bank_id || '');
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
