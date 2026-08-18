@extends('layout.app')
@section('meta-information')
    <title>Passport Holders</title>
@endsection
@section('main-content')

<div class="min-h-screen bg-gray-50 p-6">

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Passport Holders</h1>
        <p class="text-sm text-gray-500 mt-0.5">Manage all registered passport holders</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-100">
            <form method="GET" action="{{ route('role.passport-holder.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                  class="flex items-center gap-3 flex-1 flex-wrap">
                {{-- Name search --}}
                <div class="relative min-w-[220px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </span>
                    <input type="text" name="name" value="{{ request('name') }}"
                           placeholder="Search name..."
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50">
                </div>
                {{-- Category --}}
                <select name="category_id"
                        class="py-2 px-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 min-w-[160px]">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ $category->id == request('category_id') ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <select name="type"
                        class="py-2 px-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 min-w-[170px]">
                    <option value="">All Types</option>
                    @foreach(['general'=>'General','visa'=>'Visa Processing','ticket'=>'Air Ticketing','contract_flight'=>'Contract Flight','contract_file'=>'Contract File'] as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected(request('type') === $typeValue)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
                {{-- Status --}}
                <select name="status"
                        class="py-2 px-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 min-w-[130px]">
                    <option value="">All Status</option>
                    <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive</option>
                </select>
                <button type="submit"
                        class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                    Filter
                </button>
                <a href="{{ route('role.passport-holder.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                   class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    Reset
                </a>
            </form>
            @can('create passport holder')
            <button type="button" class="create-new-btn flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm ml-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Passport Holder
            </button>
            @endcan
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3 text-left w-12">#</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Passport No</th>
                        <th class="px-4 py-3 text-left">Nationality</th>
                        <th class="px-4 py-3 text-left">DOB</th>
                        <th class="px-4 py-3 text-left">Issue Date</th>
                        <th class="px-4 py-3 text-left">Expiry Date</th>
                        <th class="px-4 py-3 text-left">Category</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        @canany(['edit passport holder', 'delete passport holder'])
                        <th class="px-4 py-3 text-center">Actions</th>
                        @endcanany
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($datas as $key => $value)
                        @php
                            $expiryDate  = $value->expiry_date ? \Carbon\Carbon::parse($value->expiry_date) : null;
                            $isExpiring  = $expiryDate && $expiryDate->diffInDays(now(), false) <= 0 && $expiryDate->diffInDays(now()) <= 90;
                            $isExpired   = $expiryDate && $expiryDate->isPast();
                        @endphp
                        <tr class="hover:bg-blue-50/30 transition-colors">
                            <td class="px-4 py-3.5">
                                <span class="text-xs font-semibold text-gray-400">
                                    {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                        {{ strtoupper(substr($value->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 leading-tight">{{ $value->name }}</p>
                                        @if($value->phone)
                                            <p class="text-xs text-gray-400 mt-0.5">{{ $value->phone }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="font-mono text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-1 rounded">
                                    {{ $value->passport_no ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-gray-600 text-sm">{{ $value->nationality ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-gray-500 text-xs">
                                {{ $value->date_of_birth ? \Carbon\Carbon::parse($value->date_of_birth)->format('d M Y') : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-gray-500 text-xs">
                                {{ $value->issue_date ? \Carbon\Carbon::parse($value->issue_date)->format('d M Y') : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-xs">
                                @if($expiryDate)
                                    <span class="{{ $isExpired ? 'text-red-600 font-semibold' : ($isExpiring ? 'text-amber-600 font-medium' : 'text-gray-500') }}">
                                        {{ $expiryDate->format('d M Y') }}
                                        @if($isExpired)
                                            <span class="block text-[10px] text-red-400">Expired</span>
                                        @elseif($isExpiring)
                                            <span class="block text-[10px] text-amber-400">Expiring soon</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @if($value->category)
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-purple-50 text-purple-700">
                                        {{ $value->category->name }}
                                    </span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @php
                                    $typeLabels = ['general'=>'General','visa'=>'Visa Processing','ticket'=>'Air Ticketing','contract_flight'=>'Contract Flight','contract_file'=>'Contract File'];
                                    $typeColors = ['general'=>'bg-gray-100 text-gray-700','visa'=>'bg-blue-100 text-blue-700','ticket'=>'bg-cyan-100 text-cyan-700','contract_flight'=>'bg-violet-100 text-violet-700','contract_file'=>'bg-amber-100 text-amber-700'];
                                    $holderType = $value->type ?: 'general';
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $typeColors[$holderType] ?? $typeColors['general'] }}">{{ $typeLabels[$holderType] ?? Str::headline($holderType) }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if($value->status)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            @canany(['edit passport holder', 'delete passport holder'])
                            <td class="px-4 py-3.5">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit passport holder')
                                    <button type="button"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg text-indigo-500 bg-indigo-50 hover:bg-indigo-100 transition edit-item-btn"
                                            data-item_id="{{ $value->id }}"
                                            data-name="{{ $value->name }}"
                                            data-passport_no="{{ $value->passport_no }}"
                                            data-nationality="{{ $value->nationality }}"
                                            data-date_of_birth="{{ $value->date_of_birth }}"
                                            data-issue_date="{{ $value->issue_date }}"
                                            data-expiry_date="{{ $value->expiry_date }}"
                                            data-phone="{{ $value->phone }}"
                                            data-category_id="{{ $value->category_id }}"
                                            data-type="{{ $value->type ?: 'general' }}"
                                            data-status="{{ $value->status }}"
                                            title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                    @endcan
                                    @can('delete passport holder')
                                    <button type="button"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg text-red-500 bg-red-50 hover:bg-red-100 transition"
                                            onclick="confirmDelete('{{ $value->id }}', 'this item')"
                                            title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                            @endcanany
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                    </svg>
                                    <p class="text-sm font-medium">No passport holders found</p>
                                    <p class="text-xs text-gray-300">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100">
            <p class="text-sm text-gray-500">
                Showing
                <span class="font-semibold text-gray-700">{{ $datas->firstItem() ?? 0 }}</span>–<span class="font-semibold text-gray-700">{{ $datas->lastItem() ?? 0 }}</span>
                of
                <span class="font-semibold text-gray-700">{{ $datas->total() }}</span>
                records
            </p>
            <div>{{ $datas->appends(request()->all())->links() }}</div>
        </div>
    </div>
</div>

@include('passport-holder.create-modal')
@include('passport-holder.edit-modal')
@include('passport-holder.delete-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script>
        $(document).ready(function() {
            $('.select2').select2();

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            // Show create modal
            $('.create-new-btn').click(function() { showModal('#createModal'); });

            // Show edit modal
            $('.edit-item-btn').click(function() {
                $('#editItemId').val($(this).data('item_id'));
                $('#edit_name').val($(this).data('name'));
                $('#edit_passport_no').val($(this).data('passport_no'));
                $('#edit_nationality').val($(this).data('nationality'));
                $('#edit_phone').val($(this).data('phone'));
                $('#edit_date_of_birth').val($(this).data('date_of_birth'));
                $('#edit_issue_date').val($(this).data('issue_date'));
                $('#edit_expiry_date').val($(this).data('expiry_date'));
                $('#edit_category_id').val($(this).data('category_id')).trigger('change');
                $('#edit_type').val($(this).data('type') || 'general');
                $('#edit_status').prop('checked', $(this).data('status') == 1);
                showModal('#editModal');
            });

            // Close modals
            $(document).on('click', '.modal-close-create', function() { hideModal('#createModal'); });
            $(document).on('click', '.modal-close-edit',   function() { hideModal('#editModal'); });
            $(document).on('click', '.modal-close-delete', function() { hideModal('#deleteModal'); });
            $(document).on('click', '.modal-backdrop', function() {
                hideModal('#createModal'); hideModal('#editModal'); hideModal('#deleteModal');
            });

            // Create form submission
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (validateCreateForm()) {
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: new FormData($('#createForm')[0]),
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: 'Passport holder created!' });
                                hideModal('#createModal');
                                $('#createForm')[0].reset();
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                            }
                        },
                        error: function() {
                            Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create passport holder.' });
                        }
                    });
                }
            });

            // Edit form submission
            $('#editSubmit').click(function() {
                if (validateEditForm()) {
                    $.ajax({
                        url: $(this).data('action'),
                        method: 'POST',
                        data: new FormData($('#editForm')[0]),
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: 'Passport holder updated!' });
                                hideModal('#editModal');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                            }
                        },
                        error: function() {
                            Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                        }
                    });
                }
            });

            // Delete confirmation
            $('#confirmDeleteBtn').click(function() {
                $.ajax({
                    url: $(this).data('action'),
                    method: 'DELETE',
                    data: { item_id: $(this).data('item-id') },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Deleted successfully!' });
                            hideModal('#deleteModal');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });
        });

        function showModal(id) { $(id).removeClass('hidden').addClass('flex'); }
        function hideModal(id) { $(id).removeClass('flex').addClass('hidden'); }

        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-input').removeClass('border-red-500');

            if (!$('#create_name').val().trim()) {
                $('#create_name').addClass('border-red-500').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_passport_no').val().trim()) {
                $('#create_passport_no').addClass('border-red-500').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_nationality').val().trim()) {
                $('#create_nationality').addClass('border-red-500').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_phone').val().trim()) {
                $('#create_phone').addClass('border-red-500').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-input').removeClass('border-red-500');

            if (!$('#edit_name').val().trim()) {
                $('#edit_name').addClass('border-red-500').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            if (!$('#edit_passport_no').val().trim()) {
                $('#edit_passport_no').addClass('border-red-500').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            if (!$('#edit_nationality').val().trim()) {
                $('#edit_nationality').addClass('border-red-500').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            if (!$('#edit_phone').val().trim()) {
                $('#edit_phone').addClass('border-red-500').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            showModal('#deleteModal');
        }
    </script>
@endsection
