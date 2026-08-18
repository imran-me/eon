@extends('layout.app')
@section('meta-information')
    <title>Flight Categories</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--open, .select2-dropdown { z-index: 99999; }
    .swal2-container { z-index: 100000 !important; }
    span [aria-current="page"] span { background-color:#2563eb !important; color:white; border-color:#2563eb; }
</style>
@endsection
@section('main-content')

<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600 flex-shrink-0">
        <i class="fas fa-folder"></i>
    </span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Flight Category</h1>
        <p class="text-xs text-slate-500">Contract flight types — seat config, base fare & airline tie-ups per category</p>
    </div>
    <button class="create-btn rounded-lg bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i> Add Category</button>
</header>

<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="rounded-xl bg-blue-50 p-4">
        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white mb-3 shadow-md"><i class="fas fa-folder"></i></div>
        <b class="block text-2xl font-bold text-blue-900">{{ $stats['total_categories'] }}</b>
        <p class="text-[10px] font-bold uppercase text-blue-700 mt-1">Categories</p>
        <p class="text-xs text-slate-500 mt-1">Active types</p>
    </div>
    <div class="rounded-xl bg-emerald-50 p-4">
        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white mb-3 shadow-md"><i class="fas fa-kaaba"></i></div>
        <b class="block text-2xl font-bold text-emerald-900">{{ optional($stats['top_category'])->name ?? '—' }}</b>
        <p class="text-[10px] font-bold uppercase text-emerald-700 mt-1">Top Category</p>
        <p class="text-xs text-slate-500 mt-1">{{ optional($stats['top_category'])->default_seats ?? 0 }} seats configured</p>
    </div>
    <div class="rounded-xl bg-amber-50 p-4">
        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white mb-3 shadow-md"><i class="fas fa-plane"></i></div>
        <b class="block text-2xl font-bold text-amber-900">{{ $stats['avg_seats'] }}</b>
        <p class="text-[10px] font-bold uppercase text-amber-700 mt-1">Avg Seats</p>
        <p class="text-xs text-slate-500 mt-1">All categories</p>
    </div>
    <div class="rounded-xl bg-violet-50 p-4">
        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-violet-400 to-violet-600 flex items-center justify-center text-white mb-3 shadow-md"><i class="fas fa-handshake"></i></div>
        <b class="block text-2xl font-bold text-violet-900">{{ $stats['airline_partners'] }}</b>
        <p class="text-[10px] font-bold uppercase text-violet-700 mt-1">Airline Partners</p>
        <p class="text-xs text-slate-500 mt-1">{{ $stats['partner_names']->implode(', ') ?: '—' }}{{ $stats['airline_partners'] > 3 ? '…' : '' }}</p>
    </div>
</section>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
 <form method="GET" action="{{ route('role.flight-categories.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="flex items-center gap-3 bg-slate-50 px-4 py-3">
  <div class="flex flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
   <i class="fas fa-search text-xs text-slate-400"></i>
   <input type="text" name="search" value="{{ request('search') }}" placeholder="Search category, route, airline..." class="flex-1 border-0 outline-none text-sm bg-transparent">
  </div>
  <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
  <a href="{{ route('role.flight-categories.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
 </form>
 <div class="overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-200">
   <thead class="bg-slate-50">
    <tr>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Category</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Category Type</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Code</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Typical Route(s)</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Default Seats</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Base Fare (৳)</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Airlines</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
     <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Action</th>
    </tr>
   </thead>
   <tbody class="divide-y divide-slate-100">
    @forelse ($datas as $value)
    <tr class="hover:bg-slate-50">
     <td class="px-4 py-3">
      <div class="flex items-center gap-2 text-sm font-semibold text-slate-900"><i class="fas {{ $value->icon ?? 'fa-folder' }} text-blue-500 w-4 text-center"></i>{{ $value->name }}</div>
     </td>
     <td class="px-4 py-3 text-xs text-slate-600">{{ $value->categoryType?->name ?? '-' }}</td>
     <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $value->code ?? '—' }}</td>
     <td class="px-4 py-3 text-xs text-slate-600">{{ $value->typical_routes ?? '—' }}</td>
     <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $value->default_seats }}</td>
     <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-800">{{ number_format($value->base_fare, 0) }}</td>
     <td class="px-4 py-3 text-xs text-slate-600">{{ $value->airlines->pluck('name')->implode(', ') ?: '—' }}</td>
     <td class="px-4 py-3">
      @if($value->status === 'active')
       <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">Active</span>
      @elseif($value->status === 'seasonal')
       <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-semibold text-amber-700">Seasonal</span>
      @else
       <span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-semibold text-red-700">Inactive</span>
      @endif
     </td>
     <td class="px-4 py-3">
      <div class="flex justify-end gap-1.5">
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600" onclick="viewCategory({{ $value->id }})" title="View"><i class="fas fa-eye text-xs"></i></button>
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn"
        data-item_id="{{ $value->id }}"
        data-action="{{ route('role.flight-categories.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'flight_category' => $value->id]) }}"
        data-name="{{ $value->name }}"
        data-flight_category_type_id="{{ $value->flight_category_type_id }}"
        data-code="{{ $value->code }}"
        data-icon="{{ $value->icon }}"
        data-typical_routes="{{ $value->typical_routes }}"
        data-default_seats="{{ $value->default_seats }}"
        data-base_fare="{{ $value->base_fare }}"
        data-status="{{ $value->status }}"
        data-airline_ids="{{ $value->airlines->pluck('id')->implode(',') }}"
        title="Edit"><i class="fas fa-edit text-xs"></i></button>
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100" onclick="confirmDelete('{{ $value->id }}', '{{ addslashes($value->name) }}')" title="Delete"><i class="fas fa-trash text-xs"></i></button>
      </div>
     </td>
    </tr>
    @empty
    <tr>
     <td colspan="9" class="p-12 text-center">
      <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
      <p class="text-sm font-semibold text-slate-500">No flight categories found</p>
      <p class="mt-1 text-xs text-slate-400">Add a new flight category to get started.</p>
     </td>
    </tr>
    @endforelse
   </tbody>
  </table>
 </div>
 <div class="p-4">{{ $datas->appends(request()->all())->links() }}</div>
</div>

@include('flight-categories.create-modal')
@include('flight-categories.edit-modal')
@include('flight-categories.delete-modal')
@include('flight-categories.view-modal')

<div id="quickTypeModal" class="fixed inset-0 z-[9200] bg-black/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto hidden modal-backdrop">
    <div class="w-full max-w-xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-br from-blue-600 to-blue-700 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-base flex-shrink-0"><i class="fas fa-tag"></i></div>
            <div>
                <div class="text-sm font-bold text-white">Add Category Type</div>
                <div class="text-xs text-blue-100 mt-0.5">Create and select a new flight type</div>
            </div>
            <button type="button" class="ml-auto h-8 w-8 rounded-xl bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center quick-type-close">x</button>
        </div>
        <div class="p-5 max-h-[calc(100vh-220px)] overflow-y-auto">
            <form id="quickTypeForm" action="{{ route('role.flight-category-types.store',['role'=>Str::slug(Auth::user()->getRoleNames()->first())]) }}">@csrf
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-full"><label class="block text-xs font-semibold text-slate-600 mb-1">Type Name <sup>*</sup></label><input id="quick_type_name" name="name" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none" placeholder="e.g. Turkish - 2 PNR Ticket"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Code</label><input name="code" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none" placeholder="TR-2PNR"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Base Fare</label><input type="number" name="base_fare" min="0" step="0.01" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none"></div>
                    <input type="hidden" name="status" value="active">
                </div>
            </form>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
            <button type="button" class="rounded-xl border bg-slate-100 text-slate-700 px-4 py-2 text-sm font-semibold cursor-pointer inline-flex items-center gap-1.5 quick-type-close">Cancel</button>
            <button id="quickTypeSubmit" type="button" class="rounded-xl bg-blue-600 text-white px-4 py-2 text-sm font-semibold cursor-pointer inline-flex items-center gap-1.5"><i class="fas fa-save"></i> Save Type</button>
        </div>
    </div>
</div>

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const fcCategories = @json($datas->items());

        $(document).ready(function() {
            $('#create_airline_ids').select2({ dropdownParent: $('#createModal'), placeholder: 'Select airlines...' });
            $('#edit_airline_ids').select2({ dropdownParent: $('#editModal'), placeholder: 'Select airlines...' });

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Show create modal
            $('.create-btn').click(function() {
                $('#createForm')[0].reset();
                $('#createForm .error-message').addClass('hidden');
                $('#create_airline_ids').val(null).trigger('change');
                $('#create_status').val('active');
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-btn').click(function() {
                $('#editItemId').val($(this).data('item_id'));
                $('#edit_name').val($(this).data('name'));
                $('#edit_flight_category_type_id').val($(this).data('flight_category_type_id'));
                $('#edit_code').val($(this).data('code'));
                $('#edit_icon').val($(this).data('icon'));
                $('#edit_typical_routes').val($(this).data('typical_routes'));
                $('#edit_default_seats').val($(this).data('default_seats'));
                $('#edit_base_fare').val($(this).data('base_fare'));
                $('#edit_status').val($(this).data('status'));
                const airlineIds = ($(this).data('airline_ids') || '').toString().split(',').filter(Boolean);
                $('#edit_airline_ids').val(airlineIds).trigger('change');
                $('#editeForm .error-message').addClass('hidden');
                $('#editSubmit').data('action', $(this).data('action'));
                $('#editModal').removeClass('hidden');
            });

            let quickTypeTarget = null;
            $('.quick-type-btn').click(function() {
                quickTypeTarget = $(this).closest('.fcm-full').find('.category-type-select').attr('id');
                $('#quickTypeForm')[0].reset();
                $('#quickTypeModal').removeClass('hidden');
            });
            $('.quick-type-close').click(function() { $('#quickTypeModal').addClass('hidden'); });
            $('#quickTypeSubmit').click(function() {
                if (!$('#quick_type_name').val().trim()) {
                    Swal.fire({icon:'warning', title:'Missing', text:'Type name is required.'}); return;
                }
                $.post($('#quickTypeForm').attr('action'), $('#quickTypeForm').serialize()).done(function(response) {
                    if (!response.success) { Swal.fire({icon:'error', title:'Error', text:response.message}); return; }
                    const type = response.data;
                    $('.category-type-select').each(function() {
                        if (!$(this).find(`option[value="${type.id}"]`).length) $(this).append(new Option(type.name, type.id));
                    });
                    if (quickTypeTarget) $('#' + quickTypeTarget).val(type.id);
                    $('#quickTypeModal').addClass('hidden');
                    Swal.fire({icon:'success', title:'Added', text:response.message, timer:1200, showConfirmButton:false});
                });
            });
            $('.category-type-select').change(function() {
                const fare = $(this).find(':selected').data('fare');
                const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
                if (fare !== undefined && Number($('#' + prefix + '_base_fare').val() || 0) === 0) $('#' + prefix + '_base_fare').val(fare);
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
            $('.modal-close-view, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-view').length) {
                    $('#viewModal').addClass('hidden');
                }
            });

            // Create submit
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (validateCreateForm()) {
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: $('#createForm').serialize(),
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: 'Flight category created successfully!' });
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr.responseText);
                            Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create flight category.' });
                        }
                    });
                }
            });

            // Edit submit
            $('#editSubmit').click(function() {
                if (validateEditForm()) {
                    $.ajax({
                        url: $(this).data('action'),
                        method: 'POST',
                        data: $('#editeForm').serialize() + '&_method=PUT',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: 'Flight category updated successfully!' });
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

            // Delete confirmation
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('item-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: { item_id: dataId },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Flight category deleted successfully!' });
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
        });

        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-input').removeClass('border-red-500');
            if (!$('#create_name').val().trim()) {
                $('#create_name_msg').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editeForm .error-message').addClass('hidden');
            $('#editeForm .form-input').removeClass('border-red-500');
            if (!$('#edit_name').val().trim()) {
                $('#edit_name_msg').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            const role = '{{ Str::slug(Auth::user()->getRoleNames()->first()) }}';
            $('#confirmDeleteBtn').data('action', `/${role}/flight-categories/${id}`);
            $('#deleteModal').removeClass('hidden');
        }

        function viewCategory(id) {
            const cat = fcCategories.find(c => c.id === id);
            if (!cat) return;
            $('#view_name').text(cat.name);
            $('#view_type').text(cat.category_type ? cat.category_type.name : '-');
            $('#view_code').text(cat.code || '—');
            $('#view_routes').text(cat.typical_routes || '—');
            $('#view_seats').text(cat.default_seats);
            $('#view_fare').text('৳ ' + Number(cat.base_fare).toLocaleString());
            $('#view_airlines').text((cat.airlines || []).map(a => a.name).join(', ') || '—');
            var _sc = cat.status === 'active' ? 'bg-emerald-100 text-emerald-700' : cat.status === 'seasonal' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700';
            $('#view_status').html('<span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold ' + _sc + '">' + (cat.status.charAt(0).toUpperCase() + cat.status.slice(1)) + '</span>');
            $('#viewModal').removeClass('hidden');
        }
    </script>
@endsection
