@extends('layout.app')

@section('meta-information')
    <title>Demo Data Manager</title>
@endsection

@section('css')
<style>
    .dm-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(15,23,42,.05);
    }
    .section-card {
        border: 2px solid #e5e7eb;
        border-radius: 14px;
        padding: 16px 18px;
        cursor: pointer;
        transition: border-color .15s, background .15s, box-shadow .15s;
        user-select: none;
        position: relative;
    }
    .section-card:hover {
        border-color: #93c5fd;
        background: #f0f9ff;
    }
    .section-card.selected {
        border-color: #ef4444;
        background: #fff5f5;
        box-shadow: 0 0 0 3px rgba(239,68,68,.1);
    }
    .section-card input[type=checkbox] {
        position: absolute;
        top: 14px;
        right: 14px;
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #ef4444;
    }
    .badge-count {
        display: inline-flex;
        align-items: center;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        margin-top: 6px;
    }
    .badge-has { background:#fee2e2; color:#dc2626; }
    .badge-empty { background:#f3f4f6; color:#9ca3af; }
    .badge-scope { background:#e0f2fe; color:#0369a1; }
    .icon-box {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    /* modal */
    #confirm-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    #confirm-modal.show { display: flex; }
    .modal-box {
        background: #fff;
        border-radius: 20px;
        padding: 32px 28px;
        width: 100%;
        max-width: 440px;
        box-shadow: 0 25px 60px rgba(0,0,0,.2);
    }
</style>
@endsection

@section('main-content')
@php
    $role = \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first());
    $scopeCompany = $companyId ? $companies->firstWhere('id', $companyId) : null;
    $scopeLabel = $scopeCompany ? ($scopeCompany->short_name ?: $scopeCompany->name) : null;
@endphp

<div class="dm-card">
    <div class="p-6 border-b border-gray-100">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-red-100 text-red-600">
                        <i class="fas fa-trash-alt text-sm"></i>
                    </span>
                    Demo Data Manager
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Select the sections you want to clear, then click <strong>Clear Selected</strong>. This action is <strong>irreversible</strong>.
                </p>
            </div>
            <button id="select-all-btn" onclick="toggleSelectAll()"
                class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">
                Select All
            </button>
        </div>

        {{-- Company scope. Deliberately labelled with the sections it actually
             reaches: every other section wipes its table across all companies,
             exactly as it always has, and this control does not touch them.
             The list is built from the sections themselves so it cannot fall out
             of step with which ones honour the filter. --}}
        @php $scopedNames = collect($sections)->filter(fn($s) => !empty($s['scoped']))->pluck('sub'); @endphp
        <form method="GET" action="{{ route('role.demo-data.index', ['role' => $role]) }}"
              class="mt-4 flex flex-wrap items-center gap-2">
            <label for="company_id" class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                Limit to company
            </label>
            <select name="company_id" id="company_id" onchange="this.form.submit()"
                class="text-sm rounded-lg border-gray-300 py-1.5 pl-3 pr-8 focus:border-pink-400 focus:ring-pink-400">
                <option value="">All companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected($companyId === $company->id)>
                        {{ $company->short_name ?: $company->name }}
                    </option>
                @endforeach
            </select>
            <span class="text-xs text-gray-400">
                Limits
                @foreach ($scopedNames as $name)<strong>{{ $name }}</strong>@if (!$loop->last){{ $loop->remaining === 1 ? ' and ' : ', ' }}@endif @endforeach
                only — every other section clears all companies.
            </span>
        </form>
    </div>

    <div class="p-6">
        @if (session('success'))
            <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                <i class="fas fa-check-circle mt-0.5 text-emerald-500"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($sections as $key => $section)
            @php
                $colors = [
                    'blue'    => ['bg' => '#eff6ff', 'icon' => '#2563eb', 'ibg' => '#dbeafe'],
                    'indigo'  => ['bg' => '#eef2ff', 'icon' => '#4f46e5', 'ibg' => '#e0e7ff'],
                    'violet'  => ['bg' => '#f5f3ff', 'icon' => '#7c3aed', 'ibg' => '#ede9fe'],
                    'emerald' => ['bg' => '#ecfdf5', 'icon' => '#059669', 'ibg' => '#d1fae5'],
                    'teal'    => ['bg' => '#f0fdfa', 'icon' => '#0d9488', 'ibg' => '#ccfbf1'],
                    'orange'  => ['bg' => '#fff7ed', 'icon' => '#ea580c', 'ibg' => '#ffedd5'],
                    'amber'   => ['bg' => '#fffbeb', 'icon' => '#d97706', 'ibg' => '#fef3c7'],
                    'cyan'    => ['bg' => '#ecfeff', 'icon' => '#0891b2', 'ibg' => '#cffafe'],
                    'rose'    => ['bg' => '#fff1f2', 'icon' => '#e11d48', 'ibg' => '#ffe4e6'],
                    'pink'    => ['bg' => '#fdf2f8', 'icon' => '#db2777', 'ibg' => '#fce7f3'],
                    'purple'  => ['bg' => '#faf5ff', 'icon' => '#7c3aed', 'ibg' => '#ede9fe'],
                    'lime'    => ['bg' => '#f7fee7', 'icon' => '#65a30d', 'ibg' => '#ecfccb'],
                    'sky'     => ['bg' => '#f0f9ff', 'icon' => '#0284c7', 'ibg' => '#e0f2fe'],
                    'fuchsia' => ['bg' => '#fdf4ff', 'icon' => '#c026d3', 'ibg' => '#fae8ff'],
                    'stone'   => ['bg' => '#fafaf9', 'icon' => '#57534e', 'ibg' => '#f0efed'],
                    'green'   => ['bg' => '#f0fdf4', 'icon' => '#16a34a', 'ibg' => '#dcfce7'],
                ];
                $c = $colors[$section['color']] ?? $colors['blue'];
            @endphp
            <div class="section-card" id="card-{{ $key }}" onclick="toggleCard('{{ $key }}')">
                <input type="checkbox" id="chk-{{ $key }}" value="{{ $key }}" onclick="event.stopPropagation(); syncCard('{{ $key }}')" form="clear-form" name="sections[]">

                <div class="flex items-center gap-3 pr-8">
                    <div class="icon-box" style="background:{{ $c['ibg'] }}; color:{{ $c['icon'] }}">
                        <i class="{{ $section['icon'] }}"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $section['label'] }}</div>
                        <div class="text-sm font-bold text-gray-800 leading-tight">{{ $section['sub'] }}</div>
                    </div>
                </div>

                @if (!empty($section['note']))
                    <p class="mt-2 text-xs text-gray-500 leading-snug">{{ $section['note'] }}</p>
                @endif

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($section['count'] > 0)
                        <span class="badge-count badge-has">
                            <i class="fas fa-circle text-[6px] mr-1.5"></i>
                            {{ number_format($section['count']) }} record{{ $section['count'] != 1 ? 's' : '' }}
                        </span>
                    @else
                        <span class="badge-count badge-empty">
                            <i class="fas fa-check text-[7px] mr-1.5"></i>
                            Empty
                        </span>
                    @endif

                    @if ($companyId && !empty($section['scoped']))
                        <span class="badge-count badge-scope">
                            <i class="fas fa-filter text-[7px] mr-1.5"></i>
                            {{ $scopeLabel }} only
                        </span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- Action bar --}}
        <div class="mt-6 flex items-center justify-between gap-4 pt-5 border-t border-gray-100">
            <p class="text-sm text-gray-500" id="selection-summary">No sections selected.</p>
            <button type="button" onclick="dmOpenModal()"
                id="clear-btn"
                disabled
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-red-600 text-white font-semibold text-sm
                       hover:bg-red-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                <i class="fas fa-trash-alt"></i>
                Clear Selected
            </button>
        </div>
    </div>
</div>

{{-- Hidden form (submitted from modal) --}}
<form id="clear-form" method="POST" action="{{ route('role.demo-data.destroy', ['role' => $role]) }}">
    @csrf
    @method('DELETE')
    {{-- Carries the scope shown on screen through to the delete, so what gets
         cleared is what the counts said it would be. --}}
    <input type="hidden" name="company_id" value="{{ $companyId }}">
</form>

{{-- Confirmation modal --}}
<div id="confirm-modal">
    <div class="modal-box">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-11 h-11 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-900">Confirm Deletion</h2>
                <p class="text-sm text-gray-500">This cannot be undone.</p>
            </div>
        </div>

        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
            <p class="text-xs font-semibold text-red-700 uppercase tracking-wide mb-2">Sections to be cleared:</p>
            <ul id="modal-list" class="space-y-1 text-sm text-red-800"></ul>
        </div>

        @if ($scopeLabel)
            <div class="bg-sky-50 border border-sky-200 rounded-xl p-3 mb-5 text-xs text-sky-800">
                <i class="fas fa-filter mr-1"></i>
                {{-- Named from the sections themselves, like the scope control
                     above it. Spelling the list out by hand here meant a new
                     company-scoped section had to be remembered in two places,
                     and the sentence quietly under-reported what the filter
                     covered until someone noticed. --}}
                @foreach ($scopedNames as $name)<strong>{{ $name }}</strong>@if (!$loop->last){{ $loop->remaining === 1 ? ' and ' : ', ' }}@endif @endforeach
                {{ $scopedNames->count() === 1 ? 'is' : 'are' }} limited to <strong>{{ $scopeLabel }}</strong>.
                Any other section listed above clears <strong>all companies</strong>.
            </div>
        @endif

        <div class="flex gap-3 justify-end">
            <button type="button" onclick="dmCloseModal()"
                class="px-4 py-2 rounded-xl border border-gray-200 text-gray-700 font-medium text-sm hover:bg-gray-50 transition-colors">
                Cancel
            </button>
            <button type="button" onclick="dmSubmitForm()"
                class="px-5 py-2 rounded-xl bg-red-600 text-white font-semibold text-sm hover:bg-red-700 transition-colors flex items-center gap-2">
                <i class="fas fa-trash-alt"></i>
                Yes, Clear Data
            </button>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
const sectionLabels = {
    @foreach ($sections as $key => $section)
    '{{ $key }}': '{{ addslashes($section['sub']) }}',
    @endforeach
};

function toggleCard(key) {
    const chk = document.getElementById('chk-' + key);
    chk.checked = !chk.checked;
    updateCardState(key);
    updateSummary();
}

function syncCard(key) {
    updateCardState(key);
    updateSummary();
}

function updateCardState(key) {
    const chk = document.getElementById('chk-' + key);
    const card = document.getElementById('card-' + key);
    if (chk.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
}

let allSelected = false;
function toggleSelectAll() {
    allSelected = !allSelected;
    document.getElementById('select-all-btn').textContent = allSelected ? 'Deselect All' : 'Select All';
    document.querySelectorAll('[id^="chk-"]').forEach(chk => {
        chk.checked = allSelected;
        const key = chk.id.replace('chk-', '');
        updateCardState(key);
    });
    updateSummary();
}

function updateSummary() {
    const checked = document.querySelectorAll('[id^="chk-"]:checked');
    const btn = document.getElementById('clear-btn');
    const summary = document.getElementById('selection-summary');

    if (checked.length === 0) {
        summary.textContent = 'No sections selected.';
        btn.disabled = true;
    } else {
        summary.textContent = checked.length + ' section' + (checked.length > 1 ? 's' : '') + ' selected.';
        btn.disabled = false;
    }
}

function dmOpenModal() {
    const checked = document.querySelectorAll('[id^="chk-"]:checked');
    if (checked.length === 0) return;

    const list = document.getElementById('modal-list');
    list.innerHTML = '';
    checked.forEach(chk => {
        const key = chk.id.replace('chk-', '');
        const li = document.createElement('li');
        li.innerHTML = '<i class="fas fa-times-circle mr-1.5 text-red-500"></i>' + (sectionLabels[key] || key);
        list.appendChild(li);
    });

    document.getElementById('confirm-modal').classList.add('show');
}

function dmCloseModal() {
    document.getElementById('confirm-modal').classList.remove('show');
}

function dmSubmitForm() {
    document.getElementById('clear-form').submit();
}

// Close modal on backdrop click
document.getElementById('confirm-modal').addEventListener('click', function(e) {
    if (e.target === this) dmCloseModal();
});
</script>
@endsection
