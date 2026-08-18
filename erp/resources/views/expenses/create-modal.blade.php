<div id="createModal" class="modal fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
    <div class="modal-container bg-white w-full rounded-xl shadow-2xl z-50 overflow-y-auto pointer-events-auto" style="max-width:860px; max-height:90vh">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 rounded-t-xl sticky top-0 z-10" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%)">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(255,255,255,0.2)">
                    <i class="fas fa-file-invoice-dollar text-sm" style="color:#fff"></i>
                </div>
                <h3 class="font-semibold text-lg" style="color:#fff">Add New Expense</h3>
            </div>
            <button class="modal-close-create w-8 h-8 flex items-center justify-center rounded-lg transition duration-150" style="background:rgba(255,255,255,0.2); color:#fff">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5">
            <form class="closest" id="createForm" action="{{ route('role.expenses.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" enctype="multipart/form-data">
                @csrf

                {{-- ═══ 1. CLASSIFICATION ═══ --}}
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-1 h-5 rounded" style="background:#2563eb"></div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Classification</span>
                    </div>
                    @include('expenses.partials.classification', ['prefix' => 'create'])
                    <p id="create_expense_category_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a category</p>
                    {{-- Title is no longer asked for: the cost lines below already
                         carry a description each, and typing a title as well meant
                         saying the same thing twice.

                         The field stays as a hidden input rather than being deleted.
                         expenses.title is NOT NULL and is read by the list, the print
                         slip, the printed list and both expense reports, so the server
                         derives it from the first cost line — see
                         ExpenseController::resolveExpenseTitle(). Keeping the element
                         also keeps the page's JS honest: it does
                         $('#create_title').val().trim(), which throws on undefined the
                         moment the element is gone. --}}
                    <input type="hidden" id="create_title" name="title" value="">
                </div>

                {{-- ═══ 2. AMOUNT & DATE ═══ --}}
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-1 h-5 rounded" style="background:#0d9488"></div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Amount &amp; Date</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label for="create_expense_date" class="block text-sm font-semibold text-gray-700 mb-1.5">Date <span class="text-red-500">*</span></label>
                            <input type="date" id="create_expense_date" name="expense_date" value="{{ date('Y-m-d') }}"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a date</p>
                        </div>
                        <div>
                            <label for="create_amount" class="block text-sm font-semibold text-gray-700 mb-1.5">Amount</label>
                            {{-- Read-only, and NOT submitted. The amount is the sum of the cost
                                 lines below — this only shows it where it is expected. It used
                                 to be a typeable field whose value the server never read, so a
                                 figure typed here with no lines saved a ৳0 expense. --}}
                            <input type="text" id="create_amount" readonly tabindex="-1" value="0.00"
                                class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm font-bold text-gray-800 cursor-default">
                            <p class="text-[11px] text-gray-500 mt-1"><i class="fas fa-circle-info text-gray-400"></i> Adds up the cost lines below.</p>
                        </div>
                        <div>
                            <label for="create_reference" class="block text-sm font-semibold text-gray-700 mb-1.5">Reference No.</label>
                            <input type="text" id="create_reference" name="reference"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm"
                                placeholder="Optional">
                        </div>
                    </div>
                    @include('expenses.partials.cost-lines', ['prefix' => 'create'])
                </div>

                {{-- ═══ 3. PAYMENT SOURCE ═══ --}}
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-1 h-5 rounded" style="background:#7c3aed"></div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Source</span>
                    </div>
                    @include('expenses.partials.payment-source', ['prefix' => 'create'])
                </div>

                {{-- ═══ 4. ATTACHMENT & NOTES ═══ --}}
                <div class="mb-2">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-1 h-5 rounded" style="background:#6b7280"></div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Attachment &amp; Notes (Optional)</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="create_attachment" class="block text-sm font-semibold text-gray-700 mb-1.5">Attachment</label>
                            <input type="file" id="create_attachment" name="attachment"
                                class="block w-full text-sm text-gray-600 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-teal-500 transition duration-150 file:mr-3 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                        </div>
                        <div>
                            <label for="create_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Notes</label>
                            <textarea id="create_description" name="description" rows="2" placeholder="Additional details..."
                                class="w-full rounded-lg border border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 px-3 py-2 text-sm text-gray-700 outline-none resize-none transition"></textarea>
                        </div>
                    </div>
                    {{-- Inactive means "recorded but not posted" — no journal is written.
                         Rare, so it sits out of the way rather than beside the money. --}}
                    <div class="mt-4 max-w-[220px]">
                        <label for="create_status" class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select id="create_status" name="status"
                            class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm" style="width:100%">
                            <option value="1">Active — posts to the ledger</option>
                            <option value="0">Inactive — no journal</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="modal-footer flex justify-end gap-2 px-4 sm:px-6 py-4 border-t border-gray-200 flex-shrink-0">
            <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition modal-close-create">
                Cancel
            </button>
            <button id="createSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition">
                Submit
            </button>
        </div>
    </div>
    </div>
</div>
