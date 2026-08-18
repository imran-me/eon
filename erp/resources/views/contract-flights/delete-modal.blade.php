<div id="deleteModal" class="fcm-overlay hidden modal-backdrop" style="align-items:center;">
    <div class="fcm-box" style="max-width:420px;">
        <div class="fcm-head" style="background:linear-gradient(135deg,#dc2626,#b91c1c);">
            <div class="fcm-head-ic"><i class="fas fa-trash"></i></div>
            <div>
                <div class="fcm-title">Confirm Delete</div>
            </div>
            <button type="button" class="fcm-close modal-close-delete">✕</button>
        </div>
        <div class="fcm-body" style="padding:20px 22px;">
            <p style="font-size:13.5px;color:#1a2035;margin:0 0 6px;">
                Are you sure you want to delete <strong><span id="deleteName"></span></strong>?
            </p>
            <p style="font-size:12px;color:#dc2626;margin:0;">This action cannot be undone.</p>
        </div>
        <div class="fcm-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost modal-close-delete">Cancel</button>
            <button id="confirmDeleteBtn" data-action="" class="fcm-btn" style="background:#dc2626;color:#fff;">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>
