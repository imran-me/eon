@extends('layout.app')

@section('meta-information')
    <title>Portal List</title>
@endsection

@section('main-content')
<style>
    .portal-card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 2px 16px rgba(0,0,0,0.06);
        padding: 1.5rem 1.75rem;
    }
    .portal-card .page-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1e293b;
    }
    /* Table */
    .portal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.85rem; }
    .portal-table thead th {
        background: #f8fafc;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-size: 0.72rem;
        padding: 11px 14px;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    .portal-table tbody td {
        padding: 13px 14px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: middle;
    }
    .portal-table tbody tr:last-child td { border-bottom: none; }
    .portal-table tbody tr:hover { background: #f8fafc; }
    /* Badges */
    .badge { display:inline-block; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; letter-spacing: 0.02em; }
    .badge-active   { background: #dcfce7; color: #166534; }
    .badge-inactive { background: #fee2e2; color: #991b1b; }
    .badge-flight   { background: #dbeafe; color: #1e40af; }
    .badge-bus      { background: #fef9c3; color: #854d0e; }
    .badge-train    { background: #f3e8ff; color: #6b21a8; }
    /* Stats mini */
    .stat-mini { font-size: 0.78rem; }
    .stat-mini .val { font-weight: 700; color: #1e293b; }
    .stat-mini .lbl { color: #94a3b8; font-size: 0.7rem; }
    .stat-added { color: #16a34a; }
    .stat-used  { color: #dc2626; }
    /* Action buttons */
    .btn-sm { display:inline-flex; align-items:center; gap:4px; padding: 5px 11px; border-radius: 7px; font-size: 12px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.15s; }
    .btn-edit    { background: #eef2ff; color: #4338ca; }
    .btn-edit:hover { background: #c7d2fe; }
    .btn-balance { background: #dcfce7; color: #15803d; }
    .btn-balance:hover { background: #bbf7d0; }
    .btn-journal { background: #f0f9ff; color: #0369a1; }
    .btn-journal:hover { background: #bae6fd; }
    .btn-delete { background: #fee2e2; color: #b91c1c; }
    .btn-delete:hover { background: #fecaca; }
    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(15,23,42,0.45);
        backdrop-filter: blur(3px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 24px 48px rgba(0,0,0,0.18);
        width: 100%;
        max-width: 540px;
        overflow: hidden;
    }
    .modal-header {
        background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
        padding: 1.25rem 1.5rem;
        color: #fff;
    }
    .modal-header h3 { font-size: 1rem; font-weight: 700; margin: 0; }
    .modal-header p  { font-size: 0.8rem; opacity: 0.75; margin: 2px 0 0; }
    .modal-body  { padding: 1.5rem; }
    .modal-footer { padding: 1rem 1.5rem; background: #f8fafc; display: flex; justify-content: flex-end; gap: 0.5rem; border-top: 1px solid #e2e8f0; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem; }
    .info-card { background: #f8fafc; border-radius: 10px; padding: 0.75rem 1rem; }
    .info-card .ic-label { font-size: 0.68rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; }
    .info-card .ic-value { font-size: 1rem; font-weight: 700; color: #1e293b; margin-top: 2px; }
    .info-card.balance-card { border: 2px solid #2563eb22; }
    .info-card.balance-card .ic-value { color: #2563eb; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .form-group { margin-bottom: 0.85rem; }
    .form-group label { display: block; font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 4px; }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 11px;
        font-size: 0.85rem;
        color: #1e293b;
        background: #fff;
        transition: border-color 0.15s;
        box-sizing: border-box;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: #2563eb;
        outline: none;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .form-group .required { color: #ef4444; }
    .btn-cancel { padding: 8px 20px; border-radius: 8px; border: none; background: #e2e8f0; color: #475569; font-weight: 600; cursor: pointer; }
    .btn-submit { padding: 8px 22px; border-radius: 8px; border: none; background: #16a34a; color: #fff; font-weight: 700; cursor: pointer; }
    .btn-submit:hover { background: #15803d; }
    .alert-success { border-left: 4px solid #10b981; background: #f0fdf4; color: #166534; padding: 10px 14px; border-radius: 8px; font-weight: 500; font-size: 0.88rem; }
    .alert-error   { border-left: 4px solid #ef4444; background: #fef2f2; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; }
    /* Transaction type cards */
    .txn-type-btn {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 12px;
        cursor: pointer;
        transition: all 0.15s;
        text-align: center;
        background: #f8fafc;
        display: block;
    }
    .txn-type-btn:hover { border-color: #93c5fd; background: #eff6ff; }
    .txn-type-btn.active { border-color: #2563eb; background: #eff6ff; }
    .txn-type-btn.active-usage { border-color: #f59e0b; background: #fffbeb; }
    .txn-type-btn.active-opening { border-color: #8b5cf6; background: #f5f3ff; }
    .txn-icon  { font-size: 1.3rem; margin-bottom: 3px; }
    .txn-label { font-size: 0.78rem; font-weight: 700; color: #1e293b; }
    .txn-desc  { font-size: 0.67rem; color: #94a3b8; margin-top: 2px; line-height: 1.3; }
</style>

<div class="portal-card">
    <div class="flex justify-between items-center mb-5">
        <h2 class="page-title">All Portals</h2>
        <a href="{{ route('role.portal-management.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
           class="btn-sm" style="background:#2563eb;color:#fff;padding:8px 16px;font-size:13px;">
            + Add Portal
        </a>
    </div>

    @if(session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error mb-4">{{ session('error') }}</div>
    @endif

    <div style="overflow-x:auto;">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Portal Name</th>
                    <th>Type</th>
                    <th>Vendor</th>
                    <th>Current Balance</th>
                    <th>Credit Limit</th>
                    <th>Net Used</th>
                    <th>Next Payment Date</th>
                    <th>Next Payment Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($portals as $portal)
                @php
                    $grossUsed   = App\Models\PortalBalance::where('portal_id', $portal->id)->whereIn('type', ['ticket_purchase', 'opening_usage'])->sum('credit');
                    $totalRepaid = App\Models\PortalBalance::where('portal_id', $portal->id)->where('type', 'add_balance')->sum('debit');
                    $totalUsed   = max(0, $grossUsed - $totalRepaid);
                @endphp
                <tr>
                    <td style="color:#94a3b8;font-size:12px;">
                        {{ $loop->iteration + ($portals->currentPage() - 1) * $portals->perPage() }}
                    </td>
                    <td>
                        <div style="font-weight:600;color:#1e293b;">{{ $portal->name }}</div>
                        @if($portal->account)
                            <div style="font-size:11px;color:#94a3b8;">A/C: {{ $portal->account->name }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $portal->type }}">{{ strtoupper($portal->type) }}</span>
                    </td>
                    <td>{{ $portal->vendor->name ?? '—' }}</td>
                    <td>
                        <div style="font-weight:700;color:#1e293b;font-size:0.95rem;">
                            {{ number_format($portal->balance, 2) }}
                        </div>
                    </td>
                    <td>
                        <div class="stat-added" style="font-weight:700;">
                            {{ number_format($portal->total_added ?? 0, 2) }}
                        </div>
                    </td>
                    <td>
                        <div class="stat-used" style="font-weight:700;">
                            {{ number_format($totalUsed ?? 0, 2) }}
                        </div>
                    </td>
                    <td>
                        @if($portal->next_payment_date)
                            <div style="font-weight:600;color:#1e293b;">{{ \Carbon\Carbon::parse($portal->next_payment_date)->format('d M Y') }}</div>
                        @else
                            <span style="color:#94a3b8;font-size:12px;">—</span>
                        @endif
                    </td>
                    <td>
                        @if($portal->next_payment_amount !== null)
                            <div style="font-weight:700;color:#7c3aed;">{{ number_format($portal->next_payment_amount, 2) }}</div>
                        @else
                            <span style="color:#94a3b8;font-size:12px;">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $portal->status == 'active' ? 'badge-active' : 'badge-inactive' }}">
                            {{ ucfirst($portal->status) }}
                        </span>
                    </td>
                    <td>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;min-width:160px;">
                            <a href="{{ route('role.portal-management.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'portal_management' => $portal->id]) }}"
                               class="btn-sm btn-edit" style="justify-content:center;">&#9998; Edit</a>
                            <button type="button"
                                class="btn-sm btn-balance"
                                style="justify-content:center;"
                                onclick="openBalanceModal(
                                    '{{ $portal->id }}',
                                    '{{ addslashes($portal->name) }}',
                                    '{{ $portal->type }}',
                                    '{{ $portal->balance }}'
                                )">+ Balance</button>
                            <button type="button"
                                class="btn-sm"
                                style="background:#f3e8ff;color:#6b21a8;justify-content:center;"
                                onclick="openPaymentModal(
                                    '{{ $portal->id }}',
                                    '{{ addslashes($portal->name) }}',
                                    '{{ $portal->next_payment_date ?? '' }}',
                                    '{{ $portal->next_payment_amount ?? '' }}'
                                )">&#128197; Payment</button>
                            <a href="{{ route('role.portal-management.journal', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $portal->id]) }}"
                               class="btn-sm btn-journal" style="justify-content:center;">&#128196; Journal</a>
                            @can('delete portal')
                            <form action="{{ route('role.portal-management.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'portal_management' => $portal->id]) }}"
                                  method="POST" style="grid-column:1 / -1;"
                                  onsubmit="return confirm('Delete portal &quot;{{ addslashes($portal->name) }}&quot;? This can be restored later if needed.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-sm btn-delete" style="width:100%;justify-content:center;">&#128465; Delete</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">No portals found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $portals->links() }}</div>
</div>

{{-- Payment Info Modal --}}
<div class="modal-overlay" id="paymentModal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#6b21a8 0%,#7c3aed 100%);">
            <h3>Next Payment Info</h3>
            <p id="paymentModalSubtitle">Set next payment date &amp; amount</p>
        </div>
        <form action="{{ route('role.portal-management.update.payment-info', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
            @csrf
            <input type="hidden" name="portal_id" id="payment_portal_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Next Payment Date <span class="required">*</span></label>
                    <input type="date" name="next_payment_date" id="payment_date" required>
                </div>
                <div class="form-group">
                    <label>Next Payment Amount <span class="required">*</span></label>
                    <input type="number" name="next_payment_amount" id="payment_amount" step="0.01" min="0" required placeholder="0.00">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closePaymentModal()">Cancel</button>
                <button type="submit" class="btn-submit" style="background:#7c3aed;">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Balance Modal --}}
<div class="modal-overlay" id="balanceModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add Balance to Portal</h3>
            <p id="modalSubtitle">Manage portal funding and journal entry</p>
        </div>
        <form action="{{ route('role.portal-management.update.balance', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
            @csrf
            <input type="hidden" name="portal_id" id="modal_portal_id">

            <div class="modal-body">
                {{-- Info Cards --}}
                <div class="info-grid">
                    <div class="info-card">
                        <div class="ic-label">Account</div>
                        <div class="ic-value" id="modalPortalName" style="font-size:0.85rem;">—</div>
                    </div>
                    <div class="info-card">
                        <div class="ic-label">Type</div>
                        <div class="ic-value" id="modalPortalType" style="font-size:0.85rem;">—</div>
                    </div>
                    <div class="info-card balance-card">
                        <div class="ic-label">Current Balance</div>
                        <div class="ic-value" id="modalPortalBalance">0.00</div>
                    </div>
                </div>

                {{-- Transaction Type selector --}}
                <div class="form-group" style="margin-bottom:1rem;">
                    <label>Transaction Type <span class="required">*</span></label>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:4px;" id="txnTypeBtns">
                        <label class="txn-type-btn active" data-type="add_balance">
                            <input type="radio" name="txn_type" value="add_balance" checked style="display:none;">
                            <div class="txn-icon">💰</div>
                            <div class="txn-label">Regular Top-up</div>
                            <div class="txn-desc">Add funds to portal</div>
                        </label>
                        <label class="txn-type-btn" data-type="opening_balance">
                            <input type="radio" name="txn_type" value="opening_balance" style="display:none;">
                            <div class="txn-icon">📂</div>
                            <div class="txn-label">Opening Balance</div>
                            <div class="txn-desc">Historical funds added before software</div>
                        </label>
                        <label class="txn-type-btn" data-type="opening_usage">
                            <input type="radio" name="txn_type" value="opening_usage" style="display:none;">
                            <div class="txn-icon">📤</div>
                            <div class="txn-label">Opening Usage</div>
                            <div class="txn-desc">Historical amount used before software</div>
                        </label>
                    </div>
                </div>

                {{-- Opening Usage Notice --}}
                <div id="usageNotice" style="display:none;background:#fef3c7;border-left:4px solid #f59e0b;padding:10px 14px;border-radius:8px;margin-bottom:1rem;font-size:0.82rem;color:#92400e;">
                    <strong>Opening Usage:</strong> এই entry portal এর balance <strong>কমাবে</strong>। Software চালু হওয়ার আগে যত টাকার ticket/service নেওয়া হয়েছে সেটা এখানে দিন।
                </div>

                {{-- Row 1: Date + Amount --}}
                <div class="form-row">
                    <div class="form-group">
                        <label>Transaction Date <span class="required">*</span></label>
                        <input type="date" name="date" id="modal_date" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label id="amountLabel">Amount to Add <span class="required">*</span></label>
                        <input type="number" name="amount" id="modal_amount" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                </div>

                {{-- Row 2: Source Account + Payment Method (hidden for opening_usage) --}}
                <div class="form-row" id="sourceAccountRow">
                    <div class="form-group">
                        <label>Source Account (Pay From) <span class="required">*</span></label>
                        <select name="source_account_id" id="sourceAccountSelect" required>
                            <option value="">-- Select Account --</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Method <span class="required">*</span></label>
                        <select name="payment_method" id="paymentMethodSelect" required>
                            <option value="">-- Select --</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="mobile_banking">Mobile Banking</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                </div>

                {{-- Row 3: Reference + Remarks --}}
                <div class="form-row">
                    <div class="form-group">
                        <label>Reference No.</label>
                        <input type="text" name="reference" placeholder="TXN-001 / Cheque No.">
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <input type="text" name="remarks" id="modal_remarks" placeholder="Optional note">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeBalanceModal()">Cancel</button>
                <button type="submit" class="btn-submit">Update Balance</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script>
    function openBalanceModal(id, name, type, balance) {
        document.getElementById('modal_portal_id').value   = id;
        document.getElementById('modalPortalName').innerText    = name;
        document.getElementById('modalPortalType').innerText    = type.toUpperCase();
        document.getElementById('modalPortalBalance').innerText = parseFloat(balance).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('modalSubtitle').innerText = name + ' — ' + type.toUpperCase();
        // Reset to default type
        setTxnType('add_balance');
        document.getElementById('balanceModal').classList.add('active');
    }

    function closeBalanceModal() {
        document.getElementById('balanceModal').classList.remove('active');
    }

    function setTxnType(type) {
        const sourceRow   = document.getElementById('sourceAccountRow');
        const notice      = document.getElementById('usageNotice');
        const amountLabel = document.getElementById('amountLabel');
        const srcSelect   = document.getElementById('sourceAccountSelect');
        const paySelect   = document.getElementById('paymentMethodSelect');
        const submitBtn   = document.querySelector('.btn-submit');
        const remarksEl   = document.getElementById('modal_remarks');
        const btns        = document.querySelectorAll('.txn-type-btn');

        // Reset button styles
        btns.forEach(b => b.classList.remove('active', 'active-usage', 'active-opening'));

        if (type === 'opening_usage') {
            sourceRow.style.display = 'none';
            notice.style.display    = 'block';
            srcSelect.removeAttribute('required');
            paySelect.removeAttribute('required');
            amountLabel.innerHTML   = 'Amount Used (Opening) <span style="color:#ef4444">*</span>';
            submitBtn.style.background = '#f59e0b';
            submitBtn.textContent   = 'Record Opening Usage';
            if (!remarksEl.value) remarksEl.placeholder = 'e.g. Pre-software ticket purchases';
            document.querySelector('[data-type="opening_usage"]').classList.add('active-usage');
        } else if (type === 'opening_balance') {
            sourceRow.style.display = 'none';
            notice.style.display    = 'none';
            srcSelect.removeAttribute('required');
            paySelect.removeAttribute('required');
            amountLabel.innerHTML   = 'Opening Amount Added <span style="color:#ef4444">*</span>';
            submitBtn.style.background = '#7c3aed';
            submitBtn.textContent   = 'Record Opening Balance';
            if (!remarksEl.value) remarksEl.placeholder = 'e.g. Historical balance before software';
            document.querySelector('[data-type="opening_balance"]').classList.add('active-opening');
        } else {
            sourceRow.style.display = '';
            notice.style.display    = 'none';
            srcSelect.setAttribute('required', 'required');
            paySelect.setAttribute('required', 'required');
            amountLabel.innerHTML   = 'Amount to Add <span style="color:#ef4444">*</span>';
            submitBtn.style.background = '#16a34a';
            submitBtn.textContent   = 'Update Balance';
            if (!remarksEl.value) remarksEl.placeholder = 'Optional note';
            document.querySelector('[data-type="add_balance"]').classList.add('active');
        }
    }

    // Transaction type card click
    document.querySelectorAll('.txn-type-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const type = this.dataset.type;
            this.querySelector('input[type=radio]').checked = true;
            setTxnType(type);
        });
    });

    // Close on backdrop click
    document.getElementById('balanceModal').addEventListener('click', function(e) {
        if (e.target === this) closeBalanceModal();
    });

    function openPaymentModal(id, name, date, amount) {
        document.getElementById('payment_portal_id').value = id;
        document.getElementById('paymentModalSubtitle').innerText = name;
        document.getElementById('payment_date').value   = date;
        document.getElementById('payment_amount').value = amount;
        document.getElementById('paymentModal').classList.add('active');
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.remove('active');
    }

    document.getElementById('paymentModal').addEventListener('click', function(e) {
        if (e.target === this) closePaymentModal();
    });
</script>
@endsection
