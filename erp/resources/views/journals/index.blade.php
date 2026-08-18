@extends('layout.app')

@section('meta-information')
    <title>Manage Journal Entries</title>
@endsection

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('journals.css')
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
                <button id="openCreateModalV2" class="btn-hdr btn-hdr-credit">
                    <i class="fas fa-arrow-down"></i>Credit Journal
                </button>
                <button id="openCreateModalV22" class="btn-hdr btn-hdr-debit">
                    <i class="fas fa-arrow-up"></i>Debit Journal
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
            <form action="" method="GET" id="filterForm">
                <div class="filter-container">
                    <div class="filter-header {{ request()->hasAny(['date_from','date_to','reference','source']) ? 'active' : '' }}" id="filterToggleHeader">
                        <h3>
                            <i class="fas fa-filter mr-2"></i>Filter Options
                            @if(request()->hasAny(['date_from','date_to','reference','source']))
                                <span class="filter-badge">Active</span>
                            @endif
                        </h3>
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
            @if(session('success'))
                <div class="flash-alert flash-success">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @endif

            {{-- ── Table ── --}}
            <div class="table-wrapper" id="printArea">
                <span class="print-meta">
                    Printed on: {{ now()->format('d M Y, h:i A') }}
                    @if(request()->hasAny(['date_from','date_to','reference','source']))
                        &nbsp;|&nbsp; Filters:
                        @if(request('date_from')) From: {{ request('date_from') }} @endif
                        @if(request('date_to')) To: {{ request('date_to') }} @endif
                        @if(request('reference')) Ref: {{ request('reference') }} @endif
                        @if(request('source')) Source: {{ request('source') }} @endif
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
                    <button type="button" id="printJournalBtn" class="btn-print">
                        <i class="fas fa-print"></i>Print / Export
                    </button>
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
                            <th class="no-print" style="text-align:center">Actions</th>
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
                                <div class="action-cell">
                                    {{-- View --}}
                                    <button class="btn-action btn-view btn-view-journal"
                                            title="View Items"
                                            data-id="{{ $journal->id }}">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    {{-- Print Company Voucher --}}
                                    <a class="btn-action btn-voucher"
                                       title="Company Voucher"
                                       href="{{ route('role.journals.voucher', ['role' => Str::slug(auth()->user()->getRoleNames()->first()), 'journal' => $journal->id]) }}"
                                       target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>

                                    {{-- Print Party Voucher (Customer / Supplier / Others) --}}
                                    @php
                                        $hasParty = $journal->items->contains(fn($i) => $i->party_type && $i->party_id);
                                    @endphp
                                    @if($hasParty)
                                    <a class="btn-action btn-voucher-party"
                                       title="Party Voucher (Customer / Supplier)"
                                       href="{{ route('role.journals.party-voucher', ['role' => Str::slug(auth()->user()->getRoleNames()->first()), 'journal' => $journal->id]) }}"
                                       target="_blank">
                                        <i class="fas fa-user-tag"></i>
                                    </a>
                                    @endif

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
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}" data-account="{{ $bank->account_id }}">{{ $bank->name }}</option>
                            @endforeach
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
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}" data-account="{{ $bank->account_id }}">{{ $bank->name }}</option>
                            @endforeach
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

{{-- Bank data for JS (account_id mapping) --}}
<script id="banksData" type="application/json">
    {!! json_encode($banks->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'account_id' => $b->account_id])) !!}
</script>
{{-- Account data for JS (merged for preview lookup) --}}
<script id="accountsData" type="application/json">
    {!! json_encode($creditAccounts->merge($debitAccounts)->map(fn($a) => ['id' => $a->id, 'text' => '[' . $a->code . '] ' . $a->name . ' (' . ucfirst($a->type) . ')'])) !!}
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
            let rows = '';
            let totalD = 0, totalC = 0;
            data.items.forEach(item => {
                totalD += parseFloat(item.debit);
                totalC += parseFloat(item.credit);
                rows += `<tr>
                    <td style="padding:6px 10px;">${item.account ? '['+item.account.code+'] '+item.account.name : '—'}</td>
                    <td style="padding:6px 10px;text-align:right;color:#1d4ed8;">${parseFloat(item.debit) > 0 ? parseFloat(item.debit).toLocaleString('en-BD',{minimumFractionDigits:2}) : '—'}</td>
                    <td style="padding:6px 10px;text-align:right;color:#15803d;">${parseFloat(item.credit) > 0 ? parseFloat(item.credit).toLocaleString('en-BD',{minimumFractionDigits:2}) : '—'}</td>
                    <td style="padding:6px 10px;color:#6b7280;">${item.note ?? ''}</td>
                </tr>`;
            });
            $('#viewModalBody').html(`
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
                    <div><strong>Date:</strong> ${data.date}</div>
                    <div><strong>Reference:</strong> ${data.reference ?? '—'}</div>
                    <div><strong>Source:</strong> ${data.source}</div>
                    <div><strong>Description:</strong> ${data.description ?? '—'}</div>
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
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
                </table>`);
        });
    });

    $('#closeViewModal, #closeViewModalBtn').on('click', function () {
        $('#viewModal').removeClass('show'); $('body').css('overflow', '');
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