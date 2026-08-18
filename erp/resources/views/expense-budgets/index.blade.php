@extends('layout.app')
@section('meta-information')
    <title>Budget Setup</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    :root {
        --budget-primary: #2d3eb5;
        --budget-primary-dark: #1f2a8a;
        --budget-primary-light: #eef2ff;
        --budget-success: #10b981;
        --budget-warning: #f59e0b;
        --budget-danger: #ef4444;
        --budget-bg: #f5f7fb;
        --budget-card: #ffffff;
        --budget-text: #1f2937;
        --budget-muted: #6b7280;
        --budget-border: #e5e7eb;
    }

    .budget-shell {
        background: radial-gradient(circle at top left, rgba(45,62,181,0.10), transparent 34%),
                    radial-gradient(circle at top right, rgba(16,185,129,0.10), transparent 26%),
                    var(--budget-bg);
        padding: 18px;
    }

    .budget-card {
        background: var(--budget-card);
        border: 1px solid var(--budget-border);
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }

    .budget-header {
        background: linear-gradient(135deg, var(--budget-primary) 0%, var(--budget-primary-dark) 100%);
        color: #fff;
        padding: 18px 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .budget-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .budget-header .subtext {
        color: rgba(255,255,255,0.78);
        font-size: 12px;
        margin-top: 4px;
    }

    .budget-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    .budget-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        border-radius: 10px;
        padding: 9px 14px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.15s ease, background 0.15s ease, color 0.15s ease;
        text-decoration: none;
    }

    .budget-btn:hover { transform: translateY(-1px); text-decoration: none; }
    .budget-btn.add { background: #fff; color: var(--budget-primary); }
    .budget-btn.reset { background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.18); }

    .budget-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        padding: 16px 18px 0;
    }

    .budget-stat {
        background: #fff;
        border: 1px solid var(--budget-border);
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    }

    .budget-stat .label {
        color: var(--budget-muted);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .budget-stat .value {
        color: var(--budget-text);
        font-size: 22px;
        font-weight: 800;
        line-height: 1.1;
    }

    .budget-stat .note {
        color: var(--budget-muted);
        font-size: 12px;
        margin-top: 4px;
    }

    .budget-stat.primary { border-left: 4px solid var(--budget-primary); }
    .budget-stat.success { border-left: 4px solid var(--budget-success); }
    .budget-stat.warning { border-left: 4px solid var(--budget-warning); }
    .budget-stat.danger  { border-left: 4px solid var(--budget-danger); }

    .filter-card {
        margin: 16px 18px 0;
        background: #fff;
        border: 1px solid var(--budget-border);
        border-radius: 14px;
        padding: 14px;
        display: flex;
        gap: 12px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .filter-group { display: flex; flex-direction: column; gap: 6px; }
    .filter-group label {
        font-size: 11px;
        font-weight: 700;
        color: var(--budget-muted);
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .filter-group select,
    .filter-group input {
        min-width: 210px;
        border: 1px solid var(--budget-border);
        border-radius: 10px;
        padding: 9px 12px;
        font-size: 13px;
        color: var(--budget-text);
        background: #fff;
    }

    .filter-btn {
        border: none;
        border-radius: 10px;
        padding: 9px 14px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        height: 40px;
    }
    .filter-btn.apply { background: var(--budget-primary); color: #fff; }
    .filter-btn.reset { background: #f3f4f6; color: var(--budget-text); }

    .budget-table-wrap {
        padding: 16px 18px 18px;
    }

    .budget-table {
        width: 100%;
        border-collapse: collapse;
    }

    .budget-table thead th {
        background: #f8fafc;
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .budget-table tbody td {
        padding: 13px 14px;
        border-bottom: 1px solid #eef2f7;
        font-size: 13px;
        color: var(--budget-text);
        vertical-align: middle;
    }

    .budget-table tbody tr:hover { background: #fafcff; }

    .budget-name {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .budget-name strong { font-size: 13px; }
    .budget-name small { color: var(--budget-muted); font-size: 12px; }

    .money {
        font-weight: 800;
        font-family: "SF Mono", Consolas, monospace;
        color: #0f172a;
        white-space: nowrap;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .badge-active { background: #dcfce7; color: #166534; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-rejected { background: #fee2e2; color: #991b1b; }

    .progress {
        height: 8px;
        background: #eef2f7;
        border-radius: 999px;
        overflow: hidden;
        margin-top: 6px;
        min-width: 120px;
    }

    .progress-bar {
        height: 100%;
        border-radius: inherit;
        transition: width 0.25s ease;
    }
    .progress-bar.success { background: var(--budget-success); }
    .progress-bar.warning { background: var(--budget-warning); }
    .progress-bar.danger { background: var(--budget-danger); }

    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.15s ease;
        background: transparent;
        font-size: 13px;
    }
    .action-btn.edit { border-color: #2563eb; color: #2563eb; }
    .action-btn.edit:hover { background: #2563eb; color: #fff; }
    .action-btn.delete { border-color: #ef4444; color: #ef4444; }
    .action-btn.delete:hover { background: #ef4444; color: #fff; }

    .empty-state {
        padding: 56px 18px;
        text-align: center;
        color: var(--budget-muted);
    }
    .empty-state i { font-size: 44px; margin-bottom: 10px; opacity: 0.4; }

    .budget-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 100;
        align-items: flex-start;
        justify-content: center;
        padding-top: 56px;
        overflow-y: auto;
        background: rgba(15, 23, 42, 0.5);
    }
    .budget-modal.show { display: flex; }
    .budget-modal-card {
        width: 100%;
        max-width: 720px;
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(0,0,0,0.22);
        margin-bottom: 56px;
    }
    .budget-modal-header {
        padding: 16px 20px;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }
    .budget-modal-header h3 { margin: 0; font-size: 16px; font-weight: 800; }
    .budget-modal-header .close-btn {
        background: rgba(255,255,255,0.18);
        color: #fff;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 999px;
        cursor: pointer;
    }
    .budget-modal-body { padding: 20px; }
    .budget-modal-footer {
        padding: 14px 20px;
        background: #f9fafb;
        border-top: 1px solid #eef2f7;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    .form-row { margin-bottom: 14px; }
    .form-row label {
        display: block;
        font-size: 12px;
        font-weight: 800;
        color: var(--budget-text);
        margin-bottom: 6px;
    }
    .form-row input,
    .form-row select {
        width: 100%;
        border: 1px solid var(--budget-border);
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 13px;
        color: var(--budget-text);
    }
    .form-row input:focus,
    .form-row select:focus {
        outline: none;
        border-color: var(--budget-primary);
        box-shadow: 0 0 0 3px rgba(45,62,181,0.10);
    }
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    .btn-cancel,
    .btn-save,
    .btn-delete-confirm {
        border: none;
        border-radius: 10px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
    }
    .btn-cancel { background: #fff; color: var(--budget-text); border: 1px solid var(--budget-border); }
    .btn-save { background: var(--budget-primary); color: #fff; }
    .btn-delete-confirm { background: var(--budget-danger); color: #fff; }

    .confirm-wrap { padding: 24px 20px; text-align: center; }
    .confirm-wrap .warn-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto 12px;
        border-radius: 999px;
        background: #fee2e2;
        color: #b91c1c;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    @media (max-width: 768px) {
        .budget-shell { padding: 10px; }
        .budget-summary { grid-template-columns: 1fr 1fr; }
        .filter-group select,
        .filter-group input { min-width: 100%; }
        .form-grid-2 { grid-template-columns: 1fr; }
        .budget-table thead { display: none; }
        .budget-table tbody tr {
            display: block;
            margin: 10px 0;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
        }
        .budget-table tbody td {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            padding: 10px 14px;
        }
        .budget-table tbody td::before {
            content: attr(data-label);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
            white-space: nowrap;
        }
        .budget-table tbody td.actions-cell { justify-content: flex-end; }
        .budget-table tbody td.actions-cell::before { content: ''; }
    }
</style>
@endsection
@section('main-content')

    @include('layout.expense-tabs')


@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
@endphp

<div class="budget-shell">
    <div class="budget-card">
        <div class="budget-header">
            <div>
                <h2><i class="fas fa-chart-pie mr-2"></i>Budget Setup</h2>
                <div class="subtext">Set company-wise budgets and watch actual spend against the plan.</div>
            </div>
            <div class="budget-actions">
                <a href="{{ route('role.expenses.budget-setup', ['role' => $role]) }}" class="budget-btn reset">
                    <i class="fas fa-rotate-left"></i>Reset Filters
                </a>
                @can('create expense')
                <button type="button" class="budget-btn add create-new-btn">
                    <i class="fas fa-plus"></i>Add Budget
                </button>
                @endcan
            </div>
        </div>

        <div class="budget-summary">
            <div class="budget-stat primary">
                <div class="label">Total Budgets</div>
                <div class="value">{{ number_format($summary['total_budgets']) }}</div>
                <div class="note">Configured budget rules</div>
            </div>
            <div class="budget-stat success">
                <div class="label">Allocated</div>
                <div class="value">৳ {{ number_format($summary['total_allocated'], 2) }}</div>
                <div class="note">Budgeted amount</div>
            </div>
            <div class="budget-stat warning">
                <div class="label">Spent</div>
                <div class="value">৳ {{ number_format($summary['total_spent'], 2) }}</div>
                <div class="note">Inside each budget's current period</div>
            </div>
            <div class="budget-stat danger">
                <div class="label">Over Budget</div>
                <div class="value">{{ number_format($summary['over_budget']) }}</div>
                <div class="note">Needs immediate attention</div>
            </div>
        </div>

        <div class="filter-card">
            <form action="" method="GET" class="w-full">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="filter-group">
                        <label for="company_id">Company</label>
                        <select name="company_id" id="company_id" class="select2">
                            <option value="">All Companies</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="expense_category_id">Category</label>
                        <select name="expense_category_id" id="expense_category_id" class="select2">
                            <option value="">All Categories</option>
                            @foreach ($expenseCategories as $category)
                                <option value="{{ $category->id }}" {{ request('expense_category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="period">Period</label>
                        <select name="period" id="period" class="select2">
                            <option value="">All Periods</option>
                            @foreach (['Weekly','Monthly','Quarterly','Yearly'] as $period)
                                <option value="{{ $period }}" {{ request('period') === $period ? 'selected' : '' }}>{{ $period }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Company or category">
                    </div>
                    <button type="submit" class="filter-btn apply"><i class="fas fa-filter mr-1"></i>Apply</button>
                    <a href="{{ route('role.expenses.budget-setup', ['role' => $role]) }}" class="filter-btn reset inline-flex items-center"><i class="fas fa-eraser mr-1"></i>Clear</a>
                </div>
            </form>
        </div>

        <div class="budget-table-wrap">
            <div class="table-responsive">
                <table class="budget-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Company</th>
                            <th>Category</th>
                            <th>Period</th>
                            <th>Budget</th>
                            <th>Spent</th>
                            <th>Usage</th>
                            <th>Status</th>
                            @canany(['edit expense', 'delete expense'])
                            <th>Actions</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $index => $budget)
                            <tr>
                                <td data-label="#">{{ $datas->firstItem() + $index }}</td>
                                <td data-label="Company">
                                    <div class="budget-name">
                                        <strong>{{ $budget->company?->short_name ?? $budget->company?->name ?? '—' }}</strong>
                                        <small>{{ $budget->company?->name ?? '—' }}</small>
                                    </div>
                                </td>
                                <td data-label="Category">
                                    <div class="budget-name">
                                        <strong>{{ $budget->expense_category?->name ?? '—' }}</strong>
                                        <small>Created by {{ $budget->user?->name ?? '—' }}</small>
                                    </div>
                                </td>
                                <td data-label="Period">
                                    {{-- The window is spelled out because "Monthly · ৳50,000 · ৳12,400
                                         spent" cannot be read correctly without it. --}}
                                    <div class="budget-name">
                                        <strong>{{ $budget->period }}</strong>
                                        <small>{{ $budget->period_window }}</small>
                                    </div>
                                </td>
                                <td data-label="Budget" class="money">৳ {{ number_format($budget->amount, 2) }}</td>
                                <td data-label="Spent" class="money">৳ {{ number_format($budget->spent_amount, 2) }}</td>
                                <td data-label="Usage">
                                    <div class="money">{{ $budget->usage_percent }}%</div>
                                    <div class="progress">
                                        <div class="progress-bar {{ $budget->bar_class }}" style="width: {{ min($budget->usage_percent, 100) }}%"></div>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="badge {{ $budget->badge_class }}">{{ $budget->budget_label }}</span>
                                </td>
                                @canany(['edit expense', 'delete expense'])
                                <td data-label="Actions" class="actions-cell">
                                    <div class="flex items-center gap-2">
                                        @can('edit expense')
                                        <button type="button"
                                            class="action-btn edit edit-item-btn"
                                            data-item_id="{{ $budget->id }}"
                                            data-company_id="{{ $budget->company_id }}"
                                            data-expense_category_id="{{ $budget->expense_category_id }}"
                                            data-period="{{ $budget->period }}"
                                            data-amount="{{ $budget->amount }}"
                                            data-threshold="{{ $budget->threshold }}"
                                            title="Edit Budget">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        @endcan
                                        @can('delete expense')
                                        <button type="button" class="action-btn delete delete-item-btn" data-item_id="{{ $budget->id }}" data-name="{{ $budget->expense_category?->name }}" title="Delete Budget">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                                @endcanany
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-chart-pie"></i>
                                        <h4 class="text-gray-700 font-semibold text-lg mb-1">No budget records found</h4>
                                        <p class="text-sm">Add the first budget to start tracking expense control.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="px-4 pb-4">
            {{ $datas->appends(request()->all())->links() }}
        </div>
    </div>
</div>

@include('expense-budgets.create-modal')
@include('expense-budgets.edit-modal')
@include('expense-budgets.delete-modal')

@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    window.budgetCompanies = @json($companies->map(fn ($company) => ['id' => $company->id, 'name' => $company->name]));
    window.budgetCategories = @json($expenseCategories->map(fn ($category) => ['id' => $category->id, 'company_id' => $category->company_id, 'name' => $category->name]));
    window.budgetCreateUrl = @json(route('role.expenses.budget-setup.store', ['role' => $role]));
    window.budgetUpdateBase = @json(url('/' . $role . '/expenses/budget-setup'));
    window.budgetDeleteBase = @json(url('/' . $role . '/expenses/budget-setup'));

    // Shared rows are offered to everyone; a company's own rows only to it.
    // Narrowing to a company therefore ADDS to the shared list rather than
    // replacing it, so choosing a company never empties the dropdown.
    //
    // Same rule as expenses/partials/classification-js.blade.php and as
    // ExpenseBudgetController::spendableCategory() on the server. The
    // `company_id == null` clause was the one missing here: a category names a
    // company only when it is that company's own, and none of them currently
    // do — so `String(null) === String(6)` was false for every category and
    // picking any company blanked the whole list.
    const budgetCategoryVisible = (category, companyId) =>
        !companyId || category.company_id == null || String(category.company_id) === String(companyId);

    function buildBudgetCategoryOptions(companyId, selectedId = '') {
        const options = window.budgetCategories
            .filter(category => budgetCategoryVisible(category, companyId))
            .map(category => `<option value="${category.id}" ${String(selectedId) === String(category.id) ? 'selected' : ''}>${category.name}</option>`)
            .join('');

        return `<option value="">— Select Category —</option>${options}`;
    }

    function refreshBudgetCategorySelect(selectSelector, companyId, selectedId = '') {
        $(selectSelector).html(buildBudgetCategoryOptions(companyId, selectedId)).trigger('change');
    }

    function openBudgetModal(modalSelector) {
        $(modalSelector).addClass('show');
    }

    function closeBudgetModal(modalSelector) {
        $(modalSelector).removeClass('show');
    }

    $(document).ready(function () {
        $('.select2').select2();

        $('.create-new-btn').on('click', function () {
            $('#createForm')[0].reset();
            $('#create_company_id').val('').trigger('change');
            refreshBudgetCategorySelect('#create_expense_category_id', '');
            openBudgetModal('#createModal');
        });

        $('.edit-item-btn').on('click', function () {
            const itemId = $(this).data('item_id');
            const companyId = $(this).data('company_id');
            const categoryId = $(this).data('expense_category_id');
            const period = $(this).data('period');
            const amount = $(this).data('amount');
            const threshold = $(this).data('threshold');

            $('#editItemId').val(itemId);
            $('#edit_company_id').val(companyId).trigger('change');
            refreshBudgetCategorySelect('#edit_expense_category_id', companyId, categoryId);
            $('#edit_period').val(period).trigger('change');
            $('#edit_amount').val(amount);
            $('#edit_threshold').val(threshold);
            $('#editForm').attr('action', window.budgetUpdateBase + '/' + itemId);
            openBudgetModal('#editModal');
        });

        $('.delete-item-btn').on('click', function () {
            const itemId = $(this).data('item_id');
            const name = $(this).data('name') || 'this budget';
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', itemId);
            openBudgetModal('#deleteModal');
        });

        $('.modal-close-create, .modal-close-edit, .modal-close-delete, .budget-modal-backdrop').on('click', function (e) {
            const modal = $(this).closest('.budget-modal');
            if (modal.length) {
                closeBudgetModal('#' + modal.attr('id'));
            }
        });

        $('#createModal .budget-modal-card, #editModal .budget-modal-card, #deleteModal .budget-modal-card').on('click', function (e) {
            e.stopPropagation();
        });

        $('#create_company_id').on('change', function () {
            refreshBudgetCategorySelect('#create_expense_category_id', $(this).val());
        });

        $('#edit_company_id').on('change', function () {
            refreshBudgetCategorySelect('#edit_expense_category_id', $(this).val());
        });

        $('#createSubmit').on('click', function (e) {
            e.preventDefault();
            const formData = new FormData($('#createForm')[0]);
            $.ajax({
                url: window.budgetCreateUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: response.message || 'Budget created successfully.' });
                        closeBudgetModal('#createModal');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to create budget.' });
                }
            });
        });

        $('#editSubmit').on('click', function (e) {
            e.preventDefault();
            const formData = new FormData($('#editForm')[0]);
            $.ajax({
                url: $('#editForm').attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: response.message || 'Budget updated successfully.' });
                        closeBudgetModal('#editModal');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update budget.' });
                }
            });
        });

        $('#confirmDeleteBtn').on('click', function () {
            const itemId = $(this).data('item-id');
            $.ajax({
                url: window.budgetDeleteBase + '/' + itemId,
                method: 'POST',
                data: {
                    _method: 'DELETE',
                    _token: '{{ csrf_token() }}'
                },
                success: function (response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: response.message || 'Budget deleted successfully.' });
                        closeBudgetModal('#deleteModal');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Delete failed.' });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to delete budget.' });
                }
            });
        });

        $('.budget-modal').on('click', function (e) {
            if ($(e.target).hasClass('budget-modal')) {
                closeBudgetModal('#' + $(this).attr('id'));
            }
        });
    });
</script>
@endsection