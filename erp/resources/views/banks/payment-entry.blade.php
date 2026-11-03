@extends('layout.app')

@section('meta-information')
    <title>Payment Entry</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .pe-shell{max-width:1480px;margin:0 auto}.pe-head,.pe-card,.pe-review{background:#fff;border:1px solid #dbe3ef;border-radius:16px;box-shadow:0 10px 28px rgba(15,23,42,.055)}
    .pe-head{display:flex;align-items:center;gap:14px;padding:16px 18px;margin-bottom:14px;background:linear-gradient(135deg,#fff 65%,#f5f7ff)}.pe-head-icon{width:48px;height:48px;border-radius:14px;background:linear-gradient(145deg,#eef2ff,#e0e7ff);color:#4f46e5;display:grid;place-items:center;font-size:18px;box-shadow:inset 0 0 0 1px #dbe3ff}
    .pe-head h1{font-size:19px;font-weight:800;color:#0f172a;letter-spacing:-.02em}.pe-head p{font-size:12px;color:#64748b;margin-top:2px}.pe-modes{display:flex;gap:4px;margin-left:auto;padding:4px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:12px}
    .pe-mode{min-width:128px;border:0;border-radius:9px;padding:9px 16px;font-size:12px;font-weight:800;background:transparent;color:#64748b;transition:.18s ease}.pe-mode:hover{background:#fff;color:#334155}.pe-mode.in.is-active{background:#fff;color:#15803d;box-shadow:0 2px 8px rgba(15,23,42,.1);outline:1px solid #bbf7d0}.pe-mode.out.is-active{background:#fff;color:#b91c1c;box-shadow:0 2px 8px rgba(15,23,42,.1);outline:1px solid #fecaca}
    .pe-card{padding:20px 24px 22px}.pe-section{display:flex;align-items:center;gap:8px;font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#4f46e5;background:linear-gradient(90deg,#eef2ff,rgba(238,242,255,0));border-left:3px solid #6366f1;border-bottom:0;padding:8px 10px;margin:22px -4px 14px;border-radius:0 8px 8px 0}.pe-section:first-child{margin-top:0}.pe-section span{color:#94a3b8!important}
    .pe-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));column-gap:16px;row-gap:13px}.pe-field,.pe-field.wide{grid-column:span 6}.pe-field.full{grid-column:1/-1}.pe-field label{display:block;font-size:11px;font-weight:750;color:#334155;margin-bottom:6px}.pe-field label span{color:#dc2626}
    .pe-field input,.pe-field select{width:100%;height:44px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;font-size:13px;background:#fff;color:#0f172a;transition:border-color .15s,box-shadow .15s,background .15s}.pe-field input:hover,.pe-field select:hover{border-color:#94a3b8}.pe-field input:focus,.pe-field select:focus{outline:0;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}.pe-field input[readonly]{background:#f8fafc;color:#475569;font-weight:650}
    .pe-time{display:grid;grid-template-columns:minmax(0,1fr) 10px minmax(0,1fr) minmax(78px,.8fr);gap:7px;align-items:center}.pe-time-colon{text-align:center;font-weight:800;color:#94a3b8}.pe-time select{padding-right:26px;text-align:center}
    .pe-field .select2-container .select2-selection--single{height:44px!important;border:1px solid #cbd5e1!important;border-radius:10px!important;padding:7px 11px 0!important;transition:border-color .15s,box-shadow .15s}.pe-field .select2-container--default.select2-container--focus .select2-selection--single,.pe-field .select2-container--default.select2-container--open .select2-selection--single{border-color:#6366f1!important;box-shadow:0 0 0 3px rgba(99,102,241,.12)}.pe-field .select2-container--default .select2-selection--single .select2-selection__arrow{height:42px!important}.pe-field .select2-container--disabled .select2-selection--single{background:#f1f5f9!important}
    .pe-balance{display:inline-flex;align-items:center;min-height:18px;font-size:10.5px;color:#64748b;margin-top:5px}.pe-actions{display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #e2e8f0;margin-top:22px;padding-top:16px}.pe-btn{border:0;border-radius:10px;padding:11px 18px;font-size:12px;font-weight:800;transition:transform .15s,box-shadow .15s}.pe-btn:hover{transform:translateY(-1px)}.pe-btn-review{min-width:150px;background:linear-gradient(135deg,#4f46e5,#2563eb);color:#fff;box-shadow:0 6px 15px rgba(79,70,229,.24)}.pe-btn-back{background:#f1f5f9;color:#334155}.pe-grid-deductions .pe-field,.pe-grid-deductions .pe-field.wide{grid-column:span 3}@media(max-width:1180px){.pe-grid-deductions .pe-field,.pe-grid-deductions .pe-field.wide{grid-column:span 6}}@media(max-width:800px){.pe-grid-deductions .pe-field,.pe-grid-deductions .pe-field.wide{grid-column:1/-1}}.pe-post-rail{width:100%;padding:13px 18px;font-size:13px}.pe-post-rail:disabled{opacity:.6;cursor:not-allowed}.pe-btn-post{background:#16a34a;color:#fff;box-shadow:0 5px 14px rgba(22,163,74,.25)}
    .pe-review{display:none;padding:22px;margin-top:14px;border-color:#c4b5fd}.pe-review.is-open{display:block}.pe-review-head{display:flex;align-items:center;justify-content:space-between;background:#f5f3ff;border-radius:10px;padding:12px 14px;margin-bottom:15px}.pe-review-head strong{color:#5b21b6}.pe-pill{padding:5px 9px;border-radius:999px;font-size:10px;font-weight:800}.pe-review dl{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.pe-review dl div{background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:10px}.pe-review dt{font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8}.pe-review dd{font-size:13px;font-weight:700;color:#1e293b;margin-top:3px}.pe-error{display:none;margin-bottom:14px;padding:11px 13px;border:1px solid #fecaca;border-radius:10px;background:#fef2f2;color:#b91c1c;font-size:12px}.pe-error.is-open{display:block}
    .pe-help{font-size:11px;color:#64748b;margin-top:5px}
    .pe-workspace{display:grid;grid-template-columns:minmax(0,1fr) 400px;gap:18px;align-items:start}.pe-main{min-width:0}.pe-live{position:sticky;top:14px;background:#fff;border:1px solid #dbe3ef;border-radius:16px;box-shadow:0 10px 28px rgba(15,23,42,.055);overflow:hidden}.pe-live-head{padding:17px 18px;background:linear-gradient(135deg,#312e81,#2563eb);color:#fff}.pe-live-head-row{display:flex;align-items:center;justify-content:space-between;gap:8px}.pe-live-head strong{font-size:15px}.pe-live-head p{font-size:11px;color:#dbeafe;margin-top:4px}.pe-live-body{padding:16px}.pe-live-flow{display:grid;grid-template-columns:1fr 32px 1fr;align-items:center;gap:8px;padding:13px;border:1px solid #e0e7ff;border-radius:11px;background:#f5f7ff}.pe-live-node small{display:block;color:#94a3b8;font-size:9px;text-transform:uppercase;font-weight:800;letter-spacing:.05em}.pe-live-node b{display:block;color:#1e293b;font-size:11.5px;line-height:1.35;margin-top:3px;overflow-wrap:anywhere}.pe-live-arrow{width:30px;height:30px;display:grid;place-items:center;border-radius:50%;background:#fff;color:#4f46e5;box-shadow:0 2px 7px rgba(79,70,229,.15)}.pe-live-section{margin-top:16px}.pe-live-label{font-size:9px;font-weight:900;letter-spacing:.09em;text-transform:uppercase;color:#64748b;margin-bottom:8px}.pe-journal{border:1px solid #e2e8f0;border-radius:10px;overflow:hidden}.pe-journal-row{display:grid;grid-template-columns:minmax(0,1fr) 82px 82px;gap:8px;padding:9px 10px;border-bottom:1px solid #eef2f7;font-size:11px;align-items:center}.pe-journal-row:last-child{border-bottom:0}.pe-journal-row.head{background:#f8fafc;color:#94a3b8;font-size:9px;font-weight:800;text-transform:uppercase}.pe-journal-row span:nth-child(n+2){text-align:right;font-variant-numeric:tabular-nums}.pe-journal-row.total{background:#f8fafc;font-weight:800;color:#0f172a}.pe-live-balance{display:grid;grid-template-columns:1fr auto;gap:8px;padding:12px 13px;border-radius:10px;background:#ecfdf5;border:1px solid #bbf7d0}.pe-live-balance small{display:block;font-size:9px;color:#64748b;text-transform:uppercase}.pe-live-balance b{display:block;margin-top:2px;color:#166534;font-size:14px}.pe-live-delta{text-align:right;color:#166534;font-size:11px;font-weight:800}.pe-live-meta{margin-top:11px;padding-top:10px;border-top:1px dashed #cbd5e1;color:#64748b;font-size:10.5px;line-height:1.6}.pe-live-empty{padding:14px;border:1px dashed #cbd5e1;border-radius:10px;text-align:center;color:#94a3b8;font-size:11px}
    /* Form polish — spacing, one consistent control height, dropdown alignment */
    .pe-card{padding:26px 30px 30px}
    .pe-section{margin:30px -6px 18px;padding:9px 12px}
    .pe-grid{column-gap:20px;row-gap:20px}
    .pe-field label{margin-bottom:7px;font-size:11.5px;letter-spacing:.01em}
    .pe-field input,.pe-field select{height:46px;font-size:13.5px;padding:0 14px}
    .pe-field .select2-container{width:100%!important}
    .pe-field .select2-container .select2-selection--single{height:46px!important;padding:0 36px 0 14px!important;display:flex!important;align-items:center}
    .pe-field .select2-container--default .select2-selection--single .select2-selection__rendered{padding:0!important;line-height:normal!important;font-size:13.5px;color:#0f172a;width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .pe-field .select2-container--default .select2-selection--single .select2-selection__placeholder{color:#94a3b8;font-size:13.5px}
    .pe-field .select2-container--default .select2-selection--single .select2-selection__clear{position:absolute;right:30px;top:50%;transform:translateY(-50%);margin:0;float:none;font-size:16px;color:#94a3b8;line-height:1}
    .pe-field .select2-container--default .select2-selection--single .select2-selection__arrow{height:46px!important;right:8px!important;top:0!important}
    .pe-help,.pe-balance{margin-top:7px;line-height:1.45}
    .pe-field input[type=number]{font-variant-numeric:tabular-nums}
    .pe-time{gap:8px}.pe-time select{padding:0 22px 0 10px}
    .pe-live{top:18px}.pe-live-head{padding:19px 20px}.pe-live-body{padding:18px 20px 20px}.pe-live-section{margin-top:20px}
    .pe-post-rail{margin-top:4px;padding:14px 18px;border-radius:11px}
    .pe-dd.select2-dropdown{border:1px solid #c7d2fe;border-radius:10px;box-shadow:0 14px 34px rgba(15,23,42,.14);overflow:hidden;margin-top:4px;font-size:13px}
    .pe-dd .select2-search--dropdown{padding:10px}.pe-dd .select2-search--dropdown .select2-search__field{height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font-size:13px}.pe-dd .select2-search--dropdown .select2-search__field:focus{outline:0;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
    .pe-dd .select2-results__option{padding:9px 14px;font-size:13px}.pe-dd .select2-results__option--highlighted[aria-selected]{background:#eef2ff!important;color:#3730a3!important}.pe-dd .select2-results__option[aria-selected=true]{background:#f1f5f9}
    @media(max-width:1200px){.pe-workspace{grid-template-columns:minmax(0,1fr) 350px}}
    @media(max-width:1050px){.pe-workspace{grid-template-columns:1fr}.pe-live{position:static;order:-1}.pe-live-body{display:grid;grid-template-columns:1fr 1fr;gap:12px}.pe-live-section{margin-top:0}.pe-live-flow{grid-column:1/-1}}
    @media(max-width:800px){.pe-head{align-items:flex-start;flex-wrap:wrap}.pe-modes{width:100%;margin-left:0}.pe-mode{flex:1;min-width:0}.pe-card{padding:16px}.pe-field,.pe-field.wide{grid-column:1/-1}.pe-review dl{grid-template-columns:1fr}.pe-live-body{display:block}.pe-live-section{margin-top:14px}}
</style>
@endsection

@section('main-content')
@php $role = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first()); @endphp
<div class="pe-shell">
    <header class="pe-head">
        <span class="pe-head-icon"><i class="fas fa-money-bill-transfer"></i></span>
        <div>
            <h1>Payment Entry</h1>
            <p>Record money in or out from any bank, card, wallet or cash account.</p>
        </div>
        <div class="pe-modes" aria-label="Payment direction">
            <button type="button" class="pe-mode in is-active" data-type="deposit" aria-pressed="true"><i class="fas fa-arrow-down mr-1"></i> Payment In</button>
            <button type="button" class="pe-mode out" data-type="withdraw" aria-pressed="false"><i class="fas fa-arrow-up mr-1"></i> Payment Out</button>
        </div>
    </header>

    <div id="peError" class="pe-error" role="alert"></div>

    <div class="pe-workspace">
    <div class="pe-main">
    <form id="paymentEntryForm" class="pe-card">
        <input type="hidden" id="peType" value="deposit">
        <div class="pe-section">Account &amp; amount</div>
        <div class="pe-grid">
            <div class="pe-field">
                <label for="peDate">Date <span>*</span></label>
                <input id="peDate" type="date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="pe-field">
                <label for="peHour">Custom Time <span>*</span></label>
                <div class="pe-time">
                    <select id="peHour" aria-label="Hour" required>
                        <option value="">hh</option>
                        @for($hour = 1; $hour <= 12; $hour++)
                            <option value="{{ $hour }}">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                    <span class="pe-time-colon">:</span>
                    <select id="peMinute" aria-label="Minute" required>
                        <option value="">mm</option>
                        @for($minute = 0; $minute < 60; $minute++)
                            <option value="{{ $minute }}">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                    <select id="peAmPm" aria-label="AM or PM"><option>AM</option><option>PM</option></select>
                </div>
                <div class="pe-balance">Required transaction time; this is the time shown in the bank ledger.</div>
            </div>
            <div class="pe-field">
                <label for="peAmount">Amount <span>*</span></label>
                <input id="peAmount" type="number" min="0.01" step="0.01" required>
            </div>
            <div class="pe-field wide">
                <label for="peContra" id="peContraLabel">Received from balance / contra account <span>*</span></label>
                <select id="peContra" required>
                    <option value="">Search chart of accounts</option>
                    @foreach($accounts->groupBy('type') as $type => $typeAccounts)
                        <optgroup label="{{ ucfirst($type) }}">
                            @foreach($typeAccounts as $account)
                                <option value="{{ $account->id }}" data-name="{{ $account->name }}" data-type="{{ $account->type }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="pe-field wide">
                <label for="peBank" id="peBankLabel">Receive into bank / card / cash account <span>*</span></label>
                <select id="peBank" required>
                    <option value="">Select account</option>
                    @foreach($banks as $bank)
                        @php $bankCompany = $companiesById->get($bank->company_id); @endphp
                        <option value="{{ $bank->id }}" data-account="{{ $bank->account_id }}" data-balance="{{ (float) $bank->journal_balance }}" data-name="{{ $bank->name }}" data-company="{{ $bank->company_id }}" data-company-name="{{ $bankCompany?->name }}">
                            {{ $bank->name }}{{ $bankCompany ? ' · '.($bankCompany->short_name ?: $bankCompany->name) : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="pe-balance" id="peBalance">Choose the account where money will move.</div>
            </div>
            <div class="pe-field wide">
                <label for="peCompany">Company</label>
                <input id="peCompany" value="Select a money account first" readonly>
            </div>
            <div class="pe-field wide" id="peDepartmentWrap" hidden>
                <label for="peDepartment">Department <span>*</span></label>
                <select id="peDepartment" disabled>
                    <option value="">Search department</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" data-company="{{ $department->company_id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
                <div class="pe-balance">Required when Payment Out posts to an expense account.</div>
            </div>
        </div>

        <div class="pe-section">Transaction details</div>
        <div class="pe-grid">
            <div class="pe-field">
                <label for="peReference">Reference no.</label>
                <input id="peReference" maxlength="100">
            </div>
            <div class="pe-field wide">
                <label for="peParty" id="peBeneficiaryLabel">Received from</label>
                <select id="peParty">
                    <option value="">Search agent, vendor or customer</option>
                    @foreach(['Agent', 'Vendor', 'Customer'] as $kind)
                        <optgroup label="{{ $kind }}s">
                            @foreach($beneficiaryParties->where('kind', $kind) as $party)
                                <option value="{{ $party['id'] }}" data-name="{{ $party['name'] }}" data-kind="{{ strtolower($kind) }}">{{ $party['name'] }} · {{ $party['display_id'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <div class="pe-help">Optional identity. The selected journal head remains unchanged.</div>
            </div>
            <div class="pe-field wide">
                <label for="pePurpose">Purpose / remarks</label>
                <input id="pePurpose" maxlength="255">
            </div>
            <div class="pe-field">
                <label for="pePayMethod" id="pePayMethodLabel">Received via</label>
                <select id="pePayMethod"><option value="">Not recorded</option><option value="cash">Cash</option><option value="bank">Bank transfer</option><option value="cheque">Cheque</option></select>
            </div>
            <div class="pe-field wide" id="pePayBankWrap" hidden>
                <label for="pePayBank">Their bank</label><input id="pePayBank" maxlength="150">
            </div>
            <div class="pe-field" id="pePayRefWrap" hidden>
                <label for="pePayRef" id="pePayRefLabel">Account no.</label><input id="pePayRef" maxlength="100">
            </div>
        </div>

        <div class="pe-section">Bank deductions <span style="font-weight:500;letter-spacing:0;text-transform:none;color:#94a3b8">optional</span></div>
        <div class="pe-grid pe-grid-deductions">
            <div class="pe-field">
                <label for="peCharge">Bank charge</label>
                <input id="peCharge" type="number" min="0" step="0.01" value="0">
            </div>
            <div class="pe-field wide">
                <label for="peChargeAccount">Charge posts to</label>
                <select id="peChargeAccount">
                    <option value="">Select when charge is entered</option>
                    @foreach($accounts->where('type', 'expense') as $account)
                        <option value="{{ $account->id }}" @selected($account->code === '8510')>[{{ $account->code }}] {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pe-field">
                <label for="peVat">VAT</label>
                <input id="peVat" type="number" min="0" step="0.01" value="0">
            </div>
            <div class="pe-field wide">
                <label for="peVatAccount">VAT posts to</label>
                <select id="peVatAccount">
                    <option value="">Select when VAT is entered</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected($account->code === '8510')>[{{ $account->code }}] {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

    </form>
    </div>

    <aside class="pe-live" aria-live="polite">
        <div class="pe-live-head">
            <div class="pe-live-head-row"><strong><i class="fas fa-scale-balanced mr-1"></i> Posting overview</strong><span id="peLiveType" class="pe-pill" style="background:rgba(255,255,255,.16);color:#fff">Payment In</span></div>
            <p>Live preview — nothing posts until you press Confirm &amp; Post and confirm.</p>
        </div>
        <div class="pe-live-body">
            <div class="pe-live-flow">
                <div class="pe-live-node"><small id="peLiveLeftLabel">Received from</small><b id="peLiveContra">Select contra account</b></div>
                <div class="pe-live-arrow"><i id="peLiveArrow" class="fas fa-arrow-right"></i></div>
                <div class="pe-live-node"><small id="peLiveRightLabel">Money into</small><b id="peLiveBank">Select money account</b></div>
            </div>
            <div class="pe-live-section">
                <div class="pe-live-label">Journal preview</div>
                <div id="peLiveJournal" class="pe-journal"><div class="pe-live-empty">Choose accounts and enter an amount.</div></div>
            </div>
            <div class="pe-live-section">
                <div class="pe-live-label">Money account position</div>
                <div class="pe-live-balance"><div><small>Projected balance</small><b id="peLiveAfter">৳0.00</b></div><div class="pe-live-delta" id="peLiveDelta">No movement</div></div>
                <div id="peLiveMeta" class="pe-live-meta">Company, reference and purpose will appear here.</div>
            </div>
            <div class="pe-live-section">
                <button type="submit" form="paymentEntryForm" id="pePost" class="pe-btn pe-btn-post pe-post-rail"><i class="fas fa-check mr-1"></i> Confirm &amp; Post</button>
            </div>
        </div>
    </aside>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    const form = document.getElementById('paymentEntryForm');
    const bank = document.getElementById('peBank');
    const contra = document.getElementById('peContra');
    const type = document.getElementById('peType');
    const department = document.getElementById('peDepartment');
    const party = document.getElementById('peParty');
    const error = document.getElementById('peError');
    const money = value => new Intl.NumberFormat('en-BD', {minimumFractionDigits:2, maximumFractionDigits:2}).format(Number(value || 0));
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));

    function optionLabel(select, fallback) {
        const option = select.selectedOptions[0];
        return option?.value ? (option.dataset.name || option.textContent.trim()) : fallback;
    }

    function updatePostingOverview() {
        const incoming = type.value === 'deposit';
        const amount = Number(document.getElementById('peAmount').value || 0);
        const charge = Number(document.getElementById('peCharge').value || 0);
        const vat = Number(document.getElementById('peVat').value || 0);
        const deductions = charge + vat;
        const bankOption = bank.selectedOptions[0];
        const bankName = optionLabel(bank, 'Select money account');
        const contraName = optionLabel(contra, 'Select contra account');
        const current = Number(bankOption?.dataset.balance || 0);
        const movement = incoming ? amount - deductions : -(amount + deductions);
        const after = current + movement;

        document.getElementById('peLiveType').textContent = incoming ? 'Payment In' : 'Payment Out';
        document.getElementById('peLiveLeftLabel').textContent = incoming ? 'Received from' : 'Pay from';
        document.getElementById('peLiveRightLabel').textContent = incoming ? 'Money into' : 'Paid to';
        document.getElementById('peLiveContra').textContent = incoming ? contraName : bankName;
        document.getElementById('peLiveBank').textContent = incoming ? bankName : contraName;
        document.getElementById('peLiveArrow').className = 'fas fa-arrow-right';

        const rows = [];
        if (amount > 0 && bankOption?.value && contra.value) {
            if (incoming) {
                rows.push([bankName, Math.max(0, amount - deductions), 0]);
                if (charge > 0) rows.push([optionLabel(document.getElementById('peChargeAccount'), 'Bank charge'), charge, 0]);
                if (vat > 0) rows.push([optionLabel(document.getElementById('peVatAccount'), 'VAT'), vat, 0]);
                rows.push([contraName, 0, amount]);
            } else {
                rows.push([contraName, amount, 0]);
                if (charge > 0) rows.push([optionLabel(document.getElementById('peChargeAccount'), 'Bank charge'), charge, 0]);
                if (vat > 0) rows.push([optionLabel(document.getElementById('peVatAccount'), 'VAT'), vat, 0]);
                rows.push([bankName, 0, amount + deductions]);
            }
        }
        const totalDebit = rows.reduce((sum, row) => sum + row[1], 0);
        const totalCredit = rows.reduce((sum, row) => sum + row[2], 0);
        document.getElementById('peLiveJournal').innerHTML = rows.length
            ? '<div class="pe-journal-row head"><span>Account</span><span>Debit</span><span>Credit</span></div>'
                + rows.map(row => `<div class="pe-journal-row"><span>${escapeHtml(row[0])}</span><span>${row[1] ? money(row[1]) : '—'}</span><span>${row[2] ? money(row[2]) : '—'}</span></div>`).join('')
                + `<div class="pe-journal-row total"><span>Total</span><span>${money(totalDebit)}</span><span>${money(totalCredit)}</span></div>`
            : '<div class="pe-live-empty">Choose accounts and enter an amount.</div>';

        document.getElementById('peLiveAfter').textContent = '৳'+money(bankOption?.value ? after : 0);
        document.getElementById('peLiveDelta').textContent = amount > 0
            ? (movement >= 0 ? '+' : '−')+'৳'+money(Math.abs(movement))
            : 'No movement';
        document.getElementById('peLiveDelta').style.color = movement < 0 ? '#b91c1c' : '#166534';
        const company = bankOption?.dataset.companyName || 'Company not selected';
        const reference = document.getElementById('peReference').value || 'Auto-generated reference';
        const purpose = purposeValue() || 'Purpose not entered';
        document.getElementById('peLiveMeta').innerHTML = `<strong>${escapeHtml(company)}</strong><br>${escapeHtml(reference)}<br>${escapeHtml(purpose)}`;
    }

    function searchable(select, placeholder) {
        if (!window.jQuery?.fn?.select2) return;
        window.jQuery(select).select2({width:'100%', placeholder, allowClear:true, dropdownCssClass:'pe-dd'});
    }
    searchable(bank, 'Search bank, card, wallet or cash');
    searchable(contra, 'Search code or account name');
    searchable(department, 'Search department');
    searchable(document.getElementById('peChargeAccount'), 'Search charge account');
    searchable(document.getElementById('peVatAccount'), 'Search VAT account');
    searchable(party, 'Search agent, vendor or customer');

    function purposeValue() {
        return document.getElementById('pePurpose').value.trim();
    }

    function generatedReference() {
        const now = new Date();
        const pad = value => String(value).padStart(2, '0');
        const stamp = now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate())
            + pad(now.getHours()) + pad(now.getMinutes()) + pad(now.getSeconds());
        return 'BANK-' + (type.value === 'deposit' ? 'DEPOSIT-' : 'WITHDRAW-') + stamp;
    }

    function refreshReference() {
        const input = document.getElementById('peReference');
        if (!input.value || /^BANK-(DEPOSIT|WITHDRAW)-\d{14}$/.test(input.value)) input.value = generatedReference();
    }

    function customTimeValue() {
        const hourValue = document.getElementById('peHour').value;
        const minuteValue = document.getElementById('peMinute').value;
        if (hourValue === '' || minuteValue === '') return '';
        let hour = Number(hourValue) % 12;
        if (document.getElementById('peAmPm').value === 'PM') hour += 12;
        return String(hour).padStart(2, '0') + ':' + String(minuteValue).padStart(2, '0');
    }

    function customTimeLabel() {
        const hour = document.getElementById('peHour').value;
        const minute = document.getElementById('peMinute').value;
        return hour && minute !== ''
            ? String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0') + ' ' + document.getElementById('peAmPm').value
            : '—';
    }

    function initialiseCustomTime() {
        const now = new Date();
        const hour24 = now.getHours();
        document.getElementById('peHour').value = String((hour24 % 12) || 12);
        document.getElementById('peMinute').value = String(now.getMinutes());
        document.getElementById('peAmPm').value = hour24 < 12 ? 'AM' : 'PM';
    }

    function paintPayMethod() {
        const method = document.getElementById('pePayMethod').value;
        const details = method === 'bank' || method === 'cheque';
        document.getElementById('pePayBankWrap').hidden = !details;
        document.getElementById('pePayRefWrap').hidden = !details;
        document.getElementById('pePayRefLabel').textContent = method === 'cheque' ? 'Cheque no.' : 'Account no.';
        if (!details) {
            document.getElementById('pePayBank').value = '';
            document.getElementById('pePayRef').value = '';
        }
    }

    function refreshDepartment() {
        const needsDepartment = type.value === 'withdraw' && contra.selectedOptions[0]?.dataset.type === 'expense';
        const companyId = bank.selectedOptions[0]?.dataset.company || '';
        const wrap = document.getElementById('peDepartmentWrap');
        wrap.hidden = !needsDepartment;
        department.disabled = !needsDepartment;
        department.setAttribute('aria-required', needsDepartment ? 'true' : 'false');
        [...department.options].forEach(option => {
            if (!option.value) return;
            option.disabled = !companyId || option.dataset.company !== companyId;
        });
        if (!needsDepartment || department.selectedOptions[0]?.disabled) department.value = '';
        if (window.jQuery?.fn?.select2) window.jQuery(department).trigger('change.select2');
    }

    function paintDirection() {
        const incoming = type.value === 'deposit';
        document.getElementById('peBankLabel').innerHTML = (incoming ? 'Receive into' : 'Pay from') + ' bank / card / cash account <span>*</span>';
        document.getElementById('peContraLabel').innerHTML = (incoming ? 'Received from' : 'Paid to') + ' balance / contra account <span>*</span>';
        document.getElementById('peBeneficiaryLabel').textContent = incoming ? 'Received from' : 'Paid to';
        document.getElementById('pePayMethodLabel').textContent = incoming ? 'Received via' : 'Paid via';
    }

    document.querySelectorAll('.pe-mode').forEach(button => button.addEventListener('click', function () {
        type.value = this.dataset.type;
        document.querySelectorAll('.pe-mode').forEach(item => {
            item.classList.toggle('is-active', item === this);
            item.setAttribute('aria-pressed', item === this ? 'true' : 'false');
        });
        paintDirection();
        refreshReference();
        refreshDepartment();
        updatePostingOverview();
    }));

    function refreshBankContext() {
        const option = bank.selectedOptions[0];
        document.getElementById('peBalance').textContent = option?.value
            ? 'Current balance: ৳' + money(option.dataset.balance)
            : 'Choose the account where money will move.';
        document.getElementById('peCompany').value = option?.dataset.companyName || 'Select a money account first';
        [...contra.options].forEach(item => item.disabled = option?.dataset.account && item.value === option.dataset.account);
        if (contra.selectedOptions[0]?.disabled) {
            contra.value = '';
            if (window.jQuery?.fn?.select2) window.jQuery(contra).trigger('change.select2');
        }
        refreshDepartment();
        updatePostingOverview();
    }
    if (window.jQuery?.fn?.select2) {
        window.jQuery(bank).on('change.paymentEntry', refreshBankContext);
        window.jQuery(contra).on('change.paymentEntry', refreshDepartment);
    } else {
        bank.addEventListener('change', refreshBankContext);
        contra.addEventListener('change', refreshDepartment);
    }
    document.getElementById('pePayMethod').addEventListener('change', paintPayMethod);
    form.addEventListener('input', updatePostingOverview);
    form.addEventListener('change', updatePostingOverview);

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        error.classList.remove('is-open');
        if (!form.reportValidity() || !bank.value || !contra.value) return;
        if (!department.disabled && !department.value) {
            error.textContent = 'Choose the department responsible for this expense payment.';
            error.classList.add('is-open');
            document.getElementById('peDepartmentWrap').scrollIntoView({behavior:'smooth', block:'center'});
            return;
        }
        const charge = Number(document.getElementById('peCharge').value || 0);
        const vat = Number(document.getElementById('peVat').value || 0);
        if (charge > 0 && !document.getElementById('peChargeAccount').value) {
            error.textContent = 'Choose the account where the bank charge will be posted.';
            error.classList.add('is-open');
            return;
        }
        if (vat > 0 && !document.getElementById('peVatAccount').value) {
            error.textContent = 'Choose the account where VAT will be posted.';
            error.classList.add('is-open');
            return;
        }
        const incoming = type.value === 'deposit';
        const rows = [
            ['Money account', bank.selectedOptions[0].dataset.name],
            ['Balance account', contra.selectedOptions[0].dataset.name],
            ['Amount', '৳' + money(document.getElementById('peAmount').value) + (charge ? ' + ৳' + money(charge) + ' charge' : '') + (vat ? ' + ৳' + money(vat) + ' VAT' : '')],
            ['Date & time', document.getElementById('peDate').value + ' · ' + customTimeLabel()],
            ['Reference', document.getElementById('peReference').value || 'Auto-generated'],
            ['Purpose', purposeValue() || '—'],
        ];
        Swal.fire({
            icon:'question',
            title:'Post this ' + (incoming ? 'Payment In' : 'Payment Out') + '?',
            html:'<div style="text-align:left;font-size:13px">' + rows.map(r => '<div style="display:flex;justify-content:space-between;gap:16px;padding:6px 0;border-bottom:1px solid #e2e8f0"><span style="color:#64748b">' + escapeHtml(r[0]) + '</span><b style="text-align:right">' + escapeHtml(r[1]) + '</b></div>').join('') + '</div>',
            showCancelButton:true,
            confirmButtonText:'Confirm & post',
            cancelButtonText:'Back to edit',
            confirmButtonColor:'#16a34a',
            focusCancel:true
        }).then(result => { if (result.isConfirmed) postEntry(); });
    });

    async function postEntry() {
        const postButton = document.getElementById('pePost');
        postButton.disabled = true;
        error.classList.remove('is-open');
        try {
            const transactionUrl = @json(route('role.banks.transaction', ['role' => $role, 'bank' => '__BANK__'])).replace('__BANK__', bank.value);
            const response = await fetch(transactionUrl, {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
                body:JSON.stringify({
                    entry_context:'payment_entry',
                    type:type.value,
                    amount:document.getElementById('peAmount').value,
                    date:document.getElementById('peDate').value,
                    time:customTimeValue(),
                    contra_account_id:contra.value,
                    expense_department_id:department.disabled ? null : department.value,
                    reference:document.getElementById('peReference').value,
                    beneficiary:party.selectedOptions[0]?.dataset.name || '',
                    beneficiary_party_id:party.value || null,
                    purpose:purposeValue(),
                    transaction_kind:'payment',
                    allocation_mode:'unallocated',
                    pay_method:document.getElementById('pePayMethod').value || null,
                    pay_bank:document.getElementById('pePayBank').value,
                    pay_ref:document.getElementById('pePayRef').value,
                    charge_amount:document.getElementById('peCharge').value || 0,
                    charge_account_id:document.getElementById('peChargeAccount').value || null,
                    vat_amount:document.getElementById('peVat').value || 0,
                    vat_account_id:document.getElementById('peVatAccount').value || null
                })
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'The entry could not be posted.');
            await Swal.fire({icon:'success',title:'Payment posted',text:result.message || 'The journal and account balance were updated.',confirmButtonColor:'#16a34a'});
            window.location.reload();
        } catch (exception) {
            error.textContent = exception.message || 'Network error. Please try again.';
            error.classList.add('is-open');
            error.scrollIntoView({behavior:'smooth', block:'center'});
        } finally {
            postButton.disabled = false;
        }
    }
    refreshBankContext();
    initialiseCustomTime();
    paintPayMethod();
    refreshReference();
    updatePostingOverview();
})();
</script>
@endsection
