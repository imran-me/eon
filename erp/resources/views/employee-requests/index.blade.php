@extends('layout.app')
@section('meta-information')
    <title>Employee Requests & Requirements</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    /* ── Status badges ── */
    .sb { display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:0.7rem;font-weight:700;letter-spacing:.3px; }
    .sb-pending      { background:#fef3c7;color:#92400e; }
    .sb-under_review { background:#dbeafe;color:#1e40af; }
    .sb-approved     { background:#d1fae5;color:#065f46; }
    .sb-rejected     { background:#fee2e2;color:#991b1b; }
    .sb-fulfilled    { background:#e0f2fe;color:#0c4a6e; }
    .sb-closed       { background:#f3f4f6;color:#4b5563; }

    .cb-request { background:#dbeafe;color:#1d4ed8; }
    .cb-require { background:#ede9fe;color:#7c3aed; }
    .cb { display:inline-flex;align-items:center;gap:3px;padding:2px 9px;border-radius:12px;font-size:0.68rem;font-weight:700; }

    /* ── Stat cards ── */
    .stat-card { background:#fff;border-radius:14px;border:1px solid #f1f5f9;padding:16px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 4px rgba(0,0,0,.04); }
    .stat-icon { width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0; }

    /* ── Action buttons ── */
    .ab { display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;font-size:0.72rem;font-weight:600;border:none;cursor:pointer;transition:all .15s;white-space:nowrap; }
    .ab:hover { opacity:.85;transform:translateY(-1px); }
    .ab-approve  { background:#d1fae5;color:#065f46; }
    .ab-reject   { background:#fee2e2;color:#991b1b; }
    .ab-disburse { background:#ecfdf5;color:#047857; }
    .ab-recover  { background:#fef9c3;color:#854d0e; }
    .ab-fulfill  { background:#cffafe;color:#0e7490; }
    .ab-verify   { background:#dbeafe;color:#1d4ed8; }
    .ab-close    { background:#f1f5f9;color:#475569; }
    .ab-edit     { background:#e0e7ff;color:#3730a3; }
    .ab-delete   { background:#ffe4e6;color:#be123c; }
    .ab-pdf      { background:#f3f4f6;color:#374151; }
    .ab-fast     { background:#fef3c7;color:#b45309; }
    .ab-review   { background:#1e3a5f;color:#fff; }

    /* ── Review drawer ── */
    #reviewDrawer { transition:transform .25s cubic-bezier(.4,0,.2,1); }
    .review-section { border-top:1px solid #f1f5f9;padding-top:14px;margin-top:14px; }
    .review-label { font-size:0.7rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px; }
    .review-value { font-size:0.88rem;font-weight:500;color:#1e293b; }

    /* ── Pending row highlight ── */
    /* tr.is-pending td { border-left:3px solid #f59e0b; }
    tr.is-overdue td { border-left:3px solid #ef4444; } */
</style>
@endsection
@section('main-content')

@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp

{{-- ── Stat Cards ── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <div class="stat-card">
        <div class="stat-icon bg-blue-50 text-blue-600"><i class="fas fa-layer-group"></i></div>
        <div>
            <div class="text-2xl font-bold text-blue-600 leading-none">{{ $stats['total'] }}</div>
            <div class="text-xs text-gray-400 mt-1 font-medium">Total Records</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-amber-50 text-amber-500"><i class="fas fa-clock"></i></div>
        <div>
            <div class="text-2xl font-bold text-amber-500 leading-none">{{ $stats['pending'] }}</div>
            <div class="text-xs text-gray-400 mt-1 font-medium">Awaiting Review</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-green-50 text-green-600"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="text-2xl font-bold text-green-600 leading-none">{{ $stats['approved'] }}</div>
            <div class="text-xs text-gray-400 mt-1 font-medium">Approved</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-cyan-50 text-cyan-600"><i class="fas fa-bolt"></i></div>
        <div>
            <div class="text-2xl font-bold text-cyan-600 leading-none">{{ $stats['fulfilled'] }}</div>
            <div class="text-xs text-gray-400 mt-1 font-medium">Fulfilled</div>
        </div>
    </div>
</div>

{{-- ── Main Card ── --}}
<div class="states-table mt-0">
<div class="states-table-container">

    {{-- Header --}}
    <div class="states-table-header px-5 py-4 flex flex-wrap justify-between items-center gap-3">
        <h2 class="text-white text-base font-bold flex items-center gap-2">
            <i class="fas fa-inbox"></i>
            @if(request('category') == 'require') Requirements List
            @elseif(request('category') == 'request') Requests List
            @else Employee Requests &amp; Requirements
            @endif
        </h2>
        <div class="flex items-center gap-2 flex-wrap">
            @can('view employee request')
            <a href="{{ route('role.employee-requests.report', ['role' => $role]) }}"
               class="inline-flex items-center gap-1.5 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                <i class="fas fa-chart-bar text-xs"></i> Report
            </a>
            @endcan
            @can('create employee request')
            <button class="create-new-btn inline-flex items-center gap-1.5 bg-white text-blue-700 hover:bg-blue-50 text-xs font-bold px-4 py-1.5 rounded-lg transition shadow-sm">
                <i class="fas fa-plus text-xs"></i> Add New
            </button>
            @endcan
        </div>
    </div>

    {{-- Category Tabs --}}
    <div class="flex gap-1 px-5 pt-4 border-b border-gray-100">
        @foreach(['' => 'All', 'request' => '📤 Requests', 'require' => '📥 Requirements'] as $val => $label)
        <button onclick="setCategory('{{ $val }}')"
            class="px-4 py-2 text-sm font-semibold rounded-t-lg border-b-2 transition
                {{ request('category', '') === $val
                    ? ($val === 'require' ? 'border-purple-600 text-purple-700 bg-purple-50' : 'border-blue-600 text-blue-700 bg-blue-50')
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Filter --}}
    <form action="" method="get" id="filterForm">
        <input type="hidden" name="category" id="hiddenCategory" value="{{ request('category') }}">
        <div class="filter-container mt-0" style="margin:12px 16px 0">
            <div class="filter-header">
                <h3 class="text-sm"><i class="fas fa-filter mr-2"></i>Filter</h3>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="filter-content">
                <div class="filter-row">
                    @if($role !== 'employee')
                    <div class="filter-group">
                        <label>Employee</label>
                        <select name="employee_id" class="select2" style="width:100%">
                            <option value="">All</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ $user->id == request('employee_id') ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="filter-group">
                        <label>Type</label>
                        <select name="request_type">
                            <option value="">All</option>
                            @foreach ($requestTypes as $cat => $types)
                                <optgroup label="{{ ucfirst($cat) }}">
                                    @foreach ($types as $val => $label)
                                        <option value="{{ $val }}" {{ request('request_type') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All</option>
                            @foreach (['pending','under_review','approved','rejected','fulfilled','closed'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" id="resetBtn" class="reset-btn">Reset</button>
                    <button type="submit" class="apply-btn">Apply</button>
                </div>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="table-responsive" style="padding:16px">
        <table class="table table-hover min-w-full">
            <thead>
                <tr>
                    <th class="text-xs uppercase tracking-wider">#</th>
                    <th class="text-xs uppercase tracking-wider">Employee</th>
                    <th class="text-xs uppercase tracking-wider">Category / Type</th>
                    <th class="text-xs uppercase tracking-wider">Amount</th>
                    <th class="text-xs uppercase tracking-wider">Deadline</th>
                    <th class="text-xs uppercase tracking-wider">Submitted</th>
                    <th class="text-xs uppercase tracking-wider">Status</th>
                    <th class="text-xs uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($datas as $key => $item)
                @php
                    $isOverdue  = $item->deadline && $item->deadline->isPast() && !in_array($item->status, ['fulfilled','closed']);
                    $isPending  = $item->status === 'pending';
                    $rowClass   = $isOverdue ? 'is-overdue' : ($isPending ? 'is-pending' : '');
                @endphp
                <tr class="{{ $rowClass }} hover:bg-gray-50/70 transition-colors">

                    {{-- # --}}
                    <td class="text-sm font-bold text-gray-500">
                        {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                    </td>

                    {{-- Employee --}}
                    <td>
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white
                                        flex items-center justify-center text-xs font-bold flex-shrink-0">
                                {{ strtoupper(substr($item->employee?->name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-800 leading-tight">{{ $item->employee?->name }}</div>
                                <div class="text-xs text-gray-400">ID #{{ $item->id }}</div>
                            </div>
                        </div>
                    </td>

                    {{-- Category / Type --}}
                    <td>
                        <span class="cb {{ $item->category === 'request' ? 'cb-request' : 'cb-require' }} mb-1">
                            {{ $item->category === 'request' ? '📤 Request' : '📥 Require' }}
                        </span>
                        <div class="text-xs text-gray-600 mt-1 font-medium">{{ \App\Models\EmployeeRequest::requestTypeLabel($item->request_type) }}</div>
                    </td>

                    {{-- Amount --}}
                    <td>
                        @if($item->amount)
                            <span class="text-sm font-bold text-gray-800">৳{{ number_format($item->amount, 2) }}</span>
                            @if($item->disbursement)
                                <div class="text-xs text-green-600 font-semibold mt-0.5"><i class="fas fa-check-circle mr-0.5"></i>Disbursed</div>
                            @endif
                        @else
                            <span class="text-gray-300 text-sm">—</span>
                        @endif
                    </td>

                    {{-- Deadline --}}
                    <td>
                        @if($item->deadline)
                            <span class="text-sm {{ $isOverdue ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                                {{ $item->deadline->format('d M Y') }}
                            </span>
                            @if($isOverdue)
                                <div class="text-xs font-bold text-red-500">OVERDUE</div>
                            @endif
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    {{-- Submitted --}}
                    <td class="text-xs text-gray-500">
                        {{ ($item->requested_at ?? $item->created_at)->format('d M Y') }}
                    </td>

                    {{-- Status --}}
                    <td>
                        <span class="sb sb-{{ $item->status }}">
                            @if($item->status === 'pending') <i class="fas fa-clock text-[10px]"></i>
                            @elseif($item->status === 'approved') <i class="fas fa-check text-[10px]"></i>
                            @elseif($item->status === 'rejected') <i class="fas fa-times text-[10px]"></i>
                            @elseif($item->status === 'fulfilled') <i class="fas fa-bolt text-[10px]"></i>
                            @elseif($item->status === 'closed') <i class="fas fa-lock text-[10px]"></i>
                            @else <i class="fas fa-search text-[10px]"></i>
                            @endif
                            {{ ucfirst(str_replace('_',' ',$item->status)) }}
                        </span>
                        @if($item->approvedBy)
                            <div class="text-xs text-gray-400 mt-1">by {{ $item->approvedBy->name }}</div>
                        @endif
                    </td>

                    {{-- Actions --}}
                    <td>
                        <div class="flex items-center gap-1.5 flex-wrap">

                            {{-- Primary: Review button for pending/under_review --}}
                            @if(in_array($item->status, ['pending','under_review']))
                                @can('approve employee request')
                                <button class="ab ab-review review-btn"
                                    data-id="{{ $item->id }}"
                                    data-employee="{{ $item->employee?->name }}"
                                    data-category="{{ $item->category }}"
                                    data-request_type="{{ $item->request_type }}"
                                    data-type="{{ \App\Models\EmployeeRequest::requestTypeLabel($item->request_type) }}"
                                    data-amount="{{ $item->amount }}"
                                    data-reason="{{ e($item->reason) }}"
                                    data-status="{{ $item->status }}"
                                    data-deadline="{{ $item->deadline?->format('d M Y') }}"
                                    data-submitted="{{ ($item->requested_at ?? $item->created_at)->format('d M Y') }}"
                                    data-remarks="{{ e($item->remarks) }}"
                                    data-image="{{ $item->image ? asset($item->image) : '' }}"
                                    data-metadata='@json($item->metadata ?? [])'
                                    title="Review & Approve/Reject">
                                    <i class="fas fa-clipboard-check text-xs"></i> Review
                                </button>
                                @endcan
                            @endif

                            {{-- Disburse --}}
                            @if($item->category === 'request' && $item->status === 'approved' && !$item->disbursement)
                                @can('disburse employee request')
                                <button class="ab ab-disburse disburse-btn"
                                    data-id="{{ $item->id }}"
                                    data-amount="{{ $item->amount }}"
                                    data-name="{{ $item->employee?->name ?? '—' }}"
                                    data-type="{{ \App\Models\EmployeeRequest::requestTypeLabel($item->request_type) }}"
                                    title="Record Disbursement">
                                    <i class="fas fa-money-bill-wave text-xs"></i> Disburse
                                </button>
                                @endcan
                            @endif

                            {{-- Recovery --}}
                            {{-- @if($item->category === 'request' && $item->status === 'fulfilled' && in_array($item->request_type, ['advance_salary','loan']) && $item->remainingBalance() > 0)
                                @can('disburse employee request')
                                <button class="ab ab-recover recover-btn"
                                    data-id="{{ $item->id }}"
                                    data-amount="{{ $item->amount }}"
                                    data-recovered="{{ $item->totalRecovered() }}"
                                    data-remaining="{{ $item->remainingBalance() }}"
                                    title="Add Recovery Installment">
                                    <i class="fas fa-undo text-xs"></i> Recover
                                </button>
                                @endcan
                            @endif --}}

                            {{-- Fulfill (require, approved) --}}
                            @if($item->category === 'require' && $item->status === 'approved')
                                @if(Auth::id() == $item->employee_id || Auth::user()->hasPermissionTo('fulfill employee request'))
                                <button class="ab ab-fulfill fulfill-btn"
                                    data-id="{{ $item->id }}" title="Mark Fulfilled">
                                    <i class="fas fa-check-double text-xs"></i> Fulfill
                                </button>
                                @endif
                            @endif

                            {{-- Verify --}}
                            @if($item->category === 'require' && $item->status === 'fulfilled' && $item->requireAssignment)
                                @can('verify require assignment')
                                <button class="ab ab-verify verify-btn"
                                    data-id="{{ $item->requireAssignment->id }}" title="Verify Fulfillment">
                                    <i class="fas fa-clipboard-check text-xs"></i> Verify
                                </button>
                                @endcan
                            @endif

                            {{-- Fast-track --}}
                            @if($item->request_type === 'emergency_fund' && $item->status === 'pending')
                                @can('approve employee request')
                                <button class="ab ab-fast fast-track-btn"
                                    data-id="{{ $item->id }}" title="Fast-Track Emergency">
                                    <i class="fas fa-bolt text-xs"></i>
                                </button>
                                @endcan
                            @endif

                            {{-- NOC PDF --}}
                            @if($item->request_type === 'noc_certificate' && in_array($item->status, ['approved','fulfilled']))
                                <a href="{{ route('role.employee-requests.noc-pdf', ['role' => $role, 'id' => $item->id]) }}"
                                    class="ab ab-pdf" title="Download NOC PDF" target="_blank">
                                    <i class="fas fa-file-pdf text-xs"></i>
                                </a>
                            @endif

                            {{-- Close --}}
                            @if($item->status === 'fulfilled')
                                @can('approve employee request')
                                <button class="ab ab-close close-btn"
                                    data-id="{{ $item->id }}" title="Close">
                                    <i class="fas fa-lock text-xs"></i>
                                </button>
                                @endcan
                            @endif

                            {{-- Edit --}}
                            @can('edit employee request')
                            @if($item->status === 'pending')
                            <button class="ab ab-edit edit-item-btn"
                                data-id="{{ $item->id }}"
                                data-employee_id="{{ $item->employee_id }}"
                                data-category="{{ $item->category }}"
                                data-request_type="{{ $item->request_type }}"
                                data-amount="{{ $item->amount }}"
                                data-reason="{{ $item->reason }}"
                                data-deadline="{{ $item->deadline?->format('Y-m-d') }}"
                                data-image="{{ $item->image ? asset($item->image) : '' }}"
                                data-metadata='@json($item->metadata ?? [])'
                                title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            @endif
                            @endcan

                            {{-- Delete --}}
                            @can('delete employee request')
                            <button class="ab ab-delete"
                                onclick="confirmDelete('{{ $item->id }}','{{ addslashes($item->employee?->name) }}')"
                                title="Delete">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                            @endcan

                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-16">
                        <div class="flex flex-col items-center gap-3 text-gray-300">
                            <i class="fas fa-inbox text-5xl"></i>
                            <p class="text-sm font-medium text-gray-400">No records found</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="px-4 py-3 border-t border-gray-100">
        {{ $datas->appends(request()->all())->links() }}
    </div>

</div>
</div>

{{-- ════════════════════════════════════════════
     REVIEW MODAL  (Approve / Reject)
════════════════════════════════════════════ --}}
<div id="reviewModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeReview()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg pointer-events-auto flex flex-col max-h-[90vh]">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-800">Review Request</h3>
                    <p class="text-xs text-gray-400" id="rv_subtitle">Approve or reject this request</p>
                </div>
            </div>
            <button onclick="closeReview()" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">

            {{-- Employee + status --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div id="rv_avatar" class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600
                                text-white flex items-center justify-center text-sm font-bold flex-shrink-0">?</div>
                    <div>
                        <div id="rv_name" class="text-sm font-bold text-gray-800">—</div>
                        <div class="text-xs text-gray-400">Employee</div>
                    </div>
                </div>
                <span id="rv_status_badge" class="sb"></span>
            </div>

            {{-- Request details grid --}}
            <div class="grid grid-cols-2 gap-3 bg-gray-50 rounded-xl p-4">
                <div>
                    <div class="review-label">Category</div>
                    <div id="rv_category" class="review-value">—</div>
                </div>
                <div>
                    <div class="review-label">Type</div>
                    <div id="rv_type" class="review-value">—</div>
                </div>
                <div>
                    <div class="review-label">Amount</div>
                    <div id="rv_amount" class="review-value">—</div>
                </div>
                <div>
                    <div class="review-label">Deadline</div>
                    <div id="rv_deadline" class="review-value">—</div>
                </div>
                <div class="col-span-2">
                    <div class="review-label">Submitted</div>
                    <div id="rv_submitted" class="review-value">—</div>
                </div>
            </div>

            {{-- Reason --}}
            <div id="rv_reason_wrap" class="hidden">
                <div class="review-label mb-1">Reason / Description</div>
                <div id="rv_reason" class="text-sm text-gray-700 bg-blue-50 rounded-xl px-4 py-3 border border-blue-100"></div>
            </div>

            {{-- Attachment --}}
            <div id="rv_attachment_wrap" class="hidden">
                <div class="review-label mb-2">Attachment</div>
                {{-- Image preview --}}
                <div id="rv_image_preview_wrap" class="hidden">
                    <a id="rv_image_link" href="#" target="_blank">
                        <img id="rv_image_thumb" src="" alt="attachment"
                            class="max-h-48 rounded-xl border border-gray-200 object-contain cursor-zoom-in w-full">
                    </a>
                </div>
                {{-- PDF / doc download --}}
                <div id="rv_file_download_wrap" class="hidden">
                    <a id="rv_file_link" href="#" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 transition">
                        <i class="fas fa-file-download text-blue-500"></i>
                        <span id="rv_file_name">Download File</span>
                    </a>
                </div>
            </div>

            {{-- Additional Fields --}}
            <div id="rv_additional_fields_wrap" class="hidden">
                <div class="review-label mb-2">Additional Fields</div>
                <div id="rv_additional_fields" class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 grid grid-cols-1 md:grid-cols-2 gap-3"></div>
            </div>

            {{-- Previous remarks --}}
            <div id="rv_prev_remarks_wrap" class="hidden">
                <div class="review-label mb-1">Previous Remarks</div>
                <div id="rv_prev_remarks" class="text-sm text-gray-600 bg-amber-50 rounded-xl px-4 py-3 border border-amber-200 italic"></div>
            </div>

            {{-- Decision section --}}
            <div class="review-section">
                <div class="review-label mb-2">Decision Remarks</div>
                <textarea id="rv_remarks" rows="3"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none"
                    placeholder="Add remarks (required for rejection, optional for approval)…"></textarea>
            </div>

        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button onclick="closeReview()"
                class="px-4 py-2 text-sm font-semibold text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                Cancel
            </button>
            <div class="flex gap-2">
                <button id="doReject"
                    class="inline-flex items-center gap-2 px-5 py-2 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-lg transition">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button id="doApprove"
                    class="inline-flex items-center gap-2 px-5 py-2 text-sm font-bold text-white bg-green-600 hover:bg-green-700 rounded-lg transition">
                    <i class="fas fa-check"></i> Approve
                </button>
            </div>
        </div>

    </div>
    </div>
</div>

{{-- Other modals --}}
@include('employee-requests.create-modal')
@include('employee-requests.edit-modal')
@include('employee-requests.delete-modal')
@include('employee-requests.reject-modal')
@include('employee-requests.disburse-modal')
@include('employee-requests.fulfill-modal')
@include('employee-requests.recover-modal')

@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const reqTypeApiUrl = "{{ route('role.projects.ajax.department-types', ['role' => $role]) }}";
const fieldDefsUrl  = "{{ route('role.projects.ajax.field-definitions', ['role' => $role]) }}";
const requireTypesData = @json($requireTypes ?? []);

$(document).ready(function () {
    $('.select2').select2();
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    // Filter toggle
    document.querySelector('.filter-header').addEventListener('click', function () {
        this.classList.toggle('active');
        document.querySelector('.filter-content').classList.toggle('active');
    });

    // ── Create modal ──
    $('.create-new-btn').click(function () {
        $('#createModal').removeClass('hidden');
        toggleCategoryFields($('#create_category').val(), '#create');
    });
    $(document).on('click', '.modal-close-create', function () { $('#createModal').addClass('hidden'); });

    $('#create_request_type').select2({
        placeholder: '-- Select Type --',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#createModal'),
    });

    // select2 change fires on the native element — bind after select2 init
    $('#create_category').on('change', function () { toggleCategoryFields($(this).val(), '#create'); });

    $(document).on('change', '#create_request_type', function () {
        const category = $('#create_category').val();
        if (category === 'require') {
            const $opt   = $(this).find('option:selected');
            const typeId = $opt.data('id') || '';
            const typeName = $opt.text();
            $('#create_require_type_id').val(typeId);
            loadRequireFields(typeId, typeName);
            toggleMetaFields('');
        } else {
            toggleMetaFields($(this).val());
        }
    });
    toggleMetaFields($('#create_request_type').val());

    $('#createSubmit').click(function (e) {
        e.preventDefault();
        $.ajax({
            url: $('#createForm').attr('action'), method: 'POST',
            data: new FormData($('#createForm')[0]), processData: false, contentType: false,
            success: function (res) {
                if (res.success) { Swal.fire('Done', 'Created successfully!', 'success'); $('#createModal').addClass('hidden'); setTimeout(() => location.reload(), 800); }
                else { Swal.fire('Error', res.message, 'error'); }
            },
            error: function () { Swal.fire('Error', 'Something went wrong.', 'error'); }
        });
    });

    // ── Edit modal ──
    $(document).on('click', '.edit-item-btn', function () {
        const d = $(this).data();
        const metadata = (typeof d.metadata === 'object' && d.metadata !== null) ? d.metadata : {};

        // Reset all meta panels
        toggleEditMetaFields('');

        $('#editItemId').val(d.id);
        $('#edit_employee_id').val(d.employee_id).trigger('change');
        $('#edit_amount').val(d.amount);
        $('#edit_reason').val(d.reason);
        $('#edit_deadline').val(d.deadline);

        // Reset image section
        $('#edit_image_input').val('');
        resetEditImagePreview();
        $('#edit_current_image_wrap').addClass('hidden');

        if (d.category === 'require') {
            $('#edit_category').val('require').trigger('change.select2');
            $('#edit_amount_wrap').show();
            $('#edit_deadline_wrap').show();
            $('#edit_image_wrap').show();

            // Show existing attachment link if present
            if (d.image) {
                $('#edit_current_image_link').attr('href', d.image);
                $('#edit_current_image_wrap').removeClass('hidden');
            }

            // Clear dynamic section
            $('#edit_require_dynamic_section').hide();
            $('#edit_require_dynamic_fields').html('');
            $('#edit_require_type_id').val('');

            const requireTypeId = metadata.require_type_id || '';
            const customFields  = metadata.custom_fields  || {};

            // Load types from pre-loaded data then pre-select and load field defs
            if (requireTypesData.length) {
                let opts = '<option value="">-- Select Type --</option>';
                requireTypesData.forEach(t => { opts += `<option value="${t.name}" data-id="${t.id}">${t.name}</option>`; });
                $('#edit_request_type').html(opts);

                let matchedId = '', matchedName = '';
                requireTypesData.forEach(t => {
                    if (String(t.id) === String(requireTypeId)) {
                        matchedId = t.id; matchedName = t.name;
                    }
                });
                if (matchedId) {
                    $('#edit_request_type').val(matchedName);
                    $('#edit_require_type_id').val(matchedId);
                    loadEditRequireFields(matchedId, matchedName, customFields);
                }
            }
        } else {
            $('#edit_category').val(d.category).trigger('change.select2');
            $('#edit_amount_wrap').show();
            $('#edit_deadline_wrap').hide();
            $('#edit_image_wrap').hide();
            $('#edit_require_dynamic_section').hide();
            $('#edit_require_dynamic_fields').html('');

            // Rebuild static request type options
            const types = @json(\App\Models\EmployeeRequest::REQUEST_TYPES);
            $('#edit_request_type').empty();
            if (types[d.category]) {
                $.each(types[d.category], function (val, label) {
                    $('#edit_request_type').append(new Option(label, val));
                });
            }
            $('#edit_request_type').val(d.request_type);

            // Show & pre-populate meta panel for the selected type
            toggleEditMetaFields(d.request_type);
            populateEditMetaFields(metadata);
        }

        $('#editModal').removeClass('hidden');
    });

    // Category change in edit modal (user-triggered)
    $(document).on('change', '#edit_category', function () { toggleCategoryFields($(this).val(), '#edit'); });

    // Type change in edit modal
    $(document).on('change', '#edit_request_type', function () {
        if ($('#edit_category').val() === 'require') {
            const $opt = $(this).find('option:selected');
            const typeId   = $opt.data('id') || '';
            const typeName = $opt.text();
            $('#edit_require_type_id').val(typeId);
            loadEditRequireFields(typeId, typeName, {});
        } else {
            toggleEditMetaFields($(this).val());
        }
    });

    $(document).on('click', '.modal-close-edit', function () { $('#editModal').addClass('hidden'); });

    $('#editSubmit').click(function () {
        const id  = $('#editItemId').val();
        const url = '{{ route("role.employee-requests.update", ["role" => $role, "employee_request" => "__ID__"]) }}'.replace('__ID__', id);
        $.ajax({
            url, method: 'POST',
            data: new FormData($('#editForm')[0]), processData: false, contentType: false,
            success: function (res) {
                if (res.success) { Swal.fire('Done', 'Updated!', 'success'); $('#editModal').addClass('hidden'); setTimeout(() => location.reload(), 800); }
                else { Swal.fire('Error', res.message, 'error'); }
            },
            error: function () { Swal.fire('Error', 'Something went wrong.', 'error'); }
        });
    });

    // ── Delete ──
    $(document).on('click', '.modal-close-delete', function () { $('#deleteModal').addClass('hidden'); });
    $('#confirmDeleteBtn').click(function () {
        $.ajax({
            url: $(this).data('action'), method: 'DELETE', data: { item_id: $(this).data('item-id') },
            success: function (res) {
                if (res.success) { Swal.fire('Done', 'Deleted.', 'success'); $('#deleteModal').addClass('hidden'); setTimeout(() => location.reload(), 500); }
                else { Swal.fire('Error', res.message, 'error'); }
            }
        });
    });

    // ── REVIEW MODAL (Approve / Reject) ──
    let currentReviewId = null;

    $(document).on('click', '.review-btn', function () {
        const d = $(this).data();
        currentReviewId = d.id;

        // Populate header
        $('#rv_name').text(d.employee);
        $('#rv_avatar').text((d.employee || '?').charAt(0).toUpperCase());
        $('#rv_subtitle').text('Request #' + d.id);

        // Status badge
        const statusMap = {
            pending:      'sb-pending',
            under_review: 'sb-under_review',
            approved:     'sb-approved',
            rejected:     'sb-rejected',
            fulfilled:    'sb-fulfilled',
            closed:       'sb-closed'
        };
        $('#rv_status_badge')
            .removeClass(Object.values(statusMap).join(' '))
            .addClass(statusMap[d.status] || '')
            .text(d.status.replace('_', ' ').replace(/^\w/, c => c.toUpperCase()));

        // Details
        $('#rv_category').html(d.category === 'request'
            ? '<span class="cb cb-request">📤 Request</span>'
            : '<span class="cb cb-require">📥 Require</span>');
        $('#rv_type').text(d.type || '—');
        $('#rv_amount').text(d.amount ? '৳' + parseFloat(d.amount).toLocaleString('en-BD', {minimumFractionDigits:2}) : '—');
        $('#rv_deadline').text(d.deadline || '—');
        $('#rv_submitted').text(d.submitted || '—');

        // Reason
        if (d.reason) {
            $('#rv_reason').text(d.reason);
            $('#rv_reason_wrap').removeClass('hidden');
        } else {
            $('#rv_reason_wrap').addClass('hidden');
        }

        // Attachment
        $('#rv_attachment_wrap, #rv_image_preview_wrap, #rv_file_download_wrap').addClass('hidden');
        if (d.image) {
            $('#rv_attachment_wrap').removeClass('hidden');
            const isPdf = d.image.match(/\.(pdf|doc|docx)(\?|$)/i);
            if (isPdf) {
                const parts = d.image.split('/');
                $('#rv_file_name').text(parts[parts.length - 1] || 'Download File');
                $('#rv_file_link').attr('href', d.image);
                $('#rv_file_download_wrap').removeClass('hidden');
            } else {
                $('#rv_image_thumb').attr('src', d.image);
                $('#rv_image_link').attr('href', d.image);
                $('#rv_image_preview_wrap').removeClass('hidden');
            }
        }

        // Additional fields from metadata
        let metadata = {};
        try {
            const raw = d.metadata;
            if (typeof raw === 'object' && raw !== null) { metadata = raw; }
            else if (raw) { metadata = JSON.parse(raw); }
        } catch(e) { metadata = {}; }
        $('#rv_additional_fields').html('');
        $('#rv_additional_fields_wrap').addClass('hidden');

        if (d.category === 'require' && metadata.require_type_id && metadata.custom_fields && Object.keys(metadata.custom_fields).length) {
            // Fetch field definitions to get labels
            $.get(fieldDefsUrl, { project_department_id: metadata.require_type_id }, function (res) {
                if (res.success && res.data.length) {
                    let html = '';
                    res.data.forEach(function (f) {
                        const val = metadata.custom_fields[f.id];
                        if (val !== undefined && val !== '' && val !== null) {
                            html += `<div><div class="text-xs font-semibold text-indigo-700 mb-0.5">${f.label}</div><div class="text-sm text-gray-800 font-medium">${val}</div></div>`;
                        }
                    });
                    if (html) {
                        $('#rv_additional_fields').html(html);
                        $('#rv_additional_fields_wrap').removeClass('hidden');
                    }
                }
            });
        } else if (d.category === 'request') {
            const metaLabelMap = {
                meta_project_id: 'Project ID', meta_expense_type: 'Expense Type',
                meta_destination: 'Destination', meta_purpose: 'Purpose',
                meta_trip_from: 'Trip From', meta_trip_to: 'Trip To',
                meta_policy_title: 'Policy Title',
                meta_training_name: 'Training Name', meta_provider: 'Provider',
                meta_emergency_type: 'Emergency Type',
                meta_document_type: 'Document Type', meta_noc_purpose: 'Purpose / Addressed To',
                meta_bonus_type: 'Bonus Type', meta_tax_flag: 'Tax Applicable',
                meta_item_name: 'Item Name', meta_quantity: 'Quantity', meta_urgency: 'Urgency',
                meta_asset_laptop: 'Laptop', meta_asset_id_card: 'ID Card',
                meta_asset_keys: 'Keys', meta_asset_other: 'Other Items',
                meta_review_period: 'Review Period', meta_review_cycle: 'Cycle',
                meta_period_type: 'Period Type', meta_period_start: 'Period Start', meta_period_end: 'Period End',
                meta_req_category: 'Req. Category', meta_req_cost: 'Estimated Cost', meta_req_vendor: 'Vendor',
                meta_interior_project_id: 'Interior Project', meta_material_spec: 'Material Spec',
                meta_material_qty: 'Quantity', meta_material_unit: 'Unit', meta_interior_cost: 'Estimated Cost',
            };
            let html = '';
            $.each(metaLabelMap, function (key, label) {
                const val = metadata[key];
                if (val !== undefined && val !== '' && val !== null && val !== false && val !== 0) {
                    const display = val === true || val === 1 || val === '1' ? 'Yes' : val;
                    html += `<div><div class="text-xs font-semibold text-indigo-700 mb-0.5">${label}</div><div class="text-sm text-gray-800 font-medium">${display}</div></div>`;
                }
            });
            if (html) {
                $('#rv_additional_fields').html(html);
                $('#rv_additional_fields_wrap').removeClass('hidden');
            }
        }

        // Prev remarks
        if (d.remarks) {
            $('#rv_prev_remarks').text(d.remarks);
            $('#rv_prev_remarks_wrap').removeClass('hidden');
        } else {
            $('#rv_prev_remarks_wrap').addClass('hidden');
        }

        $('#rv_remarks').val('');
        $('#reviewModal').removeClass('hidden');
    });

    $('#doApprove').click(function () {
        if (!currentReviewId) return;
        const url = '{{ route("role.employee-requests.approve", ["role" => $role, "id" => "__ID__"]) }}'.replace('__ID__', currentReviewId);
        const remarks = $('#rv_remarks').val();
        $.post(url, { remarks }, function (res) {
            if (res.success) {
                Swal.fire({ icon:'success', title:'Approved!', text: res.message, timer:1500, showConfirmButton:false });
                closeReview();
                setTimeout(() => location.reload(), 1600);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }).fail(function () { Swal.fire('Error', 'Something went wrong.', 'error'); });
    });

    $('#doReject').click(function () {
        if (!currentReviewId) return;
        const remarks = $('#rv_remarks').val().trim();
        if (!remarks) {
            Swal.fire('Required', 'Please enter rejection reason before rejecting.', 'warning');
            $('#rv_remarks').focus();
            return;
        }
        const url = '{{ route("role.employee-requests.reject", ["role" => $role, "id" => "__ID__"]) }}'.replace('__ID__', currentReviewId);
        $.post(url, { remarks }, function (res) {
            if (res.success) {
                Swal.fire({ icon:'success', title:'Rejected', text: res.message, timer:1500, showConfirmButton:false });
                closeReview();
                setTimeout(() => location.reload(), 1600);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }).fail(function () { Swal.fire('Error', 'Something went wrong.', 'error'); });
    });

    // ── Disburse ──
    $(document).on('click', '.disburse-btn', function () {
        const d = $(this).data();
        $('#disburseRequestId').val(d.id);
        $('#disburse_amount').val(d.amount);
        $('#disburse_requested_amount').text(d.amount ? parseFloat(d.amount).toFixed(2) : '—');
        $('#disburse_employee_name').text(d.name || '—');
        $('#disburse_request_type').text(d.type || '—');
        $('#disburse_file_label').text('Click to attach file...');
        $('#disburse_attachment').val('');
        $('textarea[name="note"]').val('');
        $('#disburseModal').removeClass('hidden');
    });
    $(document).on('click', '.modal-close-disburse', function () { $('#disburseModal').addClass('hidden'); });
    $('#disburseSubmit').click(function () {
        const id   = $('#disburseRequestId').val();
        const url  = '{{ route("role.employee-requests.disburse", ["role" => $role, "id" => "__ID__"]) }}'.replace('__ID__', id);
        $.ajax({
            url, method: 'POST', data: new FormData($('#disburseForm')[0]), processData: false, contentType: false,
            success: function (res) {
                if (res.success) { Swal.fire('Done', res.message, 'success'); $('#disburseModal').addClass('hidden'); setTimeout(() => location.reload(), 800); }
                else { Swal.fire('Error', res.message, 'error'); }
            },
            error: function () { Swal.fire('Error', 'Something went wrong.', 'error'); }
        });
    });

    // ── Recover ──
    $(document).on('click', '.recover-btn', function () {
        const d = $(this).data();
        $('#recoverRequestId').val(d.id);
        $('#recover_total_amount').text(parseFloat(d.amount).toFixed(2));
        $('#recover_recovered_amount').text(parseFloat(d.recovered).toFixed(2));
        $('#recover_remaining_balance').text(parseFloat(d.remaining).toFixed(2));
        $('#recover_deducted_amount').val('').attr('max', d.remaining);
        $('#recover_note').val('');
        $('#recoverModal').removeClass('hidden');
    });
    $(document).on('click', '.modal-close-recover', function () { $('#recoverModal').addClass('hidden'); });
    $('#recoverSubmit').click(function () {
        const id  = $('#recoverRequestId').val();
        const url = '{{ route("role.employee-requests.recover", ["role" => $role, "id" => "__ID__"]) }}'.replace('__ID__', id);
        $.post(url, {
            deducted_amount: $('#recover_deducted_amount').val(),
            deducted_at:     $('#recover_deducted_at').val(),
            note:            $('#recover_note').val(),
        }, function (res) {
            if (res.success) { Swal.fire('Done', res.message, 'success'); $('#recoverModal').addClass('hidden'); setTimeout(() => location.reload(), 800); }
            else { Swal.fire('Error', res.message, 'error'); }
        });
    });

    // ── Fulfill ──
    $(document).on('click', '.fulfill-btn', function () {
        $('#fulfillRequestId').val($(this).data('id'));
        $('#fulfillNote').val('');
        $('#fulfillModal').removeClass('hidden');
    });
    $(document).on('click', '.modal-close-fulfill', function () { $('#fulfillModal').addClass('hidden'); });
    $('#fulfillSubmit').click(function () {
        const id  = $('#fulfillRequestId').val();
        const url = '{{ route("role.employee-requests.fulfill", ["role" => $role, "id" => "__ID__"]) }}'.replace('__ID__', id);
        $.post(url, { fulfillment_note: $('#fulfillNote').val() }, function (res) {
            if (res.success) { Swal.fire('Done', res.message, 'success'); $('#fulfillModal').addClass('hidden'); setTimeout(() => location.reload(), 800); }
            else { Swal.fire('Error', res.message, 'error'); }
        });
    });

    // ── Verify ──
    $(document).on('click', '.verify-btn', function () {
        const id  = $(this).data('id');
        const url = '{{ route("role.require-assignments.verify", ["role" => $role, "id" => "__ID__"]) }}'.replace('__ID__', id);
        Swal.fire({ title: 'Verify Fulfillment?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Verify' })
            .then(r => { if (r.isConfirmed) $.ajax({ url, method: 'PUT', success: function (res) {
                if (res.success) { Swal.fire('Done', res.message, 'success'); setTimeout(() => location.reload(), 800); }
                else { Swal.fire('Error', res.message, 'error'); }
            }}); });
    });

    // ── Fast-track ──
    $(document).on('click', '.fast-track-btn', function () {
        const id  = $(this).data('id');
        const url = '{{ route("role.employee-requests.fast-track", ["role" => $role, "id" => "__ID__"]) }}'.replace('__ID__', id);
        Swal.fire({ title: '⚡ Fast-Track Emergency?', text: 'Moves to Under Review immediately.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Fast-Track', confirmButtonColor: '#f59e0b' })
            .then(r => { if (r.isConfirmed) $.post(url, {}, function (res) {
                if (res.success) { Swal.fire('Done', res.message, 'success'); setTimeout(() => location.reload(), 800); }
                else { Swal.fire('Error', res.message, 'error'); }
            }); });
    });

    // ── Close ──
    $(document).on('click', '.close-btn', function () {
        const id  = $(this).data('id');
        const url = '{{ route("role.employee-requests.close", ["role" => $role, "id" => "__ID__"]) }}'.replace('__ID__', id);
        Swal.fire({ title: 'Close this request?', icon: 'question', showCancelButton: true, confirmButtonText: 'Close It', confirmButtonColor: '#6b7280' })
            .then(r => { if (r.isConfirmed) $.post(url, {}, function (res) {
                if (res.success) { Swal.fire('Done', res.message, 'success'); setTimeout(() => location.reload(), 800); }
                else { Swal.fire('Error', res.message, 'error'); }
            }); });
    });

    // ── Reset filter ──
    document.getElementById('resetBtn').addEventListener('click', function (e) {
        e.preventDefault();
        window.location = '{{ route("role.employee-requests.index", ["role" => $role]) }}';
    });
});

function closeReview() {
    $('#reviewModal').addClass('hidden');
}

function setCategory(cat) {
    document.getElementById('hiddenCategory').value = cat;
    document.getElementById('filterForm').submit();
}

function toggleMetaFields(type) {
    $('#meta_project_payment_wrap,#meta_travel_wrap,#meta_policy_wrap,#meta_training_wrap,' +
      '#meta_emergency_wrap,#meta_noc_wrap,#meta_bonus_wrap,#meta_equipment_wrap,' +
      '#meta_asset_return_wrap,#meta_performance_wrap,#meta_timesheet_wrap,' +
      '#meta_office_req_wrap,#meta_interior_wrap').hide();
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

    if (category === 'require') {
        $amount.show(); $deadline.show();
        const imgWrap  = prefix === '#create' ? '#create_image_wrap'              : '#edit_image_wrap';
        const dynSec   = prefix === '#create' ? '#create_require_dynamic_section' : '#edit_require_dynamic_section';
        const dynFld   = prefix === '#create' ? '#create_require_dynamic_fields'  : '#edit_require_dynamic_fields';
        const typeInp  = prefix === '#create' ? '#create_require_type_id'         : '#edit_require_type_id';
        $(imgWrap).show();
        $(dynSec).hide();
        $(dynFld).html('');
        $(typeInp).val('');
        toggleMetaFields('');
        if (requireTypesData.length) {
            let opts = '<option value="">-- Select Type --</option>';
            requireTypesData.forEach(t => { opts += `<option value="${t.name}" data-id="${t.id}">${t.name}</option>`; });
            $typesel.html(opts).trigger('change');
        } else {
            $typesel.html('<option value="">-- No types configured --</option>').trigger('change');
        }
    } else {
        if (types[category]) $.each(types[category], function (val, label) { $typesel.append(new Option(label, val)); });
        if (category === 'request') { $amount.show(); $deadline.hide(); }
        else { $amount.hide(); $deadline.show(); }
        if (prefix === '#create') {
            $('#create_image_wrap').hide();
            $('#create_image_input').val(''); resetCreateImagePreview();
            $('#create_require_dynamic_section').hide();
            $('#create_require_dynamic_fields').html('');
            $typesel.trigger('change');
            toggleMetaFields($typesel.val());
        } else if (prefix === '#edit') {
            $('#edit_image_wrap').hide();
            $('#edit_require_dynamic_section').hide();
            $('#edit_require_dynamic_fields').html('');
        }
    }
}

function toggleEditMetaFields(type) {
    $('#edit_meta_project_payment_wrap,#edit_meta_travel_wrap,#edit_meta_policy_wrap,' +
      '#edit_meta_training_wrap,#edit_meta_emergency_wrap,#edit_meta_noc_wrap,' +
      '#edit_meta_bonus_wrap,#edit_meta_equipment_wrap,#edit_meta_asset_return_wrap,' +
      '#edit_meta_performance_wrap,#edit_meta_timesheet_wrap,' +
      '#edit_meta_office_req_wrap,#edit_meta_interior_wrap').hide();
    const map = {
        'project_payment':       '#edit_meta_project_payment_wrap',
        'travel_allowance':      '#edit_meta_travel_wrap',
        'policy_acknowledgment': '#edit_meta_policy_wrap',
        'training_completion':   '#edit_meta_training_wrap',
        'emergency_fund':        '#edit_meta_emergency_wrap',
        'noc_certificate':       '#edit_meta_noc_wrap',
        'bonus_incentive':       '#edit_meta_bonus_wrap',
        'equipment_resource':    '#edit_meta_equipment_wrap',
        'asset_return':          '#edit_meta_asset_return_wrap',
        'performance_review':    '#edit_meta_performance_wrap',
        'timesheet_log':         '#edit_meta_timesheet_wrap',
        'office_requisition':    '#edit_meta_office_req_wrap',
        'interior_project':      '#edit_meta_interior_wrap',
    };
    if (map[type]) $(map[type]).show();
}

function populateEditMetaFields(metadata) {
    if (!metadata || typeof metadata !== 'object') return;
    const fieldMap = {
        'meta_project_id':       '[name="meta_project_id"]',
        'meta_expense_type':     '[name="meta_expense_type"]',
        'meta_destination':      '[name="meta_destination"]',
        'meta_purpose':          '[name="meta_purpose"]',
        'meta_trip_from':        '[name="meta_trip_from"]',
        'meta_trip_to':          '[name="meta_trip_to"]',
        'meta_policy_title':     '[name="meta_policy_title"]',
        'meta_training_name':    '[name="meta_training_name"]',
        'meta_provider':         '[name="meta_provider"]',
        'meta_emergency_type':   '[name="meta_emergency_type"]',
        'meta_document_type':    '[name="meta_document_type"]',
        'meta_noc_purpose':      '[name="meta_noc_purpose"]',
        'meta_bonus_type':       '[name="meta_bonus_type"]',
        'meta_item_name':        '[name="meta_item_name"]',
        'meta_quantity':         '[name="meta_quantity"]',
        'meta_urgency':          '[name="meta_urgency"]',
        'meta_asset_other':      '[name="meta_asset_other"]',
        'meta_review_period':    '[name="meta_review_period"]',
        'meta_review_cycle':     '[name="meta_review_cycle"]',
        'meta_period_type':      '[name="meta_period_type"]',
        'meta_period_start':     '[name="meta_period_start"]',
        'meta_period_end':       '[name="meta_period_end"]',
        'meta_req_category':     '[name="meta_req_category"]',
        'meta_req_cost':         '[name="meta_req_cost"]',
        'meta_req_vendor':       '[name="meta_req_vendor"]',
        'meta_interior_project_id': '[name="meta_interior_project_id"]',
        'meta_material_spec':    '[name="meta_material_spec"]',
        'meta_material_qty':     '[name="meta_material_qty"]',
        'meta_material_unit':    '[name="meta_material_unit"]',
        'meta_interior_cost':    '[name="meta_interior_cost"]',
    };
    $.each(fieldMap, function (key, selector) {
        if (metadata[key] !== undefined) {
            $('#editForm ' + selector).val(metadata[key]);
        }
    });
    // Checkboxes
    $('#edit_meta_tax_flag').prop('checked', !!metadata.meta_tax_flag);
    $('#edit_meta_asset_laptop').prop('checked', !!metadata.meta_asset_laptop);
    $('#edit_meta_asset_id_card').prop('checked', !!metadata.meta_asset_id_card);
    $('#edit_meta_asset_keys').prop('checked', !!metadata.meta_asset_keys);
}

function buildRequireFieldHtml(field) {
    const required = field.required ? 'required' : '';
    const star     = field.required ? '<span class="text-red-500">*</span>' : '';
    const name     = `custom_fields[${field.id}]`;
    const id       = `req_cf_${field.id}`;
    const cls      = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500';
    let input = '';
    if (field.type === 'textarea') {
        input = `<textarea id="${id}" name="${name}" rows="2" ${required} class="${cls}" placeholder="${field.label}"></textarea>`;
    } else if (field.type === 'select' && field.options) {
        const opts = field.options.split(',').map(o => `<option value="${o.trim()}">${o.trim()}</option>`).join('');
        input = `<select id="${id}" name="${name}" ${required} class="${cls}"><option value="">Select...</option>${opts}</select>`;
    } else {
        const type = field.type === 'number' ? 'number' : (field.type === 'date' ? 'date' : 'text');
        input = `<input type="${type}" id="${id}" name="${name}" ${required} class="${cls}" placeholder="${field.label}">`;
    }
    return `<div><label for="${id}" class="block text-xs font-semibold text-gray-600 mb-1">${field.label} ${star}</label>${input}</div>`;
}

function loadRequireFields(typeId, typeName) {
    const $section   = $('#create_require_dynamic_section');
    const $container = $('#create_require_dynamic_fields');
    $section.hide();
    $container.html('');
    if (!typeId) return;
    $.get(fieldDefsUrl, { project_department_id: typeId }, function (res) {
        if (res.success && res.data.length) {
            $('#create_require_type_label').text(typeName + ' — Additional Fields');
            res.data.forEach(f => { $container.append(buildRequireFieldHtml(f)); });
            $section.show();
        }
    });
}

function previewCreateImage(input) {
    const file = input.files[0];
    if (!file) return;
    $('#create_image_filename').text(file.name).removeClass('hidden');
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function (e) {
            $('#create_image_thumb').attr('src', e.target.result);
            $('#create_image_preview').removeClass('hidden');
            $('#create_image_placeholder').hide();
        };
        reader.readAsDataURL(file);
    } else {
        $('#create_image_preview').addClass('hidden');
        $('#create_image_placeholder').show();
    }
}

function resetCreateImagePreview() {
    $('#create_image_preview').addClass('hidden');
    $('#create_image_placeholder').show();
    $('#create_image_filename').addClass('hidden').text('');
    $('#create_image_thumb').attr('src', '');
}

function loadEditRequireFields(typeId, typeName, customFields) {
    const $section   = $('#edit_require_dynamic_section');
    const $container = $('#edit_require_dynamic_fields');
    $section.hide();
    $container.html('');
    if (!typeId) return;
    $.get(fieldDefsUrl, { project_department_id: typeId }, function (res) {
        if (res.success && res.data.length) {
            $('#edit_require_type_label').text(typeName + ' — Additional Fields');
            res.data.forEach(f => {
                $container.append(buildRequireFieldHtml(f));
                // Pre-populate existing value from metadata
                if (customFields && customFields[f.id] !== undefined) {
                    const $el = $container.find('#req_cf_' + f.id);
                    if ($el.is(':checkbox')) { $el.prop('checked', !!customFields[f.id]); }
                    else { $el.val(customFields[f.id]); }
                }
            });
            $section.show();
        }
    });
}

function previewEditImage(input) {
    const file = input.files[0];
    if (!file) return;
    $('#edit_image_filename').text(file.name).removeClass('hidden');
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function (e) {
            $('#edit_image_thumb').attr('src', e.target.result);
            $('#edit_image_preview').removeClass('hidden');
            $('#edit_image_placeholder').hide();
        };
        reader.readAsDataURL(file);
    } else {
        $('#edit_image_preview').addClass('hidden');
        $('#edit_image_placeholder').show();
    }
}

function resetEditImagePreview() {
    $('#edit_image_preview').addClass('hidden');
    $('#edit_image_placeholder').show();
    $('#edit_image_filename').addClass('hidden').text('');
    $('#edit_image_thumb').attr('src', '');
}

function confirmDelete(id, name) {
    $('#deleteName').text(name);
    $('#confirmDeleteBtn')
        .data('item-id', id)
        .data('action', '{{ route("role.employee-requests.destroy", ["role" => $role, "employee_request" => "__ID__"]) }}'.replace('__ID__', id));
    $('#deleteModal').removeClass('hidden');
}
</script>
@endsection
