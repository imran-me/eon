@extends('layout.app')

@section('meta-information')
    <title>Recurring Expenses</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    .re-wrap{max-width:1500px;margin:auto}.re-card{background:#fff;border:1px solid #dbe4ef;border-radius:12px;box-shadow:0 4px 18px rgba(15,23,42,.05);padding:18px;margin-bottom:16px}.re-title{font-size:17px;font-weight:800;color:#0f172a}.re-sub{font-size:12px;color:#64748b;margin-top:2px}.re-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}.re-field{grid-column:span 3;min-width:0}.re-field.wide{grid-column:span 6}.re-field label{display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:5px}.re-field input,.re-field select,.re-field textarea{width:100%;height:38px;border:1px solid #cbd5e1;border-radius:7px;padding:7px 10px;font-size:12px;background:#fff;box-sizing:border-box}.re-field textarea{height:38px;resize:vertical}.re-section{margin:16px 0 10px;padding:7px 10px;border-left:3px solid #4f46e5;background:#f5f7ff;color:#3150c8;font-size:10px;font-weight:800;letter-spacing:.09em;text-transform:uppercase}.re-lines{width:100%;border-collapse:collapse}.re-lines th{font-size:10px;text-transform:uppercase;color:#64748b;text-align:left;padding:8px;background:#f8fafc;border-bottom:1px solid #dbe4ef}.re-lines td{padding:7px;border-bottom:1px solid #edf2f7;vertical-align:top}.re-lines input,.re-lines select{width:100%;height:36px;border:1px solid #cbd5e1;border-radius:7px;padding:6px 8px;font-size:12px;box-sizing:border-box}.re-head{font-size:11px;color:#475569;padding-top:9px}.re-actions{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:14px}.re-btn{border:0;border-radius:7px;padding:9px 14px;font-size:12px;font-weight:700;cursor:pointer}.re-primary{background:#2563eb;color:#fff}.re-soft{background:#eef2ff;color:#3730a3}.re-danger{background:#fff1f2;color:#be123c}.re-filter{display:flex;gap:8px;margin-bottom:12px}.re-filter input{height:36px;min-width:260px;border:1px solid #cbd5e1;border-radius:7px;padding:0 10px}.re-table{width:100%;border-collapse:collapse}.re-table th,.re-table td{padding:11px 9px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px;vertical-align:top}.re-table th{background:#f8fafc;color:#64748b;font-size:10px;text-transform:uppercase}.re-pill{display:inline-block;border-radius:999px;padding:3px 8px;font-size:10px;font-weight:700}.re-on{background:#dcfce7;color:#166534}.re-off{background:#f1f5f9;color:#64748b}.re-total{text-align:right;font-weight:800;color:#0f172a}.re-modal{display:none;position:fixed;inset:0;z-index:1050;background:rgba(15,23,42,.55);padding:22px;align-items:flex-start;justify-content:center}.re-modal.is-open{display:flex}.re-modal-panel{width:min(1320px,calc(100vw - 32px));max-height:calc(100vh - 44px);overflow:auto;background:#fff;border-radius:14px;box-shadow:0 24px 70px rgba(15,23,42,.28)}.re-modal .re-card{border:0;box-shadow:none;margin:0;padding:22px}.re-modal-head{position:sticky;top:-22px;z-index:6;margin:-22px -22px 16px;padding:18px 22px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:flex-start}.re-modal-close{width:34px;height:34px;border:0;border-radius:8px;background:#eef2ff;color:#334155;font-size:20px;cursor:pointer}.re-list-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}.select2-container{width:100%!important}.select2-container .select2-selection--single{height:38px!important;border-color:#cbd5e1!important;border-radius:7px!important}.select2-selection__rendered{line-height:36px!important;font-size:12px}.select2-selection__arrow{height:36px!important}.select2-container--open{z-index:1100}@media(max-width:900px){.re-field,.re-field.wide{grid-column:span 6}.re-lines-wrap{overflow:auto}.re-lines{min-width:920px}}@media(max-width:600px){.re-modal{padding:8px}.re-modal-panel{width:100%;max-height:calc(100vh - 16px)}.re-field,.re-field.wide{grid-column:span 12}.re-filter{display:block}.re-filter input{min-width:0;width:100%;margin-bottom:8px}}

    /* Recurring-entry modal: an accounting workspace, not a stretched card. */
    .re-modal{background:rgba(15,23,42,.65);backdrop-filter:blur(3px);padding:16px;align-items:center}
    .re-modal-panel{width:min(1280px,calc(100vw - 32px));max-height:calc(100vh - 32px);background:#f8fafc;border:1px solid rgba(255,255,255,.28);border-radius:16px;box-shadow:0 30px 90px rgba(15,23,42,.42)}
    .re-modal .re-card{padding:22px;background:#f8fafc}
    .re-modal-head{top:-22px;margin:-22px -22px 16px;padding:17px 22px;background:linear-gradient(135deg,#4338ca,#2563eb);border:0;align-items:center;color:#fff}
    .re-modal-title-wrap{display:flex;align-items:center;gap:12px}.re-modal-icon{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;background:rgba(255,255,255,.16);font-size:16px}
    .re-modal-head .re-title{color:#fff}.re-modal-head .re-sub{color:#dbeafe}.re-modal-close{width:35px;height:35px;border-radius:9px;background:rgba(255,255,255,.16);color:#fff}
    .re-auto-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;margin-bottom:14px;border:1px solid #c7d2fe;border-radius:10px;overflow:hidden;background:#c7d2fe}.re-auto-item{display:flex;align-items:center;gap:9px;padding:10px 12px;background:#eef2ff}.re-auto-item i{color:#4f46e5}.re-auto-item strong{display:block;font-size:11px;color:#1e293b}.re-auto-item span{display:block;margin-top:1px;font-size:10px;color:#64748b}
    .re-block{margin-top:14px;padding:16px;border:1px solid #e2e8f0;border-radius:11px;background:#fff}.re-section{margin:0 0 13px;padding:0;border:0;background:transparent}.re-section:after{content:"";display:inline-block;width:48px;margin:0 0 3px 9px;border-top:1px dashed #a5b4fc}
    .re-grid{gap:14px}.re-field label{margin-bottom:6px;color:#334155}.re-field input,.re-field select,.re-field textarea{height:40px;border-radius:8px;padding:8px 11px;transition:border-color .15s,box-shadow .15s}.re-field input:focus,.re-field select:focus,.re-field textarea:focus{outline:0;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}.re-field textarea{height:40px}
    .re-lines{border-collapse:separate;border-spacing:0 8px}.re-lines th{padding:0 9px 3px;background:transparent;border:0;font-size:9px;letter-spacing:.04em}.re-lines td{padding:9px;background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;vertical-align:middle}.re-lines td:first-child{border-left:3px solid #818cf8;border-radius:9px 0 0 9px}.re-lines td:last-child{border-right:1px solid #e2e8f0;border-radius:0 9px 9px 0}.re-lines input,.re-lines select{height:38px;background:#fff}.re-head{padding-top:0;font-weight:700;line-height:1.35}
    .re-actions{position:sticky;bottom:-22px;z-index:5;margin:16px -22px -22px;padding:13px 22px;border-top:1px solid #e2e8f0;background:#fff;box-shadow:0 -8px 20px rgba(15,23,42,.04)}.re-btn{border-radius:8px;font-weight:750;transition:transform .12s,box-shadow .12s}.re-btn:hover{transform:translateY(-1px)}.re-primary{box-shadow:0 5px 12px rgba(37,99,235,.22)}
    .re-actions{justify-content:flex-end}.re-line-add-row{display:flex;justify-content:flex-start;margin-top:8px}.re-add-line{background:#4f46e5;color:#fff;box-shadow:0 4px 10px rgba(79,70,229,.18)}.re-add-line:hover{background:#4338ca;color:#fff}
    .select2-container .select2-selection--single{height:40px!important;border-radius:8px!important}.select2-selection__rendered{line-height:38px!important}.select2-selection__arrow{height:38px!important}
    @media(max-width:900px){.re-auto-strip{grid-template-columns:1fr}.re-lines{min-width:980px}}
    @media(max-width:600px){.re-modal{padding:6px}.re-modal-panel{max-height:calc(100vh - 12px)}.re-actions{align-items:flex-end}.re-actions>div{text-align:right}}
</style>
@endsection

@section('main-content')
@include('layout.expense-tabs')
<div class="re-wrap">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @if(auth()->user()->can('create expense') || auth()->user()->can('edit expense'))
    <div id="recurringModal" class="re-modal" role="dialog" aria-modal="true" aria-labelledby="formTitle">
    <div id="recurringModalPanel" class="re-modal-panel">
    <form id="recurringForm" class="re-card" method="POST" action="{{ route('role.expenses.recurring.store', ['role' => request()->route('role')]) }}">
        @csrf
        <input type="hidden" name="_method" id="formMethod" value="POST">
        <div class="re-modal-head">
            <div class="re-modal-title-wrap"><div class="re-modal-icon"><i class="fas fa-calendar-check"></i></div><div><div class="re-title" id="formTitle">New recurring expense</div><div class="re-sub" id="formSubtitle">Set it once. The system prepares the monthly draft for accountant approval.</div></div></div>
            <button type="button" class="re-modal-close" id="closeRecurringModal" aria-label="Close" onclick="document.getElementById('recurringModal').classList.remove('is-open');document.body.style.overflow=''">&times;</button>
        </div>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="re-auto-strip">
            <div class="re-auto-item"><i class="fas fa-calendar-day"></i><div><strong>Runs every month</strong><span>Automatically on day 1</span></div></div>
            <div class="re-auto-item"><i class="fas fa-bolt"></i><div><strong>Current month included</strong><span>Draft created immediately</span></div></div>
            <div class="re-auto-item"><i class="fas fa-shield-alt"></i><div><strong>Approval protected</strong><span>No ledger posting before approval</span></div></div>
        </div>
        <div class="re-block">
        <div class="re-section">Ownership & payment source</div>
        <div class="re-grid">
            <div class="re-field"><label>Company *</label><select name="company_id" id="companySelect" class="re-select" required><option value="">Select company</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div>
            <div class="re-field"><label>Department *</label><select name="expense_department_id" id="departmentSelect" class="re-select" required><option value="">Select company first</option></select></div>
            <div class="re-field"><label>Status</label><select name="is_active"><option value="1">Active</option><option value="0">Paused</option></select></div>
            <div class="re-field"><label>Payment method *</label><select name="payment_mode" id="paymentMode" required><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="mobile_banking">Mobile banking</option><option value="digital_wallet">Digital wallet</option><option value="other">Other</option></select></div>
            <div class="re-field wide"><label id="moneyAccountLabel">Bank / money account</label><select name="bank_id" id="bankSelect" class="re-select"><option value="">Cash in hand — no account selection needed</option></select><div id="moneyAccountHint" class="re-sub">Cash expenses use the company's cash-in-hand account.</div></div>
            <div class="re-field wide"><label>Notes</label><textarea name="description"></textarea></div>
        </div>
        </div>

        <div class="re-block">
        <div class="re-section">Monthly expense breakdown</div>
        <div class="re-lines-wrap"><table class="re-lines"><thead><tr><th style="width:23%">Category</th><th style="width:23%">Sub-category</th><th style="width:22%">Expense head</th><th>Description</th><th style="width:120px">Amount</th><th style="width:42px"></th></tr></thead><tbody id="lineRows"></tbody></table></div>
        <div class="re-line-add-row"><button type="button" class="re-btn re-add-line" id="addLine"><i class="fas fa-plus" style="margin-right:5px"></i>Add expense line</button></div>
        </div>
        <div class="re-actions"><div><span style="font-size:11px;color:#64748b;margin-right:14px">Monthly total <strong id="monthlyTotal" style="font-size:15px;color:#0f172a">৳0.00</strong></span><button class="re-btn re-primary" id="saveRecurringButton" type="submit"><i class="fas fa-check" style="margin-right:5px"></i><span>Save recurring expense</span></button></div></div>
    </form>
    </div>
    </div>
    @endif

    <div class="re-card">
        <div class="re-list-head"><div><div class="re-title">Recurring expense schedules</div><div class="re-sub">Monthly fixed costs create a pending draft automatically.</div></div>@can('create expense')<button type="button" class="re-btn re-primary" id="openRecurringModal" onclick="document.getElementById('recurringModal').classList.add('is-open');document.body.style.overflow='hidden'">+ New recurring expense</button>@endcan</div>
        <form class="re-filter"><input name="search" value="{{ request('search') }}" placeholder="Search schedule"><button class="re-btn re-soft">Search</button></form>
        <div style="overflow:auto"><table class="re-table"><thead><tr><th>Schedule</th><th>Company / Department</th><th>Monthly breakdown</th><th>Timing</th><th>Status</th><th style="width:130px">Actions</th></tr></thead><tbody>
        @forelse($templates as $template)
            @php
                $editPayload = [
                    'id' => $template->id, 'name' => $template->name, 'description' => $template->description,
                    'company_id' => $template->company_id, 'expense_department_id' => $template->expense_department_id,
                    'generation_day' => $template->generation_day, 'start_month' => $template->start_month?->format('Y-m'),
                    'end_month' => $template->end_month?->format('Y-m'), 'payment_mode' => $template->payment_mode,
                    'bank_id' => $template->bank_id, 'is_active' => $template->is_active ? 1 : 0,
                    'lines' => $template->lines->map(fn($line) => [
                        'expense_category_id' => $line->expense_category_id, 'expense_sub_category_id' => $line->expense_sub_category_id,
                        'account_id' => $line->account_id, 'description' => $line->description, 'amount' => $line->amount,
                    ])->values(),
                ];
            @endphp
            <tr><td><strong>{{ $template->name }}</strong><div style="color:#64748b;margin-top:3px">{{ $template->payment_mode === 'bank_transfer' ? ($template->bank?->name ?? 'Bank chosen at approval') : str($template->payment_mode)->replace('_', ' ')->title() }}</div></td>
                <td>{{ $template->company?->short_name ?: $template->company?->name }}<div style="color:#64748b;margin-top:3px">{{ $template->expenseDepartment?->name }}</div></td>
                <td>@foreach($template->lines->take(2) as $line)<div>{{ $line->description }} — ৳{{ number_format($line->amount, 2) }}</div>@endforeach @if($template->lines->count()>2)<div style="color:#64748b">+{{ $template->lines->count()-2 }} more</div>@endif<div class="re-total">৳{{ number_format($template->lines->sum('amount'),2) }}</div></td>
                <td>Every month · day 1<div style="color:#64748b;margin-top:3px">Current month is created immediately</div><div style="color:#64748b">{{ $template->generations->count() }} draft(s) generated</div></td>
                <td><span class="re-pill {{ $template->is_active ? 're-on' : 're-off' }}">{{ $template->is_active ? 'Active' : 'Paused' }}</span></td>
                <td><div style="display:flex;gap:6px">@can('edit expense')<button type="button" class="re-btn re-soft editSchedule" data-template='@json($editPayload)' onclick="window.editRecurringExpense?.(this)">Edit</button>@endcan @can('delete expense')<form method="POST" action="{{ route('role.expenses.recurring.destroy',['role'=>request()->route('role'),'recurringExpense'=>$template]) }}" onsubmit="return confirm('Stop this recurring expense? Existing generated expenses will remain.')">@csrf @method('DELETE')<button class="re-btn re-danger">Stop</button></form>@endcan</div></td></tr>
        @empty<tr><td colspan="6" style="text-align:center;color:#64748b;padding:28px">No recurring expense schedule yet.</td></tr>@endforelse
        </tbody></table></div>
        <div style="margin-top:12px">{{ $templates->links() }}</div>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
function initRecurringExpenses() {
    const pageData = {{ Illuminate\Support\Js::from($pageData) }};
    const classification = pageData.classification;
    const departments = pageData.departments;
    const accounts = pageData.accounts;
    const moneyAccounts = pageData.moneyAccounts;
    const form = document.getElementById('recurringForm'); if (!form || form.dataset.recurringReady === '1') return;
    form.dataset.recurringReady = '1';
    const modal = document.getElementById('recurringModal');
    const tbody = document.getElementById('lineRows');
    const storeUrl = pageData.storeUrl;
    const updateBase = pageData.updateBase;
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
    function selectedCompany(){return document.getElementById('companySelect').value}
    function belongsToCompany(item){const company=selectedCompany();return !!company&&(!item.company_id||String(item.company_id)===String(company))}
    function categoryOptions(selected=''){const company=selectedCompany();const rows=company?classification.categories.filter(belongsToCompany):[];return `<option value="">${company?'Select category':'Select company first'}</option>`+rows.map(c=>`<option value="${c.id}" data-account="${c.account_id||''}" ${String(c.id)===String(selected)?'selected':''}>${esc(c.name)}</option>`).join('')}
    function accountName(id){return accounts.find(a=>String(a.id)===String(id))?.label||'Select a category'}
    function addLine(data={}){const i=tbody.children.length;const tr=document.createElement('tr');tr.innerHTML=`<td><select class="line-category" name="lines[${i}][expense_category_id]" required>${categoryOptions(data.expense_category_id)}</select></td><td><select class="line-sub" name="lines[${i}][expense_sub_category_id]"></select></td><td><div class="re-head"></div><input class="line-account" type="hidden" name="lines[${i}][account_id]"></td><td><input name="lines[${i}][description]" value="${esc(data.description||'')}" required></td><td><input class="line-amount" type="number" step="0.01" min="0.01" name="lines[${i}][amount]" value="${esc(data.amount||'')}" required></td><td><button type="button" class="re-btn re-danger removeLine" title="Remove line" style="padding:8px 11px">×</button></td>`;tbody.appendChild(tr);refreshLine(tr,data);$(tr).find('.line-category,.line-sub').select2({dropdownParent:$('#recurringModalPanel'),width:'100%'});}
    function refreshLine(tr,data={}){const cat=tr.querySelector('.line-category'),sub=tr.querySelector('.line-sub'),account=tr.querySelector('.line-account'),head=tr.querySelector('.re-head');function syncSubs(selected=''){const category=classification.categories.find(c=>String(c.id)===cat.value&&belongsToCompany(c));const subs=classification.subCategories.filter(s=>String(s.category_id)===cat.value&&belongsToCompany(s));sub.innerHTML='<option value="">No sub-category</option>'+subs.map(s=>`<option value="${s.id}" data-account="${s.account_id||''}" ${String(s.id)===String(selected)?'selected':''}>${esc(s.name)}</option>`).join('');const chosen=sub.selectedOptions[0];const accountId=chosen?.dataset.account||category?.account_id||(category?data.account_id:'')||'';account.value=accountId;head.textContent=accountName(accountId);$(sub).trigger('change.select2')}cat.addEventListener('change',()=>syncSubs());sub.addEventListener('change',()=>syncSubs(sub.value));syncSubs(data.expense_sub_category_id||'');}
    function reindex(){[...tbody.children].forEach((tr,i)=>tr.querySelectorAll('[name]').forEach(el=>el.name=el.name.replace(/lines\[\d+\]/,`lines[${i}]`)))}
    function total(){document.getElementById('monthlyTotal').textContent='৳'+[...document.querySelectorAll('.line-amount')].reduce((s,e)=>s+(parseFloat(e.value)||0),0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}
    function filterMoneyAccounts(keepValue=''){const company=document.getElementById('companySelect').value,mode=document.getElementById('paymentMode').value,bank=document.getElementById('bankSelect');const wanted={bank_transfer:'bank',mobile_banking:'mobile_banking',digital_wallet:'digital_wallet'}[mode]||null;const needs=!!wanted;const rows=moneyAccounts.filter(a=>(!company||String(a.company_id)===String(company))&&(!wanted||a.type===wanted));const labels={bank_transfer:'bank account',mobile_banking:'mobile banking account',digital_wallet:'digital wallet'};bank.innerHTML=`<option value="">${needs?'Select '+labels[mode]:(mode==='cash'?'Cash in hand — no account selection needed':'Select a money account (optional)')}</option>`+rows.map(a=>`<option value="${a.id}">${esc(a.name)}</option>`).join('');bank.disabled=mode==='cash';bank.required=needs;if(keepValue&&rows.some(a=>String(a.id)===String(keepValue)))bank.value=String(keepValue);document.getElementById('moneyAccountLabel').textContent=needs?'Pay from '+labels[mode]+' *':'Bank / money account';document.getElementById('moneyAccountHint').textContent=mode==='cash'?"Cash expenses use the company's cash-in-hand account.":(needs?'Only matching '+labels[mode]+'s for the selected company are shown.':'Any company money account may be selected, or choose it during approval.');$(bank).trigger('change.select2')}
    function filterDepartments(keepValue=''){const company=document.getElementById('companySelect').value,department=document.getElementById('departmentSelect');const rows=company?departments.filter(d=>String(d.company_id)===String(company)):[];department.innerHTML=`<option value="">${company?'Select department':'Select company first'}</option>`+rows.map(d=>`<option value="${d.id}">${esc(d.name)}</option>`).join('');if(keepValue&&rows.some(d=>String(d.id)===String(keepValue)))department.value=String(keepValue);$(department).trigger('change.select2')}
    function filterOwned(){filterDepartments();filterMoneyAccounts(document.getElementById('bankSelect').value)}
    document.getElementById('companySelect').addEventListener('change',()=>{filterOwned();[...tbody.children].forEach(tr=>{const category=tr.querySelector('.line-category');category.innerHTML=categoryOptions();category.dispatchEvent(new Event('change'))})});document.getElementById('paymentMode').addEventListener('change',()=>filterMoneyAccounts());document.getElementById('addLine').addEventListener('click',()=>addLine());tbody.addEventListener('click',e=>{if(e.target.classList.contains('removeLine')&&tbody.children.length>1){e.target.closest('tr').remove();reindex();total()}});tbody.addEventListener('input',total);
    function openModal(){modal.classList.add('is-open');document.body.style.overflow='hidden';document.getElementById('recurringModalPanel').scrollTop=0}
    function reset(){form.reset();form.action=storeUrl;document.getElementById('formMethod').value='POST';document.getElementById('formTitle').textContent='New recurring expense';document.getElementById('formSubtitle').textContent='Set it once. The system prepares the monthly draft for accountant approval.';document.querySelector('#saveRecurringButton span').textContent='Save recurring expense';tbody.innerHTML='';addLine();$('.re-select').val('').trigger('change');filterDepartments();filterMoneyAccounts();total()}
    function closeModal(){modal.classList.remove('is-open');document.body.style.overflow='';reset()}
    function editRecurringExpense(btn){const d=JSON.parse(btn.dataset.template);form.action=updateBase+'/'+d.id;document.getElementById('formMethod').value='PUT';document.getElementById('formTitle').textContent='Edit recurring expense';document.getElementById('formSubtitle').textContent='Update existing lines or add another monthly expense to this schedule.';document.querySelector('#saveRecurringButton span').textContent='Update recurring expense';['description','payment_mode','is_active'].forEach(k=>{if(form.elements[k])form.elements[k].value=d[k]??''});$('#companySelect').val(String(d.company_id)).trigger('change');filterDepartments(d.expense_department_id);filterMoneyAccounts(d.bank_id||'');tbody.innerHTML='';d.lines.forEach(addLine);total();openModal()}
    window.editRecurringExpense=editRecurringExpense;document.getElementById('openRecurringModal')?.addEventListener('click',()=>{reset();openModal()});document.getElementById('closeRecurringModal').addEventListener('click',closeModal);
    $('.re-select').select2({dropdownParent:$('#recurringModalPanel')});filterDepartments();filterMoneyAccounts();addLine();total();if({{ $errors->any() ? 'true' : 'false' }})openModal();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRecurringExpenses, { once:true });
} else {
    initRecurringExpenses();
}
</script>
@endsection
