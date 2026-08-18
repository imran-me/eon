<div id="rejectModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-lg shadow-lg z-50">
        <div class="modal-content py-4 px-6">
            <div class="modal-header flex justify-between items-center pb-3 border-bottom mb-3">
                <h4 class="font-semibold text-danger"><i class="fas fa-times-circle mr-2"></i>Reject Request</h4>
                <button class="modal-close-reject"><i class="fas fa-times"></i></button>
            </div>
            <input type="hidden" id="rejectRequestId">
            <div class="mb-3">
                <label class="form-label fw-bold text-sm">Rejection Reason <span class="text-danger">*</span></label>
                <textarea id="reject_remarks" rows="4" class="form-control" placeholder="Explain why this request is being rejected..."></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary modal-close-reject">Cancel</button>
                <button id="rejectSubmit" type="button" class="btn btn-danger">Reject</button>
            </div>
        </div>
    </div>
</div>
