@extends('layout.app')
@section('meta-information')
    <title>Manage Ticket Purchases</title>
@endsection
@section('css')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
:root {
  --border:#e4e8f0; --border2:#d0d6e8;
  --text:#1a2035; --text2:#5a6480; --text3:#9aa3bc;
  --accent:#2563eb; --accent2:#1d4ed8; --accent-light:#eff6ff;
  --shadow:0 1px 4px rgba(30,50,100,.07);
  --shadow2:0 4px 16px rgba(30,50,100,.10);
}
body { font-family:'DM Sans',sans-serif; }

/* Page header */
.tv-page-head{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;margin-bottom:14px;box-shadow:var(--shadow);}
.tv-page-logo{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#5b21b6,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.tv-page-title{font-size:18px;font-weight:700;color:var(--text);}
.tv-page-sub{font-size:12px;color:var(--text2);margin-top:1px;}
.tv-page-actions{margin-left:auto;}
.erp-btn{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none;}
.btn-purple{background:linear-gradient(135deg,#5b21b6,#7c3aed);color:#fff;}
.btn-purple:hover{background:linear-gradient(135deg,#4c1d95,#6d28d9);color:#fff;}
.btn-ghost-sm{background:#f0f2f8;color:var(--text2);border:1px solid var(--border);padding:5px 10px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:4px;text-decoration:none;}
.btn-ghost-sm:hover{border-color:var(--border2);color:var(--text);}
.btn-edit-sm{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;text-decoration:none;}
.btn-edit-sm:hover{background:#dbeafe;color:#1d4ed8;}
.btn-del-sm{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;}
.btn-del-sm:hover{background:#fee2e2;}
.btn-legs-sm{background:#f0fdfa;color:#0f766e;border:1px solid #99f6e4;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;}
.btn-legs-sm:hover{background:#ccfbf1;}

/* Card wrapper */
.tk-page-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow2);}
.tk-card-head{background:linear-gradient(90deg,#1e3a8a 0%,#1e40af 100%);padding:14px 20px;display:flex;align-items:center;justify-content:space-between;}
.tk-card-head h2{color:#fff;font-size:15px;font-weight:700;margin:0;}

/* Filter */
.tk-filter{border-bottom:1px solid var(--border);}
.tk-filter-header{padding:13px 20px;background:#f8fafc;cursor:pointer;display:flex;justify-content:space-between;align-items:center;border-left:4px solid #7c3aed;transition:background .2s;}
.tk-filter-header:hover{background:#f1f3f9;}
.tk-filter-header h3{margin:0;font-size:13.5px;font-weight:700;color:var(--text);}
.tk-filter-header .toggle-icon{transition:transform .3s;color:var(--text2);}
.tk-filter-header.active .toggle-icon{transform:rotate(180deg);}
.tk-filter-body{background:#fff;max-height:0;overflow:hidden;transition:max-height .3s ease-out,padding .3s ease-out;}
.tk-filter-body.active{padding:16px 20px;max-height:480px;}
.tk-filter-row{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:12px;}
.tk-filter-group{flex:1;min-width:180px;}
.tk-filter-group label{display:block;margin-bottom:5px;font-size:11px;font-weight:600;color:var(--text2);letter-spacing:.2px;}
.tk-filter-group select,.tk-filter-group input{box-sizing:border-box;width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .12s;color:var(--text);background:#fff;}
.tk-filter-group select:focus,.tk-filter-group input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);}
.tk-filter-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:6px;}
.tk-filter-apply{padding:8px 18px;background:var(--accent);color:#fff;border:none;border-radius:7px;font-size:12.5px;font-weight:600;cursor:pointer;transition:background .15s;font-family:'DM Sans',sans-serif;}
.tk-filter-apply:hover{background:var(--accent2);}
.tk-filter-reset{padding:8px 18px;background:#f0f2f8;color:var(--text2);border:1px solid var(--border);border-radius:7px;font-size:12.5px;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif;}
.tk-filter-reset:hover{background:#e5e7eb;}

/* Select2 inside filter */
.tk-filter-body .select2-container{max-width:100%;}
.tk-filter-body .select2-container .select2-selection--single{height:38px;border:1px solid var(--border);border-radius:8px;}
.tk-filter-body .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:38px;padding-left:12px;font-size:13px;color:var(--text);}
.tk-filter-body .select2-container--default .select2-selection--single .select2-selection__arrow{height:36px;}
.tk-filter-body .select2-container--default.select2-container--focus .select2-selection--single{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);}

/* Table */
.tk-table-wrap{overflow-x:auto;padding:0;}
.tk-table{width:100%;border-collapse:collapse;font-size:13px;}
.tk-table thead th{background:#f8fafc;padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid var(--border);white-space:nowrap;}
.tk-table thead th:first-child{padding-left:20px;}
.tk-table tbody td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text);}
.tk-table tbody td:first-child{padding-left:20px;}
.tk-table tbody tr:hover{background:#fafbff;}
.tk-table tbody tr:last-child td{border-bottom:none;}
.tk-row-title{font-weight:600;color:var(--text);font-size:13px;}
.tk-row-sub{font-size:11px;color:var(--text2);margin-top:2px;}
.tk-mono{font-family:'DM Mono',monospace;font-size:12.5px;}
.tk-chip{display:inline-flex;align-items:center;gap:4px;border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700;letter-spacing:.3px;white-space:nowrap;}
.tk-chip-type{background:#ede9fe;color:#5b21b6;}
.tk-chip-trip{background:#e0f2fe;color:#075985;}
.tk-amt{font-family:'DM Mono',monospace;font-size:12.5px;line-height:1.6;white-space:nowrap;}
.tk-amt .lbl{font-family:'DM Sans',sans-serif;font-size:11px;color:var(--text2);display:inline-block;min-width:36px;}
.tk-amt .paid{color:#15803d;}
.tk-amt .due{color:#dc2626;}
.tk-amt .total{color:var(--text);font-weight:600;}
.tk-st{border-radius:6px;padding:3px 9px;font-size:11px;font-weight:700;display:inline-block;border:1px solid transparent;white-space:nowrap;}
.tk-st-draft{background:#f3f4f6;color:#374151;border-color:#d1d5db;}
.tk-st-pending{background:#fef9c3;color:#854d0e;border-color:#fde68a;}
.tk-st-confirm{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
.tk-st-cancelled{background:#fee2e2;color:#991b1b;border-color:#fecaca;}
.tk-actions{display:flex;gap:6px;}
.tk-empty{text-align:center;padding:48px 20px;color:var(--text2);}
.tk-empty i{font-size:2.5rem;opacity:.35;margin-bottom:12px;display:block;}
.tk-empty h4{font-size:15px;font-weight:600;margin-bottom:4px;color:var(--text);}
.tk-empty p{font-size:12.5px;}

/* Pagination */
.tk-pagination{padding:12px 20px;border-top:1px solid var(--border);background:#fafbff;}
span [aria-current="page"] span{background-color:#2563eb !important;color:white;border-color:#2563eb;}

/* Delete + Route Legs modals are Tailwind-built; they only need the backdrop. */
.modal{transition:opacity .25s ease;}
.modal-backdrop{background-color:rgba(0,0,0,.5);}
</style>
@endsection
@section('main-content')

<div style="padding:0 0 20px;">

  {{-- Page header --}}
  <div class="tv-page-head">
    <div class="tv-page-logo"><i class="fas fa-shopping-cart"></i></div>
    <div>
      <div class="tv-page-title">Ticket Purchases</div>
      <div class="tv-page-sub">Tickets bought from vendors, one per passenger, with payment status</div>
    </div>
    <div class="tv-page-actions">
      <a href="{{ route('role.ticket-purchase.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="erp-btn btn-purple create-new-btn">
        <i class="fas fa-plus"></i> Add New Ticket Purchase
      </a>
    </div>
  </div>

  {{-- Main card --}}
  <div class="tk-page-card">

    {{-- Card header --}}
    <div class="tk-card-head">
      <h2><i class="fas fa-list" style="margin-right:8px;opacity:.8;"></i>Ticket Purchase List</h2>
    </div>

    {{-- Filter --}}
    <div class="tk-filter">
      <form action="" method="get" id="filterForm">
        {{-- The Manage Banks filter bar. Ticket-no search and the
             purchase-date range live here; the selects stay in the
             collapsible panel below and travel with the same submit. --}}
        <x-list-filter-bar search="ticket_no" placeholder="Search ticket no…"
                           :reset="route('role.ticket-purchase.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])"
                           :total="$datas->total()" />
        <div class="tk-filter-header" id="tkFilterHeader">
          <h3><i class="fas fa-filter" style="margin-right:8px;color:#7c3aed;"></i>Filter Options</h3>
          <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="tk-filter-body" id="tkFilterBody">
          <div class="tk-filter-row">
            <div class="tk-filter-group">
              <label for="passport_holder_id">Passport Holders</label>
              <select id="passport_holder_id" name="passport_holder_id" class="select2" style="width:100%">
                <option value="">All</option>
                @foreach ($passport_holders as $passport_holder)
                  <option value="{{ $passport_holder->id }}" {{ $passport_holder->id == request('passport_holder_id') ? 'selected' : '' }}>{{ $passport_holder->name }}</option>
                @endforeach
              </select>
            </div>
            {{-- <div class="tk-filter-group">
              <label for="bank_id">Bank</label>
              <select id="bank_id" name="bank_id" class="select2" style="width:100%">
                <option value="">All</option>
                @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
              </select>
            </div> --}}
            <div class="tk-filter-group">
              <label for="ticket_id">Ticket</label>
              <select id="ticket_id" name="ticket_id" class="select2" style="width:100%">
                <option value="">All</option>
                @foreach ($tickets as $ticket)
                  <option value="{{ $ticket->id }}" {{ $ticket->id == request('ticket_id') ? 'selected' : '' }}>{{ $ticket->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="vendor_id">Vendor</label>
              <select id="vendor_id" name="vendor_id" class="select2" style="width:100%">
                <option value="">All</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" {{ $vendor->id == request('vendor_id') ? 'selected' : '' }}>{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="portal_id">Portals</label>
              <select id="portal_id" name="portal_id" class="select2" style="width:100%">
                <option value="">All</option>
                @foreach ($portals as $portal)
                  <option value="{{ $portal->id }}" {{ $portal->id == request('portal_id') ? 'selected' : '' }}>{{ $portal->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="ticket_type">Ticket Type</label>
              <select id="ticket_type" name="ticket_type" class="select2" style="width:100%">
                <option value="">All</option>
                <option value="air" {{ request('ticket_type') == 'air' ? 'selected' : '' }}>Air</option>
                <option value="bus" {{ request('ticket_type') == 'bus' ? 'selected' : '' }}>Bus</option>
                <option value="train" {{ request('ticket_type') == 'train' ? 'selected' : '' }}>Train</option>
                <option value="other" {{ request('ticket_type') == 'other' ? 'selected' : '' }}>Other</option>
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="trip_type">Trip Type</label>
              <select id="trip_type" name="trip_type" class="select2" style="width:100%">
                <option value="">All</option>
                <option value="one-way" {{ request('trip_type') == 'one-way' ? 'selected' : '' }}>One-way</option>
                <option value="two-way" {{ request('trip_type') == 'two-way' ? 'selected' : '' }}>Two-way</option>
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="created_by">Created By</label>
              <select id="created_by" name="created_by" class="select2" style="width:100%">
                <option value="">All</option>
                @foreach ($users as $user)
                  <option value="{{ $user->id }}" {{ $user->id == request('created_by') ? 'selected' : '' }}>{{ $user->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="status">Status</label>
              <select id="status" name="status" class="select2" style="width:100%">
                <option value="">All</option>
                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="confirm" {{ request('status') == 'confirm' ? 'selected' : '' }}>Confirm</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
              </select>
            </div>
          </div>
          <div class="tk-filter-actions">
            <button type="button" class="tk-filter-reset reset-btn">Reset</button>
            <button type="submit" class="tk-filter-apply">Apply Filters</button>
          </div>
        </div>
      </form>
    </div>

    {{-- Table --}}
    <div class="tk-table-wrap">
      <table class="tk-table">
        <thead>
          <tr>
            <th>SL</th>
            <th>Ticket No</th>
            <th>Passport Holder</th>
            <th>Vendor</th>
            <th>Ticket</th>
            <th>Trip Type</th>
            <th>Amount Info</th>
            <th>Status</th>
            <th style="width:1%;white-space:nowrap">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($datas as $key => $value)
          <tr>
            <td>
              <span class="tk-mono" style="color:var(--text2);">{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</span>
            </td>
            <td>
              <div class="tk-row-title">{{ $value->ticket_no }}</div>
              <div class="tk-row-sub tk-mono">{{ $value->purchase_date }}</div>
            </td>
            {{-- Passport Holder and Vendor deliberately wrap: they carry the
                 longest, most variable strings on the row, and nowrap gave the
                 old table a hard 1137px floor that clipped Actions on a laptop. --}}
            <td>{{ $value->passportHolder?->name ?? '—' }}</td>
            <td>{{ $value->vendor?->name ?? '—' }}</td>
            <td>
              <span class="tk-chip tk-chip-type">By {{ ucfirst($value->ticket_type) }}</span>
              <span style="margin-left:4px;">{{ $value->ticket?->title ?? '—' }}</span>
            </td>
            <td>
              <span class="tk-chip tk-chip-trip">{{ ucfirst($value->trip_type) }}</span>
            </td>
            <td>
              <div class="tk-amt">
                <div><span class="lbl">Paid</span> <span class="paid">{{ number_format((float) $value->paid_amount, 2) }}</span></div>
                <div><span class="lbl">Due</span> <span class="due">{{ number_format((float) $value->due_amount, 2) }}</span></div>
                <div><span class="lbl">Total</span> <span class="total">{{ number_format((float) $value->amount, 2) }}</span></div>
              </div>
            </td>
            <td>
              @php
                $stClass = in_array($value->status, ['draft', 'pending', 'confirm', 'cancelled'], true)
                    ? 'tk-st-' . $value->status
                    : 'tk-st-draft';
              @endphp
              <span class="tk-st {{ $stClass }}">{{ ucfirst($value->status) }}</span>
            </td>
            <td>
              <div class="tk-actions" style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>

                <a target="_blank" href="{{ route('role.ticket-purchase.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_purchase' => $value->id]) }}"
                   class="btn-ghost-sm" title="View Invoice" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                  <i class="fas fa-print" style="font-size:11px;margin:0"></i>
                </a>

                {{-- Route Legs: cost breakdown only, no ledger impact — available at every status,
                     including confirmed/cancelled, unlike the full Edit below. --}}
                @can('edit ticket purchase')
                <button type="button"
                        onclick="openLegsModal('{{ $value->id }}', '{{ $value->ticket_no }}')"
                        class="btn-legs-sm" title="Route Legs (multi-city cost)" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                  <i class="fas fa-route" style="font-size:11px;margin:0"></i>
                </button>
                @endcan

                {{-- Edit and Delete are live at every status (owner's decision,
                     2026-09-14). update() re-derives and re-posts everything the
                     purchase feeds; destroy() refuses only a PNR that is sold or
                     linked to a contract flight passenger, and says which. --}}
                @can('edit ticket purchase')
                <a href="{{ route('role.ticket-purchase.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_purchase' => $value->id]) }}"
                   class="btn-edit-sm edit-item-btn" title="Edit" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                  <i class="fas fa-edit" style="font-size:11px;margin:0"></i>
                </a>
                @endcan

                @can('delete ticket purchase')
                <button type="button" class="btn-del-sm"
                        onclick="confirmDelete('{{ $value->id }}', '{{ $value->ticket_no }}')"
                        title="Delete" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                  <i class="fas fa-trash" style="font-size:11px;margin:0"></i>
                </button>
                @endcan

              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="9">
              <div class="tk-empty">
                <i class="fas fa-inbox"></i>
                <h4>No ticket purchases found</h4>
                <p>Try adjusting your filters or add a new ticket purchase.</p>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    <div class="tk-pagination">
      {{ $datas->appends(request()->all())->links() }}
    </div>

  </div>
</div>

@include('dashboard.ticket_purchases.delete-modal')

<!-- Route Legs Modal -->
<div id="legsModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-2xl mx-auto rounded shadow-lg z-50 overflow-y-auto" style="max-height:85vh;">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Route Legs — <span id="legsTicketNo"></span></h3>
                <button class="modal-close-legs z-50"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-sm text-gray-500 mb-3">Break this ticket's itinerary into priced segments (e.g. DAC → BAH → IST). This is a cost breakdown only — it does not change the purchase's total amount, vendor, or ledger, so it can be edited even after the ticket is confirmed.</p>
                <input type="hidden" id="legsTicketPurchaseId">
                <div id="legsContainer" class="space-y-3 mb-3"></div>
                <button type="button" onclick="addLegRow()" class="btn btn-sm px-3 py-1.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-md hover:bg-blue-100 text-sm">
                    <i class="fas fa-plus mr-1"></i> Add Leg
                </button>
            </div>
            <div class="modal-footer flex justify-end pt-3 mt-2 border-t border-gray-200">
                <button class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-legs">
                    Cancel
                </button>
                <button id="legsSaveBtn" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Save Legs
                </button>
            </div>
        </div>
    </div>
</div>

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

        // The ✕ only (owner, 2026-09-22) — the backdrop no longer closes it.
        $('.modal-close-delete').click(function() {
            $('#deleteModal').addClass('hidden');
        });

        // ── Route Legs modal ────────────────────────────────────────────
        let legIndex = 0;

        function addLegRow(prefill) {
            prefill = prefill || {};
            const i = legIndex++;
            const row =
                '<div class="leg-row bg-gray-50 border border-gray-200 rounded-md p-3 grid grid-cols-1 md:grid-cols-5 gap-3 items-end" data-leg-index="' + i + '">' +
                    '<div><label class="block text-xs font-semibold text-gray-600 mb-1">From</label>' +
                        '<input type="text" name="legs[' + i + '][from_location]" class="form-input w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm" placeholder="e.g. DAC" value="' + (prefill.from_location || '') + '"></div>' +
                    '<div><label class="block text-xs font-semibold text-gray-600 mb-1">To</label>' +
                        '<input type="text" name="legs[' + i + '][to_location]" class="form-input w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm" placeholder="e.g. IST" value="' + (prefill.to_location || '') + '"></div>' +
                    '<div><label class="block text-xs font-semibold text-gray-600 mb-1">Travel Date</label>' +
                        '<input type="text" data-dt name="legs[' + i + '][travel_date]" class="form-input w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm" value="' + (prefill.travel_date || '') + '"></div>' +
                    '<div><label class="block text-xs font-semibold text-gray-600 mb-1">Cost</label>' +
                        '<input type="number" min="0" step="0.01" name="legs[' + i + '][cost]" class="form-input w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm" value="' + (prefill.cost ?? '') + '"></div>' +
                    '<div><button type="button" onclick="$(this).closest(\'.leg-row\').remove()" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash"></i> Remove</button></div>' +
                '</div>';
            $('#legsContainer').append(row);
        }

        function openLegsModal(ticketPurchaseId, ticketNo) {
            $('#legsTicketPurchaseId').val(ticketPurchaseId);
            $('#legsTicketNo').text(ticketNo);
            $('#legsContainer').empty();
            legIndex = 0;

            $.ajax({
                url: '{{ route('role.ticket-purchase.legs', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => 'PLACEHOLDER']) }}'.replace('PLACEHOLDER', ticketPurchaseId),
                method: 'GET',
                success: function (response) {
                    if (response.success && response.legs.length) {
                        response.legs.forEach(function (leg) { addLegRow(leg); });
                    }
                    $('#legsModal').removeClass('hidden');
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Failed', text: 'Could not load route legs.' });
                }
            });
        }

        $('.modal-close-legs').click(function () {
            $('#legsModal').addClass('hidden');
        });

        $('#legsSaveBtn').click(function () {
            const ticketPurchaseId = $('#legsTicketPurchaseId').val();
            const formData = $('#legsContainer').find('input').serialize();

            $.ajax({
                url: '{{ route('role.ticket-purchase.legs', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => 'PLACEHOLDER']) }}'.replace('PLACEHOLDER', ticketPurchaseId),
                method: 'POST',
                data: formData,
                success: function (response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: 'Route legs updated.' });
                        $('#legsModal').addClass('hidden');
                    } else {
                        Swal.fire({ icon: 'error', title: 'Failed', text: response.message || 'Could not save route legs.' });
                    }
                },
                error: function (xhr) {
                    Swal.fire({ icon: 'error', title: 'Failed', text: xhr.responseJSON?.message || 'Could not save route legs.' });
                }
            });
        });

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

        // filter js
        document.addEventListener('DOMContentLoaded', function() {
            const filterHeader  = document.getElementById('tkFilterHeader');
            const filterContent = document.getElementById('tkFilterBody');

            // Auto-open filter if any params present
            @php
                $hasFilters = collect(['passport_holder_id','ticket_id','vendor_id','portal_id','ticket_type','trip_type','created_by','status'])->contains(fn($k) => request($k));
            @endphp
            @if($hasFilters)
            filterHeader.classList.add('active');
            filterContent.classList.add('active');
            @endif

            filterHeader.addEventListener('click', function() {
                this.classList.toggle('active');
                filterContent.classList.toggle('active');
            });

            document.querySelector('.reset-btn').addEventListener('click', function (e) {
                e.preventDefault();
                window.location = '{{ route('role.ticket-purchase.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@include('partials.datetime-picker'){{-- date + optional time on operational date boxes (2026-09-30) --}}
@endsection
