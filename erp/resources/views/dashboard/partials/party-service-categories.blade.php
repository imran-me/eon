@php
    $partyServices = [
        'ticket' => ['Ticket', 'fas fa-ticket-alt', 'indigo'],
        'visa' => ['Visa', 'fas fa-passport', 'blue'],
        'flight' => ['Flight', 'fas fa-plane', 'sky'],
        'file' => ['File', 'fas fa-folder-open', 'orange'],
        'instructor' => ['Instructor', 'fas fa-chalkboard-teacher', 'emerald'],
        'others' => ['Others', 'fas fa-ellipsis-h', 'gray'],
    ];
    $selectedServices = collect(old('service_categories', $party?->service_categories ?? []));
@endphp

@once
    <style>
        .party-service-option {
            display: flex;
            align-items: center;
            gap: .55rem;
            cursor: pointer;
            border: 1px solid #e5e7eb;
            border-radius: .5rem;
            padding: .75rem;
            color: #4b5563;
            font-size: .875rem;
            font-weight: 600;
            transition: border-color .15s, background-color .15s, color .15s;
        }
        .party-service-option:hover { border-color: #a5b4fc; background: #f9fafb; }
        .party-service-option:has(input:checked) { border-color: #6366f1; background: #eef2ff; color: #4338ca; }
        .party-service-option input { width: 16px; height: 16px; flex: 0 0 auto; accent-color: #4f46e5; cursor: pointer; }
    </style>
@endonce

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" data-travel-services hidden>
    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
            <i class="fas fa-layer-group text-indigo-600 text-sm"></i>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-700">Travel Service Categories</h2>
            <p class="text-xs text-gray-400">Shown because EPAL Travels &amp; Consultancy is selected</p>
        </div>
    </div>
    <div class="p-5 grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));">
        @foreach($partyServices as $value => [$label, $icon, $color])
            <label class="party-service-option">
                <input type="checkbox" name="service_categories[]" value="{{ $value }}"
                    @checked($selectedServices->contains($value))>
                <i class="{{ $icon }} w-4 text-center"></i>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    @error('service_categories')
        <p class="px-5 pb-4 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-travel-services]').forEach(function (root) {
        function applyCompanies(ids) {
            var show = ids.map(String).includes('2');
            root.hidden = !show;
            if (!show) root.querySelectorAll('input[type="checkbox"]').forEach(function (box) { box.checked = false; });
        }
        function selectedCompanies() {
            return Array.from(document.querySelectorAll('[data-party-assignments] [data-company-box]:checked')).map(function (box) { return box.value; });
        }
        document.addEventListener('party-companies-changed', function (event) { applyCompanies(event.detail.companyIds || []); });
        applyCompanies(selectedCompanies());
    });
});
</script>
