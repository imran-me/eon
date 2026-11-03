@extends('layout.app')
@section('meta-information')
<title>{{ request()->routeIs('role.contract-files.dashboard') ? 'File Dashboard' : (request()->routeIs('role.contract-files.accounts') ? 'File Accounts' : (request()->routeIs('role.contract-files.board') ? 'Passenger Board' : 'Files & Passengers')) }}</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container { width: 100% !important }
    .select2-container--default .select2-selection--single { height: 38px; border: 1px solid #d7deea; border-radius: 8px; display: flex; align-items: center }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color: #1a2035; font-size: 12.5px; line-height: 38px; padding-left: 11px; padding-right: 28px }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; right: 6px }
    .select2-container--open, .select2-dropdown { z-index: 99999 }
</style>
@endsection
@section('main-content')
@php($role = Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first()))
@include('layout.contract-file-tabs')
@include('contract-files.workspace-styles')
{{-- Contract Flight's own look (owner, 2026-10-25): dashboard, ledger and
     Flights & Passengers sheets, the column sort. All fd-/fdl-/mfp-/mj- prefixed. --}}
@include('contract-files.flight-dashboard-styles')
@include('contract-flights.dashboard-ledger-styles')
@include('contract-files.flight-list-styles')
@include('contract-flights.partials.accounts-styles')
@include('contract-flights.partials.table-sort')
@if(request()->routeIs('role.contract-files.dashboard'))
@include('contract-files.dashboard')
@elseif(request()->routeIs('role.contract-files.board'))
@include('contract-files.board')
@elseif(request()->routeIs('role.contract-files.accounts'))
@include('contract-files.accounts')
@else
@include('contract-files.passenger-list')
@endif

@include('contract-files.create-modal')
@include('contract-files.batch-builder')
@include('contract-files.edit-modal')
@include('contract-files.view-modal')
@include('contract-files.record-sheet-modal')
@include('contract-files.delete-modal')
@include('contract-files.pay-vendor-modal')
@include('contract-files.money-action-modal')

<div id="cfPhModal" class="fixed inset-0 z-[9100] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop" style="z-index:9300">
    <div class="w-full max-w-xl overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex items-center gap-3 bg-blue-600 px-5 py-4 text-white"><i class="fas fa-user-plus"></i><div><h2 class="text-sm font-bold">Add New Passport Holder</h2><p class="text-[11px] text-blue-100">Save applicant and select automatically</p></div><button type="button" class="ml-auto h-8 w-8 rounded-lg bg-white/15" onclick="closeCfPhModal()"><i class="fas fa-times"></i></button></header>
        <div class="p-5">
            <div id="cfPhErr" style="display:none;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:8px 12px;font-size:12px;margin-bottom:12px;"></div>
            <div class="cfm-grid" style="grid-template-columns:1fr 1fr;margin-bottom:0;">
                <div><label class="cfm-lbl">Full Name <sup>*</sup></label><input type="text" id="cfPhName" class="cfm-input" placeholder="e.g. Md Rahman"></div>
                <div><label class="cfm-lbl">Passport No</label><input type="text" id="cfPhPassportNo" class="cfm-input" placeholder="e.g. BD1234567"></div>
                <div><label class="cfm-lbl">Nationality</label><input type="text" id="cfPhNationality" class="cfm-input" value="Bangladeshi"></div>
                <div><label class="cfm-lbl">Phone</label><input type="text" id="cfPhPhone" class="cfm-input" placeholder="01XXXXXXXXX"></div>
                <div><label class="cfm-lbl">Date of Birth</label><input type="date" id="cfPhDob" class="cfm-input"></div>
                <div><label class="cfm-lbl">Category</label><select id="cfPhCategory" class="cfm-select"><option value="">Select category</option>@foreach($phCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                <div><label class="cfm-lbl">Type</label><select id="cfPhType" class="cfm-select">@include('passport-holder.type-options')</select></div>
                <div><label class="cfm-lbl">Issue Date</label><input type="date" id="cfPhIssueDate" class="cfm-input"></div>
                <div><label class="cfm-lbl">Expiry Date</label><input type="date" id="cfPhExpiryDate" class="cfm-input"></div>
            </div>
        </div>
        <footer class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="rounded-lg border px-4 py-2 text-xs font-semibold" onclick="closeCfPhModal()">Cancel</button><button type="button" id="cfPhSaveBtn" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white" onclick="submitCfPhModal()"><i class="fas fa-save mr-1"></i>Save Passport Holder</button></footer>
    </div>
</div>
@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const roleSlug = '{{ $role }}';
    const categories = @json($categoriesJson);
    let passportHolders = @json($passportHoldersJson);
    let files = @json($filesJson);
    const statuses = @json($statuses);
    let cfPhTargetSelectId = null;

    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        initSelect2();

        $('.create-btn').on('click', function() {
            if (window.CFFileBuilder) {
                window.CFFileBuilder.open();
                @if($groupClient) window.CFFileBuilder.setClient(@json($groupClient->id)); @endif
                return;
            }
            $('#createForm')[0].reset();
            resetSelect2('#createModal');
            applyPassportHolder('create', '');
            $('#create_file_number').val('{{ $nextFileNumber }}');
            $('#create_status').val('file_received');
            @if($groupClient)
            $('#create_client_id').val(@json($groupClient->id)).trigger('change.select2');
            $('#create_client_type').val(@json($groupClient->hasRole('agent') ? 'agent' : 'direct')).trigger('change.select2');
            @endif
            $('#create_submit_date').val(new Date().toISOString().slice(0, 10));
            renderDocuments('#create_documents_grid', []);
            clearErrors('#createForm');
            $('#create_vendor_id, #create_portal_id, #create_cost_bank_id').prop('disabled', false);
            $('#create_cost_bank_wrap, #create_cost_sched_wrap').show();
            $('#createModal').removeClass('hidden');
            refreshSelect2('#createModal');
        });

        if (new URLSearchParams(window.location.search).get('open') === 'create') {
            $('.create-btn').first().trigger('click');
        }

        $('#create_contract_file_category_id').on('change', function() {
            applyCategory('create', this.value);
        });
        $('#edit_contract_file_category_id').on('change', function() {
            applyCategory('edit', this.value, false);
        });
        $('#create_country_id').on('change', function() {
            filterCategoryOptions('create', true);
        });
        $('#edit_country_id').on('change', function() {
            filterCategoryOptions('edit', true);
        });
        // Setup (2026-10-25): a new country / category / visa type on a file ADDS the documents Setup
        // requires for it (unticked — not received yet). Nothing is removed and no figure changes:
        // the file's money has its own history.
        $('#edit_country_id, #edit_contract_file_category_id, #edit_contract_file_visa_type_id').on('change', function() {
            addSetupDocuments('edit');
        });
        $('#create_passport_holder_id').on('change', function() {
            applyPassportHolder('create', this.value);
        });
        $('#edit_passport_holder_id').on('change', function() {
            applyPassportHolder('edit', this.value);
        });

        $('#createAddDoc').on('click', function() {
            addCustomDocument('create');
        });
        $('#editAddDoc').on('click', function() {
            addCustomDocument('edit');
        });

        $('.modal-close-create,.modal-close-edit,.modal-close-view,.modal-close-delete').on('click', closeModals);
        $('.modal-backdrop').on('click', function(e) {
            if ($(e.target).hasClass('modal-backdrop') && e.target.id !== 'editModal') closeModals();
        });

        $('#createSubmit').on('click', function(e) {
            e.preventDefault();
            submitFile('#createForm', $('#createForm').attr('action'), 'Contract file created successfully.', '#createModal');
        });

        $('#editSubmit').on('click', function(e) {
            e.preventDefault();
            submitFile('#editForm', $(this).data('action'), 'Contract file updated successfully.', '#editModal');
        });

        $('#deleteReason,#deleteConfirmation').on('input', function() {
            const ready = $('#deleteReason').val().trim().length >= 5 && $('#deleteConfirmation').val() === 'CONFIRM';
            $('#confirmDeleteBtn').prop('disabled', !ready);
            $('#deleteValidation').addClass('hidden').text('');
        });

        $('#confirmDeleteBtn').on('click', function() {
            const reason = $('#deleteReason').val().trim();
            const confirmation = $('#deleteConfirmation').val();
            if (reason.length < 5 || confirmation !== 'CONFIRM') {
                $('#deleteValidation').removeClass('hidden').text('Enter a reason of at least 5 characters and type CONFIRM exactly.');
                return;
            }
            const button = $(this);
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Deleting…');
            $.ajax({
                url: button.data('action'),
                method: 'DELETE',
                data: {
                    item_id: button.data('item-id'),
                    deletion_reason: reason,
                    confirmation: confirmation
                },
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Done',
                            text: 'Contract file deleted successfully.'
                        });
                        $('#deleteModal').addClass('hidden');
                        setTimeout(() => location.reload(), 600);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message || 'Delete failed.'
                        });
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {})[0]?.[0] || 'Delete request failed.';
                    $('#deleteValidation').removeClass('hidden').text(message);
                },
                complete: function() {
                    button.prop('disabled', false).html('<i class="fas fa-trash mr-1"></i> Delete file');
                }
            });
        });
    });

    function closeModals() {
        $('#createModal,#editModal,#viewModal,#deleteModal,#cfPhModal').addClass('hidden');
    }

    function initSelect2() {
        $('.cf-select2').select2({
            width: '100%',
            allowClear: false
        });
        $('.cfm-select').select2({
            width: '100%',
            allowClear: false
        });
    }

    function resetSelect2(scope) {
        $(scope).find('select').val('').trigger('change.select2');
    }

    function refreshSelect2(scope) {
        $(scope).find('select').trigger('change.select2');
    }

    function clearErrors(form) {
        $(form + ' .error-message').addClass('hidden');
    }

    function submitFile(form, url, successText, modal) {
        if (!validateFileForm(form)) return;
        const hasFiles = $(form).find('input[type="file"]').length > 0;
        $.ajax({
            url: url,
            method: 'POST',
            data: hasFiles ? new FormData($(form)[0]) : $(form).serialize(),
            processData: !hasFiles,
            contentType: hasFiles ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Done',
                        text: successText
                    });
                    $(modal).addClass('hidden');
                    setTimeout(() => location.reload(), 700);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'Request failed.'
                    });
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'Request failed.'
                });
            }
        });
    }

    function validateFileForm(form) {
        let ok = true;
        clearErrors(form);
        ['passport_holder_id', 'client_id', 'client_type', 'client_price', 'country_id', 'contract_file_category_id', 'vendor_id', 'visa_rate', 'submit_date'].forEach(function(name) {
            const field = $(form + ' [name="' + name + '"]');
            if (!field.val()) {
                $('#' + field.attr('id') + '_msg').removeClass('hidden');
                ok = false;
            }
        });
        return ok;
    }

    function applyPassportHolder(prefix, id) {
        const holder = passportHolders.find(item => String(item.id) === String(id));
        const $info = $('#' + prefix + '_holder_info');
        if (!holder) {
            $info.addClass('hidden');
            $('#' + prefix + '_holder_passport').text('-');
            $('#' + prefix + '_holder_phone').text('-');
            $('#' + prefix + '_holder_nationality').text('-');
            $('#' + prefix + '_holder_dob').text('-');
            return;
        }

        $('#' + prefix + '_holder_passport').text(holder.passport_no || '-');
        $('#' + prefix + '_holder_phone').text(holder.phone || '-');
        $('#' + prefix + '_holder_nationality').text(holder.nationality || '-');
        $('#' + prefix + '_holder_dob').text(formatDate(holder.date_of_birth));
        $info.removeClass('hidden');
    }

    function applyCategory(prefix, id, resetDocs = true) {
        const category = categories.find(item => String(item.id) === String(id));
        if (!category) return;
        $('#' + prefix + '_country_id').val(category.country_id || '');
        filterCategoryOptions(prefix, false);
        $('#' + prefix + '_country_id').trigger('change.select2');
        $('#' + prefix + '_visa_rate').val(category.visa_rate || 0);
        if (resetDocs) renderDocuments('#' + prefix + '_documents_grid', (category.documents_list || []).map(name => ({
            name: name,
            checked: true
        })));
    }

    const setupMatchUrl = @json($setupMatchUrl ?? null);
    function addSetupDocuments(prefix) {
        if (!setupMatchUrl) return;
        const q = new URLSearchParams({
            country_id: $('#' + prefix + '_country_id').val() || '',
            visa_type_id: $('#' + prefix + '_contract_file_visa_type_id').val() || '',
            category_id: $('#' + prefix + '_contract_file_category_id').val() || ''
        });
        fetch(setupMatchUrl + '?' + q, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : null)
            .then(r => {
                if (!r || !Array.isArray(r.documents)) return;
                const target = '#' + prefix + '_documents_grid';
                const have = new Set($(target).find('input[name="document_names[]"]').map((i, el) => String(el.value).toLowerCase()).get());
                const missing = r.documents.filter(name => !have.has(String(name).toLowerCase()));
                if (!missing.length) return;
                $(target + ' .cfm-doc-empty').remove();
                missing.forEach(name => $(target).append(documentRow(name, false)));
            })
            .catch(() => {});
    }

    function filterCategoryOptions(prefix, resetIfMismatch = true) {
        const countryId = $('#' + prefix + '_country_id').val();
        const $categorySelect = $('#' + prefix + '_contract_file_category_id');
        const currentCategory = $categorySelect.val();
        let currentAllowed = true;

        $categorySelect.find('option').each(function() {
            const optionCountry = String($(this).data('country') || '');
            if (!$(this).val()) {
                $(this).prop('hidden', false);
                return;
            }
            const show = !countryId || optionCountry === String(countryId);
            $(this).prop('hidden', !show).prop('disabled', !show);
            if ($(this).val() === currentCategory && !show) {
                currentAllowed = false;
            }
        });

        if (resetIfMismatch && !currentAllowed) {
            $categorySelect.val('').trigger('change.select2');
            $('#' + prefix + '_visa_rate').val('');
            renderDocuments('#' + prefix + '_documents_grid', []);
        }
    }

    function renderDocuments(target, docs) {
        const $target = $(target).empty();
        if (!docs.length) {
            $target.append('<div class="cfm-doc-empty">Select a category to load required documents.</div>');
            return;
        }
        docs.forEach(function(doc) {
            const name = doc.name || doc;
            const checked = doc.checked === undefined ? true : !!doc.checked;
            $target.append(documentRow(name, checked));
        });
    }

    function documentRow(name, checked) {
        const safe = escapeHtml(name);
        return `<label class="cfm-doc"><input type="hidden" name="document_names[]" value="${safe}"><input type="checkbox" name="received_documents[]" value="${safe}" ${checked ? 'checked' : ''}> <span>${safe}</span></label>`;
    }

    function addCustomDocument(prefix) {
        const input = $('#' + prefix + '_custom_doc');
        const name = input.val().trim();
        if (!name) return;
        const target = '#' + prefix + '_documents_grid';
        $(target + ' .cfm-doc-empty').remove();
        $(target).append(documentRow(name, false));
        input.val('');
    }

    function editFile(id) {
        const item = files.find(file => file.id === id);
        if (!item) return;
        $('#edit_file_number').val(item.file_number);
        $('#edit_passport_holder_id').val(item.passport_holder_id || '').trigger('change.select2');
        applyPassportHolder('edit', item.passport_holder_id || '');
        $('#edit_country_id').val(item.country_id || '').trigger('change.select2');
        filterCategoryOptions('edit', false);
        $('#edit_contract_file_category_id').val(item.contract_file_category_id || '').trigger('change.select2');
        $('#edit_contract_file_visa_type_id').val(item.contract_file_visa_type_id || '').trigger('change.select2');
        $('#edit_vendor_id').val(item.vendor_id || '').trigger('change.select2');
        $('#edit_portal_id').val(item.portal_id || '').trigger('change.select2');
        $('#edit_cost_bank_id').val(item.cost_bank_id || '').trigger('change.select2');
        $('#edit_cost_paid_amount').val(item.cost_paid_amount || 0);
        $('#edit_purchase_date').val(item.purchase_date || '');
        cfOnVendorChange(document.getElementById('edit_vendor_id'));
        cfOnPortalChange(document.getElementById('edit_portal_id'));
        $('#edit_client_id').val(item.client_id || '').trigger('change.select2');
        $('#edit_client_type').val(item.client_type || 'agent').trigger('change.select2');
        $('#edit_client_price').val(item.client_price || 0);
        $('#edit_visa_rate').val(item.visa_rate || 0);
        $('#edit_payable_date').val(item.payable_date || '');
        $('#edit_submit_date').val(item.submit_date || '');
        $('#edit_expected_out_date').val(item.expected_out_date || '');
        $('#edit_status').val(item.status || 'file_received').trigger('change.select2');
        $('#edit_exception_flag').val(item.exception_flag || '').trigger('change.select2');
        $('#edit_notes').val(item.notes || '');
        renderDocuments('#edit_documents_grid', item.required_documents || []);
        $('#editSubmit').data('action', `/${roleSlug}/contract-files/${id}`);
        const actions=document.getElementById('cf_edit_money_actions');actions.replaceChildren();
        const source=document.querySelector(`[data-file-row="${id}"] [data-cf-money-info]`);
        if(source){const heading=document.createElement('span');heading.textContent='Financial actions';actions.append(heading,source.cloneNode(true));}
        clearErrors('#editForm');
        window.resetFileEditSteps(item);
        $('#editModal').removeClass('hidden');
        refreshSelect2('#editModal');
    }

    function viewFile(id) {
        return window.openFileSheet(id);
    }

    function openCfPhModal(selectId) {
        cfPhTargetSelectId = selectId;
        ['cfPhName', 'cfPhPassportNo', 'cfPhPhone', 'cfPhDob', 'cfPhIssueDate', 'cfPhExpiryDate'].forEach(id => $('#' + id).val(''));
        $('#cfPhNationality').val('Bangladeshi');
        $('#cfPhCategory').val('').trigger('change.select2');
        $('#cfPhType').val('contract_file');
        $('#cfPhErr').hide().text('');
        $('#cfPhModal').removeClass('hidden');
    }

    function closeCfPhModal() {
        $('#cfPhModal').addClass('hidden');
        cfPhTargetSelectId = null;
    }

    function submitCfPhModal() {
        const payload = {
            name: $('#cfPhName').val().trim(),
            passport_no: $('#cfPhPassportNo').val().trim(),
            nationality: $('#cfPhNationality').val().trim(),
            phone: $('#cfPhPhone').val().trim(),
            date_of_birth: $('#cfPhDob').val(),
            issue_date: $('#cfPhIssueDate').val(),
            expiry_date: $('#cfPhExpiryDate').val(),
            category_id: $('#cfPhCategory').val(),
            type: $('#cfPhType').val(),
            status: 1,
            // Contract File asks for the name only (owner, 2026-10-26) — the rest can be filled in later
            source: 'contract_file',
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        if (!payload.name) {
            $('#cfPhErr').text('Full name is required.').show();
            return;
        }

        $('#cfPhSaveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving');
        $.ajax({
            url: "{{ route('role.passport-holder.store', ['role' => $role]) }}",
            method: 'POST',
            data: payload,
            success: function(res) {
                $('#cfPhSaveBtn').prop('disabled', false).html('<i class="fas fa-save"></i> Save Passport Holder');
                if (!res.success) {
                    $('#cfPhErr').text(res.message || 'Failed to save passport holder.').show();
                    return;
                }

                const holder = {
                    id: res.data.id,
                    name: res.data.name,
                    passport_no: res.data.passport_no,
                    phone: res.data.phone,
                    nationality: res.data.nationality,
                    date_of_birth: res.data.date_of_birth
                };
                passportHolders.push(holder);
                if (window.CFFileBuilder) {
                    window.CFFileBuilder.registerHolder(holder);
                }
                const label = holder.name + (holder.phone ? ' - ' + holder.phone : '');
                $('#create_passport_holder_id,#edit_passport_holder_id,#cfe-add-holder').each(function() {
                    if (!$(this).find('option[value="' + holder.id + '"]').length) {
                        $(this).append(new Option(label, holder.id));
                    }
                });
                if (cfPhTargetSelectId) {
                    $('#' + cfPhTargetSelectId).val(holder.id).trigger('change').trigger('change.select2');
                }
                closeCfPhModal();
                Swal.fire({icon: 'success', title: 'Added', text: holder.name + ' has been saved.', timer: 1500, showConfirmButton: false});
            },
            error: function(xhr) {
                $('#cfPhSaveBtn').prop('disabled', false).html('<i class="fas fa-save"></i> Save Passport Holder');
                $('#cfPhErr').text(xhr.responseJSON?.message || 'Server error. Please try again.').show();
            }
        });
    }

    function confirmDelete(id, name) {
        $('#deleteName').text(name);
        $('#confirmDeleteBtn').data('item-id', id).data('action', `/${roleSlug}/contract-files/${id}`);
        $('#deleteReason,#deleteConfirmation').val('');
        $('#deleteValidation').addClass('hidden').text('');
        $('#confirmDeleteBtn').prop('disabled', true).html('<i class="fas fa-trash mr-1"></i> Delete file');
        $('#deleteModal').removeClass('hidden');
        setTimeout(() => $('#deleteReason').trigger('focus'), 0);
    }

    // ── Vendor / Portal / Cost Bank mutual exclusion ────────────────
    function cfFieldPrefix(el) {
        return el.id.startsWith('edit_') ? 'edit' : 'create';
    }

    function cfOnVendorChange(sel) {
        const prefix = cfFieldPrefix(sel);
        const portalSel = document.getElementById(prefix + '_portal_id');
        if (!portalSel) return;
        setTimeout(function () {
            if (sel.value) {
                $(portalSel).val(null).prop('disabled', true).trigger('change.select2');
            } else {
                $(portalSel).prop('disabled', false).trigger('change.select2');
            }
            cfToggleCostBank(portalSel);
        }, 0);
    }

    function cfOnPortalChange(sel) {
        const prefix = cfFieldPrefix(sel);
        const vendorSel = document.getElementById(prefix + '_vendor_id');
        if (!vendorSel) return;
        setTimeout(function () {
            if (sel.value) {
                $(vendorSel).val(null).prop('disabled', true).trigger('change.select2');
            } else {
                $(vendorSel).prop('disabled', false).trigger('change.select2');
            }
            cfToggleCostBank(sel);
        }, 0);
    }

    function cfToggleCostBank(portalSel) {
        const prefix = cfFieldPrefix(portalSel);
        const bankSel = document.getElementById(prefix + '_cost_bank_id');
        const bankWrap = document.getElementById(prefix + '_cost_bank_wrap');
        const schedWrap = document.getElementById(prefix + '_cost_sched_wrap');
        if (bankSel) {
            $(bankSel).prop('disabled', !!portalSel.value);
            if (portalSel.value) { $(bankSel).val(null).trigger('change.select2'); }
        }
        if (bankWrap) bankWrap.style.display = portalSel.value ? 'none' : '';
        if (schedWrap) schedWrap.style.display = portalSel.value ? 'none' : '';
    }

    // ── Pay Vendor ────────────────────────────────────────────────
    let _payVendorFileId = null;

    function openPayVendorModal(id, fileNumber, vendorName, dueAmount) {
        _payVendorFileId = id;
        $('#pv_file_number').text(fileNumber);
        $('#pv_vendor_name').text(vendorName);
        $('#pv_due_amount').text(Number(dueAmount).toFixed(2));
        $('#pv_bank_id').val('');
        $('#pv_payment_date').val('{{ now()->toDateString() }}');
        $('#pv_payment_amount').val(dueAmount);
        $('#pv_reference_no').val('');
        $('#pv_error').addClass('hidden').text('');
        $('#payVendorModal').removeClass('hidden');
    }

    $('.modal-close-pay-vendor').on('click', function () {
        $('#payVendorModal').addClass('hidden');
        _payVendorFileId = null;
    });

    $('#pv_submit_btn').on('click', function () {
        const err = $('#pv_error');
        err.addClass('hidden').text('');

        if (!$('#pv_bank_id').val()) {
            err.removeClass('hidden').text('Please select a bank.');
            return;
        }
        if (!$('#pv_payment_amount').val() || Number($('#pv_payment_amount').val()) <= 0) {
            err.removeClass('hidden').text('Please enter a valid payment amount.');
            return;
        }

        $.ajax({
            url: `/${roleSlug}/contract-files/${_payVendorFileId}/pay-vendor`,
            method: 'POST',
            data: {
                bank_id:        $('#pv_bank_id').val(),
                payment_date:   $('#pv_payment_date').val(),
                payment_amount: $('#pv_payment_amount').val(),
                reference_no:   $('#pv_reference_no').val(),
            },
            success: function (response) {
                if (response.success) {
                    $('#payVendorModal').addClass('hidden');
                    window.location.reload();
                } else {
                    err.removeClass('hidden').text(response.message || 'Payment failed.');
                }
            },
            error: function (xhr) {
                err.removeClass('hidden').text(xhr.responseJSON?.message || 'Payment failed.');
            }
        });
    });

    function formatDate(value) {
        if (!value) return '-';
        return new Date(value).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [match];
        });
    }
</script>

{{-- Deep link from the Operations Status Tracker: `?open={id}` opens that
     file's view modal. The tracker also sends `?search={file_number}` because
     `files` above only holds the current page — the search guarantees the
     record is on it. Inert on every visit without `?open` in the URL. --}}
<script>
    (function () {
        var openId = new URLSearchParams(window.location.search).get('open');
        if (!openId || !/^[0-9]+$/.test(openId)) return;

        window.addEventListener('load', function () {
            if (typeof viewFile === 'function') viewFile(Number(openId));
        });
    })();
</script>
@endsection
