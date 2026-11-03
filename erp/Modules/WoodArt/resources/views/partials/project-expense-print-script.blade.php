<script>
            document.addEventListener('click', function(event) {
                const printButton = event.target.closest('[data-wa-project-print]');
                if (!printButton) return;
                const today = new Date();
                const localDate = date => {
                    const offset = date.getTimezoneOffset();
                    return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 10);
                };
                const todayValue = localDate(today);
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                const yesterdayValue = localDate(yesterday);
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.delete('page');
                currentParams.delete('view');
                currentParams.delete('print');
                const hasCurrentFilter = Array.from(currentParams.values()).some(value => value !== '');
                const printBase = printButton.dataset.waProjectPrint;

                const option = (value, title, hint, extra = '', disabled = false) => `
                    <label class="wa-project-print-option${disabled ? ' is-disabled' : ''}">
                        <input type="radio" name="expensePrintMode" value="${value}" ${value === 'today' ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                        <span><b>${title}</b>${hint ? `<small>${hint}</small>` : ''}${extra}</span>
                    </label>`;
                const dateInput = (id, value) => `<input type="date" id="${id}" value="${value}" onfocus="this.closest('label').querySelector('input[type=radio]').checked=true">`;

                Swal.fire({
                    title: 'Print expenses',
                    html: `<style>
                        .wa-project-print-list{display:flex;flex-direction:column;gap:7px;text-align:left}
                        .wa-project-print-option{display:flex;align-items:flex-start;gap:10px;padding:10px 11px;border:1px solid #dbe3ee;border-radius:8px;cursor:pointer}
                        .wa-project-print-option:has(input:checked){border-color:#0d9488;background:#f0fdfa}
                        .wa-project-print-option.is-disabled{opacity:.5;cursor:not-allowed}
                        .wa-project-print-option>input{margin-top:3px;accent-color:#0d9488}
                        .wa-project-print-option>span{display:flex;flex:1;flex-direction:column;gap:3px;color:#334155;font-size:13px}
                        .wa-project-print-option small{color:#64748b;font-size:10px}
                        .wa-project-print-dates{display:flex;align-items:center;gap:6px;margin-top:4px;color:#94a3b8;font-size:10px}
                        .wa-project-print-dates input{min-width:0;height:32px;padding:0 7px;border:1px solid #cbd5e1;border-radius:6px;font-size:11px}
                    </style><div class="wa-project-print-list">
                        ${option('today', 'Today', todayValue)}
                        ${option('yesterday', 'Yesterday', yesterdayValue)}
                        ${option('day', 'A specific day', '', `<div class="wa-project-print-dates">${dateInput('expensePrintDay', todayValue)}</div>`)}
                        ${option('range', 'A date range', '', `<div class="wa-project-print-dates">${dateInput('expensePrintFrom', currentParams.get('from') || todayValue)}<span>to</span>${dateInput('expensePrintTo', currentParams.get('to') || todayValue)}</div>`)}
                        ${option('filtered', 'Current filtered results', hasCurrentFilter ? 'Use the search and date filters currently applied.' : 'Apply a search or date filter first.', '', !hasCurrentFilter)}
                    </div>`,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-print"></i> Print',
                    confirmButtonColor: '#0d9488',
                    width: 460,
                    focusConfirm: false,
                    preConfirm: () => {
                        const mode = document.querySelector('input[name="expensePrintMode"]:checked')?.value;
                        let from = '';
                        let to = '';
                        if (mode === 'today') from = to = todayValue;
                        if (mode === 'yesterday') from = to = yesterdayValue;
                        if (mode === 'day') from = to = document.getElementById('expensePrintDay')?.value || '';
                        if (mode === 'range') {
                            from = document.getElementById('expensePrintFrom')?.value || '';
                            to = document.getElementById('expensePrintTo')?.value || '';
                        }
                        if ((mode === 'day' || mode === 'range') && (!from || !to)) {
                            Swal.showValidationMessage('Please select the required date.');
                            return false;
                        }
                        if (from && to && from > to) [from, to] = [to, from];
                        return { mode, from, to };
                    },
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const params = result.value.mode === 'filtered'
                        ? new URLSearchParams(currentParams)
                        : new URLSearchParams();
                    if (result.value.from) params.set('from', result.value.from);
                    if (result.value.to) params.set('to', result.value.to);
                    params.set('print', '1');
                    window.open(`${printBase}?${params.toString()}`, '_blank', 'noopener');
                });
            });
</script>
