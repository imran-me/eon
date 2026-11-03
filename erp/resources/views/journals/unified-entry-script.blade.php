const journalCompanies = @json($companies->map(fn ($company) => ['id' => $company->id, 'name' => $company->name]));
$('#openUnifiedJournal').on('click', function () {
    const root = document.createElement('div');
    root.className = 'uj-form';
    root.innerHTML = `<div class="je-grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr) minmax(0,1.5fr)"><div><label>Company *</label><select id="uj-company"></select></div><div><label>Date *</label><input id="uj-date" type="date"></div><div><label>Reference</label><input id="uj-reference" maxlength="100"></div></div>
        <label>Description</label><textarea id="uj-description" rows="2" maxlength="500"></textarea>
        <div><div class="uj-section"><strong>Journal Lines</strong><span>Enter a debit or credit amount on each line.</span></div><div class="uj-table"><div class="uj-line-grid uj-heading"><span>#</span><span>Account Head</span><span>Party (optional)</span><span>Debit (BDT)</span><span>Credit (BDT)</span><span>Note</span><span></span></div><div id="uj-lines"></div></div><button type="button" id="uj-add" class="uj-add">+ Add Line</button></div>
        <div class="uj-summary" id="uj-totals"><div><span>Total Debit</span><strong id="uj-total-debit">0.00</strong></div><div><span>Total Credit</span><strong id="uj-total-credit">0.00</strong></div><div><span>Difference</span><strong id="uj-difference">0.00</strong></div><div id="uj-status">Enter amounts</div></div><p id="uj-review" class="je-notice" hidden></p>`;
    const find = id => root.querySelector('#uj-' + id);
    const company = find('company');
    company.add(new Option('Choose company...', ''));
    journalCompanies.forEach(c => company.add(new Option(c.name, c.id)));
    company.value = @json($companyId ?: auth()->user()->company_id ?: '');
    find('date').value = @json(now()->timezone('Asia/Dhaka')->format('Y-m-d'));
    const partyOptions = JSON.parse(document.getElementById('partiesData').textContent);
    const fillParties = select => {
        select.add(new Option('No party', ''));
        Object.entries(partyOptions).forEach(([type, parties]) => parties.forEach(p => select.add(new Option(`${p.name} (${type})`, `${type}:${p.id}`))));
    };
    let rows = [], reviewed = false;
    const enhanceSelect = select => {
        if (!root.isConnected) return;
        if ($(select).hasClass('select2-hidden-accessible')) return;
        $(select).select2({width:'100%', dropdownParent:$(Swal.getPopup()), minimumResultsForSearch:0,
            placeholder:select === company ? 'Choose company...' : 'Search code or name'});
        $(select).on('change.unifiedJournal', event => {
            if (!event.originalEvent) select.dispatchEvent(new Event('change', {bubbles:true}));
        });
    };
    const fillAccounts = select => {
        const selected = select.value;
        select.replaceChildren(new Option('Select account...', ''));
        select.disabled = !company.value;
        accountsData.filter(a => !a.company_id || String(a.company_id) === company.value).forEach(a => select.add(new Option(a.text, a.id)));
        select.value = selected;
        $(select).trigger('change.select2');
    };
    const addRow = (item = {}) => {
        const row = document.createElement('div'); row.className = 'uj-line-grid uj-row';
        const account = document.createElement('select'); fillAccounts(account); account.value = item.account_id || '';
        const party = document.createElement('select'); fillParties(party); party.value = item.party || ''; party.setAttribute('aria-label', 'Party');
        account.setAttribute('aria-label', 'Account head');
        const number = document.createElement('span'); number.className = 'uj-row-number';
        const debit = document.createElement('input'); debit.type = 'number'; debit.min = 0; debit.step = '.01'; debit.value = item.debit || 0; debit.setAttribute('aria-label', 'Debit');
        const credit = document.createElement('input'); credit.type = 'number'; credit.min = 0; credit.step = '.01'; credit.value = item.credit || 0; credit.setAttribute('aria-label', 'Credit');
        const note = document.createElement('input'); note.placeholder = 'Line note'; note.maxLength = 500; note.value = item.note || '';
        const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = '×'; remove.className = 'uj-remove'; remove.title = 'Remove line'; remove.setAttribute('aria-label', 'Remove line');
        const field = {row, account, party, debit, credit, note, number, remove}; rows.push(field);
        remove.onclick = () => { if (rows.length <= 2) return; [account,party].forEach(select => { if ($(select).hasClass('select2-hidden-accessible')) $(select).select2('destroy'); }); rows = rows.filter(r => r !== field); row.remove(); update(); };
        row.append(number, account, party, debit, credit, note, remove); find('lines').append(row);
        debit.addEventListener('input', () => { if (Number(debit.value) > 0) credit.value = 0; });
        credit.addEventListener('input', () => { if (Number(credit.value) > 0) debit.value = 0; });
        enhanceSelect(account); enhanceSelect(party);
    };
    const partyFields = value => { const [party_type, party_id] = value.split(':'); return value ? {party_type, party_id:Number(party_id)} : {}; };
    const getItems = () => rows.map(r => ({account_id:r.account.value, debit:Number(r.debit.value), credit:Number(r.credit.value), note:r.note.value, ...partyFields(r.party.value)}));
    function update() {
        reviewed = false; find('review').hidden = true;
        const items = getItems(); const debit = items.reduce((s,i) => s+i.debit,0), credit = items.reduce((s,i) => s+i.credit,0);
        const format = value => value.toLocaleString('en-BD', {minimumFractionDigits:2, maximumFractionDigits:2});
        find('total-debit').textContent = format(debit); find('total-credit').textContent = format(credit); find('difference').textContent = format(debit-credit);
        const balanced = Math.abs(debit-credit) < .01 && debit > 0;
        find('status').textContent = balanced ? '✓ Balanced' : debit === 0 && credit === 0 ? 'Enter amounts' : 'Needs balancing';
        find('status').className = balanced ? 'is-balanced' : 'is-pending';
        rows.forEach((r, index) => { r.number.textContent = index+1; r.remove.disabled = rows.length <= 2; });
        if (Swal.getConfirmButton()) Swal.getConfirmButton().textContent = 'Review Journal';
    }
    addRow(); addRow();
    company.onchange = () => { rows.map(r => r.account).forEach(fillAccounts); update(); };
    find('add').onclick = () => { addRow(); update(); };
    root.addEventListener('input', update); root.addEventListener('change', update);
    Swal.fire({title:'New Journal', html:root, width:1280, customClass:{popup:'journal-editor-popup uj-popup'}, showCloseButton:true, showCancelButton:true,
        confirmButtonText:'Review Journal', showLoaderOnConfirm:true, allowOutsideClick:() => !Swal.isLoading(),
        didOpen:() => { [company,...rows.flatMap(r => [r.account,r.party])].forEach(enhanceSelect); update(); },
        willClose:() => { $(root).find('select.select2-hidden-accessible').each(function () { $(this).off('.unifiedJournal').select2('destroy'); }); },
        preConfirm:async () => {
            const items = getItems();
            if (!company.value || !find('date').value || items.some(i => !i.account_id || !Number.isFinite(i.debit) || !Number.isFinite(i.credit) || i.debit < 0 || i.credit < 0 || (i.debit > 0) === (i.credit > 0))) {
                Swal.showValidationMessage('Choose company, date and accounts. Each line must have either debit or credit.'); return false;
            }
            const debit = items.reduce((s,i) => s+i.debit,0), credit = items.reduce((s,i) => s+i.credit,0);
            if (Math.abs(debit-credit) >= .01) { Swal.showValidationMessage('Debit and credit totals must match.'); return false; }
            if (!reviewed) {
                find('review').textContent = `${items.length} lines for ${company.selectedOptions[0].text}, dated ${find('date').value}. Debit and credit both BDT ${debit.toFixed(2)}. Review the accounts above, then Post Journal.`;
                find('review').hidden = false; reviewed = true; Swal.getConfirmButton().textContent = 'Post Journal'; return false;
            }
            try { return await $.ajax({url:storeUrl, method:'POST', data:{_token:$('meta[name="csrf-token"]').attr('content'),company_id:company.value,date:find('date').value,reference:find('reference').value,description:find('description').value,source:'manual',items}}); }
            catch (xhr) { Swal.showValidationMessage(xhr.responseJSON?.message || 'Journal could not be posted.'); return false; }
        }
    }).then(result => { if (result.isConfirmed) Swal.fire('Journal posted', 'Your balanced journal has been recorded.', 'success').then(() => location.reload()); });
});
