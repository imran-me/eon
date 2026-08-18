@extends('layout.app')
@section('meta-information')
<title>Contract File Manage Sales</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    :root { --b: #e4e8f0; --m: #5a6480 }
    body { font-family: 'DM Sans', sans-serif }

    /* ── Action icon buttons ── */
    .act { width:27px;height:27px;border-radius:7px;border:1px solid var(--b);background:#fff;color:#64748b;display:inline-flex;align-items:center;justify-content:center;font-size:11.5px;cursor:pointer }
    .act:hover { transform:translateY(-1px) }

    /* ── Empty state ── */
    .empty { text-align:center;color:var(--m);padding:40px }

    /* ── Payment status pills ── */
    .pay { display:inline-block;padding:3px 10px;border-radius:999px;font-size:10.5px;font-weight:900 }
    .pay-paid    { background:#dcfce7;color:#166534 }
    .pay-partial { background:#ffedd5;color:#c2410c }
    .pay-due     { background:#fee2e2;color:#b91c1c }

    /* ── Text helpers (used in JS-generated detail HTML) ── */
    .mono { font-family:'DM Mono',monospace }
    .main { font-weight:900;color:#111827 }
    .sub  { font-size:11px;color:#94a3b8;margin-top:2px }

    /* ── Form modal sections ── */
    .sale-section { padding-bottom:18px;margin-bottom:18px;border-bottom:1px solid #eef2f7 }
    .sale-section.mb-0 { margin-bottom:0;padding-bottom:0;border-bottom:0 }
    .sale-section-title { display:flex;align-items:center;gap:9px;font-size:11px;font-weight:900;color:#64748b;text-transform:uppercase;letter-spacing:1.8px;margin-bottom:14px }
    .sale-section-title span { letter-spacing:.8px;text-transform:none;font-size:10px;color:#94a3b8;font-weight:700 }

    /* ── Form inputs ── */
    .lbl { display:block;font-size:11px;font-weight:900;color:#64748b;text-transform:uppercase;margin-bottom:7px }
    .inp,.sel,.txt { width:100%;border:1px solid #d7deea;border-radius:9px;padding:10px 12px;font-size:13px;outline:none;background:#fff }
    .txt { min-height:70px }
    .mini { padding:18px;font-size:12px }
    .grid { display:grid;grid-template-columns:1fr 1fr;gap:14px }
    .full { grid-column:1/-1 }

    /* ── File picker in create/edit modals ── */
    .file-filter-row { display:flex;gap:8px;align-items:center;margin-bottom:10px }
    .filter-chip { height:39px;padding:0 13px;border-radius:10px;border:1px solid #d7deea;background:#fff;color:#475569;font-size:12px;font-weight:800;white-space:nowrap }
    .bundle-table-wrap { border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff }
    .bundle-table { width:100%;border-collapse:collapse }
    .bundle-table th { font-size:10px;font-weight:900;text-transform:uppercase;color:#94a3b8;padding:9px 10px;background:#f9fafb;text-align:left }
    .bundle-table td { font-size:12px;color:#374151;padding:9px 10px;border-top:1px solid #f3f4f6 }
    .bundle-table tr:hover td { background:#f0fdfa }
    .bundle-table tr.selected td { background:#ccfbf1 }
    .bundle-selected-bar { display:flex;justify-content:space-between;align-items:center;padding:9px 12px;background:#f0fdfa;border-top:1px solid #ccfbf1;color:#0f766e;font-size:12px;font-weight:900 }
    .bundle-profit { font-weight:900 }
    .bundle-profit.profit-pos { color:#059669 }
    .bundle-profit.profit-neg { color:#dc2626 }
    .bundle-profit.profit-zero { color:#94a3b8 }
    .row-profit.profit-pos { color:#059669;font-weight:700 }
    .row-profit.profit-neg { color:#dc2626;font-weight:700 }
    .row-profit.profit-zero { color:#94a3b8 }
    .bg-soft { background:#f8fafc }
    .bg-due  { background:#fff7ed;color:#c2410c;font-weight:900 }
    .pay-grid { grid-template-columns:repeat(3,1fr) }

    /* ── Detail modal — JS-generated voucher HTML ── */
    .voucher-info-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px }
    .voucher-info-card { background:#f8fafc;border-radius:10px;padding:14px 16px }
    .voucher-info-card .label { font-size:10px;color:#94a3b8;font-weight:900;text-transform:uppercase;margin-bottom:7px }
    .voucher-info-card .value { color:#1f2937;font-size:14px;font-weight:900 }
    .voucher-table-wrap { border:1px solid #dbe3ef;border-radius:12px;overflow:hidden;margin-bottom:18px }
    .voucher-table { width:100%;border-collapse:collapse;font-size:12px }
    .voucher-table th { background:#0b63ce;color:#fff;padding:11px 14px;font-size:10.5px;font-weight:900;text-transform:uppercase;text-align:left }
    .voucher-table td { padding:13px 14px;border-bottom:1px solid #eef2f7;color:#334155;vertical-align:top }
    .voucher-table tr:last-child td { border-bottom:0 }
    .voucher-summary-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:18px 0 }
    .voucher-summary-card { border-radius:12px;padding:16px;text-align:center }
    .voucher-summary-card .num { font-size:21px;font-weight:900;line-height:1.1 }
    .voucher-summary-card .caption { margin-top:5px;font-size:10px;font-weight:900;text-transform:uppercase }
    .voucher-status-row { text-align:center;padding:0 0 16px;border-bottom:1px solid #eef2f7;margin-bottom:2px }
    .voucher-status-pill { display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:9px 22px;font-size:13px;font-weight:900 }

    /* ── Select2 & SweetAlert z-index ── */
    .select2-container { width:100% !important }
    .select2-container--open,.select2-dropdown { z-index:99999 }
    .swal2-container { z-index:100000 !important }

    @media(max-width:640px) { .grid { grid-template-columns:1fr } }

</style>
@endsection
@section('main-content')
@php($role = Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first()))
@include('contract-file-sales.content')
@include('contract-file-sales.create-modal')
@include('contract-file-sales.edit-modal')
@include('contract-file-sales.details-modal')
@include('contract-file-sales.delete-modal')
@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const role = '{{ $role }}',
        sales = @json($salesJson),
        files = @json($filesJson),
        clients = @json($clientsJson),
        customersData = @json($customersJson);

    const selectedFiles = { create: new Set(), edit: new Set() };
    const lineDrafts = { create: {}, edit: {} };

    $(function() {
        $('.select2,.select2-filter').select2({ width: '100%' });
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        $('.create-btn').click(() => openCreateSale());
        $('.modal-close-create,.modal-close-edit,.modal-close-details,.modal-close-delete,.modal-backdrop').click(e => {
            if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('[class*=modal-close]').length) $('.modalx').addClass('hidden')
        });

        $('#create_client_id,#edit_client_id').on('change', function() {
            syncClientPhone(this.id.startsWith('edit_') ? 'edit' : 'create');
        });
        $('#create_agent_picker,#edit_agent_picker,#create_customer_picker,#edit_customer_picker').on('change', function() {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            const kind = this.id.includes('_agent_picker') ? 'agent' : 'customer';
            pickClientParty(prefix, kind, this.value);
        });
        $('#create_country_id,#edit_country_id').on('change', function() {
            renderFileBundle(this.id.startsWith('edit_') ? 'edit' : 'create');
        });
        $(document).on('input', '.bundle-line-input', function() {
            const prefix = $(this).data('prefix');
            const id = String($(this).data('id'));
            lineDrafts[prefix][id] = lineDrafts[prefix][id] || {};
            if ($(this).hasClass('bundle-sale-price')) lineDrafts[prefix][id].sale_price = Number($(this).val() || 0);
            if ($(this).hasClass('bundle-vendor-cost')) lineDrafts[prefix][id].vendor_cost = Number($(this).val() || 0);
            updateRowProfit(prefix, id);
            updateBundleTotals(prefix);
        });
        $('.calc').on('input', function() { calcStatus(this.id.startsWith('edit_') ? 'edit' : 'create') });
        $('#createSubmit').click(() => submitSale('#createForm', '#createModal'));
        $('#editSubmit').click(() => submitSale('#editForm', '#editModal'));
        $('#confirmDeleteBtn').click(deleteSale);
    });

    // Picking an agent/vendor clears and locks out the customer picker (and vice
    // versa) — only one party can be the sale's client_id — then syncs the
    // hidden field/phone.
    function pickClientParty(prefix, kind, id) {
        const otherSelector = kind === 'agent' ? `#${prefix}_customer_picker` : `#${prefix}_agent_picker`;
        if (id) {
            $(otherSelector).val('').trigger('change.select2').prop('disabled', true);
        } else {
            $(otherSelector).prop('disabled', false);
        }
        $(`#${prefix}_client_id`).val(id).trigger('change');
    }

    function openCreateSale() {
        $('#createForm')[0].reset();
        clearErrors('#createForm');
        selectedFiles.create.clear();
        lineDrafts.create = {};
        $('#create_invoice_number').val('{{ $nextInvoiceNumber }}');
        $('#create_sale_date').val(new Date().toISOString().slice(0, 10));
        $('#create_client_id,#create_country_id').val(null).trigger('change');
        $('#create_agent_picker,#create_customer_picker').prop('disabled', false).val(null).trigger('change.select2');
        $('#create_payment_status').val('due');
        $('#create_paid_amount').val(0);
        $('#create_payment_method').val('cash');
        $('#create_bank_id').val('');
        toggleFileSaleBankField('create');
        renderFileBundle('create');
        $('#createModal').removeClass('hidden');
    }

    function toggleFileSaleBankField(prefix) {
        const isAdvance = $(`#${prefix}_payment_method`).val() === 'advance';
        $(`#${prefix}_bank_field`).toggleClass('hidden', isAdvance);
        if (isAdvance) $(`#${prefix}_bank_id`).val('');
    }

    function editSale(id) {
        $.get(`/${role}/contract-file-sales/${id}/edit`).done(s => {
            $('#editForm')[0].reset();
            clearErrors('#editForm');
            selectedFiles.edit.clear();
            lineDrafts.edit = {};
            $('#editForm').attr('action', `/${role}/contract-file-sales/${id}`);
            $('#edit_invoice_number').val(s.invoice_number);
            $('#edit_sale_date').val(s.sale_date);
            $('#edit_client_id').val(s.client_id).trigger('change');
            const isCustomer = customersData.some(c => String(c.id) === String(s.client_id));
            $('#edit_agent_picker').prop('disabled', false).val(isCustomer ? null : s.client_id).trigger('change.select2');
            $('#edit_customer_picker').prop('disabled', false).val(isCustomer ? s.client_id : null).trigger('change.select2');
            if (s.client_id) {
                $(isCustomer ? '#edit_agent_picker' : '#edit_customer_picker').prop('disabled', true);
            }
            $('#edit_country_id').val(s.country_id).trigger('change');
            (s.items || []).forEach(item => {
                const id = String(item.contract_file_id);
                selectedFiles.edit.add(id);
                lineDrafts.edit[id] = { sale_price: Number(item.sale_price || 0), vendor_cost: Number(item.vendor_cost || 0) };
            });
            $('#edit_paid_amount').val(s.paid_amount);
            $('#edit_receivable_date').val(s.receivable_date);
            $('#edit_payment_status').val(s.payment_status);
            $('#edit_payment_method').val(s.payment_method || 'cash');
            $('#edit_bank_id').val(s.bank_id || '');
            toggleFileSaleBankField('edit');
            $('#edit_notes').val(s.notes);
            renderFileBundle('edit');
            $('#editModal').removeClass('hidden');
        }).fail(() => Swal.fire('Error', 'Failed to load sale data.', 'error'));
    }

    function syncClientPhone(prefix) {
        const client = clients.find(c => String(c.id) === String($(`#${prefix}_client_id`).val()));
        $(`#${prefix}_client_phone`).val(client?.phone || '');
    }

    function renderFileBundle(prefix) {
        const countryId = String($(`#${prefix}_country_id`).val() || '');
        const rows = files.filter(file => !countryId || String(file.country_id) === countryId);
        const $body = $(`#${prefix}_files_body`);

        if (!rows.length) {
            $body.html('<tr><td colspan="8" class="empty mini">No contract files available.</td></tr>');
            updateBundleTotals(prefix);
            return;
        }

        $body.html(rows.map(file => {
            const id = String(file.id);
            const selected = selectedFiles[prefix].has(id);
            const draft = lineDrafts[prefix][id] || {};
            const salePrice = Number(draft.sale_price ?? file.visa_rate ?? 0).toFixed(2);
            const vendorCost = Number(draft.vendor_cost ?? file.visa_rate ?? 0).toFixed(2);
            const status = file.status || 'Available';
            const profit = Number(salePrice) - Number(vendorCost);
            const profitCls = profit > 0 ? 'profit-pos' : (profit < 0 ? 'profit-neg' : 'profit-zero');

            return `<tr class="${selected ? 'selected' : ''}" onclick="toggleBundleFile('${prefix}', '${id}')">
                <td><input type="checkbox" ${selected ? 'checked' : ''} onclick="event.stopPropagation(); toggleBundleFile('${prefix}', '${id}')"></td>
                <td class="mono main">${escapeHtml(file.file_number || '-')}</td>
                <td>${escapeHtml(file.applicant_name || '-')}</td>
                <td><div>${escapeHtml(file.country_name || '-')}</div><div class="sub">${escapeHtml(file.category_name || file.category || '-')}</div></td>
                <td><input class="bundle-line-input bundle-sale-price" data-prefix="${prefix}" data-id="${id}" type="number" min="0" step="0.01" value="${salePrice}" onclick="event.stopPropagation()"></td>
                <td><input class="bundle-line-input bundle-vendor-cost" data-prefix="${prefix}" data-id="${id}" type="number" min="0" step="0.01" value="${vendorCost}" onclick="event.stopPropagation()"></td>
                <td class="row-profit ${profitCls}" id="${prefix}_row_profit_${id}">${Number(profit).toLocaleString()}</td>
                <td><span class="file-pill">${escapeHtml(status)}</span></td>
            </tr>`;
        }).join(''));

        $(`#${prefix}_select_all`).prop('checked', rows.length > 0 && rows.every(file => selectedFiles[prefix].has(String(file.id))));
        updateBundleTotals(prefix);
    }

    function updateRowProfit(prefix, id) {
        const file = files.find(f => String(f.id) === id);
        const draft = lineDrafts[prefix][id] || {};
        const salePrice = Number(draft.sale_price ?? file?.visa_rate ?? 0);
        const vendorCost = Number(draft.vendor_cost ?? file?.visa_rate ?? 0);
        const profit = salePrice - vendorCost;
        const profitCls = profit > 0 ? 'profit-pos' : (profit < 0 ? 'profit-neg' : 'profit-zero');
        $(`#${prefix}_row_profit_${id}`)
            .text(Number(profit).toLocaleString())
            .removeClass('profit-pos profit-neg profit-zero')
            .addClass(profitCls);
    }

    function toggleBundleFile(prefix, id) {
        if (selectedFiles[prefix].has(id)) selectedFiles[prefix].delete(id);
        else {
            selectedFiles[prefix].add(id);
            const file = files.find(f => String(f.id) === id);
            lineDrafts[prefix][id] = lineDrafts[prefix][id] || { sale_price: Number(file?.visa_rate || 0), vendor_cost: Number(file?.visa_rate || 0) };
        }
        renderFileBundle(prefix);
    }

    function toggleAllBundleFiles(prefix, checkbox) {
        const countryId = String($(`#${prefix}_country_id`).val() || '');
        files.filter(file => !countryId || String(file.country_id) === countryId).forEach(file => {
            const id = String(file.id);
            if (checkbox.checked) {
                selectedFiles[prefix].add(id);
                lineDrafts[prefix][id] = lineDrafts[prefix][id] || { sale_price: Number(file.visa_rate || 0), vendor_cost: Number(file.visa_rate || 0) };
            } else selectedFiles[prefix].delete(id);
        });
        renderFileBundle(prefix);
    }

    function updateBundleTotals(prefix) {
        let total = 0, vendorCost = 0;
        const $hidden = $(`#${prefix}_hidden_inputs`).empty();

        selectedFiles[prefix].forEach(id => {
            const file = files.find(f => String(f.id) === String(id));
            const draft = lineDrafts[prefix][id] || {};
            const salePrice = Number(draft.sale_price ?? file?.visa_rate ?? 0);
            const cost = Number(draft.vendor_cost ?? file?.visa_rate ?? 0);
            total += salePrice;
            vendorCost += cost;
            $hidden.append($('<input>').attr({ type: 'hidden', name: 'file_ids[]', value: id }));
            $hidden.append($('<input>').attr({ type: 'hidden', name: `item_sale_prices[${id}]`, value: salePrice }));
            $hidden.append($('<input>').attr({ type: 'hidden', name: `item_vendor_costs[${id}]`, value: cost }));
        });

        $(`#${prefix}_selected_count`).text(`${selectedFiles[prefix].size} file${selectedFiles[prefix].size === 1 ? '' : 's'} selected`);
        $(`#${prefix}_grand_total`).text(Number(total).toLocaleString());
        $(`#${prefix}_total_amount`).val(total.toFixed(2));
        $(`#${prefix}_vendor_cost`).val(vendorCost.toFixed(2));

        const profit = total - vendorCost;
        const marginPct = total > 0 ? (profit / total * 100) : 0;
        const profitCls = profit > 0 ? 'profit-pos' : (profit < 0 ? 'profit-neg' : 'profit-zero');
        $(`#${prefix}_grand_profit`)
            .text(`Profit: BDT ${profit.toLocaleString()} (${marginPct.toFixed(1)}%)`)
            .removeClass('profit-pos profit-neg profit-zero')
            .addClass(profitCls);

        calcStatus(prefix);
    }

    function calcStatus(prefix) {
        const t = Number($(`#${prefix}_total_amount`).val() || 0), p = Number($(`#${prefix}_paid_amount`).val() || 0);
        const due = Math.max(0, t - p);
        $(`#${prefix}_due_amount`).val(due.toFixed(2));
        $(`#${prefix}_payment_status`).val(p >= t && t > 0 ? 'paid' : (p > 0 ? 'partial' : 'due'));
    }

    function clearErrors(form) { $(form + ' .error-message').addClass('hidden'); }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' } [char]));
    }

    function validateSale(form) {
        clearErrors(form);
        const prefix = form === '#editForm' ? 'edit' : 'create';
        let ok = true;
        ['client_id', 'sale_date', 'total_amount'].forEach(n => {
            if (!$(`#${prefix}_${n}`).val()) { $(`#${prefix}_${n}_msg`).removeClass('hidden'); ok = false; }
        });
        if (!selectedFiles[prefix].size) { $(`#${prefix}_file_ids_msg`).removeClass('hidden'); ok = false; }
        return ok;
    }

    function submitSale(form, modal) {
        if (!validateSale(form)) return;
        const btn = form === '#editForm' ? $('#editSubmit') : $('#createSubmit');
        btn.prop('disabled', true);
        $.post($(form).attr('action'), $(form).serialize()).done(res => {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Done', text: res.message, timer: 1400, showConfirmButton: false });
                $(modal).addClass('hidden');
                setTimeout(() => location.reload(), 700);
            } else Swal.fire('Error', res.message, 'error');
        }).fail(xhr => Swal.fire('Error', xhr.responseJSON?.message || 'Request failed.', 'error')).always(() => btn.prop('disabled', false));
    }

    function viewSale(id) {
        $('#v_invoice').text('Loading...');
        $('#v_subtitle').text('');
        $('#v_body').html('<div class="full empty">Loading...</div>');
        $('#v_print_btn').attr('href', `/${role}/contract-file-sales/${id}/voucher`);
        $('#v_email_btn').off('click').on('click', () => Swal.fire('Email Voucher', 'Email sending is not configured for contract file vouchers yet.', 'info'));
        $('#detailsModal').removeClass('hidden');
        $.get(`/${role}/contract-file-sales/${id}`).done(s => {
            const items = s.items || s.files || [];
            const statusText = (s.payment_status || 'due').charAt(0).toUpperCase() + (s.payment_status || 'due').slice(1);
            const statusClass = s.payment_status === 'paid' ? 'pay-paid' : (s.payment_status === 'partial' ? 'pay-partial' : 'pay-due');
            const rows = items.map((f, index) => {
                const profit = Number(f.sale_price || 0) - Number(f.vendor_cost || 0);
                const profitCls = profit > 0 ? 'profit-pos' : (profit < 0 ? 'profit-neg' : 'profit-zero');
                return `<tr>
                <td class="mono">${index + 1}</td>
                <td><div class="mono main" style="color:#0b63ce;">${escapeHtml(f.file_number || '-')}</div><div class="main">${escapeHtml(f.applicant || '-')}</div><div class="sub">${f.passport_no ? 'PP: ' + escapeHtml(f.passport_no) : ''}</div></td>
                <td>${escapeHtml(f.country || '-')}</td>
                <td>${escapeHtml(f.category || '-')}</td>
                <td class="mono main">BDT ${Number(f.sale_price || 0).toLocaleString()}</td>
                <td class="mono">BDT ${Number(f.vendor_cost || 0).toLocaleString()}</td>
                <td class="mono row-profit ${profitCls}">BDT ${profit.toLocaleString()}</td>
                <td><span class="pay ${statusClass}">${escapeHtml(statusText)}</span></td>
            </tr>`;
            }).join('') || '<tr><td colspan="8" class="empty">No files found.</td></tr>';

            $('#v_invoice').text(`Voucher ${s.invoice_number} - ${s.client_name}`);
            $('#v_subtitle').text('Contract File Sale Invoice');
            $('#v_body').html(`<div class="voucher-info-grid">
                <div class="voucher-info-card"><div class="label">Client</div><div class="value">${escapeHtml(s.client_name || '-')}</div></div>
                <div class="voucher-info-card"><div class="label">Phone</div><div class="value">${escapeHtml(s.client_phone || '-')}</div></div>
                <div class="voucher-info-card"><div class="label">Date</div><div class="value">${escapeHtml(s.sale_date || '-')}</div></div>
            </div>
            <div class="voucher-table-wrap"><table class="voucher-table"><thead><tr><th>#</th><th>File ID / Applicant</th><th>Country</th><th>File Category</th><th>Sale Price</th><th>Vendor Cost</th><th>Profit</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table></div>
            <div class="voucher-summary-grid">
                <div class="voucher-summary-card" style="background:#eef7ff;"><div class="num" style="color:#0b63ce;">${items.length}</div><div class="caption" style="color:#60a5fa;">Files</div></div>
                <div class="voucher-summary-card" style="background:#effaf2;"><div class="num" style="color:#16a34a;">BDT ${Number(s.total_amount || 0).toLocaleString()}</div><div class="caption" style="color:#4ade80;">Grand Total</div></div>
                <div class="voucher-summary-card" style="background:#ecfdf5;"><div class="num" style="color:#059669;">BDT ${Number(s.paid_amount || 0).toLocaleString()}</div><div class="caption" style="color:#34d399;">Paid</div></div>
                <div class="voucher-summary-card" style="background:#fff0f0;"><div class="num" style="color:#dc1f35;">BDT ${Number(s.due_amount || 0).toLocaleString()}</div><div class="caption" style="color:#fb7185;">Due</div></div>
                <div class="voucher-summary-card" style="background:#f5f3ff;"><div class="num" style="color:#7c3aed;">BDT ${Number(s.net_profit || 0).toLocaleString()}</div><div class="caption" style="color:#a78bfa;">Net Profit</div></div>
            </div>
            <div class="voucher-status-row"><span class="voucher-status-pill ${statusClass}">Payment Status: ${escapeHtml(statusText)}</span></div>
            ${cmtHtml('contract_file_sale', id)}`);
            loadComments('contract_file_sale', id);
        }).fail(() => $('#v_body').html('<div class="full empty">Failed to load details.</div>'));
    }

    function confirmDelete(id, name) {
        $('#deleteName').text(name);
        $('#confirmDeleteBtn').data('id', id);
        $('#deleteModal').removeClass('hidden');
    }

    function deleteSale() {
        $.ajax({ url: `/${role}/contract-file-sales/${$('#confirmDeleteBtn').data('id')}`, method: 'DELETE', data: { item_id: $('#confirmDeleteBtn').data('id') } })
            .done(res => { if (res.success) { Swal.fire('Done', res.message, 'success'); setTimeout(() => location.reload(), 500) } else Swal.fire('Error', res.message, 'error'); });
    }
</script>
@include('components.comment-panel')
@endsection
