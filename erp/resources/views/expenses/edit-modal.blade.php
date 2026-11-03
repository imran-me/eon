<div id="editModal" class="modal fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
    <div class="modal-container expense-entry-modal bg-white w-full rounded-xl shadow-2xl z-50 overflow-hidden pointer-events-auto flex flex-col" style="max-width:1280px; max-height:92vh">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 rounded-t-xl sticky top-0 z-10" style="background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%)">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(255,255,255,0.2)">
                    <i class="fas fa-pen text-sm" style="color:#fff"></i>
                </div>
                <h3 class="font-semibold text-lg" style="color:#fff">Edit Expense</h3>
            </div>
            <button class="modal-close-edit w-8 h-8 flex items-center justify-center rounded-lg transition duration-150" style="background:rgba(255,255,255,0.2); color:#fff">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 flex-1 min-h-0 overflow-y-auto expense-modal-scroll">
            <form id="editForm" method="POST" enctype="multipart/form-data"
                action="{{ route('role.expenses.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'expense' => 1]) }}">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id">

                {{-- ═══ 1. EXPENSE DETAILS ═══ --}}
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-1 h-5 rounded" style="background:#2563eb"></div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Expense Details</span>
                    </div>
                    @include('expenses.partials.classification', ['prefix' => 'edit'])
                    <p id="edit_expense_category_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a category</p>
                    <div class="mt-4">
                        {{-- Title, Date and Amount were missing from this modal entirely,
                             while the list page's JS filled and validated them — so
                             #edit_title.val() came back undefined and the Update button
                             threw before it ever posted. Same fields as the create modal
                             now, in the same order. --}}
                        {{-- Hidden, like the create modal — but it still CARRIES the
                             existing title, which is the point. Editing an old expense
                             that was given a real title must not silently rewrite it to
                             the first cost line; resolveExpenseTitle() keeps whatever
                             arrives here and only derives one when it is blank. --}}
                        <input type="hidden" id="edit_title" name="title" value="">
                        <input type="hidden" id="edit_amount" value="0.00">
                    </div>
                </div>

                {{-- ═══ 2. EXPENSE BREAKDOWN ═══ --}}
                <div class="mb-5">
                    @include('expenses.partials.cost-lines', ['prefix' => 'edit'])
                </div>

                {{-- ═══ 3. PAYMENT SOURCE ═══ --}}
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-1 h-5 rounded" style="background:#7c3aed"></div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Source</span>
                    </div>
                    @include('expenses.partials.payment-source', ['prefix' => 'edit'])
                </div>

                {{-- ═══ 4. OPTIONAL INFORMATION ═══ --}}
                <div class="mb-2">
                    <details id="edit_additional_information" class="rounded-xl border border-gray-200 bg-gray-50/60">
                        <summary class="cursor-pointer select-none px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">
                            <i class="fas fa-circle-plus mr-2 text-gray-400"></i>Additional Information (Optional)
                        </summary>
                    <div class="exp-extra-details border-0 border-t rounded-none">
                        <div>
                            <label for="edit_attachment" class="block text-sm font-semibold text-gray-700 mb-1.5">Attachment</label>
                            <div class="flex items-center gap-3">
                                <img id="preview_attc" src="" alt="Current attachment" class="rounded-lg border border-gray-200 object-cover hidden" style="width:52px; height:52px">
                                <input type="file" id="edit_attachment" name="attachment"
                                    class="block w-full text-sm text-gray-600 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-teal-500 transition duration-150 file:mr-3 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                            </div>
                        </div>
                        <div>
                            <label for="edit_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Notes</label>
                            <textarea id="edit_description" name="description" rows="2" placeholder="Additional details..."
                                class="w-full rounded-lg border border-gray-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 px-3 py-2 text-sm text-gray-700 outline-none resize-none transition"></textarea>
                        </div>
                    </div>
                    </details>
                </div>

            </form>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-100 sticky bottom-0">
            <button type="button" class="modal-close-edit px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition duration-150">
                Cancel
            </button>
            <button data-action="{{ route('role.expenses.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'expense' => 1]) }}"
                id="editSubmit" type="button"
                class="px-5 py-2 text-sm font-semibold text-white rounded-lg transition duration-150 inline-flex items-center gap-2" style="background:#0f766e">
                <i class="fas fa-save"></i>Update Expense
            </button>
        </div>

    </div>
    </div>
</div>
