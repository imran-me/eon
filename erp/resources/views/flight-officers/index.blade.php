@extends('layout.app')
@section('meta-information')<title>Flight Officers</title>@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .select2-container--open, .select2-dropdown { z-index: 99999; }
</style>
@endsection
@section('main-content')
@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp

<header class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
 <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 text-xl"><i class="fas fa-user-shield"></i></span>
 <div class="flex-1">
  <h1 class="text-lg font-bold text-slate-900">Flight Officers</h1>
  <p class="text-xs text-slate-500">Boarding and immigration profiles linked to existing users</p>
 </div>
 <button type="button" class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i>Add Officer</button>
</header>

<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
 <div class="rounded-xl bg-blue-50 p-4">
  <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 text-white text-xl shadow-md"><i class="fas fa-user-shield"></i></div>
  <div class="text-3xl font-black leading-none text-blue-900 mb-1">{{ $stats['total'] }}</div>
  <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Total Officers</div>
  <div class="mt-1 text-xs text-slate-500">All officers</div>
 </div>
 <div class="rounded-xl bg-emerald-50 p-4">
  <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white text-xl shadow-md"><i class="fas fa-plane-departure"></i></div>
  <div class="text-3xl font-black leading-none text-emerald-900 mb-1">{{ $stats['boarding'] }}</div>
  <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Boarding</div>
  <div class="mt-1 text-xs text-slate-500">Officers</div>
 </div>
 <div class="rounded-xl bg-amber-50 p-4">
  <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white text-xl shadow-md"><i class="fas fa-passport"></i></div>
  <div class="text-3xl font-black leading-none text-amber-900 mb-1">{{ $stats['immigration'] }}</div>
  <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Immigration</div>
  <div class="mt-1 text-xs text-slate-500">Officers</div>
 </div>
 <div class="rounded-xl bg-violet-50 p-4">
  <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-violet-400 to-violet-700 text-white text-xl shadow-md"><i class="fas fa-circle-check"></i></div>
  <div class="text-3xl font-black leading-none text-violet-900 mb-1">{{ $stats['active'] }}</div>
  <div class="text-[10px] font-bold uppercase tracking-wide text-violet-700">Active</div>
  <div class="mt-1 text-xs text-slate-500">Enabled</div>
 </div>
</section>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
 <form method="GET" action="{{ route('role.flight-officers.index',['role'=>$role]) }}" class="flex items-center gap-3 bg-slate-50 px-4 py-3">
  <div class="flex flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
   <i class="fas fa-search text-xs text-slate-400"></i>
   <input name="search" value="{{ request('search') }}" placeholder="Search officer name or phone..." class="flex-1 border-0 outline-none text-sm bg-transparent">
  </div>
  <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
  <a href="{{ route('role.flight-officers.index',['role'=>$role]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
 </form>
 <div class="overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-200">
   <thead class="bg-slate-50">
    <tr>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Officer</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Airline / Desk</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Work Roles</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Contact</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Experience</th>
     <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
     <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Action</th>
    </tr>
   </thead>
   <tbody class="divide-y divide-slate-100">
    @forelse($datas as $data)
    <tr class="hover:bg-slate-50">
     <td class="px-4 py-3">
      <b class="block text-xs font-semibold text-slate-800">{{ $data->user?->name }}</b>
      <span class="text-[10px] text-slate-400">{{ $data->user?->phone }}</span>
     </td>
     <td class="px-4 py-3 text-xs text-slate-600">{{ $data->airline?->name ?? 'All / General' }}</td>
     <td class="px-4 py-3">@foreach($data->work_roles ?? [] as $roleName)<span class="mr-1 inline-block rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">{{ ucfirst($roleName) }}</span>@endforeach</td>
     <td class="px-4 py-3 text-xs text-slate-600">{{ $data->contact ?: $data->user?->phone }}</td>
     <td class="px-4 py-3 text-xs text-slate-600">{{ $data->experience ?: '-' }}</td>
     <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $data->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($data->status) }}</span></td>
     <td class="px-4 py-3">
      <div class="flex justify-end gap-1.5">
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 view-btn" data-json="{{ $data->toJson() }}" title="View"><i class="fas fa-eye text-xs"></i></button>
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn" data-json="{{ $data->toJson() }}" data-action="{{ route('role.flight-officers.update',['role'=>$role,'flight_officer'=>$data->id]) }}" title="Edit"><i class="fas fa-edit text-xs"></i></button>
       <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 delete-btn" data-id="{{ $data->id }}" data-name="{{ $data->user?->name }}" data-action="{{ route('role.flight-officers.destroy',['role'=>$role,'flight_officer'=>$data->id]) }}" title="Delete"><i class="fas fa-trash text-xs"></i></button>
      </div>
     </td>
    </tr>
    @empty
    <tr>
     <td colspan="7" class="p-12 text-center">
      <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
      <p class="text-sm font-semibold text-slate-500">No officer profiles found</p>
      <p class="mt-1 text-xs text-slate-400">Add a new flight officer to get started.</p>
     </td>
    </tr>
    @endforelse
   </tbody>
  </table>
 </div>
 <div class="p-4">{{ $datas->appends(request()->all())->links() }}</div>
</div>

@include('flight-officers.create-modal')
@include('flight-officers.edit-modal')
@include('flight-officers.delete-modal')
@include('flight-officers.view-modal')
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
        $('#create_officer_user_id,#create_officer_airline_id').select2({
            dropdownParent: $('#createOfficerModal'),
            width: '100%'
        });
        $('#edit_officer_user_id,#edit_officer_airline_id').select2({
            dropdownParent: $('#editOfficerModal'),
            width: '100%'
        });
        $('.create-btn').on('click', function() {
            $('#createOfficerForm')[0].reset();
            $('#createOfficerForm .error-message').addClass('hidden');
            $('#create_officer_user_id,#create_officer_airline_id').val(null).trigger('change');
            $('#createOfficerModal').removeClass('hidden')
        });
        $('.edit-btn').on('click', function() {
            const d = $(this).data('json');
            $('#editOfficerForm .error-message').addClass('hidden');
            $('#editOfficerForm').attr('action', $(this).data('action'));
            $('#edit_officer_user_id').val(d.user_id).trigger('change');
            $('#edit_officer_airline_id').val(d.airline_id).trigger('change');
            $('#edit_officer_contact').val(d.contact);
            $('#edit_officer_experience').val(d.experience);
            $('#edit_officer_status').val(d.status);
            $('#editOfficerForm input[name="work_roles[]"]').prop('checked', false);
            (d.work_roles || []).forEach(r => $('#editOfficerForm input[value="' + r + '"]').prop('checked', true));
            $('#editOfficerModal').removeClass('hidden')
        });
        $('.view-btn').on('click', function() {
            const d = $(this).data('json');
            $('#view_officer_name').text(d.user?.name || '-');
            $('#view_officer_airline').text(d.airline?.name || 'All / General');
            $('#view_officer_roles').text((d.work_roles || []).map(r => r.replace(/\b\w/g, c => c.toUpperCase())).join(', '));
            $('#view_officer_contact').text(d.contact || d.user?.phone || '-');
            $('#view_officer_experience').text(d.experience || '-');
            $('#view_officer_status').text((d.status || '').replace(/\b\w/g, c => c.toUpperCase()));
            $('#viewOfficerModal').removeClass('hidden')
        });
        $('.delete-btn').on('click', function() {
            $('#deleteOfficerName').text($(this).data('name'));
            $('#confirmDeleteOfficer').data('id', $(this).data('id')).data('action', $(this).data('action'));
            $('#deleteOfficerModal').removeClass('hidden')
        });
        $('.modal-close-create').on('click', () => $('#createOfficerModal').addClass('hidden'));
        $('.modal-close-edit').on('click', () => $('#editOfficerModal').addClass('hidden'));
        $('.modal-close-view').on('click', () => $('#viewOfficerModal').addClass('hidden'));
        $('.modal-close-delete').on('click', () => $('#deleteOfficerModal').addClass('hidden'));
        $('#createOfficerSubmit').on('click', function() {
            if (validateOfficerForm('create')) {
                submitOfficer($('#createOfficerForm'), $(this), 'Officer profile created successfully.')
            }
        });
        $('#editOfficerSubmit').on('click', function() {
            if (validateOfficerForm('edit')) {
                submitOfficer($('#editOfficerForm'), $(this), 'Officer profile updated successfully.')
            }
        });
        $('#confirmDeleteOfficer').on('click', function() {
            const btn = $(this).prop('disabled', true);
            $.ajax({
                url: btn.data('action'),
                method: 'DELETE',
                data: {
                    item_id: btn.data('id')
                }
            }).done(handleOfficerSuccess).fail(handleOfficerError).always(() => btn.prop('disabled', false))
        });
    });

    function validateOfficerForm(prefix) {
        let isValid = true;
        const form = prefix === 'create' ? $('#createOfficerForm') : $('#editOfficerForm');
        form.find('.error-message').addClass('hidden');

        if (!$('#' + prefix + '_officer_user_id').val()) {
            $('#' + prefix + '_officer_user_id_msg').removeClass('hidden');
            isValid = false;
        }

        if (!form.find('input[name="work_roles[]"]:checked').length) {
            $('#' + prefix + '_officer_work_roles_msg').removeClass('hidden');
            isValid = false;
        }

        return isValid;
    }

    function submitOfficer(form, button, message) {
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
        }).fail(handleOfficerError).always(() => button.prop('disabled', false))
    }

    function handleOfficerSuccess(r) {
        if (r.success) {
            Swal.fire('Done', r.message, 'success');
            setTimeout(() => location.reload(), 600)
        } else Swal.fire('Error', r.message, 'error')
    }

    function handleOfficerError(xhr) {
        Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong.', 'error')
    }
</script>
@endsection
