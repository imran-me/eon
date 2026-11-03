<script>
(() => {
    const initialiseExpenseEntry = () => {
        const form = document.getElementById('wa-exp-form');
        if (! form || form.dataset.ready) return;
        form.dataset.ready = '1';
        const rows = form.querySelector('#wa-exp-rows'), project = form.querySelector('#wa-bulk-project');
        const amount = value => { const parsed = Number(String(value || '').replace(/[^0-9.-]/g, '')); return Number.isFinite(parsed) ? parsed : 0; };
        const money = value => '৳ ' + value.toLocaleString('en-BD', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const value = (row, key) => row.querySelector(`[name$="[${key}]"]`)?.value?.trim() || '';
        const active = row => ['head_id', 'supplier_key', 'description', 'person_id', 'bank_id', 'unit_price', 'amount'].some(key => value(row, key) !== '');
        const payerSelected = row => value(row, 'source') === 'bank' ? Boolean(value(row, 'payment_method') && value(row, 'bank_id')) : value(row, 'person_id') !== '';
        const chargeReady = row => value(row, 'source') !== 'bank' || amount(value(row, 'bank_charge_amount')) <= 0 || value(row, 'bank_charge_account_id') !== '';
        const complete = row => Boolean(value(row, 'date') && value(row, 'voucher_no') && value(row, 'head_id') && value(row, 'supplier_key') && value(row, 'description') && payerSelected(row) && chargeReady(row) && amount(value(row, 'amount')) > 0);
        const rename = () => [...rows.querySelectorAll('[data-wa-exp-row]')].forEach((row, index) => { row.querySelector('[data-wa-exp-index]').textContent = index + 1; row.querySelectorAll('[name^="rows["]').forEach(input => input.name = input.name.replace(/^rows\[\d+]/, `rows[${index}]`)); });
        const draw = () => {
            let count = 0, pocket = 0, advance = 0, bank = 0, bankCharge = 0, partial = 0;
            rows.querySelectorAll('[data-wa-exp-row]').forEach(row => {
                const source = value(row, 'source');
                const method = row.querySelector('[data-wa-exp-method]');
                const bankPicker = row.querySelector('[data-wa-exp-bank]');
                const chargeInput = row.querySelector('[data-wa-exp-bank-charge]');
                const chargeAccount = row.querySelector('[data-wa-exp-charge-account]');
                const category = row.querySelector('[data-wa-exp-category]');
                const head = row.querySelector('[data-wa-exp-head]');
                row.dataset.source = source;
                if (category && head) {
                    [...head.options].forEach(option => { if (option.value) option.hidden = ! category.value || option.dataset.category !== category.value; });
                    if (head.selectedOptions[0]?.hidden) head.value = '';
                }
                if (source === 'bank' && method && bankPicker) {
                    const wanted = {bank_transfer: 'bank', mobile_banking: 'mobile_banking', digital_wallet: 'digital_wallet'}[method.value] || null;
                    [...bankPicker.options].forEach(option => { if (option.value) option.hidden = Boolean(wanted && option.dataset.type !== wanted); });
                    if (bankPicker.selectedOptions[0]?.hidden) bankPicker.value = '';
                    if (chargeAccount && ! chargeAccount.value) {
                        const preferred = [...chargeAccount.options].find(option => option.textContent.includes('[8510]'));
                        if (preferred) chargeAccount.value = preferred.value;
                    }
                } else {
                    if (bankPicker) bankPicker.value = '';
                    if (chargeInput) chargeInput.value = '0';
                }
                const quantity = amount(value(row, 'quantity'));
                const unitPrice = amount(value(row, 'unit_price'));
                const amountInput = row.querySelector('[name$="[amount]"]');
                if (amountInput && quantity > 0 && unitPrice > 0) amountInput.value = (quantity * unitPrice).toFixed(2);
                const started = active(row), done = complete(row), sum = amount(value(row, 'amount'));
                row.classList.toggle('wa-exp-complete', done); row.classList.toggle('wa-exp-partial', started && ! done);
                if (started && ! done) partial++;
                if (done) { count++; if (source === 'advance') advance += sum; else if (source === 'bank') { bank += sum; bankCharge += amount(value(row, 'bank_charge_amount')); } else pocket += sum; }
            });
            form.querySelector('#wa-exp-count').textContent = count; form.querySelector('#wa-exp-pocket').textContent = money(pocket); form.querySelector('#wa-exp-advance').textContent = money(advance); form.querySelector('#wa-exp-bank').textContent = money(bank); form.querySelector('#wa-exp-bank-charge').textContent = money(bankCharge); form.querySelector('#wa-exp-total').textContent = money(pocket + advance + bank + bankCharge);
            const status = form.querySelector('#wa-exp-status'), ready = Boolean(project.value && count && ! partial);
            status.classList.toggle('wa-exp-ready', ready); status.querySelector('i').className = ready ? 'bi bi-check-circle' : 'bi bi-exclamation-circle';
            status.querySelector('span').textContent = ready ? `${count} expense ${count === 1 ? 'line is' : 'lines are'} ready for review.` : (! project.value ? 'Choose the project / cost centre.' : (partial ? `Complete ${partial} highlighted ${partial === 1 ? 'line' : 'lines'}.` : 'Complete at least one expense line.'));
        };
        const addRow = source => {
            const template = source || rows.querySelector('[data-wa-exp-row]'); if (! template) return;
            const row = template.cloneNode(true); row.querySelectorAll('.wa-exp-row-error').forEach(error => error.remove()); row.classList.remove('wa-exp-error', 'wa-exp-partial', 'wa-exp-complete');
            row.querySelector('[data-wa-project-field]')?.remove();
            if (! source) {
                row.querySelectorAll('input').forEach(input => input.value = input.type === 'date' ? new Date().toISOString().slice(0, 10) : (input.name.endsWith('[quantity]') ? '1' : (input.name.endsWith('[bank_charge_amount]') ? '0' : '')));
                row.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
            }
            const firstVoucher = form.querySelector('[data-wa-voucher]')?.value || `EXP-${new Date().getFullYear()}`;
            const voucher = row.querySelector('[data-wa-voucher]');
            if (voucher) voucher.value = `${firstVoucher}-${rows.children.length + 1}`;
            rows.appendChild(row); rename(); draw(); row.querySelector('input,select')?.focus(); row.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        };
        form.addEventListener('click', event => {
            if (event.target.closest('[data-wa-exp-add]')) return addRow();
            const duplicate = event.target.closest('[data-wa-exp-duplicate]'); if (duplicate) return addRow(duplicate.closest('[data-wa-exp-row]'));
            const remove = event.target.closest('[data-wa-exp-remove]'); if (! remove) return;
            const row = remove.closest('[data-wa-exp-row]');
            if (rows.children.length === 1 || row.querySelector('[data-wa-project-field]')) {
                row.querySelectorAll('input').forEach(input => { if (input.type !== 'date' && ! input.matches('[data-wa-voucher]')) input.value = input.name.endsWith('[quantity]') ? '1' : (input.name.endsWith('[bank_charge_amount]') ? '0' : ''); });
                row.querySelectorAll('select:not(#wa-bulk-project)').forEach(select => select.selectedIndex = 0); row.querySelectorAll('.wa-exp-row-error').forEach(error => error.remove()); row.classList.remove('wa-exp-error');
            } else row.remove();
            rename(); draw();
        });
        form.addEventListener('input', draw); form.addEventListener('change', draw); form.addEventListener('submit', () => { form.classList.add('wa-exp-is-loading'); form.querySelector('#wa-exp-submit').disabled = true; }); rename(); draw();
    };
    initialiseExpenseEntry();
    document.addEventListener('wa:navigated', initialiseExpenseEntry);
})();
</script>
