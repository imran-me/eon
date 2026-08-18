@extends('layout.app')
@section('meta-information')<title>Flight Category Types</title>@endsection
@section('css')@endsection
@section('main-content')
@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp

<header class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
 <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 text-xl"><i class="fas fa-tags"></i></span>
 <div class="flex-1">
  <h1 class="text-lg font-bold text-slate-900">Flight Category Types</h1>
  <p class="text-xs text-slate-500">Reusable airline and ticket combinations used by flight categories</p>
 </div>
 <button type="button" class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i>Add Type</button>
</header>

<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
 <div class="rounded-xl bg-blue-50 p-4">
  <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 text-white text-xl shadow-md"><i class="fas fa-tags"></i></div>
  <div class="text-3xl font-black leading-none text-blue-900 mb-1">{{ $stats['total'] }}</div>
  <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Total Types</div>
  <div class="mt-1 text-xs text-slate-500">All types</div>
 </div>
 <div class="rounded-xl bg-emerald-50 p-4">
  <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white text-xl shadow-md"><i class="fas fa-circle-check"></i></div>
  <div class="text-3xl font-black leading-none text-emerald-900 mb-1">{{ $stats['active'] }}</div>
  <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Active</div>
  <div class="mt-1 text-xs text-slate-500">Enabled</div>
 </div>
 <div class="rounded-xl bg-amber-50 p-4">
  <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white text-xl shadow-md"><i class="fas fa-coins"></i></div>
  <div class="text-3xl font-black leading-none text-amber-900 mb-1">BDT {{ number_format($stats['avg_fare'],0) }}</div>
  <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Average Fare</div>
  <div class="mt-1 text-xs text-slate-500">Per ticket</div>
 </div>
 <div class="rounded-xl bg-violet-50 p-4">
  <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-violet-400 to-violet-700 text-white text-xl shadow-md"><i class="fas fa-diagram-project"></i></div>
  <div class="text-3xl font-black leading-none text-violet-900 mb-1">{{ $stats['used'] }}</div>
  <div class="text-[10px] font-bold uppercase tracking-wide text-violet-700">Types In Use</div>
  <div class="mt-1 text-xs text-slate-500">In categories</div>
 </div>
</section>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
 <form method="GET" action="{{ route('role.flight-category-types.index',['role'=>$role]) }}" class="flex items-center gap-3 bg-slate-50 px-4 py-3">
  <div class="flex flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
   <i class="fas fa-search text-xs text-slate-400"></i>
   <input name="search" value="{{ request('search') }}" placeholder="Search type name or code..." class="flex-1 border-0 outline-none text-sm bg-transparent">
  </div>
  <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
  <a href="{{ route('role.flight-category-types.index',['role'=>$role]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
 </form>
 <div class="overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-200">
   <thead class="bg-slate-50">
    <tr>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Type Name</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Code</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Base Fare</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Categories</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
     <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Action</th>
    </tr>
   </thead>
   <tbody class="divide-y divide-slate-100">
    @forelse($datas as $type)
    <tr class="hover:bg-slate-50">
     <td class="px-4 py-3"><span class="flex items-center gap-2 text-sm font-semibold text-slate-800"><i class="fas fa-plane text-blue-400"></i>{{ $type->name }}</span></td>
     <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $type->code ?: '-' }}</td>
     <td class="px-4 py-3 text-xs font-bold text-slate-800">BDT {{ number_format($type->base_fare,0) }}</td>
     <td class="px-4 py-3"><span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700">{{ $type->flight_categories_count }}</span></td>
     <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $type->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($type->status) }}</span></td>
     <td class="px-4 py-3">
      <div class="flex justify-end gap-1.5">
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 view-btn" data-json="{{ $type->toJson() }}" title="View"><i class="fas fa-eye text-xs"></i></button>
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn" data-json="{{ $type->toJson() }}" data-action="{{ route('role.flight-category-types.update',['role'=>$role,'flight_category_type'=>$type->id]) }}" title="Edit"><i class="fas fa-edit text-xs"></i></button>
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 delete-btn" data-id="{{ $type->id }}" data-name="{{ $type->name }}" data-action="{{ route('role.flight-category-types.destroy',['role'=>$role,'flight_category_type'=>$type->id]) }}" title="Delete"><i class="fas fa-trash text-xs"></i></button>
      </div>
     </td>
    </tr>
    @empty
    <tr>
     <td colspan="6" class="p-12 text-center">
      <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
      <p class="text-sm font-semibold text-slate-500">No category types found</p>
      <p class="mt-1 text-xs text-slate-400">Add a new flight category type to get started.</p>
     </td>
    </tr>
    @endforelse
   </tbody>
  </table>
 </div>
 <div class="p-4">{{ $datas->appends(request()->all())->links() }}</div>
</div>

@include('flight-category-types.create-modal')
@include('flight-category-types.edit-modal')
@include('flight-category-types.delete-modal')
@include('flight-category-types.view-modal')
@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $('.create-btn').on('click', function() {
            $('#createTypeForm')[0].reset();
            $('#createTypeForm .error-message').addClass('hidden');
            $('#createTypeModal').removeClass('hidden')
        });
        $('.edit-btn').on('click', function() {
            const d = $(this).data('json');
            $('#editTypeForm .error-message').addClass('hidden');
            $('#editTypeForm').attr('action', $(this).data('action'));
            $('#edit_type_name').val(d.name);
            $('#edit_type_code').val(d.code);
            $('#edit_type_base_fare').val(d.base_fare);
            $('#edit_type_status').val(d.status);
            $('#editTypeModal').removeClass('hidden')
        });
        $('.view-btn').on('click', function() {
            const d = $(this).data('json');
            $('#view_type_name').text(d.name || '-');
            $('#view_type_code').text(d.code || '-');
            $('#view_type_fare').text('BDT ' + Number(d.base_fare || 0).toLocaleString());
            $('#view_type_status').text((d.status || '').replace(/\b\w/g, c => c.toUpperCase()));
            $('#view_type_categories').text(d.flight_categories_count || 0);
            $('#viewTypeModal').removeClass('hidden')
        });
        $('.delete-btn').on('click', function() {
            $('#deleteTypeName').text($(this).data('name'));
            $('#confirmDeleteType').data('id', $(this).data('id')).data('action', $(this).data('action'));
            $('#deleteTypeModal').removeClass('hidden')
        });
        $('.modal-close-create').on('click', () => $('#createTypeModal').addClass('hidden'));
        $('.modal-close-edit').on('click', () => $('#editTypeModal').addClass('hidden'));
        $('.modal-close-view').on('click', () => $('#viewTypeModal').addClass('hidden'));
        $('.modal-close-delete').on('click', () => $('#deleteTypeModal').addClass('hidden'));
        $('#createTypeSubmit').on('click', function() {
            if (validateTypeForm('create')) {
                submitType($('#createTypeForm'), $(this), 'Category type created successfully.')
            }
        });
        $('#editTypeSubmit').on('click', function() {
            if (validateTypeForm('edit')) {
                submitType($('#editTypeForm'), $(this), 'Category type updated successfully.')
            }
        });
        $('#confirmDeleteType').on('click', function() {
            const btn = $(this).prop('disabled', true);
            $.ajax({
                url: btn.data('action'),
                method: 'DELETE',
                data: {
                    item_id: btn.data('id')
                }
            }).done(handleTypeSuccess).fail(handleTypeError).always(() => btn.prop('disabled', false))
        });
    });

    function validateTypeForm(prefix) {
        let isValid = true;
        const form = prefix === 'create' ? $('#createTypeForm') : $('#editTypeForm');
        form.find('.error-message').addClass('hidden');

        if (!$('#' + prefix + '_type_name').val().trim()) {
            $('#' + prefix + '_type_name_msg').removeClass('hidden');
            isValid = false;
        }

        const baseFare = Number($('#' + prefix + '_type_base_fare').val() || 0);
        if (baseFare < 0) {
            $('#' + prefix + '_type_base_fare_msg').removeClass('hidden');
            isValid = false;
        }

        return isValid;
    }

    function submitType(form, button, message) {
        button.prop('disabled', true);
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize()
        }).done(function(r) {
            if (!r.success) {
                Swal.fire('Error', r.message, 'error');
                return
            }
            Swal.fire('Done', message, 'success');
            setTimeout(() => location.reload(), 600)
        }).fail(handleTypeError).always(() => button.prop('disabled', false))
    }

    function handleTypeSuccess(r) {
        if (r.success) {
            Swal.fire('Done', r.message, 'success');
            setTimeout(() => location.reload(), 600)
        } else Swal.fire('Error', r.message, 'error')
    }

    function handleTypeError(xhr) {
        Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong.', 'error')
    }
</script>
@endsection
