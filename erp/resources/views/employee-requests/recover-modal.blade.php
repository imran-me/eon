<div id="recoverModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-lg shadow-lg z-50">
        <div class="modal-content py-4 px-6">
            <div class="modal-header flex justify-between items-center pb-3 border-bottom mb-3">
                <h4 class="font-semibold"><i class="fas fa-undo mr-2 text-warning"></i>Add Recovery Installment</h4>
                <button class="modal-close-recover"><i class="fas fa-times"></i></button>
            </div>

            <div class="mb-3 p-3 bg-light rounded">
                <div class="d-flex justify-content-between text-sm">
                    <span class="text-muted">Total Amount:</span>
                    <strong id="recover_total_amount">—</strong>
                </div>
                <div class="d-flex justify-content-between text-sm mt-1">
                    <span class="text-muted">Already Recovered:</span>
                    <strong id="recover_recovered_amount" class="text-success">—</strong>
                </div>
                <div class="d-flex justify-content-between text-sm mt-1">
                    <span class="text-muted">Remaining Balance:</span>
                    <strong id="recover_remaining_balance" class="text-danger">—</strong>
                </div>
            </div>

            <input type="hidden" id="recoverRequestId">

            <div class="mb-3">
                <label class="form-label fw-bold text-sm">Deduction Amount <span class="text-danger">*</span></label>
                <input type="number" id="recover_deducted_amount" name="deducted_amount" step="0.01" min="0.01"
                    class="form-control" placeholder="0.00" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-sm">Deduction Date <span class="text-danger">*</span></label>
                <input type="date" id="recover_deducted_at" name="deducted_at" class="form-control"
                    value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-sm">Note</label>
                <textarea id="recover_note" name="note" rows="2" class="form-control"
                    placeholder="e.g. Deducted from May 2026 payslip"></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary modal-close-recover">Cancel</button>
                <button id="recoverSubmit" type="button" class="btn btn-warning">
                    <i class="fas fa-undo mr-1"></i>Record Recovery
                </button>
            </div>
        </div>
    </div>
</div>
