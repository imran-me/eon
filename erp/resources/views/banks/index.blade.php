@extends('layout.app')
@section('meta-information')
    <title>Manage Bank Accounts</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single { height: 42px !important; padding-top: 4px; border-radius: 0.5rem !important; border-color: #e2e8f0 !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
</style>
@endsection
@section('main-content')
@php $role = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first()); @endphp

@include('layout.bank-tabs')

{{-- Page header --}}
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 flex-shrink-0"><i class="fas fa-building-columns"></i></span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Manage Bank Accounts</h1>
        <p class="text-xs text-slate-500">Bank, mobile banking, digital wallet and cash accounts used across the system</p>
    </div>
    {{-- The "Accounts Dashboard" button that stood here is now the first tab. --}}
    @can('create bank')
    <button class="create-new-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700">
        <i class="fas fa-plus mr-1"></i> Add New Bank
    </button>
    @endcan
</header>

@if(session('success'))
<div class="mb-4 flex gap-3 items-center rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    <i class="fas fa-check-circle text-emerald-500"></i> {{ session('success') }}
</div>
@endif

{{-- Table card with integrated filter --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    {{-- Integrated filter --}}
    <form action="" method="GET" class="closest">
        <div class="flex flex-wrap items-end gap-2 bg-slate-50 px-4 py-3">
            <div class="min-w-[160px]">
                <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Account Type</label>
                <select id="account_type" name="account_type" class="select2 w-full text-xs">
                    <option value="">All</option>
                    <option value="savings" {{ old('account_type') == 'savings' ? 'selected' : '' }}>Savings</option>
                    <option value="current" {{ old('account_type') == 'current' ? 'selected' : '' }}>Current</option>
                    <option value="fixed" {{ old('account_type') == 'fixed' ? 'selected' : '' }}>Fixed</option>
                </select>
            </div>
            <div class="min-w-[160px]">
                <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Bank Type</label>
                <select id="bank_type" name="bank_type" class="select2 w-full text-xs">
                    <option value="">All</option>
                    <option value="national" {{ old('bank_type') == 'national' ? 'selected' : '' }}>National</option>
                    <option value="international" {{ old('bank_type') == 'international' ? 'selected' : '' }}>International</option>
                </select>
            </div>
            <div class="min-w-[160px]">
                <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Payment Type</label>
                <select id="type" name="type" class="select2 w-full text-xs">
                    <option value="">All</option>
                    <option value="bank" {{ old('type') == 'bank' ? 'selected' : '' }}>Bank</option>
                    <option value="mobile_banking" {{ old('type') == 'mobile_banking' ? 'selected' : '' }}>Mobile Banking</option>
                    <option value="digital_wallet" {{ old('type') == 'digital_wallet' ? 'selected' : '' }}>Digital Wallet</option>
                </select>
            </div>
            <div class="min-w-[140px]">
                <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Status</label>
                <select id="status" name="status" class="select2 w-full text-xs">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="relative flex-1 min-w-[180px]">
                <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Name</label>
                <input type="text" name="name" value="{{ request('name') }}" id="name" placeholder="Search name..."
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs outline-none focus:border-indigo-500">
            </div>
            <button type="submit" class="apply-btn rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white h-[38px]">Filter</button>
            <button type="button" class="reset-btn rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 h-[38px]">Reset</button>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">SL</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Branch</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Account</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Acc Type</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Routing No.</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Account No.</th>
                    <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Balance</th>
                    <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($datas as $key => $value)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-xs text-slate-400">
                            {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $value->name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $value->branch_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $value->account_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600 capitalize">{{ $value->account_type }}</td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-500">{{ $value->routing_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-500">{{ $value->account_number }}</td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">{{ number_format($value->journal_balance ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($value->status)
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">Active</span>
                            @else
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-semibold text-amber-700">Inactive</span>
                            @endif
                        </td>
                        @canany(['edit bank', 'delete bank'])
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                @can('edit bank')
                                <button class="edit-item-btn flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100"
                                    data-item_id="{{ $value->id }}"
                                    data-name="{{ $value->name }}"
                                    data-company_id="{{ $value->company_id }}"
                                    data-branch_name="{{ $value->branch_name }}"
                                    data-account_id="{{ $value->account_id }}"
                                    data-account_name="{{ $value->account_name }}"
                                    data-account_type="{{ $value->account_type }}"
                                    data-bank_type="{{ $value->bank_type }}"
                                    data-type="{{ $value->type }}"
                                    data-routing_number="{{ $value->routing_number }}"
                                    data-account_number="{{ $value->account_number }}"
                                    data-iban="{{ $value->iban }}"
                                    data-swift_code="{{ $value->swift_code }}"
                                    data-currency="{{ $value->currency }}"
                                    data-address="{{ $value->address }}"
                                    data-balance="{{ $value->balance }}"
                                    data-status="{{ $value->status }}"
                                    title="Edit">
                                    <i class="fas fa-pencil-alt text-xs"></i>
                                </button>
                                @endcan
                                @can('delete bank')
                                <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-500 hover:bg-red-100"
                                    onclick="confirmDelete('{{ $value->id }}', '{{ addslashes($value->name) }}')" title="Delete">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                                @endcan
                            </div>
                        </td>
                        @endcanany
                    </tr>
                @empty
                <tr>
                    <td colspan="10" class="p-12 text-center">
                        <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-500">No data found.</p>
                        <p class="text-xs text-slate-400 mt-1">Try filtering with different criteria.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4">{{ $datas->appends(request()->all())->links() }}</div>
</div>

@include('banks.create-modal')
@include('banks.edit-modal')
@include('banks.delete-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {

            // initialized select2
            $('.select2').select2();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Show create modal
            $('.create-new-btn').click(function() {
                $('#createForm')[0].reset();
                $('#createForm .error-message').addClass('hidden');
                $('#createForm input, #createForm select').removeClass('border-red-500');
                toggleBankTypeFields('create');
                $('#createForm select.select2').trigger('change.select2');
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-item-btn').click(function() {
                const item_id = $(this).data('item_id');
                const name = $(this).data('name');
                const company_id = $(this).data('company_id');
                const branch_name = $(this).data('branch_name');
                const account_id = $(this).data('account_id');
                const account_name = $(this).data('account_name');
                const account_type = $(this).data('account_type');
                const bank_type = $(this).data('bank_type');
                const type = $(this).data('type');
                const routing_number = $(this).data('routing_number');
                const account_number = $(this).data('account_number');
                const iban = $(this).data('iban');
                const swift_code = $(this).data('swift_code');
                const currency = $(this).data('currency');
                const address = $(this).data('address');
                const balance = $(this).data('balance');
                const status = $(this).data('status');

                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_name').val(name);
                $('#edit_company_id').val(company_id).trigger('change');
                $('#edit_branch_name').val(branch_name);
                $('#edit_account_name').val(account_name);
                $('#edit_account_id').val(account_id).trigger('change');
                $('#edit_account_type').val(account_type).trigger('change');
                $('#edit_bank_type').val(bank_type).trigger('change');
                $('#edit_type').val(type).trigger('change');
                $('#edit_routing_number').val(routing_number);
                $('#edit_account_number').val(account_number);
                $('#edit_iban').val(iban);
                $('#edit_swift_code').val(swift_code);
                $('#edit_currency').val(currency);
                $('#edit_address').val(address);
                $('#edit_balance').val(balance);
                $('#edit_status').prop('checked', status);
                $('#editForm .error-message').addClass('hidden');
                $('#editForm input, #editForm select').removeClass('border-red-500');
                $('#editModal').removeClass('hidden');
            });

            // Close modals
            $('.modal-close-create, .modal-backdrop').click(function(e) {
                if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                    $('#createModal').addClass('hidden');
                }
            });

            $('.modal-close-edit, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
                    $('#editModal').addClass('hidden');
                }
            });

            $('.modal-close-delete, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                    $('#deleteModal').addClass('hidden');
                }
            });

            // Close success alert
            $('.close-btn').click(function() {
                $(this).closest('.alert').addClass('hidden');
            });

            // Create state form submission
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (validateCreateForm()) {
                    let formData = new FormData($('#createForm')[0]);
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Done',
                                    text: 'Data created successfully!'
                                });
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: response.message || 'Something went wrong.'
                                });
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Failed to create data.'
                            });
                        }
                    });
                }
            });

            // Edit state form submission
            $('#editSubmit').click(function() {
                if (validateEditForm()) {
                    let formData = new FormData($('#editForm')[0]);
                    $.ajax({
                        url: $(this).data('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: "success",
                                    title: "Done",
                                    text: "Data updated successfully!",
                                });
                                $('#editModal').addClass('hidden');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Oops...",
                                    text: response.message || "Update failed.",
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
                }
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
                        if (response.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Done",
                                text: "Data deleted successfully!",
                            });
                            $('#deleteModal').addClass('hidden');
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

            // Reset filters
            $('.reset-btn').click(function(e) {
                e.preventDefault();
                window.location = '{{ route('role.banks.index', ['role' => $role]) }}';
            });
        });

        // Bank-only fields — Cash and Mobile Banking/Digital Wallet accounts
        // don't have branch names, routing numbers, IBANs, SWIFT codes, or a
        // savings/current/fixed account type, so those are hidden (and
        // disabled, so they're left out of the submitted FormData) unless
        // Payment Type is "Bank".
        const BANK_ONLY_FIELDS = ['branch_name', 'bank_type', 'account_type', 'routing_number', 'iban', 'swift_code'];

        function toggleBankTypeFields(prefix) {
            const type = $('#' + prefix + '_type').val();
            const isBank = type === 'bank' || !type;
            const isMobile = type === 'mobile_banking' || type === 'digital_wallet';

            BANK_ONLY_FIELDS.forEach(function (field) {
                $('#' + prefix + '_field_' + field).toggleClass('hidden', !isBank);
                $('#' + prefix + '_' + field).prop('disabled', !isBank);
                if (!isBank) $('#' + prefix + '_' + field + '_msg').addClass('hidden');
            });

            // Account Number: not applicable to Cash at all; relabeled as a
            // wallet/mobile number (and made optional) for Mobile Banking /
            // Digital Wallet, kept as-is (required) for Bank.
            const $accNumWrap  = $('#' + prefix + '_field_account_number');
            const $accNumInput = $('#' + prefix + '_account_number');
            const $accNumLabel = $('#' + prefix + '_account_number_label');

            if (type === 'cash') {
                $accNumWrap.addClass('hidden');
                $accNumInput.prop('disabled', true);
                $('#' + prefix + '_account_number_msg').addClass('hidden');
            } else {
                $accNumWrap.removeClass('hidden');
                $accNumInput.prop('disabled', false);
                $accNumLabel.html(isMobile
                    ? 'Wallet / Mobile Number'
                    : 'Account Number <span class="text-red-500">*</span>');
            }

            $('#' + prefix + '_mobile_providers').toggleClass('hidden', type !== 'mobile_banking');
        }

        $(document).on('change', '#create_type, #edit_type', function () {
            toggleBankTypeFields(this.id.startsWith('edit_') ? 'edit' : 'create');
        });

        // Quick-pick a mobile banking provider — just prefills the Name field
        // so the user doesn't have to type "bKash" etc. by hand.
        $(document).on('click', '.mobile-provider-btn', function () {
            const prefix = $(this).closest('form').attr('id') === 'editForm' ? 'edit' : 'create';
            $('#' + prefix + '_name').val($(this).data('provider')).focus();
        });

        // Form validation functions
        function validateBankForm(prefix) {
            let isValid = true;
            const form = '#' + prefix + 'Form';
            const type = $('#' + prefix + '_type').val();

            $(form + ' .error-message').addClass('hidden');
            $(form + ' input, ' + form + ' select').removeClass('border-red-500');

            let requiredFields = ['name', 'company_id', 'account_name', 'type', 'account_id', 'balance'];
            if (type === 'bank' || !type) {
                requiredFields = requiredFields.concat(['account_type', 'bank_type', 'account_number']);
            }

            requiredFields.forEach(function (field) {
                const $field = $('#' + prefix + '_' + field);
                if (!String($field.val() || '').trim()) {
                    $('#' + prefix + '_' + field + '_msg').removeClass('hidden');
                    $field.addClass('border-red-500');
                    isValid = false;
                }
            });

            return isValid;
        }

        function validateCreateForm() {
            return validateBankForm('create');
        }

        function validateEditForm() {
            return validateBankForm('edit');
        }

        // Delete confirmation
        function confirmDelete(id, name = null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }
    </script>
@endsection
