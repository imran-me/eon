<div id="selfieModal" class="modal fixed inset-0 items-center justify-center z-50" style="display: none;">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-2xl mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 id="selfieModalTitle" class="text-xl font-semibold">Selfie Preview</h3>
                <button type="button" class="modal-close-selfie z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <img id="selfiePreview" src="" alt="Selfie Preview" class="w-full max-h-[70vh] object-contain rounded-md">
                </div>
            </div>
            <div class="modal-footer flex justify-end pt-4">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 modal-close-selfie">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
