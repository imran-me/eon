@extends('layout.app')
@section('meta-information')
    <title>Manage Tickets</title>
@endsection
@section('css')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
:root {
  --border:#e4e8f0; --border2:#d0d6e8;
  --text:#1a2035; --text2:#5a6480; --text3:#9aa3bc;
  --accent:#2563eb; --accent2:#1d4ed8; --accent-light:#eff6ff;
  --shadow:0 1px 4px rgba(30,50,100,.07);
  --shadow2:0 4px 16px rgba(30,50,100,.10);
}
body { font-family:'DM Sans',sans-serif; }

/* Page header */
.tv-page-head{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;margin-bottom:14px;box-shadow:var(--shadow);}
.tv-page-logo{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#5b21b6,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.tv-page-title{font-size:18px;font-weight:700;color:var(--text);}
.tv-page-sub{font-size:12px;color:var(--text2);margin-top:1px;}
.tv-page-actions{margin-left:auto;}
.erp-btn{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none;}
.btn-purple{background:linear-gradient(135deg,#5b21b6,#7c3aed);color:#fff;}
.btn-purple:hover{background:linear-gradient(135deg,#4c1d95,#6d28d9);color:#fff;}
.btn-ghost-sm{background:#f0f2f8;color:var(--text2);border:1px solid var(--border);padding:5px 10px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:4px;}
.btn-ghost-sm:hover{border-color:var(--border2);color:var(--text);}
.btn-edit-sm{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;}
.btn-edit-sm:hover{background:#dbeafe;}
.btn-del-sm{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;}
.btn-del-sm:hover{background:#fee2e2;}

/* Card wrapper */
.tk-page-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow2);}
.tk-card-head{background:linear-gradient(90deg,#1e3a8a 0%,#1e40af 100%);padding:14px 20px;display:flex;align-items:center;justify-content:space-between;}
.tk-card-head h2{color:#fff;font-size:15px;font-weight:700;margin:0;}

/* Filter */
.tk-filter{border-bottom:1px solid var(--border);}
.tk-filter-header{padding:13px 20px;background:#f8fafc;cursor:pointer;display:flex;justify-content:space-between;align-items:center;border-left:4px solid #7c3aed;transition:background .2s;}
.tk-filter-header:hover{background:#f1f3f9;}
.tk-filter-header h3{margin:0;font-size:13.5px;font-weight:700;color:var(--text);}
.tk-filter-header .toggle-icon{transition:transform .3s;color:var(--text2);}
.tk-filter-header.active .toggle-icon{transform:rotate(180deg);}
.tk-filter-body{background:#fff;max-height:0;overflow:hidden;transition:max-height .3s ease-out,padding .3s ease-out;}
.tk-filter-body.active{padding:16px 20px;max-height:400px;}
.tk-filter-row{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:12px;}
.tk-filter-group{flex:1;min-width:180px;}
.tk-filter-group label{display:block;margin-bottom:5px;font-size:11px;font-weight:600;color:var(--text2);letter-spacing:.2px;}
.tk-filter-group select,.tk-filter-group input{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .12s;color:var(--text);}
.tk-filter-group select:focus,.tk-filter-group input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);}
.tk-filter-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:6px;}
.tk-filter-apply{padding:8px 18px;background:var(--accent);color:#fff;border:none;border-radius:7px;font-size:12.5px;font-weight:600;cursor:pointer;transition:background .15s;font-family:'DM Sans',sans-serif;}
.tk-filter-apply:hover{background:var(--accent2);}
.tk-filter-reset{padding:8px 18px;background:#f0f2f8;color:var(--text2);border:1px solid var(--border);border-radius:7px;font-size:12.5px;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif;}
.tk-filter-reset:hover{background:#e5e7eb;}

/* Select2 inside filter */
.tk-filter-body .select2-container .select2-selection--single{height:38px;border:1px solid var(--border);border-radius:8px;}
.tk-filter-body .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:38px;padding-left:12px;font-size:13px;color:var(--text);}
.tk-filter-body .select2-container--default .select2-selection--single .select2-selection__arrow{height:36px;}
.tk-filter-body .select2-container--default.select2-container--focus .select2-selection--single{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);}

/* Table */
.tk-table-wrap{overflow-x:auto;padding:0;}
.tk-table{width:100%;border-collapse:collapse;font-size:13px;}
.tk-table thead th{background:#f8fafc;padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid var(--border);white-space:nowrap;}
.tk-table thead th:first-child{padding-left:20px;}
.tk-table tbody td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text);}
.tk-table tbody td:first-child{padding-left:20px;}
.tk-table tbody tr:hover{background:#fafbff;}
.tk-table tbody tr:last-child td{border-bottom:none;}
.tk-route-badge{display:inline-flex;align-items:center;gap:5px;background:#ede9fe;color:#5b21b6;border-radius:6px;padding:3px 8px;font-size:11.5px;font-weight:700;letter-spacing:.3px;}
.tk-route-badge i{font-size:10px;}
.tk-row-title{font-weight:600;color:var(--text);font-size:13px;}
.tk-row-sub{font-size:11px;color:var(--text2);margin-top:2px;}
.tk-mono{font-family:'DM Mono',monospace;font-size:12.5px;}
.tk-badge-active{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:6px;padding:3px 9px;font-size:11px;font-weight:700;display:inline-block;}
.tk-badge-inactive{background:#fef9c3;color:#854d0e;border:1px solid #fde68a;border-radius:6px;padding:3px 9px;font-size:11px;font-weight:700;display:inline-block;}
.tk-empty{text-align:center;padding:48px 20px;color:var(--text2);}
.tk-empty i{font-size:2.5rem;opacity:.35;margin-bottom:12px;display:block;}
.tk-empty h4{font-size:15px;font-weight:600;margin-bottom:4px;color:var(--text);}
.tk-empty p{font-size:12.5px;}

/* Pagination */
.tk-pagination{padding:12px 20px;border-top:1px solid var(--border);background:#fafbff;}
span [aria-current="page"] span{background-color:#2563eb !important;color:white;border-color:#2563eb;}
</style>
@endsection
@section('main-content')

<div style="padding:0 0 20px;">

  {{-- Page header --}}
  <div class="tv-page-head">
    <div class="tv-page-logo">✈</div>
    <div>
      <div class="tv-page-title">Manage Tickets</div>
      <div class="tv-page-sub">Ticket routes, pricing, and stock management</div>
    </div>
    <div class="tv-page-actions">
      @can('create ticket')
      <button class="erp-btn btn-purple create-new-btn">
        <i class="fas fa-plus"></i> Add New Ticket
      </button>
      @endcan
    </div>
  </div>

  {{-- Main card --}}
  <div class="tk-page-card">

    {{-- Card header --}}
    <div class="tk-card-head">
      <h2><i class="fas fa-ticket-alt" style="margin-right:8px;opacity:.8;"></i>Ticket List</h2>
    </div>

    {{-- Filter --}}
    <div class="tk-filter">
      <form action="" method="get" id="filterForm">
        <div class="tk-filter-header" id="tkFilterHeader">
          <h3><i class="fas fa-filter" style="margin-right:8px;color:#7c3aed;"></i>Filter Options</h3>
          <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="tk-filter-body" id="tkFilterBody">
          <div class="tk-filter-row">
            <div class="tk-filter-group">
              <label for="vendor_id">Vendor</label>
              <select id="vendor_id" name="vendor_id" class="select2" style="width:100%">
                <option value="">All</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" {{ $vendor->id == request('vendor_id') ? 'selected' : '' }}>{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="from_airport_id">From Airport</label>
              <select id="from_airport_id" name="from_airport_id" class="select2" style="width:100%">
                <option value="">All</option>
                @foreach ($airports as $airport)
                  <option value="{{ $airport->id }}" {{ $airport->id == request('from_airport_id') ? 'selected' : '' }}>{{ $airport->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="to_airport_id">To Airport</label>
              <select id="to_airport_id" name="to_airport_id" class="select2" style="width:100%">
                <option value="">All</option>
                @foreach ($airports as $airport)
                  <option value="{{ $airport->id }}" {{ $airport->id == request('to_airport_id') ? 'selected' : '' }}>{{ $airport->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="portal_id">Portal</label>
              <select id="portal_id" name="portal_id" class="select2" style="width:100%">
                <option value="">All</option>
                @foreach ($portals as $portal)
                  <option value="{{ $portal->id }}" {{ $portal->id == request('portal_id') ? 'selected' : '' }}>{{ $portal->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="is_active">Status</label>
              <select id="is_active" name="is_active" class="select2" style="width:100%">
                <option value="">All</option>
                <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>
            <div class="tk-filter-group">
              <label for="search">Title</label>
              <input type="text" name="search" value="{{ request('search') }}" id="search" placeholder="Search by title...">
            </div>
          </div>
          <div class="tk-filter-actions">
            <button type="button" class="tk-filter-reset reset-btn">Reset</button>
            <button type="submit" class="tk-filter-apply">Apply Filters</button>
          </div>
        </div>
      </form>
    </div>

    {{-- Table --}}
    <div class="tk-table-wrap">
      <table class="tk-table">
        <thead>
          <tr>
            <th>SL</th>
            <th>Ticket Route</th>
            <th>Airline</th>
            <th>Vendor</th>
            <th>Portal</th>
            <th>Price</th>
            <th>Remaining Qty</th>
            <th>Sale Qty</th>
            <th>Total Sales</th>
            <th>Status</th>
            @canany(['view ticket', 'edit ticket', 'delete ticket'])
            <th>Actions</th>
            @endcanany
          </tr>
        </thead>
        <tbody>
          @forelse ($datas as $key => $value)
          <tr>
            <td>
              <span class="tk-mono" style="color:var(--text2);">{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</span>
            </td>
            <td>
              <div class="tk-row-title">{{ $value->title }}</div>
              @if($value->from_airport || $value->to_airport)
              <div style="margin-top:4px;">
                <span class="tk-route-badge">
                  {{ $value->from_airport?->code ?? '—' }}
                  <i class="fas fa-plane-departure"></i>
                  {{ $value->to_airport?->code ?? '—' }}
                </span>
              </div>
              @endif
            </td>
            <td>
              <span style="font-size:13px;color:var(--text);">{{ $value->airline?->name ?? '—' }}</span>
            </td>
            <td>
              <span style="font-size:13px;color:var(--text);">{{ $value->vendor?->name ?? '—' }}</span>
            </td>
            <td>
              <span style="font-size:13px;color:var(--text);">{{ $value->portal?->name ?? '—' }}</span>
            </td>
            <td>
              <span class="tk-mono" style="color:#0d9488;font-weight:600;">৳ {{ number_format($value->price, 2) }}</span>
            </td>
            <td>
              <span class="tk-mono">{{ $value->qty }}</span>
            </td>
            <td>
              <span class="tk-mono">{{ $value->total_sale_qty }}</span>
            </td>
            <td>
              <span class="tk-mono" style="color:#2563eb;font-weight:600;">{{ $value->total_sale_amount }}</span>
            </td>
            <td>
              @if ($value->status)
                <span class="tk-badge-active">Active</span>
              @else
                <span class="tk-badge-inactive">Inactive</span>
              @endif
            </td>
            @canany(['view ticket', 'edit ticket', 'delete ticket'])
            <td>
              <div style="display:flex;gap:6px;">
                @can('view ticket')
                <button type="button" class="btn-ghost-sm edit-btn"
                  data-item_id="{{ $value->id }}"
                  data-action="{{ route('role.tickets.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket' => $value->id]) }}"
                  data-title="{{ $value->title }}"
                  data-vendor_id="{{ $value->vendor_id }}"
                  data-portal_id="{{ $value->portal_id }}"
                  data-airline_id="{{ $value->airline_id }}"
                  data-from_airport_id="{{ $value->from_airport_id }}"
                  data-to_airport_id="{{ $value->to_airport_id }}"
                  data-price="{{ $value->price }}"
                  data-qty="{{ $value->qty }}"
                  data-status="{{ $value->status }}"
                  title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                @endcan
                @can('view ticket')
                <button type="button" class="btn-del-sm" onclick="confirmDelete('{{ $value->id }}', 'this item')" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
                @endcan
              </div>
            </td>
            @endcanany
          </tr>
          @empty
          <tr>
            <td colspan="11">
              <div class="tk-empty">
                <i class="fas fa-inbox"></i>
                <h4>No tickets found</h4>
                <p>Try adjusting your filters or add a new ticket route.</p>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    <div class="tk-pagination">
      {{ $datas->appends(request()->all())->links() }}
    </div>

  </div>
</div>

@include('tickets.create-modal')
@include('tickets.edit-modal')
@include('tickets.delete-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#createModal .select2').select2({ dropdownParent: $('#createModal') });
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Create modal: vendor ↔ portal mutual exclusion
            $(document).on('change', '#create_vendor_id', function() {
                if ($(this).val()) {
                    $('#create_portal_id').val('').trigger('change');
                }
            });
            $(document).on('change', '#create_portal_id', function() {
                if ($(this).val()) {
                    $('#create_vendor_id').val('').trigger('change');
                }
            });

            // Edit modal: vendor ↔ portal mutual exclusion
            $(document).on('change', '#edit_vendor_id', function() {
                if ($(this).val()) {
                    $('#edit_portal_id').val('').trigger('change');
                }
            });
            $(document).on('change', '#edit_portal_id', function() {
                if ($(this).val()) {
                    $('#edit_vendor_id').val('').trigger('change');
                }
            });
        });

        // Show create modal
        $('.create-new-btn').click(function() {
            $('#createModal').removeClass('hidden');
        });

        // Show edit modal
        $('.edit-btn').click(function() {
            const item_id = $(this).data('item_id');
            const title = $(this).data('title');
            const vendor_id = $(this).data('vendor_id');
            const portal_id = $(this).data('portal_id');
            const airline_id = $(this).data('airline_id');
            const from_airport_id = $(this).data('from_airport_id');
            const to_airport_id = $(this).data('to_airport_id');
            const price = $(this).data('price');
            const qty = $(this).data('qty');
            const status = $(this).data('status');

            $('#editModal').css('display', 'flex');
            $('#editModal .select2').each(function() {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
                $(this).select2({ dropdownParent: $('#editModal') });
            });

            $('#editItemId').val(item_id);
            $('#edit_title').val(title);
            $('#edit_vendor_id').val(vendor_id).trigger('change');
            $('#edit_portal_id').val(portal_id).trigger('change');
            $('#edit_airline_id').val(airline_id).trigger('change');
            $('#edit_from_airport_id').val(from_airport_id).trigger('change');
            $('#edit_to_airport_id').val(to_airport_id).trigger('change');
            $('#edit_price').val(price);
            $('#edit_qty').val(qty);
            $('#edit_status').prop('checked', status == 1 || status === true);
            $('#editSubmit').data('action', $(this).data('action'));
        });

        // Close modals
        $('.modal-close-create, .modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                $('#createModal').addClass('hidden');
            }
        });

        $('.modal-close-edit, .modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-edit').length || $(e.target).hasClass('modal-backdrop')) {
                $('#editModal').css('display', 'none');
            }
        });

        $('.modal-close-delete, .modal-backdrop').click(function(e) {
            if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                $('#deleteModal').addClass('hidden');
            }
        });

        $('.close-btn').click(function() {
            $(this).closest('.alert').addClass('hidden');
        });

        // Create submit
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
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Ticket created successfully!' });
                            $('#createModal').addClass('hidden');
                            $('#createForm')[0].reset();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create ticket.' });
                    }
                });
            }
        });

        // Edit submit
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
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Ticket updated successfully!' });
                            $('#editModal').css('display', 'none');
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
                        Swal.fire({ icon: 'success', title: 'Done', text: 'Ticket deleted successfully!' });
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

        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            if (!$('#create_title').val().trim()) {
                $('#create_title').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_vendor_id').val() && !$('#create_portal_id').val()) {
                $('#create_vendor_msg').text('Please select at least a vendor or portal.').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_airline_id').val()) {
                $('#create_airline_msg').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_from_airport_id').val()) {
                $('#create_from_airport_msg').removeClass('hidden');
                isValid = false;
            }
            if (!$('#create_to_airport_id').val()) {
                $('#create_to_airport_msg').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editForm .error-message').addClass('hidden');
            if (!$('#edit_title').val().trim()) {
                $('#edit_title').next('.error-message').removeClass('hidden');
                isValid = false;
            }
            if (!$('#edit_vendor_id').val() && !$('#edit_portal_id').val()) {
                $('#edit_vendor_id_msg').text('Please select at least a vendor or portal.').removeClass('hidden');
                isValid = false;
            }
            if (!$('#edit_airline_id').val()) { $('#edit_airline_id_msg').removeClass('hidden'); isValid = false; }
            if (!$('#edit_from_airport_id').val()) { $('#edit_from_airport_id_msg').removeClass('hidden'); isValid = false; }
            if (!$('#edit_to_airport_id').val()) { $('#edit_to_airport_id_msg').removeClass('hidden'); isValid = false; }
            return isValid;
        }

        function confirmDelete(id, name = null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterHeader = document.getElementById('tkFilterHeader');
            const filterBody   = document.getElementById('tkFilterBody');

            @php
                $hasFilters = collect(['vendor_id','from_airport_id','to_airport_id','portal_id','is_active','search'])->contains(fn($k) => request($k) !== null && request($k) !== '');
            @endphp
            @if($hasFilters)
            filterHeader.classList.add('active');
            filterBody.classList.add('active');
            @endif

            filterHeader.addEventListener('click', function () {
                this.classList.toggle('active');
                filterBody.classList.toggle('active');
            });

            document.querySelector('.reset-btn').addEventListener('click', function (e) {
                e.preventDefault();
                window.location = "{{ route('role.tickets.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
            });
        });
    </script>
@endsection
