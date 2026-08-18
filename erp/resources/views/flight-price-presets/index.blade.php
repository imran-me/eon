@extends('layout.app')
@section('meta-information')<title>Flight Price Presets</title>@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .select2-container--open, .select2-dropdown { z-index: 99999; }
    .swal2-container { z-index: 100000 !important; }
</style>
@endsection
@section('main-content')
@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600 flex-shrink-0">
        <i class="fas fa-coins"></i>
    </span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Flight Price Presets</h1>
        <p class="text-xs text-slate-500">Airline, category, class and handling-wise automatic price builder</p>
    </div>
    <button type="button" class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i> Add Preset</button>
</header>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
 <form method="GET" action="{{ route('role.flight-price-presets.index', ['role'=>$role]) }}" class="flex items-center gap-3 bg-slate-50 px-4 py-3">
  <div class="flex flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
   <i class="fas fa-search text-xs text-slate-400"></i>
   <input name="search" value="{{ request('search') }}" placeholder="Search airline or category type..." class="flex-1 border-0 bg-transparent text-sm outline-none">
  </div>
  <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
  <a href="{{ route('role.flight-price-presets.index', ['role'=>$role]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
 </form>
 <div class="overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-200">
   <thead class="bg-slate-50">
    <tr>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Airline</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Category / Type</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Class</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Handling</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Cost Breakdown</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Sale Price</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
     <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Action</th>
    </tr>
   </thead>
   <tbody class="divide-y divide-slate-100">
    @forelse($datas as $data)
    <tr class="hover:bg-slate-50">
     <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $data->airline?->name }}</td>
     <td class="px-4 py-3">
      <div class="text-sm text-slate-800">{{ $data->flightCategory?->name ?? 'Any category' }}</div>
      <div class="text-xs text-slate-400">{{ $data->categoryType?->name ?? 'Any type' }}</div>
     </td>
     <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst($data->ticket_class) }}</td>
     <td class="px-4 py-3 text-sm text-slate-600">{{ ucwords(str_replace('_',' ',$data->handling_type)) }}</td>
     <td class="px-4 py-3 font-mono text-xs text-slate-500">T {{ number_format($data->ticket_cost) }} + M {{ number_format($data->manpower_cost) }} + B {{ number_format($data->boarding_cost) }} + I {{ number_format($data->immigration_cost) }}</td>
     <td class="px-4 py-3 font-mono text-sm font-bold text-slate-800">{{ number_format($data->sale_price) }}</td>
     <td class="px-4 py-3">
      @if($data->status === 'active')
       <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">Active</span>
      @else
       <span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-semibold text-red-700">Inactive</span>
      @endif
     </td>
     <td class="px-4 py-3">
      <div class="flex justify-end gap-1.5">
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 view-btn" data-json="{{ $data->toJson() }}" title="View"><i class="fas fa-eye text-xs"></i></button>
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn" data-json="{{ $data->toJson() }}" data-action="{{ route('role.flight-price-presets.update',['role'=>$role,'flight_price_preset'=>$data->id]) }}" title="Edit"><i class="fas fa-edit text-xs"></i></button>
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 delete-btn" data-id="{{ $data->id }}" data-name="{{ $data->airline?->name }}" data-action="{{ route('role.flight-price-presets.destroy',['role'=>$role,'flight_price_preset'=>$data->id]) }}" title="Delete"><i class="fas fa-trash text-xs"></i></button>
      </div>
     </td>
    </tr>
    @empty
    <tr>
     <td colspan="8" class="p-12 text-center">
      <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
      <p class="text-sm font-semibold text-slate-500">No price presets found</p>
      <p class="mt-1 text-xs text-slate-400">Add a preset to auto-calculate flight sale prices.</p>
     </td>
    </tr>
    @endforelse
   </tbody>
  </table>
 </div>
 <div class="p-4">{{ $datas->appends(request()->all())->links() }}</div>
</div>

@include('flight-price-presets.create-modal')
@include('flight-price-presets.edit-modal')
@include('flight-price-presets.delete-modal')
@include('flight-price-presets.view-modal')
@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $('#createPresetForm select').select2({
            dropdownParent: $('#createPresetModal'),
            width: '100%'
        });
        $('#editPresetForm select').select2({
            dropdownParent: $('#editPresetModal'),
            width: '100%'
        });
        $('.create-btn').on('click', function() {
            $('#createPresetForm')[0].reset();
            $('#createPresetForm .error-message').addClass('hidden');
            $('#createPresetForm select').trigger('change');
            $('#createPresetModal').removeClass('hidden')
        });
        $('.edit-btn').on('click', function() {
            const d = $(this).data('json');
            $('#editPresetForm .error-message').addClass('hidden');
            $('#editPresetForm').attr('action', $(this).data('action'));
            ['airline_id', 'flight_category_id', 'flight_category_type_id', 'ticket_class', 'handling_type', 'ticket_cost', 'manpower_cost', 'boarding_cost', 'immigration_cost', 'sale_price', 'status'].forEach(k => $('#edit_preset_' + k).val(d[k]));
            $('#editPresetForm select').trigger('change');
            $('#editPresetModal').removeClass('hidden')
        });
        $('.view-btn').on('click', function() {
            const d = $(this).data('json');
            $('#view_preset_airline').text(d.airline?.name || '-');
            $('#view_preset_category').text(d.flight_category?.name || 'Any category');
            $('#view_preset_type').text(d.category_type?.name || 'Any type');
            $('#view_preset_class').text((d.ticket_class || '').replace(/\b\w/g, c => c.toUpperCase()));
            $('#view_preset_handling').text((d.handling_type || '').replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase()));
            $('#view_preset_costs').text('T ' + Number(d.ticket_cost).toLocaleString() + ' + M ' + Number(d.manpower_cost).toLocaleString() + ' + B ' + Number(d.boarding_cost).toLocaleString() + ' + I ' + Number(d.immigration_cost).toLocaleString());
            $('#view_preset_sale').text('BDT ' + Number(d.sale_price).toLocaleString());
            $('#view_preset_status').text((d.status || '').replace(/\b\w/g, c => c.toUpperCase()));
            $('#viewPresetModal').removeClass('hidden')
        });
        $('.delete-btn').on('click', function() {
            $('#deletePresetName').text($(this).data('name'));
            $('#confirmDeletePreset').data('id', $(this).data('id')).data('action', $(this).data('action'));
            $('#deletePresetModal').removeClass('hidden')
        });
        $('.modal-close-create').on('click', () => $('#createPresetModal').addClass('hidden'));
        $('.modal-close-edit').on('click', () => $('#editPresetModal').addClass('hidden'));
        $('.modal-close-view').on('click', () => $('#viewPresetModal').addClass('hidden'));
        $('.modal-close-delete').on('click', () => $('#deletePresetModal').addClass('hidden'));
        $('#createPresetSubmit').on('click', function() {
            if (validatePresetForm('create')) {
                submitPreset($('#createPresetForm'), $(this), 'Preset created successfully.')
            }
        });
        $('#editPresetSubmit').on('click', function() {
            if (validatePresetForm('edit')) {
                submitPreset($('#editPresetForm'), $(this), 'Preset updated successfully.')
            }
        });
        $('#confirmDeletePreset').on('click', function() {
            const btn = $(this).prop('disabled', true);
            $.ajax({
                url: btn.data('action'),
                method: 'DELETE',
                data: {
                    item_id: btn.data('id')
                }
            }).done(handleSuccess).fail(handleError).always(() => btn.prop('disabled', false))
        });
    });

    function validatePresetForm(prefix) {
        let isValid = true;
        const form = prefix === 'create' ? $('#createPresetForm') : $('#editPresetForm');
        form.find('.error-message').addClass('hidden');

        if (!$('#' + prefix + '_preset_airline_id').val()) {
            $('#' + prefix + '_preset_airline_id_msg').removeClass('hidden');
            isValid = false;
        }

        ['ticket_cost', 'manpower_cost', 'boarding_cost', 'immigration_cost', 'sale_price'].forEach(function(field) {
            const input = $('#' + prefix + '_preset_' + field);
            if (input.val() === '' || Number(input.val()) < 0) {
                $('#' + prefix + '_preset_' + field + '_msg').removeClass('hidden');
                isValid = false;
            }
        });

        return isValid;
    }

    function submitPreset(form, button, message) {
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
        }).fail(handleError).always(() => button.prop('disabled', false))
    }

    function handleSuccess(r) {
        if (r.success) {
            Swal.fire('Done', r.message, 'success');
            setTimeout(() => location.reload(), 600)
        } else Swal.fire('Error', r.message, 'error')
    }

    function handleError(xhr) {
        Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong.', 'error')
    }
</script>
@endsection
