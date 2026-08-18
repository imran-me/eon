@extends('layout.app')
@section('meta-information')
    <title>Manage Notices</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
<style>
:root{
  --indigo:#4f46e5;--violet:#7c3aed;--amber:#f59e0b;
  --slate-900:#0f172a;--slate-800:#1e293b;--slate-700:#334155;
  --slate-600:#475569;--slate-500:#64748b;--slate-400:#94a3b8;
  --slate-300:#cbd5e1;--slate-200:#e2e8f0;--slate-100:#f1f5f9;--slate-50:#f8fafc;
  --green:#22c55e;--red:#ef4444;--blue:#3b82f6;
}

/* ── PAGE WRAPPER ── */
.notice-page{display:flex;flex-direction:column;gap:20px;}

/* ── PAGE HEADER ── */
.notice-page-header{
  background:linear-gradient(135deg,var(--indigo) 0%,var(--violet) 100%);
  border-radius:16px;padding:20px 24px;color:white;
  display:flex;align-items:center;justify-content:space-between;
  position:relative;overflow:hidden;
}
.notice-page-header::before{content:'';position:absolute;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.07);top:-80px;right:-50px;}
.notice-page-header::after{content:'';position:absolute;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.04);bottom:-50px;left:120px;}
.nph-left{position:relative;z-index:1;}
.nph-icon{width:44px;height:44px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:10px;}
.nph-title{font-size:18px;font-weight:800;margin-bottom:2px;}
.nph-sub{font-size:12px;opacity:.7;}
.nph-right{position:relative;z-index:1;}
.nph-add-btn{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(255,255,255,.15);backdrop-filter:blur(6px);
  border:1.5px solid rgba(255,255,255,.3);color:white;
  font-size:13px;font-weight:700;padding:10px 20px;
  border-radius:12px;cursor:pointer;transition:.2s;
}
.nph-add-btn:hover{background:rgba(255,255,255,.25);transform:translateY(-1px);box-shadow:0 4px 16px rgba(0,0,0,.2);}

/* ── FILTER CARD ── */
.notice-filter-card{
  background:white;border-radius:16px;
  border:1px solid var(--slate-200);
  box-shadow:0 1px 4px rgba(0,0,0,.04);
  overflow:hidden;
}
.nfc-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 20px;cursor:pointer;
  border-bottom:1px solid transparent;
  transition:background .15s;
}
.nfc-header:hover{background:var(--slate-50);}
.nfc-header.active{border-bottom-color:var(--slate-200);}
.nfc-header-left{display:flex;align-items:center;gap:10px;}
.nfc-header-icon{width:32px;height:32px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--violet);}
.nfc-header-title{font-size:13px;font-weight:700;color:var(--slate-700);}
.nfc-toggle{font-size:12px;color:var(--slate-400);transition:transform .25s;}
.nfc-header.active .nfc-toggle{transform:rotate(180deg);}
.nfc-body{display:none;padding:20px;}
.nfc-body.active{display:block;}
.nfc-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.nfc-group label{font-size:11px;font-weight:600;color:var(--slate-500);margin-bottom:5px;display:block;text-transform:uppercase;letter-spacing:.5px;}
.nfc-group input,.nfc-group select{
  width:100%;border:1.5px solid var(--slate-200);border-radius:10px;
  padding:8px 12px;font-size:12px;color:var(--slate-700);
  background:white;transition:border-color .2s,box-shadow .2s;
}
.nfc-group input:focus,.nfc-group select:focus{
  outline:none;border-color:var(--indigo);
  box-shadow:0 0 0 3px rgba(79,70,229,.1);
}
.nfc-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid var(--slate-100);}
.nfc-btn-reset{
  display:inline-flex;align-items:center;gap:6px;
  border:1.5px solid var(--slate-200);background:white;
  color:var(--slate-600);font-size:12px;font-weight:600;
  padding:8px 18px;border-radius:10px;cursor:pointer;transition:.2s;
}
.nfc-btn-reset:hover{border-color:var(--slate-300);background:var(--slate-50);}
.nfc-btn-apply{
  display:inline-flex;align-items:center;gap:6px;
  background:var(--indigo);border:none;
  color:white;font-size:12px;font-weight:700;
  padding:8px 20px;border-radius:10px;cursor:pointer;transition:.2s;
}
.nfc-btn-apply:hover{background:var(--violet);box-shadow:0 4px 12px rgba(79,70,229,.3);}

/* ── TABLE CARD ── */
.notice-table-card{
  background:white;border-radius:16px;
  border:1px solid var(--slate-200);
  box-shadow:0 1px 4px rgba(0,0,0,.04);
  overflow:hidden;
}
.ntc-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 20px;border-bottom:1px solid var(--slate-100);
}
.ntc-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:var(--slate-800);}
.ntc-title-dot{width:8px;height:8px;background:var(--indigo);border-radius:50%;}
.ntc-count{font-size:11px;background:var(--slate-100);color:var(--slate-500);font-weight:600;padding:3px 10px;border-radius:999px;}

/* ── TABLE STYLES ── */
.notice-tbl{width:100%;border-collapse:collapse;}
.notice-tbl thead tr{background:var(--slate-50);border-bottom:1.5px solid var(--slate-200);}
.notice-tbl th{
  font-size:11px;font-weight:700;color:var(--slate-500);
  text-transform:uppercase;letter-spacing:.6px;
  padding:12px 16px;text-align:left;white-space:nowrap;
}
.notice-tbl tbody tr{border-bottom:1px solid var(--slate-100);transition:background .15s;}
.notice-tbl tbody tr:last-child{border-bottom:none;}
.notice-tbl tbody tr:hover{background:#fafaff;}
.notice-tbl td{font-size:13px;color:var(--slate-700);padding:13px 16px;vertical-align:middle;}

.ntbl-sl{
  font-size:11px;font-weight:800;color:var(--slate-400);
  background:var(--slate-100);border-radius:6px;
  padding:3px 8px;display:inline-block;
}

.ntbl-company{display:flex;align-items:center;gap:8px;}
.ntbl-company-badge{
  width:28px;height:28px;border-radius:8px;
  background:linear-gradient(135deg,var(--indigo),var(--violet));
  display:flex;align-items:center;justify-content:center;
  color:white;font-size:10px;font-weight:800;flex-shrink:0;
}
.ntbl-company-name{font-weight:600;color:var(--slate-800);font-size:12px;}

.ntbl-dept{
  font-size:11px;font-weight:600;color:var(--violet);
  background:#f5f3ff;border:1px solid #ede9fe;
  border-radius:6px;padding:3px 9px;display:inline-block;
}

.ntbl-title{font-weight:700;color:var(--slate-800);}

.ntbl-desc-text{font-size:12px;color:var(--slate-600);}
.ntbl-read-more{
  font-size:11px;color:var(--indigo);font-weight:600;
  background:none;border:none;cursor:pointer;padding:0 0 0 4px;
  transition:color .2s;
}
.ntbl-read-more:hover{color:var(--violet);}

.ntbl-date{
  display:flex;align-items:center;gap:5px;
  font-size:12px;font-weight:500;color:var(--slate-600);
}
.ntbl-date i{font-size:11px;color:var(--slate-400);}

.ntbl-creator{font-size:11px;color:var(--slate-500);}

/* ── STATUS BADGES ── */
.status-badge{
  display:inline-flex;align-items:center;gap:5px;
  font-size:11px;font-weight:700;padding:4px 11px;border-radius:999px;
}
.status-badge-dot{width:6px;height:6px;border-radius:50%;}
.status-published{background:#dcfce7;color:#15803d;}
.status-published .status-badge-dot{background:#22c55e;}
.status-draft{background:#fef9c3;color:#a16207;}
.status-draft .status-badge-dot{background:#eab308;}

/* ── ACTION BUTTONS ── */
.action-btns{display:flex;align-items:center;gap:6px;}
.act-btn{
  width:30px;height:30px;border-radius:8px;border:1.5px solid;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:12px;cursor:pointer;transition:.2s;background:white;
}
.act-btn-edit{border-color:#c7d2fe;color:var(--indigo);}
.act-btn-edit:hover{background:var(--indigo);color:white;border-color:var(--indigo);box-shadow:0 3px 8px rgba(79,70,229,.25);}
.act-btn-delete{border-color:#fecaca;color:var(--red);}
.act-btn-delete:hover{background:var(--red);color:white;border-color:var(--red);box-shadow:0 3px 8px rgba(239,68,68,.25);}

/* ── EMPTY STATE ── */
.notice-empty{text-align:center;padding:60px 24px;}
.notice-empty-icon{width:72px;height:72px;background:var(--slate-100);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 16px;}
.notice-empty-title{font-size:16px;font-weight:700;color:var(--slate-700);margin-bottom:6px;}
.notice-empty-sub{font-size:13px;color:var(--slate-400);}

/* ── PAGINATION ── */
.ntc-pagination{padding:14px 20px;border-top:1px solid var(--slate-100);}

#descriptionPopup:not(.hidden),#editModal:not(.hidden){display:flex;}

@media(max-width:900px){
  .nfc-grid{grid-template-columns:1fr 1fr;}
  .notice-page-header{flex-direction:column;align-items:flex-start;gap:14px;}
}
@media(max-width:600px){
  .nfc-grid{grid-template-columns:1fr;}
  .notice-tbl th:nth-child(5),.notice-tbl td:nth-child(5){display:none;}
}
</style>
@endsection
@section('main-content')

<div class="notice-page">

    {{-- ── PAGE HEADER ── --}}
    <div class="notice-page-header">
        <div class="nph-left">
            <div class="nph-icon">📢</div>
            <div class="nph-title">Notice Management</div>
            <div class="nph-sub">Create, manage and publish company notices</div>
        </div>
        @can('create notice')
        <div class="nph-right">
            <button class="nph-add-btn create-new-btn">
                <i class="fas fa-plus"></i> Add New Notice
            </button>
        </div>
        @endcan
    </div>

    {{-- ── FILTER CARD ── --}}
    <div class="notice-filter-card">
        <form action="" method="get" id="filterForm">
            <div class="nfc-header" id="nfcToggle">
                <div class="nfc-header-left">
                    <div class="nfc-header-icon"><i class="fas fa-filter"></i></div>
                    <span class="nfc-header-title">Filter Notices</span>
                </div>
                <i class="fas fa-chevron-down nfc-toggle"></i>
            </div>
            <div class="nfc-body" id="nfcBody">
                <div class="nfc-grid">
                    <div class="nfc-group">
                        <label>Company</label>
                        <select name="company_id" class="select2" style="width:100%">
                            <option value="">All Companies</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" {{ $company->id == request('company_id') ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="nfc-group">
                        <label>Department</label>
                        <select name="department_id" class="select2" style="width:100%">
                            <option value="">All Departments</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" {{ $department->id == request('department_id') ? 'selected' : '' }}>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="nfc-group">
                        <label>Title</label>
                        <input type="text" name="title" value="{{ request('title') }}" placeholder="Search by title...">
                    </div>
                    <div class="nfc-group">
                        <label>Status</label>
                        <select name="status" class="select2" style="width:100%">
                            <option value="">All Status</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>Published</option>
                        </select>
                    </div>
                    <div class="nfc-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}">
                    </div>
                    <div class="nfc-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}">
                    </div>
                </div>
                <div class="nfc-actions">
                    <button type="button" class="nfc-btn-reset reset-btn">
                        <i class="fas fa-rotate-left"></i> Reset
                    </button>
                    <button type="submit" class="nfc-btn-apply">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- ── TABLE CARD ── --}}
    <div class="notice-table-card">
        <div class="ntc-header">
            <div class="ntc-title">
                <span class="ntc-title-dot"></span>
                Notice List
            </div>
            <span class="ntc-count">{{ $datas->total() }} records</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="notice-tbl">
                <thead>
                    <tr>
                        <th style="width:52px">#</th>
                        <th>Company</th>
                        <th>Department</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Publish Date</th>
                        <th>Expiry Date</th>
                        <th>Created</th>
                        <th>Status</th>
                        @canany(['edit product', 'delete product'])
                        <th>Actions</th>
                        @endcanany
                    </tr>
                </thead>
                <tbody>
                    @forelse ($datas as $key => $value)
                    <tr id="notice-{{ $value->id }}">
                        <td>
                            <span class="ntbl-sl">{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</span>
                        </td>
                        <td>
                            <div class="ntbl-company">
                                <div class="ntbl-company-badge">
                                    {{ strtoupper(substr($value->company?->name ?? 'N', 0, 1)) }}
                                </div>
                                <span class="ntbl-company-name">{{ $value->company?->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td>
                            @if($value->department?->name)
                                <span class="ntbl-dept">{{ $value->department->name }}</span>
                            @else
                                <span style="color:var(--slate-400);font-size:12px;">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="ntbl-title">{{ $value->title }}</span>
                        </td>
                        <td>
                            <span class="ntbl-desc-text">
                                @if(strlen($value->description) > 50)
                                    {{ mb_substr($value->description, 0, 50) }}<button type="button" class="ntbl-read-more desc-read-more" data-description="{{ e($value->description) }}">...read more</button>
                                @else
                                    {{ $value->description }}
                                @endif
                            </span>
                        </td>
                        <td>
                            <div class="ntbl-date">
                                <i class="fas fa-calendar-check"></i>
                                {{ $value->publish_date ? \Carbon\Carbon::parse($value->publish_date)->format('M d, Y') : '—' }}
                            </div>
                        </td>
                        <td>
                            <div class="ntbl-date">
                                <i class="fas fa-calendar-xmark"></i>
                                {{ $value->expiry_date ? \Carbon\Carbon::parse($value->expiry_date)->format('M d, Y') : '—' }}
                            </div>
                        </td>
                        <td>
                            <span class="ntbl-creator">{{ date('M d, Y', strtotime($value->created_at)) }}</span>
                        </td>
                        <td>
                            @if($value->status == 'published')
                                <span class="status-badge status-published">
                                    <span class="status-badge-dot"></span> Published
                                </span>
                            @elseif($value->status == 'draft')
                                <span class="status-badge status-draft">
                                    <span class="status-badge-dot"></span> Draft
                                </span>
                            @endif
                        </td>
                        @canany(['edit product', 'delete product'])
                        <td>
                            <div class="action-btns">
                                @can('edit product')
                                <button class="act-btn act-btn-edit edit-btn"
                                    data-item_id="{{ $value->id }}"
                                    data-company_id="{{ $value->company_id }}"
                                    data-department_id="{{ $value->department_id }}"
                                    data-title="{{ $value->title }}"
                                    data-publish_date="{{ $value->publish_date }}"
                                    data-expiry_date="{{ $value->expiry_date }}"
                                    data-description="{{ $value->description }}"
                                    data-status="{{ $value->status }}"
                                    data-icon="{{ $value->icon ?? '📢' }}"
                                    data-card_color="{{ $value->card_color ?? '#f97316,#f59e0b' }}"
                                    data-text_color="{{ $value->text_color ?? '#ffffff' }}"
                                    data-badge_icon="{{ $value->badge_icon ?? 'fas fa-bell' }}"
                                    data-badge_label="{{ $value->badge_label ?? 'REMINDER' }}"
                                    data-slide_image="{{ $value->slide_image ? asset('storage/' . $value->slide_image) : '' }}"
                                    title="Edit Notice">
                                    <i class="fas fa-pen"></i>
                                </button>
                                @endcan
                                @can('delete product')
                                <button class="act-btn act-btn-delete" onclick="confirmDelete('{{ $value->id }}', '{{ $value->title }}')" title="Delete Notice">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endcan
                            </div>
                        </td>
                        @endcanany
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10">
                            <div class="notice-empty">
                                <div class="notice-empty-icon">📭</div>
                                <div class="notice-empty-title">No notices found</div>
                                <div class="notice-empty-sub">Try adjusting your filters or add a new notice.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ntc-pagination">
            {{ $datas->appends(request()->all())->links() }}
        </div>
    </div>

</div>

@include('notices.create-modal')
@include('notices.edit-modal')
@include('notices.delete-modal')

{{-- Description Popup --}}
<div id="descriptionPopup" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div id="descriptionPopupBackdrop" class="absolute inset-0 bg-black/40"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 z-10">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-base font-semibold text-gray-800">Description</h3>
            <button id="descriptionPopupClose" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p id="descriptionPopupText" class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap"></p>
    </div>
</div>


@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();
            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });
        });

        // Filter toggle
        document.getElementById('nfcToggle').addEventListener('click', function() {
            this.classList.toggle('active');
            document.getElementById('nfcBody').classList.toggle('active');
        });

        // Show create modal
        $('.create-new-btn').click(function() {
            $('#createModal').removeClass('hidden');
        });

        // Show edit modal
        $('.edit-btn').click(function() {
            $('#editModal').removeClass('hidden');
            $('#editModal .select2').select2();
            $('#editItemId').val($(this).data('item_id'));
            $('#edit_title').val($(this).data('title'));
            $('#edit_description').val($(this).data('description'));
            $('#edit_publish_date').val($(this).data('publish_date'));
            $('#edit_expiry_date').val($(this).data('expiry_date'));
            $('#edit_company_id').val($(this).data('company_id')).trigger('change');
            $('#edit_department_id').val($(this).data('department_id')).trigger('change');
            $('#edit_status').val($(this).data('status')).trigger('change');
            // style fields
            if (typeof populateEditNoticeStyle === 'function') {
                populateEditNoticeStyle(
                    $(this).data('icon'),
                    $(this).data('card_color'),
                    $(this).data('text_color'),
                    $(this).data('badge_icon'),
                    $(this).data('badge_label'),
                    $(this).data('slide_image')
                );
            }
            // update preview text
            $('#edit_prev_title').text($(this).data('title') || 'Notice Title');
            $('#edit_prev_desc').text($(this).data('description') || 'Notice description preview.');
        });

        // Close modals
        $('.modal-close-create, .modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                $('#createModal').addClass('hidden');
            }
        });
        $('.modal-close-edit').click(function() {
            $('#editModal').addClass('hidden');
        });
        $(document).on('click', '#editModal .modal-backdrop', function() {
            $('#editModal').addClass('hidden');
        });

        $('.modal-close-delete, .modal-backdrop').click(function(e) {
            if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                $('#deleteModal').addClass('hidden');
            }
        });
        $('.close-btn').click(function() { $(this).closest('.alert').addClass('hidden'); });

        // Create
        $('#createSubmit').click(function(e) {
            e.preventDefault();
            if (validateCreateForm()) {
                let formData = new FormData($('#createForm')[0]);
                $.ajax({
                    url: $('#createForm').attr('action'), method: 'POST',
                    data: formData, processData: false, contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon:'success', title:'Done', text:'Notice created successfully!' });
                            $('#createModal').addClass('hidden');
                            $('#createForm')[0].reset();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon:'error', title:'Oops...', text: response.message || 'Something went wrong.' });
                        }
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        Swal.fire({ icon:'error', title:'Error!', text:'Failed to create notice.' });
                    }
                });
            }
        });

        // Edit
        $('#editSubmit').click(function() {
            if (validateEditForm()) {
                let formData = new FormData($('#editForm')[0]);
                $.ajax({
                    url: $(this).data('action'), method: 'POST',
                    data: formData, processData: false, contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon:'success', title:'Done', text:'Notice updated successfully!' });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon:'error', title:'Oops...', text: response.message || 'Update failed.' });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({ icon:'error', title:'Error!', text:'Something went wrong!' });
                    }
                });
            }
        });

        // Delete
        $('#confirmDeleteBtn').click(function() {
            $.ajax({
                url: $(this).data('action'), method: 'DELETE',
                data: { item_id: $(this).data('item-id') },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon:'success', title:'Done', text:'Notice deleted successfully!' });
                        $('#deleteModal').addClass('hidden');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        Swal.fire({ icon:'error', title:'Oops...', text: response.message });
                    }
                },
                error: function() {
                    Swal.fire({ icon:'error', title:'Error!', text:'Something went wrong!' });
                }
            });
        });

        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');
            if (!$('#create_title').val().trim()) {
                $('#create_title').parent().find('.error-message').removeClass('hidden');
                $('#create_title').addClass('border-red-500'); isValid = false;
            }
            if (!$('#create_description').val().trim()) {
                $('#create_description').parent().find('.error-message').removeClass('hidden');
                $('#create_description').addClass('border-red-500'); isValid = false;
            }
            if (!$('#create_status').val().trim()) {
                $('#create_status').parent().find('.error-message').removeClass('hidden');
                $('#create_status').addClass('border-red-500'); isValid = false;
            }
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            if (!$('#edit_title').val().trim()) {
                $('#edit_title').parent().find('.error-message').removeClass('hidden');
                $('#edit_title').addClass('border-red-500'); isValid = false;
            }
            if (!$('#edit_description').val().trim()) {
                $('#edit_description').parent().find('.error-message').removeClass('hidden');
                $('#edit_description').addClass('border-red-500'); isValid = false;
            }
            if (!$('#edit_status').val().trim()) {
                $('#edit_status').parent().find('.error-message').removeClass('hidden');
                $('#edit_status').addClass('border-red-500'); isValid = false;
            }
            return isValid;
        }

        function resetCreateForm() {
            $('#createForm')[0].reset();
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');
        }

        // Description popup
        $(document).on('click', '.desc-read-more', function() {
            $('#descriptionPopupText').text($(this).data('description'));
            $('#descriptionPopup').removeClass('hidden');
        });
        $('#descriptionPopupClose, #descriptionPopupBackdrop').on('click', function() {
            $('#descriptionPopup').addClass('hidden');
        });

        function confirmDelete(id, name = null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

        // Reset filter
        document.querySelector('.reset-btn').addEventListener('click', function(e) {
            e.preventDefault();
            window.location = "{{ route('role.notices.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
        });
    </script>
@endsection
