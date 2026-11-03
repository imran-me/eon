@extends('layout.app')
@section('meta-information')
    <title>Edit Ticket Purchase #{{ $ticketPurchase->ticket_no }}</title>
@endsection
@php
    $tp      = $ticketPurchase;
    $role    = Str::slug(Auth::user()->getRoleNames()->first());
    $sale    = $saleItem?->sale;
    $dt      = fn ($v) => $v ? substr((string) $v, 0, 16) : '';
    $money   = fn ($v) => $v === null || $v === '' ? '' : number_format((float) $v, 2, '.', '');
    // What is on the books now — the rail's "What will change" compares against these.
    $orig = [
        'cost' => (float) $tp->amount, 'paid' => (float) $tp->paid_amount, 'com' => (float) ($tp->commission_amount ?? 0),
        'buyer' => $sale ? (string) $sale->client_id : '', 'price' => $saleItem ? (float) $saleItem->price : null,
        'invoice' => $sale?->invoice_no, 'others' => $sale ? max(0, $sale->items->count() - 1) : 0,
        'status' => $tp->status, 'vendor' => (string) ($tp->vendor_id ?? ''),
    ];
@endphp
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
    {{-- Same layout as Ticket Entry (owner, 2026-10-07): the four tinted panels, the
         same fields, and the summary rail with what Save will change. Page-scoped (te-). --}}
    .te-wrap{padding:4px 0 20px;}
    .te-head{background:#fff;border:1px solid #e6e9f2;border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:14px;margin-bottom:14px;box-shadow:0 1px 4px rgba(30,50,100,.07);}
    .te-logo{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#5b21b6,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
    .te-title{font-size:17px;font-weight:700;color:#1a2035;}
    .te-sub{font-size:12px;color:#64748b;margin-top:2px;}
    .te-sub b{font-family:ui-monospace,monospace;color:#4c1d95;}
    .te-chip{display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;margin-left:6px;}
    .te-head .right{margin-left:auto;display:flex;gap:8px;}
    .erp-btn{padding:9px 16px;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;border:none;transition:all .15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none;}
    .btn-ghost{background:#f0f2f8;color:#5a6480;border:1px solid #e4e8f0;} .btn-ghost:hover{color:#1a2035;border-color:#d0d6e8;}
    .btn-green-s{background:#16a34a;color:#fff;} .btn-green-s:hover{background:#15803d;} .btn-green-s:disabled{opacity:.6;cursor:not-allowed;}

    .te-grid{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start;}
    @media(max-width:1180px){.te-grid{grid-template-columns:1fr;}.te-rail{position:static!important;}}
    .te-card{background:#fff;border:1px solid #e6e9f2;border-radius:14px;padding:12px 14px 14px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px rgba(79,70,229,.05);}

    .te-zone{--z:#7c3aed;--zb:#faf8ff;--zl:#d9cefb;background:var(--zb);border:1.5px solid var(--zl);border-left:4px solid var(--z);border-radius:10px;padding:8px 12px 4px;margin-top:12px;box-shadow:0 1px 2px rgba(15,23,42,.06),0 4px 12px rgba(15,23,42,.07);}
    .te-zone:first-child{margin-top:0;}
    .te-zone.z-ticket{--z:#2563eb;--zb:#f7faff;--zl:#c5d7f7;}
    .te-zone.z-flight{--z:#0891b2;--zb:#f5fcfd;--zl:#b8e2ea;}
    .te-zone.z-fare{--z:#059669;--zb:#f6fcf9;--zl:#b5e2cd;}
    .te-zone.z-legs{--z:#ea580c;--zb:#fffaf5;--zl:#fbd5b5;}
    .te-zone-h{display:flex;align-items:center;gap:8px;margin:0 0 9px;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--z);}
    .te-zone-h i{width:19px;height:19px;border-radius:6px;background:#fff;color:var(--z);display:inline-flex;align-items:center;justify-content:center;font-size:9px;box-shadow:0 0 0 1px var(--zl);}
    .te-zone-h::after{content:'';flex:1;border-top:1px solid var(--zl);margin-left:4px;}
    .te-zone-h .te-zone-act{order:3;margin-left:6px;}

    .te-row{display:grid;gap:10px;margin-bottom:9px;grid-template-columns:repeat(var(--c,3),minmax(0,1fr));}
    .te-row > .s2{grid-column:span 2;}
    .te-row.te-row-trav{grid-template-columns:1.6fr 1.6fr 1fr 1fr;}
    .te-row.te-row-flight{grid-template-columns:1fr 2.8fr .6fr 1fr;}
    @media(max-width:900px){.te-row,.te-row.te-row-trav,.te-row.te-row-flight{grid-template-columns:repeat(2,minmax(0,1fr));}.te-row > .s2{grid-column:span 2;}}
    @media(max-width:560px){.te-row,.te-row.te-row-trav,.te-row.te-row-flight{grid-template-columns:1fr;}.te-row > .s2{grid-column:auto;}}
    .te-lbl{display:block;font-size:9.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin:0 0 3px 2px;}
    .te-lbl .req{color:#dc2626;}
    .te-in{width:100%;box-sizing:border-box;height:34px;padding:6px 10px;border:1px solid #dfe3ee;border-radius:8px;font-size:12.5px;background:#fff;color:#1a2035;outline:none;transition:border-color .12s,box-shadow .12s;}
    .te-in:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .te-in.te-invalid{border-color:#dc2626!important;background:#fef2f2!important;box-shadow:0 0 0 2px rgba(220,38,38,.08)!important;}
    .te-wrap .select2-container.te-invalid .select2-selection--single{border-color:#dc2626!important;background:#fef2f2!important;box-shadow:0 0 0 2px rgba(220,38,38,.08)!important;}
    .te-error{display:block;margin:4px 2px 0;color:#dc2626;font-size:10px;font-weight:600;line-height:1.35;}
    .te-form-error{margin:0 0 12px;padding:9px 12px;border:1px solid #fecaca;border-radius:9px;background:#fef2f2;color:#b91c1c;font-size:11px;font-weight:600;}
    .te-in[readonly]{background:#f8fafc;color:#475569;}
    .te-num{text-align:right;font-variant-numeric:tabular-nums;}
    .te-net{background:#f5f3ff!important;color:#4c1d95;font-weight:700;}
    .te-profit{background:#f8fafc;font-weight:700;text-align:right;}
    .te-profit.pos{color:#15803d;background:#f0fdf4;} .te-profit.neg{color:#b91c1c;background:#fef2f2;}
    .te-pnr{border-color:#c4b5fd;background:#faf5ff;font-family:ui-monospace,monospace;font-weight:700;letter-spacing:.04em;}
    .te-hint{display:block;font-size:10.5px;color:#64748b;margin:3px 0 0 2px;}
    .te-hint.g{color:#059669;} .te-hint.w{color:#b45309;} .te-hint.r{color:#dc2626;}
    .te-pair{display:grid;grid-template-columns:1fr 1fr;}
    .te-pair > div:first-child .te-in{border-top-right-radius:0;border-bottom-right-radius:0;}
    .te-pair > div:last-child .te-in{border-top-left-radius:0;border-bottom-left-radius:0;margin-left:-1px;}
    .te-wrap .select2-container--default .select2-selection--single{height:34px;border:1px solid #dfe3ee;border-radius:8px;display:flex;align-items:center;font-size:12.5px;}
    .te-wrap .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:32px;padding-left:10px;color:#1a2035;}
    .te-wrap .select2-container--default .select2-selection--single .select2-selection__arrow{height:32px;}
    .te-wrap .select2-container--default.select2-container--focus .select2-selection--single{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .te-note{font-size:11.5px;border-radius:8px;padding:8px 10px;margin:2px 0 8px;display:flex;gap:8px;align-items:flex-start;}
    .te-note.info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}
    .te-note.warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a;}
    .te-note.bad{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
    .te-leg{display:grid;grid-template-columns:1fr 1fr 1.2fr 1fr 34px;gap:10px;margin-bottom:8px;align-items:end;}
    .te-leg-del{height:34px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;color:#dc2626;cursor:pointer;}
    .te-add{padding:4px 11px;border-radius:7px;background:#fff;color:#c2410c;border:1px solid #fdba74;font-size:11px;font-weight:700;cursor:pointer;}

    .te-rail{position:sticky;top:84px;}
    .te-rail-card{background:#fff;border:1px solid #e6e9f2;border-radius:14px;padding:12px 14px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px rgba(79,70,229,.06);}
    .te-rail-h{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#8b88a8;}
    .te-rail-h i{width:22px;height:22px;border-radius:7px;background:#f5f3ff;color:#7c3aed;display:inline-flex;align-items:center;justify-content:center;font-size:10px;}
    .te-rr{display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:12px;color:#64748b;}
    .te-rr b{font-weight:600;color:#334155;font-variant-numeric:tabular-nums;}
    .te-rr.strong,.te-rr.strong b{font-weight:700;color:#1e1b4b;}
    .te-rr b.g{color:#059669;} .te-rr b.r{color:#dc2626;}
    .te-sep{border-top:1px dashed #e2e8f0;margin:6px 0;}
    .te-post h4{margin:8px 0 6px;font-size:10.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#334155;}
    .te-pl{display:flex;justify-content:space-between;gap:8px;padding:2px 0;font-size:12px;cursor:default;}
    .te-pl span{color:#475569;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .te-pl b{white-space:nowrap;font-variant-numeric:tabular-nums;color:#334155;font-weight:600;}
    .te-pl b.g{color:#059669;} .te-pl b.r{color:#dc2626;}
    .te-pw{font-size:11px;color:#b45309;margin-top:2px;}
    .te-none{font-size:11.5px;color:#94a3b8;}
    .te-act{display:flex;flex-direction:column;gap:8px;margin-top:12px;}
    .te-act .erp-btn{width:100%;justify-content:center;}
    </style>
@endsection
@section('main-content')
@include('layout.ticketing-tabs')

<div class="te-wrap">
  <div class="te-head">
    <div class="te-logo"><i class="fas fa-pencil"></i></div>
    <div>
      <div class="te-title">Edit Ticket Purchase
        @php $chip = ['confirm' => ['Purchased', '#ecfdf5', '#047857'], 'cancelled' => ['Cancelled', '#fef2f2', '#b91c1c'], 'pending' => ['Pending', '#fffbeb', '#b45309'], 'draft' => ['Draft', '#f1f5f9', '#475569']][$tp->status] ?? [$tp->status, '#f1f5f9', '#475569']; @endphp
        <span class="te-chip" style="background:{{ $chip[1] }};color:{{ $chip[2] }}">{{ $chip[0] }}</span>
      </div>
      <div class="te-sub">PNR <b>{{ $tp->ticket_no }}</b>{{ $sale ? ' · sold on ' . ($sale->invoice_no ?: '#' . $sale->id) : '' }}{{ $onFlight ? ' · on a contract flight' : '' }}</div>
    </div>
    <div class="right">
      <a href="{{ route('role.ticket-direct-sale.index', ['role' => $role]) }}" class="erp-btn btn-ghost"><i class="fas fa-list"></i> Ticket Orders</a>
    </div>
  </div>

  <form id="purchaseEditForm" action="{{ route('role.ticket-direct-sale.update', ['role' => $role, 'ticket_direct_sale' => $tp->id]) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div id="teFormError" class="te-form-error" hidden></div>
    <input type="hidden" name="ticket_type" value="{{ $tp->ticket_type ?: 'air' }}">
    <div class="te-grid">
      <div class="te-card">

        {{-- ── Traveller & route ── --}}
        <div class="te-zone">
          <div class="te-zone-h"><i class="fas fa-user"></i> Traveller &amp; route</div>
          <div class="te-row te-row-trav">
            <div>
              <label class="te-lbl">Passport holder <span class="req">*</span></label>
              <select name="passport_holder_id" required class="select2 te-in" style="width:100%">
                <option value="">— Select passenger —</option>
                @foreach ($passport_holders as $ph)
                  <option value="{{ $ph->id }}" @selected($tp->passport_holder_id == $ph->id)>{{ $ph->name }}{{ $ph->passport_no ? ' — ' . $ph->passport_no : '' }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="te-lbl">Ticket route <span class="req">*</span></label>
              <select name="ticket_id" required class="select2 te-in" style="width:100%">
                <option value="">— Select route —</option>
                @foreach ($tickets as $t)
                  <option value="{{ $t->id }}" @selected($tp->ticket_id == $t->id) data-ff="{{ strtolower($t->from_airport?->country?->code ?? '') }}" data-tf="{{ strtolower($t->to_airport?->country?->code ?? '') }}">{{ $t->title }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="te-lbl">Trip type <span class="req">*</span></label>
              <select name="trip_type" required class="select2 te-in" style="width:100%">
                <option value="one-way" @selected($tp->trip_type == 'one-way')>One-way</option>
                <option value="two-way" @selected($tp->trip_type == 'two-way')>Round Trip</option>
                <option value="multi-city" @selected($tp->trip_type == 'multi-city')>Multi-city</option>
              </select>
            </div>
            <div>
              <label class="te-lbl">Class <span class="req">*</span></label>
              <select name="ticket_class" required class="select2 te-in" style="width:100%">
                <option value="economy" @selected($tp->ticket_class == 'economy')>Economy</option>
                <option value="business" @selected($tp->ticket_class == 'business')>Business</option>
                <option value="first" @selected($tp->ticket_class == 'first')>First Class</option>
              </select>
            </div>
          </div>
        </div>

        {{-- ── Ticket & seller ── --}}
        <div class="te-zone z-ticket">
          <div class="te-zone-h"><i class="fas fa-ticket-alt"></i> Ticket &amp; seller</div>
          <div class="te-row">
            <div>
              <label class="te-lbl">PNR <span class="req">*</span></label>
              <input type="text" name="ticket_no" required value="{{ $tp->ticket_no }}" class="te-in te-pnr">
            </div>
            <div>
              <label class="te-lbl">E-ticket no.</label>
              <input type="text" name="name_ticket_no" maxlength="100" value="{{ $tp->name_ticket_no ?? '' }}" placeholder="e.g. 072-2345678901" class="te-in">
            </div>
            <div>
              <label class="te-lbl">Issue date <span class="req">*</span></label>
              <input type="date" name="purchase_date" required value="{{ \Carbon\Carbon::parse($tp->purchase_date)->format('Y-m-d') }}" class="te-in">
            </div>
          </div>
          <div class="te-row">
            <div>
              <label class="te-lbl">Validating carrier</label>
              <select name="validating_carrier_id" class="select2 te-in" style="width:100%">
                <option value="">— Airline that issues —</option>
                @foreach ($airlines as $al)
                  <option value="{{ $al->id }}" @selected(($tp->validating_carrier_id ?? null) == $al->id)>{{ $al->name }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="te-lbl">Vendor</label>
              <select name="vendor_id" id="teVendor" class="select2 te-in" style="width:100%">
                <option value="">— Select vendor —</option>
                @foreach ($vendors as $v)
                  <option value="{{ $v->id }}" @selected($tp->vendor_id == $v->id)>{{ $v->name }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="te-lbl">Portal</label>
              <select name="portal_id" id="tePortal" class="select2 te-in" style="width:100%">
                <option value="">— Select portal —</option>
                @foreach ($portals as $p)
                  <option value="{{ $p->id }}" @selected($tp->portal_id == $p->id)>{{ $p->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>

        {{-- ── Flight ── --}}
        <div class="te-zone z-flight">
          <div class="te-zone-h"><i class="fas fa-plane-departure"></i> Flight</div>
          <div class="te-row te-row-flight">
            <div>
              <label class="te-lbl">Flight no.</label>
              <input type="text" name="flight_no" maxlength="20" value="{{ $tp->flight_no ?? '' }}" class="te-in" style="text-transform:uppercase" placeholder="GF 249">
            </div>
            <div class="te-pair">
              <div><label class="te-lbl">Departure</label><input type="text" data-dt name="departure_at" value="{{ $dt($tp->departure_at ?? null) }}" class="te-in"></div>
              <div><label class="te-lbl">Arrival</label><input type="text" data-dt name="arrival_at" value="{{ $dt($tp->arrival_at ?? null) }}" class="te-in"></div>
            </div>
            <div>
              <label class="te-lbl">RBD</label>
              <input type="text" name="rbd" maxlength="4" value="{{ $tp->rbd ?? '' }}" class="te-in" style="text-transform:uppercase;text-align:center;font-weight:700" placeholder="Y">
            </div>
            <div>
              @if(\App\Services\TicketBaggage::ready())
              <label class="te-lbl">Free baggage KG</label>
              <input type="number" step="0.01" min="0" name="free_baggage_kg" value="{{ $tp->free_baggage_kg }}" class="te-in">
              @if($tp->baggage)<small style="color:#64748b">Recorded allowance: {{ $tp->baggage }}</small>@endif
              @else
              <label class="te-lbl">Baggage</label>
              <input type="text" name="baggage" maxlength="50" value="{{ $tp->baggage ?? '' }}" class="te-in">
              @endif
            </div>
          </div>
        </div>

        {{-- ── Fare & payment ── --}}
        @include('dashboard.ticket_direct_sales.partials.baggage-fields', ['purchase' => $tp])
        <div class="te-zone z-fare">
          <div class="te-zone-h"><i class="fas fa-coins"></i> Fare &amp; payment</div>
          <div class="te-row" style="--c:6">
            <div class="te-pair s2" style="grid-column:span 2">
              <div><label class="te-lbl">Base fare</label><input type="number" step="0.01" min="0" name="base_fare" value="{{ $money($tp->base_fare ?? null) }}" class="te-in te-num" id="teFare" placeholder="0.00"></div>
              <div><label class="te-lbl">Taxes</label><input type="number" step="0.01" min="0" name="tax_amount" value="{{ $money($tp->tax_amount ?? null) }}" class="te-in te-num" id="teTax" placeholder="0.00"></div>
            </div>
            <div class="te-pair s2" style="grid-column:span 2">
              <div><label class="te-lbl">Comm. %</label><input type="number" step="0.0001" min="0" max="100" name="commission_pct" value="{{ $tp->commission_pct !== null ? (float) $tp->commission_pct : '' }}" class="te-in te-num" id="tePct" placeholder="0"></div>
              <div><label class="te-lbl">Comm. ৳</label><input type="number" step="0.01" min="0" name="commission_amount" value="{{ $money($tp->commission_amount ?? null) }}" class="te-in te-num" id="teCom" placeholder="0.00"></div>
            </div>
            <div class="s2">
              <label class="te-lbl">Tax codes</label>
              <input type="text" name="tax_breakdown" maxlength="255" value="{{ $tp->tax_breakdown ?? '' }}" class="te-in" id="teTaxCodes" placeholder="YQ 12000, BD 500">
            </div>
          </div>
          <div class="te-row">
            <div>
              <label class="te-lbl">Net cost <span class="req">*</span></label>
              <input type="number" name="amount" required step="0.01" value="{{ $money($tp->amount) }}" class="te-in te-num te-net" id="teCost">
            </div>
            <div>
              <label class="te-lbl">Agent price <span id="teSaleRequired" class="req" hidden>*</span></label>
              <input type="number" name="sale_price" step="0.01" min="0" value="{{ $saleItem ? $money($saleItem->price) : '' }}" class="te-in te-num" id="teSale" @disabled($onFlight)>
            </div>
            <div>
              <label class="te-lbl">Add payment</label>
              <input type="number" name="paid_amount" step="0.01" min="0" value="" placeholder="0.00" class="te-in te-num" id="tePay" @disabled($tp->status === 'cancelled')>
              <span class="te-hint g">Already paid ৳{{ number_format((float) $tp->paid_amount, 2) }}</span>
            </div>
          </div>
          <div class="te-row">
            <div>
              <label class="te-lbl">Profit</label>
              <input type="text" readonly tabindex="-1" class="te-in te-profit" id="teProfit">
            </div>
            <div>
              <label class="te-lbl">Cost due date</label>
              <input type="date" name="cost_due_date" value="{{ optional($tp->schedules()->whereIn('status', \App\Models\PaymentSchedule::OPEN_STATUSES)->first())->scheduled_date }}" class="te-in">
            </div>
            <div>
              <label class="te-lbl">Replace attachment</label>
              <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="te-in" style="padding-top:5px">
              @if($tp->attachment)<a class="te-hint" href="{{ \Illuminate\Support\Facades\Storage::url($tp->attachment) }}" target="_blank" style="color:#2563eb">View current attachment</a>@endif
            </div>
          </div>
          <div class="te-row">
            <div>
              <label class="te-lbl">Sold to <span id="teBuyerRequired" class="req" hidden>*</span></label>
              <select name="sale_client_id" id="teBuyer" class="select2 te-in" style="width:100%" @disabled($onFlight)>
                <option value="">— Not sold yet —</option>
                @include('dashboard.ticket_direct_sales.partials.buyer-options')
              </select>
            </div>
            <div>
              <label class="te-lbl">Cost bank</label>
              <select name="bank_id" class="select2 te-in" style="width:100%">
                <option value="">— Select bank —</option>
                @include('partials.bank-options', ['banks' => $banks, 'selected' => $tp->bank_id])
              </select>
            </div>
            <div>
              <label class="te-lbl">Payment method</label>
              <select name="payment_method" class="select2 te-in" style="width:100%">
                @foreach (['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'mobile_banking' => 'Mobile Banking', 'advance' => 'Advance', 'other' => 'Other'] as $k => $l)
                  <option value="{{ $k }}">{{ $l }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="te-row">
            <div>
              <label class="te-lbl">Status <span class="req">*</span></label>
              <select name="status" id="teStatus" required class="select2 te-in" style="width:100%">
                <option value="confirm" @selected($tp->status === 'confirm')>Purchased</option>
                <option value="pending" @selected($tp->status === 'pending')>Pending</option>
                <option value="draft" @selected($tp->status === 'draft')>Draft</option>
                <option value="cancelled" @selected($tp->status === 'cancelled')>Cancelled</option>
              </select>
            </div>
            <div>
              <label class="te-lbl">Currency <span class="req">*</span></label>
              <input type="text" name="currency" required value="{{ $tp->currency ?? 'BDT' }}" class="te-in" maxlength="5">
            </div>
            <div id="teRefundBox" style="display:none">
              <label class="te-lbl">Airline refund</label>
              <input type="number" step="0.01" min="0" max="{{ (float) $tp->amount }}" name="refund_amount" id="teRefund" value="{{ $money($tp->amount) }}" class="te-in te-num">
              <span class="te-hint">Cost ৳{{ number_format((float) $tp->amount, 2) }} · penalty = cost − refund</span>
            </div>
          </div>
          @if($tp->status === 'cancelled')
            <div class="te-note bad"><i class="fas fa-ban"></i><span>Cancelled{{ $tp->refund_amount !== null ? ' — refund ৳' . number_format((float) $tp->refund_amount, 2) . ', penalty ৳' . number_format((float) $tp->amount - (float) $tp->refund_amount, 2) : '' }}. It cannot be reopened; Net Cost may be corrected but cannot be lower than the posted refund.</span></div>
          @endif
          @if($onFlight)
            <div class="te-note info"><i class="fas fa-plane"></i><span>This ticket is on a contract flight — the agent is billed per head on the flight, so it is not sold here.</span></div>
          @elseif($sale && $orig['others'] > 0)
            <div class="te-note info"><i class="fas fa-file-invoice"></i><span>Sold on invoice {{ $sale->invoice_no ?: '#' . $sale->id }} with {{ $orig['others'] }} other ticket{{ $orig['others'] > 1 ? 's' : '' }}. A new price re-prices only this one; changing the buyer is done in Manage Sales.</span></div>
          @endif
        </div>

        {{-- ── Route legs ── --}}
        <div class="te-zone z-legs">
          <div class="te-zone-h"><i class="fas fa-route"></i> Route legs <small style="text-transform:none;letter-spacing:0;font-weight:500;color:#9a6b4a">— optional, sectors priced per segment</small>
            <button type="button" class="te-add te-zone-act" onclick="teAddLeg()">+ Add leg</button></div>
          <div id="legsContainer"></div>
        </div>
      </div>

      {{-- ── Summary rail ── --}}
      <aside class="te-rail">
        <div class="te-rail-card">
          <div class="te-rail-h"><i class="fas fa-receipt"></i> Summary</div>
          <div class="te-rr"><span>Base fare</span><b id="sFare">৳0.00</b></div>
          <div class="te-rr"><span>Taxes</span><b id="sTax">৳0.00</b></div>
          <div class="te-rr"><span>Commission</span><b id="sCom" class="g">৳0.00</b></div>
          <div class="te-rr strong"><span>Net cost</span><b id="sCost">৳0.00</b></div>
          <div class="te-rr"><span>Agent price</span><b id="sSale">—</b></div>
          <div class="te-rr strong"><span>Profit</span><b id="sProfit">—</b></div>
          <div class="te-sep"></div>
          <div class="te-rr"><span>Paid</span><b id="sPaid">৳0.00</b></div>
          <div class="te-rr"><span>Due to seller</span><b id="sDue" class="r">৳0.00</b></div>
          <div class="te-post" id="tePost"></div>
          <div class="te-act">
            <button id="submitEditBtn" type="button" class="erp-btn btn-green-s">💾 Update Purchase</button>
            <a href="{{ route('role.ticket-direct-sale.index', ['role' => $role]) }}" class="erp-btn btn-ghost">✕ Cancel</a>
          </div>
        </div>
        @if(\App\Support\UserRoleSetting\Access::allows('travels.ticketing', 'd') && \Illuminate\Support\Facades\Route::has('role.ticket-purge.preview'))
        {{-- Delete wrong entry (2026-10-12): this ticket, the invoice it is on (with its other tickets),
             its changes and every ledger / journal posting. Preview first; DELETE must be typed. --}}
        <button type="button" class="te-purge" data-ticket-purge="{{ $ticketPurchase->id }}" data-purge-done="{{ route('role.ticket-direct-sale.index', ['role' => $role]) }}"
                title="Removes this ticket, its invoice, changes and every ledger / journal posting">
          <i class="fas fa-trash"></i> Delete wrong entry
        </button>
        @endif
      </aside>
    </div>
  </form>
</div>
@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const TE = @json($orig);
const teNum = v => parseFloat(v) || 0;
const teTk = n => '৳' + (Number(n) || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const teEsc = t => String(t ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
const $id = id => document.getElementById(id);
const teText = sel => { const o = sel?.options?.[sel.selectedIndex]; return o && o.value ? o.text.trim() : ''; };

$(function () {
  $('.select2').select2({ width: '100%' });
  $('#teBuyer').val(TE.buyer).trigger('change.select2');
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
  @php
      // travel_date has no Carbon cast on TicketLeg — a plain string.
      $existingLegsForJs = $ticketPurchase->legs->map(fn ($leg) => [
          'from_location' => $leg->from_location, 'to_location' => $leg->to_location,
          'travel_date' => $leg->travel_date, 'cost' => $leg->cost,
      ]);
  @endphp
  (@json($existingLegsForJs)).forEach(teAddLeg);
  $('#teStatus').on('change', teRecalc);
  $('#teVendor, #tePortal, #teBuyer, [name="bank_id"]').on('change', teRecalc);
  $(document).on('input', '#purchaseEditForm input', teRecalc);
  teRecalc();
});

// % and ৳ fill each other; the fare build-up works out the net cost (same as Ticket Entry)
function teFare(el) {
  const fare = teNum($id('teFare').value), tax = teNum($id('teTax').value), pctEl = $id('tePct'), com = $id('teCom');
  if (el === com) pctEl.value = com.value !== '' && fare ? String(+(teNum(com.value) / fare * 100).toFixed(4)) : (com.value === '' ? '' : pctEl.value);
  else if (teNum(pctEl.value)) com.value = (fare * teNum(pctEl.value) / 100).toFixed(2);
  else if (el === pctEl) com.value = '';
  else if (com.value !== '' && fare) pctEl.value = String(+(teNum(com.value) / fare * 100).toFixed(4));
  pctEl.placeholder = !fare && com.value !== '' ? 'needs base fare' : '0';
  if ((fare || tax) && !$id('teCost').readOnly) $id('teCost').value = (fare + tax - teNum(com.value)).toFixed(2);
}
$(document).on('input', '#teFare, #teTax, #tePct, #teCom', function () { teFare(this); });
$(document).on('input', '#teTaxCodes', function () {
  let sum = 0, m; const re = /([A-Z0-9]{2})\s*[:=]?\s*(\d[\d,]*(?:\.\d+)?)/gi;
  while ((m = re.exec(this.value)) !== null) sum += teNum(m[2].replace(/,/g, ''));
  if (sum) { $id('teTax').value = sum.toFixed(2); teFare($id('teTax')); }
});

function teRecalc() {
  const st = $id('teStatus').value, cancel = st === 'cancelled';
  $('#teRefundBox').toggle(cancel && TE.status !== 'cancelled');
  const cost = teNum($id('teCost').value), com = teNum($id('teCom').value);
  const pay = cancel ? 0 : Math.min(teNum($id('tePay').value), Math.max(0, cost - TE.paid));
  const priceRaw = $id('teSale').value, buyer = $id('teBuyer').value, priced = priceRaw !== '' && !$id('teSale').disabled;
  const baggage = baggageAmounts(document.getElementById('purchaseEditForm'));
  const price = teNum(priceRaw), profit = price - cost + baggage.profit;
  const set = (i, v, c) => { const e = $id(i); e.textContent = v; if (c !== undefined) e.className = c; };
  set('sFare', teTk($id('teFare').value)); set('sTax', teTk($id('teTax').value)); set('sCom', teTk(com), 'g'); set('sCost', teTk(cost + baggage.cost));
  set('sSale', priced || baggage.charge ? teTk((priced ? price : 0) + baggage.charge) : '—');
  const totalProfit = (priced ? price - cost : 0) + baggage.profit;
  set('sProfit', priced || baggage.cost || baggage.charge ? teTk(totalProfit) : '—', totalProfit < 0 ? 'r' : 'g');
  const baggagePaid = $id('tePortal').value && !$id('teVendor').value ? baggage.cost : 0;
  set('sPaid', teTk(TE.paid + pay + baggagePaid)); set('sDue', teTk(Math.max(0, cost + baggage.cost - TE.paid - pay - baggagePaid)), cost + baggage.cost - TE.paid - pay - baggagePaid > 0 ? 'r' : '');
  const pf = $id('teProfit'); pf.value = priced ? profit.toFixed(2) : ''; pf.classList.toggle('pos', priced && profit > 0); pf.classList.toggle('neg', priced && profit < 0);

  // What this save will change — compared with what is on the books now.
  const L = [], row = (l, a, c, tip) => L.push(`<div class="te-pl"${tip ? ` title="${teEsc(tip)}"` : ''}><span>${l}</span><b class="${c || ''}">${a}</b></div>`);
  const warn = t => L.push(`<div class="te-pw">${t}</div>`);
  if (baggage.cost) row('Extra baggage cost', teTk(baggage.cost), 'r');
  if (baggage.charge) row('Baggage charged to ticket buyer', teTk(baggage.charge), 'r');
  if (baggage.cost || baggage.charge) row('Baggage profit', teTk(baggage.profit), baggage.profit < 0 ? 'r' : 'g');
  const seller = teText($id('teVendor')) || teText($id('tePortal')) || 'the seller';
  if (cancel && TE.status !== 'cancelled') {
    const refund = Math.min(teNum($id('teRefund').value), TE.cost);
    row('Ticket cancelled — kept at', teTk(TE.cost), '', 'Nothing is deleted; a reversal is added');
    row('Refund due from ' + teEsc(seller), teTk(refund), 'g', 'Seller ledger Cr · Dr Payable / Cr 5110 Ticket Cost');
    if (TE.cost - refund > 0) row('Airline keeps (penalty)', teTk(TE.cost - refund), 'r', 'Stays in 5110 Ticket Cost');
    if (TE.buyer) warn('The agent\'s sale is not reversed here — use Ticket Orders → Change → Refund / Void for that.');
  } else if (TE.status === 'cancelled') {
    L.push('<div class="te-none">Cancelled ticket — only its details can change, no money.</div>');
  } else if (st !== 'confirm') {
    L.push('<div class="te-none">Pending / Draft — the cost stays on the books as recorded; nothing new is posted.</div>');
  } else {
    if (Math.abs(cost - TE.cost) >= 0.01) row('Seller bill ' + (cost > TE.cost ? 'up' : 'down'), (cost > TE.cost ? '+' : '−') + teTk(Math.abs(cost - TE.cost)), cost > TE.cost ? 'r' : 'g', 'Bill re-synced · Dr 5110 Ticket Cost / Cr Payable');
    if (pay > 0) row('Pay ' + teEsc(seller) + ' now', teTk(pay), '', 'Dr Payable / Cr ' + (teText(document.querySelector('[name="bank_id"]')) || 'bank or portal'));
    if (Math.abs(com - TE.com) >= 0.01) row('Commission income ' + (com > TE.com ? 'added' : 'reduced'), (com > TE.com ? '+' : '−') + teTk(Math.abs(com - TE.com)), com > TE.com ? 'g' : 'r', 'Difference posted · Dr 5110 / Cr 4171 IATA Air Ticket Commission');
    if (priced || TE.buyer) {
      const who = teText($id('teBuyer'));
      if (!TE.buyer && priced && buyer) row('Bill to ' + teEsc(who) + ' (new sale)', teTk(price), '', 'Agent ledger · Dr 1311 Receivable / Cr 4110 Air Ticket Sales');
      else if (!TE.buyer && priced) warn('Pick "Sold to" to bill the agent.');
      else if (TE.buyer && (!priced || !buyer)) warn('Sold on ' + teEsc(TE.invoice || 'an invoice') + ' — take it off the sale in Manage Sales, not here.');
      else if (buyer !== TE.buyer && TE.others > 0) warn('That invoice holds other tickets — change the buyer from Manage Sales.');
      else if (buyer !== TE.buyer) row('Invoice ' + teEsc(TE.invoice || '') + ' moves to ' + teEsc(who), teTk(price), '', 'Ledger rows move to the new buyer');
      else if (Math.abs(price - (TE.price ?? 0)) >= 0.01) row('Agent price ' + (price > TE.price ? 'up' : 'down'), (price > TE.price ? '+' : '−') + teTk(Math.abs(price - TE.price)), '', 'Invoice ' + (TE.invoice || '') + ' re-priced · Dr 1311 / Cr 4110');
    }
    if (!L.length) L.push('<div class="te-none">No money changes — details only.</div>');
  }
  $id('tePost').innerHTML = '<h4>What will change</h4>' + L.join('');
  teSyncSaleRequired();
}

function teSyncSaleRequired() {
  const priceEntered = $id('teSale').value !== '';
  const buyerSelected = $id('teBuyer').value !== '';
  $id('teBuyerRequired').hidden = !priceEntered;
  $id('teSaleRequired').hidden = !buyerSelected;
  $id('teBuyer').required = priceEntered;
  $id('teSale').required = buyerSelected;
}

function teClearFieldError(field) {
  if (!field) return;
  field.classList.remove('te-invalid');
  $(field).next('.select2-container').removeClass('te-invalid');
  field.closest('.te-row > div, .te-pair > div')?.querySelector('.te-error')?.remove();
}

function teShowFieldError(field, message) {
  if (!field) return;
  teClearFieldError(field);
  field.classList.add('te-invalid');
  if ($(field).hasClass('select2-hidden-accessible')) $(field).next('.select2-container').addClass('te-invalid');
  const error = document.createElement('small');
  error.className = 'te-error';
  error.textContent = message;
  (field.closest('.te-row > div, .te-pair > div') || field.parentElement).appendChild(error);
}

function teSaleValidation() {
  teSyncSaleRequired();
  const buyer = $id('teBuyer'), price = $id('teSale');
  teClearFieldError(buyer); teClearFieldError(price);
  if (price.value !== '' && !buyer.value) {
    teShowFieldError(buyer, 'Select who this ticket is sold to.');
    return buyer;
  }
  if (buyer.value && price.value === '') {
    teShowFieldError(price, 'Enter the agent price for this buyer.');
    return price;
  }
  return null;
}

function teShowServerErrors(errors) {
  let first = null;
  Object.entries(errors || {}).forEach(([key, messages]) => {
    const name = key.split('.').reduce((value, part, index) => index ? `${value}[${part}]` : part, '');
    const field = $id('purchaseEditForm').elements.namedItem(name);
    if (!field) return;
    teShowFieldError(field, Array.isArray(messages) ? messages[0] : messages);
    if (!first) first = field;
  });
  if (first) {
    first.scrollIntoView({ behavior:'smooth', block:'center' });
    setTimeout(() => $(first).hasClass('select2-hidden-accessible') ? $(first).select2('open') : first.focus({preventScroll:true}), 300);
  }
}

function teShowGeneralError(message) {
  const box = $id('teFormError');
  box.textContent = message || 'The purchase could not be updated.';
  box.hidden = false;
  box.scrollIntoView({ behavior:'smooth', block:'center' });
}

$(document).on('input change', '#teSale, #teBuyer', function () {
  teClearFieldError(this);
  teSyncSaleRequired();
});

// ── Route legs ──
let teLegIdx = 0;
function teAddLeg(prefill) {
  prefill = prefill || {}; const i = teLegIdx++;
  const r = document.createElement('div'); r.className = 'te-leg'; r.dataset.legIndex = i;
  r.innerHTML = `<div><label class="te-lbl">From</label><input type="text" name="legs[${i}][from_location]" class="te-in" placeholder="DAC" value="${teEsc(prefill.from_location || '')}"></div>
    <div><label class="te-lbl">To</label><input type="text" name="legs[${i}][to_location]" class="te-in" placeholder="IST" value="${teEsc(prefill.to_location || '')}"></div>
    <div><label class="te-lbl">Travel date</label><input type="text" data-dt name="legs[${i}][travel_date]" class="te-in" value="${teEsc(prefill.travel_date || '')}"></div>
    <div><label class="te-lbl">Cost (৳)</label><input type="number" min="0" step="0.01" name="legs[${i}][cost]" class="te-in te-num" value="${prefill.cost ?? ''}"></div>
    <button type="button" class="te-leg-del" onclick="this.closest('.te-leg').remove()" title="Remove leg"><i class="fas fa-trash"></i></button>`;
  $id('legsContainer').appendChild(r);
  if (window.epalDtInit) try { window.epalDtInit(r); } catch (e) {}
}

$('#submitEditBtn').on('click', function (e) {
  e.preventDefault();
  $id('teFormError').hidden = true;
  const invalidSaleField = teSaleValidation();
  if (invalidSaleField) {
    invalidSaleField.scrollIntoView({ behavior:'smooth', block:'center' });
    setTimeout(() => $(invalidSaleField).hasClass('select2-hidden-accessible') ? $(invalidSaleField).select2('open') : invalidSaleField.focus({preventScroll:true}), 300);
    return;
  }
  const $btn = $(this);
  $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
  const fd = new FormData($id('purchaseEditForm'));
  fd.append('_method', 'PUT');
  // a disabled field is not posted — send the buyer/price keys anyway so the server knows the page had them
  if (!fd.has('sale_client_id')) fd.append('sale_client_id', '');
  if (!fd.has('sale_price')) fd.append('sale_price', '');
  $.ajax({
    url: $id('purchaseEditForm').action, method: 'POST', data: fd, processData: false, contentType: false,
    success: function (r) {
      if (r.success) {
        $btn.html('<i class="fas fa-check"></i> Saved');
        window.location.href = @json(route('role.ticket-direct-sale.index', ['role' => $role]));
      } else {
        $btn.prop('disabled', false).html('💾 Update Purchase');
        if (r.errors) teShowServerErrors(r.errors);
        else teShowGeneralError(r.message);
      }
    },
    error: function (xhr) {
      $btn.prop('disabled', false).html('💾 Update Purchase');
      if (xhr.status === 422 && xhr.responseJSON?.errors) {
        teShowServerErrors(xhr.responseJSON.errors);
        return;
      }
      console.error(xhr.responseText);
      teShowGeneralError(xhr.responseJSON?.message || 'Failed to update purchase.');
    }
  });
});
</script>
@include('partials.datetime-picker'){{-- date + optional time on operational date boxes (2026-09-30) --}}
@if(\App\Support\UserRoleSetting\Access::allows('travels.ticketing', 'd') && \Illuminate\Support\Facades\Route::has('role.ticket-purge.preview'))
<style>
  .te-purge{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;margin-top:12px;padding:10px 14px;border-radius:10px;border:1px solid #fecaca;background:#fff5f5;color:#dc2626;font-size:13px;font-weight:700;cursor:pointer;transition:background .15s,border-color .15s;}
  .te-purge:hover{background:#fee2e2;border-color:#fca5a5;}
  .te-purge:disabled{opacity:.6;cursor:wait;}
</style>
@include('dashboard.ticket_direct_sales.partials.ticket-purge-script')
@endif
@include('dashboard.ticket_direct_sales.partials.baggage-script')
@endsection
