{{--
    Department, Category and Sub-category on one page.

    They are one chain — Company → Department → Category → Sub-category — and the
    expense form walks it in that order, so the setup screens are stacked in that
    order too. Each panel keeps its own table, its own Add button and its own
    pager; the filter at the top drives all three, because filtering one link of a
    chain and not the others describes nothing.

    Every Add / Edit / Delete posts to the existing per-entity controllers. This
    page adds no write path of its own.
--}}
@extends('layout.app')

@section('meta-information')
    <title>Expense Classification</title>
@endsection

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
        .cls-panel{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05);}
        .cls-panel-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;
            padding:14px 20px;border-bottom:1px solid #f1f5f9;}
        .cls-panel-title{display:flex;align-items:center;gap:11px;min-width:0;}
        .cls-step{width:30px;height:30px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;
            font-size:12px;font-weight:800;color:#fff;flex-shrink:0;}
        .cls-panel h2{font-size:15px;font-weight:800;color:#0f172a;line-height:1.2;margin:0;}
        .cls-panel .cls-sub{font-size:11.5px;color:#94a3b8;margin-top:2px;}

        /* Accent per step, so the three tables stay tellable apart while scrolling. */
        .cls-dept .cls-step{background:#4f46e5;}
        .cls-cat  .cls-step{background:#0d9488;}
        .cls-sub  .cls-step{background:#7c3aed;}
        .cls-dept .cls-add{background:#4f46e5;}
        .cls-cat  .cls-add{background:#0d9488;}
        .cls-sub  .cls-add{background:#7c3aed;}
        .cls-add{display:inline-flex;align-items:center;gap:7px;color:#fff;border:0;cursor:pointer;
            padding:8px 14px;border-radius:9px;font-size:12.5px;font-weight:700;transition:filter .15s;}
        .cls-add:hover{filter:brightness(1.1);}

        .cls-count{font-size:11px;font-weight:700;color:#64748b;background:#f1f5f9;border-radius:20px;padding:3px 10px;white-space:nowrap;}
        .cls-warn{font-size:11px;font-weight:700;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:20px;padding:2px 9px;white-space:nowrap;}

        .cls-table{width:100%;border-collapse:collapse;}
        .cls-table thead th{background:#f8fafc;font-size:10.5px;font-weight:800;color:#64748b;text-transform:uppercase;
            letter-spacing:.05em;text-align:left;padding:10px 16px;border-bottom:1px solid #e5e7eb;white-space:nowrap;}
        .cls-table tbody td{padding:11px 16px;font-size:13px;color:#334155;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        .cls-table tbody tr:hover{background:#f8fafc;}
        .cls-table tbody tr:last-child td{border-bottom:0;}
        .cls-name{font-weight:700;color:#0f172a;}
        .cls-muted{color:#94a3b8;}

        .cls-pill{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;border-radius:20px;padding:2px 9px;white-space:nowrap;}
        .cls-pill.on{background:#ecfdf5;color:#047857;}
        .cls-pill.off{background:#f1f5f9;color:#64748b;}
        .cls-pill.company{background:#eff6ff;color:#1d4ed8;}
        .cls-pill.shared{background:#f5f3ff;color:#6d28d9;}
        .cls-pill.parent{background:#f0fdfa;color:#0f766e;}
        .cls-pill.acct{background:#f8fafc;color:#475569;border:1px solid #e2e8f0;}
        .cls-pill.unmapped{background:#fffbeb;color:#b45309;}

        .cls-act{display:inline-flex;align-items:center;justify-content:center;width:29px;height:29px;border-radius:7px;
            border:1px solid transparent;background:transparent;cursor:pointer;font-size:12px;transition:all .15s;}
        .cls-act.edit{border-color:#0d9488;color:#0d9488;}
        .cls-act.edit:hover{background:#0d9488;color:#fff;}
        .cls-act.del{border-color:#ef4444;color:#ef4444;}
        .cls-act.del:hover{background:#ef4444;color:#fff;}

        .cls-empty{text-align:center;padding:34px 16px;color:#94a3b8;font-size:13px;}
        .cls-foot{padding:10px 16px;border-top:1px solid #f1f5f9;}
        .cls-foot .pagination{margin:0;}

        /* The chain strip that explains why these three sit together. */
        .cls-chain{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:12px;color:#475569;
            background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:11px 16px;}
        .cls-chain b{color:#0f172a;font-weight:700;}
        .cls-chain i.sep{color:#cbd5e1;font-size:10px;}

        /* ── select2 inside the modals ──
           The generated box sits in a column of Tailwind-styled inputs, so it
           borrows their metrics (38px, rounded-lg, gray-300) rather than
           select2's own — layout/table-design sets those to 40px app-wide. */
        .cls-modal .select2-container--default .select2-selection--single{
            height:38px;border:1px solid #d1d5db;border-radius:.5rem;}
        .cls-modal .select2-container--default .select2-selection--single .select2-selection__rendered{
            line-height:36px;padding-left:.75rem;padding-right:1.75rem;font-size:.875rem;color:#374151;}
        .cls-modal .select2-container--default .select2-selection--single .select2-selection__arrow{
            height:36px;right:6px;}
        .cls-modal .select2-container--default.select2-container--open .select2-selection--single{border-color:#6366f1;}
        .cls-modal .select2-dropdown{border-color:#d1d5db;border-radius:.5rem;font-size:.875rem;}
        .cls-modal .select2-results__option{padding:7px 12px;}
        /* The open list is appended into the modal root, where the form panel
           already claims z-50 — without this the dropdown opens behind it. */
        .cls-modal .select2-container--open{z-index:60;}

        @media(max-width:768px){
            .cls-table thead{display:none;}
            .cls-table tbody tr{display:block;border-bottom:1px solid #e5e7eb;padding:6px 0;}
            .cls-table tbody td{display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:0;padding:6px 16px;}
            .cls-table tbody td::before{content:attr(data-label);font-size:10.5px;font-weight:800;color:#64748b;
                text-transform:uppercase;letter-spacing:.04em;flex-shrink:0;}
            .cls-table tbody td.no-label::before{content:"";}
        }
    </style>
@endsection

@section('main-content')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $canCreate = auth()->user()->can('create expense');
    $canEdit   = auth()->user()->can('edit expense');
    $canDelete = auth()->user()->can('delete expense');
    $canManage = $canEdit || $canDelete;
    $hasFilter = request()->hasAny(['company_id', 'search', 'status']);
@endphp

<div class="p-4 md:p-6 space-y-5">

    @include('layout.expense-tabs')

    {{-- ── Header ── --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                <i class="fas fa-layer-group text-indigo-500 mr-1.5"></i>Expense Classification
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                The three lists an expense is filed against — set them up here, in the order the expense form asks for them.
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-400 shrink-0">
            <span class="cls-count">{{ number_format($counts['departments']) }} departments</span>
            <span class="cls-count">{{ number_format($counts['categories']) }} categories</span>
            <span class="cls-count">{{ number_format($counts['sub_categories']) }} sub-categories</span>
        </div>
    </div>

    <div class="cls-chain">
        <i class="fas fa-diagram-project text-indigo-400"></i>
        <b>Company</b> <i class="fas fa-chevron-right sep"></i>
        <b>Department</b> <i class="fas fa-chevron-right sep"></i>
        <b>Category</b> <i class="fas fa-chevron-right sep"></i>
        <b>Sub-category</b>
        <span class="ml-1">— a department belongs to one company, which is how picking one fills the company in on the expense form.
        A category with no company is <span class="cls-pill shared"><i class="fas fa-globe"></i> shared</span> by every company.</span>
    </div>

    {{-- ── One filter for all three panels ── --}}
    <form method="get" action="{{ route('role.expense-classification.index', ['role' => $role]) }}">
        <div class="filter-container">
            <div class="filter-header {{ $hasFilter ? 'active' : '' }}">
                <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="filter-content {{ $hasFilter ? 'active' : '' }}">
                <div class="closest filter-row">
                    <div class="filter-group">
                        <label for="search">Name</label>
                        <input type="text" id="search" name="search" class="form-control" style="width:100%"
                               value="{{ request('search') }}" placeholder="matches all three lists">
                    </div>
                    <div class="filter-group">
                        <label for="company_id">Company</label>
                        <select id="company_id" name="company_id" class="form-control select2" style="width:100%">
                            <option value="">All</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ (string) $company->id === request('company_id') ? 'selected' : '' }}>
                                    {{ $company->short_name ?: $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control select2" style="width:100%">
                            <option value="">All</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" class="reset-btn">Reset</button>
                    <button type="submit" class="apply-btn">Apply Filters</button>
                </div>
            </div>
        </div>
    </form>

    {{-- ═══ 1. Departments ═══ --}}
    <section class="cls-panel cls-dept" id="panel-departments">
        <div class="cls-panel-head">
            <div class="cls-panel-title">
                <span class="cls-step">1</span>
                <div>
                    <h2>Departments</h2>
                    <div class="cls-sub">the unit that spends — always belongs to one company</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="cls-count">{{ number_format($counts['departments_on']) }} of {{ number_format($counts['departments']) }} active</span>
                @if($canCreate)
                    <button type="button" class="cls-add" data-open="deptCreateModal"><i class="fas fa-plus"></i> New Department</button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="cls-table">
                <thead>
                    <tr>
                        <th style="width:4%">#</th>
                        <th style="width:26%">Department</th>
                        <th style="width:18%">Company</th>
                        <th style="width:28%">Description</th>
                        <th style="width:12%">Expenses</th>
                        <th style="width:{{ $canManage ? '8%' : '12%' }}">Status</th>
                        @if($canManage)<th style="width:10%">Actions</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($departments as $i => $row)
                        <tr>
                            <td data-label="#" class="cls-muted">{{ $departments->firstItem() + $i }}</td>
                            <td data-label="Department"><span class="cls-name">{{ $row->name }}</span></td>
                            <td data-label="Company">
                                @if($row->company)
                                    <span class="cls-pill company"><i class="fas fa-building"></i>{{ $row->company->short_name ?: $row->company->name }}</span>
                                @else
                                    <span class="cls-muted">—</span>
                                @endif
                            </td>
                            <td data-label="Description">{{ $row->description ?: '—' }}</td>
                            <td data-label="Expenses">{{ number_format($row->expenses_count) }}</td>
                            <td data-label="Status">
                                <span class="cls-pill {{ $row->status ? 'on' : 'off' }}">{{ $row->status ? 'Active' : 'Inactive' }}</span>
                            </td>
                            @if($canManage)
                                <td class="no-label">
                                    <div class="flex items-center gap-1.5 justify-end md:justify-start">
                                        @if($canEdit)
                                            <button type="button" class="cls-act edit js-dept-edit" title="Edit"
                                                data-id="{{ $row->id }}"
                                                data-company_id="{{ $row->company_id }}"
                                                data-name="{{ $row->name }}"
                                                data-description="{{ $row->description }}"
                                                data-status="{{ (int) $row->status }}"><i class="fas fa-pen"></i></button>
                                        @endif
                                        @if($canDelete)
                                            <button type="button" class="cls-act del js-cls-delete" title="Delete"
                                                data-id="{{ $row->id }}"
                                                data-name="{{ $row->name }}"
                                                data-kind="department"
                                                data-url="{{ route('role.expense-departments.destroy', ['role' => $role, 'expense_department' => $row->id]) }}"><i class="fas fa-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 7 : 6 }}" class="cls-empty">
                            <i class="fas fa-sitemap fa-lg text-gray-300 block mb-2"></i>
                            No departments{{ $hasFilter ? ' match this filter' : ' yet' }}.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($departments->hasPages())
            <div class="cls-foot">{{ $departments->links() }}</div>
        @endif
    </section>

    {{-- ═══ 2. Categories ═══ --}}
    <section class="cls-panel cls-cat" id="panel-categories">
        <div class="cls-panel-head">
            <div class="cls-panel-title">
                <span class="cls-step">2</span>
                <div>
                    <h2>Categories</h2>
                    <div class="cls-sub">what the money was spent on — and which account it posts to</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($counts['categories_unmapped'] > 0)
                    <span class="cls-warn" title="An unmapped category posts to the general expense account">
                        <i class="fas fa-triangle-exclamation"></i> {{ $counts['categories_unmapped'] }} unmapped
                    </span>
                @endif
                <span class="cls-count">{{ number_format($counts['categories_on']) }} of {{ number_format($counts['categories']) }} active</span>
                @if($canCreate)
                    <button type="button" class="cls-add" data-open="catCreateModal"><i class="fas fa-plus"></i> New Category</button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="cls-table">
                <thead>
                    <tr>
                        <th style="width:4%">#</th>
                        <th style="width:22%">Category</th>
                        <th style="width:16%">Company</th>
                        <th style="width:20%">Posts To</th>
                        <th style="width:18%">Description</th>
                        <th style="width:10%">Subs</th>
                        <th style="width:{{ $canManage ? '8%' : '10%' }}">Status</th>
                        @if($canManage)<th style="width:10%">Actions</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $i => $row)
                        <tr>
                            <td data-label="#" class="cls-muted">{{ $categories->firstItem() + $i }}</td>
                            <td data-label="Category"><span class="cls-name">{{ $row->name }}</span></td>
                            <td data-label="Company">
                                @if($row->company_id && $row->company)
                                    <span class="cls-pill company"><i class="fas fa-building"></i>{{ $row->company->short_name ?: $row->company->name }}</span>
                                @else
                                    {{-- Not "unset": a category with no company is offered to every company. --}}
                                    <span class="cls-pill shared"><i class="fas fa-globe"></i>Shared</span>
                                @endif
                            </td>
                            <td data-label="Posts To">
                                @if($row->account)
                                    <span class="cls-pill acct">[{{ $row->account->code }}] {{ $row->account->name }}</span>
                                @else
                                    <span class="cls-pill unmapped" title="Falls back to {{ $fallbackAccount ? '['.$fallbackAccount->code.'] '.$fallbackAccount->name : 'the general expense account' }}">
                                        <i class="fas fa-triangle-exclamation"></i>Not mapped
                                    </span>
                                @endif
                            </td>
                            <td data-label="Description">{{ $row->description ?: '—' }}</td>
                            <td data-label="Subs">{{ number_format($row->sub_categories_count) }}</td>
                            <td data-label="Status">
                                <span class="cls-pill {{ $row->status ? 'on' : 'off' }}">{{ $row->status ? 'Active' : 'Inactive' }}</span>
                            </td>
                            @if($canManage)
                                <td class="no-label">
                                    <div class="flex items-center gap-1.5 justify-end md:justify-start">
                                        @if($canEdit)
                                            <button type="button" class="cls-act edit js-cat-edit" title="Edit"
                                                data-id="{{ $row->id }}"
                                                data-company_id="{{ $row->company_id }}"
                                                data-account_id="{{ $row->account_id }}"
                                                data-name="{{ $row->name }}"
                                                data-description="{{ $row->description }}"
                                                data-status="{{ (int) $row->status }}"><i class="fas fa-pen"></i></button>
                                        @endif
                                        @if($canDelete)
                                            <button type="button" class="cls-act del js-cls-delete" title="Delete"
                                                data-id="{{ $row->id }}"
                                                data-name="{{ $row->name }}"
                                                data-kind="category"
                                                data-subs="{{ (int) $row->sub_categories_count }}"
                                                data-url="{{ route('role.expense-categories.destroy', ['role' => $role, 'expense_category' => $row->id]) }}"><i class="fas fa-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="cls-empty">
                            <i class="fas fa-folder fa-lg text-gray-300 block mb-2"></i>
                            No categories{{ $hasFilter ? ' match this filter' : ' yet' }}.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())
            <div class="cls-foot">{{ $categories->links() }}</div>
        @endif
    </section>

    {{-- ═══ 3. Sub-categories ═══ --}}
    <section class="cls-panel cls-sub" id="panel-subcategories">
        <div class="cls-panel-head">
            <div class="cls-panel-title">
                <span class="cls-step">3</span>
                <div>
                    <h2>Sub-categories</h2>
                    <div class="cls-sub">the detail under a category — optional on an expense</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($counts['sub_categories_unmapped'] > 0)
                    <span class="cls-warn" title="An unmapped sub-category posts wherever its category posts, which loses the split the chart carries">
                        <i class="fas fa-triangle-exclamation"></i> {{ $counts['sub_categories_unmapped'] }} following category
                    </span>
                @endif
                <span class="cls-count">{{ number_format($counts['sub_categories_on']) }} of {{ number_format($counts['sub_categories']) }} active</span>
                @if($canCreate)
                    <button type="button" class="cls-add" data-open="subCreateModal"><i class="fas fa-plus"></i> New Sub-category</button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="cls-table">
                <thead>
                    <tr>
                        <th style="width:4%">#</th>
                        <th style="width:20%">Sub-category</th>
                        <th style="width:16%">Parent Category</th>
                        <th style="width:12%">Company</th>
                        <th style="width:20%">Posts To</th>
                        <th style="width:12%">Description</th>
                        <th style="width:{{ $canManage ? '8%' : '12%' }}">Status</th>
                        @if($canManage)<th style="width:10%">Actions</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($subCategories as $i => $row)
                        <tr>
                            <td data-label="#" class="cls-muted">{{ $subCategories->firstItem() + $i }}</td>
                            <td data-label="Sub-category"><span class="cls-name">{{ $row->name }}</span></td>
                            <td data-label="Parent">
                                @if($row->expense_category)
                                    <span class="cls-pill parent"><i class="fas fa-folder"></i>{{ $row->expense_category->name }}</span>
                                @else
                                    <span class="cls-muted">—</span>
                                @endif
                            </td>
                            <td data-label="Company">
                                @if($row->company)
                                    <span class="cls-pill company"><i class="fas fa-building"></i>{{ $row->company->short_name ?: $row->company->name }}</span>
                                @else
                                    <span class="cls-muted">—</span>
                                @endif
                            </td>
                            {{-- Its own account when it has one, otherwise what it actually
                                 inherits — the category's line, or the general fallback if the
                                 category has none either. Showing a bare "Not mapped" here
                                 would read as "goes nowhere", which is never true. --}}
                            <td data-label="Posts To">
                                @if($row->account)
                                    <span class="cls-pill acct">[{{ $row->account->code }}] {{ $row->account->name }}</span>
                                @elseif($row->expense_category?->account)
                                    <span class="cls-pill unmapped" title="Inherited from the {{ $row->expense_category->name }} category">
                                        <i class="fas fa-turn-up fa-rotate-90"></i>[{{ $row->expense_category->account->code }}] {{ $row->expense_category->account->name }}
                                    </span>
                                @else
                                    <span class="cls-pill unmapped" title="Neither this sub-category nor its category names an account">
                                        <i class="fas fa-triangle-exclamation"></i>{{ $fallbackAccount ? '['.$fallbackAccount->code.'] '.$fallbackAccount->name : 'Not mapped' }}
                                    </span>
                                @endif
                            </td>
                            <td data-label="Description">{{ $row->description ?: '—' }}</td>
                            <td data-label="Status">
                                <span class="cls-pill {{ $row->status ? 'on' : 'off' }}">{{ $row->status ? 'Active' : 'Inactive' }}</span>
                            </td>
                            @if($canManage)
                                <td class="no-label">
                                    <div class="flex items-center gap-1.5 justify-end md:justify-start">
                                        @if($canEdit)
                                            <button type="button" class="cls-act edit js-sub-edit" title="Edit"
                                                data-id="{{ $row->id }}"
                                                data-company_id="{{ $row->company_id }}"
                                                data-expense_category_id="{{ $row->expense_category_id }}"
                                                data-account_id="{{ $row->account_id }}"
                                                data-name="{{ $row->name }}"
                                                data-description="{{ $row->description }}"
                                                data-status="{{ (int) $row->status }}"><i class="fas fa-pen"></i></button>
                                        @endif
                                        @if($canDelete)
                                            <button type="button" class="cls-act del js-cls-delete" title="Delete"
                                                data-id="{{ $row->id }}"
                                                data-name="{{ $row->name }}"
                                                data-kind="sub-category"
                                                data-url="{{ route('role.expense-subcategories.destroy', ['role' => $role, 'expense_subcategory' => $row->id]) }}"><i class="fas fa-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="cls-empty">
                            <i class="fas fa-folder-tree fa-lg text-gray-300 block mb-2"></i>
                            No sub-categories{{ $hasFilter ? ' match this filter' : ' yet' }}.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($subCategories->hasPages())
            <div class="cls-foot">{{ $subCategories->links() }}</div>
        @endif
    </section>

</div>

@include('expense-classification.modals', [
    'role'             => $role,
    'companies'        => $companies,
    'accounts'         => $accounts,
    'fallbackAccount'  => $fallbackAccount,
    'parentCategories' => $parentCategories,
    'canViewAll'       => $canViewAll,
])

@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
/*
 * One driver for three entities. The old pages each shipped their own copy of
 * this with the SAME element ids (#createModal, #editForm, #confirmDeleteBtn),
 * which is exactly why they could not simply be dropped onto one page — every id
 * here is prefixed with its entity.
 */
$(function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    // Guarded: select2 comes off a CDN, and an unguarded call throws
    // "$(...).select2 is not a function" the moment that fetch fails — which
    // aborts the rest of this ready block, so every Add / Edit / Delete button
    // on the page silently stops working too. Degrade to plain <select>s
    // instead; the filter still submits.
    if ($.fn.select2) {
        $('.select2').select2();

        /*
         * The modal dropdowns, made searchable. "Posts To Account" lists the
         * whole chart of accounts and "Parent Category" every active category —
         * a native <select> gives no way to find a row in either.
         *
         * dropdownParent is the modal ROOT, not the panel and not <body>:
         *   - the panel is `overflow-y-auto`, which clips the dropdown;
         *   - <body> puts it behind the backdrop.
         * Each modal is its own parent, so the list opens over its own form.
         */
        $('.cls-modal').each(function () {
            const $modal = $(this);

            $modal.find('.cls-select2').each(function () {
                const $sel = $(this);

                $sel.select2({
                    dropdownParent: $modal,
                    // The modals start `hidden`, so select2 cannot measure them —
                    // without an explicit width every box renders zero-wide.
                    width: '100%',
                    // A search box over "Active / Inactive" is noise; it appears
                    // once a list is long enough to actually need one.
                    minimumResultsForSearch: $sel.find('option').length > 8 ? 0 : Infinity,
                });
            });
        });
    } else {
        console.warn('select2 did not load — dropdowns fall back to plain selects.');
    }

    const open  = id => $('#' + id).removeClass('hidden');
    const close = id => $('#' + id).addClass('hidden');

    $('[data-open]').on('click', function () { open($(this).data('open')); });
    $('[data-close]').on('click', function () { close($(this).data('close')); });
    $('.cls-modal-backdrop').on('click', function () { close($(this).closest('.cls-modal').attr('id')); });

    // Esc closes whatever is open — three stacked forms make a stray modal easy
    // to lose track of. An open select2 list gets first claim on the key, so
    // dismissing a dropdown does not also throw away the form behind it.
    $(document).on('keydown', e => {
        if (e.key !== 'Escape') return;
        if ($('.select2-container--open').length) return;
        $('.cls-modal').addClass('hidden');
    });

    function errorFrom(xhr) {
        // 422 comes from $request->validate() in the category / sub-category
        // controllers; the department controller returns {success:false} at 200.
        const j = xhr.responseJSON || {};
        if (j.errors) return Object.values(j.errors)[0][0];
        return j.message || 'Something went wrong.';
    }

    function post(formId, modalId, method) {
        const $form = $('#' + formId);
        $.ajax({
            url: $form.attr('action'),
            method: method || 'POST',
            data: $form.serialize(),
            success(res) {
                if (res.success === false) {
                    Swal.fire({ icon: 'error', title: 'Oops...', text: res.message || 'Something went wrong.' });
                    return;
                }
                close(modalId);
                Swal.fire({ icon: 'success', title: 'Done', text: res.message || 'Saved.' });
                setTimeout(() => window.location.reload(), 800);
            },
            error: xhr => Swal.fire({ icon: 'error', title: 'Error!', text: errorFrom(xhr) })
        });
    }

    function filled(sel, msgSel) {
        const ok = !!$(sel).val() && String($(sel).val()).trim() !== '';
        $(msgSel).toggleClass('hidden', ok);
        return ok;
    }

    /*
     * Edit URLs are built from the update route with the id swapped in, not from
     * the index route + '/id' — the three index routes now redirect here, so
     * deriving an update URL from one would break the moment that changes again.
     */
    const UPDATE_URL = {
        dept: '{{ route('role.expense-departments.update', ['role' => $role, 'expense_department' => '__ID__']) }}',
        cat:  '{{ route('role.expense-categories.update',  ['role' => $role, 'expense_category'   => '__ID__']) }}',
        sub:  '{{ route('role.expense-subcategories.update', ['role' => $role, 'expense_subcategory' => '__ID__']) }}',
    };
    const urlFor = (kind, id) => UPDATE_URL[kind].replace('__ID__', id);

    /* ── Department ── */
    $('#deptCreateSubmit').on('click', function () {
        if (!filled('#dept_create_name', '#dept_create_name_msg')) return;
        post('deptCreateForm', 'deptCreateModal');
    });
    $('.js-dept-edit').on('click', function () {
        const d = $(this).data();
        $('#dept_edit_id, #dept_edit_item_id').val(d.id);
        $('#deptEditForm').attr('action', urlFor('dept', d.id));
        $('#dept_edit_company_id').val(d.company_id || '').trigger('change');
        $('#dept_edit_name').val(d.name);
        $('#dept_edit_description').val(d.description || '');
        $('#dept_edit_status').val(String(d.status)).trigger('change');
        open('deptEditModal');
    });
    $('#deptEditSubmit').on('click', function () {
        if (!filled('#dept_edit_name', '#dept_edit_name_msg')) return;
        post('deptEditForm', 'deptEditModal', 'PUT');
    });

    /* ── Category ── */
    $('#catCreateSubmit').on('click', function () {
        if (!filled('#cat_create_name', '#cat_create_name_msg')) return;
        post('catCreateForm', 'catCreateModal');
    });
    $('.js-cat-edit').on('click', function () {
        const d = $(this).data();
        $('#cat_edit_id, #cat_edit_item_id').val(d.id);
        $('#catEditForm').attr('action', urlFor('cat', d.id));
        $('#cat_edit_company_id').val(d.company_id || '').trigger('change');
        $('#cat_edit_account_id').val(d.account_id || '').trigger('change');
        $('#cat_edit_name').val(d.name);
        $('#cat_edit_description').val(d.description || '');
        $('#cat_edit_status').val(String(d.status)).trigger('change');
        open('catEditModal');
    });
    $('#catEditSubmit').on('click', function () {
        if (!filled('#cat_edit_name', '#cat_edit_name_msg')) return;
        post('catEditForm', 'catEditModal', 'PUT');
    });

    /* ── Sub-category ── */
    $('#subCreateSubmit').on('click', function () {
        const okCat  = filled('#sub_create_expense_category_id', '#sub_create_category_msg');
        const okName = filled('#sub_create_name', '#sub_create_name_msg');
        if (!okCat || !okName) return;
        post('subCreateForm', 'subCreateModal');
    });
    $('.js-sub-edit').on('click', function () {
        const d = $(this).data();
        $('#sub_edit_id, #sub_edit_item_id').val(d.id);
        $('#subEditForm').attr('action', urlFor('sub', d.id));
        $('#sub_edit_company_id').val(d.company_id || '').trigger('change');
        $('#sub_edit_expense_category_id').val(d.expense_category_id || '').trigger('change');
        $('#sub_edit_account_id').val(d.account_id || '').trigger('change');
        $('#sub_edit_name').val(d.name);
        $('#sub_edit_description').val(d.description || '');
        $('#sub_edit_status').val(String(d.status)).trigger('change');
        open('subEditModal');
    });
    $('#subEditSubmit').on('click', function () {
        const okCat  = filled('#sub_edit_expense_category_id', '#sub_edit_category_msg');
        const okName = filled('#sub_edit_name', '#sub_edit_name_msg');
        if (!okCat || !okName) return;
        post('subEditForm', 'subEditModal', 'PUT');
    });

    /* ── Delete (one modal, all three) ── */
    $('.js-cls-delete').on('click', function () {
        const d = $(this).data();
        $('#clsDeleteKind').text(d.kind);
        $('#clsDeleteName').text(d.name);
        // A category with children is worth warning about before the click, not
        // after — deleting it leaves its sub-categories pointing at nothing.
        $('#clsDeleteSubs').toggleClass('hidden', !(d.subs > 0)).find('span').text(d.subs);
        $('#clsDeleteConfirm').data('url', d.url).data('id', d.id);
        open('clsDeleteModal');
    });
    $('#clsDeleteConfirm').on('click', function () {
        $.ajax({
            url: $(this).data('url'),
            method: 'DELETE',
            data: { item_id: $(this).data('id') },
            success(res) {
                close('clsDeleteModal');
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Done', text: res.message });
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    // An in-use department is refused with a reason, not a shrug.
                    Swal.fire({ icon: 'info', title: 'Cannot delete', text: res.message });
                }
            },
            error: xhr => { close('clsDeleteModal'); Swal.fire({ icon: 'error', title: 'Error!', text: errorFrom(xhr) }); }
        });
    });

    /* ── Filter box ── */
    const fh = document.querySelector('.filter-container .filter-header');
    const fc = document.querySelector('.filter-container .filter-content');
    if (fh && fc) {
        fh.addEventListener('click', function () {
            this.classList.toggle('active');
            fc.classList.toggle('active');
        });
    }
    $('.filter-container .reset-btn').on('click', function (e) {
        e.preventDefault();
        window.location = '{{ route('role.expense-classification.index', ['role' => $role]) }}';
    });
});
</script>
@endsection
