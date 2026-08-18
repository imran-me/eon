<div id="deleteModal" class="budget-modal">
    <div class="budget-modal-card" style="max-width: 440px;">
        <div class="budget-modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
            <h3><i class="fas fa-triangle-exclamation mr-2"></i>Delete Budget</h3>
            <button type="button" class="close-btn modal-close-delete"><i class="fas fa-times"></i></button>
        </div>
        <div class="confirm-wrap">
            <div class="warn-icon"><i class="fas fa-trash"></i></div>
            <p class="mb-2 text-gray-800 font-medium">Delete budget for <span id="deleteName" class="font-bold"></span>?</p>
            <p class="text-sm text-gray-500">This action cannot be undone.</p>
        </div>
        <div class="budget-modal-footer">
            <button type="button" class="btn-cancel modal-close-delete">Cancel</button>
            <button type="button" id="confirmDeleteBtn" class="btn-delete-confirm"><i class="fas fa-trash mr-1"></i>Delete</button>
        </div>
    </div>
</div>