<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-3xl mx-auto rounded shadow-lg z-50">
        <div class="modal-content flex flex-col py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-2" style="border-bottom: 3px solid #e8e8e8; width: 100%">
                <h3 class="text-xl font-semibold">Add New Notice</h3>
                <button class="modal-close-create z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body overflow-y-auto mt-2" style="max-height: calc(90vh - 120px); scrollbar-width: thin;">
                <form class="closest" id="createForm" action="{{ route('role.notices.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4">
                        <div class="mb-2">
                            <label for="company_id" class="block text-gray-700 text-sm font-bold mb-2">Company</label>
                            <select id="company_id" name="company_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">Select Company</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Company</p>
                        </div>
                        <div class="mb-2">
                            <label for="department_id" class="block text-gray-700 text-sm font-bold mb-2">Department</label>
                            <select id="department_id" name="department_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">Select Department</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Department</p>
                        </div>
                        <div class="mb-2">
                            <label for="create_title" class="block text-gray-700 text-sm font-bold mb-2">Title</label>
                            <input type="text" id="create_title" name="title" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a Title">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Title</p>
                        </div>
                        <div class="mb-2">
                            <label for="publish_date" class="block text-gray-700 text-sm font-bold mb-2">Published Date</label>
                            <input type="date" id="publish_date" name="publish_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="{{ Carbon\Carbon::now()->format('Y-m-d') }}">
                        </div>
                        <div class="mb-2">
                            <label for="expiry_date" class="block text-gray-700 text-sm font-bold mb-2">Expiry Date</label>
                            <input type="date" id="expiry_date" name="expiry_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="{{ Carbon\Carbon::now()->format('Y-m-d') }}">
                        </div>
                        <div class="mb-2">
                            <label for="create_status" class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                            <select id="create_status" name="status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">Select Status</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Status</p>
                        </div>
                        <div class="col-span-2 mb-2">
                            <label for="create_description" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                            <textarea id="create_description" name="description" rows="3" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a Description"></textarea>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Description</p>
                        </div>

                        {{-- ── STYLE FIELDS ── --}}
                        <div class="col-span-2">
                            <div style="border-top:2px dashed #e2e8f0;padding-top:14px;margin-bottom:6px;">
                                <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-3">🎨 Card Appearance</p>
                            </div>
                        </div>

                        {{-- Icon --}}
                        <div class="mb-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Icon</label>
                            <input type="hidden" id="create_icon" name="icon" value="fas fa-bullhorn">
                            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;" id="create_icon_presets">
                                @php
                                $cardIcons = [
                                    'fas fa-bullhorn','fas fa-bell','fas fa-exclamation-triangle',
                                    'fas fa-thumbtack','fas fa-calendar-alt',
                                    'fas fa-briefcase','fas fa-bullseye','fas fa-clipboard-list',
                                    'fas fa-rocket','fas fa-lightbulb',
                                ];
                                @endphp
                                @foreach($cardIcons as $ci)
                                <button type="button" class="card-icon-btn" data-target="create_icon" data-icon="{{ $ci }}"
                                    style="height:38px;border:1.5px solid #e2e8f0;border-radius:8px;background:#f8fafc;cursor:pointer;transition:.15s;color:#6366f1;font-size:16px;"
                                    title="{{ $ci }}"><i class="{{ $ci }}"></i></button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Card Color --}}
                        <div class="mb-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Card Color <span class="text-gray-400 font-normal">(gradient preset)</span></label>
                            <input type="hidden" id="create_card_color" name="card_color" value="#f97316,#f59e0b">
                            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:7px;margin-bottom:10px;" id="create_gradient_presets">
                                @php
                                $gradients = [
                                    ['#f97316','#f59e0b'],['#ec4899','#f97316'],['#8b5cf6','#ec4899'],
                                    ['#3b82f6','#06b6d4'],['#10b981','#3b82f6'],['#ef4444','#f97316'],
                                    ['#6366f1','#8b5cf6'],['#14b8a6','#10b981'],['#f43f5e','#fb7185'],
                                    ['#0ea5e9','#6366f1'],
                                ];
                                @endphp
                                @foreach($gradients as $g)
                                <button type="button" class="gradient-preset-btn" data-target="create_card_color" data-value="{{ $g[0] }},{{ $g[1] }}"
                                    style="height:32px;border-radius:8px;border:2px solid transparent;cursor:pointer;transition:.15s;background:linear-gradient(135deg,{{ $g[0] }},{{ $g[1] }});"
                                    title="{{ $g[0] }} → {{ $g[1] }}"></button>
                                @endforeach
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div>
                                    <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">From</label>
                                    <input type="color" id="create_color_from" value="#f97316"
                                        style="width:48px;height:34px;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;padding:2px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">To</label>
                                    <input type="color" id="create_color_to" value="#f59e0b"
                                        style="width:48px;height:34px;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;padding:2px;">
                                </div>
                                <div id="create_gradient_preview" style="flex:1;height:34px;border-radius:8px;border:1.5px solid #e2e8f0;background:linear-gradient(135deg,#f97316,#f59e0b);margin-top:18px;"></div>
                            </div>
                        </div>

                        {{-- Text Color --}}
                        <div class="mb-2 col-span-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Text Color</label>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:8px;transition:.15s;" class="text-color-option" data-target="create_text_color" data-value="#ffffff">
                                    <input type="radio" name="create_text_color_radio" value="#ffffff" checked style="display:none;">
                                    <span style="width:18px;height:18px;border-radius:4px;background:#1e293b;border:1.5px solid #e2e8f0;display:inline-block;"></span>
                                    <span>White Text</span>
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:8px;transition:.15s;" class="text-color-option" data-target="create_text_color" data-value="#1e293b">
                                    <input type="radio" name="create_text_color_radio" value="#1e293b" style="display:none;">
                                    <span style="width:18px;height:18px;border-radius:4px;background:#ffffff;border:1.5px solid #e2e8f0;display:inline-block;"></span>
                                    <span>Dark Text</span>
                                </label>
                                <div style="display:flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:8px;">
                                    <label style="font-size:13px;margin:0;">Custom</label>
                                    <input type="color" id="create_text_color_picker" value="#ffffff"
                                        style="width:34px;height:26px;border:none;cursor:pointer;border-radius:4px;padding:2px;">
                                </div>
                                <input type="hidden" id="create_text_color" name="text_color" value="#ffffff">
                            </div>
                        </div>

                        {{-- Badge --}}
                        <div class="col-span-2 mb-2">
                            <div style="border-top:2px dashed #e2e8f0;padding-top:14px;margin-bottom:6px;">
                                <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-3">🏷️ Badge</p>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Badge Icon <span class="text-gray-400 font-normal">(Font Awesome)</span></label>
                                    <input type="hidden" id="create_badge_icon" name="badge_icon" value="fas fa-bell">
                                    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;" id="create_badge_icon_presets">
                                        @php
                                        $faIcons = [
                                            'fas fa-bell','fas fa-bullhorn','fas fa-exclamation-triangle',
                                            'fas fa-info-circle','fas fa-external-link-alt',
                                            'fas fa-star','fas fa-fire','fas fa-clock',
                                            'fas fa-thumbtack','fas fa-calendar-alt',
                                        ];
                                        @endphp
                                        @foreach($faIcons as $fi)
                                        <button type="button" class="badge-icon-btn" data-target="create_badge_icon" data-icon="{{ $fi }}"
                                            style="height:34px;border:1.5px solid #e2e8f0;border-radius:8px;background:#f8fafc;cursor:pointer;transition:.15s;color:#6366f1;font-size:14px;"
                                            title="{{ $fi }}"><i class="{{ $fi }}"></i></button>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Badge Label</label>
                                    <input type="text" id="create_badge_label" name="badge_label" value="REMINDER" maxlength="20"
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 mb-2"
                                        placeholder="e.g. REMINDER">
                                    <div style="display:flex;flex-wrap:wrap;gap:5px;">
                                        @foreach(['REMINDER','URGENT','NOTICE','ANNOUNCEMENT','UPDATE','INFO','DEADLINE','NEWS','ACHIEVEMENT'] as $lbl)
                                        <button type="button" class="badge-label-btn" data-target="create_badge_label" data-label="{{ $lbl }}"
                                            style="font-size:10px;font-weight:700;padding:3px 9px;border:1.5px solid #e2e8f0;border-radius:999px;background:#f8fafc;cursor:pointer;transition:.15s;color:#475569;">{{ $lbl }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Slide Image --}}
                        <div class="col-span-2 mb-2">
                            <div style="border-top:2px dashed #e2e8f0;padding-top:14px;margin-bottom:6px;">
                                <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-3">🖼️ Slide Image <span class="font-normal normal-case text-gray-400">(optional — person or greeting photo)</span></p>
                            </div>
                            <div style="display:flex;align-items:flex-start;gap:16px;">
                                <div id="create_img_preview_wrap" style="width:80px;height:80px;border-radius:50%;border:2px dashed #cbd5e1;background:#f8fafc;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="18" cy="11" r="6" fill="#cbd5e1"/>
                                        <path d="M4 32C4 24 10 20 18 20C26 20 32 24 32 32" fill="#cbd5e1"/>
                                    </svg>
                                </div>
                                <div style="flex:1;">
                                    <label for="create_slide_image" style="display:inline-flex;align-items:center;gap:7px;padding:7px 16px;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:12px;font-weight:600;color:#475569;background:#f8fafc;transition:.15s;" onmouseover="this.style.borderColor='#6366f1';this.style.color='#6366f1'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#475569'">
                                        <i class="fas fa-upload"></i> Choose Image
                                    </label>
                                    <input type="file" id="create_slide_image" name="slide_image" accept="image/*" style="display:none;">
                                    <p style="font-size:11px;color:#94a3b8;margin-top:6px;">JPG, PNG, GIF — max 2MB. Will appear as a circular avatar on the right side of the slide.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Live Preview --}}
                        <div class="col-span-2 mb-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Live Preview</label>
                            <div id="create_card_preview" style="
                                background:linear-gradient(135deg,#f97316,#f59e0b);
                                border-radius:14px;padding:20px 24px;
                                display:flex;align-items:center;gap:18px;
                                min-height:90px;position:relative;overflow:hidden;
                            ">
                                <div id="prev_icon_wrap" style="width:52px;height:52px;background:rgba(0,0,0,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;color:white;"><i class="fas fa-bullhorn"></i></div>
                                <div style="flex:1;min-width:0;">
                                    <div id="prev_badge" style="display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:3px 11px;color:#fff;margin-bottom:6px;letter-spacing:.6px;">
                                        <i id="prev_badge_icon" class="fas fa-bell"></i>
                                        <span id="prev_badge_label">REMINDER</span>
                                    </div>
                                    <div id="prev_title" style="font-size:15px;font-weight:800;color:#ffffff;margin-bottom:3px;">Notice Title</div>
                                    <div id="prev_desc" style="font-size:12px;color:rgba(255,255,255,.8);">Notice description preview text will appear here.</div>
                                </div>
                                <div id="create_prev_img" style="width:60px;height:60px;border-radius:50%;overflow:hidden;border:2.5px solid rgba(255,255,255,.35);background:rgba(255,255,255,.1);flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                                    <svg width="38" height="38" viewBox="0 0 38 38" fill="none"><circle cx="19" cy="11" r="7" fill="rgba(255,255,255,0.35)"/><path d="M4 36C4 27 11 22 19 22C27 22 34 27 34 36" fill="rgba(255,255,255,0.25)"/><path d="M28 23C30 19 34 14 32 10C30.5 7 27 8.5 26 12C25 15.5 28 21 28 23Z" fill="rgba(255,255,255,0.3)"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    // Icon preview
    document.getElementById('create_icon').addEventListener('input', function(){
        var v = this.value || '📢';
        document.getElementById('create_icon_preview').textContent = v;
        document.getElementById('prev_icon_wrap').textContent = v;
    });
    // Icon presets
    document.querySelectorAll('.icon-preset-btn[data-target="create_icon"]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var icon = this.dataset.icon;
            document.getElementById('create_icon').value = icon;
            document.getElementById('create_icon_preview').textContent = icon;
            document.getElementById('prev_icon_wrap').textContent = icon;
        });
    });
    // Title preview
    document.getElementById('create_title').addEventListener('input', function(){
        document.getElementById('prev_title').textContent = this.value || 'Notice Title';
    });
    // Description preview
    document.getElementById('create_description').addEventListener('input', function(){
        document.getElementById('prev_desc').textContent = this.value || 'Notice description preview text will appear here.';
    });

    function updateCreateGradient(from, to){
        var grad = 'linear-gradient(135deg,' + from + ',' + to + ')';
        document.getElementById('create_card_color').value = from + ',' + to;
        document.getElementById('create_gradient_preview').style.background = grad;
        document.getElementById('create_card_preview').style.background = grad;
        // highlight active preset
        document.querySelectorAll('#create_gradient_presets .gradient-preset-btn').forEach(function(b){
            b.style.borderColor = 'transparent';
        });
    }
    // Gradient presets
    document.querySelectorAll('.gradient-preset-btn[data-target="create_card_color"]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var parts = this.dataset.value.split(',');
            document.getElementById('create_color_from').value = parts[0];
            document.getElementById('create_color_to').value = parts[1];
            updateCreateGradient(parts[0], parts[1]);
            document.querySelectorAll('#create_gradient_presets .gradient-preset-btn').forEach(function(b){ b.style.borderColor='transparent'; });
            this.style.borderColor = '#6366f1';
        });
    });
    // Custom color pickers
    document.getElementById('create_color_from').addEventListener('input', function(){
        updateCreateGradient(this.value, document.getElementById('create_color_to').value);
    });
    document.getElementById('create_color_to').addEventListener('input', function(){
        updateCreateGradient(document.getElementById('create_color_from').value, this.value);
    });

    // Text color options
    document.querySelectorAll('.text-color-option[data-target="create_text_color"]').forEach(function(lbl){
        lbl.addEventListener('click', function(){
            document.getElementById('create_text_color').value = this.dataset.value;
            document.getElementById('create_text_color_picker').value = this.dataset.value;
            applyCreateTextColor(this.dataset.value);
            document.querySelectorAll('.text-color-option[data-target="create_text_color"]').forEach(function(l){ l.style.borderColor='#e2e8f0'; l.style.background='white'; });
            this.style.borderColor = '#6366f1'; this.style.background = '#f5f3ff';
        });
    });
    document.getElementById('create_text_color_picker').addEventListener('input', function(){
        document.getElementById('create_text_color').value = this.value;
        applyCreateTextColor(this.value);
    });
    function applyCreateTextColor(color){
        document.getElementById('prev_title').style.color = color;
        document.getElementById('prev_desc').style.color = color;
    }
    // Badge icon presets
    document.querySelectorAll('.badge-icon-btn[data-target="create_badge_icon"]').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.getElementById('create_badge_icon').value = this.dataset.icon;
            document.getElementById('prev_badge_icon').className = this.dataset.icon;
            document.querySelectorAll('#create_badge_icon_presets .badge-icon-btn').forEach(function(b){ b.style.borderColor='#e2e8f0'; b.style.background='#f8fafc'; });
            this.style.borderColor = '#6366f1'; this.style.background = '#eef2ff';
        });
    });
    // Badge label presets
    document.querySelectorAll('.badge-label-btn[data-target="create_badge_label"]').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.getElementById('create_badge_label').value = this.dataset.label;
            document.getElementById('prev_badge_label').textContent = this.dataset.label;
            document.querySelectorAll('.badge-label-btn[data-target="create_badge_label"]').forEach(function(b){ b.style.borderColor='#e2e8f0'; b.style.color='#475569'; b.style.background='#f8fafc'; });
            this.style.borderColor='#6366f1'; this.style.color='#6366f1'; this.style.background='#eef2ff';
        });
    });
    document.getElementById('create_badge_label').addEventListener('input', function(){
        document.getElementById('prev_badge_label').textContent = this.value || 'REMINDER';
    });

    // Slide image preview
    document.getElementById('create_slide_image').addEventListener('change', function(){
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(e){
            var wrap = document.getElementById('create_img_preview_wrap');
            wrap.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
            // also update live preview image area
            var prev = document.getElementById('create_prev_img');
            if (prev) prev.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
        };
        reader.readAsDataURL(file);
    });

    // Init active state for first preset
    document.querySelector('#create_gradient_presets .gradient-preset-btn').style.borderColor = '#6366f1';
    document.querySelector('.text-color-option[data-target="create_text_color"]').style.borderColor = '#6366f1';
    document.querySelector('.text-color-option[data-target="create_text_color"]').style.background = '#f5f3ff';
    document.querySelector('#create_badge_icon_presets .badge-icon-btn').style.borderColor = '#6366f1';
    document.querySelector('#create_badge_icon_presets .badge-icon-btn').style.background = '#eef2ff';
    document.querySelector('.badge-label-btn[data-target="create_badge_label"]').style.borderColor='#6366f1';
    document.querySelector('.badge-label-btn[data-target="create_badge_label"]').style.color='#6366f1';
    document.querySelector('.badge-label-btn[data-target="create_badge_label"]').style.background='#eef2ff';
})();
</script>
