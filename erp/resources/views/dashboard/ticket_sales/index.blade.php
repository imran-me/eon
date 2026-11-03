@extends('layout.app')
@section('meta-information')
    <title>Manage Ticket Sales</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--open, .select2-dropdown { z-index: 99999; }
    .swal2-container { z-index: 100000 !important; }
    span[aria-current="page"] span { background-color: #2563eb !important; color: white; border-color: #2563eb; }
    .tk-table { width: 100%; border-collapse: collapse; }
    .tk-table td { font-size: 12px; color: #374151; padding: 9px 10px; }
    .tk-table tr:hover td { background: #eff6ff; }
    .tk-table tr.selected td { background: #dbeafe; }
    #tsCreateModal .ts-create-header { background:#2563eb; background:linear-gradient(90deg,#1d4ed8 0%,#2563eb 52%,#0ea5e9 100%); }
    #tsEditModal .ts-edit-header { background:#7c3aed; background:linear-gradient(90deg,#6d28d9 0%,#7c3aed 52%,#6366f1 100%); }
    #tsCreateModal .ts-create-shell, #tsEditModal .ts-edit-shell { max-height:calc(100vh - 2rem); }
    #tsCreateModal .ts-create-body, #tsEditModal .ts-edit-body { scrollbar-width:thin; scrollbar-color:#94a3b8 #f1f5f9; }
    #tsCreateModal .ts-create-section, #tsEditModal .ts-edit-section { margin-top:1.25rem; padding-top:1.25rem; border-top:1px solid #e2e8f0; }
    #tsCreateModal form > div:first-of-type .ts-create-section,
    #tsEditModal form > div:first-of-type .ts-edit-section { margin-top:0; padding-top:0; border-top:0; }
    #tsCreateModal input:not([type="checkbox"]), #tsCreateModal select, #tsCreateModal textarea,
    #tsEditModal input:not([type="checkbox"]), #tsEditModal select, #tsEditModal textarea { min-height:42px; border-radius:10px; transition:border-color .15s,box-shadow .15s,background .15s; }
    #tsCreateModal input:not([type="checkbox"]):focus, #tsCreateModal select:focus, #tsCreateModal textarea:focus { border-color:#3b82f6 !important; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
    #tsEditModal input:not([type="checkbox"]):focus, #tsEditModal select:focus, #tsEditModal textarea:focus { border-color:#8b5cf6 !important; box-shadow:0 0 0 3px rgba(139,92,246,.12); }
    #tsCreateModal label, #tsEditModal label { line-height:1.25rem; }
    #tsCreateModal .select2-container .select2-selection--single,
    #tsEditModal .select2-container .select2-selection--single { height:42px;border-radius:10px;border-color:#cbd5e1;display:flex;align-items:center; }
    #tsCreateModal .select2-container .select2-selection--single .select2-selection__rendered,
    #tsEditModal .select2-container .select2-selection--single .select2-selection__rendered { padding-left:12px;line-height:40px; }
    #tsCreateModal .select2-container .select2-selection--single .select2-selection__arrow,
    #tsEditModal .select2-container .select2-selection--single .select2-selection__arrow { height:40px; }
</style>
@endsection
@section('main-content')
@include('layout.ticketing-tabs')

    {{-- Stat Cards --}}
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl bg-blue-50 p-4">
            <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-clipboard-list"></i></div>
            <div class="text-3xl font-black text-blue-900 leading-none mb-1">{{ $stats['total_sales_mtd'] }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Total Sales (MTD)</div>
            <div class="text-xs text-slate-500 mt-1">{{ $datas->total() }} invoices total</div>
        </div>
        <div class="rounded-xl bg-emerald-50 p-4">
            <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-money-bill-wave"></i></div>
            <div class="text-3xl font-black text-emerald-900 leading-none mb-1">৳{{ number_format($stats['revenue_mtd'], 0) }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Revenue</div>
            <div class="text-xs text-slate-500 mt-1">This month total</div>
        </div>
        <div class="rounded-xl bg-amber-50 p-4">
            <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-hourglass-half"></i></div>
            <div class="text-3xl font-black text-amber-900 leading-none mb-1">৳{{ number_format($stats['total_due'], 0) }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Due / Pending</div>
            <div class="text-xs text-slate-500 mt-1">Across all invoices</div>
        </div>
        <div class="rounded-xl bg-rose-50 p-4">
            <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-rose-400 to-rose-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-chart-line"></i></div>
            <div class="text-3xl font-black text-rose-900 leading-none mb-1">৳{{ number_format(max(0, $stats['profit_mtd']), 0) }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wide text-rose-700">Profit Earned</div>
            <div class="text-xs text-slate-500 mt-1">This month, net of cost</div>
        </div>
    </section>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 text-sm px-4 py-3 rounded-xl mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 text-red-700 text-sm px-4 py-3 rounded-xl mb-4">{{ session('error') }}</div>
    @endif

    {{-- Page header --}}
    <header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600 flex-shrink-0"><i class="fas fa-ticket-alt"></i></span>
        <div class="flex-1">
            <h1 class="text-lg font-bold text-slate-900">Manage Ticket Sales</h1>
            <p class="text-xs text-slate-500">Bundle one or more confirmed tickets into one invoice</p>
        </div>
        <button type="button" onclick="tsOpenCreate()" class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700">
            <i class="fas fa-plus mr-1"></i> Create Sale
        </button>
    </header>

    {{-- Filter + Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {{-- The Manage Banks filter bar, with this page's three selects slotted
             in. A select applies on change; the sale-date range and the invoice
             search go with Apply. --}}
        <form method="GET" action="">
            <x-ticketing-filter-bar search="invoice_no" placeholder="Search invoice no…"
                               :reset="route('role.ticket-sales.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])"
                               :total="$datas->total()">
                <select name="client_id" onchange="this.form.requestSubmit()" aria-label="Client">
                    <option value="">All Clients</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" {{ $agent->id == request('client_id') ? 'selected' : '' }}>{{ $agent->name }}</option>
                    @endforeach
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" {{ $customer->id == request('client_id') ? 'selected' : '' }}>{{ $customer->name }}</option>
                    @endforeach
                </select>
                <select name="ticket_id" onchange="this.form.requestSubmit()" aria-label="Ticket">
                    <option value="">All Tickets</option>
                    @foreach ($tickets as $ticket)
                        <option value="{{ $ticket->id }}" {{ $ticket->id == request('ticket_id') ? 'selected' : '' }}>{{ $ticket->title }}</option>
                    @endforeach
                </select>
                <select name="status" onchange="this.form.requestSubmit()" aria-label="Status">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="confirm" {{ request('status') == 'confirm' ? 'selected' : '' }}>Confirm</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </x-ticketing-filter-bar>
        </form>

        <div class="overflow-x-auto" data-tfb-swap>
            <table class="min-w-full divide-y divide-slate-200" data-cf-sort>
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Invoice #</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Client</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Tickets</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Route</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Total (৳)</th>
                        <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Paid</th>
                        <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Due</th>
                        <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500">Payment</th>
                        <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-2 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500" style="width:1%;white-space:nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($datas as $value)
                    @php
                        $pBadgeClass = ['paid' => 'bg-emerald-100 text-emerald-700', 'partial' => 'bg-amber-100 text-amber-700', 'due' => 'bg-red-100 text-red-700'][$value->payment_status] ?? 'bg-red-100 text-red-700';
                        $sBadgeClass = ['confirm' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', 'draft' => 'bg-slate-100 text-slate-600', 'cancelled' => 'bg-red-100 text-red-700'][$value->status] ?? 'bg-slate-100 text-slate-600';
                        $firstItem   = $value->items->first();
                        $firstTicket = $firstItem?->ticketPurchase?->ticket;
                        $pnrList     = $value->items->take(3)->map(fn ($i) => $i->ticketPurchase?->ticket_no)->filter()->implode(', ');
                        $extraCount  = max(0, $value->items->count() - 3);
                        $clientIsAgent = $value->client?->roles?->contains('name', 'agent') ?? false;
                        $clientCode = $value->client ? ($clientIsAgent ? 'EP-AG-' : 'EP-CU-').str_pad((string) $value->client->id, 3, '0', STR_PAD_LEFT) : null;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <span class="font-bold text-blue-700 text-sm">{{ $value->invoice_no }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-semibold text-slate-800">{{ $value->client?->name ?? '—' }}</div>
                            <div class="text-xs text-slate-400">{{ $value->client?->email }}</div>
                            @if($value->client)<div class="mt-0.5 text-[10px] text-slate-400">{{ $clientIsAgent ? 'Agent' : 'Customer' }} ID: <a href="{{ route('role.party-statement.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'party_id' => $value->client->id]) }}" class="font-semibold text-blue-700 hover:underline" title="Open party ledger">{{ $clientCode }}</a></div>@endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-semibold text-blue-700">
                                <i class="fas fa-ticket-alt text-[9px] mr-0.5"></i>
                                {{ $value->items->count() }} ticket{{ $value->items->count() != 1 ? 's' : '' }}
                            </span>
                            <div class="text-[11px] text-slate-400 mt-1">{{ $pnrList }}{{ $extraCount > 0 ? ", +{$extraCount}" : '' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-slate-800">{{ $firstTicket?->title ?? '—' }}</div>
                            <div class="text-xs text-slate-400">{{ $firstTicket?->from_airport?->code }} → {{ $firstTicket?->to_airport?->code }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ \Carbon\Carbon::parse($value->sale_date)->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">{{ number_format($value->total_amount) }}</td>
                        <td class="px-4 py-3 text-right text-sm font-medium text-emerald-600">{{ number_format($value->paid_amount) }}</td>
                        <td class="px-4 py-3 text-right text-sm {{ $value->due_amount > 0 ? 'font-semibold text-red-500' : 'text-slate-400' }}">{{ number_format($value->due_amount) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $pBadgeClass }}">{{ ucfirst($value->payment_status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $sBadgeClass }}">{{ ucfirst($value->status) }}</span>
                        </td>
                        <td class="px-2 py-3">
                            <div style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
                                <button type="button" onclick="tsShowDetail({{ $value->id }})"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-blue-50 hover:text-blue-600" title="View Details" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                    <i class="fas fa-eye text-xs" style="font-size:11px;margin:0"></i>
                                </button>
                                <a target="_blank" href="{{ route('role.ticket-sales.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_sale' => $value->id]) }}"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-teal-200 bg-teal-50 text-teal-600 hover:bg-teal-100" title="Print Invoice" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                    <i class="fas fa-print text-xs" style="font-size:11px;margin:0"></i>
                                </a>
                                @if($value->due_amount > 0 && $value->status !== 'cancelled')
                                <button type="button" onclick="openReceivePaymentModal({{ $value->id }}, '{{ $value->invoice_no }}', {{ $value->due_amount }})"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100" title="Receive payment" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                    <i class="fas fa-hand-holding-usd text-xs" style="font-size:11px;margin:0"></i>
                                </button>
                                @else
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-50 text-slate-300" title="Fully paid" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                    <i class="fas fa-check text-xs" style="font-size:11px;margin:0"></i>
                                </span>
                                @endif
                                <button type="button" onclick="tseOpen({{ $value->id }})"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100" title="Edit Sale" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                    <i class="fas fa-pencil-alt text-xs" style="font-size:11px;margin:0"></i>
                                </button>
                                <button type="button" onclick="confirmDelete('{{ $value->id }}', '{{ $value->invoice_no }}')"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100" title="Delete" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                    <i class="fas fa-trash text-xs" style="font-size:11px;margin:0"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="p-12 text-center">
                            <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
                            <p class="text-sm font-semibold text-slate-500">No ticket sales found</p>
                            <p class="mt-1 text-xs text-slate-400">Try adjusting your filters or create a new sale.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4" data-tfb-swap>{{ $datas->appends(request()->all())->links() }}</div>
    </div>

    {{-- View Details Modal --}}
    <div id="tsdModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto">
        <div class="w-full max-w-3xl bg-white rounded-2xl overflow-hidden shadow-2xl">
            <div class="flex items-center gap-3 bg-gradient-to-r from-indigo-600 to-blue-500 px-5 py-4">
                <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0"><i class="fas fa-receipt"></i></div>
                <div class="min-w-0 flex-1">
                    <h2 id="tsdTitle" class="text-sm font-bold text-white truncate">Ticket Sale Details</h2>
                    <p id="tsdSubtitle" class="text-blue-100 text-xs mt-0.5"></p>
                </div>
                <button type="button" onclick="tsCloseDetail()" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <div id="tsdBody" class="p-5 max-h-[calc(100vh-180px)] overflow-y-auto">{{-- populated by JS --}}</div>
        </div>
    </div>

    {{-- Receive Payment Modal --}}
    <div id="receivePaymentModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-center justify-center p-4 overflow-y-auto">
        <div class="w-full max-w-2xl max-h-[calc(100vh-2rem)] bg-white rounded-2xl overflow-y-auto shadow-2xl">
            <div class="flex items-center gap-3 bg-gradient-to-r from-emerald-600 to-green-500 px-5 py-4">
                <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="flex-1">
                    <h2 class="text-sm font-bold text-white">Receive Ticket Payment</h2>
                    <p class="text-xs text-emerald-100 mt-0.5">Invoice: <span id="rpInvoiceLabel"></span> — Due: <span id="rpDueLabel"></span></p>
                </div>
                <button type="button" onclick="closeReceivePaymentModal()" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <div class="p-5">
                <form id="receivePaymentForm" method="POST">
                    @csrf
                    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-slate-500"><i class="fas fa-sack-dollar mr-1"></i> Payment Details</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Amount <span class="text-red-500">*</span></label><input type="number" name="payment_amount" id="rpAmount" step="0.01" min="0.01" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Payment Date <span class="text-red-500">*</span></label><input type="date" name="payment_date" id="rpDate" max="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Custom Time <span class="text-red-500">*</span></label><input type="hidden" name="transaction_time" id="rpTransactionTime"><div class="flex items-center gap-1"><select id="rpHour" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-1 py-2 text-sm">@for($h=1;$h<=12;$h++)<option value="{{ $h }}">{{ str_pad($h,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><b>:</b><select id="rpMinute" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-1 py-2 text-sm">@for($m=0;$m<60;$m++)<option value="{{ $m }}">{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><select id="rpPeriod" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-1 py-2 text-sm"><option>AM</option><option>PM</option></select></div></div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-slate-500"><i class="fas fa-building-columns mr-1"></i> Money Route</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Received Via <span class="text-red-500">*</span></label><select name="payment_method" id="rpMethod" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_banking">Mobile Banking</option><option value="cheque">Cheque</option><option value="card">Card</option><option value="other">Other</option></select></div>
                            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Company Account (money received into) <span class="text-red-500">*</span></label><select name="bank_id" id="rpBank" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">— Select account —</option>@include('partials.bank-options', ['banks' => $banks, 'selected' => null])</select></div>
                            <div id="rpPayerFields" class="hidden sm:col-span-2 rounded-lg border border-blue-200 bg-blue-50/60 p-3"><p id="rpPayerTitle" class="mb-2 text-[11px] font-bold uppercase text-blue-700"></p><div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><div><label id="rpPayerBankLabel" class="block text-xs font-semibold text-slate-600 mb-1"></label><input type="text" name="payer_bank" id="rpPayerBank" maxlength="150" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div><div><label id="rpPayerRefLabel" class="block text-xs font-semibold text-slate-600 mb-1"></label><input type="text" name="payer_reference" id="rpPayerRef" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div></div></div>
                            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Reference No. / TRNX ID</label><input type="text" name="reference_no" id="rpReference" readonly class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-500"></div>
                            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Note (optional)</label><input type="text" name="notes" maxlength="500" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" onclick="closeReceivePaymentModal()" class="rounded-xl border border-slate-200 bg-slate-100 text-slate-700 px-4 py-2 text-sm font-semibold">Cancel</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 text-white px-5 py-2 text-sm font-semibold">Confirm Receipt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('dashboard.ticket_sales.delete-modal')
    @include('dashboard.ticket_sales.create-modal')
    @include('dashboard.ticket_sales.edit-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
        });

        $(document).on('click', '.modal-close-delete', function() {
            $('#deleteModal').addClass('hidden');
        });
        $('#deleteModal').on('click', function(e) { if (e.target.id === 'deleteModal') $(this).addClass('hidden'); });

        // Close success alert
        $('.close-btn').click(function() {
            $(this).closest('.alert').addClass('hidden');
        });

        // Delete confirmation
        $('#confirmDeleteBtn').click(function() {
            const dataId = $(this).data('item-id');
            const deleteUrl = $(this).data('action');
            $.ajax({
                url: deleteUrl,
                method: 'DELETE',
                data: {
                    item_id: dataId,
                },
                success: function (response) {
                    console.log(response);
                    if (response.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Done",
                            text: "Data deleted successfully!",
                        });
                        $('#deleteModal').addClass('hidden');
                        console.log('trigger reload');                                
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Opps...",
                            text: response.message,
                        });
                    }
                },
                error: function (xhr) {
                    console.error('❌ Error:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Something went wrong!'
                    });
                }
            });  
        });        

        // Delete confirmation
        function confirmDelete(id, name=null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

        // View sale details modal (voucher style, fetched via AJAX)
        const TS_DETAIL_BASE = "{{ url(Str::slug(Auth::user()->getRoleNames()->first()) . '/ticket-sales') }}";
        const TS_PSTATUS_COLORS = { paid: '#16a34a', partial: '#d97706', due: '#dc2626' };
        const TS_STATUS_COLORS  = { confirm: '#16a34a', pending: '#d97706', draft: '#6b7280', cancelled: '#dc2626' };

        function tsShowDetail(id) {
            $('#tsdTitle').text('Loading…');
            $('#tsdSubtitle').text('');
            $('#tsdBody').html('<div class="text-center py-12 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading...</div>');
            $('#tsdModal').removeClass('hidden');

            $.ajax({
                url: TS_DETAIL_BASE + '/' + id + '/detail',
                method: 'GET',
                dataType: 'json',
                success: function (d) {
                    if (!d.success) { $('#tsdBody').html('<div class="text-center py-12 text-red-400">Failed to load details.</div>'); return; }

                    $('#tsdTitle').text('Invoice ' + d.invoice_no);
                    $('#tsdSubtitle').text(d.client + ' — ' + d.sale_date);

                    var rows = (d.items || []).map(function (it, i) {
                        var route = (it.from || it.to) ? ((it.from || '—') + ' → ' + (it.to || '—')) : (it.route || '—');
                        return '<tr>'
                            + '<td style="padding:9px 10px;font-size:12px;">' + (i + 1) + '</td>'
                            + '<td style="padding:9px 10px;font-size:12px;"><span class="font-mono font-bold text-blue-700">' + (it.ticket_no || '—') + '</span><br><span class="text-gray-500" style="font-size:11px;">' + (it.passenger || '—') + '</span></td>'
                            + '<td style="padding:9px 10px;font-size:12px;">' + (it.route || '—') + '</td>'
                            + '<td style="padding:9px 10px;font-size:12px;">' + route + '</td>'
                            + '<td style="padding:9px 10px;font-size:12px;text-align:right;font-weight:600;">' + (d.currency || '') + Number(it.price).toLocaleString() + '</td>'
                            + '</tr>';
                    }).join('');

                    var pColor = TS_PSTATUS_COLORS[d.payment_status] || '#6b7280';
                    var sColor = TS_STATUS_COLORS[d.status] || '#6b7280';

                    var html = ''
                        + '<div class="grid gap-3 mb-4" style="grid-template-columns: repeat(3, 1fr);">'
                        + '  <div style="background:#f8fafc;border-radius:10px;padding:10px 14px;"><span class="text-xs text-gray-400 block">Invoice No</span><strong>' + d.invoice_no + '</strong></div>'
                        + '  <div style="background:#f8fafc;border-radius:10px;padding:10px 14px;"><span class="text-xs text-gray-400 block">Client</span><strong>' + d.client + '</strong></div>'
                        + '  <div style="background:#f8fafc;border-radius:10px;padding:10px 14px;"><span class="text-xs text-gray-400 block">Sale Date</span><strong>' + d.sale_date + '</strong></div>'
                        + '</div>'
                        + '<div style="border-radius:12px;overflow:hidden;margin-bottom:16px;">'
                        + '  <table style="width:100%;border-collapse:collapse;">'
                        + '    <thead><tr style="background:#1d4ed8;">'
                        + '      <th style="padding:8px 10px;font-size:10px;color:#fff;text-transform:uppercase;text-align:left;">#</th>'
                        + '      <th style="padding:8px 10px;font-size:10px;color:#fff;text-transform:uppercase;text-align:left;">PNR / Passenger</th>'
                        + '      <th style="padding:8px 10px;font-size:10px;color:#fff;text-transform:uppercase;text-align:left;">Ticket</th>'
                        + '      <th style="padding:8px 10px;font-size:10px;color:#fff;text-transform:uppercase;text-align:left;">Route</th>'
                        + '      <th style="padding:8px 10px;font-size:10px;color:#fff;text-transform:uppercase;text-align:right;">Price</th>'
                        + '    </tr></thead>'
                        + '    <tbody>' + (rows || '<tr><td colspan="5" class="text-center text-gray-400 text-xs py-4">No tickets.</td></tr>') + '</tbody>'
                        + '  </table>'
                        + '</div>'
                        + '<div class="grid gap-3 mb-4" style="grid-template-columns: repeat(4, 1fr);">'
                        + '  <div style="background:#eff6ff;border-radius:12px;padding:14px 16px;text-align:center;"><div class="font-black text-blue-700" style="font-size:1.4rem;">' + (d.items ? d.items.length : 0) + '</div><div class="text-xs text-gray-500 uppercase">Tickets</div></div>'
                        + '  <div style="background:#f0fdf4;border-radius:12px;padding:14px 16px;text-align:center;"><div class="font-black text-emerald-700" style="font-size:1.4rem;">' + (d.currency || '') + Number(d.total_amount).toLocaleString() + '</div><div class="text-xs text-gray-500 uppercase">Grand Total</div></div>'
                        + '  <div style="background:#ecfdf5;border-radius:12px;padding:14px 16px;text-align:center;"><div class="font-black text-green-700" style="font-size:1.4rem;">' + (d.currency || '') + Number(d.paid_amount).toLocaleString() + '</div><div class="text-xs text-gray-500 uppercase">Paid</div></div>'
                        + '  <div style="background:#fff1f2;border-radius:12px;padding:14px 16px;text-align:center;"><div class="font-black text-rose-700" style="font-size:1.4rem;">' + (d.currency || '') + Number(d.due_amount).toLocaleString() + '</div><div class="text-xs text-gray-500 uppercase">Due</div></div>'
                        + '</div>'
                        + '<div class="text-center mb-4">'
                        + '  <span style="display:inline-block;padding:5px 16px;border-radius:20px;font-size:12px;font-weight:700;background:' + pColor + '22;color:' + pColor + ';margin-right:6px;">Payment: ' + (d.payment_status ? d.payment_status.charAt(0).toUpperCase() + d.payment_status.slice(1) : '—') + '</span>'
                        + '  <span style="display:inline-block;padding:5px 16px;border-radius:20px;font-size:12px;font-weight:700;background:' + sColor + '22;color:' + sColor + ';">Status: ' + (d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : '—') + '</span>'
                        + '</div>'
                        + cmtHtml('ticket_sale', id)
                        + '<div class="flex gap-3 justify-end pt-4" style="margin-top:16px;">'
                        + '  <button type="button" onclick="tsCloseDetail()" class="px-4 py-2 rounded-xl border border-gray-200 text-gray-700 font-medium text-sm hover:bg-gray-50 transition-colors">Close</button>'
                        + '  <a href="' + TS_DETAIL_BASE + '/' + id + '" target="_blank" class="px-5 py-2 rounded-xl bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700 transition-colors flex items-center gap-2"><i class="fas fa-print"></i> Print Invoice</a>'
                        + '</div>';

                    $('#tsdBody').html(html);
                    loadComments('ticket_sale', id);
                },
                error: function () {
                    $('#tsdBody').html('<div class="text-center py-12 text-red-400">Failed to load details.</div>');
                }
            });
        }
        function tsCloseDetail() {
            $('#tsdModal').addClass('hidden');
        }
        $('#tsdModal').on('click', function (e) { if (e.target.id === 'tsdModal') tsCloseDetail(); });

        // ── Receive Payment (settles a due invoice) ───────────────────
        const TS_MAKE_PAYMENT_BASE = "{{ url(Str::slug(Auth::user()->getRoleNames()->first()) . '/ticket-sales/make/payment') }}";
        const rpAccountTypes = { cash:['cash'], bank_transfer:['bank'], cheque:['bank'], card:['bank'], mobile_banking:['mobile_banking','digital_wallet'] };
        const rpPayerCopy = {
            bank_transfer:{title:'Customer / Agent paid from',bank:'Bank Name',ref:'Account Number',bankPh:'e.g. Dutch-Bangla Bank',refPh:'Party account number'},
            cheque:{title:'Cheque details',bank:'Bank Name (cheque drawn on)',ref:'Cheque No.',bankPh:'e.g. Dutch-Bangla Bank',refPh:'Cheque number'},
            mobile_banking:{title:'Customer / Agent paid from',bank:'Provider / Service',ref:'Mobile Account Number',bankPh:'e.g. bKash, Nagad or Rocket',refPh:'Party mobile account number'}
        };
        function syncTicketPaymentRoute() {
            const method = document.getElementById('rpMethod').value;
            const account = document.getElementById('rpBank');
            const wanted = rpAccountTypes[method] || null;
            let first = null;
            [...account.options].forEach(option => {
                if (!option.value) return;
                const hidden = !!wanted && !wanted.includes(option.dataset.type || '');
                option.hidden = hidden; option.disabled = hidden;
                if (!hidden && !first) first = option;
            });
            account.querySelectorAll('optgroup').forEach(group => {
                const visible = [...group.querySelectorAll('option')].some(option => !option.hidden);
                group.hidden = !visible; group.disabled = !visible;
            });
            if (account.selectedOptions[0]?.hidden || account.selectedOptions[0]?.disabled) account.value = '';
            if (method === 'cash' && first) account.value = ([...account.options].find(o => !o.hidden && /office\s*cash/i.test(o.textContent)) || first).value;
            const copy = rpPayerCopy[method];
            document.getElementById('rpPayerFields').classList.toggle('hidden', !copy);
            const bank = document.getElementById('rpPayerBank'), ref = document.getElementById('rpPayerRef');
            if (copy) {
                document.getElementById('rpPayerTitle').textContent = copy.title;
                document.getElementById('rpPayerBankLabel').innerHTML = copy.bank + ' <span class="text-red-500">*</span>';
                document.getElementById('rpPayerRefLabel').innerHTML = copy.ref + ' <span class="text-red-500">*</span>';
                bank.placeholder = copy.bankPh; ref.placeholder = copy.refPh;
            }
            [bank,ref].forEach(field => { field.required=!!copy; field.disabled=!copy; if(!copy) field.value=''; });
        }
        document.getElementById('rpMethod')?.addEventListener('change', syncTicketPaymentRoute);

        function openReceivePaymentModal(saleId, invoiceNo, dueAmt) {
            $('#rpInvoiceLabel').text(invoiceNo);
            $('#rpDueLabel').text('৳' + parseFloat(dueAmt).toFixed(2));
            $('#rpAmount').val(parseFloat(dueAmt).toFixed(2)).attr('max', dueAmt);
            const now = new Date(), hour24 = now.getHours();
            $('#rpDate').val([now.getFullYear(),String(now.getMonth()+1).padStart(2,'0'),String(now.getDate()).padStart(2,'0')].join('-'));
            $('#rpHour').val(hour24 % 12 || 12); $('#rpMinute').val(now.getMinutes()); $('#rpPeriod').val(hour24 >= 12 ? 'PM' : 'AM');
            $('#rpMethod').val('cash'); $('#rpBank').val(''); $('#rpPayerBank,#rpPayerRef').val('');
            $('#rpReference').val(invoiceNo + '-PAY-' + [now.getFullYear(),String(now.getMonth()+1).padStart(2,'0'),String(now.getDate()).padStart(2,'0'),String(now.getHours()).padStart(2,'0'),String(now.getMinutes()).padStart(2,'0'),String(now.getSeconds()).padStart(2,'0')].join(''));
            syncTicketPaymentRoute();
            $('#receivePaymentForm').attr('action', TS_MAKE_PAYMENT_BASE + '/' + saleId);
            $('#receivePaymentModal').removeClass('hidden');
        }
        function closeReceivePaymentModal() {
            $('#receivePaymentModal').addClass('hidden');
        }
        $('#receivePaymentModal').on('click', function (e) { if (e.target.id === 'receivePaymentModal') closeReceivePaymentModal(); });
        $('#receivePaymentForm').on('submit', function () {
            let hour=Number($('#rpHour').val())||12; const minute=String($('#rpMinute').val()).padStart(2,'0'), period=$('#rpPeriod').val();
            if(period==='PM'&&hour!==12) hour+=12; if(period==='AM'&&hour===12) hour=0;
            $('#rpTransactionTime').val(String(hour).padStart(2,'0')+':'+minute);
        });

        // ══════════════════════════════════════════════════════════
        //  CREATE / EDIT TICKET SALE — modal logic (mirrors visa-sales)
        // ══════════════════════════════════════════════════════════
        const TS_ROLE = '{{ Str::slug(Auth::user()->getRoleNames()->first()) }}';
        const TS_AVAILABLE_URL = "{{ route('role.ticket-sales.available-purchases', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
        const TS_BASE_URL      = "{{ url(Str::slug(Auth::user()->getRoleNames()->first()) . '/ticket-sales') }}";
        const TS_AGENT_IDS     = new Set(@json($agents->pluck('id')));

        // ── CREATE ──────────────────────────────────────────────────
        let tsTicketData    = [];
        let tsSelectedIds   = new Set();
        let tsSalePrices    = {};
        let tsTotalAmount   = 0;
        let tsTicketQuery   = '';

        const TS_PARTY_FIELDS = {
            create: { id: '#ts_create_client_id', agent: '#ts_create_agent_picker', customer: '#ts_create_customer_picker' },
            edit:   { id: '#tse_client_id',       agent: '#tse_agent_picker',       customer: '#tse_customer_picker' },
        };

        // Picking an agent/vendor clears and locks out the customer picker
        // (and vice versa) — only one party can be the sale's client_id.
        function tsPickClient(prefix, kind, id) {
            const f = TS_PARTY_FIELDS[prefix];
            const otherKind = kind === 'agent' ? 'customer' : 'agent';
            if (id) {
                $(f[otherKind]).val('').trigger('change.select2').prop('disabled', true);
            } else {
                $(f[otherKind]).prop('disabled', false);
            }
            $(f.id).val(id || '').trigger('change');
        }

        $(document).on('change', '#ts_create_agent_picker, #tse_agent_picker', function() {
            tsPickClient(this.id.startsWith('tse_') ? 'edit' : 'create', 'agent', this.value);
        });
        $(document).on('change', '#ts_create_customer_picker, #tse_customer_picker', function() {
            tsPickClient(this.id.startsWith('tse_') ? 'edit' : 'create', 'customer', this.value);
        });

        function tsOpenCreate() {
            $('#tsCreateForm')[0].reset();
            $('#ts_create_client_id').val('');
            $('#ts_create_agent_picker, #ts_create_customer_picker').prop('disabled', false).val('').trigger('change.select2');
            $('#ts_create_date').val(new Date().toISOString().slice(0,10));
            tsSelectedIds.clear();
            tsSalePrices = {};
            tsTicketData = [];
            tsTicketQuery = '';
            $('#tsTicketSearch').val('');
            $('#tsTicketSearchClear').addClass('hidden');
            $('#tsHiddenInputs').empty();
            $('#tsCreateModal').removeClass('hidden');
            tsToggleBankField();
            tsLoadAvailable();
        }
        function tsCloseCreate() {
            $('#tsCreateModal').addClass('hidden');
        }

        // Advance payments are covered by the agent's existing party-statement
        // credit, not a new bank deposit — so no bank account applies to them.
        function tsToggleBankField() {
            const isAdvance = $('#tsPaymentMethod').val() === 'advance';
            $('#tsBankField').toggleClass('hidden', isAdvance);
            if (isAdvance) $('#tsBank').val('');
        }

        function tsLoadAvailable() {
            const $tbody = $('#tsTicketsBody');
            $tbody.html('<tr><td colspan="5" class="text-center py-4 text-gray-400 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i> Loading...</td></tr>');
            $.ajax({
                url: TS_AVAILABLE_URL,
                method: 'GET',
                success: function (data) {
                    tsTicketData = data.purchases || [];
                    tsRenderTickets();
                },
                error: function () {
                    $tbody.html('<tr><td colspan="5" class="text-center py-4 text-red-400 text-xs">Failed to load tickets.</td></tr>');
                }
            });
        }

        function tsRenderTickets() {
            const $tbody = $('#tsTicketsBody');
            if (!tsTicketData.length) {
                $tbody.html('<tr><td colspan="5" class="text-center py-6 text-gray-400 text-xs">No confirmed unsold tickets available.</td></tr>');
                return;
            }
            const visibleTickets = tsFilteredTickets();
            if (!visibleTickets.length) {
                $tbody.html('<tr><td colspan="5" class="text-center py-6 text-gray-400 text-xs"><i class="fas fa-search mr-1"></i>No ticket matches your search.</td></tr>');
                return;
            }
            $tbody.html(visibleTickets.map(function (t) {
                const checked  = tsSelectedIds.has(t.id) ? 'checked' : '';
                const selClass = tsSelectedIds.has(t.id) ? 'selected' : '';
                const salePrice = (tsSalePrices[t.id] !== undefined) ? tsSalePrices[t.id] : t.price;
                return '<tr class="' + selClass + '" onclick="tsToggle(' + t.id + ', ' + t.price + ')">' +
                    '<td><input type="checkbox" ' + checked + ' onclick="event.stopPropagation(); tsToggle(' + t.id + ', ' + t.price + ')"></td>' +
                    '<td class="font-mono font-bold text-blue-700">' + (t.ticket_no || '—') + '</td>' +
                    '<td>' + t.passenger + '</td>' +
                    '<td>' + t.route + '</td>' +
                    '<td class="text-right">' +
                        '<input type="number" step="0.01" min="0" class="w-24 text-right rounded border border-slate-200 px-2 py-1 text-xs" ' +
                            'value="' + salePrice + '" onclick="event.stopPropagation()" oninput="tsSetPrice(' + t.id + ', this.value)">' +
                        '<div class="text-[10px] text-slate-400 mt-0.5">Cost: ' + Number(t.price).toLocaleString() + '</div>' +
                    '</td>' +
                    '</tr>';
            }).join(''));
        }

        function tsFilteredTickets() {
            if (!tsTicketQuery) return tsTicketData;
            return tsTicketData.filter(function (t) {
                return [t.ticket_no, t.name_ticket_no, t.passenger, t.passport_no, t.route]
                    .filter(Boolean).join(' ').toLowerCase().includes(tsTicketQuery);
            });
        }

        function tsFilterTickets(value) {
            tsTicketQuery = String(value || '').trim().toLowerCase();
            $('#tsTicketSearchClear').toggleClass('hidden', !tsTicketQuery);
            $('#tsSelectAll').prop('checked', false);
            tsRenderTickets();
        }

        function tsClearTicketSearch() {
            $('#tsTicketSearch').val('').focus();
            tsFilterTickets('');
        }

        function tsSetPrice(id, value) {
            tsSalePrices[id] = parseFloat(value) || 0;
            tsUpdateTotals();
        }

        function tsToggle(id, price) {
            if (tsSelectedIds.has(id)) {
                tsSelectedIds.delete(id);
            } else {
                tsSelectedIds.add(id);
                if (tsSalePrices[id] === undefined) tsSalePrices[id] = price;
            }
            tsRenderTickets();
            tsUpdateTotals();
        }

        function tsToggleAll(chk) {
            tsFilteredTickets().forEach(function (t) {
                if (chk.checked) {
                    tsSelectedIds.add(t.id);
                    if (tsSalePrices[t.id] === undefined) tsSalePrices[t.id] = t.price;
                } else {
                    tsSelectedIds.delete(t.id);
                }
            });
            tsRenderTickets();
            tsUpdateTotals();
        }

        function tsUpdateTotals() {
            let total = 0;
            let totalCost = 0;
            tsTicketData.forEach(function (t) {
                if (tsSelectedIds.has(t.id)) {
                    total += parseFloat(tsSalePrices[t.id] !== undefined ? tsSalePrices[t.id] : t.price) || 0;
                    totalCost += parseFloat(t.price) || 0;
                }
            });
            tsTotalAmount = total;

            $('#tsSelectedCount').text(tsSelectedIds.size + ' ticket' + (tsSelectedIds.size !== 1 ? 's' : '') + ' selected');
            $('#tsGrandTotal').text(total.toFixed(2));
            $('#tsTotalDisplay').val(total.toFixed(2));

            const profit = total - totalCost;
            const marginPct = total > 0 ? (profit / total * 100) : 0;
            $('#tsGrandMargin').text('Profit: ' + profit.toFixed(2) + ' (' + marginPct.toFixed(1) + '%)')
                .toggleClass('text-emerald-600', profit >= 0)
                .toggleClass('text-red-500', profit < 0)
                .toggleClass('text-slate-400', profit === 0);

            const $wrap = $('#tsHiddenInputs').empty();
            tsSelectedIds.forEach(function (id) {
                $wrap.append($('<input>').attr({ type: 'hidden', name: 'ticket_purchase_ids[]', value: id }));
                $wrap.append($('<input>').attr({ type: 'hidden', name: 'sale_prices[' + id + ']', value: tsSalePrices[id] !== undefined ? tsSalePrices[id] : 0 }));
            });

            tsCalcDue();
        }

        function tsCalcDue() {
            const paid = parseFloat($('#tsPaidInput').val()) || 0;
            const due  = Math.max(0, tsTotalAmount - paid);
            $('#tsDueDisplay').val(due.toFixed(2));
            $('#ts_create_payment_status').val(paid <= 0 ? 'due' : (due <= 0 ? 'paid' : 'partial'));
        }

        $(document).on('click', '#tsCreateSubmit', function () {
            $('#ts_create_tickets_msg').addClass('hidden');
            if (tsSelectedIds.size === 0) {
                $('#ts_create_tickets_msg').removeClass('hidden');
                return;
            }
            if (!$('#ts_create_client_id').val()) {
                Swal.fire({ icon: 'error', title: 'Error!', text: 'Please select an agent, vendor, or customer.' });
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Saving...');

            $.ajax({
                url: $('#tsCreateForm').attr('action'),
                method: 'POST',
                data: new FormData($('#tsCreateForm')[0]),
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: res.message });
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Sale');
                        Swal.fire({ icon: 'error', title: 'Oops...', text: res.message || 'Something went wrong.' });
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Sale');
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create sale.' });
                }
            });
        });

        $('#tsCreateModal').on('click', function (e) { if (e.target.id === 'tsCreateModal') tsCloseCreate(); });

        // ── EDIT ────────────────────────────────────────────────────
        let tseTicketData  = [];
        let tseSelectedIds = new Set();
        let tseSalePrices  = {};
        let tseTotalAmount = 0;
        let tsePrevPaid    = 0;
        let tseSaleId      = null;
        let tseTicketQuery = '';

        function tseOpen(saleId) {
            tseSaleId = saleId;
            $('#tsEditForm')[0].reset();
            tseSelectedIds.clear();
            tseSalePrices = {};
            tseTicketData = [];
            tseTicketQuery = '';
            $('#tseTicketSearch').val('');
            $('#tseTicketSearchClear').addClass('hidden');
            $('#tseHiddenInputs').empty();
            $('#tsEditForm').attr('action', TS_BASE_URL + '/' + saleId);
            $('#tsEditModal').removeClass('hidden');
            tseToggleBankField();

            $.ajax({
                url: TS_BASE_URL + '/' + saleId + '/edit',
                method: 'GET',
                success: function (res) {
                    if (!res.success) { Swal.fire('Error!', 'Could not load sale.', 'error'); return; }
                    const sale = res.sale;
                    $('#tseInvoiceLabel').text(sale.invoice_no);
                    const isAgent = TS_AGENT_IDS.has(Number(sale.client_id));
                    $('#tse_agent_picker').prop('disabled', false).val(isAgent ? sale.client_id : '').trigger('change.select2');
                    $('#tse_customer_picker').prop('disabled', false).val(!isAgent && sale.client_id ? sale.client_id : '').trigger('change.select2');
                    if (sale.client_id) {
                        $(isAgent ? '#tse_customer_picker' : '#tse_agent_picker').prop('disabled', true);
                    }
                    $('#tse_client_id').val(sale.client_id || '');
                    $('#tseSaleDate').val(sale.sale_date);
                    $('#tseReceivableDate').val(sale.due_date || '');
                    $('#tseStatus').val(sale.status).trigger('change');
                    $('#tseCurrency').val(sale.currency);
                    $('#tseBank').val(sale.bank_id).trigger('change');
                    $('#tsePaymentInstructionBank').val(sale.payment_instruction_bank_id || '').trigger('change');
                    tseToggleBankField();
                    tsePrevPaid = parseFloat(sale.paid_amount) || 0;
                    $('#tsePrevPaid').text(tsePrevPaid.toFixed(2));
                    $('#tsePaidInput').val(0);

                    sale.items.forEach(function (i) {
                        tseSelectedIds.add(i.purchase_id);
                        tseSalePrices[i.purchase_id] = parseFloat(i.price) || 0;
                    });

                    tseLoadAvailable();
                },
                error: function () {
                    Swal.fire('Error!', 'Failed to load sale data.', 'error');
                }
            });
        }
        function tseClose() {
            $('#tsEditModal').addClass('hidden');
        }

        // Advance payments are covered by the agent's existing party-statement
        // credit, not a new bank deposit — so no bank account applies to them.
        function tseToggleBankField() {
            const isAdvance = $('#tsePaymentMethod').val() === 'advance';
            $('#tseBankField').toggleClass('hidden', isAdvance);
            if (isAdvance) $('#tseBank').val('');
        }

        function tseLoadAvailable() {
            const $tbody = $('#tseTicketsBody');
            $tbody.html('<tr><td colspan="5" class="text-center py-4 text-gray-400 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i> Loading...</td></tr>');
            $.ajax({
                url: TS_AVAILABLE_URL,
                method: 'GET',
                data: { exclude_sale_id: tseSaleId },
                success: function (data) {
                    tseTicketData = data.purchases || [];
                    tseRenderTickets();
                    tseUpdateTotals();
                },
                error: function () {
                    $tbody.html('<tr><td colspan="5" class="text-center py-4 text-red-400 text-xs">Failed to load tickets.</td></tr>');
                }
            });
        }

        function tseRenderTickets() {
            const $tbody = $('#tseTicketsBody');
            if (!tseTicketData.length) {
                $tbody.html('<tr><td colspan="5" class="text-center py-6 text-gray-400 text-xs">No confirmed unsold tickets available.</td></tr>');
                return;
            }
            const visibleTickets = tseFilteredTickets();
            if (!visibleTickets.length) {
                $tbody.html('<tr><td colspan="5" class="text-center py-6 text-gray-400 text-xs"><i class="fas fa-search mr-1"></i>No ticket matches your search.</td></tr>');
                return;
            }
            $tbody.html(visibleTickets.map(function (t) {
                const checked  = tseSelectedIds.has(t.id) ? 'checked' : '';
                const selClass = tseSelectedIds.has(t.id) ? 'selected' : '';
                const salePrice = (tseSalePrices[t.id] !== undefined) ? tseSalePrices[t.id] : t.price;
                return '<tr class="' + selClass + '" onclick="tseToggle(' + t.id + ', ' + t.price + ')">' +
                    '<td><input type="checkbox" ' + checked + ' onclick="event.stopPropagation(); tseToggle(' + t.id + ', ' + t.price + ')"></td>' +
                    '<td class="font-mono font-bold text-purple-700">' + (t.ticket_no || '—') + '</td>' +
                    '<td>' + t.passenger + '</td>' +
                    '<td>' + t.route + '</td>' +
                    '<td class="text-right">' +
                        '<input type="number" step="0.01" min="0" class="w-24 text-right rounded border border-slate-200 px-2 py-1 text-xs" ' +
                            'value="' + salePrice + '" onclick="event.stopPropagation()" oninput="tseSetPrice(' + t.id + ', this.value)">' +
                        '<div class="text-[10px] text-slate-400 mt-0.5">Cost: ' + Number(t.price).toLocaleString() + '</div>' +
                    '</td>' +
                    '</tr>';
            }).join(''));
        }

        function tseFilteredTickets() {
            if (!tseTicketQuery) return tseTicketData;
            return tseTicketData.filter(function (t) {
                return [t.ticket_no, t.name_ticket_no, t.passenger, t.passport_no, t.route]
                    .filter(Boolean).join(' ').toLowerCase().includes(tseTicketQuery);
            });
        }

        function tseFilterTickets(value) {
            tseTicketQuery = String(value || '').trim().toLowerCase();
            $('#tseTicketSearchClear').toggleClass('hidden', !tseTicketQuery);
            $('#tseSelectAll').prop('checked', false);
            tseRenderTickets();
        }

        function tseClearTicketSearch() {
            $('#tseTicketSearch').val('').focus();
            tseFilterTickets('');
        }

        function tseSetPrice(id, value) {
            tseSalePrices[id] = parseFloat(value) || 0;
            tseUpdateTotals();
        }

        function tseToggle(id, price) {
            if (tseSelectedIds.has(id)) {
                tseSelectedIds.delete(id);
            } else {
                tseSelectedIds.add(id);
                if (tseSalePrices[id] === undefined) tseSalePrices[id] = price;
            }
            tseRenderTickets();
            tseUpdateTotals();
        }

        function tseToggleAll(chk) {
            tseFilteredTickets().forEach(function (t) {
                if (chk.checked) {
                    tseSelectedIds.add(t.id);
                    if (tseSalePrices[t.id] === undefined) tseSalePrices[t.id] = t.price;
                } else {
                    tseSelectedIds.delete(t.id);
                }
            });
            tseRenderTickets();
            tseUpdateTotals();
        }

        function tseUpdateTotals() {
            let total = 0;
            let totalCost = 0;
            tseTicketData.forEach(function (t) {
                if (tseSelectedIds.has(t.id)) {
                    total += parseFloat(tseSalePrices[t.id] !== undefined ? tseSalePrices[t.id] : t.price) || 0;
                    totalCost += parseFloat(t.price) || 0;
                }
            });
            tseTotalAmount = total;

            $('#tseSelectedCount').text(tseSelectedIds.size + ' ticket' + (tseSelectedIds.size !== 1 ? 's' : '') + ' selected');
            $('#tseGrandTotal').text(total.toFixed(2));
            $('#tseTotalDisplay').val(total.toFixed(2));

            const profit = total - totalCost;
            const marginPct = total > 0 ? (profit / total * 100) : 0;
            $('#tseGrandMargin').text('Profit: ' + profit.toFixed(2) + ' (' + marginPct.toFixed(1) + '%)')
                .toggleClass('text-emerald-600', profit >= 0)
                .toggleClass('text-red-500', profit < 0)
                .toggleClass('text-slate-400', profit === 0);

            const $wrap = $('#tseHiddenInputs').empty();
            tseSelectedIds.forEach(function (id) {
                $wrap.append($('<input>').attr({ type: 'hidden', name: 'ticket_purchase_ids[]', value: id }));
                $wrap.append($('<input>').attr({ type: 'hidden', name: 'sale_prices[' + id + ']', value: tseSalePrices[id] !== undefined ? tseSalePrices[id] : 0 }));
            });

            tseCalcDue();
        }

        function tseCalcDue() {
            const addl = parseFloat($('#tsePaidInput').val()) || 0;
            const totalPaid = Math.min(tsePrevPaid + addl, tseTotalAmount);
            const due  = Math.max(0, tseTotalAmount - totalPaid);
            $('#tseDueDisplay').val(due.toFixed(2));
            $('#tsePaymentStatus').val(totalPaid <= 0 ? 'due' : (due <= 0 ? 'paid' : 'partial'));
        }

        $(document).on('click', '#tsEditSubmit', function () {
            $('#ts_edit_tickets_msg').addClass('hidden');
            if (tseSelectedIds.size === 0) {
                $('#ts_edit_tickets_msg').removeClass('hidden');
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Saving...');

            $.ajax({
                url: $('#tsEditForm').attr('action'),
                method: 'POST',
                data: new FormData($('#tsEditForm')[0]),
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: res.message });
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Sale');
                        Swal.fire({ icon: 'error', title: 'Oops...', text: res.message || 'Something went wrong.' });
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Sale');
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to update sale.' });
                }
            });
        });

        $('#tsEditModal').on('click', function (e) { if (e.target.id === 'tsEditModal') tseClose(); });
    </script>
    @include('components.comment-panel')
@endsection
