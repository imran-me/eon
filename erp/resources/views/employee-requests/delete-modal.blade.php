<div id="deleteModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-sm mx-auto rounded-lg shadow-lg z-50">
        <div class="modal-content py-4 px-6 text-center">
            <i class="fas fa-exclamation-triangle fa-2x text-warning mb-3"></i>
            <h4 class="font-semibold mb-2">Delete Record?</h4>
            <p class="text-muted text-sm mb-4">This will delete the record for <strong id="deleteName"></strong>. This action cannot be undone.</p>
            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-secondary modal-close-delete">Cancel</button>
                <button id="confirmDeleteBtn" type="button" class="btn btn-danger">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>
