@extends('layout.app')

@section('meta-information')
    <title>Manage Accounts</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* ── Filter Panel ───────────────────────────── */
    .filter-container {
        margin: 15px 15px 0 15px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,.1);
    }
    .filter-container .filter-header {
        background-color: #f8f9fa;
        padding: 16px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid #3b82f6;
        transition: background-color .3s;
    }
    .filter-container .filter-header:hover { background-color: #e9ecef; }
    .filter-container .filter-header h3 { margin:0; font-size:18px; font-weight:600; color:#1f2937; }
    .filter-container .filter-header .toggle-icon { transition: transform .3s; }
    .filter-container .filter-header.active .toggle-icon { transform: rotate(180deg); }
    .filter-container .filter-content {
        background:#fff; padding:0; max-height:0; overflow:hidden;
        transition: max-height .3s ease-out, padding .3s ease-out;
    }
    .filter-container .filter-content.active { padding:20px; max-height:500px; }
    .filter-container .filter-row { display:flex; flex-wrap:wrap; gap:20px; margin-bottom:16px; }
    .filter-container .filter-group { flex:1; min-width:200px; }
    .filter-container .filter-group label { display:block; margin-bottom:6px; font-weight:500; color:#374151; }
    .filter-container .filter-group select,
    .filter-container .filter-group input {
        width:100%; padding:10px; border:1px solid #d1d5db;
        border-radius:6px; font-size:14px; transition:border-color .3s;
    }
    .filter-container .filter-group select:focus,
    .filter-container .filter-group input:focus {
        outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1);
    }
    .filter-container .filter-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:20px; }
    .filter-container .filter-actions button { padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; transition:all .3s; }
    .filter-container .filter-actions .apply-btn  { background:#3b82f6; color:#fff; border:none; }
    .filter-container .filter-actions .apply-btn:hover { background:#2563eb; }
    .filter-container .filter-actions .reset-btn  { background:#f8f9fa; color:#6b7280; border:1px solid #d1d5db; }
    .filter-container .filter-actions .reset-btn:hover { background:#e5e7eb; }

    /* ── Select2 height fix ─────────────────────── */
    .select2-container .select2-selection--single { height:42px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:40px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:42px; top:1px; right:3px; width:20px; }

    /* ── Pagination active page ─────────────────── */
    span [aria-current="page"] span {
        background-color:#2563eb !important; background:#2563eb !important;
        color:#fff; border-color:#2563eb;
    }

    /* ── Table ──────────────────────────────────── */
    .accounts-table { width:100%; border-collapse:collapse; }
    .accounts-table thead tr { background:#f1f5f9; }
    .accounts-table thead th {
        padding:12px 16px; text-align:left; font-weight:600;
        font-size:13px; color:#475569; text-transform:uppercase; letter-spacing:.5px;
        border-bottom:2px solid #e2e8f0;
    }
    .accounts-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .accounts-table tbody tr:hover { background:#f8fafc; }
    .accounts-table tbody td { padding:12px 16px; font-size:14px; color:#334155; vertical-align:middle; }

    /* ── Type badge ─────────────────────────────── */
    .type-badge {
        display:inline-block; padding:3px 10px; border-radius:999px;
        font-size:12px; font-weight:600; text-transform:capitalize;
    }
    .type-asset     { background:#dbeafe; color:#1d4ed8; }
    .type-liability { background:#fee2e2; color:#b91c1c; }
    .type-equity    { background:#d1fae5; color:#065f46; }
    .type-income    { background:#dcfce7; color:#15803d; }
    .type-expense   { background:#fef3c7; color:#92400e; }

    /* ── Status badge ───────────────────────────── */
    .status-badge {
        display:inline-block; padding:3px 10px; border-radius:999px;
        font-size:12px; font-weight:600;
    }
    .status-active   { background:#d1fae5; color:#065f46; }
    .status-inactive { background:#fee2e2; color:#b91c1c; }

    /* ── Action buttons ─────────────────────────── */
    .btn-action {
        display:inline-flex; align-items:center; justify-content:center;
        width:32px; height:32px; border-radius:6px; border:none;
        cursor:pointer; font-size:13px; transition:all .2s;
    }
    .btn-edit   { background:#dbeafe; color:#1d4ed8; }
    .btn-edit:hover { background:#bfdbfe; }
    .btn-delete { background:#fee2e2; color:#b91c1c; }
    .btn-delete:hover { background:#fecaca; }

    /* ── Modal ──────────────────────────────────── */
    .modal-overlay {
        position:fixed; inset:0; background:rgba(0,0,0,.5);
        display:flex; align-items:center; justify-content:center;
        z-index:9999; opacity:0; pointer-events:none; transition:opacity .25s;
    }
    .modal-overlay.show { opacity:1; pointer-events:all; }
    .modal-box {
        background:#fff; border-radius:12px; width:100%; max-width: 700px;
        max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25);
        transform:translateY(20px); transition:transform .25s;
    }
    .modal-overlay.show .modal-box { transform:translateY(0); }
    .modal-header {
        display:flex; justify-content:space-between; align-items:center;
        padding:20px 24px; border-bottom:1px solid #e2e8f0;
        background:#2563eb; border-radius:12px 12px 0 0;
    }
    .modal-header h4 { margin:0; color:#fff; font-size:17px; font-weight:600; }
    .modal-close {
        background:rgba(255,255,255,.2); border:none; color:#fff;
        width:32px; height:32px; border-radius:6px; cursor:pointer;
        font-size:16px; display:flex; align-items:center; justify-content:center;
        transition:background .2s;
    }
    .modal-close:hover { background:rgba(255,255,255,.35); }
    .modal-body { padding:24px; }
    .modal-footer { padding:16px 24px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px; }

    /* ── Form controls inside modal ─────────────── */
    .form-group { margin-bottom:18px; }
    .form-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
    .form-group label span.req { color:#ef4444; margin-left:2px; }
    .form-group .form-control {
        width:100%; padding:10px 12px; border:1px solid #d1d5db;
        border-radius:6px; font-size:14px; color:#1f2937; transition:border-color .2s;
    }
    .form-group .form-control:focus {
        outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12);
    }
    .form-group .invalid-feedback { font-size:12px; color:#ef4444; margin-top:4px; }
    .form-group .form-control.is-invalid { border-color:#ef4444; }

    /* ── Toggle switch ──────────────────────────── */
    .toggle-wrap { display:flex; align-items:center; gap:10px; }
    .toggle-switch { position:relative; width:44px; height:24px; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider {
        position:absolute; inset:0; background:#d1d5db; border-radius:24px;
        cursor:pointer; transition:background .2s;
    }
    .toggle-slider::before {
        content:''; position:absolute; width:18px; height:18px;
        left:3px; top:3px; background:#fff; border-radius:50%;
        transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2);
    }
    .toggle-switch input:checked + .toggle-slider { background:#3b82f6; }
    .toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }

    /* ── Empty state ────────────────────────────── */
    .empty-state { text-align:center; padding:48px 20px; color:#94a3b8; }
    .empty-state i { font-size:40px; margin-bottom:12px; }
    .empty-state p { font-size:14px; }

    /* ── Code cell ──────────────────────────────── */
    .code-chip {
        font-family:monospace; background:#f1f5f9; border:1px solid #e2e8f0;
        border-radius:4px; padding:2px 7px; font-size:12px; color:#475569;
    }

    /* ── Responsive ─────────────────────────────── */
    @media (max-width:768px) {
        .accounts-table thead { display:none; }
        .accounts-table tbody tr { display:block; margin-bottom:12px; border:1px solid #e2e8f0; border-radius:8px; }
        .accounts-table tbody td { display:flex; justify-content:space-between; padding:10px 14px; border-bottom:1px solid #f1f5f9; }
        .accounts-table tbody td:last-child { border-bottom:none; }
        .accounts-table tbody td::before { content:attr(data-label); font-weight:600; color:#64748b; font-size:12px; text-transform:uppercase; }
    }
    .modal-body{
        display: grid;
        gap: 20px;
        grid-template-columns: repeat(2, 1fr); 
    }
</style>
@endsection

@section('main-content')

{{-- ═══════════════════ MAIN CARD ═══════════════════ --}}
<div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
    <div class="states-table-container">

        {{-- Header --}}
        <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
            <h2 class="text-white text-xl font-semibold" style="color:white">
                <i class="fas fa-wallet mr-2"></i>Manage Accounts
            </h2>
            @can('create account')
            <button id="openCreateModal"
                    class="btn btn-primary bg-white text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-md font-medium transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add New Account
            </button>
            @endcan
        </div>

        <div class="states-table-content">

            {{-- ── Filter Panel ── --}}
            <form action="" method="GET" id="filterForm">
                <div class="filter-container">
                    <div class="filter-header" id="filterToggleHeader">
                        <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="filter-content {{ (request()->hasAny(['name','status','type'])) ? 'active' : '' }}" id="filterBody">
                        <div class="filter-row">
                            {{-- Name / Code search --}}
                            <div class="filter-group">
                                <label for="name">Name / Code</label>
                                <input type="text" name="name" id="name"
                                       value="{{ request('name') }}"
                                       placeholder="Search by name or code…"
                                       class="form-control">
                            </div>

                            {{-- Type --}}
                            <div class="filter-group">
                                <label for="type">Type</label>
                                <select name="type" id="type" class="form-control select2" style="width:100%">
                                    <option value="">All Types</option>
                                    @foreach(['asset','liability','equity','income','expense'] as $t)
                                        <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>
                                            {{ ucfirst($t) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Status --}}
                            <div class="filter-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control select2" style="width:100%">
                                    <option value="">All</option>
                                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="button" class="reset-btn" id="resetFilterBtn">
                                <i class="fas fa-undo mr-1"></i>Reset
                            </button>
                            <button type="submit" class="apply-btn">
                                <i class="fas fa-search mr-1"></i>Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- ── Flash Messages ── --}}
            <div class="px-4 pt-4">
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-md mb-3 flex items-center gap-2" role="alert">
                        <i class="fas fa-check-circle"></i> {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-md mb-3 flex items-center gap-2" role="alert">
                        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                    </div>
                @endif
            </div>

            {{-- ── Table ── --}}
            <div class="p-4 overflow-x-auto">
                @if($accounts->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No accounts found. Adjust your filters or add a new account.</p>
                    </div>
                @else
                <table class="accounts-table">
                    <thead>
                        <tr>
                            {{-- # and Actions stay plain: a row counter and buttons.
                                 Sorting is server-side, so it orders every account,
                                 not just the fifteen on this page. --}}
                            <th>#</th>
                            <th>@include('partials.sortable-th', ['key' => 'code', 'label' => 'Code'])</th>
                            <th>@include('partials.sortable-th', ['key' => 'name', 'label' => 'Name'])</th>
                            <th>@include('partials.sortable-th', ['key' => 'type', 'label' => 'Type'])</th>
                            <th>@include('partials.sortable-th', ['key' => 'parent', 'label' => 'Parent Account'])</th>
                            <th>@include('partials.sortable-th', ['key' => 'company', 'label' => 'Company'])</th>
                            <th>@include('partials.sortable-th', ['key' => 'opening_balance', 'label' => 'Opening Balance', 'dirDefault' => 'desc'])</th>
                            <th>@include('partials.sortable-th', ['key' => 'status', 'label' => 'Status'])</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accounts as $index => $account)
                        <tr id="row-{{ $account->id }}">
                            <td data-label="#">{{ $accounts->firstItem() + $index }}</td>

                            <td data-label="Code">
                                <span class="code-chip">{{ $account->code }}</span>
                            </td>

                            <td data-label="Name">
                                <span class="font-medium text-gray-800">{{ $account->name }}</span>
                            </td>

                            <td data-label="Type">
                                <span class="type-badge type-{{ $account->type }}">{{ ucfirst($account->type) }}</span>
                            </td>

                            <td data-label="Parent">
                                @if($account->parent)
                                    <span class="text-gray-600 text-sm">
                                        <i class="fas fa-sitemap text-gray-400 mr-1"></i>
                                        {{ $account->parent->name }}
                                        <span class="code-chip ml-1">{{ $account->parent->code }}</span>
                                    </span>
                                @else
                                    <span class="text-gray-400 text-sm">—</span>
                                @endif
                            </td>

                            <td data-label="Company">
                                @if($account->company)
                                    <span class="text-gray-600 text-sm">{{ $account->company->short_name ?: $account->company->name }}</span>
                                @else
                                    <span class="text-gray-400 text-sm">Shared</span>
                                @endif
                            </td>

                            <td data-label="Opening Balance">
                                <span class="font-mono text-sm">
                                    {{ number_format($account->opening_balance, 2) }}
                                </span>
                            </td>

                            <td data-label="Status">
                                <span class="status-badge {{ $account->status ? 'status-active' : 'status-inactive' }}">
                                    {{ $account->status ? 'Active' : 'Inactive' }}
                                </span>
                            </td>

                            <td data-label="Actions" class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    {{-- Edit button --}}
                                    @can('edit account')
                                    <button class="btn-action btn-edit btn-edit-account"
                                            {{-- title="{{ $account->isSystemAccount() ? 'System account — limited edit' : 'Edit Account' }}" --}}
                                            title="Edit Account"
                                            data-id="{{ $account->id }}">
                                        <i class="fas fa-pen"></i>
                                        {{-- <i class="fas {{ $account->isSystemAccount() ? 'fa-lock' : 'fa-pen' }}"></i> --}}
                                    </button>
                                    @endcan

                                    {{-- Delete button — hidden entirely for system accounts --}}
                                    @can('delete account')
                                    @if(!$account->isSystemAccount())
                                    <button class="btn-action btn-delete btn-delete-account"
                                            title="Delete Account"
                                            data-id="{{ $account->id }}"
                                            data-name="{{ $account->name }}">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    @else
                                    <button class="btn-action" disabled
                                            title="System account — cannot delete"
                                            style="background:#f1f5f9;color:#cbd5e1;cursor:not-allowed;">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Pagination --}}
                @if($accounts->hasPages())
                <div class="mt-4 flex justify-between items-center text-sm text-gray-500">
                    <span>
                        Showing {{ $accounts->firstItem() }}–{{ $accounts->lastItem() }}
                        of {{ $accounts->total() }} accounts
                    </span>
                    <div>{{ $accounts->links() }}</div>
                </div>
                @endif
                @endif
            </div>{{-- /p-4 --}}

        </div>{{-- /states-table-content --}}
    </div>
</div>

{{-- ═══════════════════ CREATE / EDIT MODAL ═══════════════════ --}}
<div class="modal-overlay" id="accountModal">
    <div class="modal-box">
        <div class="modal-header">
            <h4 id="modalTitle"><i class="fas fa-wallet mr-2"></i>Add New Account</h4>
            <button type="button" class="modal-close" id="closeModal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="accountForm" novalidate>
            @csrf
            <input type="hidden" id="accountId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label for="m_code">Account Code <span class="req">*</span></label>
                    <input type="text" id="m_code" name="code" class="form-control"
                           placeholder="e.g. 1001" maxlength="50" autocomplete="off">
                    <div class="invalid-feedback" id="err_code"></div>
                </div>

                <div class="form-group">
                    <label for="m_name">Account Name <span class="req">*</span></label>
                    <input type="text" id="m_name" name="name" class="form-control"
                           placeholder="e.g. Cash in Hand" maxlength="255" autocomplete="off">
                    <div class="invalid-feedback" id="err_name"></div>
                </div>

                <div class="form-group">
                    <label for="m_type">Type <span class="req">*</span></label>
                    <select id="m_type" name="type" class="form-control select2-modal" style="width:100%">
                        <option value="">— Select Type —</option>
                        <option value="asset">Asset</option>
                        <option value="liability">Liability</option>
                        <option value="equity">Equity</option>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                    <div class="invalid-feedback" id="err_type"></div>
                </div>

                <div class="form-group">
                    <label for="m_parent_id">Parent Account</label>
                    <select id="m_parent_id" name="parent_id" class="form-control select2-modal" style="width:100%">
                        <option value="">— None (Root Account) —</option>
                        @foreach($allAccounts as $acc)
                            <option value="{{ $acc->id }}">
                                [{{ $acc->code }}] {{ $acc->name }} ({{ ucfirst($acc->type) }})
                            </option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" id="err_parent_id"></div>
                </div>

                <div class="form-group">
                    <label for="m_company_id">Company</label>
                    <select id="m_company_id" name="company_id" class="form-control select2-modal" style="width:100%">
                        <option value="">— Shared / All Companies —</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" id="err_company_id"></div>
                </div>

                <div class="form-group">
                    <label for="m_opening_balance">Opening Balance</label>
                    <input type="number" id="m_opening_balance" name="opening_balance"
                           class="form-control" value="0" min="0" step="0.01"
                           placeholder="0.00">
                    <div class="invalid-feedback" id="err_opening_balance"></div>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <div class="toggle-wrap">
                        <label class="toggle-switch">
                            <input type="checkbox" id="m_status" name="status" value="1" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <span id="statusLabel" class="text-sm font-medium text-gray-700">Active</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="reset-btn" id="cancelModal"
                        style="padding:10px 20px;border-radius:6px;font-weight:500;cursor:pointer;background:#f8f9fa;color:#6b7280;border:1px solid #d1d5db;">
                    Cancel
                </button>
                <button type="submit" id="submitBtn"
                        style="padding:10px 20px;border-radius:6px;font-weight:500;cursor:pointer;background:#3b82f6;color:#fff;border:none;">
                    <i class="fas fa-save mr-1"></i><span id="submitBtnText">Save Account</span>
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function () {

    // ── CSRF setup ──────────────────────────────────────────
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const accountsBase = '{{ rtrim(route("role.accounts.index", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]), "/") }}';
    const baseUrl  = '{{ route("role.accounts.index", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
    const storeUrl = '{{ route("role.accounts.store", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

    // ── Init Select2 ────────────────────────────────────────
    function initSelect2() {
        $('.select2').select2();
        $('.select2-modal').select2({ dropdownParent: $('#accountModal') });
    }
    initSelect2();

    // ── Filter panel toggle ──────────────────────────────────
    const $filterHeader  = $('#filterToggleHeader');
    const $filterContent = $('#filterBody');

    $filterHeader.on('click', function () {
        $(this).toggleClass('active');
        $filterContent.toggleClass('active');
    });

    // Auto-open filter if any filter is active
    if ('{{ request()->hasAny(["name","status","type"]) ? "1" : "" }}' === '1') {
        $filterHeader.addClass('active');
        $filterContent.addClass('active');
    }

    // ── Reset filter ─────────────────────────────────────────
    $('#resetFilterBtn').on('click', function (e) {
        e.preventDefault();
        window.location.href = baseUrl;
    });

    // ── Status toggle label ───────────────────────────────────
    $('#m_status').on('change', function () {
        $('#statusLabel').text(this.checked ? 'Active' : 'Inactive');
    });

    // ── Open CREATE modal ────────────────────────────────────
    $('#openCreateModal').on('click', function () {
        resetModal();
        $('#modalTitle').html('<i class="fas fa-plus-circle mr-2"></i>Add New Account');
        $('#submitBtnText').text('Save Account');
        openModal();
    });

    // ── Open EDIT modal ──────────────────────────────────────
    $(document).on('click', '.btn-edit-account', function () {
        const id      = $(this).data('id');
        const editUrl = `${accountsBase}/${id}/edit`;

        $.get(editUrl)
            .done(function (account) {
                resetModal();
                $('#accountId').val(account.id);
                $('#m_code').val(account.code);
                $('#m_name').val(account.name);
                $('#m_type').val(account.type).trigger('change');
                $('#m_parent_id').val(account.parent_id ?? '').trigger('change');
                $('#m_company_id').val(account.company_id ?? '').trigger('change');
                $('#m_opening_balance').val(account.opening_balance);
                $('#m_status').prop('checked', account.status == 1);
                $('#statusLabel').text(account.status == 1 ? 'Active' : 'Inactive');
                $('#modalTitle').html('<i class="fas fa-pen mr-2"></i>Edit Account');
                $('#submitBtnText').text('Update Account');
                openModal();
            })
            .fail(function () {
                Swal.fire('Error', 'Could not load account data. Please try again.', 'error');
            });
    });

    // ── Close modal ──────────────────────────────────────────
    $('#closeModal, #cancelModal').on('click', closeModal);
    $('#accountModal').on('click', function (e) {
        if (e.target === this) closeModal();
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // ── Submit (create / update) ─────────────────────────────
    $('#accountForm').on('submit', function (e) {
        e.preventDefault();
        clearErrors();

        const id      = $('#accountId').val();
        const isEdit  = id !== '';
        const url     = isEdit ? `${accountsBase}/${id}` : storeUrl;
        const method  = isEdit ? 'PUT' : 'POST';

        // Build data
        const data = {
            code:            $('#m_code').val().trim(),
            name:            $('#m_name').val().trim(),
            type:            $('#m_type').val(),
            parent_id:       $('#m_parent_id').val() || null,
            company_id:      $('#m_company_id').val() || null,
            opening_balance: $('#m_opening_balance').val() || 0,
            status:          $('#m_status').is(':checked') ? 1 : 0,
            _method:         method,
        };

        const $btn = $('#submitBtn').prop('disabled', true);
        $btn.find('#submitBtnText').text('Saving…');

        $.post(url, data)
            .done(function (response) {
                if (response.success) {
                    closeModal();
                    Swal.fire({
                        icon: 'success',
                        title: 'Done!',
                        text: response.message,
                        timer: 1800,
                        showConfirmButton: false,
                    }).then(() => window.location.reload());
                }
            })
            .fail(function (xhr) {
                $btn.prop('disabled', false);
                $('#submitBtnText').text(isEdit ? 'Update Account' : 'Save Account');

                const response = xhr.responseJSON;

                if (xhr.status === 422 && response.errors) {
                    // This handles standard validation errors
                    $.each(response.errors, function (field, messages) {
                        $(`#m_${field}`).addClass('is-invalid');
                        $(`#err_${field}`).text(messages[0]).show();
                    });
                } else {
                    // This handles your custom "message" or other server errors
                    Swal.fire('Error', response?.message ?? 'Something went wrong.', 'error');
                }
            });
    });

    // ── Delete ───────────────────────────────────────────────
    $(document).on('click', '.btn-delete-account', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        const url = `${accountsBase}/${id}`;

        Swal.fire({
            title:             'Delete Account?',
            html:              `Are you sure you want to delete <strong>${name}</strong>?<br>
                                <small class="text-gray-500">This action uses soft delete and can be reversed.</small>`,
            icon:              'warning',
            showCancelButton:  true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.ajax({ url, type: 'POST', data: { _method: 'DELETE' } })
                .done(function (response) {
                    if (response.success) {
                        $(`#row-${id}`).fadeOut(300, function () { $(this).remove(); });
                        Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, timer: 1600, showConfirmButton: false });
                    }
                })
                .fail(function (xhr) {
                    Swal.fire('Cannot Delete', xhr.responseJSON?.message ?? 'An error occurred.', 'error');
                });
        });
    });

    // ── Helpers ──────────────────────────────────────────────
    function openModal()  { $('#accountModal').addClass('show'); $('body').css('overflow', 'hidden'); }
    function closeModal() { $('#accountModal').removeClass('show'); $('body').css('overflow', ''); }

    function resetModal() {
        $('#accountId').val('');
        $('#accountForm')[0].reset();
        $('#m_opening_balance').val('0');
        $('#m_status').prop('checked', true);
        $('#statusLabel').text('Active');
        $('#m_type').val('').trigger('change');
        $('#m_parent_id').val('').trigger('change');
        $('#m_company_id').val('').trigger('change');
        clearErrors();
    }

    function clearErrors() {
        $('.form-control').removeClass('is-invalid');
        $('.invalid-feedback').text('').hide();
    }
});
</script>
@endsection