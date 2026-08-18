<style>
#disburseModal { display:none;align-items:center;justify-content:center; }
#disburseModal:not(.hidden) { display:flex; }
#disburseModal .dm-overlay { position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(3px); }
#disburseModal .dm-card {
    position:relative;width:100%;max-width:500px;border-radius:16px;
    background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.25);
    overflow:hidden;max-height:92vh;display:flex;flex-direction:column;
}
#disburseModal .dm-head {
    background:linear-gradient(135deg,#1e40af 0%,#3b82f6 100%);
    padding:18px 20px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}
#disburseModal .dm-head-icon {
    width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.2);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
#disburseModal .dm-close {
    width:30px;height:30px;border-radius:8px;border:none;
    background:rgba(255,255,255,.18);color:#fff;cursor:pointer;
    display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0;
}
#disburseModal .dm-close:hover { background:rgba(255,255,255,.32); }
#disburseModal .dm-summary {
    margin:16px 16px 0;border-radius:12px;
    background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1px solid #bfdbfe;
    padding:14px 16px;display:flex;align-items:center;gap:12px;flex-shrink:0;
}
#disburseModal .dm-avatar {
    width:44px;height:44px;border-radius:50%;background:#2563eb;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
#disburseModal .dm-body { padding:16px;overflow-y:auto;flex:1; }
#disburseModal .dm-label {
    display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;letter-spacing:.3px;
}
#disburseModal .dm-input {
    width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:9px 12px;
    font-size:13px;color:#111827;background:#fafafa;transition:border-color .15s,box-shadow .15s;outline:none;
}
#disburseModal .dm-input:focus { border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12);background:#fff; }
#disburseModal .dm-input-prefix {
    display:flex;border:1.5px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fafafa;
    transition:border-color .15s,box-shadow .15s;
}
#disburseModal .dm-input-prefix:focus-within { border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12);background:#fff; }
#disburseModal .dm-prefix-sym {
    padding:9px 11px;background:#f3f4f6;border-right:1.5px solid #e5e7eb;
    font-size:13px;font-weight:700;color:#6b7280;white-space:nowrap;display:flex;align-items:center;
}
#disburseModal .dm-prefix-input {
    flex:1;border:none;background:transparent;padding:9px 12px;font-size:13px;color:#111827;outline:none;
}
#disburseModal .dm-attach {
    border:2px dashed #d1d5db;border-radius:10px;padding:11px 14px;
    display:flex;align-items:center;gap:8px;cursor:pointer;background:#fafafa;transition:border-color .15s,background .15s;
}
#disburseModal .dm-attach:hover { border-color:#3b82f6;background:#eff6ff; }
#disburseModal .dm-foot {
    padding:12px 16px;border-top:1px solid #f3f4f6;background:#f9fafb;
    display:flex;justify-content:flex-end;gap:8px;flex-shrink:0;
}
#disburseModal .dm-btn-cancel {
    padding:8px 18px;border-radius:9px;border:1.5px solid #d1d5db;
    background:#fff;color:#374151;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s;
}
#disburseModal .dm-btn-cancel:hover { background:#f3f4f6;border-color:#9ca3af; }
#disburseModal .dm-btn-submit {
    padding:8px 20px;border-radius:9px;border:none;
    background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;
    font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;
    display:flex;align-items:center;gap:6px;
}
#disburseModal .dm-btn-submit:hover { background:linear-gradient(135deg,#15803d,#16a34a);transform:translateY(-1px);box-shadow:0 4px 12px rgba(22,163,74,.3); }
</style>

<div id="disburseModal" class="modal fixed inset-0 z-50 hidden">
    <div class="dm-overlay"></div>

    <div class="dm-card mx-3">

        {{-- Header --}}
        <div class="dm-head">
            <div class="d-flex align-items-center gap-3">
                <div class="dm-head-icon">
                    <i class="fas fa-money-bill-wave text-white" style="font-size:17px;"></i>
                </div>
                <div>
                    <div class="text-white fw-bold" style="font-size:15px;line-height:1.2;">Record Disbursement</div>
                    <div class="text-white opacity-75" style="font-size:11px;">Process payment to employee</div>
                </div>
            </div>
            <button class="dm-close modal-close-disburse">
                <i class="fas fa-times" style="font-size:12px;"></i>
            </button>
        </div>

        {{-- Summary --}}
        <div class="dm-summary">
            <div class="dm-avatar">
                <i class="fas fa-user text-white" style="font-size:17px;"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-bold text-dark text-truncate" id="disburse_employee_name" style="font-size:14px;">—</div>
                <div style="font-size:12px;color:#6b7280;" id="disburse_request_type">—</div>
            </div>
            <div class="text-end flex-shrink-0 ms-2">
                <div style="font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Requested</div>
                <div class="fw-bold" style="font-size:20px;color:#2563eb;line-height:1.2;">৳<span id="disburse_requested_amount">—</span></div>
            </div>
        </div>

        {{-- Form --}}
        <div class="dm-body">
            <form id="disburseForm" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="disburseRequestId">

                {{-- Payment Method --}}
                <div class="mb-3">
                    <label class="dm-label">
                        <i class="fas fa-credit-card me-1" style="color:#6b7280;"></i>
                        Payment Method <span class="text-danger">*</span>
                    </label>
                    <div class="dm-input-prefix">
                        <span class="dm-prefix-sym"><i class="fas fa-wallet" style="font-size:12px;"></i></span>
                        <select name="payment_method" class="dm-prefix-input" required style="cursor:pointer;">
                            @foreach (\App\Models\RequestDisbursement::PAYMENT_METHODS as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Amount --}}
                <div class="mb-3">
                    <label class="dm-label">
                        <i class="fas fa-taka-sign me-1" style="color:#6b7280;"></i>
                        Disbursed Amount <span class="text-danger">*</span>
                    </label>
                    <div class="dm-input-prefix">
                        <span class="dm-prefix-sym">৳</span>
                        <input type="number" name="disbursed_amount" id="disburse_amount"
                            step="0.01" min="0.01" placeholder="0.00" required class="dm-prefix-input">
                    </div>
                </div>

                {{-- Note --}}
                <div class="mb-3">
                    <label class="dm-label">
                        <i class="fas fa-sticky-note me-1" style="color:#6b7280;"></i>
                        Note <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <textarea name="note" rows="2" class="dm-input"
                        placeholder="Add a note..." style="resize:none;"></textarea>
                </div>

                {{-- Attachment --}}
                <div>
                    <label class="dm-label">
                        <i class="fas fa-paperclip me-1" style="color:#6b7280;"></i>
                        Attachment <span class="text-muted fw-normal">(PDF/Image, max 5MB)</span>
                    </label>
                    <div class="dm-attach" onclick="document.getElementById('disburse_attachment').click()">
                        <i class="fas fa-cloud-upload-alt" style="color:#9ca3af;font-size:15px;"></i>
                        <span id="disburse_file_label" style="font-size:12px;color:#6b7280;">Click to upload file...</span>
                    </div>
                    <input type="file" name="attachment" id="disburse_attachment" class="d-none"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onchange="document.getElementById('disburse_file_label').textContent = this.files[0]?.name || 'Click to upload file...'">
                </div>
            </form>
        </div>

        {{-- Footer --}}
        <div class="dm-foot">
            <button type="button" class="dm-btn-cancel modal-close-disburse">
                <i class="fas fa-times me-1" style="font-size:11px;"></i> Cancel
            </button>
            <button id="disburseSubmit" type="button" class="dm-btn-submit">
                <i class="fas fa-check-circle" style="font-size:13px;"></i>
                Confirm Disbursement
            </button>
        </div>

    </div>
</div>
