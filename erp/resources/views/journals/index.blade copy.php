@extends('layout.app')

@section('meta-information')
    <title>Manage Journal Entries</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* ── Filter Panel (same as accounts) ────────────────── */
    .filter-container { margin:15px 15px 0 15px; border-radius:8px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.1); }
    .filter-container .filter-header { background:#f8f9fa; padding:16px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; border-left:4px solid #3b82f6; transition:background .3s; }
    .filter-container .filter-header:hover { background:#e9ecef; }
    .filter-container .filter-header h3 { margin:0; font-size:18px; font-weight:600; color:#1f2937; }
    .filter-container .filter-header .toggle-icon { transition:transform .3s; }
    .filter-container .filter-header.active .toggle-icon { transform:rotate(180deg); }
    .filter-container .filter-content { background:#fff; padding:0; max-height:0; overflow:hidden; transition:max-height .3s ease-out, padding .3s ease-out; }
    .filter-container .filter-content.active { padding:20px; max-height:500px; }
    .filter-container .filter-row { display:flex; flex-wrap:wrap; gap:20px; margin-bottom:16px; }
    .filter-container .filter-group { flex:1; min-width:200px; }
    .filter-container .filter-group label { display:block; margin-bottom:6px; font-weight:500; color:#374151; }
    .filter-container .filter-group select,
    .filter-container .filter-group input { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; transition:border-color .3s; }
    .filter-container .filter-group select:focus,
    .filter-container .filter-group input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
    .filter-container .filter-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:20px; }
    .filter-container .filter-actions button { padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; transition:all .3s; }
    .filter-container .filter-actions .apply-btn  { background:#3b82f6; color:#fff; border:none; }
    .filter-container .filter-actions .apply-btn:hover { background:#2563eb; }
    .filter-container .filter-actions .reset-btn  { background:#f8f9fa; color:#6b7280; border:1px solid #d1d5db; }
    .filter-container .filter-actions .reset-btn:hover { background:#e5e7eb; }

    /* ── Select2 fix ─────────────────────────────────────── */
    .select2-container .select2-selection--single { height:42px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:40px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:42px; top:1px; right:3px; width:20px; }

    /* ── Pagination ──────────────────────────────────────── */
    span [aria-current="page"] span { background-color:#2563eb !important; color:#fff; border-color:#2563eb; }

    /* ── Table ───────────────────────────────────────────── */
    .journal-table { width:100%; border-collapse:collapse; }
    .journal-table thead tr { background:#f1f5f9; }
    .journal-table thead th { padding:12px 16px; text-align:left; font-weight:600; font-size:13px; color:#475569; text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid #e2e8f0; }
    .journal-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .journal-table tbody tr:hover { background:#f8fafc; }
    .journal-table tbody td { padding:12px 16px; font-size:14px; color:#334155; vertical-align:middle; }

    /* ── Source badge ────────────────────────────────────── */
    .source-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; text-transform:capitalize; }
    .source-manual   { background:#e0e7ff; color:#3730a3; }
    .source-sale     { background:#d1fae5; color:#065f46; }
    .source-purchase { background:#fef3c7; color:#92400e; }
    .source-expense  { background:#fee2e2; color:#b91c1c; }
    .source-salary   { background:#dbeafe; color:#1d4ed8; }

    /* ── Balance badge ───────────────────────────────────── */
    .balanced-yes { background:#d1fae5; color:#065f46; display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; }
    .balanced-no  { background:#fee2e2; color:#b91c1c; display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; }

    /* ── Action buttons ──────────────────────────────────── */
    .btn-action { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:6px; border:none; cursor:pointer; font-size:13px; transition:all .2s; }
    .btn-edit   { background:#dbeafe; color:#1d4ed8; }
    .btn-edit:hover { background:#bfdbfe; }
    .btn-delete { background:#fee2e2; color:#b91c1c; }
    .btn-delete:hover { background:#fecaca; }
    .btn-view   { background:#f0fdf4; color:#15803d; }
    .btn-view:hover { background:#dcfce7; }

    /* ── Modal ───────────────────────────────────────────── */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1040; opacity:0; pointer-events:none; transition:opacity .25s; }
    .modal-overlay.show { opacity:1; pointer-events:all; }
    .modal-box { background:#fff; border-radius:12px; width:100%; max-width:900px; max-height:96vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); transform:translateY(20px); transition:transform .25s; }
    .modal-overlay.show .modal-box { transform:translateY(0); }
    .modal-header { display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid #e2e8f0; background:#2563eb; border-radius:12px 12px 0 0; position:sticky; top:0; z-index:10; }
    .modal-header h4 { margin:0; color:#fff; font-size:17px; font-weight:600; }
    .modal-close { background:rgba(255,255,255,.2); border:none; color:#fff; width:32px; height:32px; border-radius:6px; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; transition:background .2s; }
    .modal-close:hover { background:rgba(255,255,255,.35); }
    .modal-body { padding:24px; }
    .modal-footer { padding:16px 24px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px; position:sticky; bottom:0; background:#fff; border-top:1px solid #e2e8f0; border-radius:0 0 12px 12px; }

    /* ── Form ────────────────────────────────────────────── */
    .form-row { display:flex; flex-wrap:wrap; gap:16px; }
    .form-group { margin-bottom:16px; }
    .form-group.half { flex:1; min-width:200px; }
    .form-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
    .form-group label span.req { color:#ef4444; margin-left:2px; }
    .form-group .form-control { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; color:#1f2937; transition:border-color .2s; box-sizing:border-box; }
    .form-group .form-control:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
    .form-group .form-control.is-invalid { border-color:#ef4444; }
    .invalid-feedback { font-size:12px; color:#ef4444; margin-top:4px; display:none; }

    /* ── Items section ───────────────────────────────────── */
    .items-section { margin-top:8px; }
    .items-section .section-title { font-size:14px; font-weight:700; color:#1e40af; border-bottom:2px solid #dbeafe; padding-bottom:8px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; }
    .items-table { width:100%; border-collapse:collapse; }
    .items-table thead th { background:#f1f5f9; padding:9px 10px; font-size:12px; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #e2e8f0; }
    .items-table tbody td { padding:7px 6px; vertical-align:middle; border-bottom:1px solid #f1f5f9; }
    .items-table .item-input { width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:5px; font-size:13px; box-sizing:border-box; }
    .items-table .item-input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,.12); }
    .items-table .item-input.is-invalid { border-color:#ef4444; }
    .btn-remove-row { background:#fee2e2; border:none; color:#b91c1c; width:28px; height:28px; border-radius:5px; cursor:pointer; font-size:13px; display:inline-flex; align-items:center; justify-content:center; transition:background .2s; }
    .btn-remove-row:hover { background:#fecaca; }
    .btn-add-row { background:#dbeafe; border:none; color:#1d4ed8; padding:7px 14px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; transition:background .2s; }
    .btn-add-row:hover { background:#bfdbfe; }

    /* ── Balance indicator ───────────────────────────────── */
    .balance-bar { display:flex; gap:20px; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 16px; margin-top:12px; font-size:13px; }
    .balance-bar .bal-item { display:flex; flex-direction:column; gap:2px; }
    .balance-bar .bal-label { font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; }
    .balance-bar .bal-value { font-size:15px; font-weight:700; font-family:monospace; }
    .balance-bar .bal-debit  { color:#1d4ed8; }
    .balance-bar .bal-credit { color:#065f46; }
    .balance-bar .bal-diff.ok   { color:#065f46; }
    .balance-bar .bal-diff.warn { color:#ef4444; }
    .balance-bar .bal-spacer { flex:1; }

    /* ── View modal (read-only) ──────────────────────────── */
    .view-items-table { width:100%; border-collapse:collapse; margin-top:8px; }
    .view-items-table th { background:#f1f5f9; padding:9px 12px; font-size:12px; font-weight:600; color:#475569; text-transform:uppercase; border-bottom:2px solid #e2e8f0; }
    .view-items-table td { padding:9px 12px; font-size:13px; color:#334155; border-bottom:1px solid #f1f5f9; }
    .view-items-table tfoot td { background:#f8fafc; font-weight:700; }
    .code-chip { font-family:monospace; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 7px; font-size:12px; color:#475569; }

    /* ── Empty state ─────────────────────────────────────── */
    .empty-state { text-align:center; padding:48px 20px; color:#94a3b8; }
    .empty-state i { font-size:40px; margin-bottom:12px; }

    /* ── Responsive ──────────────────────────────────────── */
    @media (max-width:768px) {
        .journal-table thead { display:none; }
        .journal-table tbody tr { display:block; margin-bottom:12px; border:1px solid #e2e8f0; border-radius:8px; }
        .journal-table tbody td { display:flex; justify-content:space-between; padding:10px 14px; border-bottom:1px solid #f1f5f9; }
        .journal-table tbody td::before { content:attr(data-label); font-weight:600; color:#64748b; font-size:12px; text-transform:uppercase; }
        .modal-box { max-width:98vw; }
    }
    .grid-body{
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
                <i class="fas fa-book mr-2"></i>Journal Entries
            </h2>
            @can('create journal')
            <button id="openCreateModal"
                    class="bg-white text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-md font-medium transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add Journal Entry
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
                    <div class="filter-content {{ request()->hasAny(['date_from','date_to','reference','source']) ? 'active' : '' }}"
                         id="filterBody">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="date_from">Date From</label>
                                <input type="date" name="date_from" id="date_from"
                                       value="{{ request('date_from') }}" class="form-control">
                            </div>
                            <div class="filter-group">
                                <label for="date_to">Date To</label>
                                <input type="date" name="date_to" id="date_to"
                                       value="{{ request('date_to') }}" class="form-control">
                            </div>
                            <div class="filter-group">
                                <label for="f_reference">Reference</label>
                                <input type="text" name="reference" id="f_reference"
                                       value="{{ request('reference') }}"
                                       placeholder="Search reference…" class="form-control">
                            </div>
                            <div class="filter-group">
                                <label for="f_source">Source</label>
                                <select name="source" id="f_source" class="form-control select2" style="width:100%">
                                    <option value="">All Sources</option>
                                    @foreach($sources as $src)
                                        <option value="{{ $src }}" {{ request('source') === $src ? 'selected' : '' }}>
                                            {{ ucfirst($src) }}
                                        </option>
                                    @endforeach
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

            {{-- Flash messages --}}
            <div class="px-4 pt-4">
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-md mb-3 flex items-center gap-2">
                        <i class="fas fa-check-circle"></i> {{ session('success') }}
                    </div>
                @endif
            </div>

            {{-- ── Table ── --}}
            <div class="p-4 overflow-x-auto">
                @if($journals->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <p>No journal entries found. Adjust your filters or create a new entry.</p>
                    </div>
                @else
                <table class="journal-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Source</th>
                            <th>Description</th>
                            <th class="text-right">Total Debit</th>
                            <th class="text-right">Total Credit</th>
                            <th>Balanced</th>
                            <th>Created By</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($journals as $index => $journal)
                        @php
                            $totalDebit  = $journal->items->sum('debit');
                            $totalCredit = $journal->items->sum('credit');
                            $isBalanced  = abs($totalDebit - $totalCredit) < 0.01;
                        @endphp
                        <tr id="row-{{ $journal->id }}">
                            <td data-label="#">{{ $journals->firstItem() + $index }}</td>

                            <td data-label="Date">
                                <span class="font-medium">{{ $journal->date->format('d M Y') }}</span>
                            </td>

                            <td data-label="Reference">
                                @if($journal->reference)
                                    <span class="code-chip">{{ $journal->reference }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            <td data-label="Source">
                                <span class="source-badge source-{{ $journal->source }}">
                                    {{ ucfirst($journal->source) }}
                                </span>
                            </td>

                            <td data-label="Description">
                                <span class="text-sm text-gray-600">
                                    {{ Str::limit($journal->description, 40) ?? '—' }}
                                </span>
                            </td>

                            <td data-label="Total Debit" class="text-right font-mono text-blue-700 font-semibold">
                                {{ number_format($totalDebit, 2) }}
                            </td>

                            <td data-label="Total Credit" class="text-right font-mono text-green-700 font-semibold">
                                {{ number_format($totalCredit, 2) }}
                            </td>

                            <td data-label="Balanced">
                                @if($isBalanced)
                                    <span class="balanced-yes"><i class="fas fa-check mr-1"></i>Yes</span>
                                @else
                                    <span class="balanced-no"><i class="fas fa-times mr-1"></i>No</span>
                                @endif
                            </td>

                            <td data-label="Created By">
                                <span class="text-sm">{{ $journal->createdBy?->name ?? '—' }}</span>
                            </td>

                            <td data-label="Actions" class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    {{-- View --}}
                                    <button class="btn-action btn-view btn-view-journal"
                                            title="View Items"
                                            data-id="{{ $journal->id }}">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    @if($journal->source === 'manual')
                                        @can('edit journal')
                                        <button class="btn-action btn-edit btn-edit-journal"
                                                title="Edit"
                                                data-id="{{ $journal->id }}">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        @endcan

                                        @can('delete journal')
                                        <button class="btn-action btn-delete btn-delete-journal"
                                                title="Delete"
                                                data-id="{{ $journal->id }}"
                                                data-ref="{{ $journal->reference ?? 'Entry #'.$journal->id }}">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Pagination --}}
                @if($journals->hasPages())
                <div class="mt-4 flex justify-between items-center text-sm text-gray-500">
                    <span>
                        Showing {{ $journals->firstItem() }}–{{ $journals->lastItem() }}
                        of {{ $journals->total() }} entries
                    </span>
                    <div>{{ $journals->links() }}</div>
                </div>
                @endif
                @endif
            </div>

        </div>
    </div>
</div>

{{-- ═══════════════════ CREATE / EDIT MODAL ═══════════════════ --}}
<div class="modal-overlay" id="journalModal">
    <div class="modal-box">
        <div class="modal-header">
            <h4 id="modalTitle"><i class="fas fa-book mr-2"></i>Add Journal Entry</h4>
            <button type="button" class="modal-close" id="closeModal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="journalForm" novalidate>
            @csrf
            <input type="hidden" id="journalId" value="">

            <div class="modal-body">
                {{-- Row 1: date, reference, source --}}
                <div class="form-row">
                    <div class="form-group half">
                        <label for="m_date">Date <span class="req">*</span></label>
                        <input type="date" id="m_date" name="date" class="form-control">
                        <div class="invalid-feedback" id="err_date"></div>
                    </div>
                    <div class="form-group half">
                        <label for="m_reference">Reference</label>
                        <input type="text" id="m_reference" name="reference"
                               class="form-control" placeholder="e.g. JV-2024-001" maxlength="100">
                        <div class="invalid-feedback" id="err_reference"></div>
                    </div>
                    <div class="form-group half">
                        <label for="m_source">Source <span class="req">*</span></label>
                        <select id="m_source" name="source" class="form-control select2-modal" style="width:100%">
                            <option value="">— Select Source —</option>
                            @foreach($sources as $src)
                                <option value="{{ $src }}">{{ ucfirst($src) }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="err_source"></div>
                    </div>
                </div>

                {{-- Description --}}
                <div class="form-group">
                    <label for="m_description">Description</label>
                    <textarea id="m_description" name="description" class="form-control"
                              rows="2" maxlength="500" placeholder="Optional note about this entry…"></textarea>
                    <div class="invalid-feedback" id="err_description"></div>
                </div>

                {{-- ── Line Items ── --}}
                <div class="items-section">
                    <div class="section-title">
                        <span><i class="fas fa-list mr-1"></i>Line Items <span class="req">*</span></span>
                        <button type="button" class="btn-add-row" id="addRowBtn">
                            <i class="fas fa-plus mr-1"></i>Add Row
                        </button>
                    </div>
                    <div id="err_items" style="color:#ef4444;font-size:13px;margin-bottom:8px;display:none;"></div>

                    <div style="overflow-x:auto;">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width:35%">Account <span style="color:#ef4444">*</span></th>
                                    <th style="width:18%">Debit</th>
                                    <th style="width:18%">Credit</th>
                                    <th style="width:24%">Note</th>
                                    <th style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                {{-- rows injected by JS --}}
                            </tbody>
                        </table>
                    </div>

                    {{-- Balance indicator --}}
                    <div class="balance-bar" id="balanceBar">
                        <div class="bal-item">
                            <span class="bal-label">Total Debit</span>
                            <span class="bal-value bal-debit" id="balDebit">0.00</span>
                        </div>
                        <div class="bal-item">
                            <span class="bal-label">Total Credit</span>
                            <span class="bal-value bal-credit" id="balCredit">0.00</span>
                        </div>
                        <div class="bal-spacer"></div>
                        <div class="bal-item" style="text-align:right;">
                            <span class="bal-label">Difference</span>
                            <span class="bal-value bal-diff ok" id="balDiff">0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="cancelModal"
                        style="padding:10px 20px;border-radius:6px;font-weight:500;cursor:pointer;background:#f8f9fa;color:#6b7280;border:1px solid #d1d5db;">
                    Cancel
                </button>
                <button type="submit" id="submitBtn"
                        style="padding:10px 20px;border-radius:6px;font-weight:500;cursor:pointer;background:#3b82f6;color:#fff;border:none;">
                    <i class="fas fa-save mr-1"></i><span id="submitBtnText">Save Entry</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════ VIEW MODAL (read-only) ═══════════════════ --}}
<div class="modal-overlay" id="viewModal">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-header" style="background:#475569;">
            <h4 id="viewModalTitle"><i class="fas fa-eye mr-2"></i>Journal Entry Details</h4>
            <button type="button" class="modal-close" id="closeViewModal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="viewModalBody">
            <div style="text-align:center;padding:30px;color:#94a3b8;">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" id="closeViewModalBtn"
                    style="padding:10px 20px;border-radius:6px;font-weight:500;cursor:pointer;background:#f8f9fa;color:#6b7280;border:1px solid #d1d5db;">
                Close
            </button>
        </div>
    </div>
</div>

{{-- Account options for JS --}}
<script id="accountsData" type="application/json">
    {!! json_encode($accounts->map(fn($a) => ['id' => $a->id, 'text' => '[' . $a->code . '] ' . $a->name . ' (' . ucfirst($a->type) . ')'])) !!}
</script>

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

    // ── Setup ────────────────────────────────────────────
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const journalsBase = '{{ rtrim(route("role.journals.index", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]), "/") }}';
    const storeUrl     = '{{ route("role.journals.store",  ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
    const baseUrl      = '{{ route("role.journals.index",  ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

    const accountsData = JSON.parse(document.getElementById('accountsData').textContent);

    // ── Init Select2 ─────────────────────────────────────
    function initFilterSelect2() {
        $('.select2').select2();
    }
    function initModalSelect2() {
        $('.select2-modal').select2({ dropdownParent: $('#journalModal') });
    }
    initFilterSelect2();

    // ── Filter toggle ────────────────────────────────────
    $('#filterToggleHeader').on('click', function () {
        $(this).toggleClass('active');
        $('#filterBody').toggleClass('active');
    });
    @if(request()->hasAny(['date_from','date_to','reference','source']))
        $('#filterToggleHeader').addClass('active');
        $('#filterBody').addClass('active');
    @endif

    $('#resetFilterBtn').on('click', function (e) {
        e.preventDefault();
        window.location.href = baseUrl;
    });

    // ── Open CREATE modal ─────────────────────────────────
    $('#openCreateModal').on('click', function () {
        resetModal();
        $('#modalTitle').html('<i class="fas fa-plus-circle mr-2"></i>Add Journal Entry');
        $('#submitBtnText').text('Save Entry');
        // Set today's date
        $('#m_date').val(new Date().toISOString().split('T')[0]);
        addItemRow();        
        addItemRow();        
        openModal('#journalModal');
        initModalSelect2();
    });

    // ── Open EDIT modal ───────────────────────────────────
    $(document).on('click', '.btn-edit-journal', function () {
        const id = $(this).data('id');
        $.get(`${journalsBase}/${id}/edit`)
            .done(function (entry) {
                resetModal();
                $('#journalId').val(entry.id);
                $('#m_date').val(entry.date);
                $('#m_reference').val(entry.reference ?? '');
                $('#m_source').val(entry.source);
                $('#m_description').val(entry.description ?? '');

                entry.items.forEach(function (item) {
                    addItemRow(item);
                });

                updateBalance();
                $('#modalTitle').html('<i class="fas fa-pen mr-2"></i>Edit Journal Entry');
                $('#submitBtnText').text('Update Entry');
                openModal('#journalModal');
                initModalSelect2();
                // Set select2 values after init
                $('#m_source').trigger('change');
            })
            .fail(function () {
                Swal.fire('Error', 'Could not load journal data.', 'error');
            });
    });

    // ── Close modals ──────────────────────────────────────
    $('#closeModal, #cancelModal').on('click', function () { closeModal('#journalModal'); });
    $('#closeViewModal, #closeViewModalBtn').on('click', function () { closeModal('#viewModal'); });
    $('.modal-overlay').on('click', function (e) { if (e.target === this) closeModal('#' + this.id); });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') { closeModal('#journalModal'); closeModal('#viewModal'); }
    });

    // ── Add item row ──────────────────────────────────────
    $('#addRowBtn').on('click', function () { addItemRow(); });

    function addItemRow(item = null) {
        const rowId = Date.now() + Math.random();
        const accountOptions = accountsData.map(a =>
            `<option value="${a.id}" ${item && item.account_id == a.id ? 'selected' : ''}>${a.text}</option>`
        ).join('');

        const tr = `
        <tr class="item-row" data-row="${rowId}">
            <td>
                <select class="item-input item-account select2-item" name="items[account_id][]" style="width:100%">
                    <option value="">— Select Account —</option>
                    ${accountOptions}
                </select>
            </td>
            <td>
                <input type="number" class="item-input item-debit" name="items[debit][]"
                       value="${item ? parseFloat(item.debit).toFixed(2) : '0.00'}"
                       min="0" step="0.01" placeholder="0.00">
            </td>
            <td>
                <input type="number" class="item-input item-credit" name="items[credit][]"
                       value="${item ? parseFloat(item.credit).toFixed(2) : '0.00'}"
                       min="0" step="0.01" placeholder="0.00">
            </td>
            <td>
                <input type="text" class="item-input item-note" name="items[note][]"
                       value="${item ? (item.note ?? '') : ''}" maxlength="255" placeholder="Optional note">
            </td>
            <td>
                <button type="button" class="btn-remove-row remove-row-btn" title="Remove row">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>`;

        $('#itemsBody').append(tr);

        // Init select2 on new row
        const $lastSelect = $('#itemsBody .item-row:last-child .select2-item');
        $lastSelect.select2({ dropdownParent: $('#journalModal') });

        // Update balance on change
        $lastSelect.on('change', updateBalance);
        updateBalance();
    }

    // ── Remove item row ───────────────────────────────────
    $(document).on('click', '.remove-row-btn', function () {
        if ($('.item-row').length <= 2) {
            Swal.fire({ icon: 'warning', title: 'Minimum 2 rows required.', timer: 1500, showConfirmButton: false });
            return;
        }
        $(this).closest('.item-row').remove();
        updateBalance();
    });

    // ── Balance calculator ────────────────────────────────
    $(document).on('input', '.item-debit, .item-credit', function () {
        // Prevent entering both debit and credit on same row
        const $row = $(this).closest('.item-row');
        if ($(this).hasClass('item-debit') && parseFloat($(this).val()) > 0) {
            $row.find('.item-credit').val('0.00');
        } else if ($(this).hasClass('item-credit') && parseFloat($(this).val()) > 0) {
            $row.find('.item-debit').val('0.00');
        }
        updateBalance();
    });

    function updateBalance() {
        let totalDebit = 0, totalCredit = 0;
        $('.item-debit').each(function ()  { totalDebit  += parseFloat($(this).val()) || 0; });
        $('.item-credit').each(function () { totalCredit += parseFloat($(this).val()) || 0; });
        const diff = totalDebit - totalCredit;

        $('#balDebit').text(totalDebit.toFixed(2));
        $('#balCredit').text(totalCredit.toFixed(2));
        $('#balDiff').text(Math.abs(diff).toFixed(2))
                     .removeClass('ok warn')
                     .addClass(Math.abs(diff) < 0.01 ? 'ok' : 'warn');
    }

    // ── Submit ────────────────────────────────────────────
    $('#journalForm').on('submit', function (e) {
        e.preventDefault();
        clearErrors();

        const id     = $('#journalId').val();
        const isEdit = id !== '';
        const url    = isEdit ? `${journalsBase}/${id}` : storeUrl;

        // Build items array
        const items = [];
        let itemError = false;

        $('.item-row').each(function () {
            const accountId = $(this).find('.item-account').val();
            const debit     = parseFloat($(this).find('.item-debit').val())  || 0;
            const credit    = parseFloat($(this).find('.item-credit').val()) || 0;
            const note      = $(this).find('.item-note').val();

            if (!accountId) {
                $(this).find('.item-account').addClass('is-invalid');
                itemError = true;
            }
            items.push({ account_id: accountId, debit, credit, note });
        });

        if (itemError) {
            $('#err_items').text('Please select an account for every line item.').show();
            return;
        }

        // Client-side balance check
        const totalDebit  = items.reduce((s, i) => s + i.debit, 0);
        const totalCredit = items.reduce((s, i) => s + i.credit, 0);
        if (Math.abs(totalDebit - totalCredit) >= 0.01) {
            $('#err_items').text(
                `Entry is not balanced. Debit (${totalDebit.toFixed(2)}) ≠ Credit (${totalCredit.toFixed(2)})`
            ).show();
            return;
        }

        const data = {
            date:        $('#m_date').val(),
            reference:   $('#m_reference').val(),
            source:      $('#m_source').val(),
            description: $('#m_description').val(),
            items:       items,
            _method:     isEdit ? 'PUT' : 'POST',
        };

        const $btn = $('#submitBtn').prop('disabled', true);
        $('#submitBtnText').text('Saving…');

        $.ajax({
            url:         url,
            type:        'POST',
            contentType: 'application/json',
            data:        JSON.stringify(data),
        })
        .done(function (response) {
            if (response.success) {
                closeModal('#journalModal');
                Swal.fire({
                    icon: 'success', title: 'Done!', text: response.message,
                    timer: 1800, showConfirmButton: false,
                }).then(() => window.location.reload());
            }
        })
        .fail(function (xhr) {
            $btn.prop('disabled', false);
            $('#submitBtnText').text(isEdit ? 'Update Entry' : 'Save Entry');

            if (xhr.status === 422) {
                const err = xhr.responseJSON;
                // Field-level validation errors from Laravel
                if (err.errors) {
                    $.each(err.errors, function (field, messages) {
                        $(`#err_${field.replace(/\./g, '_')}`).text(messages[0]).show();
                        $(`#m_${field}`).addClass('is-invalid');
                    });
                }
                // Single abort() message (balance / source guard)
                if (err.message) {
                    $('#err_items').text(err.message).show();
                }
            } else {
                Swal.fire('Error', xhr.responseJSON?.message ?? 'Something went wrong.', 'error');
            }
        });
    });

    // ── View modal ────────────────────────────────────────
    $(document).on('click', '.btn-view-journal', function () {
        const id = $(this).data('id');
        $('#viewModalBody').html('<div style="text-align:center;padding:30px;color:#94a3b8;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
        openModal('#viewModal');

        $.get(`${journalsBase}/${id}/edit`)
            .done(function (entry) {
                let itemsHtml = '';
                let totalDebit = 0, totalCredit = 0;

                entry.items.forEach(function (item) {
                    totalDebit  += parseFloat(item.debit);
                    totalCredit += parseFloat(item.credit);
                    const accName = item.account ? item.account.name : '—';
                    const accCode = item.account ? item.account.code : '';
                    itemsHtml += `
                        <tr>
                            <td><span class="code-chip">${accCode}</span> ${accName}</td>
                            <td class="text-right font-mono" style="color:#1d4ed8;">${parseFloat(item.debit).toFixed(2)}</td>
                            <td class="text-right font-mono" style="color:#065f46;">${parseFloat(item.credit).toFixed(2)}</td>
                            <td>${item.note ?? '—'}</td>
                        </tr>`;
                });

                const balanced = Math.abs(totalDebit - totalCredit) < 0.01;

                $('#viewModalTitle').html('<i class="fas fa-eye mr-2"></i>Journal Entry — ' + (entry.reference ?? 'Entry #' + entry.id));
                $('#viewModalBody').html(`
                    <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px;">
                        <div><span style="font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;">Date</span><br>
                            <span style="font-weight:600;">${entry.date}</span></div>
                        <div><span style="font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;">Reference</span><br>
                            <span>${entry.reference ? '<span class="code-chip">'+entry.reference+'</span>' : '—'}</span></div>
                        <div><span style="font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;">Source</span><br>
                            <span class="source-badge source-${entry.source}">${entry.source.charAt(0).toUpperCase()+entry.source.slice(1)}</span></div>
                    </div>
                    ${entry.description ? `<div style="background:#f8fafc;border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#475569;">${entry.description}</div>` : ''}
                    <table class="view-items-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>${itemsHtml}</tbody>
                        <tfoot>
                            <tr>
                                <td><strong>Total</strong></td>
                                <td class="text-right font-mono" style="color:#1d4ed8;"><strong>${totalDebit.toFixed(2)}</strong></td>
                                <td class="text-right font-mono" style="color:#065f46;"><strong>${totalCredit.toFixed(2)}</strong></td>
                                <td>${balanced ? '<span class="balanced-yes"><i class="fas fa-check mr-1"></i>Balanced</span>' : '<span class="balanced-no"><i class="fas fa-times mr-1"></i>Not Balanced</span>'}</td>
                            </tr>
                        </tfoot>
                    </table>
                `);
            })
            .fail(function () {
                $('#viewModalBody').html('<p style="color:#ef4444;padding:20px;">Could not load entry data.</p>');
            });
    });

    // ── Delete ────────────────────────────────────────────
    $(document).on('click', '.btn-delete-journal', function () {
        const id  = $(this).data('id');
        const ref = $(this).data('ref');

        Swal.fire({
            title:             'Delete Entry?',
            html:              `Are you sure you want to delete <strong>${ref}</strong>?<br>
                                <small class="text-gray-500">All line items will also be removed.</small>`,
            icon:              'warning',
            showCancelButton:  true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url:  `${journalsBase}/${id}`,
                type: 'POST',
                data: { _method: 'DELETE' },
                contentType: 'application/x-www-form-urlencoded',
            })
            .done(function (response) {
                if (response.success) {
                    $(`#row-${id}`).fadeOut(300, function () { $(this).remove(); });
                    Swal.fire({ icon:'success', title:'Deleted!', text:response.message, timer:1600, showConfirmButton:false });
                }
            })
            .fail(function (xhr) {
                Swal.fire('Cannot Delete', xhr.responseJSON?.message ?? 'An error occurred.', 'error');
            });
        });
    });

    // ── Helpers ───────────────────────────────────────────
    function openModal(sel)  { $(sel).addClass('show'); $('body').css('overflow', 'hidden'); }
    function closeModal(sel) { $(sel).removeClass('show'); $('body').css('overflow', ''); }

    function resetModal() {
        $('#journalId').val('');
        $('#journalForm')[0].reset();
        $('#itemsBody').empty();
        updateBalance();
        clearErrors();
    }

    function clearErrors() {
        $('.form-control, .item-input').removeClass('is-invalid');
        $('.invalid-feedback').text('').hide();
        $('#err_items').text('').hide();
    }
});
</script>
@endsection