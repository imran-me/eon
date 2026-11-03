@extends('layout.app')

@section('meta-information')
    <title>Manage Journal Entries</title>
@endsection

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('journals.css')
    <style>
        #viewModal .modal-box { width:calc(100vw - 40px); max-width:1040px!important; max-height:90vh; display:flex; flex-direction:column; }
        #viewModal .modal-header { background:linear-gradient(120deg,#1e40af,#2563eb)!important; padding:22px 26px; flex-shrink:0; }
        #viewModal .modal-header h4 { font-size:20px; }
        #viewModal .modal-body { display:block; padding:26px; overflow-y:auto; }
        #viewModal .modal-footer { padding:16px 26px; background:#f8fafc; flex-shrink:0; }
        .journal-detail-meta { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; padding:18px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; overflow-wrap:anywhere; line-height:1.7; }
        .journal-detail-table-wrap { overflow-x:auto; border:1px solid #e2e8f0; border-radius:10px; }
        #viewModal table { min-width:620px; }
        #viewModal table th { padding:13px 16px!important; color:#475569; }
        #viewModal table td { padding:15px 16px!important; line-height:1.6; }
        #viewModal table th:first-child { width:40%; }
        #viewModal table th:last-child { width:28%; }
        .journal-detail-actions { display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-top:22px; }
        .journal-detail-actions a, .journal-detail-actions button { margin:0!important; padding:11px 16px!important; border-radius:7px!important; font-size:13px; }
        @media(max-width:640px) { #viewModal .modal-body { padding:16px; } .journal-detail-meta { grid-template-columns:1fr; gap:8px; } }
        .journal-editor-popup { padding:0 0 24px!important; border-radius:14px!important; overflow:hidden; }
        .journal-editor-popup .swal2-title { text-align:left; padding:22px 26px; margin:0; background:#eff6ff; color:#1e3a8a; font-size:22px; }
        .journal-editor-popup .swal2-html-container { margin:0; padding:24px 26px; overflow-x:hidden; text-align:left; font-size:13px; }
        .journal-editor-popup .swal2-actions { justify-content:flex-end; margin:0; padding:0 26px; }
        .journal-editor-popup input, .journal-editor-popup select, .journal-editor-popup textarea { box-sizing:border-box; width:100%; min-width:0; border:1px solid #cbd5e1; border-radius:7px; padding:10px 12px; background:#fff; color:#1e293b; font-size:13px; }
        .journal-editor-popup input:focus, .journal-editor-popup select:focus, .journal-editor-popup textarea:focus { outline:2px solid #bfdbfe; border-color:#2563eb; }
        .journal-editor-popup input[type=number] { text-align:right; }
        .journal-editor-popup .je-grid { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr) minmax(0,1fr) minmax(0,1.5fr); gap:12px; }
        .journal-editor-popup [hidden] { display:none!important; }
        .uj-popup { max-width:calc(100vw - 32px)!important; }
        .uj-popup .swal2-html-container { max-height:calc(90vh - 170px); overflow-y:auto; }
        .uj-popup .swal2-actions { padding-top:18px; border-top:1px solid #e2e8f0; }
        .uj-form input, .uj-form select { height:40px; align-self:center; }
        .uj-form .select2-container { min-width:0; width:100%!important; }
        .uj-form .select2-selection--single { height:40px!important; border:1px solid #cbd5e1!important; border-radius:7px!important; }
        .uj-form .select2-selection__rendered { line-height:38px!important; padding-left:12px!important; font-size:13px; }
        .uj-form .select2-selection__arrow { height:38px!important; }
        .uj-popup .select2-dropdown { border-color:#cbd5e1; }
        .uj-popup .select2-search__field { height:36px; }
        .uj-form input[type=number] { font-variant-numeric:tabular-nums; }
        .uj-section { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:24px 0 12px; }
        .uj-section strong { font-size:15px; color:#1e293b; }
        .uj-section span { font-size:12px; color:#64748b; }
        .uj-table { border:1px solid #e2e8f0; border-radius:9px; overflow-x:auto; }
        .uj-line-grid { display:grid; grid-template-columns:28px minmax(180px,2fr) minmax(150px,1.5fr) minmax(100px,1fr) minmax(100px,1fr) minmax(140px,1.4fr) 32px; gap:10px; min-width:880px; align-items:center; }
        .uj-heading { padding:12px; background:#f1f5f9; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; }
        .uj-row { padding:12px; border-top:1px solid #e2e8f0; }
        .uj-row:nth-child(even) { background:#fafcff; }
        .uj-row-number { color:#94a3b8; font-weight:600; }
        .uj-remove { height:30px; width:30px; border-radius:6px; color:#dc2626; background:#fff1f2; font-size:20px; }
        .uj-remove:disabled { opacity:.3; cursor:not-allowed; }
        .uj-add { margin-top:12px; padding:9px 14px; border:1px solid #bfdbfe; border-radius:7px; color:#1d4ed8; background:#eff6ff; font-weight:600; }
        .uj-summary { display:grid; grid-template-columns:1fr 1fr 1fr auto; align-items:center; gap:20px; margin:22px 0 0; padding:18px; border:1px solid #e2e8f0; background:#f8fafc; border-radius:10px; }
        .uj-summary span { display:block; font-size:11px; color:#64748b; margin-bottom:5px; }
        .uj-summary strong { font-size:18px; color:#1e293b; font-variant-numeric:tabular-nums; }
        .uj-summary .is-balanced { color:#15803d; background:#dcfce7; padding:8px 12px; border-radius:20px; font-weight:600; }
        .uj-summary .is-pending { color:#64748b; font-size:12px; }
        @media(max-width:640px) { .uj-summary { grid-template-columns:1fr 1fr; } .uj-section { align-items:flex-start; flex-direction:column; } }
        .journal-editor-popup .je-heading { margin-top:22px; padding:10px 12px; background:#f1f5f9; border-radius:7px; font-weight:700; color:#475569; }
        .journal-editor-popup .je-row { padding:12px; border-bottom:1px solid #e2e8f0; }
        .journal-editor-popup .je-notice { padding:12px 14px; margin:0 0 18px; background:#f8fafc; border-left:3px solid #2563eb; line-height:1.6; }
        .journal-editor-popup label { display:block; font-weight:600; margin:14px 0 7px; color:#334155; }
        .journal-editor-popup .je-totals { margin:16px 0; padding:14px; background:#f0fdf4; border-radius:8px; font-weight:700; text-align:right; }
        @media(max-width:640px) { .journal-editor-popup .je-grid { grid-template-columns:minmax(0,1fr) minmax(0,1fr); } .journal-editor-popup .swal2-html-container { padding:16px; } }
    </style>
@endsection

@section('main-content')

{{-- ═══════════════════ MAIN CARD ═══════════════════ --}}
<div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
    <div class="states-table-container">

        {{-- Header --}}
        <div class="jnl-page-header">
            <div class="header-title">
                <div class="header-icon"><i class="fas fa-book"></i></div>
                <h2>Journal Entries
                    <small>Manage general ledger journal entries</small>
                </h2>
            </div>
            @can('create journal')
            <div class="jnl-header-actions">
                <button type="button" id="openUnifiedJournal" class="btn-hdr btn-hdr-credit">
                    <i class="fas fa-plus"></i>New Journal
                </button>
                <div class="opening-dropdown" id="openingDropdown">
                    <button type="button" class="btn-hdr btn-hdr-opening" id="openingDropdownToggle">
                        <i class="fas fa-layer-group"></i>Opening Balances
                        <i class="fas fa-chevron-down caret"></i>
                    </button>
                    <div class="opening-menu">
                        <button type="button" id="openCreateModalRcv" class="om-rcv">
                            <i class="fas fa-file-invoice-dollar"></i>Opening Receivable
                        </button>
                        <button type="button" id="openCreateModalPay" class="om-pay">
                            <i class="fas fa-hand-holding-usd"></i>Opening Payable
                        </button>
                        <button type="button" id="openCreateModalAst" class="om-ast">
                            <i class="fas fa-boxes-stacked"></i>Opening Asset
                        </button>
                    </div>
                </div>
            </div>
            @endcan
        </div>

        {{-- Stats strip --}}
        @php
            $totalJournals   = $journals->total();
            $totalDebitAll   = $journals->getCollection()->sum(fn($j) => $j->items->sum('debit'));
            $totalCreditAll  = $journals->getCollection()->sum(fn($j) => $j->items->sum('credit'));
            $balancedCount   = $journals->getCollection()->filter(fn($j) => abs($j->items->sum('debit') - $j->items->sum('credit')) < 0.01)->count();
        @endphp
        <div class="jnl-stats-strip">
            <div class="jnl-stat-card stat-total">
                <div class="stat-icon"><i class="fas fa-list-ol"></i></div>
                <div>
                    <div class="stat-label">Total Entries</div>
                    <div class="stat-value">{{ number_format($totalJournals) }}</div>
                </div>
            </div>
            <div class="jnl-stat-card stat-debit">
                <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                <div>
                    <div class="stat-label">Total Debit (page)</div>
                    <div class="stat-value">{{ number_format($totalDebitAll, 2) }}</div>
                </div>
            </div>
            <div class="jnl-stat-card stat-credit">
                <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
                <div>
                    <div class="stat-label">Total Credit (page)</div>
                    <div class="stat-value">{{ number_format($totalCreditAll, 2) }}</div>
                </div>
            </div>
            <div class="jnl-stat-card stat-balanced">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-label">Balanced (page)</div>
                    <div class="stat-value">{{ $balancedCount }} / {{ $journals->count() }}</div>
                </div>
            </div>
        </div>

        <div class="states-table-content">

            {{-- ── Filter Panel ── --}}
            <form method="GET" id="filterForm" class="lfb-panel" style="margin:16px 14px;">
                @if(request('reference'))
                    <input type="hidden" name="reference" value="{{ request('reference') }}">
                @endif
                <x-list-filter-bar from="date_from" to="date_to" placeholder="Search reference, description, account or amount..." :total="$journals->total()">
                    @if(Auth::user()->is_super_admin)
                        <select name="company_id" aria-label="Company">
                            <option value="">All Companies / Group</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" @selected((int)$companyId === (int)$company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if($companyId)
                        <select name="project_id" aria-label="Project">
                            <option value="">All projects</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected((int)$projectId === (int)$project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select name="source" aria-label="Source">
                        <option value="">All Sources</option>
                        @foreach($sources as $src)
                            <option value="{{ $src }}" @selected(request('source') === $src)>{{ Str::headline($src) }}</option>
                        @endforeach
                    </select>
                    <x-slot:actions>
                        <button type="button" id="printJournalBtn" class="lfb-action"><i class="fas fa-print"></i> Print</button>
                    </x-slot:actions>
                </x-list-filter-bar>
            </form>

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="flash-alert flash-success">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @endif

            {{-- ── Table ── --}}
            <div class="table-wrapper" id="printArea">
                <span class="print-meta">
                    Printed on: {{ now()->format('d M Y, h:i A') }}
                    @if(request()->hasAny(['company_id','company','project_id','date_from','date_to','reference','source']))
                        &nbsp;|&nbsp; Filters:
                        @if(request('date_from')) From: {{ request('date_from') }} @endif
                        @if(request('date_to')) To: {{ request('date_to') }} @endif
                        @if(request('reference')) Ref: {{ request('reference') }} @endif
                        @if(request('source')) Source: {{ request('source') }} @endif
                        @if($projectId) Project: {{ optional($projects->firstWhere('id', $projectId))->name }} @endif
                    @endif
                </span>
                @if($journals->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-book-open"></i></div>
                        <h4>No Journal Entries Found</h4>
                        <p>Adjust your filters or create a new entry to get started.</p>
                    </div>
                @else
                <div class="table-toolbar">
                    <span class="toolbar-info">
                        <i class="fas fa-table mr-1"></i>
                        Showing {{ $journals->firstItem() }}–{{ $journals->lastItem() }} of {{ $journals->total() }} entries
                    </span>
                </div>
                <div class="table-scroll">
                <table class="journal-table" id="journalTable">
                    <thead>
                        <tr>
                            {{-- Balanced has no sort link: it is derived by comparing
                                 the two totals, so there is no column to order on.
                                 # and Actions stay plain for the usual reasons. --}}
                            <th class="row-num">#</th>
                            <th>@include('partials.sortable-th', ['key' => 'date', 'label' => 'Date', 'dirDefault' => 'desc'])</th>
                            <th>@include('partials.sortable-th', ['key' => 'reference', 'label' => 'Reference'])</th>
                            <th>@include('partials.sortable-th', ['key' => 'source', 'label' => 'Source'])</th>
                            <th>@include('partials.sortable-th', ['key' => 'description', 'label' => 'Description'])</th>
                            <th style="text-align:right">@include('partials.sortable-th', ['key' => 'debit', 'label' => 'Total Debit', 'dirDefault' => 'desc'])</th>
                            <th style="text-align:right">@include('partials.sortable-th', ['key' => 'credit', 'label' => 'Total Credit', 'dirDefault' => 'desc'])</th>
                            <th>Balanced</th>
                            <th>@include('partials.sortable-th', ['key' => 'created_by', 'label' => 'Created By'])</th>
                            <th class="no-print" style="text-align:center;width:1%;white-space:nowrap">Actions</th>
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
                            <td data-label="#" class="row-num" style="text-align:center;color:#94a3b8;font-size:12px;font-weight:600;">{{ $journals->firstItem() + $index }}</td>

                            <td data-label="Date" style="font-size:13px;">
                                @include('partials.date-time-cell', ['date' => $journal->date, 'recordedAt' => $journal->created_at])
                            </td>

                            <td data-label="Reference">
                                @if($journal->reference)
                                    <span class="code-chip">{{ $journal->reference }}</span>
                                @else
                                    <span style="color:#cbd5e1;">—</span>
                                @endif
                            </td>

                            <td data-label="Source">
                                <span class="source-badge source-{{ $journal->source }}">
                                    {{ ucfirst(str_replace('_', ' ', $journal->source)) }}
                                </span>
                            </td>

                            <td data-label="Description" style="max-width:180px;">
                                <span style="font-size:13px;color:#64748b;">
                                    {{ Str::limit($journal->description, 38) ?: '—' }}
                                </span>
                            </td>

                            <td data-label="Total Debit" style="text-align:right;">
                                <span class="amount-debit">{{ number_format($totalDebit, 2) }}</span>
                            </td>

                            <td data-label="Total Credit" style="text-align:right;">
                                <span class="amount-credit">{{ number_format($totalCredit, 2) }}</span>
                            </td>

                            <td data-label="Balanced">
                                @if($isBalanced)
                                    <span class="balanced-yes"><i class="fas fa-check"></i>Yes</span>
                                @else
                                    <span class="balanced-no"><i class="fas fa-times"></i>No</span>
                                @endif
                            </td>

                            <td data-label="Created By">
                                <span style="font-size:13px;">{{ $journal->createdBy?->name ?? '—' }}</span>
                            </td>

                            <td data-label="Actions" class="no-print" style="text-align:center;">
                                <div class="action-cell" style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
                                    {{-- View --}}
                                    <button class="btn-action btn-view btn-view-journal"
                                            title="View Items"
                                            data-id="{{ $journal->id }}" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                        <i class="fas fa-eye" style="font-size:11px;margin:0"></i>
                                    </button>

                                    {{-- Print Company Voucher --}}
                                    <a class="btn-action btn-voucher"
                                       title="Company Voucher"
                                       href="{{ route('role.journals.voucher', ['role' => Str::slug(auth()->user()->getRoleNames()->first()), 'journal' => $journal->id]) }}"
                                       target="_blank" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                        <i class="fas fa-print" style="font-size:11px;margin:0"></i>
                                    </a>

                                        @can('edit journal')
                                        @if(!$journal->reversed_journal_entry_id && !$journal->reversedBy)
                                        <button type="button" class="btn-action btn-edit btn-correct-journal"
                                                title="Edit debit and credit journal rows"
                                                aria-label="Edit journal {{ $journal->reference }}"
                                                data-id="{{ $journal->id }}" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                            <i class="fas fa-pen" style="font-size:11px;margin:0"></i>
                                        </button>
                                        @endif
                                        @endcan

                                    @if($journal->source === 'manual')
                                        @can('delete journal')
                                        <button class="btn-action btn-delete btn-delete-journal"
                                                title="Delete"
                                                data-id="{{ $journal->id }}"
                                                data-ref="{{ $journal->reference ?? 'Entry #'.$journal->id }}" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                            <i class="fas fa-trash-alt" style="font-size:11px;margin:0"></i>
                                        </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot id="printTotalsRow">
                        <tr style="background:#f1f5f9;font-weight:700;border-top:2px solid #cbd5e1;">
                            <td colspan="5" style="padding:7px 14px;font-size:12px;color:#475569;">Page Total ({{ $journals->count() }} entries)</td>
                            <td style="padding:7px 14px;text-align:right;color:#1d4ed8;font-family:monospace;">{{ number_format($totalDebitAll, 2) }}</td>
                            <td style="padding:7px 14px;text-align:right;color:#15803d;font-family:monospace;">{{ number_format($totalCreditAll, 2) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>

                {{-- Pagination --}}
                @if($journals->hasPages())
                <div class="pagination-row">
                    <span>Showing {{ $journals->firstItem() }}–{{ $journals->lastItem() }} of {{ $journals->total() }} entries</span>
                    <div>{{ $journals->links() }}</div>
                </div>
                @endif
                </div>{{-- /.table-scroll --}}
                @endif
            </div>{{-- /.table-wrapper --}}

        </div>
    </div>
</div>

{{-- ═══════════════════ CREDIT MODAL ═══════════════════ --}}
{{-- Credit: Bank account gets DEBITED, selected account gets CREDITED --}}
<div class="modal-overlay" id="journalModalV2">
    <div class="modal-box" style="max-width: 600px">
        <div class="modal-header" style="background:#16a34a;">
            <h4><i class="fas fa-arrow-down mr-2"></i>Add Credit Journal <small style="font-size:13px;opacity:.8;">(Money IN)</small></h4>
            <button type="button" class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
        </div>
        <form id="creditJournalForm" novalidate>
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group half">
                        <label>Company <span class="req">*</span></label>
                        <select id="cr_company_id" name="company_id" class="form-control select2-modal" style="width:100%">
                            <option value="" disabled selected>— Select Company —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="cr_err_company"></div>
                    </div>
                    <div class="form-group half">
                        <label>Date <span class="req">*</span></label>
                        <input type="date" id="cr_date" class="form-control" required>
                        <div class="invalid-feedback" id="cr_err_date"></div>
                    </div>
                    <div class="form-group half">
                        <label>Reference</label>
                        <input type="text" id="cr_reference" class="form-control" placeholder="e.g. JV-001" maxlength="100">
                    </div>
                    <div class="form-group half">
                        <label>Bank <span class="req">*</span></label>
                        <select id="cr_bank_id" class="form-control select2-modal" style="width:100%">
                            <option value="">— Select Bank —</option>
                            @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
                        </select>
                        <div class="invalid-feedback" id="cr_err_bank"></div>
                    </div>
                    <div class="form-group half">
                        <label>Credit Account <span class="req">*</span></label>
                        <select id="cr_account_id" class="form-control select2-modal" style="width:100%">
                            <option value="">— Select Account —</option>
                            @foreach($creditAccounts as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }} ({{ ucfirst($account->type) }})</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="cr_err_account"></div>
                    </div>
                    <div class="form-group half">
                        <label>Amount <span class="req">*</span></label>
                        <input type="number" id="cr_amount" class="form-control" min="0.01" step="0.01" placeholder="0.00">
                        <div class="invalid-feedback" id="cr_err_amount"></div>
                    </div>
                    <div class="form-group half">
                        <label>Description</label>
                        <textarea id="cr_description" class="form-control" rows="2" maxlength="500" placeholder="Optional note…"></textarea>
                    </div>
                </div>

                {{-- Preview --}}
                <div id="cr_preview" style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px;margin-top:8px;font-size:13px;">
                    <strong style="color:#16a34a;">Journal Preview:</strong>
                    <table style="width:100%;margin-top:6px;border-collapse:collapse;">
                        <thead><tr style="background:#dcfce7;">
                            <th style="padding:4px 8px;text-align:left;">Account</th>
                            <th style="padding:4px 8px;text-align:right;">Debit</th>
                            <th style="padding:4px 8px;text-align:right;">Credit</th>
                        </tr></thead>
                        <tbody>
                            <tr><td style="padding:4px 8px;" id="cr_preview_bank">—</td><td style="padding:4px 8px;text-align:right;" id="cr_preview_debit">—</td><td style="padding:4px 8px;text-align:right;">—</td></tr>
                            <tr><td style="padding:4px 8px;" id="cr_preview_account">—</td><td style="padding:4px 8px;text-align:right;">—</td><td style="padding:4px 8px;text-align:right;" id="cr_preview_credit">—</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="cancelModal" class="btn-modal-cancel">Cancel</button>
                <button type="submit" id="cr_submitBtn" class="btn-modal-save" style="background:#16a34a;">
                    <i class="fas fa-save"></i>Save Credit Journal
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════ DEBIT MODAL ═══════════════════ --}}
{{-- Debit: Selected account gets DEBITED, Bank account gets CREDITED --}}
<div class="modal-overlay" id="journalModalV22">
    <div class="modal-box" style="max-width: 600px">
        <div class="modal-header" style="background:#dc2626;">
            <h4><i class="fas fa-arrow-up mr-2"></i>Add Debit Journal <small style="font-size:13px;opacity:.8;">(Money OUT)</small></h4>
            <button type="button" class="modal-close" id="closeModalV2"><i class="fas fa-times"></i></button>
        </div>
        <form id="debitJournalForm" novalidate>
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group half">
                        <label>Company <span class="req">*</span></label>
                        <select id="dr_company_id" name="company_id" class="form-control select2-modal" style="width:100%">
                            <option value="" disabled selected>— Select Company —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="dr_err_company"></div>
                    </div>
                    <div class="form-group half">
                        <label>Date <span class="req">*</span></label>
                        <input type="date" id="dr_date" class="form-control" required>
                        <div class="invalid-feedback" id="dr_err_date"></div>
                    </div>
                    <div class="form-group half">
                        <label>Reference</label>
                        <input type="text" id="dr_reference" class="form-control" placeholder="e.g. JV-001" maxlength="100">
                    </div>
                    <div class="form-group half">
                        <label>Bank <span class="req">*</span></label>
                        <select id="dr_bank_id" class="form-control select2-modal" style="width:100%">
                            <option value="">— Select Bank —</option>
                            @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
                        </select>
                        <div class="invalid-feedback" id="dr_err_bank"></div>
                    </div>
                    <div class="form-group half">
                        <label>Debit Account <span class="req">*</span></label>
                        <select id="dr_account_id" class="form-control select2-modal" style="width:100%">
                            <option value="">— Select Account —</option>
                            @foreach($debitAccounts as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }} ({{ ucfirst($account->type) }})</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="dr_err_account"></div>
                    </div>
                    <div class="form-group half">
                        <label>Amount <span class="req">*</span></label>
                        <input type="number" id="dr_amount" class="form-control" min="0.01" step="0.01" placeholder="0.00">
                        <div class="invalid-feedback" id="dr_err_amount"></div>
                    </div>
                    <div class="form-group half">
                        <label>Description</label>
                        <textarea id="dr_description" class="form-control" rows="2" maxlength="500" placeholder="Optional note…"></textarea>
                    </div>
                </div>

                {{-- Preview --}}
                <div id="dr_preview" style="display:none;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:12px;margin-top:8px;font-size:13px;">
                    <strong style="color:#dc2626;">Journal Preview:</strong>
                    <table style="width:100%;margin-top:6px;border-collapse:collapse;">
                        <thead><tr style="background:#fee2e2;">
                            <th style="padding:4px 8px;text-align:left;">Account</th>
                            <th style="padding:4px 8px;text-align:right;">Debit</th>
                            <th style="padding:4px 8px;text-align:right;">Credit</th>
                        </tr></thead>
                        <tbody>
                            <tr><td style="padding:4px 8px;" id="dr_preview_account">—</td><td style="padding:4px 8px;text-align:right;" id="dr_preview_debit">—</td><td style="padding:4px 8px;text-align:right;">—</td></tr>
                            <tr><td style="padding:4px 8px;" id="dr_preview_bank">—</td><td style="padding:4px 8px;text-align:right;">—</td><td style="padding:4px 8px;text-align:right;" id="dr_preview_credit">—</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="cancelModalV2" class="btn-modal-cancel">Cancel</button>
                <button type="submit" id="dr_submitBtn" class="btn-modal-save" style="background:#dc2626;">
                    <i class="fas fa-save"></i>Save Debit Journal
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ═══════════════════ OPENING RECEIVABLE MODAL ═══════════════════ --}}
{{-- Flow: DR — Accounts Receivable (per customer) | CR — Opening Balance Equity (total) --}}
<div class="modal-overlay" id="journalModalRcv">
    <div class="modal-box" style="max-width:820px;">
        <div class="modal-header" style="background:#0369a1;">
            <h4><i class="fas fa-file-invoice-dollar mr-2"></i>Add Customer Due Payments <small style="font-size:13px;opacity:.8;">(Opening Receivables)</small></h4>
            <button type="button" class="modal-close" id="closeModalRcv"><i class="fas fa-times"></i></button>
        </div>
        <form id="receivableJournalForm" novalidate>
            @csrf
            <div class="modal-body">

                {{-- Accounting flow indicator --}}
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:11px 16px;margin-bottom:16px;font-size:13px;color:#1d4ed8;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Accounting Flow:</strong>
                    <span style="background:#dbeafe;color:#1d4ed8;padding:3px 10px;border-radius:4px;font-weight:700;"><i class="fas fa-arrow-up mr-1"></i>DR — Accounts Receivable</span>
                    <span style="color:#94a3b8;">→</span>
                    <span style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:4px;font-weight:700;"><i class="fas fa-arrow-down mr-1"></i>CR — Opening Balance Equity</span>
                </div>

                {{-- Header fields --}}
                <div class="form-row">
                    <div class="form-group half">
                        <label>Company <span class="req">*</span></label>
                        <select id="rcv_company_id" class="form-control" style="width:100%">
                            <option value="" disabled selected>— Select Company —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="rcv_err_company"></div>
                    </div>
                    <div class="form-group half">
                        <label>As of Date <span class="req">*</span></label>
                        <input type="date" id="rcv_date" class="form-control">
                        <div class="invalid-feedback" id="rcv_err_date"></div>
                    </div>
                    <div class="form-group half">
                        <label>Reference</label>
                        <input type="text" id="rcv_reference" class="form-control" placeholder="e.g. OB-RCV-001" maxlength="100">
                    </div>
                    <div class="form-group half">
                        <label>Description</label>
                        <input type="text" id="rcv_description" class="form-control" placeholder="Opening receivables as of date" maxlength="500">
                    </div>
                    <div class="form-group half">
                        <label>Receivable Account <span class="req" title="Debit side — typically Accounts Receivable (Asset)">*</span></label>
                        <select id="rcv_ar_account_id" class="form-control" style="width:100%">
                            <option value="">— Select Asset Account —</option>
                            @foreach($debitAccounts->where('type', 'asset') as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="rcv_err_ar"></div>
                    </div>
                    <div class="form-group half">
                        <label>Opening Balance Equity Account <span class="req" title="Credit side — Opening Balance Equity">*</span></label>
                        <select id="rcv_equity_account_id" class="form-control" style="width:100%">
                            <option value="">— Select Equity Account —</option>
                            @foreach($creditAccounts->where('type', 'equity') as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="rcv_err_equity"></div>
                    </div>
                </div>

                {{-- Party due rows table --}}
                <div class="items-section">
                    <div class="section-title">
                        <span><i class="fas fa-users mr-1"></i>Due Amounts by Party <span class="req">*</span></span>
                        <button type="button" id="rcv_addRow" class="btn-add-row">
                            <i class="fas fa-plus mr-1"></i>Add Row
                        </button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="items-table" style="min-width:760px;">
                            <thead>
                                <tr>
                                    <th style="width:14%">Party Type <span class="req">*</span></th>
                                    <th style="width:20%">Party <span class="req">*</span></th>
                                    <th style="width:13%">Invoice / Ref No.</th>
                                    <th style="width:13%">Amount (৳) <span class="req">*</span></th>
                                    <th style="width:14%;background:#fffbeb;color:#92400e;">Due Date <i class="fas fa-calendar" style="font-size:10px;margin-left:3px;"></i></th>
                                    <th style="width:22%">Note</th>
                                    <th style="width:4%"></th>
                                </tr>
                            </thead>
                            <tbody id="rcv_tbody"></tbody>
                        </table>
                    </div>
                    <div class="invalid-feedback" id="rcv_err_rows" style="margin-top:6px;"></div>
                </div>

                {{-- Live journal preview --}}
                <div id="rcv_preview" style="display:none;background:#eff6ff;border:1.5px solid #93c5fd;border-radius:8px;padding:14px;margin-top:14px;font-size:13px;">
                    <strong style="color:#1d4ed8;"><i class="fas fa-eye mr-1"></i>Journal Entry Preview</strong>
                    <table style="width:100%;margin-top:8px;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#dbeafe;">
                                <th style="padding:5px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#1e40af;">Account</th>
                                <th style="padding:5px 10px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;color:#1e40af;">Debit (৳)</th>
                                <th style="padding:5px 10px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;color:#1e40af;">Credit (৳)</th>
                            </tr>
                        </thead>
                        <tbody id="rcv_preview_tbody"></tbody>
                        <tfoot>
                            <tr style="background:#f0f9ff;font-weight:700;border-top:2px solid #bfdbfe;">
                                <td style="padding:5px 10px;">Total</td>
                                <td style="padding:5px 10px;text-align:right;color:#1d4ed8;" id="rcv_preview_total_dr">—</td>
                                <td style="padding:5px 10px;text-align:right;color:#15803d;" id="rcv_preview_total_cr">—</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" id="cancelModalRcv" class="btn-modal-cancel">Cancel</button>
                <button type="submit" id="rcv_submitBtn" class="btn-modal-save" style="background:#0369a1;">
                    <i class="fas fa-save"></i>Save Receivable Entry
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════ OPENING PAYABLE MODAL ═══════════════════ --}}
{{-- Flow: DR — Opening Balance Equity (total) | CR — Accounts Payable (per supplier) --}}
<div class="modal-overlay" id="journalModalPay">
    <div class="modal-box" style="max-width:820px;">
        <div class="modal-header" style="background:#d97706;">
            <h4><i class="fas fa-hand-holding-usd mr-2"></i>Add Supplier Due Amounts <small style="font-size:13px;opacity:.8;">(Opening Payables)</small></h4>
            <button type="button" class="modal-close" id="closeModalPay"><i class="fas fa-times"></i></button>
        </div>
        <form id="payableJournalForm" novalidate>
            @csrf
            <div class="modal-body">

                {{-- Accounting flow indicator --}}
                <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:11px 16px;margin-bottom:16px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Accounting Flow:</strong>
                    <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:4px;font-weight:700;"><i class="fas fa-arrow-up mr-1"></i>DR — Opening Balance Equity</span>
                    <span style="color:#94a3b8;">→</span>
                    <span style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:4px;font-weight:700;"><i class="fas fa-arrow-down mr-1"></i>CR — Accounts Payable</span>
                </div>

                {{-- Header fields --}}
                <div class="form-row">
                    <div class="form-group half">
                        <label>Company <span class="req">*</span></label>
                        <select id="pay_company_id" class="form-control" style="width:100%">
                            <option value="" disabled selected>— Select Company —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="pay_err_company"></div>
                    </div>
                    <div class="form-group half">
                        <label>As of Date <span class="req">*</span></label>
                        <input type="date" id="pay_date" class="form-control">
                        <div class="invalid-feedback" id="pay_err_date"></div>
                    </div>
                    <div class="form-group half">
                        <label>Reference</label>
                        <input type="text" id="pay_reference" class="form-control" placeholder="e.g. OB-PAY-001" maxlength="100">
                    </div>
                    <div class="form-group half">
                        <label>Description</label>
                        <input type="text" id="pay_description" class="form-control" placeholder="Opening supplier payables as of date" maxlength="500">
                    </div>
                    <div class="form-group half">
                        <label>Payable Account <span class="req" title="Credit side — typically Accounts Payable (Liability)">*</span></label>
                        <select id="pay_ap_account_id" class="form-control" style="width:100%">
                            <option value="">— Select Liability Account —</option>
                            @foreach($creditAccounts->where('type', 'liability') as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="pay_err_ap"></div>
                    </div>
                    <div class="form-group half">
                        <label>Opening Balance Equity Account <span class="req" title="Debit side — Opening Balance Equity">*</span></label>
                        <select id="pay_equity_account_id" class="form-control" style="width:100%">
                            <option value="">— Select Equity Account —</option>
                            @foreach($creditAccounts->where('type', 'equity') as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="pay_err_equity"></div>
                    </div>
                </div>

                {{-- Party due rows table --}}
                <div class="items-section">
                    <div class="section-title" style="color:#92400e;border-bottom-color:#fde68a;">
                        <span><i class="fas fa-truck mr-1"></i>Payable Amounts by Supplier <span class="req">*</span></span>
                        <button type="button" id="pay_addRow" class="btn-add-row" style="background:#fef3c7;color:#92400e;">
                            <i class="fas fa-plus mr-1"></i>Add Row
                        </button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="items-table" style="min-width:760px;">
                            <thead>
                                <tr>
                                    <th style="width:14%">Party Type <span class="req">*</span></th>
                                    <th style="width:20%">Party <span class="req">*</span></th>
                                    <th style="width:13%">Bill / PO Ref No.</th>
                                    <th style="width:13%">Amount (৳) <span class="req">*</span></th>
                                    <th style="width:14%;background:#fffbeb;color:#92400e;">Due Date <i class="fas fa-calendar" style="font-size:10px;margin-left:3px;"></i></th>
                                    <th style="width:22%">Note</th>
                                    <th style="width:4%"></th>
                                </tr>
                            </thead>
                            <tbody id="pay_tbody"></tbody>
                        </table>
                    </div>
                    <div class="invalid-feedback" id="pay_err_rows" style="margin-top:6px;"></div>
                </div>

                {{-- Live journal preview --}}
                <div id="pay_preview" style="display:none;background:#fffbeb;border:1.5px solid #fcd34d;border-radius:8px;padding:14px;margin-top:14px;font-size:13px;">
                    <strong style="color:#92400e;"><i class="fas fa-eye mr-1"></i>Journal Entry Preview</strong>
                    <table style="width:100%;margin-top:8px;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#fef3c7;">
                                <th style="padding:5px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#92400e;">Account</th>
                                <th style="padding:5px 10px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;color:#92400e;">Debit (৳)</th>
                                <th style="padding:5px 10px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;color:#92400e;">Credit (৳)</th>
                            </tr>
                        </thead>
                        <tbody id="pay_preview_tbody"></tbody>
                        <tfoot>
                            <tr style="background:#fffbeb;font-weight:700;border-top:2px solid #fcd34d;">
                                <td style="padding:5px 10px;">Total</td>
                                <td style="padding:5px 10px;text-align:right;color:#1d4ed8;" id="pay_preview_total_dr">—</td>
                                <td style="padding:5px 10px;text-align:right;color:#15803d;" id="pay_preview_total_cr">—</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" id="cancelModalPay" class="btn-modal-cancel">Cancel</button>
                <button type="submit" id="pay_submitBtn" class="btn-modal-save" style="background:#d97706;">
                    <i class="fas fa-save"></i>Save Payable Entry
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════ OPENING ASSET MODAL ═══════════════════ --}}
{{-- Flow: DR — Asset Account (per row) | CR — Opening Balance Equity (total) --}}
<div class="modal-overlay" id="journalModalAst">
    <div class="modal-box" style="max-width:900px;">
        <div class="modal-header" style="background:#059669;">
            <h4><i class="fas fa-boxes-stacked mr-2"></i>Add Existing Assets <small style="font-size:13px;opacity:.8;">(Office Materials &amp; Equipment)</small></h4>
            <button type="button" class="modal-close" id="closeModalAst"><i class="fas fa-times"></i></button>
        </div>
        <form id="assetJournalForm" novalidate>
            @csrf
            <div class="modal-body">

                {{-- Accounting flow indicator --}}
                <div style="background:#ecfdf5;border:1px solid #6ee7b7;border-radius:8px;padding:11px 16px;margin-bottom:16px;font-size:13px;color:#065f46;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Accounting Flow:</strong>
                    <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:4px;font-weight:700;"><i class="fas fa-arrow-up mr-1"></i>DR — Asset Account (e.g. Office Equipment)</span>
                    <span style="color:#94a3b8;">→</span>
                    <span style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:4px;font-weight:700;"><i class="fas fa-arrow-down mr-1"></i>CR — Opening Balance Equity</span>
                </div>

                {{-- Header fields --}}
                <div class="form-row">
                    <div class="form-group half">
                        <label>Company <span class="req">*</span></label>
                        <select id="ast_company_id" class="form-control" style="width:100%">
                            <option value="" disabled selected>— Select Company —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="ast_err_company"></div>
                    </div>
                    <div class="form-group half">
                        <label>As of Date <span class="req">*</span></label>
                        <input type="date" id="ast_date" class="form-control">
                        <div class="invalid-feedback" id="ast_err_date"></div>
                    </div>
                    <div class="form-group half">
                        <label>Reference</label>
                        <input type="text" id="ast_reference" class="form-control" placeholder="e.g. OB-AST-001" maxlength="100">
                    </div>
                    <div class="form-group half">
                        <label>Description</label>
                        <input type="text" id="ast_description" class="form-control" placeholder="Opening asset balances as of date" maxlength="500">
                    </div>
                    <div class="form-group half">
                        <label>Opening Balance Equity Account <span class="req" title="Credit side — Opening Balance Equity">*</span></label>
                        <select id="ast_equity_account_id" class="form-control" style="width:100%">
                            <option value="">— Select Equity Account —</option>
                            @foreach($creditAccounts->where('type', 'equity') as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="ast_err_equity"></div>
                    </div>
                </div>

                {{-- Asset items table --}}
                <div class="items-section">
                    <div class="section-title" style="color:#065f46;border-bottom-color:#a7f3d0;">
                        <span><i class="fas fa-boxes-stacked mr-1"></i>Asset Items <span class="req">*</span></span>
                        <button type="button" id="ast_addRow" class="btn-add-row" style="background:#d1fae5;color:#065f46;">
                            <i class="fas fa-plus mr-1"></i>Add Row
                        </button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="items-table" style="min-width:780px;">
                            <thead>
                                <tr>
                                    <th style="width:24%">Asset Account <span class="req">*</span></th>
                                    <th style="width:26%">Item / Description <span class="req">*</span></th>
                                    <th style="width:9%">Qty</th>
                                    <th style="width:17%">Value (৳) <span class="req">*</span></th>
                                    <th style="width:20%">Purchase Date</th>
                                    <th style="width:4%"></th>
                                </tr>
                            </thead>
                            <tbody id="ast_tbody"></tbody>
                        </table>
                    </div>
                    <div class="invalid-feedback" id="ast_err_rows" style="margin-top:6px;"></div>
                </div>

                {{-- Live journal preview --}}
                <div id="ast_preview" style="display:none;background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:8px;padding:14px;margin-top:14px;font-size:13px;">
                    <strong style="color:#065f46;"><i class="fas fa-eye mr-1"></i>Journal Entry Preview</strong>
                    <table style="width:100%;margin-top:8px;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#d1fae5;">
                                <th style="padding:5px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#065f46;">Account</th>
                                <th style="padding:5px 10px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;color:#065f46;">Debit (৳)</th>
                                <th style="padding:5px 10px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;color:#065f46;">Credit (৳)</th>
                            </tr>
                        </thead>
                        <tbody id="ast_preview_tbody"></tbody>
                        <tfoot>
                            <tr style="background:#f0fdf4;font-weight:700;border-top:2px solid #6ee7b7;">
                                <td style="padding:5px 10px;">Total</td>
                                <td style="padding:5px 10px;text-align:right;color:#1d4ed8;" id="ast_preview_total_dr">—</td>
                                <td style="padding:5px 10px;text-align:right;color:#15803d;" id="ast_preview_total_cr">—</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" id="cancelModalAst" class="btn-modal-cancel">Cancel</button>
                <button type="submit" id="ast_submitBtn" class="btn-modal-save" style="background:#059669;">
                    <i class="fas fa-save"></i>Save Asset Entry
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

@can('edit journal')
<div class="modal-overlay" id="reclassifyModal">
    <div class="modal-box" style="max-width:620px;">
        <div class="modal-header" style="background:#4338ca;">
            <h4><i class="fas fa-right-left mr-2"></i>Reclassify Account Head</h4>
            <button type="button" class="modal-close" id="closeReclassifyModal"><i class="fas fa-times"></i></button>
        </div>
        <form id="reclassifyForm">
            @csrf
            <div class="modal-body">
                <div style="padding:11px 13px;margin-bottom:14px;border:1px solid #c7d2fe;border-radius:8px;background:#eef2ff;color:#3730a3;font-size:12px;line-height:1.55;"><strong>Audit-safe correction:</strong> the original journal remains unchanged. A balanced adjustment moves this line from the old head to the new head.</div>
                <div class="form-group"><label>Current journal line</label><div id="reclassifyCurrent" style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:7px;background:#f8fafc;font-size:13px;"></div></div>
                <div class="form-row">
                    <div class="form-group half"><label>New Account Head <span class="req">*</span></label><select id="reclassifyAccount" class="form-control" style="width:100%"></select></div>
                    <div class="form-group half"><label>Effective Date <span class="req">*</span></label><input id="reclassifyDate" type="date" class="form-control" required></div>
                </div>
                <div class="form-group"><label>Correction Reason <span class="req">*</span></label><textarea id="reclassifyReason" class="form-control" rows="3" maxlength="500" required placeholder="Why is this account head being corrected?"></textarea><div style="font-size:11px;color:#64748b;margin-top:5px;">This reason becomes part of the permanent journal description.</div></div>
            </div>
            <div class="modal-footer"><button type="button" id="cancelReclassify" class="btn-modal-cancel">Cancel</button><button type="submit" id="saveReclassify" class="btn-modal-save" style="background:#4338ca;"><i class="fas fa-check"></i>Create Adjustment</button></div>
        </form>
    </div>
</div>
@endcan

{{-- Bank data for JS (account_id mapping) --}}
<script id="banksData" type="application/json">
    {!! json_encode($banks->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'account_id' => $b->account_id])) !!}
</script>
{{-- Account data for JS (merged for preview lookup) --}}
<script id="accountsData" type="application/json">
    {!! json_encode($creditAccounts->merge($debitAccounts)->map(fn($a) => ['id' => $a->id, 'company_id' => $a->company_id, 'text' => '[' . $a->code . '] ' . $a->name . ' (' . ucfirst($a->type) . ')'])) !!}
</script>
{{-- Party data for Opening Balance modals --}}
<script id="partiesData" type="application/json">
{!! json_encode([
    'customer' => $partyCustomers->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'sub' => $c->phone ?? '']),
    'supplier' => $partySuppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'sub' => $s->phone ?? '']),
    'agent'    => $partyAgents->map(fn($u)    => ['id' => $u->id, 'name' => $u->name, 'sub' => $u->phone ?? '']),
    'vendor'   => $partyVendors->map(fn($u)   => ['id' => $u->id, 'name' => $u->name, 'sub' => $u->phone ?? '']),
]) !!}
</script>
{{-- Asset accounts for Opening Asset modal --}}
<script id="assetAccountsData" type="application/json">
{!! json_encode($assetAccounts->map(fn($a) => ['id' => $a->id, 'text' => '[' . $a->code . '] ' . $a->name])) !!}
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

    const storeUrl   = '{{ route("role.journals.store", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
    const banksData  = JSON.parse(document.getElementById('banksData').textContent);
    const accountsData = JSON.parse(document.getElementById('accountsData').textContent);
    const canReclassifyJournal = @json(auth()->user()->can('edit journal'));
    let reclassifyContext = null;
    @include('journals.unified-entry-script')

    // ── Opening Balances dropdown toggle ────────────────
    $('#openingDropdownToggle').on('click', function (e) {
        e.stopPropagation();
        $('#openingDropdown').toggleClass('open');
    });
    $(document).on('click', function () {
        $('#openingDropdown').removeClass('open');
    });
    $('#openingDropdown').on('click', function (e) { e.stopPropagation(); });

    // ── Filter toggle ────────────────────────────────────
    $('#filterToggleHeader').on('click', function () {
        $(this).toggleClass('active');
        $('#filterBody').toggleClass('active');
    });
    $('#resetFilterBtn').on('click', function () {
        window.location.href = window.location.pathname;
    });

    // ── Print ─────────────────────────────────────────────
    $('#printJournalBtn').on('click', function () {
        window.print();
    });

    // ── Modal open/close ────────────────────────────────
    $('#openCreateModalV2').on('click', function () {
        resetCreditForm();
        $('#journalModalV2').addClass('show'); $('body').css('overflow', 'hidden');
    });
    $('#openCreateModalV22').on('click', function () {
        resetDebitForm();
        $('#journalModalV22').addClass('show'); $('body').css('overflow', 'hidden');
    });
    $('#closeModal, #cancelModal').on('click', function () {
        $('#journalModalV2').removeClass('show'); $('body').css('overflow', '');
    });
    $('#closeModalV2, #cancelModalV2').on('click', function () {
        $('#journalModalV22').removeClass('show'); $('body').css('overflow', '');
    });

    // ── Select2 ─────────────────────────────────────────
    $('.select2-item').select2();
    $('#cr_bank_id, #cr_account_id').select2({ dropdownParent: $('#journalModalV2') });
    $('#dr_bank_id, #dr_account_id').select2({ dropdownParent: $('#journalModalV22') });

    // ── Preview updater helpers ──────────────────────────
    function getAccountText(id) {
        const a = accountsData.find(x => x.id == id);
        return a ? a.text : '—';
    }
    function getBankName(id) {
        const b = banksData.find(x => x.id == id);
        return b ? b.name : '—';
    }
    function formatAmt(v) {
        const n = parseFloat(v);
        return (n > 0) ? n.toLocaleString('en-BD', {minimumFractionDigits:2}) : '—';
    }

    function updateCreditPreview() {
        const bankId    = $('#cr_bank_id').val();
        const accountId = $('#cr_account_id').val();
        const amount    = $('#cr_amount').val();
        if (bankId && accountId && parseFloat(amount) > 0) {
            $('#cr_preview_bank').text(getBankName(bankId));
            $('#cr_preview_account').text(getAccountText(accountId));
            $('#cr_preview_debit').text(formatAmt(amount));
            $('#cr_preview_credit').text(formatAmt(amount));
            $('#cr_preview').show();
        } else {
            $('#cr_preview').hide();
        }
    }

    function updateDebitPreview() {
        const bankId    = $('#dr_bank_id').val();
        const accountId = $('#dr_account_id').val();
        const amount    = $('#dr_amount').val();
        if (bankId && accountId && parseFloat(amount) > 0) {
            $('#dr_preview_account').text(getAccountText(accountId));
            $('#dr_preview_bank').text(getBankName(bankId));
            $('#dr_preview_debit').text(formatAmt(amount));
            $('#dr_preview_credit').text(formatAmt(amount));
            $('#dr_preview').show();
        } else {
            $('#dr_preview').hide();
        }
    }

    $('#cr_bank_id, #cr_account_id').on('change', updateCreditPreview);
    $('#cr_amount').on('input', updateCreditPreview);
    $('#dr_bank_id, #dr_account_id').on('change', updateDebitPreview);
    $('#dr_amount').on('input', updateDebitPreview);

    // ── Reset helpers ────────────────────────────────────
    function resetCreditForm() {
        $('#cr_date').val(''); $('#cr_reference').val('');
        $('#cr_bank_id').val('').trigger('change');
        $('#cr_account_id').val('').trigger('change');
        $('#cr_amount').val(''); $('#cr_description').val('');
        $('#cr_preview').hide();
        $('.invalid-feedback').text('');
    }
    function resetDebitForm() {
        $('#dr_date').val(''); $('#dr_reference').val('');
        $('#dr_bank_id').val('').trigger('change');
        $('#dr_account_id').val('').trigger('change');
        $('#dr_amount').val(''); $('#dr_description').val('');
        $('#dr_preview').hide();
        $('.invalid-feedback').text('');
    }

    // ── CREDIT Journal Submit ────────────────────────────
    // Bank account → Debit | Selected account → Credit
    $('#creditJournalForm').on('submit', function (e) {
        e.preventDefault();

        const companyId = $('#cr_company_id').val();
        const date      = $('#cr_date').val();
        const bankId    = $('#cr_bank_id').val();
        const accountId = $('#cr_account_id').val();
        const amount    = parseFloat($('#cr_amount').val());
        let valid = true;

        if (!companyId) { $('#cr_err_company').text('Company is required.'); valid = false; }
        else            { $('#cr_err_company').text(''); }
        if (!date)      { $('#cr_err_date').text('Date is required.');    valid = false; }
        else            { $('#cr_err_date').text(''); }
        if (!bankId)    { $('#cr_err_bank').text('Bank is required.');    valid = false; }
        else            { $('#cr_err_bank').text(''); }
        if (!accountId) { $('#cr_err_account').text('Account is required.'); valid = false; }
        else            { $('#cr_err_account').text(''); }
        if (!amount || amount <= 0) { $('#cr_err_amount').text('Enter a valid amount.'); valid = false; }
        else            { $('#cr_err_amount').text(''); }
        if (!valid) return;

        const bankAccountId = $('#cr_bank_id option:selected').data('account');
        if (!bankAccountId) {
            Swal.fire('Error', 'Selected bank has no linked account. Please configure the bank first.', 'error');
            return;
        }

        const payload = {
            _token:      $('meta[name="csrf-token"]').attr('content'),
            company_id:  companyId,
            date:        date,
            reference:   $('#cr_reference').val() || null,
            source:      'manual',
            description: $('#cr_description').val() || null,
            items: [
                { account_id: bankAccountId, debit: amount, credit: 0, note: 'Bank Debit' },
                { account_id: accountId,     debit: 0, credit: amount, note: 'Credit Entry' },
            ]
        };

        submitJournal(payload, '#cr_submitBtn', '#journalModalV2', resetCreditForm);
    });

    // ── DEBIT Journal Submit ─────────────────────────────
    // Selected account → Debit | Bank account → Credit
    $('#debitJournalForm').on('submit', function (e) {
        e.preventDefault();

        const companyId = $('#dr_company_id').val();
        const date      = $('#dr_date').val();
        const bankId    = $('#dr_bank_id').val();
        const accountId = $('#dr_account_id').val();
        const amount    = parseFloat($('#dr_amount').val());
        let valid = true;

        if (!companyId) { $('#dr_err_company').text('Company is required.'); valid = false; }
        else            { $('#dr_err_company').text(''); }
        if (!date)      { $('#dr_err_date').text('Date is required.');    valid = false; }
        else            { $('#dr_err_date').text(''); }
        if (!bankId)    { $('#dr_err_bank').text('Bank is required.');    valid = false; }
        else            { $('#dr_err_bank').text(''); }
        if (!accountId) { $('#dr_err_account').text('Account is required.'); valid = false; }
        else            { $('#dr_err_account').text(''); }
        if (!amount || amount <= 0) { $('#dr_err_amount').text('Enter a valid amount.'); valid = false; }
        else            { $('#dr_err_amount').text(''); }
        if (!valid) return;

        const bankAccountId = $('#dr_bank_id option:selected').data('account');
        if (!bankAccountId) {
            Swal.fire('Error', 'Selected bank has no linked account. Please configure the bank first.', 'error');
            return;
        }

        const payload = {
            _token:      $('meta[name="csrf-token"]').attr('content'),
            company_id:  companyId,
            date:        date,
            reference:   $('#dr_reference').val() || null,
            source:      'manual',
            description: $('#dr_description').val() || null,
            items: [
                { account_id: accountId,     debit: amount, credit: 0, note: 'Debit Entry' },
                { account_id: bankAccountId, debit: 0, credit: amount, note: 'Bank Credit' },
            ]
        };

        submitJournal(payload, '#dr_submitBtn', '#journalModalV22', resetDebitForm);
    });

    // ── Shared AJAX submit ───────────────────────────────
    function submitJournal(payload, btnSelector, modalSelector, resetFn) {
        const $btn = $(btnSelector);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving…');

        $.ajax({
            url:  storeUrl,
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': payload._token },
            success: function (res) {
                if (res.success) {
                    $(modalSelector).removeClass('show'); $('body').css('overflow', '');
                    resetFn();
                    Swal.fire({ icon: 'success', title: 'Saved!', text: res.message, timer: 2000, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message ?? 'Something went wrong.';
                Swal.fire('Error', msg, 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Entry');
            }
        });
    }

    // ── View Journal ─────────────────────────────────────
    const viewBase = '{{ rtrim(route("role.journals.index", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]), "/") }}';

    $(document).on('click', '.btn-view-journal', function () {
        const id = $(this).data('id');
        $('#viewModalBody').html('<div style="text-align:center;padding:30px;color:#94a3b8;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
        $('#viewModal').addClass('show'); $('body').css('overflow', 'hidden');

        $.get(viewBase + '/' + id + '/edit', function (data) {
            window.currentJournalForCorrection = data;
            let rows = '';
            let totalD = 0, totalC = 0;
            data.items.forEach(item => {
                totalD += parseFloat(item.debit);
                totalC += parseFloat(item.credit);
                const amount = parseFloat(item.debit) > 0 ? parseFloat(item.debit) : parseFloat(item.credit);
                const side = parseFloat(item.debit) > 0 ? 'Debit' : 'Credit';
                const correctionAction = canReclassifyJournal && item.can_reclassify
                    ? `<button type="button" class="btn-reclassify-line" data-journal="${data.id}" data-item="${item.id}" data-account="${item.account_id}" data-company="${data.company_id}" data-date="${String(data.date).substring(0,10)}" data-side="${side}" data-amount="${amount}" style="margin-left:7px;border:1px solid #c7d2fe;border-radius:5px;background:#eef2ff;color:#4338ca;padding:3px 7px;font-size:10px;font-weight:700;cursor:pointer" title="Move this line to another account head"><i class="fas fa-right-left"></i> Reclassify</button>`
                    : (canReclassifyJournal && !item.can_reclassify ? `<span style="margin-left:7px;color:#94a3b8;font-size:10px">Already corrected / reversed</span>` : '');
                rows += `<tr>
                    <td style="padding:6px 10px;">${item.account ? '['+item.account.code+'] '+item.account.name : '—'}${correctionAction}</td>
                    <td style="padding:6px 10px;text-align:right;color:#1d4ed8;">${parseFloat(item.debit) > 0 ? parseFloat(item.debit).toLocaleString('en-BD',{minimumFractionDigits:2}) : '—'}</td>
                    <td style="padding:6px 10px;text-align:right;color:#15803d;">${parseFloat(item.credit) > 0 ? parseFloat(item.credit).toLocaleString('en-BD',{minimumFractionDigits:2}) : '—'}</td>
                    <td style="padding:6px 10px;color:#6b7280;">${item.note ?? ''}</td>
                </tr>`;
            });
            $('#viewModalBody').html(`
                <div class="journal-detail-meta">
                    <div><strong>Date:</strong> ${new Intl.DateTimeFormat('en-GB', {day:'2-digit', month:'short', year:'numeric', timeZone:'Asia/Dhaka'}).format(new Date(data.date))}</div>
                    <div><strong>Reference:</strong> ${data.reference ?? '—'}</div>
                    <div><strong>Source:</strong> ${String(data.source).replaceAll('_',' ')}</div>
                    <div><strong>Description:</strong> ${data.description ?? '—'}</div>
                </div>
                <div class="journal-detail-table-wrap"><table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead><tr style="background:#f1f5f9;">
                        <th style="padding:6px 10px;text-align:left;">Account</th>
                        <th style="padding:6px 10px;text-align:right;">Debit</th>
                        <th style="padding:6px 10px;text-align:right;">Credit</th>
                        <th style="padding:6px 10px;text-align:left;">Note</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                    <tfoot><tr style="background:#f8fafc;font-weight:600;">
                        <td style="padding:6px 10px;">Total</td>
                        <td style="padding:6px 10px;text-align:right;color:#1d4ed8;">${totalD.toLocaleString('en-BD',{minimumFractionDigits:2})}</td>
                        <td style="padding:6px 10px;text-align:right;color:#15803d;">${totalC.toLocaleString('en-BD',{minimumFractionDigits:2})}</td>
                        <td></td>
                    </tr></tfoot>
                </table></div><div class="journal-detail-actions"></div>`);
            if (data.items.some(item => item.party_type && item.party_id)) {
                $('#viewModalBody .journal-detail-actions').append($('<a>', {href:`${viewBase}/${data.id}/party-voucher`, target:'_blank', rel:'noopener', text:'Print Party Voucher'}).css({display:'inline-block', color:'#4338ca', border:'1px solid #c7d2fe', borderRadius:6}));
            }
            if (canReclassifyJournal && data.can_reverse_replace) {
                $('#viewModalBody .journal-detail-actions').append('<button type="button" id="reverseReplaceJournal" style="border:0;border-radius:6px;background:#4338ca;color:white;font-weight:600"><i class="fas fa-pen"></i> Edit Journal / Reverse & Replace</button>');
            }
        });
    });

    $(document).on('click', '.btn-correct-journal', async function () {
        try {
            const journal = await $.get(`${viewBase}/${this.dataset.id}/edit`);
            if (!journal.can_reverse_replace) {
                Swal.fire('Journal already reversed', 'Open its replacement journal to make another correction.', 'info');
                return;
            }
            openFullJournalEditor(journal);
        } catch (xhr) {
            Swal.fire('Unable to open journal', xhr.responseJSON?.message || 'Please retry.', 'error');
        }
    });

    $(document).on('click', '#reverseReplaceJournal', function () {
        $('#viewModal').removeClass('show');
        $('body').css('overflow', '');
        openFullJournalEditor(window.currentJournalForCorrection);
    });

    async function openFullJournalEditor(journal) {
        const form = document.createElement('div');
        const notice = document.createElement('p');
        notice.className = 'je-notice';
        notice.textContent = 'Saving reverses the entire original journal and its earlier head adjustments, then posts your edited journal. Review both debit and credit totals.';
        form.append(notice);
        const date = document.createElement('input');
        date.type = 'date'; date.value = @json(now()->timezone('Asia/Dhaka')->format('Y-m-d')); date.min = journal.date.substring(0,10);
        const dateLabel = document.createElement('label'); dateLabel.textContent = 'Correction Date'; form.append(dateLabel);
        date.style.maxWidth = '240px';
        form.append(date);
        const heading = document.createElement('div');
        heading.className = 'je-grid je-heading';
        ['Account Head', 'Debit (BDT)', 'Credit (BDT)', 'Note'].forEach(label => {
            const cell = document.createElement('span'); cell.textContent = label; heading.append(cell);
        });
        form.append(heading);
        const fields = [];
        journal.items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'je-grid je-row';
            const account = document.createElement('select');
            accountsData.filter(a => !a.company_id || String(a.company_id) === String(journal.company_id))
                .forEach(a => account.add(new Option(a.text, a.id)));
            account.value = item.account_id;
            const debit = document.createElement('input'); debit.type = 'number'; debit.min = 0; debit.step = '.01'; debit.value = item.debit; debit.setAttribute('aria-label','Debit');
            const credit = document.createElement('input'); credit.type = 'number'; credit.min = 0; credit.step = '.01'; credit.value = item.credit; credit.setAttribute('aria-label','Credit');
            const note = document.createElement('input'); note.value = item.note || ''; note.placeholder = 'Note';
            row.append(account, debit, credit, note); form.append(row);
            fields.push({id:item.id, account, debit, credit, note});
        });
        const totals = document.createElement('p'); totals.className = 'je-totals';
        const updateTotals = () => {
            const debit = fields.reduce((sum, f) => sum + Number(f.debit.value), 0);
            const credit = fields.reduce((sum, f) => sum + Number(f.credit.value), 0);
            totals.textContent = `Debit: ${debit.toFixed(2)} | Credit: ${credit.toFixed(2)} | Difference: ${(debit-credit).toFixed(2)}`;
            totals.style.color = Math.abs(debit-credit) < .01 ? '#15803d' : '#dc2626';
        };
        fields.forEach(f => { f.debit.addEventListener('input', updateTotals); f.credit.addEventListener('input', updateTotals); });
        updateTotals(); form.append(totals);
        const reasonLabel = document.createElement('label'); reasonLabel.textContent = 'Correction Reason *'; form.append(reasonLabel);
        const reason = document.createElement('textarea'); reason.placeholder = 'Explain the correction (at least 5 characters)'; reason.rows = 3; reason.maxLength = 500; form.append(reason);
        const result = await Swal.fire({title:'Edit Journal', html:form, width:1000, customClass:{popup:'journal-editor-popup'}, showCloseButton:true, showCancelButton:true,
            confirmButtonText:'Reverse & Post Replacement', showLoaderOnConfirm:true, allowOutsideClick:() => !Swal.isLoading(),
            preConfirm:async () => {
                const heads = {}, lines = {}; let debit = 0, credit = 0;
                fields.forEach(f => { heads[f.id] = f.account.value; lines[f.id] = {debit:f.debit.value, credit:f.credit.value, note:f.note.value}; debit += Number(f.debit.value); credit += Number(f.credit.value); });
                if (!date.value || reason.value.trim().length < 5 || Math.abs(debit-credit) >= .01 || debit <= 0) {
                    Swal.showValidationMessage('Enter a date, correction reason, and equal positive debit/credit totals.'); return false;
                }
                try { return await $.ajax({url:`${viewBase}/${journal.id}/reverse-replace`, method:'POST',
                    data:{_token:$('meta[name="csrf-token"]').attr('content'), date:date.value, reason:reason.value.trim(), heads, lines}}); }
                catch (xhr) { Swal.showValidationMessage(xhr.responseJSON?.message || 'Unable to post replacement.'); return false; }
            }});
        if (result.isConfirmed) await Swal.fire('Journal replaced', result.value.message, 'success').then(() => location.reload());
    }

    $('#closeViewModal, #closeViewModalBtn').on('click', function () {
        $('#viewModal').removeClass('show'); $('body').css('overflow', '');
    });

    $(document).on('click', '.btn-reclassify-line', function () {
        const button = this;
        const current = accountsData.find(account => String(account.id) === String(button.dataset.account));
        reclassifyContext = { journal: button.dataset.journal, item: button.dataset.item, company: button.dataset.company };
        $('#reclassifyCurrent').text(`${current?.text || 'Current account'} · ${button.dataset.side} ${Number(button.dataset.amount).toLocaleString('en-BD', {minimumFractionDigits:2})}`);
        $('#reclassifyAccount').empty().append('<option value="">— Select new account head —</option>');
        accountsData.filter(account => String(account.id) !== String(button.dataset.account)
            && (!account.company_id || String(account.company_id) === String(button.dataset.company)))
            .forEach(account => $('#reclassifyAccount').append(new Option(account.text, account.id)));
        $('#reclassifyDate').attr('min', button.dataset.date).val(button.dataset.date);
        $('#reclassifyReason').val('');
        $('#viewModal').removeClass('show');
        $('#reclassifyModal').addClass('show');
        $('#reclassifyAccount').select2({ dropdownParent: $('#reclassifyModal'), width: '100%' });
    });

    $('#closeReclassifyModal, #cancelReclassify').on('click', function () {
        $('#reclassifyModal').removeClass('show');
        $('body').css('overflow', '');
        reclassifyContext = null;
    });

    $('#reclassifyForm').on('submit', function (event) {
        event.preventDefault();
        if (!reclassifyContext || !$('#reclassifyAccount').val() || !$('#reclassifyDate').val() || $('#reclassifyReason').val().trim().length < 5) {
            Swal.fire('Missing information', 'Choose the new head, date and enter a clear correction reason.', 'warning');
            return;
        }
        const $button = $('#saveReclassify').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>Posting…');
        $.ajax({
            url: `${viewBase}/${reclassifyContext.journal}/items/${reclassifyContext.item}/reclassify`,
            method: 'POST',
            data: { _token: $('meta[name="csrf-token"]').attr('content'), account_id: $('#reclassifyAccount').val(), date: $('#reclassifyDate').val(), reason: $('#reclassifyReason').val().trim() },
            success: response => Swal.fire('Head reclassified', response.message, 'success').then(() => location.reload()),
            error: xhr => Swal.fire('Unable to reclassify', xhr.responseJSON?.message || 'The adjustment could not be posted.', 'error'),
            complete: () => $button.prop('disabled', false).html('<i class="fas fa-check"></i>Create Adjustment'),
        });
    });

    // ── OPENING RECEIVABLE Modal ─────────────────────────

    const partiesData = JSON.parse(document.getElementById('partiesData').textContent);

    const partyTypeLabels = {
        customer: 'Customer',
        supplier: 'Supplier',
        agent:    'Agent',
        vendor:   'Vendor',
    };

    $('#openCreateModalRcv').on('click', function () {
        resetReceivableForm();
        $('#journalModalRcv').addClass('show'); $('body').css('overflow', 'hidden');
    });
    $('#closeModalRcv, #cancelModalRcv').on('click', function () {
        $('#journalModalRcv').removeClass('show'); $('body').css('overflow', '');
    });

    $('#rcv_ar_account_id, #rcv_equity_account_id').select2({ dropdownParent: $('#journalModalRcv') });

    let rcvRowCount = 0;

    function buildPartyOptions(type) {
        const list = partiesData[type] || [];
        if (!list.length) return '<option value="">— No ' + (partyTypeLabels[type] || type) + ' found —</option>';
        return '<option value="">— Select ' + (partyTypeLabels[type] || type) + ' —</option>' +
            list.map(p => `<option value="${p.id}" data-sub="${p.sub}">${p.name}${p.sub ? ' (' + p.sub + ')' : ''}</option>`).join('');
    }

    function addRcvRow() {
        rcvRowCount++;
        const rowId = 'rcv_row_' + rcvRowCount;
        const partySelectId = 'rcv_party_' + rcvRowCount;

        const typeOptions = Object.keys(partyTypeLabels)
            .map(k => `<option value="${k}">${partyTypeLabels[k]}</option>`)
            .join('');

        const row = `<tr id="${rowId}">
            <td>
                <select class="item-input rcv-party-type">
                    <option value="">— Type —</option>
                    ${typeOptions}
                </select>
            </td>
            <td>
                <select class="item-input rcv-party-select" id="${partySelectId}" disabled>
                    <option value="">— Select Type First —</option>
                </select>
            </td>
            <td><input type="text" class="item-input rcv-invoice" placeholder="INV-XXXX"></td>
            <td><input type="number" class="item-input rcv-amount" min="0.01" step="0.01" placeholder="0.00"></td>
            <td><input type="date" class="item-input rcv-due-date" style="background:#fffbeb;border-color:#fcd34d;" title="Payment due date (optional — creates schedule automatically)"></td>
            <td><input type="text" class="item-input rcv-note" placeholder="Note"></td>
            <td>
                <button type="button" class="btn-remove-row rcv-del-btn" data-row="${rowId}" title="Remove row">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>`;

        $('#rcv_tbody').append(row);

        // Init select2 on the party select for this new row
        $('#' + partySelectId).select2({
            dropdownParent: $('#journalModalRcv'),
            width: '100%',
        });
    }

    // When Party Type changes → populate the Party select
    $(document).on('change', '#rcv_tbody .rcv-party-type', function () {
        const type        = $(this).val();
        const $row        = $(this).closest('tr');
        const $partySelect = $row.find('.rcv-party-select');

        $partySelect.empty().append(buildPartyOptions(type));

        if (type) {
            $partySelect.prop('disabled', false);
        } else {
            $partySelect.prop('disabled', true).append('<option value="">— Select Type First —</option>');
        }
        $partySelect.trigger('change.select2');
        updateRcvPreview();
    });

    $('#rcv_addRow').on('click', addRcvRow);

    $(document).on('click', '.rcv-del-btn', function () {
        if ($('#rcv_tbody tr').length > 1) {
            $('#' + $(this).data('row')).remove();
            updateRcvPreview();
        }
    });

    $(document).on('input', '#rcv_tbody .rcv-amount', updateRcvPreview);
    $(document).on('change', '#rcv_tbody .rcv-party-select', updateRcvPreview);
    $('#rcv_ar_account_id, #rcv_equity_account_id').on('change', updateRcvPreview);

    function getPartyName($row) {
        const $sel = $row.find('.rcv-party-select');
        return $sel.find('option:selected').text().replace(/\s*\(.*\)$/, '').trim();
    }

    function updateRcvPreview() {
        const arId     = $('#rcv_ar_account_id').val();
        const equityId = $('#rcv_equity_account_id').val();
        let total = 0;
        let rows  = '';

        $('#rcv_tbody tr').each(function () {
            const amount    = parseFloat($(this).find('.rcv-amount').val()) || 0;
            const partyName = getPartyName($(this));
            const partyType = $(this).find('.rcv-party-type').val();
            if (amount > 0 && partyName) {
                total += amount;
                const arLabel = (arId ? getAccountText(arId) : 'Accounts Receivable') +
                                ' — ' + partyName +
                                (partyType && partyType !== 'customer' ? ' (' + partyTypeLabels[partyType] + ')' : '');
                rows += `<tr>
                    <td style="padding:4px 10px;">${arLabel}</td>
                    <td style="padding:4px 10px;text-align:right;color:#1d4ed8;">${formatAmt(amount)}</td>
                    <td style="padding:4px 10px;text-align:right;">—</td>
                </tr>`;
            }
        });

        if (total > 0 && equityId) {
            rows += `<tr>
                <td style="padding:4px 10px;">${getAccountText(equityId)}</td>
                <td style="padding:4px 10px;text-align:right;">—</td>
                <td style="padding:4px 10px;text-align:right;color:#15803d;">${formatAmt(total)}</td>
            </tr>`;
            $('#rcv_preview_tbody').html(rows);
            $('#rcv_preview_total_dr').text(formatAmt(total));
            $('#rcv_preview_total_cr').text(formatAmt(total));
            $('#rcv_preview').show();
        } else {
            $('#rcv_preview').hide();
        }
    }

    function resetReceivableForm() {
        $('#rcv_company_id').val('');
        $('#rcv_date').val('');
        $('#rcv_reference').val('');
        $('#rcv_description').val('');
        $('#rcv_ar_account_id').val('').trigger('change');
        $('#rcv_equity_account_id').val('').trigger('change');
        $('#rcv_tbody').empty();
        rcvRowCount = 0;
        addRcvRow();
        $('#rcv_preview').hide();
        $('#rcv_err_company, #rcv_err_date, #rcv_err_ar, #rcv_err_equity, #rcv_err_rows').text('').hide();
    }

    $('#receivableJournalForm').on('submit', function (e) {
        e.preventDefault();

        const companyId       = $('#rcv_company_id').val();
        const date            = $('#rcv_date').val();
        const arAccountId     = $('#rcv_ar_account_id').val();
        const equityAccountId = $('#rcv_equity_account_id').val();
        let valid = true;

        if (!companyId)       { $('#rcv_err_company').text('Company is required.').show(); valid = false; }
        else                  { $('#rcv_err_company').text('').hide(); }
        if (!date)            { $('#rcv_err_date').text('Date is required.').show(); valid = false; }
        else                  { $('#rcv_err_date').text('').hide(); }
        if (!arAccountId)     { $('#rcv_err_ar').text('Receivable account is required.').show(); valid = false; }
        else                  { $('#rcv_err_ar').text('').hide(); }
        if (!equityAccountId) { $('#rcv_err_equity').text('Opening Balance Equity account is required.').show(); valid = false; }
        else                  { $('#rcv_err_equity').text('').hide(); }

        const items  = [];
        let total    = 0;
        let rowErr   = false;
        let rowErrMsg = '';

        $('#rcv_tbody tr').each(function () {
            const partyType = $(this).find('.rcv-party-type').val();
            const partyId   = $(this).find('.rcv-party-select').val();
            const invoice   = $(this).find('.rcv-invoice').val().trim();
            const amount    = parseFloat($(this).find('.rcv-amount').val());
            const dueDate   = $(this).find('.rcv-due-date').val() || null;
            const noteRaw   = $(this).find('.rcv-note').val().trim();

            if (!partyType)      { rowErr = true; rowErrMsg = 'Select a party type for each row.'; return; }
            if (!partyId)        { rowErr = true; rowErrMsg = 'Select a party for each row.'; return; }
            if (!(amount > 0))   { rowErr = true; rowErrMsg = 'Enter a valid amount for each row.'; return; }

            total += amount;
            const partyName = $(this).find('.rcv-party-select option:selected').text().replace(/\s*\(.*\)$/, '').trim();
            const noteParts = [invoice ? 'Inv: ' + invoice : null, noteRaw || null].filter(Boolean);
            items.push({
                account_id: parseInt(arAccountId),
                debit:      amount,
                credit:     0,
                note:       noteParts.join(' | ') || null,
                party_type: partyType,
                party_id:   parseInt(partyId),
                party_name: partyName,
                due_date:   dueDate,
                reference:  invoice || null,
            });
        });

        if (rowErr || items.length === 0) {
            $('#rcv_err_rows').text(rowErrMsg || 'At least one complete row is required.').show();
            valid = false;
        } else {
            $('#rcv_err_rows').text('').hide();
            items.push({
                account_id: parseInt(equityAccountId),
                debit:      0,
                credit:     total,
                note:       'Opening Balance Equity',
                party_type: null,
                party_id:   null,
            });
        }

        if (!valid) return;

        const payload = {
            _token:      $('meta[name="csrf-token"]').attr('content'),
            company_id:  companyId,
            date:        date,
            reference:   $('#rcv_reference').val() || null,
            source:      'opening_receivable',
            description: $('#rcv_description').val() || null,
            items:       items,
        };

        $('#rcv_submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving…');
        $.ajax({
            url:         storeUrl,
            type:        'POST',
            data:        JSON.stringify(payload),
            contentType: 'application/json',
            headers:     { 'X-CSRF-TOKEN': payload._token },
            success: function (res) {
                if (res.success) {
                    $('#journalModalRcv').removeClass('show'); $('body').css('overflow', '');
                    resetReceivableForm();
                    Swal.fire({ icon: 'success', title: 'Saved!', text: res.message, timer: 2000, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message ?? 'Something went wrong.', 'error');
            },
            complete: function () {
                $('#rcv_submitBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Receivable Entry');
            }
        });
    });

    // Initialise with one blank row
    addRcvRow();

    // ── OPENING PAYABLE Modal ─────────────────────────────
    // Flow: DR — Opening Balance Equity (1 line, total) | CR — Accounts Payable (1 line per supplier)

    $('#openCreateModalPay').on('click', function () {
        resetPayableForm();
        $('#journalModalPay').addClass('show'); $('body').css('overflow', 'hidden');
    });
    $('#closeModalPay, #cancelModalPay').on('click', function () {
        $('#journalModalPay').removeClass('show'); $('body').css('overflow', '');
    });

    $('#pay_ap_account_id, #pay_equity_account_id').select2({ dropdownParent: $('#journalModalPay') });

    let payRowCount = 0;

    function addPayRow() {
        payRowCount++;
        const rowId        = 'pay_row_' + payRowCount;
        const partySelId   = 'pay_party_' + payRowCount;

        const typeOptions = Object.keys(partyTypeLabels)
            .map(k => `<option value="${k}"${k === 'supplier' ? ' selected' : ''}>${partyTypeLabels[k]}</option>`)
            .join('');

        const row = `<tr id="${rowId}">
            <td>
                <select class="item-input pay-party-type">
                    <option value="">— Type —</option>
                    ${typeOptions}
                </select>
            </td>
            <td>
                <select class="item-input pay-party-select" id="${partySelId}">
                    <option value="">— Select Supplier —</option>
                </select>
            </td>
            <td><input type="text" class="item-input pay-bill" placeholder="BILL-XXXX"></td>
            <td><input type="number" class="item-input pay-amount" min="0.01" step="0.01" placeholder="0.00"></td>
            <td><input type="date" class="item-input pay-due-date" style="background:#fffbeb;border-color:#fcd34d;" title="Payment due date (optional — creates schedule automatically)"></td>
            <td><input type="text" class="item-input pay-note" placeholder="Note"></td>
            <td>
                <button type="button" class="btn-remove-row pay-del-btn" data-row="${rowId}" title="Remove row">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>`;

        $('#pay_tbody').append(row);

        const $sel = $('#' + partySelId);
        $sel.select2({ dropdownParent: $('#journalModalPay'), width: '100%' });

        // Default to supplier and populate immediately
        $sel.empty().append(buildPartyOptions('supplier'));
        $sel.prop('disabled', false).trigger('change.select2');
    }

    $(document).on('change', '#pay_tbody .pay-party-type', function () {
        const type         = $(this).val();
        const $row         = $(this).closest('tr');
        const $partySelect = $row.find('.pay-party-select');

        $partySelect.empty().append(buildPartyOptions(type));

        if (type) {
            $partySelect.prop('disabled', false);
        } else {
            $partySelect.prop('disabled', true).append('<option value="">— Select Type First —</option>');
        }
        $partySelect.trigger('change.select2');
        updatePayPreview();
    });

    $('#pay_addRow').on('click', addPayRow);

    $(document).on('click', '.pay-del-btn', function () {
        if ($('#pay_tbody tr').length > 1) {
            $('#' + $(this).data('row')).remove();
            updatePayPreview();
        }
    });

    $(document).on('input', '#pay_tbody .pay-amount', updatePayPreview);
    $(document).on('change', '#pay_tbody .pay-party-select', updatePayPreview);
    $('#pay_ap_account_id, #pay_equity_account_id').on('change', updatePayPreview);

    function updatePayPreview() {
        const apId     = $('#pay_ap_account_id').val();
        const equityId = $('#pay_equity_account_id').val();
        let total = 0;
        let rows  = '';

        $('#pay_tbody tr').each(function () {
            const amount    = parseFloat($(this).find('.pay-amount').val()) || 0;
            const partyName = $(this).find('.pay-party-select option:selected').text().replace(/\s*\(.*\)$/, '').trim();
            const partyType = $(this).find('.pay-party-type').val();
            if (amount > 0 && partyName) {
                total += amount;
                const apLabel = (apId ? getAccountText(apId) : 'Accounts Payable') +
                                ' — ' + partyName +
                                (partyType && partyType !== 'supplier' ? ' (' + partyTypeLabels[partyType] + ')' : '');
                rows += `<tr>
                    <td style="padding:4px 10px;">${apLabel}</td>
                    <td style="padding:4px 10px;text-align:right;">—</td>
                    <td style="padding:4px 10px;text-align:right;color:#15803d;">${formatAmt(amount)}</td>
                </tr>`;
            }
        });

        if (total > 0 && equityId) {
            const equityRow = `<tr>
                <td style="padding:4px 10px;">${getAccountText(equityId)}</td>
                <td style="padding:4px 10px;text-align:right;color:#1d4ed8;">${formatAmt(total)}</td>
                <td style="padding:4px 10px;text-align:right;">—</td>
            </tr>`;
            $('#pay_preview_tbody').html(equityRow + rows);
            $('#pay_preview_total_dr').text(formatAmt(total));
            $('#pay_preview_total_cr').text(formatAmt(total));
            $('#pay_preview').show();
        } else {
            $('#pay_preview').hide();
        }
    }

    function resetPayableForm() {
        $('#pay_company_id').val('');
        $('#pay_date').val('');
        $('#pay_reference').val('');
        $('#pay_description').val('');
        $('#pay_ap_account_id').val('').trigger('change');
        $('#pay_equity_account_id').val('').trigger('change');
        $('#pay_tbody').empty();
        payRowCount = 0;
        addPayRow();
        $('#pay_preview').hide();
        $('#pay_err_company, #pay_err_date, #pay_err_ap, #pay_err_equity, #pay_err_rows').text('').hide();
    }

    $('#payableJournalForm').on('submit', function (e) {
        e.preventDefault();

        const companyId       = $('#pay_company_id').val();
        const date            = $('#pay_date').val();
        const apAccountId     = $('#pay_ap_account_id').val();
        const equityAccountId = $('#pay_equity_account_id').val();
        let valid = true;

        if (!companyId)       { $('#pay_err_company').text('Company is required.').show(); valid = false; }
        else                  { $('#pay_err_company').text('').hide(); }
        if (!date)            { $('#pay_err_date').text('Date is required.').show(); valid = false; }
        else                  { $('#pay_err_date').text('').hide(); }
        if (!apAccountId)     { $('#pay_err_ap').text('Payable account is required.').show(); valid = false; }
        else                  { $('#pay_err_ap').text('').hide(); }
        if (!equityAccountId) { $('#pay_err_equity').text('Opening Balance Equity account is required.').show(); valid = false; }
        else                  { $('#pay_err_equity').text('').hide(); }

        const items   = [];
        let total     = 0;
        let rowErr    = false;
        let rowErrMsg = '';

        $('#pay_tbody tr').each(function () {
            const partyType = $(this).find('.pay-party-type').val();
            const partyId   = $(this).find('.pay-party-select').val();
            const bill      = $(this).find('.pay-bill').val().trim();
            const amount    = parseFloat($(this).find('.pay-amount').val());
            const dueDate   = $(this).find('.pay-due-date').val() || null;
            const noteRaw   = $(this).find('.pay-note').val().trim();

            if (!partyType)    { rowErr = true; rowErrMsg = 'Select a party type for each row.'; return; }
            if (!partyId)      { rowErr = true; rowErrMsg = 'Select a party for each row.'; return; }
            if (!(amount > 0)) { rowErr = true; rowErrMsg = 'Enter a valid amount for each row.'; return; }

            total += amount;
            const partyName = $(this).find('.pay-party-select option:selected').text().replace(/\s*\(.*\)$/, '').trim();
            const noteParts = [bill ? 'Bill: ' + bill : null, noteRaw || null].filter(Boolean);
            items.push({
                account_id: parseInt(apAccountId),
                debit:      0,
                credit:     amount,
                note:       noteParts.join(' | ') || null,
                party_type: partyType,
                party_id:   parseInt(partyId),
                party_name: partyName,
                due_date:   dueDate,
                reference:  bill || null,
            });
        });

        if (rowErr || items.length === 0) {
            $('#pay_err_rows').text(rowErrMsg || 'At least one complete row is required.').show();
            valid = false;
        } else {
            $('#pay_err_rows').text('').hide();
            // DR Opening Balance Equity for the total (prepended so it appears first)
            items.unshift({
                account_id: parseInt(equityAccountId),
                debit:      total,
                credit:     0,
                note:       'Opening Balance Equity',
                party_type: null,
                party_id:   null,
            });
        }

        if (!valid) return;

        const payload = {
            _token:      $('meta[name="csrf-token"]').attr('content'),
            company_id:  companyId,
            date:        date,
            reference:   $('#pay_reference').val() || null,
            source:      'opening_payable',
            description: $('#pay_description').val() || null,
            items:       items,
        };

        $('#pay_submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving…');
        $.ajax({
            url:         storeUrl,
            type:        'POST',
            data:        JSON.stringify(payload),
            contentType: 'application/json',
            headers:     { 'X-CSRF-TOKEN': payload._token },
            success: function (res) {
                if (res.success) {
                    $('#journalModalPay').removeClass('show'); $('body').css('overflow', '');
                    resetPayableForm();
                    Swal.fire({ icon: 'success', title: 'Saved!', text: res.message, timer: 2000, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message ?? 'Something went wrong.', 'error');
            },
            complete: function () {
                $('#pay_submitBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Payable Entry');
            }
        });
    });

    // Initialise payable modal with one blank row
    addPayRow();

    // ── OPENING ASSET Modal ───────────────────────────────
    // Flow: DR — Asset Account (per row) | CR — Opening Balance Equity (total)

    const assetAccountsData = JSON.parse(document.getElementById('assetAccountsData').textContent);

    function buildAssetOptions() {
        if (!assetAccountsData.length) return '<option value="">— No Asset Accounts Found —</option>';
        return '<option value="">— Select Account —</option>' +
            assetAccountsData.map(a => `<option value="${a.id}">${a.text}</option>`).join('');
    }

    $('#openCreateModalAst').on('click', function () {
        resetAssetForm();
        $('#journalModalAst').addClass('show'); $('body').css('overflow', 'hidden');
    });
    $('#closeModalAst, #cancelModalAst').on('click', function () {
        $('#journalModalAst').removeClass('show'); $('body').css('overflow', '');
    });

    $('#ast_equity_account_id').select2({ dropdownParent: $('#journalModalAst') });

    let astRowCount = 0;

    function addAstRow() {
        astRowCount++;
        const rowId      = 'ast_row_' + astRowCount;
        const assetSelId = 'ast_asset_' + astRowCount;

        const row = `<tr id="${rowId}">
            <td>
                <select class="item-input ast-asset-account" id="${assetSelId}">
                    ${buildAssetOptions()}
                </select>
            </td>
            <td><input type="text" class="item-input ast-desc" placeholder="Item name"></td>
            <td><input type="number" class="item-input ast-qty" min="1" step="1" value="1" placeholder="1" style="text-align:center;"></td>
            <td><input type="number" class="item-input ast-value" min="0.01" step="0.01" placeholder="0.00"></td>
            <td><input type="date" class="item-input ast-pdate"></td>
            <td>
                <button type="button" class="btn-remove-row ast-del-btn" data-row="${rowId}" title="Remove row">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>`;

        $('#ast_tbody').append(row);

        $('#' + assetSelId).select2({
            dropdownParent: $('#journalModalAst'),
            width: '100%',
        }).on('change', updateAstPreview);
    }

    $('#ast_addRow').on('click', addAstRow);

    $(document).on('click', '.ast-del-btn', function () {
        if ($('#ast_tbody tr').length > 1) {
            $('#' + $(this).data('row')).remove();
            updateAstPreview();
        }
    });

    $(document).on('input', '#ast_tbody .ast-value, #ast_tbody .ast-qty', updateAstPreview);
    $(document).on('input', '#ast_tbody .ast-desc', updateAstPreview);
    $('#ast_equity_account_id').on('change', updateAstPreview);

    function getAstAccountText(id) {
        const a = assetAccountsData.find(x => x.id == id);
        return a ? a.text : '—';
    }

    function updateAstPreview() {
        const equityId = $('#ast_equity_account_id').val();
        let total = 0;
        let rows  = '';

        $('#ast_tbody tr').each(function () {
            const assetId = $(this).find('.ast-asset-account').val();
            const desc    = $(this).find('.ast-desc').val().trim();
            const qty     = parseInt($(this).find('.ast-qty').val()) || 1;
            const value   = parseFloat($(this).find('.ast-value').val()) || 0;

            if (assetId && value > 0) {
                total += value;
                const label = getAstAccountText(assetId) +
                              (desc ? ' — ' + desc : '') +
                              (qty > 1 ? ' ×' + qty : '');
                rows += `<tr>
                    <td style="padding:4px 10px;">${label}</td>
                    <td style="padding:4px 10px;text-align:right;color:#1d4ed8;">${formatAmt(value)}</td>
                    <td style="padding:4px 10px;text-align:right;">—</td>
                </tr>`;
            }
        });

        if (total > 0 && equityId) {
            rows += `<tr>
                <td style="padding:4px 10px;">${getAccountText(equityId)}</td>
                <td style="padding:4px 10px;text-align:right;">—</td>
                <td style="padding:4px 10px;text-align:right;color:#15803d;">${formatAmt(total)}</td>
            </tr>`;
            $('#ast_preview_tbody').html(rows);
            $('#ast_preview_total_dr').text(formatAmt(total));
            $('#ast_preview_total_cr').text(formatAmt(total));
            $('#ast_preview').show();
        } else {
            $('#ast_preview').hide();
        }
    }

    function resetAssetForm() {
        $('#ast_company_id').val('');
        $('#ast_date').val('');
        $('#ast_reference').val('');
        $('#ast_description').val('');
        $('#ast_equity_account_id').val('').trigger('change');
        $('#ast_tbody').empty();
        astRowCount = 0;
        addAstRow();
        $('#ast_preview').hide();
        $('#ast_err_company, #ast_err_date, #ast_err_equity, #ast_err_rows').text('').hide();
    }

    $('#assetJournalForm').on('submit', function (e) {
        e.preventDefault();

        const companyId       = $('#ast_company_id').val();
        const date            = $('#ast_date').val();
        const equityAccountId = $('#ast_equity_account_id').val();
        let valid = true;

        if (!companyId)       { $('#ast_err_company').text('Company is required.').show(); valid = false; }
        else                  { $('#ast_err_company').text('').hide(); }
        if (!date)            { $('#ast_err_date').text('Date is required.').show(); valid = false; }
        else                  { $('#ast_err_date').text('').hide(); }
        if (!equityAccountId) { $('#ast_err_equity').text('Opening Balance Equity account is required.').show(); valid = false; }
        else                  { $('#ast_err_equity').text('').hide(); }

        const items   = [];
        let total     = 0;
        let rowErr    = false;
        let rowErrMsg = '';

        $('#ast_tbody tr').each(function () {
            const assetAccountId = $(this).find('.ast-asset-account').val();
            const desc           = $(this).find('.ast-desc').val().trim();
            const qty            = parseInt($(this).find('.ast-qty').val()) || 1;
            const value          = parseFloat($(this).find('.ast-value').val());
            const pdate          = $(this).find('.ast-pdate').val();

            if (!assetAccountId)   { rowErr = true; rowErrMsg = 'Select an asset account for each row.'; return; }
            if (!desc)             { rowErr = true; rowErrMsg = 'Enter an item description for each row.'; return; }
            if (!(value > 0))      { rowErr = true; rowErrMsg = 'Enter a valid value for each row.'; return; }

            total += value;
            const noteParts = [desc + (qty > 1 ? ' ×' + qty : ''), pdate ? 'Purchased: ' + pdate : null].filter(Boolean);
            items.push({
                account_id: parseInt(assetAccountId),
                debit:      value,
                credit:     0,
                note:       noteParts.join(' | ') || null,
                party_type: null,
                party_id:   null,
            });
        });

        if (rowErr || items.length === 0) {
            $('#ast_err_rows').text(rowErrMsg || 'At least one complete asset row is required.').show();
            valid = false;
        } else {
            $('#ast_err_rows').text('').hide();
            items.push({
                account_id: parseInt(equityAccountId),
                debit:      0,
                credit:     total,
                note:       'Opening Balance Equity',
                party_type: null,
                party_id:   null,
            });
        }

        if (!valid) return;

        const payload = {
            _token:      $('meta[name="csrf-token"]').attr('content'),
            company_id:  companyId,
            date:        date,
            reference:   $('#ast_reference').val() || null,
            source:      'opening_asset',
            description: $('#ast_description').val() || null,
            items:       items,
        };

        $('#ast_submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving…');
        $.ajax({
            url:         storeUrl,
            type:        'POST',
            data:        JSON.stringify(payload),
            contentType: 'application/json',
            headers:     { 'X-CSRF-TOKEN': payload._token },
            success: function (res) {
                if (res.success) {
                    $('#journalModalAst').removeClass('show'); $('body').css('overflow', '');
                    resetAssetForm();
                    Swal.fire({ icon: 'success', title: 'Saved!', text: res.message, timer: 2000, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message ?? 'Something went wrong.', 'error');
            },
            complete: function () {
                $('#ast_submitBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Asset Entry');
            }
        });
    });

    // Initialise asset modal with one blank row
    addAstRow();

});
</script>

@endsection
