@if(!\App\Services\VendorClassificationService::installed())
<div class="bg-amber-50 rounded-xl border border-amber-200 overflow-hidden" data-vendor-classification-setup hidden>
    <div class="px-6 py-4 flex items-start gap-3">
        <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center"><i class="fas fa-triangle-exclamation text-xs"></i></span>
        <div>
            <h2 class="text-sm font-semibold text-amber-900">Construction / Interior Classification is not installed</h2>
            <p class="text-xs text-amber-700 mt-1">The vendor-type database migration is pending. No selection has been lost; this setup must exist before project-specific options can be saved.</p>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-vendor-classification-setup]').forEach(function (root) {
        function selectedCompanies() {
            return Array.from(document.querySelectorAll('[data-party-assignments] [data-company-box]:checked')).map(function (box) { return String(box.value); });
        }
        function apply(ids) { root.hidden = !(ids.includes('3') || ids.includes('6')); }
        document.addEventListener('party-companies-changed', function (event) {
            apply((event.detail.companyIds || []).map(String));
        });
        apply(selectedCompanies());
    });
});
</script>
@elseif(($vendorTypes ?? collect())->isNotEmpty())
@php
    $chosenType = (int) old('vendor_type_id', $selectedVendorType ?? 0);
    $chosenCategories = collect(old('vendor_category_ids', $selectedVendorCategories ?? []))->map(fn ($id) => (int) $id)->all();
@endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" data-vendor-classification hidden>
    <div class="px-6 py-4 border-b border-gray-100 flex items-start gap-3">
        <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center text-xs font-bold">2</span>
        <div>
            <h2 class="text-sm font-semibold text-gray-700">Construction / Interior Classification</h2>
            <p class="text-xs text-gray-400 mt-1">Shown because EPAL Constructions or Wood Art Interiors is selected.</p>
        </div>
    </div>
    <div class="p-6 space-y-5">
        <div>
            <label for="vendor_type_id" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Vendor type</label>
            <select id="vendor_type_id" name="vendor_type_id" class="w-full px-4 py-2.5 text-sm rounded-lg border border-gray-200 bg-white focus:ring-2 focus:ring-blue-500">
                <option value="">Select vendor type</option>
                @foreach($vendorTypes as $type)
                    <option value="{{ $type->id }}" @selected($chosenType === (int) $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
            @error('vendor_type_id')<p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Work / supply categories</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" data-vendor-category-list>
                @foreach($vendorCategories as $category)
                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm cursor-pointer hover:bg-gray-50"
                           data-vendor-category data-type-id="{{ $category->vendor_type_id }}">
                        <input type="checkbox" name="vendor_category_ids[]" value="{{ $category->id }}" @checked(in_array((int) $category->id, $chosenCategories, true))>
                        <span>{{ $category->name }}</span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-2" data-vendor-category-empty>Select a vendor type to see its categories.</p>
            @error('vendor_category_ids')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
            @error('vendor_category_ids.*')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-vendor-classification]').forEach(function (root) {
        var type = root.querySelector('[name="vendor_type_id"]');
        var rows = Array.from(root.querySelectorAll('[data-vendor-category]'));
        var empty = root.querySelector('[data-vendor-category-empty]');
        function refresh() {
            var visible = 0;
            rows.forEach(function (row) {
                var show = type.value !== '' && row.dataset.typeId === type.value;
                row.hidden = !show;
                if (!show) row.querySelector('input').checked = false;
                if (show) visible++;
            });
            empty.hidden = visible > 0;
        }
        function applyCompanies(ids) {
            var selected = ids.map(String);
            var show = selected.includes('3') || selected.includes('6');
            root.hidden = !show;
            if (!show) {
                type.value = '';
                rows.forEach(function (row) { row.querySelector('input').checked = false; });
            }
            refresh();
        }
        function selectedCompanies() {
            return Array.from(document.querySelectorAll('[data-party-assignments] [data-company-box]:checked')).map(function (box) { return box.value; });
        }
        type.addEventListener('change', refresh);
        document.addEventListener('party-companies-changed', function (event) { applyCompanies(event.detail.companyIds || []); });
        applyCompanies(selectedCompanies());
    });
});
</script>
@endif
