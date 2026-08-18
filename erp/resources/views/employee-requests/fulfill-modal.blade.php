<div id="fulfillModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-lg shadow-lg z-50 overflow-y-auto" style="max-height:90vh">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3 border-bottom mb-3">
                <h3 class="text-lg font-semibold">✅ Mark as Fulfilled</h3>
                <button class="modal-close-fulfill"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-sm text-gray-600 mb-3">Submit your fulfillment note for this requirement.</p>
                <form id="fulfillForm" method="POST">
                    @csrf
                    <input type="hidden" id="fulfillRequestId">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-sm">Fulfillment Note</label>
                        <textarea name="fulfillment_note" id="fulfillNote" rows="3"
                            class="form-control" placeholder="Describe what you submitted / completed..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end gap-2 pt-3 border-top mt-2">
                <button type="button" class="btn btn-secondary modal-close-fulfill">Cancel</button>
                <button id="fulfillSubmit" type="button" class="btn btn-primary">Submit Fulfillment</button>
            </div>
        </div>
    </div>
</div>
