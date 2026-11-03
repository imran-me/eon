@php
    $chosenCompanies = collect(old('company_ids', $selectedCompanies ?? []))->map(fn ($id) => (int) $id)->all();
    $chosenProjects = collect(old('project_ids', $selectedProjects ?? []))->map(fn ($id) => (int) $id)->all();
@endphp
@once
<style>
    [data-party-assignments] .party-assignment-option { transition: border-color .15s, background-color .15s, box-shadow .15s; }
    [data-party-assignments] .party-assignment-option:hover { border-color:#93c5fd; background:#f8fbff; }
    [data-party-assignments] .party-assignment-option:has(input:checked) { border-color:#3b82f6; background:#eff6ff; box-shadow:0 0 0 1px #bfdbfe inset; color:#1d4ed8; }
    [data-party-assignments] input[type="checkbox"] { width:16px; height:16px; accent-color:#2563eb; }
</style>
@endonce
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" data-party-assignments>
    <div class="px-6 py-4 border-b border-gray-100 flex items-start gap-3">
        <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center text-xs font-bold">1</span>
        <div>
            <h2 class="text-sm font-semibold text-gray-700">Choose Company &amp; Project</h2>
            <p class="text-xs text-gray-400 mt-1">The next options adapt automatically to the companies selected here.</p>
        </div>
        <span class="ml-auto text-[11px] font-semibold text-blue-700 bg-blue-50 rounded-full px-2.5 py-1" data-assignment-summary>Nothing selected</span>
    </div>
    <div class="p-6 space-y-5">
        <div>
            <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Companies <span class="text-red-500">*</span></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($companies as $company)
                <label class="party-assignment-option flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-3 text-sm cursor-pointer">
                    <input type="checkbox" name="company_ids[]" value="{{ $company->id }}" data-company-box @checked(in_array((int) $company->id, $chosenCompanies, true))>
                    <span>{{ $company->name }}</span>
                </label>
                @endforeach
            </div>
            @error('company_ids')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
            @error('company_ids.*')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Projects <span class="text-gray-400 font-normal normal-case">(optional)</span></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" data-project-list>
                @forelse($projects as $project)
                <label class="party-assignment-option flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-3 text-sm cursor-pointer" data-project-row data-company-id="{{ $project->company_id }}">
                    <input type="checkbox" name="project_ids[]" value="{{ $project->id }}" @checked(in_array((int) $project->id, $chosenProjects, true))>
                    <span>{{ $project->name }}</span>
                    <span class="ml-auto text-[10px] text-gray-400">{{ optional($companies->firstWhere('id', $project->company_id))->name }}</span>
                </label>
                @empty
                <p class="text-sm text-gray-400">No active projects found.</p>
                @endforelse
            </div>
            <p class="text-xs text-gray-400 mt-2" data-project-empty hidden>Select a company to see its projects.</p>
            @error('project_ids')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
            @error('project_ids.*')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-party-assignments]').forEach(function (root) {
        var companies = Array.from(root.querySelectorAll('[data-company-box]'));
        var rows = Array.from(root.querySelectorAll('[data-project-row]'));
        var empty = root.querySelector('[data-project-empty]');
        function refreshProjects() {
            var selected = companies.filter(function (box) { return box.checked; }).map(function (box) { return box.value; });
            var visible = 0;
            rows.forEach(function (row) {
                var show = selected.includes(row.dataset.companyId);
                row.hidden = !show;
                if (!show) row.querySelector('input').checked = false;
                if (show) visible++;
            });
            if (empty) empty.hidden = visible > 0;
            var projectCount = rows.filter(function (row) { return row.querySelector('input').checked; }).length;
            var summary = root.querySelector('[data-assignment-summary]');
            if (summary) summary.textContent = selected.length
                ? selected.length + ' compan' + (selected.length === 1 ? 'y' : 'ies') + (projectCount ? ' · ' + projectCount + ' project' + (projectCount === 1 ? '' : 's') : '')
                : 'Nothing selected';
            document.dispatchEvent(new CustomEvent('party-companies-changed', { detail: { companyIds: selected } }));
        }
        companies.forEach(function (box) { box.addEventListener('change', refreshProjects); });
        rows.forEach(function (row) { row.querySelector('input').addEventListener('change', refreshProjects); });
        refreshProjects();
    });
});
</script>
