@extends('layout.app')
@section('meta-information')
    <title>Manage Vouchers</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    .voucher-table-input { border: 1px solid #e2e8f0; padding: 0.5rem; border-radius: 0.375rem; width: 100%; }
    .voucher-table-input:focus { outline: none; border-color: #3b82f6; ring: 2px #3b82f6; }
</style>
@endsection

@section('main-content')
<div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
    <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center text-white">
        <h2 class="text-xl font-semibold"><i class="fas fa-file-invoice-dollar mr-2"></i>Voucher List</h2>
        <button class="create-new-btn bg-blue-500 hover:bg-blue-700 px-4 py-2 rounded-md transition">
            <i class="fas fa-plus mr-2"></i>Create New Voucher
        </button>
    </div>

    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voucher No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($datas as $voucher)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-bold">{{ $voucher->voucher_no }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $voucher->date }}</td>
                        <td class="px-6 py-4 whitespace-nowrap uppercase">{{ $voucher->type }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ number_format($voucher->total_amount, 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap flex space-x-2">
                            <button class="edit-voucher-btn text-blue-600 hover:text-blue-900" 
                                data-id="{{ $voucher->id }}" 
                                data-voucher="{{ json_encode($voucher) }}"
                                data-details="{{ json_encode($voucher->details) }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="text-red-600 hover:text-red-900" onclick="confirmDelete('{{ $voucher->id }}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-4">No vouchers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $datas->links() }}</div>
    </div>
</div>

<div id="voucherModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity modal-backdrop"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-5xl overflow-hidden">
            <div class="bg-blue-600 p-4 text-white flex justify-between">
                <h3 id="modalTitle" class="text-lg font-bold">Create New Voucher</h3>
                <button class="close-modal"><i class="fas fa-times"></i></button>
            </div>
            
            <form id="voucherForm" class="p-6">
                <input type="hidden" name="item_id" id="voucher_id">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium mb-1">Voucher Type</label>
                        <select name="type" id="v_type" class="form-control select2 w-full" required>
                            <option value="Receipt">Receipt</option>
                            <option value="Payment">Payment</option>
                            <option value="Journal">Journal</option>
                            <option value="Contra">Contra</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Date</label>
                        <input type="date" name="date" id="v_date" class="form-control w-full" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Narration</label>
                        <input type="text" name="narration" id="v_narration" class="form-control w-full" placeholder="General narration...">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full border" id="details-table">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2 border w-1/4">Account</th>
                                <th class="p-2 border">Description</th>
                                <th class="p-2 border w-32">Debit</th>
                                <th class="p-2 border w-32">Credit</th>
                                <th class="p-2 border w-12 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="voucher-rows">
                            </tbody>
                        <tfoot class="bg-gray-50 font-bold">
                            <tr>
                                <td colspan="2" class="p-2 text-right">Totals:</td>
                                <td class="p-2 border"><input type="text" id="total_debit" class="w-full bg-transparent border-none text-right" readonly value="0.00"></td>
                                <td class="p-2 border"><input type="text" id="total_credit" class="w-full bg-transparent border-none text-right" readonly value="0.00"></td>
                                <td class="p-2 text-center">
                                    <button type="button" id="add-row" class="text-green-600 hover:text-green-800"><i class="fas fa-plus-circle fa-lg"></i></button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" class="close-modal px-4 py-2 border rounded text-gray-600">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded shadow hover:bg-blue-700">Save Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')

@php
    $currentRole = Str::slug(Auth::user()->getRoleNames()->first());
@endphp


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    
    let rowIndex = 0;
    const accountOptions = `@foreach($accounts as $acc)<option value="{{ $acc->id }}">{{ $acc->name }}</option>@endforeach`;

    $(document).ready(function() {
        $('.select2').select2();

        // OPEN CREATE MODAL
        $('.create-new-btn').click(function() {
            resetForm();
            $('#modalTitle').text('Create New Voucher');
            $('#voucherModal').removeClass('hidden');
            addRow(); // Start with one empty row
        });

        // ADD ROW
        $('#add-row').click(function() { addRow(); });

        function addRow(data = null) {
            let row = `
            <tr class="row-item">
                <td class="p-1 border">
                    <select name="details[${rowIndex}][account_id]" class="form-control select2-dynamic w-full" required>
                        <option value="">Select Account</option>
                        ${accountOptions}
                    </select>
                </td>
                <td class="p-1 border"><input type="text" name="details[${rowIndex}][description]" class="voucher-table-input" value="${data ? data.description : ''}"></td>
                <td class="p-1 border"><input type="number" name="details[${rowIndex}][debit]" class="debit-input voucher-table-input text-right" step="0.01" value="${data ? data.debit : '0.00'}"></td>
                <td class="p-1 border"><input type="number" name="details[${rowIndex}][credit]" class="credit-input voucher-table-input text-right" step="0.01" value="${data ? data.credit : '0.00'}"></td>
                <td class="p-1 border text-center">
                    <button type="button" class="remove-row text-red-500"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
            
            $('#voucher-rows').append(row);
            let newSelect = $(`select[name="details[${rowIndex}][account_id]"]`);
            newSelect.select2();
            if(data) newSelect.val(data.chart_of_account_id).trigger('change');
            
            rowIndex++;
            calculateTotals();
        }

        // REMOVE ROW
        $(document).on('click', '.remove-row', function() {
            if ($('.row-item').length > 1) {
                $(this).closest('tr').remove();
                calculateTotals();
            }
        });

        // MATH LOGIC
        $(document).on('input', '.debit-input, .credit-input', function() {
            calculateTotals();
        });

        function calculateTotals() {
            let tDebit = 0, tCredit = 0;
            $('.debit-input').each(function() { tDebit += parseFloat($(this).val()) || 0; });
            $('.credit-input').each(function() { tCredit += parseFloat($(this).val()) || 0; });
            $('#total_debit').val(tDebit.toFixed(2));
            $('#total_credit').val(tCredit.toFixed(2));
        }

        // SUBMIT FORM (CREATE & UPDATE)
        $('#voucherForm').submit(function(e) {
    e.preventDefault();
    
    let id = $('#voucher_id').val();
    
    // Dynamically pick the route based on ID, passing the role slug
    let url = id 
        ? "{{ route('role.vouchers.update', ['role' => $currentRole]) }}" 
        : "{{ route('role.vouchers.store', ['role' => $currentRole]) }}";

    let formData = $(this).serialize();

    $.ajax({
        url: url,
        method: 'POST',
        data: formData + `&total_amount=${$('#total_debit').val()}&_token={{ csrf_token() }}`,
        success: function(res) {
            if (res.success) {
                Swal.fire('Success', 'Voucher saved!', 'success').then(() => location.reload());
            }
        }
    });
});

        // EDIT VOUCHER
        $(document).on('click', '.edit-voucher-btn', function() {
            resetForm();
            let v = $(this).data('voucher');
            let details = $(this).data('details');

            $('#voucher_id').val(v.id);
            $('#v_type').val(v.type).trigger('change');
            $('#v_date').val(v.date);
            $('#v_narration').val(v.narration);
            
            $('#modalTitle').text('Edit Voucher: ' + v.voucher_no);
            $('#voucher-rows').empty();
            
            details.forEach(d => addRow(d));
            $('#voucherModal').removeClass('hidden');
        });

        // CLOSE MODAL
        $('.close-modal, .modal-backdrop').click(function() {
            $('#voucherModal').addClass('hidden');
        });

        function resetForm() {
            $('#voucherForm')[0].reset();
            $('#voucher_id').val('');
            $('#voucher-rows').empty();
            rowIndex = 0;
            $('.select2').trigger('change');
        }
    });

    // DELETE LOGIC
function confirmDelete(id) {
    Swal.fire({
        title: 'Delete this voucher?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "{{ route('role.vouchers.destroy', ['role' => $currentRole]) }}",
                method: 'DELETE',
                data: { 
                    item_id: id, 
                    _token: "{{ csrf_token() }}" 
                },
                success: function() { location.reload(); }
            });
        }
    });
}
</script>
@endsection