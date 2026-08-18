@extends('layout.app')
@section('meta-information')
    <title>My Requests & Requirements — Self Service</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .status-pending      { background:#fef3c7; color:#92400e; }
    .status-under_review { background:#dbeafe; color:#1e40af; }
    .status-approved     { background:#d1fae5; color:#065f46; }
    .status-rejected     { background:#fee2e2; color:#991b1b; }
    .status-fulfilled    { background:#e0f2fe; color:#0c4a6e; }
    .status-closed       { background:#f3f4f6; color:#4b5563; }
    .overdue-row td  { border-left: 3px solid #ef4444 !important; }
    .action-row td   { border-left: 3px solid #f59e0b; }
    select.form-select-tw {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 28px;
    }
</style>
@endsection
@section('main-content')

{{-- ── Hero ─────────────────────────────────────────────── --}}
<div class="rounded-2xl px-8 py-7 mb-7 flex items-center gap-5"
     style="background: linear-gradient(135deg,#0c2240 0%,#1a0c2e 100%)">
    <div class="w-14 h-14 rounded-full bg-white/10 flex items-center justify-center text-3xl flex-shrink-0">
        👤
    </div>
    <div class="flex-1 min-w-0">
        <h1 class="text-xl font-bold text-slate-100 leading-tight">My Requests &amp; Requirements</h1>
        <p class="text-sm text-slate-400 mt-0.5">Self-service portal — track all your requests and company assignments in one place.</p>
    </div>
    @can('create employee request')
    <button class="create-new-btn flex-shrink-0 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl
                   bg-white/10 hover:bg-white/20 text-white text-sm font-semibold transition-colors">
        <i class="fas fa-plus text-xs"></i> New Request
    </button>
    @endcan
</div>

{{-- ── Stat cards ───────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl bg-blue-50 px-5 py-4 text-center">
        <div class="text-3xl font-bold text-blue-600 leading-none">{{ $stats['total'] }}</div>
        <div class="text-xs text-gray-500 mt-1.5 font-medium">Total</div>
    </div>
    <div class="rounded-2xl bg-amber-50 px-5 py-4 text-center">
        <div class="text-3xl font-bold text-amber-600 leading-none">{{ $stats['pending'] }}</div>
        <div class="text-xs text-gray-500 mt-1.5 font-medium">Pending</div>
    </div>
    <div class="rounded-2xl bg-green-50 px-5 py-4 text-center">
        <div class="text-3xl font-bold text-green-600 leading-none">{{ $stats['approved'] }}</div>
        <div class="text-xs text-gray-500 mt-1.5 font-medium">Approved</div>
    </div>
    <div class="rounded-2xl bg-red-50 px-5 py-4 text-center">
        <div class="text-3xl font-bold text-red-600 leading-none">{{ $stats['requires_action'] }}</div>
        <div class="text-xs text-gray-500 mt-1.5 font-medium">Requires My Action</div>
    </div>
</div>

{{-- ── Filters ──────────────────────────────────────────── --}}
<form method="get" id="filterForm">
    <input type="hidden" name="category" id="hiddenCategory" value="{{ request('category') }}">
    <div class="flex flex-wrap items-center gap-3 mb-4">

        {{-- Tab pills --}}
        <div class="flex gap-2 flex-wrap">
            @php
                $catActive = request('category');
            @endphp
            <button type="button" onclick="setCategory('')"
                class="px-4 py-1.5 rounded-full text-sm font-semibold border transition-colors
                       {{ !$catActive
                          ? 'bg-[#1e3a5f] text-blue-300 border-blue-700'
                          : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100' }}">
                All
            </button>
            <button type="button" onclick="setCategory('request')"
                class="px-4 py-1.5 rounded-full text-sm font-semibold border transition-colors
                       {{ $catActive === 'request'
                          ? 'bg-[#1e3a5f] text-blue-300 border-blue-700'
                          : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100' }}">
                📤 My Requests
            </button>
            <button type="button" onclick="setCategory('require')"
                class="px-4 py-1.5 rounded-full text-sm font-semibold border transition-colors
                       {{ $catActive === 'require'
                          ? 'bg-[#2e1a4a] text-purple-300 border-purple-700'
                          : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100' }}">
                📥 Assigned to Me
            </button>
        </div>

        {{-- Status filter --}}
        <select name="status" onchange="document.getElementById('filterForm').submit()"
            class="form-select-tw text-sm rounded-lg border border-gray-200 bg-white px-3 py-1.5
                   text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
            <option value="">All Statuses</option>
            @foreach(['pending','under_review','approved','rejected','fulfilled','closed'] as $s)
                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>
                    {{ ucfirst(str_replace('_',' ',$s)) }}
                </option>
            @endforeach
        </select>
    </div>
</form>

{{-- ── Table card ───────────────────────────────────────── --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">

    {{-- Card header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 class="text-base font-bold text-gray-800">
            @if(request('category') == 'require') 📥 Requirements Assigned to Me
            @elseif(request('category') == 'request') 📤 My Requests
            @else All My Records
            @endif
        </h2>
        <span class="text-xs text-gray-400 font-medium">{{ $datas->total() }} record(s)</span>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wide">
                    <th class="px-5 py-3 font-semibold text-left">#</th>
                    <th class="px-5 py-3 font-semibold text-left">Type</th>
                    <th class="px-5 py-3 font-semibold text-left">Category</th>
                    <th class="px-5 py-3 font-semibold text-left">Amount / Info</th>
                    <th class="px-5 py-3 font-semibold text-left">Status</th>
                    <th class="px-5 py-3 font-semibold text-left">Deadline</th>
                    <th class="px-5 py-3 font-semibold text-left">Submitted</th>
                    <th class="px-5 py-3 font-semibold text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($datas as $key => $item)
                @php
                    $isOverdue        = $item->deadline && $item->deadline->isPast() && !in_array($item->status, ['fulfilled','closed']);
                    $requiresMyAction = $item->category === 'require' && in_array($item->status, ['pending','approved']);
                    $rowClass         = $isOverdue ? 'overdue-row' : ($requiresMyAction ? 'action-row' : '');
                @endphp
                <tr class="{{ $rowClass }} hover:bg-gray-50/60 transition-colors">

                    {{-- # --}}
                    <td class="px-5 py-3.5 font-bold text-gray-700">
                        {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                    </td>

                    {{-- Type --}}
                    <td class="px-5 py-3.5 text-gray-700 font-medium">
                        {{ \App\Models\EmployeeRequest::requestTypeLabel($item->request_type) }}
                    </td>

                    {{-- Category badge --}}
                    <td class="px-5 py-3.5">
                        @if($item->category === 'request')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                📤 Request
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                                📥 Require
                            </span>
                        @endif
                    </td>

                    {{-- Amount / Info --}}
                    <td class="px-5 py-3.5 text-gray-600">
                        @if($item->amount)
                            <span class="font-semibold text-gray-800">৳{{ number_format($item->amount, 2) }}</span>
                            @if($item->disbursement)
                                <br><span class="text-xs text-green-600 font-medium">
                                    <i class="fas fa-check-circle"></i> Disbursed
                                </span>
                            @endif
                        @elseif($item->metadata)
                            <span class="text-xs text-gray-500">
                                @if(isset($item->metadata['document_type']))
                                    {{ ucfirst(str_replace('_',' ',$item->metadata['document_type'])) }}
                                @elseif(isset($item->metadata['bonus_type']))
                                    {{ ucfirst($item->metadata['bonus_type']) }} Bonus
                                @elseif(isset($item->metadata['emergency_type']))
                                    {{ ucfirst($item->metadata['emergency_type']) }} Emergency
                                    @if($item->metadata['fast_track'] ?? false)
                                        <span class="inline-block ml-1 px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-semibold">⚡ Fast</span>
                                    @endif
                                @elseif(isset($item->metadata['period_type']))
                                    {{ ucfirst($item->metadata['period_type']) }} Timesheet
                                @elseif(isset($item->metadata['review_period']))
                                    {{ $item->metadata['review_period'] }}
                                @else —
                                @endif
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    {{-- Status --}}
                    <td class="px-5 py-3.5">
                        <span class="status-{{ $item->status }} inline-block px-2.5 py-0.5 rounded-md text-xs font-semibold">
                            {{ ucfirst(str_replace('_',' ',$item->status)) }}
                        </span>
                        @if($isOverdue)
                            <div class="text-xs font-bold text-red-600 mt-0.5">OVERDUE</div>
                        @endif
                    </td>

                    {{-- Deadline --}}
                    <td class="px-5 py-3.5 {{ $isOverdue ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                        @if($item->deadline)
                            {{ $item->deadline->format('d M Y') }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    {{-- Submitted --}}
                    <td class="px-5 py-3.5 text-gray-500">
                        {{ ($item->requested_at ?? $item->created_at)->format('d M Y') }}
                    </td>

                    {{-- Actions --}}
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @if($item->category === 'require' && $item->status === 'approved')
                                @if(Auth::id() == $item->employee_id || Auth::user()->hasPermissionTo('fulfill employee request'))
                                <button class="fulfill-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg
                                               bg-green-600 hover:bg-green-700 text-white text-xs font-semibold transition-colors"
                                    data-id="{{ $item->id }}" title="Submit Fulfillment">
                                    <i class="fas fa-check-double text-xs"></i> Fulfill
                                </button>
                                @endif
                            @endif

                            @if($item->request_type === 'noc_certificate' && in_array($item->status, ['approved','fulfilled']))
                                <a href="{{ route('role.employee-requests.noc-pdf', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $item->id]) }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300
                                           text-gray-600 hover:bg-gray-50 text-xs font-semibold transition-colors"
                                    target="_blank" title="Download Document">
                                    <i class="fas fa-file-pdf text-xs"></i> Download
                                </a>
                            @endif

                            @if($item->status === 'rejected' && $item->remarks)
                                <button class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-red-200
                                               text-red-600 hover:bg-red-50 text-xs font-semibold transition-colors"
                                    onclick="alert('Rejection reason:\n{{ addslashes($item->remarks) }}')"
                                    title="View rejection reason">
                                    <i class="fas fa-info-circle text-xs"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-16 text-center">
                        <i class="fas fa-inbox text-4xl text-gray-200 block mb-3"></i>
                        <p class="text-gray-400 text-sm">No records found.</p>
                        @can('create employee request')
                            <button class="create-new-btn mt-3 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700
                                           text-white text-sm font-semibold transition-colors">
                                Submit your first request
                            </button>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $datas->appends(request()->all())->links() }}
    </div>
</div>

@include('employee-requests.create-modal')
@include('employee-requests.fulfill-modal')

@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function () {
    $('.select2').select2();
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    $('.create-new-btn').click(function () { $('#createModal').removeClass('hidden'); });
    $(document).on('click', '.modal-close-create', function () { $('#createModal').addClass('hidden'); });

    $(document).on('change', '#create_category', function () {
        toggleCategoryFields($(this).val(), '#create');
    });

    $('#createSubmit').click(function (e) {
        e.preventDefault();
        const formData = new FormData($('#createForm')[0]);
        $.ajax({
            url: $('#createForm').attr('action'),
            method: 'POST', data: formData, processData: false, contentType: false,
            success: function (res) {
                if (res.success) {
                    Swal.fire('Done', 'Created successfully!', 'success');
                    $('#createModal').addClass('hidden');
                    setTimeout(() => location.reload(), 800);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Something went wrong.', 'error'); }
        });
    });

    $(document).on('click', '.fulfill-btn', function () {
        const id = $(this).data('id');
        $('#fulfillRequestId').val(id);
        $('#fulfillNote').val('');
        $('#fulfillModal').removeClass('hidden');
    });
    $(document).on('click', '.modal-close-fulfill', function () { $('#fulfillModal').addClass('hidden'); });

    $('#fulfillSubmit').click(function () {
        const id = $('#fulfillRequestId').val();
        const url = '{{ route("role.employee-requests.fulfill", ["role" => Str::slug(Auth::user()->getRoleNames()->first()), "id" => "__ID__"]) }}'.replace('__ID__', id);
        $.post(url, { fulfillment_note: $('#fulfillNote').val() }, function (res) {
            if (res.success) {
                Swal.fire('Done', res.message, 'success');
                $('#fulfillModal').addClass('hidden');
                setTimeout(() => location.reload(), 800);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        });
    });

    $(document).on('change', '#create_request_type', function () {
        toggleMetaFields($(this).val());
    });
    toggleMetaFields($('#create_request_type').val());
});

function setCategory(cat) {
    document.getElementById('hiddenCategory').value = cat;
    document.getElementById('filterForm').submit();
}

function toggleMetaFields(type) {
    $('#meta_project_payment_wrap, #meta_travel_wrap, #meta_policy_wrap, #meta_training_wrap,' +
      '#meta_emergency_wrap, #meta_noc_wrap, #meta_bonus_wrap, #meta_equipment_wrap,' +
      '#meta_asset_return_wrap, #meta_performance_wrap, #meta_timesheet_wrap,' +
      '#meta_office_req_wrap, #meta_interior_wrap').hide();

    const map = {
        'project_payment':       '#meta_project_payment_wrap',
        'travel_allowance':      '#meta_travel_wrap',
        'policy_acknowledgment': '#meta_policy_wrap',
        'training_completion':   '#meta_training_wrap',
        'emergency_fund':        '#meta_emergency_wrap',
        'noc_certificate':       '#meta_noc_wrap',
        'bonus_incentive':       '#meta_bonus_wrap',
        'equipment_resource':    '#meta_equipment_wrap',
        'asset_return':          '#meta_asset_return_wrap',
        'performance_review':    '#meta_performance_wrap',
        'timesheet_log':         '#meta_timesheet_wrap',
        'office_requisition':    '#meta_office_req_wrap',
        'interior_project':      '#meta_interior_wrap',
    };
    if (map[type]) $(map[type]).show();
}

function toggleCategoryFields(category, prefix) {
    const $amount   = $(prefix + '_amount_wrap');
    const $deadline = $(prefix + '_deadline_wrap');
    const $typesel  = $(prefix + '_request_type');
    const types = @json(\App\Models\EmployeeRequest::REQUEST_TYPES);

    $typesel.empty();
    if (types[category]) {
        $.each(types[category], function (val, label) {
            $typesel.append(new Option(label, val));
        });
    }

    if (category === 'request') { $amount.show(); $deadline.hide(); }
    else { $amount.hide(); $deadline.show(); }

    if (prefix === '#create') toggleMetaFields($typesel.val());
}
</script>
@endsection
