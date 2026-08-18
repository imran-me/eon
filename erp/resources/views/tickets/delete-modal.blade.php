<div id="deleteModal" class="tk-create-overlay modal-backdrop hidden">
    <div class="tk-create-box" style="max-width:420px;">
        <div class="tk-create-head" style="background:linear-gradient(135deg,#dc2626,#b91c1c);">
            <span style="font-size:18px;">🗑️</span>
            <span class="tk-create-title">Confirm Delete</span>
            <button type="button" class="tk-create-close modal-close-delete">✕</button>
        </div>
        <div class="tk-create-body" style="padding:20px 24px;">
            <p style="font-size:13.5px;color:#1a2035;margin:0 0 6px;">
                Are you sure you want to delete <strong><span id="deleteName"></span></strong>?
            </p>
            <p style="font-size:12px;color:#dc2626;margin:0;">This action cannot be undone.</p>
        </div>
        <div class="tk-create-foot">
            <button type="button" class="tk-btn tk-btn-ghost modal-close-delete">Cancel</button>
            <button id="confirmDeleteBtn"
                data-action="{{ route('role.tickets.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket' => 1]) }}"
                class="tk-btn" style="background:#dc2626;color:#fff;">
                <i class="fas fa-trash" style="font-size:11px;"></i> Delete
            </button>
        </div>
    </div>
</div>
