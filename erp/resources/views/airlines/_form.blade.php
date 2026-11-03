<div class="mb-4">
    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
    <input type="text"
           id="name"
           name="name"
            value="{{ old('name', $airline?->name ?? '') }}"
           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
           placeholder="Enter airline name">
    @error('name')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

{{-- Logo (owner, 2026-10-01): uploaded here instead of copied onto the
     server by hand. Airlines that already have a hand-placed mark keep it
     until a logo is uploaded for them. --}}
<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700 mb-2">Logo <span style="font-weight:400;color:#9ca3af;">— optional · PNG, JPG or WebP, up to 2 MB</span></label>
    <div style="display:flex;align-items:center;gap:16px;">
        <div style="width:120px;height:64px;border:1px dashed #cbd5e1;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
            <img id="airlineLogoPreview" alt="" style="max-width:108px;max-height:54px;object-fit:contain;display:none;">
            <span id="airlineLogoNone" style="font-size:11px;color:#9ca3af;">No logo</span>
        </div>
        <div style="min-width:0;">
            <input type="file" name="logo" id="airlineLogoInput" accept="image/png,image/jpeg,image/webp" class="text-sm">
            <p id="airlineLogoSource" style="margin-top:4px;font-size:12px;color:#64748b;"></p>
            @if ($airline && $airline->uploadedLogoUrl())
                <label style="display:inline-flex;align-items:center;gap:6px;margin-top:4px;font-size:12px;color:#b91c1c;cursor:pointer;">
                    <input type="checkbox" name="remove_logo" value="1" id="airlineLogoRemove"> Remove the uploaded logo
                </label>
            @endif
        </div>
    </div>
    @error('logo')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
<script>
(function () {
@include('contract-flights.partials.airline-brands-script')
    const BASE = @json(asset('airline-logos'));
    const uploaded = @json($airline?->uploadedLogoUrl());
    const img = document.getElementById('airlineLogoPreview'), none = document.getElementById('airlineLogoNone');
    const note = document.getElementById('airlineLogoSource'), name = document.getElementById('name'), file = document.getElementById('airlineLogoInput');
    const remove = document.getElementById('airlineLogoRemove');
    const show = (src, text) => {
        img.onerror = () => { img.style.display = 'none'; none.style.display = ''; note.textContent = 'No logo yet — upload one.'; };
        if (src) { img.src = src; img.style.display = ''; none.style.display = 'none'; } else { img.style.display = 'none'; none.style.display = ''; }
        note.textContent = text;
    };
    function current() {
        if (file.files && file.files[0]) return;
        if (uploaded && !(remove && remove.checked)) return show(uploaded, 'Uploaded logo. Choose a file to replace it.');
        const b = airlineBrand(name ? name.value : '');
        if (b.slug && !String(b.slug).startsWith('uploaded/')) return show(BASE + '/' + b.slug + '.png', 'Built-in logo (already on the server). Upload one to replace it.');
        show(null, 'No logo yet — upload one.');
    }
    file.addEventListener('change', () => {
        const f = file.files && file.files[0];
        if (!f) return current();
        const r = new FileReader();
        r.onload = e => show(e.target.result, 'New logo — saved when you press Save.');
        r.readAsDataURL(f);
    });
    if (name) name.addEventListener('input', current);
    if (remove) remove.addEventListener('change', current);
    current();
})();
</script>

<div class="mb-4 flex items-center gap-3">
    <input type="hidden" name="status" value="0">
    <input type="checkbox"
           id="status"
           name="status"
           value="1"
           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
           {{ old('status', isset($airline) ? $airline->status : true) ? 'checked' : '' }}>
    <label for="status" class="text-sm font-medium text-gray-700">Active</label>
    @error('status')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>