@php $role = $role ?? Str::slug(Auth::user()->getRoleNames()->first()); @endphp
// The ledger pages lock the party type: search, load and export all stay
// inside it. The dashboard has no statement panel and never includes this.
const LOCKED_PARTY_TYPE = @json($type ?? null);
// The rows in the party table above the statement (ledger pages only), so
// picking one there and picking one from the search box are the same act.
const PARTY_LIST = @json(isset($parties) ? $parties : []);
const SEARCH_URL  = {{ Illuminate\Support\Js::from(route('role.party-statement.search',  ['role' => $role, 'company' => request('company'), 'project' => request('project')])) }};
const LOAD_URL    = {{ Illuminate\Support\Js::from(route('role.party-statement.load',    ['role' => $role, 'company' => request('company'), 'project' => request('project')])) }};
const TRANSACTION_DATE_URL = {{ Illuminate\Support\Js::from(url($role.'/party-statement/transactions')) }};
const PAYMENT_URL = {{ Illuminate\Support\Js::from(route('role.party-statement.payment', ['role' => $role, 'company' => request('company')])) }};
const UNPAID_INVOICES_URL = {{ Illuminate\Support\Js::from(route('role.party-statement.unpaid-invoices', ['role' => $role, 'company' => request('company'), 'project' => request('project')])) }};
const REFUND_URL = @json(\Illuminate\Support\Facades\Route::has('role.party-statement.vendor-refund') ? route('role.party-statement.vendor-refund', ['role' => $role, 'company' => request('company')]) : null);
// 'payment' or 'refund' — the one modal serves both (refund = a vendor paying us back).
let pmKind = 'payment';
const INVOICE_URL = {{ Illuminate\Support\Js::from(route('role.party-statement.invoice', ['role' => $role, 'company' => request('company')])) }};
const OPENING_BALANCE_URL = {{ Illuminate\Support\Js::from(route('role.party-statement.opening-balance', ['role' => $role, 'company' => request('company')])) }};
const EXCEL_URL   = {{ Illuminate\Support\Js::from(route('role.party-statement.excel',   ['role' => $role, 'company' => request('company'), 'project' => request('project')])) }};
const PDF_URL     = {{ Illuminate\Support\Js::from(route('role.party-statement.pdf',     ['role' => $role, 'company' => request('company'), 'project' => request('project')])) }};
const appendQuery = (url, query) => url + (url.includes('?') ? '&' : '?') + query;
// The sales invoice PDF and its window's list (2026-10-14). Route::has(): on a
// server that lags the repo the Invoice button keeps its old meaning (Create Invoice).
const SALES_INVOICE_URL = {{ Illuminate\Support\Js::from(\Illuminate\Support\Facades\Route::has('role.party-statement.sales-invoice') ? route('role.party-statement.sales-invoice', ['role' => $role, 'company' => request('company'), 'project' => request('project')]) : null) }};
const INVOICE_BRANDING_URL = {{ Illuminate\Support\Js::from(\Illuminate\Support\Facades\Route::has('role.party-statement.invoice-branding') ? route('role.party-statement.invoice-branding', ['role' => $role, 'company' => request('company')]) : null) }};
const SALES_INVOICE_LINES_URL = {{ Illuminate\Support\Js::from(\Illuminate\Support\Facades\Route::has('role.party-statement.sales-invoice-lines') ? route('role.party-statement.sales-invoice-lines', ['role' => $role, 'company' => request('company'), 'project' => request('project')]) : null) }};
const SEND_STATEMENT_URL = {{ Illuminate\Support\Js::from(route('role.party-statement.send-statement', ['role' => $role])) }};
const SEND_REMINDER_URL  = {{ Illuminate\Support\Js::from(route('role.party-statement.send-reminder',  ['role' => $role])) }};
const INVOICE_PDF_BASE = "/{{ $role }}/party-statement/invoice/";
const TS_DETAIL_BASE = "{{ url($role . '/ticket-sales') }}";
// Manage Bank, for a voucher that has a bank entry behind it (row.bank_entry,
// resolved by PartyStatementController::bankEntriesFor). Route::has(): if the
// route is ever missing the voucher stays plain text rather than the page
// failing to render.
const BANK_DASHBOARD_URL = @json(\Illuminate\Support\Facades\Route::has('role.banks.dashboard') ? route('role.banks.dashboard', ['role' => $role]) : null);
function bankEntryUrl(t) {
    if (!BANK_DASHBOARD_URL || !t || !t.bank_entry) return null;
    return `${BANK_DASHBOARD_URL}?bank=${encodeURIComponent(t.bank_entry.bank_id)}&entry=${encodeURIComponent(t.bank_entry.entry_id)}`;
}
const CSRF = "{{ csrf_token() }}";
const DISCOUNT_URL = {{ Illuminate\Support\Js::from(route('role.party-statement.discounts', ['role' => $role, 'company' => request('company'), 'project' => request('project')])) }};
let discountDocuments = [], discountRequestKey = null, discountBusy = false, discountMode = 'single';
function setDiscountMode(mode) {
    discountMode = mode;
    const overall = mode === 'overall';
    document.getElementById('discountSingleBox').classList.toggle('hidden', overall);
    document.getElementById('discountOverallBox').classList.toggle('hidden', !overall);
    document.getElementById('discountDocument').required = !overall;
    [['discountModeSingle', !overall], ['discountModeOverall', overall]].forEach(([id, on]) => {
        document.getElementById(id).className = 'py-2 rounded-lg transition ' + (on ? 'bg-white text-violet-700 shadow-sm' : 'text-gray-500 hover:text-gray-700');
    });
    discountPreview();
}
// Mirrors PartyDiscountService::postOverall — oldest invoice first, the rest stays as party credit.
function discountPreview() {
    const box = document.getElementById('discountSplit');
    if (discountMode !== 'overall') return;
    let left = Math.round(Number(document.getElementById('discountAmount').value || 0) * 100) / 100;
    if (left <= 0) { box.textContent = 'Enter an amount to see how it is split.'; return; }
    const company = new URL(DISCOUNT_URL, location.origin).searchParams.get('company');
    const open = discountDocuments.filter(d => Number(d.due) >= 0.01);
    if (!company && new Set(open.map(d => d.company)).size > 1) { box.textContent = 'This party has dues in more than one company — open the statement for one company first.'; return; }
    box.replaceChildren();
    const line = (t, cls) => { const el = document.createElement('div'); el.textContent = t; if (cls) el.className = cls; box.append(el); };
    for (const d of open) {
        if (left < 0.01) break;
        const take = Math.min(left, Number(d.due));
        line(d.label + '  →  ৳' + take.toLocaleString());
        left = Math.round((left - take) * 100) / 100;
    }
    if (left >= 0.01) line('Kept as party credit  →  ৳' + left.toLocaleString(), 'font-semibold');
}
function discountUuid() {
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c => (Number(c) ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> Number(c) / 4).toString(16));
}
async function discountFetch(url, body = null) {
    const response = await fetch(url, {method: body ? 'POST' : 'GET', headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF}, ...(body ? {body:JSON.stringify(body)} : {})});
    const data = await response.json();
    if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Discount request failed.');
    return data;
}
async function openDiscountModal() {
    if (!selectedParty || !document.getElementById('discountModal')) return;
    const partyId = selectedParty.id;
    try {
        const data = await discountFetch(appendQuery(DISCOUNT_URL, `party_id=${partyId}`));
        if (selectedParty?.id !== partyId) return;
        discountDocuments = data.documents;
        discountRequestKey = discountUuid();
        const select = document.getElementById('discountDocument');
        select.replaceChildren(new Option('Select an invoice / package', ''));
        data.documents.forEach((d, i) => select.add(new Option(`${d.label} — Invoice due ৳${Number(d.due).toLocaleString()}`, String(i))));
        document.getElementById('discountAmount').value = '';
        document.getElementById('discountReason').value = '';
        document.getElementById('discountSubmit').disabled = false;
        document.getElementById('discountDueHint').textContent = data.documents.length ? '' : 'No invoice/package found.';
        setDiscountMode('single');
        document.getElementById('discountModal').classList.remove('hidden');
    } catch (e) { Swal.fire({icon:'error', title:'Error', text:e.message}); }
}
function closeDiscountModal() { if (!discountBusy) document.getElementById('discountModal').classList.add('hidden'); }
function discountDueHint() {
    const value = document.getElementById('discountDocument').value;
    const doc = value === '' ? null : discountDocuments[Number(value)];
    document.getElementById('discountDueHint').textContent = doc ? `Invoice due: ৳${Number(doc.due).toLocaleString()}` : '';
}
async function submitDiscount(event) {
    event.preventDefault();
    if (discountBusy) return;
    const overall = discountMode === 'overall';
    const doc = discountDocuments[Number(document.getElementById('discountDocument').value)];
    if (!overall && !doc) return;
    discountBusy = true; document.getElementById('discountSubmit').disabled = true;
    try {
        const body = {party_id:selectedParty.id, amount:document.getElementById('discountAmount').value, date:document.getElementById('discountDate').value, reason:document.getElementById('discountReason').value, request_key:discountRequestKey};
        const base = DISCOUNT_URL.split('?');
        const res = overall
            ? await discountFetch(`${base[0]}/overall${base[1] ? '?' + base[1] : ''}`, body)
            : await discountFetch(DISCOUNT_URL, {...body, document_type:doc.type, document_id:doc.id});
        discountBusy = false; closeDiscountModal();
        Swal.fire({icon:'success', title:'Discount Posted', text:res.message, timer:1800, showConfirmButton:false});
        setTimeout(() => document.getElementById('loadBtn').click(), 1900);
    } catch (e) { Swal.fire({icon:'error', title:'Error', text:e.message}); }
    finally { discountBusy = false; document.getElementById('discountSubmit').disabled = false; }
}
function discountIdFromTransaction(transaction) {
    const match = String(transaction.reference_no || '').match(/^DISC-(\d+)$/i);
    return match ? Number(match[1]) : null;
}

async function openPartyTransactionCorrection(transactionId) {
    const transaction = (LAST_STATEMENT?.transactions || []).concat(LAST_STATEMENT?.reversed_transactions || []).find(row => Number(row.id) === Number(transactionId));
    if (!transaction) return;
    if (transaction.type !== 'party_discount') {
        editPartyTransactionDate(transactionId);
        return;
    }

    const discountId = discountIdFromTransaction(transaction);
    if (!discountId || !selectedParty) {
        Swal.fire({icon:'error', title:'Unable to open correction', text:'The source discount could not be identified.'});
        return;
    }

    try {
        const data = await discountFetch(appendQuery(DISCOUNT_URL, `party_id=${selectedParty.id}`));
        const source = data.discounts.find(discount => Number(discount.id) === discountId);
        if (!source) throw new Error('The source discount could not be found.');
        if (source.reversed_at) throw new Error('This discount has already been reversed.');

        const related = source.batch_key
            ? data.discounts.filter(discount => discount.batch_key === source.batch_key && !discount.reversed_at)
            : [source];
        const discount = {
            ...source,
            amount: related.reduce((total, row) => total + Number(row.amount || 0), 0),
            row_count: related.length,
        };
        const result = await showDiscountCorrectionOverview(discount, transaction);
        if (!result.isConfirmed) return;

        if (result.value.action === 'date') {
            await Swal.fire({icon:'success', title:'Updated', text:result.value.response.message, timer:1300, showConfirmButton:false});
            document.getElementById('loadBtn').click();
            return;
        }

        const base = DISCOUNT_URL.split('?');
        await discountFetch(`${base[0]}/${discount.batch_key ? 'batch/' + discount.batch_key : discount.id}/reverse${base[1] ? '?' + base[1] : ''}`, result.value.payload);
        Swal.fire({icon:'success', title:'Discount reversed', text:'The party due has been restored; bank and cash remain unchanged.', timer:1800, showConfirmButton:false});
        document.getElementById('loadBtn').click();
    } catch (error) {
        Swal.fire({icon:'error', title:'Unable to correct discount', text:error.message});
    }
}

function showDiscountCorrectionOverview(discount, transaction = null) {
    const money = value => `&#2547;${Number(value || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
    const reference = transaction?.reference_no || `DISC-${discount.id}`;
    const date = discount.discount_date || transaction?.payment_date || '—';
    const displayDate = date === '—' ? date : fmtDate(String(date).slice(0, 10));
    const allocationText = discount.batch_key && discount.row_count > 1
        ? `${discount.row_count} allocations · corrected together`
        : 'Single discount';
    const currentDate = String(transaction?.payment_date || discount.discount_date || '').slice(0, 10);
    const currentTime = String(transaction?.transaction_time || '').slice(0, 5);
    let activeAction = null;

    return Swal.fire({
        title:'', width:880, showCloseButton:true, showCancelButton:true, showConfirmButton:true,
        customClass:{popup:'discount-correction-overview', actions:'discount-overview-actions', confirmButton:'discount-hidden-action', cancelButton:'discount-overview-close'},
        confirmButtonText:'Save correction', cancelButtonText:'Close', showLoaderOnConfirm:true,
        allowOutsideClick:() => !Swal.isLoading(), allowEscapeKey:() => !Swal.isLoading(),
        html:`<style>
            .discount-correction-overview{padding:18px 20px 16px!important;border-radius:15px!important}.discount-correction-overview .swal2-html-container{margin:0!important;overflow:visible!important}.discount-correction-overview .swal2-close{top:10px!important;right:10px!important;width:32px!important;height:32px!important;border-radius:8px!important;color:#64748b!important;font-size:25px!important}.discount-correction-overview .swal2-close:hover{background:#f1f5f9!important;color:#0f172a!important}.discount-overview{font-family:inherit;text-align:left;color:#0f172a}.discount-overview *{box-sizing:border-box}.discount-overview-head{display:flex;justify-content:space-between;gap:14px;padding-right:34px}.discount-overview-kicker{font-size:10px;font-weight:800;letter-spacing:.12em;color:#2563eb;text-transform:uppercase}.discount-overview-head h2{margin:4px 0;font-size:20px;font-weight:500}.discount-overview-subtitle{font-size:11px;color:#64748b}.discount-overview-status{height:max-content;padding:5px 10px;border-radius:999px;background:#ede9fe;color:#6d28d9;font-size:9px;font-weight:800;text-transform:uppercase}.discount-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin:13px 0}.discount-summary>div{padding:9px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px}.discount-summary small{display:block;color:#64748b;font-size:9px;font-weight:800;text-transform:uppercase}.discount-summary b{display:block;margin-top:3px;font-size:11px}.discount-summary .is-money b{color:#7c3aed}.discount-journal{border:1px solid #dbe4ef;border-radius:10px;overflow:hidden}.discount-journal-title{padding:9px 11px;background:#f1f5f9;font-size:11px;font-weight:800}.discount-journal table{width:100%;border-collapse:collapse;font-size:11px}.discount-journal th,.discount-journal td{padding:8px 11px;border-top:1px solid #e2e8f0;text-align:right}.discount-journal th:first-child,.discount-journal td:first-child{text-align:left}.discount-journal th{color:#64748b;font-size:9px}.discount-journal tfoot{font-weight:800}.discount-choices{margin-top:11px;padding:12px;background:#f8fafc;border:1px solid #dbe4ef;border-radius:11px}.discount-choices-label{margin-bottom:8px;color:#64748b;font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.discount-choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.discount-choice{min-height:62px;display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:4px;padding:10px 12px;border:1px solid #dbe4ef;border-radius:9px;background:#fff;color:#334155;text-align:left;font:inherit;cursor:pointer;transition:border-color .15s,box-shadow .15s,transform .15s}.discount-choice:hover{border-color:#93c5fd;box-shadow:0 5px 14px rgba(37,99,235,.09);transform:translateY(-1px)}.discount-choice b{font-size:12px}.discount-choice small{color:#64748b;font-size:10px}.discount-choice--reverse{border-color:#fecaca;color:#b91c1c}.discount-choice--reverse small{color:#dc2626}.discount-hidden-action{display:none!important}.discount-overview-actions{width:100%!important;justify-content:flex-end!important;margin:16px 0 0!important;padding-top:14px!important;border-top:1px solid #e2e8f0}.discount-overview-close{margin:0!important;padding:9px 18px!important;border-radius:8px!important;background:#64748b!important;font-size:11px!important;font-weight:800!important}@media(max-width:640px){.discount-summary{grid-template-columns:1fr 1fr}.discount-choice-grid{grid-template-columns:1fr}}
            .discount-panel{display:none}.discount-panel.is-active{display:block}.discount-panel-back{margin:2px 0 13px;padding:0;border:0;background:none;color:#2563eb;font-size:11px;font-weight:800;cursor:pointer}.discount-form-heading{display:flex;align-items:center;gap:10px;padding:13px 15px;border:1px solid #dbeafe;border-radius:10px;background:#f8fbff}.discount-form-heading.is-danger{border-color:#fecaca;background:#fff1f2;color:#991b1b}.discount-form-heading span{display:flex;flex-direction:column;gap:3px}.discount-form-heading small{color:#64748b;font-size:10px}.discount-form-card{margin-top:13px;padding:15px;border:1px solid #dbe4ef;border-radius:11px}.discount-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:11px}.discount-field{color:#334155;font-size:9px;font-weight:800;text-transform:uppercase}.discount-field span{float:right;color:#94a3b8;font-weight:400;text-transform:none}.discount-field input,.discount-field textarea{box-sizing:border-box;width:100%;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px;padding:9px 10px;font:inherit;font-size:11px}.discount-field input{height:39px}.discount-field textarea{min-height:72px;resize:vertical}.discount-field--wide,.discount-confirm{grid-column:1/-1}.discount-confirm{padding:11px;border:1px dashed #fca5a5;border-radius:9px;background:#fff7f7}.discount-save-action{display:inline-block!important;margin:0 0 0 8px!important;padding:9px 18px!important;border-radius:8px!important;background:#2563eb!important;font-size:11px!important;font-weight:800!important}.discount-save-action.is-danger{background:#dc2626!important}@media(max-width:640px){.discount-form-grid{grid-template-columns:1fr}}
        </style><div class="discount-overview">
            <div class="discount-overview-head"><div><div class="discount-overview-kicker">Discount correction workspace</div><h2>${esc(reference)}</h2><div class="discount-overview-subtitle">${esc(LAST_STATEMENT?.party?.name || '')} · Discount allowed</div></div><span class="discount-overview-status">Active</span></div>
            <div id="discountOverviewPanel"><div class="discount-summary"><div class="is-money"><small>Discount</small><b>${money(discount.amount)}</b></div><div><small>Date</small><b>${esc(displayDate)}</b></div><div><small>Status</small><b>Active</b></div><div><small>Scope</small><b>${esc(allocationText)}</b></div></div>
            <div class="discount-journal"><div class="discount-journal-title">Current journal posting</div><table><thead><tr><th>ACCOUNT</th><th>DEBIT</th><th>CREDIT</th></tr></thead><tbody><tr><td>Sales Discounts &amp; Allowances</td><td>${money(discount.amount)}</td><td>—</td></tr><tr><td>Accounts Receivable</td><td>—</td><td>${money(discount.amount)}</td></tr></tbody><tfoot><tr><td>Balanced total</td><td>${money(discount.amount)}</td><td>${money(discount.amount)}</td></tr></tfoot></table></div>
            <div class="discount-choices"><div class="discount-choices-label">Choose a correction</div><div class="discount-choice-grid"><button type="button" class="discount-choice" data-discount-action="date"><b><i class="fas fa-calendar-day"></i> Date &amp; time</b><small>Correct posting order</small></button><button type="button" class="discount-choice discount-choice--reverse" data-discount-action="reverse"><b><i class="fas fa-rotate-left"></i> Reverse</b><small>Post opposite entry</small></button></div></div>
            </div><div id="discountDatePanel" class="discount-panel"><button type="button" class="discount-panel-back"><i class="fas fa-arrow-left"></i> Back to overview</button><div class="discount-form-heading"><i class="fas fa-calendar-check"></i><span><b>Correct date &amp; time</b><small>The discount record, party ledger and linked journal move together.</small></span></div><div class="discount-form-card"><div class="discount-form-grid"><label class="discount-field">Posting date *<input id="discountCorrectionDate" type="date" value="${esc(currentDate)}"></label><label class="discount-field">Posting time *<input id="discountCorrectionTime" type="time" value="${esc(currentTime)}"></label><label class="discount-field discount-field--wide">Correction reason *<span>Permanent audit history</span><textarea id="discountDateReason" maxlength="500"></textarea></label><div class="discount-confirm"><label class="discount-field">Final confirmation *<span>Type CONFIRM</span><input id="discountDateConfirm" autocomplete="off" placeholder="Type CONFIRM to continue"></label></div></div></div></div>
            <div id="discountReversePanel" class="discount-panel"><button type="button" class="discount-panel-back"><i class="fas fa-arrow-left"></i> Back to overview</button><div class="discount-form-heading is-danger"><i class="fas fa-rotate-left"></i><span><b>Reverse discount / credit note</b><small>Restore the party due without moving bank or cash.</small></span></div><div class="discount-journal" style="margin-top:13px"><div class="discount-journal-title">Reversal posting</div><table><tbody><tr><td>Accounts Receivable</td><td>${money(discount.amount)}</td><td>—</td></tr><tr><td>Sales Discounts &amp; Allowances</td><td>—</td><td>${money(discount.amount)}</td></tr></tbody></table></div><div class="discount-form-card"><div class="discount-form-grid"><label class="discount-field discount-field--wide">Reversal reason *<span>Permanent audit history</span><textarea id="discountReverseReason" maxlength="500"></textarea></label><div class="discount-confirm"><label class="discount-field">Final confirmation *<span>Type CONFIRM</span><input id="discountReverseConfirm" autocomplete="off" placeholder="Type CONFIRM to reverse"></label></div></div></div></div>
        </div>`,
        didOpen:() => {
            const overview = document.getElementById('discountOverviewPanel');
            const confirm = Swal.getConfirmButton();
            const showPanel = action => {
                activeAction = action;
                overview.style.display = action ? 'none' : '';
                document.getElementById('discountDatePanel').classList.toggle('is-active', action === 'date');
                document.getElementById('discountReversePanel').classList.toggle('is-active', action === 'reverse');
                confirm.classList.toggle('discount-hidden-action', !action);
                confirm.classList.toggle('discount-save-action', Boolean(action));
                confirm.classList.toggle('is-danger', action === 'reverse');
                confirm.textContent = action === 'reverse' ? 'Reverse discount' : 'Save correction';
            };
            document.querySelector('[data-discount-action="date"]')?.addEventListener('click', () => showPanel('date'));
            document.querySelector('[data-discount-action="reverse"]')?.addEventListener('click', () => showPanel('reverse'));
            document.querySelectorAll('.discount-panel-back').forEach(button => button.addEventListener('click', () => showPanel(null)));
        },
        preConfirm:async () => {
            if (activeAction === 'date') {
                const dateValue = document.getElementById('discountCorrectionDate').value;
                const time = document.getElementById('discountCorrectionTime').value;
                const note = document.getElementById('discountDateReason').value.trim();
                const confirmation = document.getElementById('discountDateConfirm').value;
                if (!dateValue || !time || note.length < 5 || confirmation !== 'CONFIRM') {
                    Swal.showValidationMessage('Select date and time, enter a reason of at least 5 characters, and type CONFIRM exactly.');
                    return false;
                }
                try {
                    const response = await fetch(`${TRANSACTION_DATE_URL}/${transaction.id}/date`, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify({date:dateValue, time, note, confirmation})});
                    const data = await response.json();
                    if (!response.ok || data.success === false) throw new Error(data.message || 'Correction failed.');
                    return {action:'date', response:data};
                } catch (error) {
                    Swal.showValidationMessage(error.message || 'Network error.');
                    return false;
                }
            }
            const reason = document.getElementById('discountReverseReason').value.trim();
            const confirmation = document.getElementById('discountReverseConfirm').value;
            if (reason.length < 5 || confirmation !== 'CONFIRM') {
                Swal.showValidationMessage('Enter a reason of at least 5 characters and type CONFIRM exactly.');
                return false;
            }
            return {action:'reverse', payload:{reason, confirmation}};
        },
    });
}

function showDiscountReversalWorkspace(discount, transaction = null) {
    const money = value => `৳${Number(value || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
    const reference = transaction?.reference_no || `DISC-${discount.id}`;
    const date = discount.discount_date || transaction?.payment_date || '—';
    const batchNote = discount.batch_key && discount.row_count > 1
        ? `<div class="ptw-batch"><i class="fas fa-layer-group"></i><span>This overall discount has <b>${discount.row_count} allocations</b>. They will be reversed together.</span></div>`
        : '';

    return Swal.fire({
        title:'', width:790, showCloseButton:true, showCancelButton:true,
        customClass:{popup:'party-date-workspace',actions:'party-date-actions',confirmButton:'party-discount-reverse',cancelButton:'party-date-cancel'},
        confirmButtonText:'Reverse discount', cancelButtonText:'Close', confirmButtonColor:'#dc2626',
        showLoaderOnConfirm:true, allowOutsideClick:() => !Swal.isLoading(),
        html:`<style>
            .party-date-workspace{padding:0!important;border-radius:14px!important;overflow:hidden}.party-date-workspace .swal2-html-container{margin:0!important;padding:0!important;overflow:visible!important}.party-date-workspace .swal2-close{top:14px!important;right:16px!important;width:30px!important;height:30px!important;border-radius:8px!important;color:#64748b!important;font-size:25px!important}.party-date-workspace .swal2-close:hover{background:#e2e8f0!important;color:#0f172a!important}
            .ptw-head{padding:28px 42px 14px;text-align:left;background:#fff}.ptw-head-copy{min-width:0;padding-right:35px}.ptw-kicker{color:#2563eb;font-size:9px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.ptw-head h2{margin:5px 0 3px;color:#172033;font-size:19px;font-weight:500;line-height:1.2}.ptw-head p{margin:0;color:#64748b;font-size:10px}.ptw-back{display:inline-flex;align-items:center;gap:5px;margin-top:8px;border:0;background:none;padding:0;color:#2563eb;font-size:10px;font-weight:700;cursor:pointer}.ptw-body{padding:0 42px 4px;text-align:left}.ptw-alert{display:flex;align-items:center;gap:12px;padding:12px 15px;border:1px solid #bfdbfe;border-radius:10px;background:#f8fbff;color:#1e293b}.ptw-alert-icon{display:flex;align-items:center;justify-content:center;flex:0 0 36px;height:36px;border-radius:9px;background:#dbeafe;color:#2563eb;font-size:15px}.ptw-alert b{display:block;font-size:13px;font-weight:500}.ptw-alert small{display:block;margin-top:3px;color:#64748b;font-size:9px;font-weight:400}
            .ptw-card{margin-top:14px;padding:16px;border:1px solid #dbe4ef;border-radius:11px;background:#fff}.ptw-field{display:block;color:#334155;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}.ptw-field span{float:right;color:#94a3b8;font-size:9px;font-weight:400;text-transform:none;letter-spacing:0}.ptw-field input,.ptw-field textarea{box-sizing:border-box;display:block;width:100%;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-family:inherit;font-size:11px;font-weight:400;outline:none}.ptw-field input{height:39px;padding:0 10px}.ptw-field textarea{min-height:74px;padding:10px;resize:vertical;line-height:1.45}.ptw-field input:focus,.ptw-field textarea:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}.ptw-confirm{margin-top:12px;padding:11px 12px;border:1px solid #dbe4ef;border-radius:9px;background:#f8fafc}.ptw-confirm code{padding:2px 5px;border-radius:4px;background:#e2e8f0;color:#334155;font-weight:600}
            .party-date-actions{justify-content:flex-end!important;width:100%!important;box-sizing:border-box!important;margin:16px 0 0!important;padding:14px 24px!important;border-top:1px solid #e2e8f0;background:#f8fafc}.party-date-actions button{margin:0 0 0 8px!important;padding:9px 17px!important;border-radius:8px!important;font-size:11px!important;font-weight:800!important;box-shadow:none!important}.party-date-cancel{border:1px solid #cbd5e1!important;background:#fff!important;color:#475569!important}
            .party-discount-reverse{background:#dc2626!important}.party-discount-reverse:hover{background:#b91c1c!important}
            .ptw-money-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-top:14px}.ptw-money-box{padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}.ptw-money-box small{display:block;color:#64748b;font-size:9px;font-weight:800;text-transform:uppercase}.ptw-money-box b{display:block;margin-top:4px;color:#172033;font-size:13px}.ptw-money-box.is-money b{color:#dc2626}
            .ptw-posting{margin-top:13px;border:1px solid #dbe4ef;border-radius:10px;overflow:hidden}.ptw-posting h4{margin:0;padding:9px 11px;background:#f1f5f9;font-size:10px}.ptw-posting table{width:100%;border-collapse:collapse;font-size:10px}.ptw-posting th,.ptw-posting td{padding:8px 11px;border-top:1px solid #e2e8f0;text-align:right}.ptw-posting th:first-child,.ptw-posting td:first-child{text-align:left}.ptw-danger{margin-top:14px;padding:13px;border:1px solid #fecaca;border-radius:10px;background:#fff1f2;color:#991b1b;font-size:10px;line-height:1.5}.ptw-danger b{display:block;margin-bottom:2px;font-size:11px}.ptw-batch{display:flex;gap:8px;margin-top:10px;padding:10px;border:1px solid #fde68a;border-radius:9px;background:#fffbeb;color:#92400e;font-size:10px}.ptw-confirm.is-danger{border-color:#fecaca;background:#fff7f7}.ptw-confirm.is-danger code{background:#fee2e2;color:#991b1b}
            @media(max-width:640px){.ptw-head,.ptw-body{padding-left:16px;padding-right:16px}.ptw-money-grid{grid-template-columns:1fr}.party-date-actions{padding-left:16px!important;padding-right:16px!important}}
        </style>
        <div class="ptw-head"><div class="ptw-head-copy"><div class="ptw-kicker">Party ledger correction workspace</div><h2>${esc(reference)}</h2><p>${esc(LAST_STATEMENT?.party?.name || '')} · Discount allowed</p>${transaction ? `<button type="button" class="ptw-back" onclick="Swal.close(); openPartyTransactionCorrection(${Number(transaction.id)})"><i class="fas fa-arrow-left"></i> Back to overview</button>` : ''}</div></div>
        <div class="ptw-body">
            <div class="ptw-alert"><span class="ptw-alert-icon"><i class="fas fa-rotate-left"></i></span><span><b>Reverse discount / credit note</b><small>Restore the party due without moving money through bank or cash.</small></span></div>
            <div class="ptw-money-grid"><div class="ptw-money-box is-money"><small>Discount</small><b>${money(discount.amount)}</b></div><div class="ptw-money-box"><small>Date</small><b>${esc(date)}</b></div><div class="ptw-money-box"><small>Current status</small><b>Active</b></div></div>
            ${batchNote}
            <div class="ptw-posting"><h4>Reversal posting</h4><table><thead><tr><th>Account</th><th>Debit</th><th>Credit</th></tr></thead><tbody><tr><td>Accounts Receivable</td><td>${money(discount.amount)}</td><td>—</td></tr><tr><td>Sales Discounts &amp; Allowances</td><td>—</td><td>${money(discount.amount)}</td></tr></tbody></table></div>
            <div class="ptw-danger"><b><i class="fas fa-triangle-exclamation"></i> Permanent accounting action</b>The original discount remains in audit history. An equal-and-opposite entry will restore the invoice or package due.</div>
            <div class="ptw-card"><label class="ptw-field">Reversal reason *<span>Saved permanently in audit history</span><textarea id="discountReverseReason" maxlength="500" placeholder="Explain why this discount must be reversed"></textarea></label>
            <div class="ptw-confirm is-danger"><label class="ptw-field">Final confirmation *<span>Type <code>CONFIRM</code></span><input id="discountReverseConfirm" autocomplete="off" placeholder="Type CONFIRM to reverse"></label></div></div>
        </div>`,
        preConfirm:() => {
            const reason = document.getElementById('discountReverseReason').value.trim();
            const confirmation = document.getElementById('discountReverseConfirm').value;
            if (reason.length < 5 || confirmation !== 'CONFIRM') {
                Swal.showValidationMessage('Enter a reason of at least 5 characters and type CONFIRM exactly.');
                return false;
            }
            return {reason, confirmation};
        },
    });
}

let selectedParty = null;
let lastLoadParams = {};
let searchTimer   = null;
const ticketDetailCache = {}; // invoice_id -> rendered HTML, so re-expanding a row doesn't re-fetch

// ── Badge helpers ──────────────────────────────────────────────────────────────
const typeBadge = {
    party_discount: {label:'Discount allowed', cls:'bg-violet-100 text-violet-700'},
    party_discount_reversal: {label:'Discount reversed', cls:'bg-amber-100 text-amber-700'},
    ticket_package_inclusion: { label: 'Package adjustment', cls: 'bg-violet-100 text-violet-700' },
    party_payment: { label: 'Payment',   cls: 'bg-emerald-100 text-emerald-700' },
    party_payment_reversal: { label: 'Payment reversal', cls: 'bg-amber-100 text-amber-700' },
    customer_advance_refund: { label: 'Advance returned', cls: 'bg-rose-100 text-rose-700' },
    customer_advance_refund_reversal: { label: 'Advance return reversal', cls: 'bg-amber-100 text-amber-700' },
    customer_service_refund: { label: 'Service refund paid', cls: 'bg-rose-100 text-rose-700' },
    customer_service_refund_reversal: { label: 'Service refund reversal', cls: 'bg-amber-100 text-amber-700' },
    customer_advance_received_reversal: { label: 'Advance reversal', cls: 'bg-amber-100 text-amber-700' },
    supplier_advance_paid_reversal: { label: 'Advance reversal', cls: 'bg-amber-100 text-amber-700' },
    bank_transfer:  { label: 'Payment',   cls: 'bg-emerald-100 text-emerald-700' },
    party_invoice:  { label: 'Invoice',   cls: 'bg-blue-100   text-blue-700' },
    ticket_sale:    { label: 'Ticket',    cls: 'bg-blue-100   text-blue-700' },
    flight_sale:    { label: 'Flight',    cls: 'bg-sky-100    text-sky-700' },
    file_sale:      { label: 'File',      cls: 'bg-teal-100   text-teal-700' },
    visa_sale:      { label: 'Visa',      cls: 'bg-purple-100 text-purple-700' },
    sale:           { label: 'Invoice',   cls: 'bg-blue-100   text-blue-700' },
    purchase:       { label: 'Purchase',  cls: 'bg-violet-100 text-violet-700' },
    adjustment:     { label: 'Adj.',      cls: 'bg-amber-100  text-amber-700' },
    opening_balance:{ label: 'Opening',   cls: 'bg-slate-100  text-slate-700' },
};
function txBadge(type) {
    const b = typeBadge[type] || { label: type.replace(/_/g,' '), cls: 'bg-gray-100 text-gray-600' };
    return `<span class="inline-flex px-2 py-0.5 text-[10px] font-semibold rounded-full ${b.cls} capitalize">${b.label}</span>`;
}

// ── Ticket sale passenger/route/ticket breakdown (on-demand) ────────────────────
// Same shape as the "Manage Sales" invoice modal (tsShowDetail) — reused here via
// the ticket-sales detail endpoint so passenger/route data isn't duplicated.
function toggleTicketDetail(txId, invoiceId) {
    const row  = document.getElementById('tx-detail-' + txId);
    const body = document.getElementById('tx-detail-body-' + txId);
    const btn  = document.getElementById('tx-toggle-' + txId);
    if (!row) return;

    const willShow = row.classList.contains('hidden');
    row.classList.toggle('hidden');
    if (btn) {
        const icon = btn.querySelector('i');
        icon.classList.toggle('fa-chevron-down', !willShow);
        icon.classList.toggle('fa-chevron-up', willShow);
    }
    if (!willShow) return; // just collapsed — nothing more to do

    if (ticketDetailCache[invoiceId]) {
        body.innerHTML = ticketDetailCache[invoiceId];
        return;
    }

    body.innerHTML = '<div class="text-xs text-gray-400 py-2"><i class="fas fa-spinner fa-spin mr-1"></i>Loading passengers…</div>';

    $.ajax({
        url: TS_DETAIL_BASE + '/' + invoiceId + '/detail',
        method: 'GET',
        dataType: 'json',
        success: function (d) {
            if (!d.success) { body.innerHTML = '<div class="text-xs text-red-400 py-2">Failed to load ticket details.</div>'; return; }

            var rows = (d.items || []).map(function (it) {
                var route = (it.from || it.to) ? ((it.from || '—') + ' → ' + (it.to || '—')) : '—';
                return '<tr class="border-b border-blue-100/60 last:border-0">'
                    + '<td class="py-1.5 pl-3 pr-3 text-xs"><span class="font-mono font-bold text-blue-700">' + (it.ticket_no || '—') + '</span><br><span class="text-gray-500">' + (it.passenger || '—') + '</span></td>'
                    + '<td class="py-1.5 pr-3 text-xs text-gray-600">' + (it.route || '—') + '</td>'
                    + '<td class="py-1.5 pr-3 text-xs text-gray-600">' + route + '</td>'
                    + '<td class="py-1.5 pr-3 text-xs text-right font-semibold text-gray-700">' + (d.currency || '৳') + Number(it.price).toLocaleString() + '</td>'
                    + '</tr>';
            }).join('');

            var html = ''
                + '<div class="rounded-lg border border-blue-100 overflow-hidden bg-white">'
                + '  <table class="w-full">'
                + '    <thead><tr class="bg-blue-50">'
                + '      <th class="py-1.5 pl-3 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">PNR / Passenger</th>'
                + '      <th class="py-1.5 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">Ticket</th>'
                + '      <th class="py-1.5 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">Route</th>'
                + '      <th class="py-1.5 pr-3 text-right text-[10px] font-semibold text-blue-700 uppercase">Price</th>'
                + '    </tr></thead>'
                + '    <tbody>' + (rows || '<tr><td colspan="4" class="text-center text-gray-400 text-xs py-3">No ticket items found.</td></tr>') + '</tbody>'
                + '  </table>'
                + '</div>';

            ticketDetailCache[invoiceId] = html;
            body.innerHTML = html;
        },
        error: function () {
            body.innerHTML = '<div class="text-xs text-red-400 py-2">Failed to load ticket details.</div>';
        }
    });
}
const roleCls = {
    vendor:   'bg-violet-100 text-violet-700',
    agent:    'bg-amber-100  text-amber-700',
    customer: 'bg-blue-100   text-blue-700',
};

// ── Row helpers shared with the bank statement's look ──────────────────────────
// The small grey time under a date, shown only when the row was recorded on the
// day it is dated — same rule as banks/dashboard.blade.php recordedTimeLine().
function recordedTimeLine(dateYmd, recordedAt, transactionTime = null) {
    if (transactionTime) return `<div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;">${esc(transactionTime)}</div>`;
    const [recDay, recTime] = String(recordedAt || '').split(' ');
    if (!recTime || recDay !== String(dateYmd || '').slice(0, 10)) return '';
    // HH:MM:SS as recorded, the way the bank tables print it.
    return `<div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;">${recTime}</div>`;
}
// Payment-method pill: the bank feed's VIA_PILL plus the two methods only the
// party ledger records. Twin of $viaPill in party-statement/dashboard.blade.php.
const VIA_PILL = {
    cash:           ['Cash',   '#dcfce7', '#15803d'],
    bank_transfer:  ['Bank',   '#dbeafe', '#1e40af'],
    cheque:         ['Cheque', '#fef3c7', '#92400e'],
    card:           ['Card',   '#ede9fe', '#5b21b6'],
    mobile_banking: ['Mobile', '#ffe4e6', '#be123c'],
};
function paidViaCell(t) {
    const pill = VIA_PILL[t.payment_method];
    if (!pill && !t.bank) return '<span class="ps-amt-none">—</span>';
    let html = `<div class="ps-nm">${esc(t.bank || (pill ? pill[0] : ''))}${pill ? `<span class="ps-flow" style="background:${pill[1]};color:${pill[2]};">${pill[0]}</span>` : ''}</div>`;
    if (t.bank_no) html += `<div class="ps-sub">A/C ${esc(t.bank_no)}</div>`;
    return html;
}
// The entry's name — twin of $kindLabel in party-statement/dashboard.blade.php.
const KIND_LABEL = {
    party_discount: 'Discount allowed', party_discount_reversal: 'Discount reversed',
    ticket_package_inclusion: 'Ticket included in package / adjustment',
    contract_file_client: 'File process', contract_file: 'File process cost', contract_file_service_cost: 'File service cost',
    contract_file_client_adjustment: 'File process adjustment', contract_file_vendor_adjustment: 'File cost adjustment',
    party_payment: 'Payment', party_payment_reversal: 'Payment reversal',
    customer_advance_refund: 'Advance returned', customer_advance_refund_reversal: 'Advance return reversal',
    customer_service_refund: 'Service refund paid', customer_service_refund_reversal: 'Service refund reversal',
    customer_advance_received: 'Payment', customer_advance_received_reversal: 'Payment reversal', supplier_advance_paid_reversal: 'Supplier advance reversal',
    bank_transfer: 'Payment', party_invoice: 'Invoice',
    ticket_sale: 'Ticket', ticket_purchase: 'Ticket purchase', flight_sale: 'Flight',
    file_sale: 'File', visa_sale: 'Visa', visa_process: 'Visa processing',
    contract_flight_passenger_agent: 'Flight passenger',
    contract_flight_passenger_change: 'Flight change charge',
    contract_flight_service_cost: 'Flight service cost', opening_balance: 'Opening',
    // 2026-09-28: cancellations add rows instead of editing them.
    contract_flight_passenger_agent_reversal: 'Flight package reversal',
    contract_flight_price_revision: 'Flight package revised',
    contract_flight_airline_penalty: 'Airline penalty',
    contract_flight_fare_difference: 'Fare difference',
    ticket_purchase_refund: 'Ticket cancelled — refund',
    contract_flight_reissue_fee: 'Reissue fee',
    vendor_refund: 'Refund received',
    // 2026-10-06: Ticket Orders → Change (TicketChange::*_TYPE).
    ticket_change_fee: 'Ticket re-issue charge',
    ticket_change_fare: 'Fare difference',
    contract_flight_reissue_fare: 'Fare difference',
    ticket_change_charge: 'Ticket change charge',
    ticket_sale_reversal: 'Ticket sale reversed',
    ticket_refund_summary: 'Ticket refund',
};
// The movement badge. A reversal or a refund due is a credit but NOT money
// received or paid, and a row voided to 0 moved nothing — naming those
// "Received" / "Paid" read as cash that never came (2026-09-28).
function flowWordFor(t, payable) {
    if (t.type === 'party_discount') return 'Discount';
    if (t.type === 'party_discount_reversal') return 'Reversed';
    const dr = Number(t.debit) > 0, cr = Number(t.credit) > 0;
    if (!dr && !cr) return 'Voided';
    if (t.type === 'contract_flight_passenger_agent_reversal') return cr ? 'Reversed' : 'Charged again';
    if (t.type === 'ticket_sale_reversal') return 'Reversed';
    if (t.type === 'contract_flight_price_revision') return 'Reversed';   // a cancelled return lowered the package (2026-10-14)
    if (t.type === 'ticket_change_fee' && cr) return 'Reversed';
    if (t.type === 'ticket_purchase_refund') return 'Refund due';
    if (t.type === 'vendor_refund') return 'Refund in';
    if (t.type === 'customer_advance_refund') return 'Advance out';
    if (t.type === 'customer_service_refund') return 'Refund out';
    if (t.type === 'customer_advance_refund_reversal' || t.type === 'customer_service_refund_reversal') return 'Reversed';
    return dr ? (payable ? 'Bill' : 'Charged') : (payable ? 'Paid' : 'Received');
}
function isInactivePartyTransaction(t) {
    const payable = selectedParty && selectedParty.type === 'vendor';
    return ['Reversed', 'Voided'].includes(flowWordFor(t, payable));
}
function kindLabel(type, t) {
    // A package reversal row that is a DEBIT is the package charged again (re-booked) — not a reversal.
    if (type === 'contract_flight_passenger_agent_reversal' && t && Number(t.debit) > 0) return 'Flight package charged again';
    return KIND_LABEL[type] || String(type || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
// What the movement means for THIS party, and where it left their balance:
// "Charged · ৳23,700 Cr → ৳11,700 Cr". Debit raises what is owed on every
// party ledger; the words differ by side (see PartyStatementController::TYPES).
function flowLine(t) {
    const payable = selectedParty && selectedParty.type === 'vendor';
    const word = flowWordFor(t, payable);
    const drcr = v => fmtMoney(v) + ' ' + (v < 0 ? 'Cr' : v > 0 ? 'Dr' : '');
    return `${word} &nbsp;·&nbsp; ${drcr(t.old_balance)} → ${drcr(t.balance)}`;
}

// The full record behind a row — Blade twin: partials/detail-table.
function psDetailTable(d) {
    const th = d.columns.map(c => `<th class="py-1.5 pl-3 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase whitespace-nowrap">${esc(c)}</th>`).join('');
    const tr = d.rows.map(r => '<tr>'
        + r.map((c, i) => `<td class="py-1.5 pl-3 pr-3 text-xs ${i ? 'text-gray-600' : 'font-semibold text-gray-800'}">${c === '' || c === null ? '—' : esc(c)}</td>`).join('') + '</tr>').join('');
    return `<div class="ps-detail-card"><table class="w-full"><thead><tr>${th}</tr></thead><tbody>${tr}</tbody></table></div>`;
}
function psToggleDetail(rowId, btn) {
    const det = document.getElementById(rowId);
    if (!det) return;
    const open = det.style.display === 'none';
    if (open) {
        det.closest('tbody').querySelectorAll('tr.ps-detail-row').forEach(other => {
            if (other === det) return;
            other.style.display = 'none';
            const otherRow = other.previousElementSibling;
            if (otherRow) {
                otherRow.dataset.open = '0';
                otherRow.querySelector('.ps-more')?.classList.remove('is-open');
            }
        });
    }
    det.style.display = open ? '' : 'none';
    const row = det.previousElementSibling;
    if (row) row.dataset.open = open ? '1' : '0';
    if (btn) btn.classList.toggle('is-open', open);
}

// HTML-escape a value that goes into markup or an attribute.
function esc(v) {
    return String(v ?? "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

// ── Formatters ─────────────────────────────────────────────────────────────────
// dd/mm/yyyy, read straight off the string — the form every Manage Bank table
// prints (fmtDMY there). Not through Date(): a server-local timestamp parsed as
// UTC can land on the day before in some browsers.
// dd/mm/yyyy, read straight off the string — the form every Manage Bank table
// prints (fmtDMY there). Not through Date(): a server-local timestamp parsed as
// UTC can land on the day before in some browsers.
function fmtDate(d) {
    const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] : (d ? String(d) : '—');
}
function fmtMoney(v) {
    return '৳ ' + Math.abs(v).toLocaleString('en-BD', { minimumFractionDigits: 0 });
}

// ── Date preset ────────────────────────────────────────────────────────────────
function resolveDates() {
    const preset = document.getElementById('datePreset').value;
    const now    = new Date();
    let from = '', to = now.toISOString().split('T')[0];
    if      (preset === 'this_year')  from = now.getFullYear() + '-01-01';
    else if (preset === 'this_month') from = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-01';
    else if (preset === 'last_3')     { const d = new Date(now); d.setMonth(d.getMonth()-3); from = d.toISOString().split('T')[0]; }
    else if (preset === 'last_6')     { const d = new Date(now); d.setMonth(d.getMonth()-6); from = d.toISOString().split('T')[0]; }
    else if (preset === 'all_time')   { from = ''; to = ''; }
    else { from = document.getElementById('dateFrom').value; to = document.getElementById('dateTo').value; }
    return { from, to };
}

document.getElementById('datePreset').addEventListener('change', function () {
    document.getElementById('customDateRow').classList.toggle('hidden', this.value !== 'custom');
});
function resolveType() {
    return document.getElementById('txnTypeFilter').value;
}
document.getElementById('txnTypeFilter').addEventListener('change', function () {
    if (selectedParty) document.getElementById('loadBtn').click();
});

// ── Party search ───────────────────────────────────────────────────────────────
document.getElementById('partySearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 1) { closeDropdown(); return; }
    searchTimer = setTimeout(() => doSearch(q), 280);
});
// The search only runs within a type. On a ledger page that type is fixed;
// the dashboard has no search at all, so the select may simply not exist.
function partyTypeValue() {
    if (LOCKED_PARTY_TYPE) return LOCKED_PARTY_TYPE;
    const sel = document.getElementById('partyType');
    return sel ? sel.value : 'all';
}
const partyTypeSel = document.getElementById('partyType');
if (partyTypeSel) partyTypeSel.addEventListener('change', () => {
    const q = document.getElementById('partySearch').value.trim();
    if (q.length >= 1) doSearch(q);
});

function doSearch(q) {
    const type = partyTypeValue();
    fetch(appendQuery(SEARCH_URL, `q=${encodeURIComponent(q)}&type=${type}`))
        .then(r => r.json()).then(renderDropdown);
}
function renderDropdown(parties) {
    const dd = document.getElementById('partyDropdown');
    if (!parties.length) {
        dd.innerHTML = '<p class="px-4 py-3 text-xs text-gray-400">No parties found</p>';
        dd.classList.remove('hidden'); return;
    }
    dd.innerHTML = parties.map(p => {
        const roles = p.roles.map(r => `<span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full ${roleCls[r]||'bg-gray-100 text-gray-500'}">${r}</span>`).join(' ');
        return `<div class="px-4 py-2.5 hover:bg-blue-50 cursor-pointer flex items-center gap-3 border-b border-gray-50 last:border-0 transition"
                     onclick='selectParty(${JSON.stringify(p)})'>
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">${p.initials}</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">${p.name}</p>
                <p class="text-xs text-gray-400">${p.phone||p.email||'No contact'}</p>
            </div>
            <div class="flex gap-1 flex-shrink-0">${roles}</div>
        </div>`;
    }).join('');
    dd.classList.remove('hidden');
}
function closeDropdown() { document.getElementById('partyDropdown').classList.add('hidden'); }
function selectParty(p) {
    selectedParty = p;
    document.getElementById('partySearch').value = p.name;
    document.getElementById('chipName').textContent = p.name;
    document.getElementById('selectedPartyChip').classList.remove('hidden');
    document.getElementById('loadBtn').disabled = false;
    closeDropdown();
    markListRow(p.id);
}

// The highlighted row in the party table (ledger pages). One row at most.
function markListRow(id) {
    document.querySelectorAll('#partyList tr[data-party-id]').forEach(tr => {
        tr.classList.toggle('is-open', Number(tr.dataset.partyId) === Number(id));
    });
}

// Open a party by id: pick it, load its statement, bring the statement into
// view. The party table's rows, the ?party_id= deep link and the dashboard's
// links all come through here, so there is exactly one way a statement opens.
let partyToScrollTo = null;
function fitPartyLedgerHeader() {
    const card = document.querySelector('#statementArea .ledger-header');
    if (!card || card.offsetParent === null) return;
    const row = card.querySelector('.ledger-head-top');
    const first = row.querySelector('.lh-zone-id');
    const actions = row.querySelector('.lh-actions');
    if (!first || !actions) return;
    const broken = () => actions.getBoundingClientRect().top - first.getBoundingClientRect().top > 8;
    card.style.zoom = '';
    if (!broken()) return;
    const available = row.clientWidth;
    const oldWrap = row.style.flexWrap;
    const oldWidth = row.style.width;
    row.style.flexWrap = 'nowrap';
    row.style.width = 'max-content';
    const needed = row.scrollWidth;
    row.style.flexWrap = oldWrap;
    row.style.width = oldWidth;
    if (!available || !needed) return;
    let zoom = Math.max(0.78, Math.floor(available / needed * 100) / 100);
    card.style.zoom = zoom;
    for (let i = 0; i < 10 && zoom < 1; i++) {
        const next = Math.min(1, Math.round((zoom + 0.01) * 100) / 100);
        card.style.zoom = next;
        if (broken()) { card.style.zoom = zoom; break; }
        zoom = next;
    }
    if (zoom >= 1) card.style.zoom = '';
}
let partyHeaderResizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(partyHeaderResizeTimer);
    partyHeaderResizeTimer = setTimeout(fitPartyLedgerHeader, 150);
});
function openParty(id) {
    const fromList = PARTY_LIST.find(p => Number(p.id) === Number(id));
    if (fromList) selectParty(fromList);
    else selectedParty = { id: Number(id), name: '' };
    partyToScrollTo = Number(id);
    document.getElementById('loadBtn').click();
}
function clearParty() {
    partyToScrollTo = null;
    selectedParty = null;
    markListRow(null);
    document.getElementById('partySearch').value = '';
    document.getElementById('selectedPartyChip').classList.add('hidden');
    document.getElementById('loadBtn').disabled  = true;
    document.getElementById('pdfBtn').disabled   = true;
    document.getElementById('excelBtn').disabled = true;
    document.getElementById('printBtn').disabled = true;
    document.getElementById('statementArea').classList.add('hidden');
    document.getElementById('placeholderArea').classList.remove('hidden');
}
document.addEventListener('click', e => {
    if (!e.target.closest('#partySearch') && !e.target.closest('#partyDropdown')) closeDropdown();
});

// ── Load statement ─────────────────────────────────────────────────────────────
document.getElementById('loadBtn').addEventListener('click', function () {
    if (!selectedParty) return;
    const { from, to } = resolveDates();
    const type = resolveType();
    lastLoadParams = { party_id: selectedParty.id, from, to, type };
    const icon = document.getElementById('loadIcon');
    icon.classList.add('fa-spin');
    this.disabled = true;

    let url = appendQuery(LOAD_URL, `party_id=${selectedParty.id}`);
    if (from) url += `&from=${from}`;
    if (to)   url += `&to=${to}`;
    if (type && type !== 'all') url += `&type=${type}`;

    fetch(url).then(r => r.json())
        .then(data => {
            renderStatement(data);
            document.getElementById('pdfBtn').disabled = false;
            document.getElementById('excelBtn').disabled = false;
            document.getElementById('printBtn').disabled = false;
            if (partyToScrollTo === Number(data.party.id)) {
                partyToScrollTo = null;
                requestAnimationFrame(() => document.getElementById('statementArea').scrollIntoView({ block: 'start', behavior: 'smooth' }));
            }
        })
        .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Failed to load statement.' }))
        .finally(() => { icon.classList.remove('fa-spin'); this.disabled = false; });
});

// ── Render statement ───────────────────────────────────────────────────────────
// The last statement loaded, kept so the table bar's search can redraw it
// without another round trip. STMT_Q is the live search text.
let LAST_STATEMENT = null;
let STMT_Q = '';

function editPartyTransactionDate(transactionId) {
    const transaction = (LAST_STATEMENT?.transactions || []).concat(LAST_STATEMENT?.reversed_transactions || []).find(row => Number(row.id) === Number(transactionId));
    if (!transaction) return;

    const isDiscount = transaction.type === 'party_discount';
    const currentDate = String(transaction.payment_date || '').slice(0, 10);
    const currentTime = String(transaction.transaction_time || '').slice(0, 5);
    Swal.fire({
        title: '',
        width: 790,
        customClass: {popup:'party-date-workspace', actions:'party-date-actions', confirmButton:'party-date-save', cancelButton:'party-date-cancel'},
        showCloseButton: true,
        closeButtonAriaLabel: 'Close transaction correction',
        html: `<style>
            .party-date-workspace{padding:0!important;border-radius:14px!important;overflow:hidden}
            .party-date-workspace .swal2-html-container{margin:0!important;padding:0!important;overflow:visible!important}
            .party-date-workspace .swal2-close{top:14px!important;right:16px!important;width:30px!important;height:30px!important;border-radius:8px!important;color:#64748b!important;font-size:25px!important}
            .party-date-workspace .swal2-close:hover{background:#e2e8f0!important;color:#0f172a!important}
            .ptw-head{padding:28px 42px 14px;text-align:left;background:#fff}
            .ptw-head-copy{min-width:0;padding-right:35px}.ptw-kicker{color:#2563eb;font-size:9px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.ptw-head h2{margin:5px 0 3px;color:#172033;font-size:19px;font-weight:500;line-height:1.2}.ptw-head p{margin:0;color:#64748b;font-size:10px}.ptw-back{display:inline-flex;align-items:center;gap:5px;margin-top:8px;border:0;background:none;padding:0;color:#2563eb;font-size:10px;font-weight:700;cursor:pointer}
            .ptw-body{padding:0 42px 4px;text-align:left}.ptw-alert{display:flex;align-items:center;gap:12px;padding:12px 15px;border:1px solid #bfdbfe;border-radius:10px;background:#f8fbff;color:#1e293b}.ptw-alert-icon{display:flex;align-items:center;justify-content:center;flex:0 0 36px;height:36px;border-radius:9px;background:#dbeafe;color:#2563eb;font-size:15px}.ptw-alert b{display:block;font-size:13px;font-weight:500}.ptw-alert small{display:block;margin-top:3px;color:#64748b;font-size:9px;font-weight:400}
            .ptw-card{margin-top:14px;padding:16px;border:1px solid #dbe4ef;border-radius:11px;background:#fff}.ptw-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ptw-field{display:block;color:#334155;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}.ptw-field span{float:right;color:#94a3b8;font-size:9px;font-weight:400;text-transform:none;letter-spacing:0}.ptw-field input,.ptw-field textarea{box-sizing:border-box;display:block;width:100%;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-family:inherit;font-size:11px;font-weight:400;outline:none}.ptw-field input{height:39px;padding:0 10px}.ptw-field textarea{min-height:74px;padding:10px;resize:vertical;line-height:1.45}.ptw-field input:focus,.ptw-field textarea:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}.ptw-span{display:block;margin-top:13px;padding:12px;border:1px solid #fed7aa;border-radius:9px;background:#fffaf0}.ptw-span textarea{background:#fff}.ptw-confirm{margin-top:12px;padding:11px 12px;border:1px solid #dbe4ef;border-radius:9px;background:#f8fafc}.ptw-confirm .ptw-field{color:#334155}.ptw-confirm code{padding:2px 5px;border-radius:4px;background:#e2e8f0;color:#334155;font-weight:600}
            .party-date-actions{justify-content:flex-end!important;width:100%!important;box-sizing:border-box!important;margin:16px 0 0!important;padding:14px 24px!important;border-top:1px solid #e2e8f0;background:#f8fafc}.party-date-actions button{margin:0 0 0 8px!important;padding:9px 17px!important;border-radius:8px!important;font-size:11px!important;font-weight:800!important;box-shadow:none!important}.party-date-save{background:#2563eb!important}.party-date-save:hover{background:#1d4ed8!important}.party-date-cancel{border:1px solid #cbd5e1!important;background:#fff!important;color:#475569!important}
            @media(max-width:640px){.ptw-head,.ptw-body{padding-left:16px;padding-right:16px}.ptw-grid{grid-template-columns:1fr}.party-date-actions{padding-left:16px!important;padding-right:16px!important}}
        </style>
        <div class="ptw-head"><div class="ptw-head-copy"><div class="ptw-kicker">Party ledger correction workspace</div><h2>${esc(transaction.reference_no || 'Transaction #'+transaction.id)}</h2><p>${esc(LAST_STATEMENT?.party?.name || '')} · ${esc(String(transaction.type || '').replace(/_/g, ' '))}</p><button type="button" class="ptw-back" onclick="Swal.close(); ${isDiscount ? `openPartyTransactionCorrection(${Number(transaction.id)})` : ''}"><i class="fas fa-arrow-left"></i> ${isDiscount ? 'Back to overview' : 'Back to ledger'}</button></div></div>
        <div class="ptw-body"><div class="ptw-alert"><span class="ptw-alert-icon"><i class="fas fa-calendar-check"></i></span><span><b>Correct date &amp; time</b><small>${isDiscount ? 'The discount record, party ledger and linked journal stay on the same corrected timestamp.' : 'Change the posting order; a linked bank entry remains on the same corrected date and time.'}</small></span></div>
            <div class="ptw-card"><div class="ptw-grid">
                <label class="ptw-field">Transaction date *<input id="partyTxDate" type="date" value="${esc(currentDate)}"></label>
                <label class="ptw-field">Transaction time *<input id="partyTxTime" type="time" value="${esc(currentTime)}"></label>
            </div>
            <label class="ptw-field ptw-span">Correction reason *<span>Saved permanently in audit history</span><textarea id="partyTxNote" maxlength="500" placeholder="Explain why the transaction date or time must change"></textarea></label>
            <div class="ptw-confirm"><label class="ptw-field">Final confirmation *<span>Type <code>CONFIRM</code></span><input id="partyTxConfirm" type="text" autocomplete="off" placeholder="Type CONFIRM to continue"></label></div></div>
        </div>`,
        showCancelButton: true,
        confirmButtonText: 'Save correction',
        cancelButtonText: 'Close',
        confirmButtonColor: '#2563eb',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            const date = document.getElementById('partyTxDate').value;
            const time = document.getElementById('partyTxTime').value;
            const note = document.getElementById('partyTxNote').value.trim();
            const confirmation = document.getElementById('partyTxConfirm').value;
            if (!date || !time || note.length < 5 || confirmation !== 'CONFIRM') {
                Swal.showValidationMessage('Select date and time, enter a note of at least 5 characters, and type CONFIRM exactly.');
                return false;
            }
            try {
                const response = await fetch(`${TRANSACTION_DATE_URL}/${transactionId}/date`, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},
                    body: JSON.stringify({date, time, note, confirmation}),
                });
                const data = await response.json();
                if (!response.ok || data.success === false) throw new Error(data.message || 'Correction failed.');
                return data;
            } catch (error) {
                Swal.showValidationMessage(error.message || 'Network error.');
                return false;
            }
        },
    }).then(result => {
        if (!result.isConfirmed) return;
        Swal.fire({icon:'success', title:'Updated', text:result.value.message, timer:1300, showConfirmButton:false})
            .then(() => document.getElementById('loadBtn').click());
    });
}

function stmtHaystack(t) {
    if (t._hay === undefined) {
        const money = v => { const n = Number(v) || 0; return n ? fmtMoney(n) + ' ' + String(n) : ''; };
        t._hay = [t.payment_date, fmtDate(t.payment_date), t.reference_no, t.type, String(t.type || '').replace(/_/g, ' '),
                  t.remarks, t.payment_method, t.bank, t.bank_no, t.bank_entry && t.bank_entry.txn_no,
                  (t.reason || []).map(l => l.text).join(' '),
                  money(t.debit), money(t.credit), money(t.balance)]
            .filter(Boolean).join(' ').toLowerCase();
    }
    return t._hay;
}

function showPartyReversalHistory() {
    const pairs = new Map();
    (LAST_STATEMENT?.audit_transactions || LAST_STATEMENT?.transactions || []).filter(row => row.reversal_pair_id).forEach(row => {
        if (!pairs.has(row.reversal_pair_id)) pairs.set(row.reversal_pair_id, []);
        pairs.get(row.reversal_pair_id).push(row);
    });
    Swal.fire({title:'Reversal history', width:760,
        html:`<div style="max-height:65vh;overflow:auto;text-align:left">${[...pairs.values()].map(rows => `<details style="padding:12px;border:1px solid #ddd6fe;border-radius:8px;margin-bottom:8px"><summary>${esc(rows[0].reference_no || 'Transaction #'+rows[0].id)}</summary>${rows.map(row => `<p style="margin:10px 0">${esc(row.payment_date)} · ${esc(row.reference_no || '')}<br>${esc(row.remarks || '')}<br>Debit ${fmtMoney(row.debit)} · Credit ${fmtMoney(row.credit)}</p>`).join('')}<b>Net effect: 0.00</b></details>`).join('') || 'No complete reversal pairs in this period.'}</div>`,
        confirmButtonText:'Close'});
}

function refundSummaryDetail(t) {
    const s = t.refund_summary;
    if (!s) return '';
    const matches = Math.abs(Number(s.original_agent_price) - Number(s.refund_to_agent) - Number(s.retained_amount)) < 0.005;
    const isVoid = s.kind === 'void';
    const memberIds = new Set((s.transaction_ids || []).map(Number));
    const auditRows = (LAST_STATEMENT?.audit_transactions || []).filter(row => memberIds.has(Number(row.id)));
    const audit = auditRows.map(row => `<div class="ps-sub" style="padding:5px 0;border-top:1px solid #e2e8f0;">${esc(fmtDate(row.payment_date))} · ${esc(row.remarks || kindLabel(row.type, row))} · Dr ${fmtMoney(Number(row.debit))} · Cr ${fmtMoney(Number(row.credit))}</div>`).join('');
    return `<div class="ps-detail-card"><table class="w-full"><thead><tr>
        <th class="py-1.5 pl-3 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">Original charge</th>
        <th class="py-1.5 pl-3 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">Sale reversed</th>
        <th class="py-1.5 pl-3 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">${isVoid ? 'Void charge' : 'Retained / deduction'}</th>
        <th class="py-1.5 pl-3 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">Net effect</th>
    </tr></thead><tbody><tr>
        <td class="py-1.5 pl-3 pr-3 text-xs font-semibold text-gray-800">${fmtMoney(Number(s.original_agent_price))}</td>
        <td class="py-1.5 pl-3 pr-3 text-xs text-green-700 font-semibold">${fmtMoney(Number(s.refund_to_agent))}</td>
        <td class="py-1.5 pl-3 pr-3 text-xs text-gray-600">${fmtMoney(Number(isVoid ? s.final_charge : s.retained_amount))}${isVoid ? ` <span class="ps-sub">(penalty ${fmtMoney(Number(s.airline_penalty))} + fee ${fmtMoney(Number(s.service_charge))})</span>` : ''}</td>
        <td class="py-1.5 pl-3 pr-3 text-xs ${matches ? 'text-green-700' : 'text-red-700'} font-semibold">${fmtMoney(Number(t.debit) - Number(t.credit))}</td>
    </tr></tbody></table>${audit ? `<details style="margin:10px 12px 12px;"><summary class="ps-sub" style="cursor:pointer;font-weight:700;">Audit postings (${auditRows.length})</summary>${audit}</details>` : ''}</div>`;
}

let partyLedgerPage = 1;
let partyLedgerPageSize = 50;
let partyLedgerPageKey = '';

function changePartyLedgerPage(page) {
    partyLedgerPage = page;
    if (LAST_STATEMENT) renderStatement(LAST_STATEMENT, true);
}

function setPartyLedgerPageSize(size) {
    partyLedgerPageSize = Number(size);
    changePartyLedgerPage(1);
}

function renderStatement(data, fromSearch = false) {
    if (!fromSearch) LAST_STATEMENT = data;
    const { party, opening_balance, total_debit, total_credit, closing_balance, date_from, date_to } = data;
    // The search narrows the rows and nothing else; Opening/Closing and the two
    // totals stay the period's. While a query is live the brought-forward row
    // and the footer are withheld rather than shown against a subset.
    const searching = STMT_Q !== '';
    const view = document.getElementById('partyTransactionView')?.value || 'active';
    let visible;
    if (view === 'reversed') {
        const inactive = data.transactions.filter(t => t.reversal_pair_id || isInactivePartyTransaction(t));
        visible = [...(data.reversed_transactions || []), ...inactive]
            .filter((row, index, rows) => rows.findIndex(candidate => Number(candidate.id) === Number(row.id)) === index);
    } else if (view === 'all') {
        // Active rows plus the cancelled pairs, in statement order (date, then
        // id), with the running balance recomputed over the merged list on
        // copies — the pair nets to zero, so the closing balance is unchanged.
        let running = Number(opening_balance) || 0;
        visible = data.transactions.concat(data.reversed_transactions || [])
            .sort((a, b) => {
                const clock = t => String(t.transaction_time || String(t.recorded_at || '').slice(11, 16) || '00:00');
                return String(a.payment_date).slice(0, 10).localeCompare(String(b.payment_date).slice(0, 10))
                    || clock(a).localeCompare(clock(b)) || a.id - b.id;
            })
            .map(t => {
                const row = Object.assign({}, t, {old_balance: running});
                running += Number(t.debit) - Number(t.credit);
                row.balance = running;
                return row;
            });
    } else {
        visible = data.transactions.filter(t => !t.reversal_pair_id && !isInactivePartyTransaction(t));
    }
    const transactions = searching ? visible.filter(t => stmtHaystack(t).includes(STMT_Q)) : visible;
    const pageKey = JSON.stringify([data.party.id, data.date_from, data.date_to, view, STMT_Q, transactions.map(t => t.id)]);
    if (!fromSearch || partyLedgerPageKey !== pageKey) partyLedgerPage = 1;
    partyLedgerPageKey = pageKey;
    const pageCount = Math.max(1, Math.ceil(transactions.length / partyLedgerPageSize));
    partyLedgerPage = Math.min(Math.max(1, partyLedgerPage), pageCount);
    const pageOffset = (partyLedgerPage - 1) * partyLedgerPageSize;
    const pageTransactions = transactions.slice().reverse().slice(pageOffset, pageOffset + partyLedgerPageSize);
    const pager = document.getElementById('partyLedgerPager');
    pager.style.display = pageCount > 1 ? 'flex' : 'none';
    const pageButton = (label, page, disabled = false, title = '', numbered = false) => `<button type="button" ${disabled ? 'disabled' : ''} title="${title || 'Page '+page}" aria-label="${title || 'Page '+page}" ${numbered && page === partyLedgerPage ? 'aria-current="page"' : ''} class="${numbered && page === partyLedgerPage ? 'is-on' : ''}" onclick="changePartyLedgerPage(${page})">${label}</button>`;
    const pageNumbers = [];
    const wantedPages = new Set([1, pageCount]);
    for (let page = Math.max(1, partyLedgerPage - 2); page <= Math.min(pageCount, partyLedgerPage + 2); page++) wantedPages.add(page);
    let previousPage = 0;
    [...wantedPages].sort((a,b) => a-b).forEach(page => {
        if (previousPage && page - previousPage > 1) pageNumbers.push('<span class="party-pager-gap">…</span>');
        pageNumbers.push(pageButton(page, page, false, '', true));
        previousPage = page;
    });
    pager.innerHTML = `<span class="party-pager-info">Showing ${pageOffset + 1}–${pageOffset + pageTransactions.length} of ${transactions.length} transactions · Page ${partyLedgerPage} of ${pageCount}</span><div class="party-pager-nav">${pageButton('<i class="fas fa-angle-double-left"></i>',1,partyLedgerPage===1,'First page')}${pageButton('<i class="fas fa-angle-left"></i>',partyLedgerPage-1,partyLedgerPage===1,'Previous page')}${pageNumbers.join('')}${pageButton('<i class="fas fa-angle-right"></i>',partyLedgerPage+1,partyLedgerPage===pageCount,'Next page')}${pageButton('<i class="fas fa-angle-double-right"></i>',pageCount,partyLedgerPage===pageCount,'Last page')}</div>`;
    psPaintCount('st', searching, transactions.length, data.transactions.length);

    document.getElementById('partyAvatar').textContent    = party.initials;
    document.getElementById('partyName').textContent      = party.name;
    document.getElementById('partyIdDisplay').textContent = party.code || ('ID-' + String(party.id).padStart(4,'0'));
    if (selectedParty) selectedParty.type = party.type;

    const setField = (wrapId, spanId, val) => {
        if (val) { document.getElementById(spanId).textContent = val; document.getElementById(wrapId).classList.remove('hidden'); }
        else     document.getElementById(wrapId).classList.add('hidden');
    };
    setField('partyContactWrap', 'partyContactName', party.contact_person);
    setField('partyPhoneWrap',   'partyPhone',        party.phone);

    document.getElementById('partyRoles').innerHTML = party.roles.map(r =>
        `<span class="lh-type">${esc(r)}</span>`
    ).join('');
    document.getElementById('statementPeriod').innerHTML =
        date_from ? fmtDate(date_from)+' → '+fmtDate(date_to) : 'All Time';

    const payable = party.type === 'vendor';
    const balanceLabel = closing_balance > 0 ? (payable ? 'Payable' : 'Receivable')
        : closing_balance < 0 ? (payable ? 'Advance Paid' : 'Advance Received') : 'Settled';
    document.getElementById('partyBalanceLabel').textContent = balanceLabel;
    document.getElementById('partyClosingLabel').textContent = balanceLabel;
    document.getElementById('partyCurrentBalance').textContent = fmtMoney(closing_balance) + (closing_balance > 0 ? ' Dr' : closing_balance < 0 ? ' Cr' : '');
    document.getElementById('partyDebitEntries').textContent = data.transactions.filter(t => Number(t.debit) > 0).length;
    document.getElementById('partyCreditEntries').textContent = data.transactions.filter(t => Number(t.credit) > 0).length;
    document.getElementById('partyTxnCount').textContent = data.transactions.length;
    const latest = data.transactions[data.transactions.length - 1];
    const latestPill = document.getElementById('partyLastPill');
    latestPill.className = 'lh-pill ' + (latest ? (Number(latest.debit) > 0 ? 'lh-pill-out' : 'lh-pill-in') : 'lh-pill-none');
    latestPill.querySelector('i').className = 'fas ' + (latest ? (Number(latest.debit) > 0 ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-minus');
    // Same words as the row badge (flowWordFor): a reversal or a revised package is a credit, not money received.
    const latestWord = latest ? flowWordFor(latest, payable) : 'None';
    document.getElementById('partyLastDirection').textContent = latestWord === 'Bill' ? 'Billed' : latestWord;
    document.getElementById('partyLastAmount').textContent = latest ? fmtMoney(Number(latest.debit) || Number(latest.credit)) : '—';
    document.getElementById('partyLastAmount').className = 'lh-last-amt ' + (latest ? (Number(latest.debit) > 0 ? 'lh-amt-out' : 'lh-amt-in') : '');
    document.getElementById('partyLastPurpose').textContent = latest ? (latest.remarks || String(latest.type || '').replace(/_/g, ' ')) : '—';
    document.getElementById('partyLastDate').textContent = latest ? fmtDate(latest.payment_date) : '—';

    document.getElementById('summOpeningBal').textContent  = fmtMoney(opening_balance) + (opening_balance > 0 ? ' Dr' : opening_balance < 0 ? ' Cr' : '');
    document.getElementById('summOpeningDate').textContent = date_from ? fmtDate(date_from) : '';
    document.getElementById('summTotalDebit').textContent  = fmtMoney(total_debit);
    document.getElementById('summDebitCount').textContent  = data.transactions.filter(t=>t.debit>0).length + ' debit entries';
    const discounts = data.transactions.filter(t => t.type === 'party_discount').reduce((n, t) => n + Number(t.credit), 0);
    const discountReversals = data.transactions.filter(t => t.type === 'party_discount_reversal').reduce((n, t) => n + Number(t.debit), 0);
    document.getElementById('summTotalDebit').textContent = fmtMoney(total_debit - discountReversals);
    document.getElementById('summTotalCredit').textContent = fmtMoney(total_credit - discounts);
    document.getElementById('summTotalCredit').title = `Discount allowed (separate from receipts): ${fmtMoney(discounts - discountReversals)}`;
    document.getElementById('partyDiscountTotal').textContent = `Discounts: ${fmtMoney(discounts - discountReversals)}`;
    document.getElementById('summCreditCount').textContent = data.transactions.filter(t=>t.credit>0).length + ' credit entries';
    document.getElementById('summClosingBal').textContent  = fmtMoney(closing_balance) + (closing_balance > 0 ? ' Dr' : closing_balance < 0 ? ' Cr' : '');
    // Debit raises what is owed on every party ledger; what "owed" means
    // depends on who the party is. A vendor's Dr balance is what WE owe THEM.
    // See PartyStatementController::TYPES.
    document.getElementById('summDebitLabel').textContent  = payable ? 'Total Billed' : 'Total Charged';
    document.getElementById('summCreditLabel').textContent = payable ? 'Total Paid' : 'Total Received';
    document.getElementById('summClosingLabel').textContent = closing_balance > 0
        ? (payable ? 'Payable to vendor (Dr)' : 'Receivable (Dr)')
        : closing_balance < 0 ? (payable ? 'Advance paid (Cr)' : 'Advance received (Cr)') : 'Settled';

    document.getElementById('ledgerSubtitle').textContent =
        (date_from ? fmtDate(date_from)+' to '+(date_to?fmtDate(date_to):'today') : 'All time') + ' — newest first';

    const tbody = document.getElementById('ledgerBody');
    tbody.innerHTML = '';

    if (!transactions.length) {
        document.getElementById('ledgerEmpty').classList.remove('hidden');
        document.getElementById('ledgerFooter').classList.add('hidden');
        document.getElementById('ledgerMeta').classList.add('hidden');
    } else {
        document.getElementById('ledgerEmpty').classList.add('hidden');

        // Newest first, the house default for every transaction table on
        // screen. The server still sends them oldest-first — each row's running
        // balance is only meaningful in that order, and the Excel/PDF exports
        // read the same feed and stay chronological — so this reverses a copy
        // for display only. slice() first: reverse() mutates, and the caller's
        // array is the one the summary counts above were taken from.
        //
        // The Opening Balance row is written after the loop for the same reason:
        // brought-forward now sits below the oldest entry, where the list ends.
        const openingRow = opening_balance !== 0 ? `
            <tr style="background:#eff6ff;">
                <td class="ps-pick"></td>
                <td class="ps-date" style="color:#94a3b8;font-style:italic;">${date_from ? fmtDate(date_from) : ''}</td>
                <td colspan="4" style="color:#64748b;font-style:italic;font-size:0.75rem;"><i class="fas fa-arrow-right" style="margin-right:4px;"></i>Opening Balance Brought Forward</td>
                <td style="text-align:right;"><span class="ps-amt-none">—</span></td>
                <td style="text-align:right;"><span class="ps-amt-none">—</span></td>
                <td style="text-align:right;"><span class="ps-bal" style="color:${opening_balance < 0 ? '#dc2626' : '#1d4ed8'};">${fmtMoney(opening_balance)} <small>${opening_balance < 0 ? 'Cr' : 'Dr'}</small></span></td>
            </tr>` : '';

        // The newest entry, identified before the flip — it is the one that
        // carries the live balance, and it is now the first row on screen.
        const latestTx = transactions[transactions.length - 1];

        // Built as strings and written to the table ONCE below. Appending with
        // `tbody.innerHTML +=` per row re-parses every row already placed, and
        // the bank ledger froze the tab on exactly that — see the note on
        // LEDGER_PER_PAGE in banks/dashboard.blade.php.
        const rows = [];

        pageTransactions.forEach((t) => {
            const isDebit  = t.debit > 0;
            const isCredit = t.credit > 0;
            const bal      = t.balance;
            const balSign  = bal < 0 ? 'Cr' : 'Dr';
            const isLatest = (t === latestTx);

            // Invoice PDF link for party_invoice type
            const invPdfLink = t.type === 'party_invoice' && t.invoice_id
                ? `<a href="${INVOICE_PDF_BASE}${t.invoice_id}/pdf" target="_blank"
                       class="ml-1.5 text-[10px] text-blue-500 hover:text-blue-700 transition" title="View Invoice PDF">
                       <i class="fas fa-file-pdf"></i></a>`
                : '';

            // Ticket sales bundle multiple passengers/routes into one ledger row —
            // expose them via an on-demand expandable row instead of a bulky remarks string.
            const isTicketSale = t.type === 'ticket_sale' && t.invoice_id;
            const ticketToggle = (isTicketSale && !t.detail)
                ? `<button type="button" id="tx-toggle-${t.id}" onclick="toggleTicketDetail(${t.id}, ${t.invoice_id})"
                       class="ml-1.5 text-[10px] text-blue-600 hover:text-blue-800 font-semibold inline-flex items-center gap-0.5 align-middle">
                       <i class="fas fa-chevron-down text-[9px]"></i> Passengers</button>`
                : '';

            // The feed's row, cell for cell (party-statement/dashboard.blade.php,
            // owner 2026-09-16: "fully the same as the dashboard's table"):
            // Party carries the name, the movement badge, the code and this
            // row's opening → closing; Entry the kind, the remarks and the
            // voucher (a link when a bank entry stands behind it); Details the
            // reason lines and the arrow; then Paid via and the money.
            const payableRow = selectedParty && selectedParty.type === 'vendor';
            const flowWord = flowWordFor(t, payableRow);
            const drcr = v => fmtMoney(v) + ' ' + (v < 0 ? 'Cr' : 'Dr');
            const balCol = v => v < 0 ? '#dc2626' : '#1d4ed8';
            const typeLabel = { agent: 'Agent', vendor: 'Vendor', client: 'Customer' }[party.type] || null;
            let reason = Array.isArray(t.reason) ? t.reason : [];
            const hasRefundSummary = !!t.refund_summary;
            if (hasRefundSummary) {
                const summary = t.refund_summary;
                reason = summary.kind === 'void' ? [
                    {text: `Original ${fmtMoney(Number(summary.original_agent_price))} · reversed ${fmtMoney(Number(summary.refund_to_agent))}`},
                    {text: `Void charge ${fmtMoney(Number(summary.final_charge))} (penalty ${fmtMoney(Number(summary.airline_penalty))} + fee ${fmtMoney(Number(summary.service_charge))})`},
                ] : [
                    {text: `Refund ${fmtMoney(Number(summary.refund_to_agent))}`},
                    {text: `Retained ${fmtMoney(Number(summary.retained_amount))} · Total ${fmtMoney(Number(summary.original_agent_price))}`},
                ];
            }
            const voucher = bankEntryUrl(t)
                ? `<a href="${bankEntryUrl(t)}" class="ps-vno ps-vno-link" onclick="event.stopPropagation();" title="Open this transaction on Manage Bank"><i class="fas fa-building-columns"></i> ${esc(t.reference_no || t.bank_entry.txn_no)}</a>`
                : (t.reference_no ? `<div class="ps-vno">${esc(t.reference_no)}</div>` : '');

            // A ticket sale with several tickets: one row per passenger (owner, 2026-10-27),
            // each with its own price and the balance carried ticket by ticket. Display only —
            // it is still one ledger row (same id, same pencil). Newest first, so the last
            // ticket of the invoice is on top.
            if (Array.isArray(t.split) && t.split.length > 1) {
                let run = t.old_balance;
                const parts = t.split.map(s => { const o = run; run += s.price; return { ...s, open: o, close: run }; });
                parts.reverse().forEach((s, k) => {
                    rows.push(`
            <tr data-pick-id="${t.id}" data-date="${String(t.payment_date || '').slice(0, 10)}">
                <td class="ps-pick"><input type="checkbox" class="ps-pick-box" aria-label="Select this transaction"></td>
                <td class="ps-date">${fmtDate(t.payment_date)}${recordedTimeLine(t.payment_date, t.recorded_at, t.transaction_time)}
                    <button type="button" class="ps-date-edit" onclick="openPartyTransactionCorrection(${t.id})" title="Correct transaction date and time" aria-label="Correct transaction date and time"><i class="fas fa-pencil-alt"></i></button></td>
                <td>
                    <div class="ps-nm">${esc(party.name)}<span class="ps-flow" style="background:#fee2e2;color:#b91c1c;"><i class="fas fa-arrow-up"></i> ${flowWord}</span></div>
                    ${party.code && typeLabel ? `<div class="ps-sub">${typeLabel} ID: <span class="ps-id">${esc(party.code)}</span></div>` : ''}
                    <div class="ps-sub">Opening: <span style="color:${balCol(s.open)};font-weight:600;">${drcr(s.open)}</span>
                        &nbsp;·&nbsp; Closing: <span style="color:${balCol(s.close)};font-weight:600;">${drcr(s.close)}</span></div>
                </td>
                <td>
                    <div class="ps-nm">${kindLabel(t.type, t)}${isLatest && k === 0 ? ' <span style="font-size:10px;color:#f59e0b;font-weight:700;margin-left:4px;">● Live</span>' : ''}${invPdfLink}</div>
                    ${t.remarks ? `<div class="ps-sub">${esc(t.remarks.length > 70 ? t.remarks.slice(0, 67) + '...' : t.remarks)}</div>` : ''}
                    ${voucher}
                </td>
                <td class="ps-details"><div class="ps-nm">${esc(s.holder || '—')}</div><div class="ps-sub">${esc([s.pnr, s.route].filter(Boolean).join(' · '))}</div></td>
                <td>${paidViaCell(t)}</td>
                <td style="text-align:right;"><span class="ps-amt-dr">${fmtMoney(s.price)}</span></td>
                <td style="text-align:right;"><span class="ps-amt-none">—</span></td>
                <td style="text-align:right;"><span class="ps-bal" style="color:${balCol(s.close)};">${fmtMoney(s.close)} <small>${s.close < 0 ? 'Cr' : 'Dr'}</small></span></td>
            </tr>`);
                });
                return;
            }
            rows.push(`
            <tr data-pick-id="${t.id}" data-date="${String(t.payment_date || '').slice(0, 10)}">
                <td class="ps-pick"><input type="checkbox" class="ps-pick-box" aria-label="Select this transaction"></td>
                <td class="ps-date">${fmtDate(t.payment_date)}${recordedTimeLine(t.payment_date, t.recorded_at, t.transaction_time)}
                    <button type="button" class="ps-date-edit" onclick="openPartyTransactionCorrection(${t.id})" title="${t.type === 'party_discount' ? 'Review or reverse this discount' : 'Correct transaction date and time'}" aria-label="${t.type === 'party_discount' ? 'Review or reverse this discount' : 'Correct transaction date and time'}"><i class="fas fa-pencil-alt"></i></button></td>
                <td>
                    <div class="ps-nm">${esc(party.name)}<span class="ps-flow" style="background:${['Reversed', 'Voided', 'Refund due'].includes(flowWord) ? '#f1f5f9' : (isDebit ? '#fee2e2' : '#dcfce7')};color:${['Reversed', 'Voided', 'Refund due'].includes(flowWord) ? '#475569' : (isDebit ? '#b91c1c' : '#15803d')};"><i class="fas fa-${flowWord === 'Reversed' ? 'rotate-left' : flowWord === 'Voided' ? 'ban' : 'arrow-' + (isDebit ? 'up' : 'down')}"></i> ${flowWord}</span></div>
                    ${party.code && typeLabel ? `<div class="ps-sub">${typeLabel} ID: <span class="ps-id">${esc(party.code)}</span></div>` : ''}
                    <div class="ps-sub">Opening: <span style="color:${balCol(t.old_balance)};font-weight:600;">${drcr(t.old_balance)}</span>
                        &nbsp;·&nbsp; Closing: <span style="color:${balCol(bal)};font-weight:600;">${drcr(bal)}</span></div>
                </td>
                <td>
                    <div class="ps-nm">${kindLabel(t.type, t)}${isLatest ? ' <span style="font-size:10px;color:#f59e0b;font-weight:700;margin-left:4px;">● Live</span>' : ''}${ticketToggle}${invPdfLink}</div>
                    ${t.remarks ? `<div class="ps-sub">${esc(t.remarks.length > 70 ? t.remarks.slice(0, 67) + '...' : t.remarks)}</div>` : ''}
                    ${voucher}
                </td>
                <td class="ps-details">${reason.length ? reason.map((l, i) => `<div class="${i ? 'ps-sub' : 'ps-nm'}"${l.tone === 'danger' ? ' style="color:#dc2626;font-weight:600;"' : ''}>${esc(l.text)}</div>`).join('') : '<span class="ps-amt-none">—</span>'}${(t.detail || hasRefundSummary)
                    ? `<button type="button" class="ps-more" onclick="psToggleDetail('st-detail-${t.id}', this)"><i class="fas fa-chevron-down"></i> Details</button>`
                    : ''}</td>
                <td>${paidViaCell(t)}</td>
                <td style="text-align:right;">${isDebit  ? `<span class="ps-amt-dr">${fmtMoney(t.debit)}</span>`  : '<span class="ps-amt-none">—</span>'}</td>
                <td style="text-align:right;">${isCredit ? `<span class="ps-amt-cr">${fmtMoney(t.credit)}</span>` : '<span class="ps-amt-none">—</span>'}</td>
                <td style="text-align:right;"><span class="ps-bal" style="color:${balCol(bal)};">${fmtMoney(bal)} <small>${balSign}</small></span></td>
            </tr>`);

            // The full record, opened by the Details arrow. Covers ticket sales
            // too, so the older "Passengers" expander is not drawn when it exists.
            if (t.detail || hasRefundSummary) {
                rows.push(`
                <tr id="st-detail-${t.id}" class="ps-detail-row" style="display:none;">
                    <td class="ps-pick"></td>
                    <td colspan="8" style="padding:0 12px 12px;background:#f8fafc;"><div style="padding-top:10px;">${hasRefundSummary ? refundSummaryDetail(t) : psDetailTable(t.detail)}</div></td>
                </tr>`);
            }
            if (isTicketSale && !t.detail) {
                rows.push(`
                <tr id="tx-detail-${t.id}" class="hidden bg-blue-50/30 border-b border-gray-50">
                    <td class="ps-pick"></td>
                    <td colspan="8" class="px-4 py-0">
                        <div id="tx-detail-body-${t.id}" class="py-3"></div>
                    </td>
                </tr>`);
            }
        });

        if (!searching && partyLedgerPage === pageCount) rows.push(openingRow);
        tbody.innerHTML = rows.join('');
        // A fresh statement drops the previous one's ticks; a search redraw
        // keeps them and the boxes follow the Set.
        if (window.PS_PICKS && PS_PICKS.st) { if (!fromSearch) PS_PICKS.st.clear(); psSyncPicks('st'); }

        document.getElementById('ledgerFooter').classList.toggle('hidden', searching || partyLedgerPage !== pageCount);
        document.getElementById('footerDate').textContent    = date_to ? fmtDate(date_to) : (d => fmtDate(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')))(new Date());
        document.getElementById('footerDebit').textContent   = fmtMoney(total_debit);
        document.getElementById('footerCredit').textContent  = fmtMoney(total_credit);
        document.getElementById('footerBalance').textContent = fmtMoney(closing_balance) + (closing_balance>0?' Dr':closing_balance<0?' Cr':'');

        document.getElementById('ledgerMeta').classList.remove('hidden');
        document.getElementById('ledgerRowCount').textContent = `${transactions.length} entries · ${transactions.filter(t=>t.credit>0).length} payments`;

        // SMS is an independent delivery channel for every party statement.
        document.getElementById('psReminderBtn').classList.remove('hidden');
    }

    document.getElementById('statementArea').classList.remove('hidden');
    document.getElementById('placeholderArea').classList.add('hidden');
    requestAnimationFrame(fitPartyLedgerHeader);
}

// ── Send to the party ──────────────────────────────────────────────────────────
// The Email / WhatsApp / SMS buttons under the statement. Email and SMS post
// independently to the server; WhatsApp only opens a prefilled wa.me chat.
// channel; WhatsApp is client-side — wa.me can carry text but not a file, so
// the message summarises the balance and says the statement was emailed.

// What the loaded ledger says about the party's money, as words — the same
// reading PartyNotifier::balanceLine gives: on every party ledger debit raises
// the balance, and what a positive figure *means* depends on the side.
function psBalanceWords(party, balance) {
    const amt = fmtMoney(balance);
    if (Math.abs(balance) < 0.005) return 'Balance: ৳ 0 (settled)';
    if (party.type === 'vendor') return balance > 0 ? `Payable to you: ${amt}` : `Advance paid to you: ${amt}`;
    return balance > 0 ? `Outstanding due: ${amt}` : `Advance with us: ${amt}`;
}

function psPostSend(url, body, btn, busyLabel, doneTitle) {
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin text-[10px]"></i> ${busyLabel}`;
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    })
    .then(r => r.json())
    .then(res => {
        // The server answers one line per channel ("Email sent to …", "SMS
        // failed: …"); success means at least one channel got through.
        const lines = String(res.message || '').split('\n').filter(Boolean);
        Swal.fire({
            icon: res.success ? 'success' : 'error',
            title: res.success ? doneTitle : 'Not sent',
            html: lines.map(l => `<div style="font-size:13px;margin:2px 0">${l.replace(/</g, '&lt;')}</div>`).join('') || (res.success ? '' : 'Failed to send.'),
        });
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' }))
    .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

function psSendStatement() {
    if (!selectedParty || !LAST_STATEMENT) return;
    const party = LAST_STATEMENT.party;
    if (!party.email) {
        Swal.fire({ icon: 'info', title: 'No email on file', text: `${party.name} has no email address. Add one under Party Management first.` });
        return;
    }
    const { from, to } = lastLoadParams;
    const range = from || to ? `${from ? fmtDate(from) : 'start'} – ${to ? fmtDate(to) : 'today'}` : 'the whole ledger to date';
    Swal.fire({
        icon: 'question', title: 'Email this statement?',
        html: `<div style="font-size:13px">To <b>${party.email}</b><br>Covering <b>${range}</b>, with the PDF attached.</div>`,
        showCancelButton: true, confirmButtonText: 'Send email', confirmButtonColor: '#2563eb',
    }).then(r => {
        if (!r.isConfirmed) return;
        psPostSend(SEND_STATEMENT_URL, { party_id: selectedParty.id, from: from || null, to: to || null },
            document.getElementById('psEmailBtn'), 'Sending…', 'Statement emailed');
    });
}

function psWhatsApp() {
    if (!selectedParty || !LAST_STATEMENT) return;
    const party = LAST_STATEMENT.party;
    const digits = String(party.phone || '').replace(/\D/g, '');
    if (digits.length < 10) {
        Swal.fire({ icon: 'info', title: 'No phone on file', text: `${party.name} has no usable phone number. Add one under Party Management first.` });
        return;
    }
    // wa.me wants the number in international form without "+": a local
    // 01XXXXXXXXX becomes 8801XXXXXXXXX; anything already carrying a country
    // code (8801…, 90…) is used as it is.
    const intl = digits.startsWith('0') ? '880' + digits.slice(1) : digits;

    const balance = Number(LAST_STATEMENT.current_balance ?? LAST_STATEMENT.closing_balance) || 0;
    const today = new Date();
    const asOf = fmtDate(today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0'));
    const co = @json(optional(\App\Models\Company::find(2) ?? \App\Models\Company::first())->name ?: 'EPAL TRAVELS & CONSULTANCY');
    // The statement itself rides along as a short link (load() mints it for
    // the range on screen; valid for 30 days) — wa.me can prefill text but never
    // attach a file. If the link is missing (route not registered yet) the
    // message falls back to pointing at the email.
    const pdfUrl = LAST_STATEMENT.share_pdf_url;
    const { from, to } = lastLoadParams;
    const range = from || to ? `${from ? fmtDate(from) : 'start'} – ${to ? fmtDate(to) : asOf}` : `up to ${asOf}`;
    const text = `Dear ${party.name},\n`
        + `Your ledger with ${co} as of ${asOf}: ${psBalanceWords(party, balance)}.\n`
        + `Ref ${party.code || party.id}.\n`
        + (pdfUrl
            ? `Statement (PDF, ${range}; link valid 30 days):\n${pdfUrl}\n`
            : (party.email ? 'The detailed statement (PDF) has been emailed to you.\n' : 'Please contact us for the detailed statement.\n'))
        + `Thank you.`;
    window.open(`https://wa.me/${intl}?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
}

function psSendReminder() {
    if (!selectedParty || !LAST_STATEMENT) return;
    const party = LAST_STATEMENT.party;
    if (!party.phone) {
        Swal.fire({ icon: 'info', title: 'No phone on file', text: `${party.name} has no phone number.` });
        return;
    }
    const { from, to } = lastLoadParams;
    Swal.fire({
        icon: 'question', title: 'Send statement by SMS?',
        html: `<div style="font-size:13px">To <b>${party.phone}</b> only.<br><span style="color:#64748b">Email and WhatsApp will not be sent. SMS charges apply.</span></div>`,
        showCancelButton: true, confirmButtonText: 'Send SMS', confirmButtonColor: '#d97706',
    }).then(r => {
        if (!r.isConfirmed) return;
        psPostSend(SEND_REMINDER_URL, { party_id: selectedParty.id, from: from || null, to: to || null },
            document.getElementById('psReminderBtn'), 'Sending…', 'SMS sent');
    });
}

// ── Export helpers ─────────────────────────────────────────────────────────────
function exportPdf() {
    if (!selectedParty) return;
    const { from, to } = resolveDates();
    const type = resolveType();
    let url = appendQuery(PDF_URL, `party_id=${selectedParty.id}`);
    if (from) url += `&from=${from}`;
    if (to)   url += `&to=${to}`;
    if (type && type !== 'all') url += `&type=${type}`;
    window.open(url, '_blank');
}
function exportExcel() {
    if (!selectedParty) return;
    const { from, to } = resolveDates();
    const type = resolveType();
    let url = appendQuery(EXCEL_URL, `party_id=${selectedParty.id}`);
    if (from) url += `&from=${from}`;
    if (to)   url += `&to=${to}`;
    if (type && type !== 'all') url += `&type=${type}`;
    window.location.href = url;
}

// ── Payment modal ──────────────────────────────────────────────────────────────
const pmAccountTypes = {
    cash: ['cash'],
    bank_transfer: ['bank'],
    cheque: ['bank'],
    online: ['bank'],
    mobile_banking: ['mobile_banking', 'digital_wallet'],
};
const pmPayerCopy = {
    bank_transfer: {
        title: '<i class="fas fa-building-columns mr-1"></i> Party bank details',
        bankLabel: 'Bank Name', bankPlaceholder: 'e.g. Dutch-Bangla Bank',
        refLabel: 'Account Number', refPlaceholder: 'Party account number',
    },
    cheque: {
        title: '<i class="fas fa-money-check mr-1"></i> Cheque details',
        bankLabel: 'Bank Name (cheque drawn on)', bankPlaceholder: 'e.g. Dutch-Bangla Bank',
        refLabel: 'Cheque No.', refPlaceholder: 'Cheque number',
    },
    mobile_banking: {
        title: '<i class="fas fa-mobile-screen-button mr-1"></i> Party mobile account',
        bankLabel: 'Provider / Service', bankPlaceholder: 'e.g. bKash, Nagad or Rocket',
        refLabel: 'Mobile Account Number', refPlaceholder: 'Party mobile account number',
    },
};

function syncPartyPaymentRoute() {
    const method = document.getElementById('pmMode').value;
    const bankSelect = document.getElementById('pmBank');
    const wantedTypes = pmAccountTypes[method] || null;
    let firstVisible = null;
    [...bankSelect.options].forEach(option => {
        if (!option.value) return;
        const hidden = !!wantedTypes && !wantedTypes.includes(option.dataset.type || '');
        option.hidden = hidden;
        option.disabled = hidden;
        if (!hidden && !firstVisible) firstVisible = option;
    });
    bankSelect.querySelectorAll('optgroup').forEach(group => {
        const visible = [...group.querySelectorAll('option')].some(option => !option.hidden);
        group.hidden = !visible;
        group.disabled = !visible;
    });
    if (bankSelect.selectedOptions[0]?.hidden || bankSelect.selectedOptions[0]?.disabled) bankSelect.value = '';
    if (method === 'cash' && firstVisible) {
        const officeCash = [...bankSelect.options].find(option => !option.hidden && /office\s*cash/i.test(option.textContent));
        bankSelect.value = (officeCash || firstVisible).value;
    }

    const copy = pmPayerCopy[method];
    document.getElementById('pmPayerFields').classList.toggle('hidden', !copy);
    const payerBank = document.getElementById('pmPayerBank');
    const payerRef = document.getElementById('pmPayerReference');
    if (copy) {
        document.getElementById('pmPayerTitle').innerHTML = copy.title;
        document.getElementById('pmPayerBankLabel').innerHTML = copy.bankLabel + ' <span class="text-red-500">*</span>';
        document.getElementById('pmPayerReferenceLabel').innerHTML = copy.refLabel + ' <span class="text-red-500">*</span>';
        payerBank.placeholder = copy.bankPlaceholder;
        payerRef.placeholder = copy.refPlaceholder;
    }
    [payerBank, payerRef].forEach(field => {
        field.required = !!copy;
        field.disabled = !copy;
        if (!copy) field.value = '';
    });
}
document.getElementById('pmMode')?.addEventListener('change', syncPartyPaymentRoute);
let pmAllocationDocs = [];

function renderPmAllocations() {
    const mode = document.getElementById('pmAllocationMode').value;
    const amount = Number(document.getElementById('pmAmount').value) || 0;
    let remaining = amount;
    const rows = pmAllocationDocs.map((doc, index) => {
        const autoAmount = mode === 'auto' ? Math.min(remaining, Number(doc.due)) : 0;
        remaining = Math.max(0, remaining - autoAmount);
        const amountControl = mode === 'manual'
            ? `<input type="number" class="pm-allocation-amount rounded-lg border border-gray-200 px-2 py-1.5 text-sm text-right" data-key="${doc.value}" min="0" max="${doc.due}" step="0.01" value="">`
            : `<div class="text-right text-sm font-bold ${autoAmount > 0 ? 'text-emerald-600' : 'text-gray-300'}">${autoAmount > 0 ? '৳' + Number(autoAmount).toLocaleString() : '—'}</div>`;
        return `<div class="grid grid-cols-[1fr_120px] gap-2 items-center px-3 py-2 ${index ? 'border-t border-gray-100' : ''}">
            <div class="min-w-0"><div class="text-xs font-semibold text-gray-700 truncate">${doc.number} · ${doc.service}</div><div class="text-[11px] text-gray-400">Due ৳${Number(doc.due).toLocaleString()}</div></div>
            ${amountControl}
        </div>`;
    }).join('');
    document.getElementById('pmAllocationTable').innerHTML = mode === 'unallocated'
        ? '<div class="px-3 py-3 text-xs text-gray-500">Payment will remain as an advance/general unallocated payment.</div>'
        : (rows || '<div class="px-3 py-3 text-xs text-amber-600">No unpaid invoice or vendor bill found.</div>');
    document.getElementById('pmInvoiceHelp').textContent = mode === 'auto'
        ? 'Oldest unpaid documents are settled first.'
        : (mode === 'manual' ? 'Allocated total must equal the payment amount.' : 'No invoice or bill will be settled.');
    document.getElementById('pmAllocationNoticeTitle').innerHTML = mode === 'unallocated'
        ? '<i class="fas fa-circle-info mr-1"></i> General / unallocated party payment'
        : '<i class="fas fa-link mr-1"></i> Document-linked payment';
    document.getElementById('pmAllocationNoticeBody').textContent = mode === 'unallocated'
        ? 'This payment will not change any invoice or vendor bill.'
        : 'Selected invoices/bills and their linked payment schedules will update together.';
}
document.getElementById('pmAllocationMode')?.addEventListener('change', () => {
    renderPmAllocations();
    paintPmAccountingHint();
});
document.getElementById('pmAmount')?.addEventListener('input', () => {
    if (document.getElementById('pmAllocationMode').value === 'auto') renderPmAllocations();
});

function openPaymentModal(kind) {
    if (!selectedParty) return;
    const modal = document.getElementById('paymentModal');
    const action = document.getElementById('pmAction');
    const vendor = LOCKED_PARTY_TYPE === 'vendor';
    action.innerHTML = vendor
        ? '<option value="payment">Pay Vendor (Cash Out)</option>'
            + (REFUND_URL ? '<option value="refund">Receive Vendor Refund (Cash In)</option>' : '')
        : '<option value="payment">Receive Payment (Cash In)</option>'
            + '<option value="advance_refund">Return Advance (Cash Out)</option>'
            + '<option value="service_refund">Service Refund (Cash Out)</option>';
    action.value = kind === 'refund' && vendor && REFUND_URL ? 'refund' : 'payment';
    document.getElementById('pmPartyDisplay').textContent = selectedParty.name;
    document.getElementById('pmAmount').value  = '';
    document.getElementById('pmAmount').removeAttribute('max');
    document.getElementById('pmMode').value    = 'bank_transfer';
    document.getElementById('pmBank').value    = '';
    document.getElementById('pmPayerBank').value = '';
    document.getElementById('pmPayerReference').value = '';
    document.getElementById('pmNotes').value   = '';
    pmAllocationDocs = [];
    const now = new Date();
    document.getElementById('pmDate').value = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
    document.getElementById('pmHour').value = now.getHours() % 12 || 12;
    document.getElementById('pmMinute').value = now.getMinutes();
    document.getElementById('pmPeriod').value = now.getHours() >= 12 ? 'PM' : 'AM';
    configurePmAction();
    syncPartyPaymentRoute();
    document.getElementById('paymentModal').classList.remove('hidden');
}

function configurePmAction() {
    if (!selectedParty) return;
    pmKind = document.getElementById('pmAction').value || 'payment';
    const modal = document.getElementById('paymentModal');
    const vendor = LOCKED_PARTY_TYPE === 'vendor';
    const isAdvanceRefund = pmKind === 'advance_refund';
    const isServiceRefund = pmKind === 'service_refund';
    const isVendorRefund = pmKind === 'refund';
    const copy = isAdvanceRefund ? {
        title: 'Return Party Advance', subtitle: 'Return advance money held for this agent or customer',
        button: 'Return Advance', help: 'Reduces the party advance and the selected bank/cash balance together.', ref: 'ARF',
    } : (isServiceRefund ? {
        title: 'Pay Service Refund', subtitle: 'Pay a refund created by a cancelled or refunded service',
        button: 'Pay Service Refund', help: 'Settles a service-refund credit and reduces the selected bank/cash balance.', ref: 'SRF',
    } : (isVendorRefund ? {
        title: 'Receive Vendor Refund', subtitle: 'Receive money returned by the vendor',
        button: 'Record Refund', help: 'Reduces the vendor advance and increases the selected bank/cash balance.', ref: 'VRF',
    } : (vendor ? {
        title: 'Pay Vendor', subtitle: 'Pay money to this party',
        button: 'Pay Vendor', help: 'Pays the vendor and reduces the selected bank/cash balance.', ref: 'RCT',
    } : {
        title: 'Receive Payment', subtitle: 'Receive money from this party',
        button: 'Record Receipt', help: 'Receives money and increases the selected bank/cash balance.', ref: 'RCT',
    })));

    modal.querySelector('h3').textContent = copy.title;
    modal.querySelector('h3 + p').textContent = copy.subtitle;
    document.getElementById('pmSubmit').innerHTML = '<i class="fas fa-check text-xs"></i> ' + copy.button;
    document.getElementById('pmActionHelp').textContent = copy.help;
    document.getElementById('pmRef').value = copy.ref + ' reference will be generated automatically';

    const allocationMode = document.getElementById('pmAllocationMode');
    const allocationWrap = document.getElementById('pmAllocationWrap');
    allocationWrap.classList.toggle('hidden', isAdvanceRefund || isServiceRefund || isVendorRefund);
    // A party payment is an overall advance by default. Invoice settlement is
    // an explicit accountant choice, preventing a general vendor payment from
    // being silently distributed across the oldest service bills.
    allocationMode.value = 'unallocated';
    allocationMode.disabled = isAdvanceRefund || isServiceRefund || isVendorRefund;
    renderPmAllocations();

    paintPmAccountingHint();

    pmAllocationDocs = [];
    if (!isAdvanceRefund && !isServiceRefund && !isVendorRefund) {
        fetch(appendQuery(UNPAID_INVOICES_URL, `party_id=${encodeURIComponent(selectedParty.id)}`), { headers: { 'Accept': 'application/json' } })
            .then(response => response.json())
            .then(data => {
                const wantedSide = vendor ? 'payable' : 'sale';
                pmAllocationDocs = (data.invoices || []).filter(doc => doc.side === wantedSide);
                renderPmAllocations();
            });
    }
}

function paintPmAccountingHint() {
    const hint = document.getElementById('pmAccountingHint');
    if (!hint) return;
    const vendor = LOCKED_PARTY_TYPE === 'vendor';
    const unallocated = document.getElementById('pmAllocationMode').value === 'unallocated';
    if (pmKind === 'advance_refund') {
        hint.textContent = 'Posting: Advance from Customers Dr → Company Bank/Cash Cr; party ledger Dr. Available advance cannot be exceeded.';
    } else if (pmKind === 'service_refund') {
        hint.textContent = 'Posting: Accounts Receivable Dr → Company Bank/Cash Cr; party ledger Dr. Available party credit cannot be exceeded.';
    } else if (pmKind === 'refund') {
        hint.textContent = 'Posting: Company Bank/Cash Dr → Accounts Payable (Vendor) Cr.';
    } else if (vendor) {
        hint.textContent = unallocated ? 'Posting: Advance to Supplier Dr → Company Bank/Cash Cr.' : 'Posting: Accounts Payable (Vendor) Dr → Company Bank/Cash Cr.';
    } else {
        hint.textContent = unallocated ? 'Posting: Company Bank/Cash Dr → Advance from Customers Cr.' : 'Posting: Company Bank/Cash Dr → Accounts Receivable (Customer/Agent) Cr.';
    }
}
document.getElementById('pmAction')?.addEventListener('change', configurePmAction);
function closePaymentModal() { document.getElementById('paymentModal').classList.add('hidden'); }

function submitPayment() {
    const amount = document.getElementById('pmAmount').value;
    if (!amount || parseFloat(amount) <= 0) { alert('Please enter a valid amount.'); return; }
    const bankId = document.getElementById('pmBank').value;
    if (!bankId) { alert('Please select the bank or cash account used for this payment.'); return; }
    const method = document.getElementById('pmMode').value;
    const payerBank = document.getElementById('pmPayerBank');
    const payerReference = document.getElementById('pmPayerReference');
    if (pmPayerCopy[method] && (!payerBank.value.trim() || !payerReference.value.trim())) {
        alert('Please complete the party payment account details.'); return;
    }
    let hour = Number(document.getElementById('pmHour').value) || 12;
    const minute = String(document.getElementById('pmMinute').value).padStart(2, '0');
    const period = document.getElementById('pmPeriod').value;
    if (period === 'PM' && hour !== 12) hour += 12;
    if (period === 'AM' && hour === 12) hour = 0;

    const btn = document.getElementById('pmSubmit');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Saving…';

    fetch(pmKind === 'refund' ? REFUND_URL : PAYMENT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
            party_id:       selectedParty.id,
            amount:         amount,
            payment_method: document.getElementById('pmMode').value,
            bank_id:        bankId,
            payment_date:   document.getElementById('pmDate').value,
            transaction_time: String(hour).padStart(2, '0') + ':' + minute,
            payer_bank:     payerBank.value,
            payer_reference: payerReference.value,
            transaction_kind: ['advance_refund', 'service_refund'].includes(pmKind) ? pmKind : 'payment',
            allocation_mode: document.getElementById('pmAllocationMode').value,
            invoice_allocations: [...document.querySelectorAll('.pm-allocation-amount')].map(input => ({ key: input.dataset.key, amount: input.value || 0 })),
            notes:          document.getElementById('pmNotes').value,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closePaymentModal();
            Swal.fire({ icon:'success', title: pmKind === 'advance_refund' ? 'Advance Returned' : (pmKind === 'service_refund' ? 'Service Refund Paid' : (pmKind === 'refund' ? 'Refund Recorded' : 'Payment Recorded')), text: res.message, timer:1800, showConfirmButton:false });
            setTimeout(() => document.getElementById('loadBtn').click(), 1900);
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message||'Failed to record payment.' });
        }
    })
    .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Network error.' }))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check text-xs"></i> '
            + (pmKind === 'advance_refund' ? 'Return Advance' : (pmKind === 'service_refund' ? 'Pay Service Refund' : (pmKind === 'refund' ? 'Record Refund' : (LOCKED_PARTY_TYPE === 'vendor' ? 'Pay Vendor' : 'Record Receipt'))));
    });
}

// ── Invoice window (partials/invoice-window) ─────────────────────────────────────
// Owner, 2026-10-14: one big modal with a switcher, everything inside it —
// Sales invoice: the party's sales listed (newest first), ticked, totalled,
// previewed as the PDF the agent gets, opened or downloaded; New invoice for
// another service: the old Create Invoice (same endpoint), then back to Sales
// with the new charge ticked. Vendors, and a server whose routes lag, keep the
// old Create Invoice modal. Closes only on its ✕.
const IVW = { party: null, lines: [], known: new Map(), picks: new Set(), q: '', from: '', to: '', bar: false, seq: 0 };

function ivwSetCurrentTime() {
    const now = new Date(), hour24 = now.getHours();
    document.getElementById('ivwTimeHour').value = String((hour24 % 12) || 12);
    document.getElementById('ivwTimeMinute').value = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('ivwTimePeriod').value = hour24 >= 12 ? 'PM' : 'AM';
}

function ivwPostingTime() {
    let hour = Number(document.getElementById('ivwTimeHour').value) % 12;
    if (document.getElementById('ivwTimePeriod').value === 'PM') hour += 12;
    return String(hour).padStart(2, '0') + ':' + document.getElementById('ivwTimeMinute').value;
}

function openInvoiceWindow(tab) {
    if (!selectedParty) return;
    const vendor = LOCKED_PARTY_TYPE === 'vendor' || selectedParty.type === 'vendor';
    const modal = document.getElementById('ivwModal');
    if (!SALES_INVOICE_URL || !SALES_INVOICE_LINES_URL || !modal || vendor) { openInvoiceModal(); return; }

    const party = (LAST_STATEMENT && LAST_STATEMENT.party) || {};
    document.getElementById('ivwParty').textContent = selectedParty.name || party.name || '—';
    document.getElementById('ivwCode').textContent = party.code || ('ID-' + String(selectedParty.id).padStart(4, '0'));
    document.getElementById('ivwNewParty').textContent = selectedParty.name || party.name || '—';
    document.getElementById('ivwPostParty').textContent = selectedParty.name || party.name || '—';

    if (IVW.party !== selectedParty.id) {           // another party: nothing carried over
        IVW.party = selectedParty.id; IVW.picks.clear(); IVW.known.clear(); IVW.lines = [];
        ['ivwDesc', 'ivwAmount', 'ivwDue', 'ivwDueText'].forEach(id => { document.getElementById(id).value = ''; });
        ivwSetCurrentTime();
        ivwNewSums();
    }
    if (!IVW.bar) {
        IVW.bar = true;
        document.querySelectorAll('#ivwModal .ivw-switch button').forEach(b => b.addEventListener('click', () => ivwTab(b.dataset.tab)));
        document.getElementById('ivwAll').addEventListener('change', e => ivwPickShown(e.target.checked));
        document.getElementById('ivwRows').addEventListener('click', e => {
            const tr = e.target.closest('tr[data-id]');
            if (!tr) return;
            const id = Number(tr.dataset.id);
            ivwPick(id, e.target.matches('input[type=checkbox]') ? e.target.checked : !IVW.picks.has(id));
        });
        document.getElementById('ivwAmount').addEventListener('input', ivwNewSums);
        // ⚙ logo + signature (only drawn for those who may edit company settings)
        const gear = document.getElementById('ivwGear');
        if (gear) {
            gear.addEventListener('click', () => ivwTab(gear.classList.contains('is-on') ? 'sales' : 'settings'));
            const pick = (inputId, prevId, pickedId) => document.getElementById(inputId).addEventListener('change', e => {
                const f = e.target.files[0];
                document.getElementById(pickedId).textContent = f ? f.name + ' — not saved yet' : '';
                if (!f) return;
                const prev = document.getElementById(prevId), line = prev.querySelector('.line');
                prev.querySelectorAll('img, .none').forEach(n => n.remove());
                const img = document.createElement('img'); img.src = URL.createObjectURL(f);
                line ? prev.insertBefore(img, line) : prev.appendChild(img);
                if (inputId === 'ivwSigFile') { IVW.removeSig = false; document.getElementById('ivwSigRemove').style.display = ''; }
            });
            pick('ivwLogoFile', 'ivwLogoPrev', 'ivwLogoPicked');
            pick('ivwSigFile', 'ivwSigPrev', 'ivwSigPicked');
            document.getElementById('ivwSigRemove').addEventListener('click', () => {
                IVW.removeSig = true;
                document.getElementById('ivwSigFile').value = '';
                const prev = document.getElementById('ivwSigPrev');
                prev.querySelectorAll('img, .none').forEach(n => n.remove());
                prev.insertAdjacentHTML('afterbegin', '<span class="none" style="margin-bottom:auto;margin-top:auto">No signature — the line is left blank</span>');
                document.getElementById('ivwSigPicked').textContent = 'Removed — not saved yet';
                document.getElementById('ivwSigRemove').style.display = 'none';
            });
        }
        // Due date: typed dd/mm/yyyy, or picked from the calendar beside it.
        const dueText = document.getElementById('ivwDueText'), due = document.getElementById('ivwDue');
        dueText.addEventListener('change', () => {
            const ymd = psParseDate(dueText.value);
            dueText.classList.toggle('is-bad', ymd === null);
            if (ymd === null) return;
            due.value = ymd; dueText.value = ymd ? psDMY(ymd) : '';
        });
        due.addEventListener('change', () => { dueText.value = due.value ? psDMY(due.value) : ''; dueText.classList.remove('is-bad'); });
        IVW.ranges = {
            week:  () => [psDaysBack(6), psYmd(new Date())],
            month: () => [psYmd(new Date()).slice(0, 8) + '01', psYmd(new Date())],
            year:  () => [new Date().getFullYear() + '-01-01', psYmd(new Date())],
            all:   () => ['', ''],
        };
        psInitBar('iv', {
            onSearch: term => { IVW.q = term; ivwRender(); },
            onRange: (key, from, to) => ivwLoad(from, to),
            ranges: IVW.ranges,
            initial: 'year',
        });
        [IVW.from, IVW.to] = IVW.ranges.year();
    }
    ivwSetCurrentTime();
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    ivwShowList();
    ivwTab(tab || 'sales');
    if (!IVW.lines.length) ivwLoad(IVW.from, IVW.to); else ivwRender();
}
function closeInvoiceWindow() {
    document.getElementById('ivwModal').hidden = true;
    document.getElementById('ivwFrame').src = 'about:blank';
    document.body.style.overflow = '';
}
function ivwTab(name) {
    document.querySelectorAll('#ivwModal .ivw-switch button').forEach(b => b.classList.toggle('is-on', b.dataset.tab === name));
    const gear = document.getElementById('ivwGear'); if (gear) gear.classList.toggle('is-on', name === 'settings');
    document.querySelectorAll('#ivwModal [data-pane]').forEach(p => { p.hidden = p.dataset.pane !== name; });
    if (name === 'new') setTimeout(() => document.getElementById('ivwDesc').focus(), 50);
}

// The party's sales for a period, from the server — the same rule and words as the PDF.
function ivwLoad(from, to) {
    IVW.from = from || ''; IVW.to = to || '';
    const seq = ++IVW.seq;
    document.getElementById('ivwRows').innerHTML = '<tr><td colspan="5" class="ivw-empty">Loading sales…</td></tr>';
    let url = appendQuery(SALES_INVOICE_LINES_URL, `party_id=${selectedParty.id}`);
    if (IVW.from) url += `&from=${IVW.from}`;
    if (IVW.to) url += `&to=${IVW.to}`;
    return fetch(url, { headers: { Accept: 'application/json' } })
        .then(r => r.ok ? r.json() : Promise.reject(r.status))
        .then(res => {
            if (seq !== IVW.seq) return;
            IVW.lines = res.lines || [];
            IVW.lines.forEach(l => IVW.known.set(l.id, l));
            ivwRender();
        })
        .catch(() => { if (seq === IVW.seq) document.getElementById('ivwRows').innerHTML = '<tr><td colspan="5" class="ivw-empty">Could not load the sales. Close and try again.</td></tr>'; });
}

function ivwShown() {
    const q = IVW.q;
    const rows = IVW.lines.slice().sort((a, b) => (b.date + String(b.id).padStart(9, '0')).localeCompare(a.date + String(a.id).padStart(9, '0')));
    if (!q) return rows;
    return rows.filter(l => [l.ref, l.day, l.kind, l.item2, l.p1, l.p2, l.pnr, l.debit, l.credit].join(' ').toLowerCase().includes(q));
}
function ivwAmt(v) { return Number(v).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function ivwRender() {
    const rows = ivwShown();
    const body = document.getElementById('ivwRows');
    if (!rows.length) {
        body.innerHTML = `<tr><td colspan="5" class="ivw-empty">${IVW.lines.length ? 'No sale matches the search.' : 'No sales in this period — try This Year or All Time.'}</td></tr>`;
    } else {
        body.innerHTML = rows.map(l => {
            const credit = l.credit > 0 && !(l.debit > 0);
            return `<tr data-id="${l.id}" class="${IVW.picks.has(l.id) ? 'is-on' : ''}">
                <td><input type="checkbox" ${IVW.picks.has(l.id) ? 'checked' : ''} aria-label="Put on the invoice"></td>
                <td><span class="p ref">${esc(l.ref || '—')}</span><span class="s">${esc(l.day)}</span></td>
                <td><span class="p">${esc(l.kind)}</span><span class="s ${l.flag ? 'flag' : ''}">${esc(l.item2)}</span></td>
                <td><span class="p">${esc(l.p1)}</span><span class="s">${esc(l.p2)}${l.pnr ? ` · <b class="pnr">${esc(l.pnr)}</b>` : ''}</span></td>
                <td class="r"><span class="p ${credit ? 'cr' : ''}">${credit ? '− ' + ivwAmt(l.credit) : ivwAmt(l.debit)}</span><span class="s">${credit ? 'Less' : 'Charge'}</span></td>
            </tr>`;
        }).join('');
    }
    const count = document.getElementById('ivCount');
    if (count) { count.style.display = ''; count.textContent = rows.length + (rows.length === 1 ? ' sale' : ' sales'); }
    ivwPaintSide();
}

function ivwPick(id, on) { if (on) IVW.picks.add(id); else IVW.picks.delete(id); ivwRender(); }
function ivwPickShown(on) { ivwShown().forEach(l => on ? IVW.picks.add(l.id) : IVW.picks.delete(l.id)); ivwRender(); }

// The right-hand panel: what is ticked, in ledger order, its totals and the PDF links.
function ivwPaintSide() {
    const picked = [...IVW.picks].map(id => IVW.known.get(id)).filter(Boolean)
        .sort((a, b) => (a.date + String(a.id).padStart(9, '0')).localeCompare(b.date + String(b.id).padStart(9, '0')));
    const shown = ivwShown();
    const all = document.getElementById('ivwAll');
    const on = shown.filter(l => IVW.picks.has(l.id)).length;
    all.checked = shown.length > 0 && on === shown.length;
    all.indeterminate = on > 0 && on < shown.length;

    const charges = picked.reduce((s, l) => s + (l.debit || 0), 0);
    const credits = picked.reduce((s, l) => s + (l.credit || 0), 0);
    document.getElementById('ivwSelN').textContent = picked.length + (picked.length === 1 ? ' sale' : ' sales');
    document.getElementById('ivwSelList').innerHTML = picked.length
        ? picked.map(l => {
            const credit = l.credit > 0 && !(l.debit > 0);
            return `<div class="ivw-side-row"><span class="r">${esc(l.ref || '—')}</span><span class="n">${esc(l.p1)}</span><span class="a ${credit ? 'cr' : ''}">${credit ? '− ' + ivwAmt(l.credit) : ivwAmt(l.debit)}</span></div>`;
        }).join('')
        : '<div class="ivw-empty">Tick the sales to put on the invoice — one, several or all of them.</div>';
    document.getElementById('ivwCharges').textContent = ivwAmt(charges);
    document.getElementById('ivwCredits').textContent = '− ' + ivwAmt(credits);
    document.getElementById('ivwCreditsRow').hidden = !(credits > 0);
    document.getElementById('ivwTotal').textContent = '৳ ' + ivwAmt(charges - credits);

    const url = ivwUrl();
    const open = document.getElementById('ivwOpen'), dl = document.getElementById('ivwDownload');
    open.href = url || '#'; dl.href = url ? url + '&download=1' : '#';
    [open, dl, document.getElementById('ivwPreviewBtn')].forEach(el => el.classList.toggle('is-off', !url));
}
function ivwUrl() {
    if (!IVW.picks.size || !selectedParty) return null;
    const ids = [...IVW.picks].join(',');
    return appendQuery(SALES_INVOICE_URL, `party_id=${selectedParty.id}&entries=${ids}`);
}
function ivwPreview() {
    const url = ivwUrl();
    if (!url) return;
    document.getElementById('ivwFrame').src = url;
    document.getElementById('ivwListWrap').hidden = true;
    document.getElementById('ivBar').hidden = true;
    document.getElementById('ivwPreview').hidden = false;
}
function ivwShowList() {
    document.getElementById('ivwPreview').hidden = true;
    document.getElementById('ivwListWrap').hidden = false;
    const bar = document.getElementById('ivBar'); if (bar) bar.hidden = false;
}

// ── ⚙ Invoice settings: logo and signature (the company's own, as in Company Settings) ──
function ivwBrandSave() {
    if (!INVOICE_BRANDING_URL) return;
    const logo = document.getElementById('ivwLogoFile').files[0], sig = document.getElementById('ivwSigFile').files[0];
    if (!logo && !sig && !IVW.removeSig) { Swal.fire({ icon: 'info', title: 'Nothing to save', text: 'Choose a new logo or signature first.' }); return; }
    const fd = new FormData();
    if (logo) fd.append('logo', logo);
    if (sig) fd.append('signature', sig);
    if (IVW.removeSig && !sig) fd.append('remove_signature', '1');
    const btn = document.getElementById('ivwBrandSave');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    fetch(INVOICE_BRANDING_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' }, body: fd })
        .then(async r => ({ ok: r.ok, res: await r.json().catch(() => ({})) }))
        .then(({ ok, res }) => {
            if (!ok || !res.success) {
                const msg = res.message || (res.errors ? Object.values(res.errors).flat()[0] : 'Could not save.');
                Swal.fire({ icon: 'error', title: 'Not saved', text: msg }); return;
            }
            ['ivwLogoFile', 'ivwSigFile'].forEach(id => { document.getElementById(id).value = ''; });
            ['ivwLogoPicked', 'ivwSigPicked'].forEach(id => { document.getElementById(id).textContent = ''; });
            IVW.removeSig = false;
            document.getElementById('ivwSigRemove').style.display = res.signature ? '' : 'none';
            document.getElementById('ivwFrame').src = 'about:blank';     // the next preview is drawn with the new images
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.message || 'Saved', timer: 2200, showConfirmButton: false });
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' }))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Save'; });
}

// ── New invoice for another service ─────────────────────────────────────────────
function ivwNewSums() {
    const v = parseFloat(document.getElementById('ivwAmount').value) || 0;
    ['ivwPostDr', 'ivwPostCr', 'ivwNewTotal'].forEach(id => { document.getElementById(id).textContent = '৳ ' + ivwAmt(v); });
}
function ivwCreate() {
    const desc = document.getElementById('ivwDesc').value.trim();
    const amount = document.getElementById('ivwAmount').value;
    if (!desc) { Swal.fire({ icon: 'warning', title: 'Service description', text: 'Write what the service is.' }); return; }
    if (!amount || parseFloat(amount) <= 0) { Swal.fire({ icon: 'warning', title: 'Amount', text: 'Enter the amount to charge.' }); return; }
    if (document.getElementById('ivwDueText').classList.contains('is-bad')) { Swal.fire({ icon: 'warning', title: 'Due date', text: 'Write the due date as dd/mm/yyyy, or pick it from the calendar.' }); return; }
    const btn = document.getElementById('ivwCreate');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating…';
    fetch(INVOICE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        body: JSON.stringify({
            party_id: selectedParty.id, service_description: desc, amount,
            due_date: document.getElementById('ivwDue').value,
            invoice_time: ivwPostingTime(),
        }),
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) { Swal.fire({ icon: 'error', title: 'Not created', text: res.message || 'Failed to create the invoice.' }); return; }
        ['ivwDesc', 'ivwAmount', 'ivwDue', 'ivwDueText'].forEach(id => { document.getElementById(id).value = ''; });
        ivwSetCurrentTime();
        ivwNewSums();
        document.getElementById('loadBtn').click();           // the ledger behind refreshes
        // Back to Sales with the new charge ticked; it is dated today, so the year holds it.
        ivwTab('sales');
        if (IVW.from && IVW.from > psYmd(new Date())) IVW.from = '';
        IVW.to = IVW.to && IVW.to < psYmd(new Date()) ? psYmd(new Date()) : IVW.to;
        ivwLoad(IVW.from, IVW.to).then(() => {
            if (res.transaction_id) { IVW.picks.clear(); IVW.picks.add(Number(res.transaction_id)); ivwRender(); ivwPreview(); }
        });
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: (res.invoice_number || 'Invoice') + ' created', timer: 2200, showConfirmButton: false });
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' }))
    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Create invoice'; });
}

// ── Invoice modal ──────────────────────────────────────────────────────────────
function openInvoiceModal() {
    if (!selectedParty) return;
    document.getElementById('invPartyDisplay').textContent = selectedParty.name;
    document.getElementById('invDesc').value       = '';
    document.getElementById('invAmount').value     = '';
    document.getElementById('invDue').value        = '';
    document.getElementById('invCountry').value    = '';
    document.getElementById('invCandidates').value = '';
    document.getElementById('invNotes').value      = '';
    document.getElementById('invoiceModal').classList.remove('hidden');
}
function closeInvoiceModal() { document.getElementById('invoiceModal').classList.add('hidden'); }

function submitInvoice() {
    const desc   = document.getElementById('invDesc').value.trim();
    const amount = document.getElementById('invAmount').value;
    if (!desc)                        { alert('Please enter a service description.'); return; }
    if (!amount || parseFloat(amount) <= 0) { alert('Please enter a valid amount.'); return; }

    const btn = document.getElementById('invSubmit');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Creating…';

    fetch(INVOICE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
            party_id:            selectedParty.id,
            service_description: desc,
            amount:              amount,
            due_date:            document.getElementById('invDue').value,
            country_id:          document.getElementById('invCountry').value,
            no_of_candidates:    document.getElementById('invCandidates').value,
            notes:               document.getElementById('invNotes').value,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeInvoiceModal();
            Swal.fire({ icon:'success', title:'Invoice Created', text: res.message, timer:1800, showConfirmButton:false });
            setTimeout(() => document.getElementById('loadBtn').click(), 1900);
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message||'Failed to create invoice.' });
        }
    })
    .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Network error.' }))
    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-invoice text-xs"></i> Create Invoice'; });
}

// ── Opening balance modal ──────────────────────────────────────────────────────
function openOpeningBalanceModal() {
    if (!selectedParty) return;
    document.getElementById('obPartyDisplay').textContent = selectedParty.name;
    document.getElementById('obAmount').value    = '';
    document.getElementById('obDirection').value = 'receivable';
    document.getElementById('obDate').value      = new Date().toISOString().split('T')[0];
    document.getElementById('obNote').value      = '';
    document.getElementById('obModal').classList.remove('hidden');
}
function closeOpeningBalanceModal() { document.getElementById('obModal').classList.add('hidden'); }

function submitOpeningBalance() {
    const amount = document.getElementById('obAmount').value;
    const asOfDate = document.getElementById('obDate').value;
    if (!amount || parseFloat(amount) <= 0) { alert('Please enter a valid amount.'); return; }
    if (!asOfDate) { alert('Please select an as-of date.'); return; }

    const btn = document.getElementById('obSubmit');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Saving…';

    fetch(OPENING_BALANCE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
            party_id:   selectedParty.id,
            amount:     amount,
            direction:  document.getElementById('obDirection').value,
            as_of_date: asOfDate,
            note:       document.getElementById('obNote').value,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeOpeningBalanceModal();
            Swal.fire({ icon:'success', title:'Opening Balance Set', text: res.message, timer:1800, showConfirmButton:false });
            setTimeout(() => document.getElementById('loadBtn').click(), 1900);
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message||'Failed to set opening balance.' });
        }
    })
    .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Network error.' }))
    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-flag text-xs"></i> Set Opening Balance'; });
}

// Escape closes all modals
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closePaymentModal(); closeInvoiceModal(); closeOpeningBalanceModal(); }
});


{{-- Deep link: ?party_id= opens that party on arrival. Ledger pages only —
     the controller has already checked the id belongs on this ledger. --}}
@if(!empty($autoPartyId))
openParty({{ (int) $autoPartyId }});
@endif

// ── The statement's table bar ─────────────────────────────────────────────────
(function initStatementBar() {
    if (!document.getElementById('stBar')) return;
    // Search: redraw the loaded statement, nothing fetched.
    // Range: write the hidden preset boxes resolveDates() reads, then Load.
    psInitBar('st', {
        onSearch: term => { STMT_Q = term; if (LAST_STATEMENT) renderStatement(LAST_STATEMENT, true); },
        onRange: (key, from, to) => {
            const preset = document.getElementById('datePreset');
            if (key === 'all') {
                preset.value = 'all_time';
                document.getElementById('dateFrom').value = '';
                document.getElementById('dateTo').value = '';
            }
            else {
                preset.value = 'custom';
                document.getElementById('dateFrom').value = from;
                document.getElementById('dateTo').value   = to;
            }
            if (selectedParty) document.getElementById('loadBtn').click();
        },
        ranges: {
            today:     () => [psYmd(new Date()), psYmd(new Date())],
            yesterday: () => [psDaysBack(1), psDaysBack(1)],
            week:      () => [psDaysBack(6), psYmd(new Date())],
            month:     () => [psYmd(new Date()).slice(0, 8) + '01', psYmd(new Date())],
            year:      () => [new Date().getFullYear() + '-01-01', psYmd(new Date())],
            all:       () => ['', ''],
        },
        initial: 'year',   // the preset select's own default
    });
    // Print — Manage Bank's chooser. The sheet is print-statement (exportPdf's
    // view): a day or range re-asks the server for that period; ticked rows
    // go as ?entries= and win over the dates.
    //
    // Templates mirror PartyStatementController::NOTE_TEMPLATES exactly —
    // the text sent to the server IS the picked template's text (or
    // whatever it was edited to), so there's nothing to keep in sync beyond
    // this one JSON pull below.
    const PARTY_NOTE_TEMPLATES = @json(\App\Http\Controllers\Dashboard\PartyStatementController::NOTE_TEMPLATES);
    const PARTY_NOTE_TEMPLATE_LABELS = {
        general: 'General reminder (default)',
        due: 'Please clear due amount',
        mismatch: 'Transaction mismatch check',
    };
    // ── Print designer (owner, 2026-10-28) ─────────────────────────────────
    // A bigger chooser for the statement only: what to print, which entry
    // categories (all ticked), and how the sheet looks — title, columns,
    // stamp, signatures, generator, bank details, watermark, orientation,
    // accent. Choices are remembered per browser; the server ignores any it
    // does not know, so a bare link still prints the classic sheet.
    const PS_KIND = @json(\App\Support\StatementRows::KIND);
    const PS_COLS = @json(collect(\App\Http\Controllers\Dashboard\PartyStatementController::PRINT_COLUMNS)->map(fn ($c) => ['label' => $c[0], 'on' => $c[2]]));
    const PS_SIGS = @json(\App\Http\Controllers\Dashboard\PartyStatementController::PRINT_SIGNATURES);
    const PS_ME   = @json(auth()->user()?->name ?? '');
    const PS_STUDIO_KEY = 'ps-print-designer-v1';
    function psStudioSaved() { try { return JSON.parse(localStorage.getItem(PS_STUDIO_KEY) || '{}') || {}; } catch (_) { return {}; } }
    // Signature images (drawn or uploaded) live in this browser only, keyed by
    // slot; the print sheet (same origin) reads them back by the same key.
    const PS_SIG_KEY = 'ps-print-signatures-v1';
    function psSigImages() { try { return JSON.parse(localStorage.getItem(PS_SIG_KEY) || '{}') || {}; } catch (_) { return {}; } }
    function psSigStore(slot, dataUrl) {
        const all = psSigImages();
        if (dataUrl) all[slot] = dataUrl; else delete all[slot];
        try { localStorage.setItem(PS_SIG_KEY, JSON.stringify(all)); return true; } catch (_) { return false; }
    }

    function psStatementStudio(prefix) {
        const cfg = PS_PRINT[prefix];
        const picks = PS_PICKS[prefix], n = picks.size;
        const period = cfg.period() || {};
        const today = psYmd(new Date());
        const shownLabel = period.from && period.to
            ? (period.from === period.to ? psDMY(period.from) : `${psDMY(period.from)} – ${psDMY(period.to)}`)
            : (period.from ? `from ${psDMY(period.from)}` : period.to ? `up to ${psDMY(period.to)}` : 'all time');
        const sv = psStudioSaved();
        const esc = v => String(v ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        // Categories = the entry names in the loaded statement, several types per name.
        const groups = {};
        (LAST_STATEMENT ? LAST_STATEMENT.transactions : []).forEach(t => {
            const label = PS_KIND[t.type] || String(t.type || 'Other').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            (groups[label] = groups[label] || { types: new Set(), count: 0 }).types.add(t.type);
            groups[label].count++;
        });
        const catHtml = Object.keys(groups).sort().map(l => `
            <label class="pz-chip"><input type="checkbox" class="pz-cat" value="${esc([...groups[l].types].join(','))}" checked>
            <span>${esc(l)} <em>${groups[l].count}</em></span></label>`).join('') || '<span class="pz-hint">No entries loaded.</span>';

        const colsOn = sv.cols || Object.keys(PS_COLS).filter(k => PS_COLS[k].on);
        const colHtml = Object.keys(PS_COLS).map(k => `
            <label class="pz-chip"><input type="checkbox" class="pz-col" value="${k}" ${colsOn.includes(k) ? 'checked' : ''}><span>${esc(PS_COLS[k].label)}</span></label>`).join('');
        const sigsOn = sv.sigs || ['authorized'];
        const sigImgs = psSigImages();
        const sigHtml = Object.keys(PS_SIGS).map(k => `
            <span class="pz-sigwrap"><label class="pz-chip"><input type="checkbox" class="pz-sig" value="${k}" ${sigsOn.includes(k) ? 'checked' : ''}><span>${esc(PS_SIGS[k])}</span></label><button type="button" class="pz-sigpen ${sigImgs[k] ? 'has' : ''}" data-sigpen="${k}" title="${sigImgs[k] ? 'Change signature' : 'Add signature'}"><i class="fas fa-${sigImgs[k] ? 'signature' : 'pen-nib'}"></i></button></span>`).join('');
        const stamps = { '': 'None', paid: 'PAID', received: 'RECEIVED', due: 'PAYMENT DUE', original: 'ORIGINAL', copy: 'COPY', verified: 'VERIFIED', custom: 'Custom…' };
        const stampHtml = Object.keys(stamps).map(k => `
            <label class="pz-chip"><input type="radio" name="pzStamp" value="${k}" ${(sv.stamp || '') === k ? 'checked' : ''}><span>${stamps[k]}</span></label>`).join('');
        const accents = ['#4f46e5', '#0f766e', '#1d4ed8', '#b45309', '#be123c', '#334155'];
        const accentHtml = accents.map(c => `
            <label class="pz-sw" style="--c:${c}"><input type="radio" name="pzAccent" value="${c}" ${(sv.accent || '#4f46e5') === c ? 'checked' : ''}><span></span></label>`).join('');
        const tog = (id, label, on) => `<label class="pz-tog"><input type="checkbox" id="${id}" ${on ? 'checked' : ''}><span>${label}</span></label>`;
        const on = (k, d = true) => sv[k] === undefined ? d : !!sv[k];
        const mode0 = n ? 'selected' : 'shown';
        const opt = (value, label, hint, extra = '') => `
            <label class="pd-opt"><input type="radio" name="pdMode" value="${value}" ${value === mode0 ? 'checked' : ''}>
            <span class="pd-body"><span class="pd-label">${label}</span>${hint ? `<span class="pd-hint">${hint}</span>` : ''}${extra}</span></label>`;
        const keys = Object.keys(cfg.noteTemplates || {});

        Swal.fire({
            title: 'Print statement',
            width: 'min(1180px, calc(100vw - 32px))',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-print"></i> Print',
            confirmButtonColor: '#4f46e5',
            focusConfirm: false,
            html: `
            <style>
                .pz { display:grid; grid-template-columns: 1fr 1.25fr 220px; gap:16px; text-align:left; max-height: calc(100vh - 220px); }
                .pz > section { overflow:auto; padding-right:4px; }
                .pz h5 { font-size:10.5px; font-weight:800; color:#4f46e5; text-transform:uppercase; letter-spacing:.07em; margin:12px 0 6px; display:flex; justify-content:space-between; align-items:center; }
                .pz h5:first-child { margin-top:0; }
                .pz h5 a { font-size:10px; color:#64748b; text-transform:none; letter-spacing:0; font-weight:600; cursor:pointer; margin-left:8px; }
                .pz .pd-opt { display:flex; gap:10px; padding:8px 10px; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer; margin-bottom:6px; }
                .pz .pd-opt:has(input:checked) { border-color:#6366f1; background:#f5f6ff; }
                .pz .pd-opt input { accent-color:#4f46e5; margin-top:3px; }
                .pz .pd-body { display:flex; flex-direction:column; gap:3px; flex:1; }
                .pz .pd-label { font-size:13px; font-weight:700; color:#1e293b; }
                .pz .pd-hint, .pz-hint { font-size:11px; color:#64748b; }
                .pz .pd-dates { display:flex; gap:6px; align-items:center; font-size:11px; color:#64748b; }
                .pz input[type=date], .pz input[type=text], .pz select, .pz textarea { padding:6px 9px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px; color:#1e293b; font-family:inherit; box-sizing:border-box; background:#fff; }
                .pz input[type=text], .pz select, .pz textarea { width:100%; }
                .pz textarea { min-height:62px; resize:vertical; margin-top:6px; }
                .pz-chips { display:flex; flex-wrap:wrap; gap:6px; }
                .pz-chip { position:relative; cursor:pointer; }
                .pz-chip input { position:absolute; opacity:0; pointer-events:none; }
                .pz-chip span { display:inline-flex; gap:5px; align-items:center; padding:5px 10px; border:1px solid #e2e8f0; border-radius:999px; font-size:11.5px; font-weight:600; color:#475569; background:#fff; }
                .pz-chip span em { font-style:normal; font-size:10px; color:#94a3b8; }
                .pz-chip input:checked + span { border-color:#6366f1; background:#eef2ff; color:#3730a3; }
                .pz-tog { display:flex; align-items:center; gap:8px; font-size:12px; color:#334155; padding:4px 0; cursor:pointer; }
                .pz-tog input { accent-color:#4f46e5; width:15px; height:15px; }
                .pz-row { display:grid; grid-template-columns:1fr 1fr; gap:4px 10px; align-items:center; }
                .pz-sw { position:relative; cursor:pointer; }
                .pz-sw input { position:absolute; opacity:0; pointer-events:none; }
                .pz-sw span { display:block; width:22px; height:22px; border-radius:50%; background:var(--c); box-shadow:0 0 0 2px #fff, 0 0 0 3px #e2e8f0; }
                .pz-sw input:checked + span { box-shadow:0 0 0 2px #fff, 0 0 0 4px var(--c); }
                .pz-seg { display:inline-flex; border:1px solid #e2e8f0; border-radius:7px; overflow:hidden; }
                .pz-seg label { cursor:pointer; } .pz-seg input { display:none; }
                .pz-seg span { display:block; padding:5px 12px; font-size:11.5px; font-weight:600; color:#475569; }
                .pz-seg input:checked + span { background:#4f46e5; color:#fff; }
                .pz-paper { position:relative; background:#fff; border:1px solid #e2e8f0; border-radius:4px; box-shadow:0 6px 18px rgba(15,23,42,.08); padding:12px 10px; aspect-ratio: 1 / 1.414; overflow:hidden; font-size:6px; color:#64748b; }
                .pz-paper.land { aspect-ratio: 1.414 / 1; }
                .pz-p-hd { display:flex; justify-content:space-between; font-weight:800; color:#1e293b; font-size:7px; }
                .pz-p-bar { margin:6px 0; text-align:center; font-weight:800; font-size:6.5px; padding:3px; border-top:1.5px solid var(--pa); color:var(--pa); background:#f5f7ff; text-transform:uppercase; letter-spacing:.06em; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
                .pz-p-sum { height:12px; border:1px solid #e2e8f0; border-radius:2px; margin-bottom:5px; }
                .pz-p-th { display:flex; gap:1px; background:var(--pa); color:#fff; font-weight:700; font-size:4.6px; padding:2px; }
                .pz-p-th span { flex:1; overflow:hidden; white-space:nowrap; }
                .pz-p-tr { height:5px; border-bottom:1px solid #eef1f6; }
                .pz-p-stamp { position:absolute; top:22%; right:10%; transform:rotate(-14deg); border:2px double currentColor; border-radius:4px; padding:2px 6px; font-weight:800; font-size:8px; letter-spacing:.08em; }
                .pz-p-wm { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; transform:rotate(-30deg); font-weight:800; font-size:18px; color:rgba(79,70,229,.08); }
                .pz-p-sigs { display:flex; justify-content:flex-end; gap:6px; margin-top:8px; }
                .pz-p-sigs span { flex:0 1 30%; border-top:1px solid #333; padding-top:1px; font-size:4.5px; text-align:center; }
                .pz-p-gen { margin-top:5px; text-align:center; font-size:4.5px; color:#94a3b8; }
                .pz-sigwrap { display:inline-flex; align-items:center; gap:2px; }
                .pz-sigpen { width:26px; height:26px; border:1px dashed #cbd5e1; border-radius:50%; background:#fff; color:#64748b; font-size:11px; cursor:pointer; }
                .pz-sigpen:hover { border-color:#6366f1; color:#4f46e5; }
                .pz-sigpen.has { border-style:solid; border-color:#10b981; color:#059669; background:#ecfdf5; }
                .pz-pad { margin-top:8px; border:1px solid #c7d2fe; border-radius:8px; padding:8px; background:#f8faff; }
                .pz-pad-hd { display:flex; justify-content:space-between; align-items:baseline; font-size:12px; color:#1e293b; margin-bottom:6px; }
                .pz-pad canvas { width:100%; height:auto; aspect-ratio: 720 / 200; background:#fff; border:1px dashed #cbd5e1; border-radius:6px; touch-action:none; cursor:crosshair; display:block;
                    background-image: linear-gradient(#fff, #fff), linear-gradient(#e2e8f0, #e2e8f0); background-size: 100% 100%, 80% 1px; background-position: 0 0, 50% 78%; background-repeat:no-repeat; }
                .pz-pad-bar { display:flex; gap:6px; align-items:center; margin-top:6px; flex-wrap:wrap; }
                .pz-btn { display:inline-flex; gap:5px; align-items:center; padding:5px 10px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; font-size:11.5px; font-weight:600; color:#334155; cursor:pointer; }
                .pz-btn.primary { background:#4f46e5; border-color:#4f46e5; color:#fff; }
                .pz-btn.danger { color:#dc2626; }
                .pz-p-seal { position:absolute; bottom:9%; left:42%; width:22px; height:22px; border:1.5px double #1d4ed8; border-radius:50%; color:#1d4ed8; font-size:4px; font-weight:800; display:flex; align-items:center; justify-content:center; transform:rotate(-12deg); opacity:.8; }
                .pz-p-seal.img { border:none; object-fit:contain; }
                .pz-p-sigs img { display:block; max-height:9px; max-width:90%; margin:0 auto 1px; }
                @media (max-width: 900px) { .pz { grid-template-columns:1fr; max-height:none; } .pz-prev { display:none; } }
            </style>
            <div class="pz">
                <section>
                    <h5>What to print</h5>
                    ${opt('range', 'A date range', '', `<div class="pd-dates"><input type="date" id="pdFrom" value="${period.from || today}"> to <input type="date" id="pdTo" value="${period.to || today}"></div>`)}
                    ${opt('selected', n ? `Selected transactions (${n})` : 'Selected transactions', n ? 'Only the rows you ticked' : 'Tick the rows you want in the table')}
                    ${opt('shown', 'What the table shows now', shownLabel)}
                    <h5>Categories <span><a data-all="1">All</a><a data-all="0">None</a></span></h5>
                    <div class="pz-chips">${catHtml}</div>
                    <h5>Note on the statement</h5>
                    <select id="pdNoteTemplate">${keys.map(k => `<option value="${k}">${esc(cfg.noteTemplateLabels[k] || k)}</option>`).join('')}<option value="custom">Custom — write your own</option></select>
                    <textarea id="pdNote">${esc(keys.length ? cfg.noteTemplates[keys[0]] : '')}</textarea>
                </section>
                <section>
                    <h5>Title &amp; look</h5>
                    <input type="text" id="pzTitle" maxlength="80" placeholder="Party Statement of Account" value="${esc(sv.title || '')}">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;gap:10px;flex-wrap:wrap;">
                        <div class="pz-seg"><label><input type="radio" name="pzOrient" value="portrait" ${sv.orient !== 'landscape' ? 'checked' : ''}><span>Portrait</span></label><label><input type="radio" name="pzOrient" value="landscape" ${sv.orient === 'landscape' ? 'checked' : ''}><span>Landscape</span></label></div>
                        <div class="pz-chips">${accentHtml}</div>
                    </div>
                    <h5>Columns <span><a data-cols="all">All</a><a data-cols="default">Default</a></span></h5>
                    <div class="pz-chips">${colHtml}</div>
                    <h5>Stamp</h5>
                    <div class="pz-chips">${stampHtml}</div>
                    <input type="text" id="pzStampText" maxlength="24" placeholder="Stamp text, e.g. SETTLED" value="${esc(sv.stamp_text || '')}" style="margin-top:6px;">
                    <h5>Signatures <span class="pz-hint" style="text-transform:none;letter-spacing:0;font-weight:500;">pen = draw or upload</span></h5>
                    <div class="pz-chips">${sigHtml}</div>
                    <div class="pz-pad" id="pzPad" hidden>
                        <div class="pz-pad-hd"><b id="pzPadTitle">Signature</b><span class="pz-hint">Draw with mouse / finger, or upload an image</span></div>
                        <canvas id="pzPadCanvas" width="720" height="200"></canvas>
                        <div class="pz-pad-bar">
                            <label class="pz-btn"><i class="fas fa-upload"></i> Upload<input type="file" id="pzPadFile" accept="image/png,image/jpeg,image/webp" hidden></label>
                            <button type="button" class="pz-btn" id="pzPadClear"><i class="fas fa-eraser"></i> Clear</button>
                            <button type="button" class="pz-btn danger" id="pzPadRemove"><i class="fas fa-trash"></i> Remove saved</button>
                            <span style="flex:1"></span>
                            <button type="button" class="pz-btn" id="pzPadClose">Close</button>
                            <button type="button" class="pz-btn primary" id="pzPadSave"><i class="fas fa-check"></i> Use signature</button>
                        </div>
                    </div>
                    <h5>Company seal</h5>
                    <div class="pz-chips" id="pzSealOpts">
                        <label class="pz-chip"><input type="radio" name="pzSeal" value="" ${!sv.seal ? 'checked' : ''}><span>None</span></label>
                        <label class="pz-chip"><input type="radio" name="pzSeal" value="auto" ${sv.seal === 'auto' ? 'checked' : ''}><span><i class="fas fa-circle-notch"></i> Round seal (company name)</span></label>
                        <label class="pz-chip"><input type="radio" name="pzSeal" value="image" ${sv.seal === 'image' ? 'checked' : ''}><span><i class="fas fa-stamp"></i> My seal image</span></label>
                        <label class="pz-btn" title="PNG with a transparent background looks best"><i class="fas fa-upload"></i> <span id="pzSealUpLabel">${sigImgs.seal ? 'Replace seal image' : 'Upload seal image'}</span><input type="file" id="pzSealFile" accept="image/png,image/jpeg,image/webp" hidden></label>
                        <img id="pzSealThumb" src="${sigImgs.seal || ''}" alt="" style="height:30px;${sigImgs.seal ? '' : 'display:none;'}">
                    </div>
                    <h5>Generated by</h5>
                    <div class="pz-row">
                        <input type="text" id="pzGen" maxlength="60" placeholder="Name on the sheet" value="${esc(sv.gen ?? PS_ME)}">
                        ${tog('pzGenOn', 'Show generator name', on('genOn'))}
                    </div>
                    <h5>Extras</h5>
                    <div class="pz-row">
                        ${tog('pzSummary', 'Summary strip', on('summary'))}
                        ${tog('pzWords', 'Amount in words', on('words'))}
                        ${tog('pzLogo', 'Company logo', on('logo'))}
                        ${tog('pzBank', 'Our bank accounts (pay to)', on('bank', false))}
                    </div>
                    <input type="text" id="pzWm" maxlength="20" placeholder="Watermark (optional), e.g. CONFIDENTIAL" value="${esc(sv.watermark || '')}" style="margin-top:6px;">
                    <div style="display:flex;justify-content:space-between;margin-top:10px;">
                        ${tog('pzRemember', 'Remember these settings', sv.remember !== false)}
                        <a id="pzReset" style="font-size:11px;color:#64748b;cursor:pointer;align-self:center;">Reset to classic</a>
                    </div>
                </section>
                <section class="pz-prev"><h5>Preview</h5><div class="pz-paper" id="pzPaper"></div></section>
            </div>`,
            didOpen: () => {
                const $ = id => document.getElementById(id);
                const all = sel => Array.from(document.querySelectorAll(sel));
                const radio = document.querySelector('input[name=pdMode][value=selected]');
                if (radio && !n) radio.addEventListener('change', () => { if (radio.checked) { Swal.close(); psStartPicking(prefix); } });
                $('pdNoteTemplate').addEventListener('change', e => { const k = e.target.value; $('pdNote').value = k === 'custom' ? '' : (cfg.noteTemplates[k] || ''); if (k === 'custom') $('pdNote').focus(); });

                const paint = () => {
                    const accent = (document.querySelector('input[name=pzAccent]:checked') || {}).value || '#4f46e5';
                    const stampV = (document.querySelector('input[name=pzStamp]:checked') || {}).value || '';
                    $('pzStampText').style.display = stampV === 'custom' ? '' : 'none';
                    const stampT = stampV === 'custom' ? ($('pzStampText').value || 'STAMP').toUpperCase() : (stampV ? stamps[stampV] : '');
                    const stampC = ['paid', 'received', 'verified'].includes(stampV) ? '#059669' : stampV === 'due' ? '#dc2626' : '#4f46e5';
                    const cols = all('.pz-col:checked').map(c => PS_COLS[c.value].label.replace(' (৳)', ''));
                    const sealV = (document.querySelector('input[name=pzSeal]:checked') || {}).value || '';
                    const paper = $('pzPaper');
                    paper.classList.toggle('land', (document.querySelector('input[name=pzOrient]:checked') || {}).value === 'landscape');
                    paper.style.setProperty('--pa', accent);
                    paper.innerHTML = `
                        ${$('pzWm').value.trim() ? `<div class="pz-p-wm">${esc($('pzWm').value.toUpperCase())}</div>` : ''}
                        <div class="pz-p-hd"><span>${$('pzLogo').checked ? '▣ LOGO' : 'Company'}</span><span>Company name</span></div>
                        <div class="pz-p-bar">${esc($('pzTitle').value.trim() || 'Party Statement of Account')}</div>
                        ${$('pzSummary').checked ? '<div class="pz-p-sum"></div>' : ''}
                        <div class="pz-p-th">${cols.map(c => `<span>${esc(c)}</span>`).join('')}</div>
                        ${'<div class="pz-p-tr"></div>'.repeat(9)}
                        ${$('pzWords').checked ? '<div style="margin-top:4px;">In words: …</div>' : ''}
                        ${$('pzBank').checked ? '<div style="margin-top:3px;">Pay to: our bank accounts</div>' : ''}
                        <div class="pz-p-sigs" style="${sigs.length > 1 ? 'justify-content:space-between' : ''}">${all('.pz-sig:checked').map(c => `<span>${psSigImages()[c.value] ? `<img src="${psSigImages()[c.value]}">` : ''}${esc(PS_SIGS[c.value])}</span>`).join('')}</div>
                        ${sealV === 'auto' ? '<div class="pz-p-seal">SEAL</div>' : (sealV === 'image' && psSigImages().seal ? `<img class="pz-p-seal img" src="${psSigImages().seal}">` : '')}
                        ${$('pzGenOn').checked ? `<div class="pz-p-gen">Generated by ${esc($('pzGen').value)}</div>` : ''}
                        ${stampT ? `<div class="pz-p-stamp" style="color:${stampC}">${esc(stampT)}</div>` : ''}`;
                };
                all('[data-all]').forEach(a => a.addEventListener('click', () => { all('.pz-cat').forEach(c => c.checked = a.dataset.all === '1'); }));
                all('[data-cols]').forEach(a => a.addEventListener('click', () => { all('.pz-col').forEach(c => c.checked = a.dataset.cols === 'all' || PS_COLS[c.value].on); paint(); }));
                $('pzReset').addEventListener('click', () => { try { localStorage.removeItem(PS_STUDIO_KEY); } catch (_) {} Swal.close(); setTimeout(() => psStatementStudio(prefix), 250); });
                document.querySelector('.pz').addEventListener('input', paint);
                document.querySelector('.pz').addEventListener('change', paint);
                paint();

                // An uploaded image, shrunk to at most 600×300 so it fits browser storage.
                const readImage = (file, done) => {
                    if (!file || !/^image\//.test(file.type)) return;
                    const fr = new FileReader();
                    fr.onload = () => {
                        const im = new Image();
                        im.onload = () => {
                            const k = Math.min(1, 600 / im.width, 300 / im.height);
                            const c = document.createElement('canvas');
                            c.width = Math.round(im.width * k); c.height = Math.round(im.height * k);
                            c.getContext('2d').drawImage(im, 0, 0, c.width, c.height);
                            done(c.toDataURL('image/png'), im, c);
                        };
                        im.src = fr.result;
                    };
                    fr.readAsDataURL(file);
                };
                const saved = (slot, url) => {
                    if (!psSigStore(slot, url)) { Swal.showValidationMessage('Browser storage is full or blocked — could not keep the image.'); return false; }
                    return true;
                };
                const refreshPen = slot => {
                    const b = document.querySelector(`[data-sigpen="${slot}"]`);
                    if (!b) return;
                    const has = !!psSigImages()[slot];
                    b.classList.toggle('has', has);
                    b.title = has ? 'Change signature' : 'Add signature';
                    b.innerHTML = `<i class="fas fa-${has ? 'signature' : 'pen-nib'}"></i>`;
                };

                // Seal: upload switches the choice to "My seal image".
                $('pzSealFile').addEventListener('change', e => readImage(e.target.files[0], url => {
                    if (!saved('seal', url)) return;
                    $('pzSealThumb').src = url; $('pzSealThumb').style.display = '';
                    $('pzSealUpLabel').textContent = 'Replace seal image';
                    document.querySelector('input[name=pzSeal][value=image]').checked = true;
                    paint();
                }));

                // Signature pad: one canvas, opened for whichever slot's pen was pressed.
                const pad = $('pzPad'), cv = $('pzPadCanvas'), ctx = cv.getContext('2d');
                let slot = null, drawing = false, dirty = false, last = null;
                const resetPad = () => { ctx.clearRect(0, 0, cv.width, cv.height); dirty = false; };
                const pos = e => { const r = cv.getBoundingClientRect(); return [(e.clientX - r.left) * cv.width / r.width, (e.clientY - r.top) * cv.height / r.height]; };
                cv.addEventListener('pointerdown', e => { drawing = true; last = pos(e); cv.setPointerCapture(e.pointerId); });
                cv.addEventListener('pointermove', e => {
                    if (!drawing) return;
                    const p = pos(e);
                    ctx.strokeStyle = '#0b2a6f'; ctx.lineWidth = 3.2; ctx.lineCap = ctx.lineJoin = 'round';
                    ctx.beginPath(); ctx.moveTo(last[0], last[1]); ctx.lineTo(p[0], p[1]); ctx.stroke();
                    last = p; dirty = true;
                });
                ['pointerup', 'pointercancel', 'pointerleave'].forEach(ev => cv.addEventListener(ev, () => { drawing = false; }));
                all('[data-sigpen]').forEach(b => b.addEventListener('click', () => {
                    slot = b.dataset.sigpen;
                    $('pzPadTitle').textContent = PS_SIGS[slot] + ' — signature';
                    resetPad();
                    const existing = psSigImages()[slot];
                    if (existing) { const im = new Image(); im.onload = () => { const k = Math.min(cv.width / im.width, cv.height / im.height); ctx.drawImage(im, (cv.width - im.width * k) / 2, (cv.height - im.height * k) / 2, im.width * k, im.height * k); }; im.src = existing; }
                    pad.hidden = false;
                    const tick = document.querySelector(`.pz-sig[value="${slot}"]`);
                    if (tick) tick.checked = true;
                    pad.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }));
                $('pzPadFile').addEventListener('change', e => readImage(e.target.files[0], (url, im) => {
                    resetPad();
                    const k = Math.min(cv.width / im.width, cv.height / im.height);
                    ctx.drawImage(im, (cv.width - im.width * k) / 2, (cv.height - im.height * k) / 2, im.width * k, im.height * k);
                    dirty = true; e.target.value = '';
                }));
                $('pzPadClear').addEventListener('click', resetPad);
                $('pzPadClose').addEventListener('click', () => { pad.hidden = true; });
                $('pzPadRemove').addEventListener('click', () => { if (slot) { saved(slot, null); refreshPen(slot); } resetPad(); pad.hidden = true; paint(); });
                $('pzPadSave').addEventListener('click', () => {
                    if (!slot) return;
                    if (!dirty) { Swal.showValidationMessage('Draw or upload a signature first.'); return; }
                    // Trim the empty margins so the signature sits tight above its line.
                    const d = ctx.getImageData(0, 0, cv.width, cv.height).data;
                    let x0 = cv.width, y0 = cv.height, x1 = 0, y1 = 0;
                    for (let y = 0; y < cv.height; y++) for (let x = 0; x < cv.width; x++) if (d[(y * cv.width + x) * 4 + 3] > 10) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
                    if (x1 <= x0) { Swal.showValidationMessage('Draw or upload a signature first.'); return; }
                    const out = document.createElement('canvas');
                    out.width = x1 - x0 + 8; out.height = y1 - y0 + 8;
                    out.getContext('2d').drawImage(cv, x0 - 4, y0 - 4, out.width, out.height, 0, 0, out.width, out.height);
                    if (!saved(slot, out.toDataURL('image/png'))) return;
                    Swal.resetValidationMessage();
                    refreshPen(slot); pad.hidden = true; paint();
                });
            },
            preConfirm: () => {
                const $ = id => document.getElementById(id);
                const all = sel => Array.from(document.querySelectorAll(sel));
                const mode = (document.querySelector('input[name=pdMode]:checked') || {}).value;
                let from = '', to = '', ids = [];
                if (mode === 'range') {
                    from = $('pdFrom').value; to = $('pdTo').value;
                    if (!from && !to) { Swal.showValidationMessage('Pick a start or an end date.'); return false; }
                    if (from && to && from > to) { const t = from; from = to; to = t; }
                } else if (mode === 'selected') ids = Array.from(picks);
                const cats = all('.pz-cat');
                const catsOn = cats.filter(c => c.checked);
                if (cats.length && !catsOn.length) { Swal.showValidationMessage('Tick at least one category.'); return false; }
                const cols = all('.pz-col:checked').map(c => c.value);
                if (!cols.length) { Swal.showValidationMessage('Keep at least one column.'); return false; }
                const st = {
                    title: $('pzTitle').value.trim(), cols, sigs: all('.pz-sig:checked').map(c => c.value),
                    stamp: (document.querySelector('input[name=pzStamp]:checked') || {}).value || '', stamp_text: $('pzStampText').value.trim(),
                    gen: $('pzGen').value.trim(), genOn: $('pzGenOn').checked,
                    summary: $('pzSummary').checked, words: $('pzWords').checked, logo: $('pzLogo').checked, bank: $('pzBank').checked,
                    watermark: $('pzWm').value.trim(), orient: (document.querySelector('input[name=pzOrient]:checked') || {}).value,
                    accent: (document.querySelector('input[name=pzAccent]:checked') || {}).value, remember: $('pzRemember').checked,
                };
                try { if (st.remember) localStorage.setItem(PS_STUDIO_KEY, JSON.stringify(st)); else localStorage.removeItem(PS_STUDIO_KEY); } catch (_) {}
                const p = new URLSearchParams();
                if (catsOn.length < cats.length) p.set('cats', catsOn.map(c => c.value).join(','));
                if (st.title) p.set('title', st.title);
                p.set('cols', cols.join(','));
                p.set('sigs', st.sigs.join(',') || 'none');
                // Which slots carry a drawn/uploaded image; the sheet reads them from this browser.
                const imgs = psSigImages();
                const withImg = st.sigs.filter(k => imgs[k]);
                if (withImg.length) p.set('sigimg', withImg.join(','));
                st.seal = (document.querySelector('input[name=pzSeal]:checked') || {}).value || '';
                if (st.seal === 'image' && !imgs.seal) { Swal.showValidationMessage('Upload the seal image first, or pick the round seal.'); return false; }
                if (st.seal) p.set('seal', st.seal);
                try { if (st.remember) localStorage.setItem(PS_STUDIO_KEY, JSON.stringify(st)); } catch (_) {}
                if (st.stamp) { p.set('stamp', st.stamp); if (st.stamp === 'custom') p.set('stamp_text', st.stamp_text); }
                p.set('gen', st.genOn ? (st.gen || PS_ME) : '');
                ['summary', 'words', 'logo', 'bank'].forEach(k => p.set(k, st[k] ? '1' : '0'));
                if (st.watermark) p.set('watermark', st.watermark);
                if (st.orient === 'landscape') p.set('orient', 'landscape');
                if (st.accent && st.accent !== '#4f46e5') p.set('accent', st.accent);
                return { mode, from, to, ids, note: $('pdNote').value.trim(), extra: '&' + p.toString() };
            },
        }).then(res => {
            if (!res.isConfirmed) return;
            const v = res.value;
            if (v.mode === 'selected' && !v.ids.length) { psStartPicking(prefix); return; }
            const q = v.mode === 'shown'
                ? ((cfg.searching && cfg.searching()) ? { from: '', to: '', ids: psShownRows(prefix).map(([id]) => id) } : Object.assign({ from: '', to: '', ids: [] }, period))
                : { from: v.from, to: v.to, ids: v.ids };
            q.note = v.note; q.extra = v.extra;
            const url = cfg.url(q);
            if (url) window.open(url, '_blank');
        });
    }
    psPrintSetup('st', {
        studio:    psStatementStudio,
        host:      document.querySelector('#statementArea table'),
        dates:     true,
        noun:      'transactions',
        title:     'Print statement',
        note:      true,
        noteTemplates: PARTY_NOTE_TEMPLATES,
        noteTemplateLabels: PARTY_NOTE_TEMPLATE_LABELS,
        ready:     () => !!(selectedParty && LAST_STATEMENT),
        readyHint: 'Pick a party and load a statement first.',
        period:    () => LAST_STATEMENT ? { from: LAST_STATEMENT.date_from || '', to: LAST_STATEMENT.date_to || '' } : resolveDates(),
        searching: () => STMT_Q !== '',
        rows:      () => LAST_STATEMENT ? LAST_STATEMENT.transactions.map(t => [Number(t.id), String(t.payment_date || '').slice(0, 10)]) : [],
        url: ({ from, to, ids, note, extra }) => {
            if (!selectedParty) return null;
            const type = resolveType();
    let url = appendQuery(PDF_URL, `party_id=${selectedParty.id}`);
            if (ids && ids.length) url += `&entries=${ids.join(',')}`;
            else { if (from) url += `&from=${from}`; if (to) url += `&to=${to}`; }
            if (type && type !== 'all') url += `&type=${type}`;
            if (note) url += `&note=${encodeURIComponent(note)}`;
            if (extra) url += extra;
            return url;
        },
    });
})();
