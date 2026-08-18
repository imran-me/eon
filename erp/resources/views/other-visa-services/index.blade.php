@extends('layout.app')
@section('meta-information')
    <title>Other Visa Services</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .swal2-container { z-index: 100000 !important; }
    span[aria-current="page"] span { background-color: #0891b2 !important; color: white; border-color: #0891b2; }
    .select2-dropdown { z-index: 99999 !important; }
</style>
@endsection
@section('main-content')

{{-- Page header --}}
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600 flex-shrink-0"><i class="fas fa-puzzle-piece"></i></span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Others — Additional Services</h1>
        <p class="text-xs text-slate-500">Core visa processing-এর বাইরের সার্ভিস — VFS Appointment, Bank Statement, Insurance, Document Support</p>
    </div>
    <button type="button" class="create-new-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700">
        <i class="fas fa-plus mr-1"></i> New Service
    </button>
</header>

{{-- Table card --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    {{-- Integrated filter --}}
    <form action="" method="get" id="filterForm">
        <div class="flex flex-wrap items-center gap-2 bg-slate-50 px-4 py-3">
            <div class="w-44">
                <select id="filter_service_type_id" name="other_service_type_id" class="select2-filter" style="width:100%">
                    <option value="">All Services</option>
                    @foreach ($serviceTypes as $type)
                        <option value="{{ $type->id }}" {{ $type->id == request('other_service_type_id') ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-44">
                <select id="filter_country_id" name="country_id" class="select2-filter" style="width:100%">
                    <option value="">All Countries</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" {{ $country->id == request('country_id') ? 'selected' : '' }}>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>
            <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-xs outline-none focus:border-blue-500">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="done" {{ request('status') == 'done' ? 'selected' : '' }}>Done</option>
                <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search applicant, service #…"
                class="flex-1 min-w-[160px] rounded-xl border border-slate-300 px-3 py-2 text-xs outline-none focus:border-blue-500">
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white">Filter</button>
            <button type="button" class="reset-btn rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</button>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">#</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Applicant</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Service</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Country</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Assigned</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Deadline</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Cost (৳)</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Sales (৳)</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Profit (৳)</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($datas as $key => $value)
                @php
                    $statusClass = [
                        'pending'     => 'bg-amber-100 text-amber-700',
                        'in_progress' => 'bg-blue-100 text-blue-700',
                        'done'        => 'bg-emerald-100 text-emerald-700',
                        'overdue'     => 'bg-red-100 text-red-700',
                        'cancelled'   => 'bg-slate-100 text-slate-600',
                    ][$value->status] ?? 'bg-slate-100 text-slate-600';
                @endphp
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-xs text-slate-400">{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-slate-800">{{ $value->passportHolder?->name ?? '—' }}</div>
                        <div class="text-xs text-slate-400">
                            @if($value->visaProcess)App #{{ $value->visaProcess->application_id }}@else{{ $value->service_code }}@endif
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-bold"
                            style="background:{{ $value->serviceType?->bg_color ?? '#f1f5f9' }};color:{{ $value->serviceType?->color ?? '#64748b' }};">
                            <i class="fas {{ $value->serviceType?->icon ?? 'fa-circle-dot' }} text-[10px]"></i>
                            {{ $value->serviceType?->name ?? '—' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $value->country?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $value->assignedOfficer?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $value->deadline ? $value->deadline->format('d M Y') : '—' }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-slate-600">৳ {{ number_format($value->cost_price, 2) }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-cyan-700">৳ {{ number_format($value->sale_price, 2) }}</td>
                    <td class="px-4 py-3 text-sm font-bold" style="color:{{ ($value->sale_price - $value->cost_price) >= 0 ? '#16a34a' : '#dc2626' }}">
                        ৳ {{ number_format($value->sale_price - $value->cost_price, 2) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $statusClass }}">
                            {{ ucfirst(str_replace('_', ' ', $value->status)) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn"
                                data-item_id="{{ $value->id }}"
                                data-action="{{ route('role.other-visa-services.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'other_visa_service' => $value->id]) }}"
                                data-passport_holder_id="{{ $value->passport_holder_id }}"
                                data-other_service_type_id="{{ $value->other_service_type_id }}"
                                data-country_id="{{ $value->country_id }}"
                                data-assigned_officer_id="{{ $value->assigned_officer_id }}"
                                data-sale_price="{{ $value->sale_price }}"
                                data-cost_price="{{ $value->cost_price }}"
                                data-deadline="{{ $value->deadline ? $value->deadline->format('Y-m-d') : '' }}"
                                data-status="{{ $value->status }}"
                                data-notes="{{ $value->notes }}"
                                data-is_billable="{{ $value->is_billable ? 1 : 0 }}"
                                data-service_code="{{ $value->service_code }}"
                                title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100"
                                onclick="confirmDelete('{{ $value->id }}', '{{ $value->service_code }}')"
                                title="Delete">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="p-12 text-center">
                        <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-500">No services found</p>
                        <p class="mt-1 text-xs text-slate-400">Try adjusting your filters or add a new other service.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4">{{ $datas->appends(request()->all())->links() }}</div>
</div>

@include('other-visa-services.create-modal')
@include('other-visa-services.edit-modal')
@include('other-visa-services.delete-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-filter').select2();
            $('#createModal .select2').select2({ dropdownParent: $('#createModal') });
            $('#editModal .select2').select2({ dropdownParent: $('#editModal') });

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            $('.reset-btn').click(function() {
                window.location = "{{ route('role.other-visa-services.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
            });
        });

        // Show create modal
        $('.create-new-btn').click(function() {
            $('#createForm')[0].reset();
            $('#createModal .select2').val('').trigger('change');
            $('#createModal').removeClass('hidden');
        });

        // Show edit modal
        $('.edit-btn').click(function() {
            $('#editItemId').val($(this).data('item_id'));
            $('#editSubmit').data('action', $(this).data('action'));
            $('#edit_service_code').val($(this).data('service_code'));
            $('#edit_passport_holder_id').val($(this).data('passport_holder_id')).trigger('change');
            $('#edit_other_service_type_id').val($(this).data('other_service_type_id')).trigger('change');
            $('#edit_country_id').val($(this).data('country_id')).trigger('change');
            $('#edit_assigned_officer_id').val($(this).data('assigned_officer_id')).trigger('change');
            $('#edit_sale_price').val($(this).data('sale_price'));
            $('#edit_cost_price').val($(this).data('cost_price'));
            $('#edit_deadline').val($(this).data('deadline'));
            $('#edit_status').val($(this).data('status'));
            $('#edit_notes').val($(this).data('notes'));
            $('#edit_is_billable').prop('checked', $(this).data('is_billable') == 1);
            $('#editModal .error-message').addClass('hidden');
            $('#editModal').removeClass('hidden');
        });

        // Close modals
        $(document).on('click', '.modal-close-create, .modal-close-edit, .modal-close-delete', function() {
            $('#createModal, #editModal, #deleteModal').addClass('hidden');
        });
        $('#createModal, #editModal, #deleteModal').on('click', function(e) {
            if (e.target === this) $(this).addClass('hidden');
        });

        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            if (!$('#create_passport_holder_id').val()) { $('#create_passport_holder_id_msg').removeClass('hidden'); isValid = false; }
            if (!$('#create_other_service_type_id').val()) { $('#create_other_service_type_id_msg').removeClass('hidden'); isValid = false; }
            if (!$('#create_sale_price').val().toString().trim()) { $('#create_sale_price_msg').removeClass('hidden'); isValid = false; }
            if (!$('#create_cost_price').val().toString().trim()) { $('#create_cost_price_msg').removeClass('hidden'); isValid = false; }
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editForm .error-message').addClass('hidden');
            if (!$('#edit_passport_holder_id').val()) { $('#edit_passport_holder_id_msg').removeClass('hidden'); isValid = false; }
            if (!$('#edit_other_service_type_id').val()) { $('#edit_other_service_type_id_msg').removeClass('hidden'); isValid = false; }
            if (!$('#edit_sale_price').val().toString().trim()) { $('#edit_sale_price_msg').removeClass('hidden'); isValid = false; }
            if (!$('#edit_cost_price').val().toString().trim()) { $('#edit_cost_price_msg').removeClass('hidden'); isValid = false; }
            return isValid;
        }

        // Create submit
        $('#createSubmit').click(function(e) {
            e.preventDefault();
            if (validateCreateForm()) {
                let formData = new FormData($('#createForm')[0]);
                formData.set('is_billable', $('#create_is_billable').is(':checked') ? 1 : 0);
                $.ajax({
                    url: $('#createForm').attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Service created successfully!' });
                            $('#createModal').addClass('hidden');
                            $('#createForm')[0].reset();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create service.' });
                    }
                });
            }
        });

        // Edit submit
        $('#editSubmit').click(function() {
            if (validateEditForm()) {
                let formData = new FormData($('#editForm')[0]);
                formData.set('is_billable', $('#edit_is_billable').is(':checked') ? 1 : 0);
                $.ajax({
                    url: $(this).data('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Service updated successfully!' });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                        }
                    },
                    error: function (xhr) {
                        console.error('Error:', xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            }
        });

        // Delete
        $('#confirmDeleteBtn').click(function() {
            const dataId = $(this).data('item-id');
            const deleteUrl = $(this).data('action');
            $.ajax({
                url: deleteUrl,
                method: 'DELETE',
                data: { item_id: dataId },
                success: function (response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: 'Service deleted successfully!' });
                        $('#deleteModal').addClass('hidden');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Oops...', text: response.message });
                    }
                },
                error: function (xhr) {
                    console.error('Error:', xhr.responseText);
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                }
            });
        });

        function confirmDelete(id, name = null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            const role = '{{ Str::slug(Auth::user()->getRoleNames()->first()) }}';
            $('#confirmDeleteBtn').data('action', `/${role}/other-visa-services/${id}`);
            $('#deleteModal').removeClass('hidden');
        }
    </script>
@endsection
