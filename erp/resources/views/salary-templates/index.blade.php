@extends('layout.app')
@section('meta-information')
    <title>Manage Salary Templates</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
@endsection
@section('main-content')
    @include('layout.payroll-tabs')

    <!-- States Table -->
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden mt-0">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Salary Templates List
                </h2>
                @can('create salary template')
                <button class="btn btn-primary create-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Salary Templates
                </button>
                @endcan
            </div>

            <div class="states-table-content">
                <!-- Success Alert -->
                <form action="" method="get">
                    <div class="filter-container">
                        <div class="filter-header">
                            <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="filter-content">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <input type="text" name="name" id="name" value="{{ request('name') }}" placeholder="Type Name...">
                                </div>
                                <div class="filter-actions" style="margin: 0">
                                    <button type="button" class="btn-sm reset-btn" style="padding: 4px 10px;">Reset</button>
                                    <button type="submit" class="btn-sm apply-btn" style="padding: 4px 10px;">Apply Filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table with Data -->
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:4%">
                                    #
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:14%">
                                    <i class="fas fa-tag mr-1 text-indigo-400"></i>Template Name
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-money-bill-wave mr-1 text-blue-400"></i>Basic Salary
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-home mr-1 text-green-400"></i>House Rent
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-briefcase-medical mr-1 text-red-400"></i>Medical
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-bus mr-1 text-yellow-500"></i>Conveyance
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-plus-circle mr-1 text-purple-400"></i>Other
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:9%">
                                    <i class="fas fa-gift mr-1 text-pink-400"></i>Bonus
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:11%">
                                    <i class="fas fa-hand-holding-usd mr-1 text-teal-500"></i>Total Salary
                                </th>
                                @canany(['edit salary template', 'delete salary template'])
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                    <i class="fas fa-cogs mr-1 text-gray-400"></i>Actions
                                </th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse ($datas as $key => $value)
                                <tr class="hover:bg-blue-50 transition-colors duration-150">
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-bold">
                                            {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center">
                                                <i class="fas fa-file-alt text-xs"></i>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-800">{{ $value->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-medium text-gray-700">৳ {{ number_format($value->basic_salary, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm text-gray-600">৳ {{ number_format($value->house_rent, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm text-gray-600">৳ {{ number_format($value->medical_allowance, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm text-gray-600">৳ {{ number_format($value->conveyance_allowance, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm text-gray-600">৳ {{ number_format($value->other_allowance, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm text-pink-600 font-medium">৳ {{ number_format($value->bonus, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-bold text-teal-700">৳ {{ number_format($value->total_salary, 2) }}</span>
                                    </td>
                                    @canany(['edit salary template', 'delete salary template'])
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            @can('edit salary template')
                                            <button
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-600 hover:text-white transition-colors duration-150 edit-btn"
                                                data-item_id="{{ $value->id }}"
                                                data-name="{{ $value->name }}"
                                                data-basic_salary="{{ $value->basic_salary }}"
                                                data-house_rent="{{ $value->house_rent }}"
                                                data-medical_allowance="{{ $value->medical_allowance }}"
                                                data-conveyance_allowance="{{ $value->conveyance_allowance }}"
                                                data-other_allowance="{{ $value->other_allowance }}"
                                                data-bonus="{{ $value->bonus }}"
                                                data-total_salary="{{ $value->total_salary }}"
                                                data-action="{{ route('role.salary-templates.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'salary_template' => $value->id]) }}"
                                                title="Edit Template">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            @endcan
                                            @can('delete salary template')
                                            <button
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-50 text-red-600 border border-red-200 hover:bg-red-600 hover:text-white transition-colors duration-150"
                                                onclick="confirmDelete('{{ $value->id }}', '{{ $value->name }}')"
                                                title="Delete Template">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center py-12">
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                        </div>
                                        <h4 class="text-gray-500 text-base font-semibold mt-1">No salary templates found</h4>
                                        <p class="text-gray-400 text-sm">Create a new template to get started.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-gray-200">
                    {{ $datas->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>

    @include('salary-templates.create-modal')
    @include('salary-templates.edit-modal')
    @include('salary-templates.delete-modal')

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
            $('.create-btn').click(function() {
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-btn').click(function() {
                const item_id = $(this).data('item_id');
                const item_name = $(this).data('name');
                const item_basic_salary = $(this).data('basic_salary');
                const item_house_rent = $(this).data('house_rent');
                const item_medical_allowance = $(this).data('medical_allowance');
                const item_conveyance_allowance = $(this).data('conveyance_allowance');
                const item_other_allowance = $(this).data('other_allowance');
                const item_bonus = $(this).data('bonus');
                const item_total_salary = $(this).data('total_salary');

                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_name').val(item_name);
                $('#edit_basic_salary').val(item_basic_salary);
                $('#edit_house_rent').val(item_house_rent);
                $('#edit_medical_allowance').val(item_medical_allowance);
                $('#edit_conveyance_allowance').val(item_conveyance_allowance);
                $('#edit_other_allowance').val(item_other_allowance);
                $('#edit_bonus').val(item_bonus);
                $('#edit_total_salary').val(item_total_salary);
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
                                text: 'Failed to create salary-templates.'
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
                                $('#editAirportModal').addClass('hidden');
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
                        console.log(response);
                        if (response.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Done",
                                text: "Data deleted successfully!",
                            });
                            $('#deleteModal').addClass('hidden');
                            console.log('trigger reload');
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
        });

        // Form validation functions
        function validateCreateForm() {
            let isValid = true;

            // Reset error messages
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');

            if (!$('#create_name').val().trim()) {
                $('#create_name').next('.error-message').removeClass('hidden');
                $('#create_name').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_basic_salary').val().trim()) {
                $('#create_basic_salary').next('.error-message').removeClass('hidden');
                $('#create_basic_salary').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_total_salary').val().trim()) {
                $('#create_total_salary').next('.error-message').removeClass('hidden');
                $('#create_total_salary').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        function validateEditForm() {
            let isValid = true;

            // Reset error messages
            $('#editAirportForm .error-message').addClass('hidden');
            $('#editAirportForm .form-select, #editAirportForm .form-input').removeClass('border-red-500');

            if (!$('#edit_name').val().trim()) {
                $('#edit_name').next('.error-message').removeClass('hidden');
                $('#edit_name').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_basic_salary').val().trim()) {
                $('#edit_basic_salary').next('.error-message').removeClass('hidden');
                $('#edit_basic_salary').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_total_salary').val().trim()) {
                $('#edit_total_salary').next('.error-message').removeClass('hidden');
                $('#edit_total_salary').addClass('border-red-500');
                isValid = false;
            }


            return isValid;
        }

        // Reset create form
        function resetCreateForm() {
            $('#createStateForm')[0].reset();
            $('#createStateForm .error-message').addClass('hidden');
            $('#createStateForm .form-select, #createStateForm .form-input').removeClass('border-red-500');
        }

        // Delete confirmation
        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterHeader = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');

            filterHeader.addEventListener('click', function() {
                this.classList.toggle('active');
                filterContent.classList.toggle('active');
            });

            // Reset button functionality
            document.querySelector('.filter-container .reset-btn').addEventListener('click', function() {
                const inputs = document.querySelectorAll('.filter-container select, .filter-container input');
                inputs.forEach(input => {
                    if (input.type === 'date') {
                        input.value = '';
                    } else {
                        input.selectedIndex = 0;
                    }
                });
            });
            document.querySelector('.reset-btn').addEventListener('click', function (e) {
                e.preventDefault();
                window.location = '{{ route('role.salary-templates.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
    <script>
document.addEventListener("DOMContentLoaded", function () {

    const fields = [
        "create_basic_salary",
        "create_house_rent",
        "create_medical_allowance",
        "create_conveyance_allowance",
        "create_other_allowance",
        "create_bonus"
    ];

    const totalField = document.getElementById("create_total_salary");

    function calculateTotal() {
        let total = 0;

        fields.forEach(id => {
            const value = parseFloat(document.getElementById(id).value) || 0;
            total += value;
        });

        totalField.value = total.toFixed(2);
    }

    // Attach listener to all fields
    fields.forEach(id => {
        document.getElementById(id).addEventListener("input", calculateTotal);
    });

});
</script>

@endsection
