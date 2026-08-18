@extends('layout.app')
@section('meta-information')
<title>Contract File Categories</title>
@endsection
@section('css')
@endsection

@section('main-content')
@php($role = Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first()))
@include('contract-file-categories.content')
@include('contract-file-categories.create-modal')
@include('contract-file-categories.edit-modal')
@include('contract-file-categories.delete-modal')
@include('contract-file-categories.view-modal')
@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const fileCategories = @json($datas->items());
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $('.create-btn').click(function() {
            $('#createForm')[0].reset();
            $('#createForm .error-message').addClass('hidden');
            $('#create_status').val('active');
            $('#createModal').removeClass('hidden');
        });
        if (new URLSearchParams(window.location.search).get('open') === 'create') {
            $('.create-btn').first().trigger('click');
        }
        $('.edit-btn').click(function() {
            $('#editItemId').val($(this).data('item_id'));
            $('#edit_name').val($(this).data('name'));
            $('#edit_country_id').val($(this).data('country_id'));
            $('#edit_visa_rate').val($(this).data('visa_rate'));
            $('#edit_required_documents').val($(this).data('required_documents'));
            $('#edit_status').val($(this).data('status'));
            $('#editeForm .error-message').addClass('hidden');
            $('#editSubmit').data('action', $(this).data('action'));
            $('#editModal').removeClass('hidden');
        });
        $('.modal-close-create,.modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) $('#createModal').addClass('hidden');
        });
        $('.modal-close-edit,.modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-edit').length || $(e.target).hasClass('modal-backdrop')) $('#editModal').addClass('hidden');
        });
        $('.modal-close-delete,.modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-delete').length || $(e.target).hasClass('modal-backdrop')) $('#deleteModal').addClass('hidden');
        });
        $('.modal-close-view,.modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-view').length || $(e.target).hasClass('modal-backdrop')) $('#viewModal').addClass('hidden');
        });
        $('#createSubmit').click(function(e) {
            e.preventDefault();
            submitForm('#createForm', $('#createForm').attr('action'), 'POST', 'File category created successfully!', '#createModal');
        });
        $('#editSubmit').click(function() {
            submitForm('#editeForm', $(this).data('action'), 'POST', 'File category updated successfully!', '#editModal', '&_method=PUT');
        });
        $('#confirmDeleteBtn').click(function() {
            $.ajax({
                url: $(this).data('action'),
                method: 'DELETE',
                data: {
                    item_id: $(this).data('item-id')
                },
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Done',
                            text: 'File category deleted successfully!'
                        });
                        $('#deleteModal').addClass('hidden');
                        setTimeout(() => location.reload(), 500);
                    } else Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: res.message
                    });
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Something went wrong!'
                    });
                }
            });
        });
    });

    function submitForm(form, url, method, msg, modal, extra = '') {
        if (!validateCategoryForm(form)) return;
        $.ajax({
            url: url,
            method: method,
            data: $(form).serialize() + extra,
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Done',
                        text: msg
                    });
                    $(modal).addClass('hidden');
                    setTimeout(() => location.reload(), 800);
                } else Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: res.message || 'Something went wrong.'
                });
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Request failed.'
                });
            }
        });
    }

    function validateCategoryForm(form) {
        let ok = true;
        $(form + ' .error-message').addClass('hidden');
        ['name', 'country_id', 'visa_rate'].forEach(function(name) {
            const field = $(form + ' [name="' + name + '"]');
            if (!field.val()) {
                $('#' + field.attr('id') + '_msg').removeClass('hidden');
                ok = false;
            }
        });
        return ok;
    }

    function confirmDelete(id, name) {
        $('#deleteName').text(name);
        $('#confirmDeleteBtn').data('item-id', id).data('action', `/{{ $role }}/contract-file-categories/${id}`);
        $('#deleteModal').removeClass('hidden');
    }

    function viewCategory(id) {
        const item = fileCategories.find(x => x.id === id);
        if (!item) return;
        $('#view_name').text(item.name);
        $('#view_code').text(item.code || '-');
        $('#view_country').text(item.country ? item.country.name : '-');
        $('#view_visa_rate').text('BDT ' + Number(item.visa_rate).toLocaleString());
        $('#view_docs_count').text((item.documents_count || 0) + ' docs');
        $('#view_documents').text((item.documents_list || []).join(', ') || '-');
        const statusClass = item.status === 'active'
            ? 'bg-emerald-100 text-emerald-700'
            : 'bg-slate-100 text-slate-600';
        $('#view_status').html(`<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${statusClass}">${item.status.charAt(0).toUpperCase()+item.status.slice(1)}</span>`);
        $('#viewModal').removeClass('hidden');
    }
</script>
@endsection
