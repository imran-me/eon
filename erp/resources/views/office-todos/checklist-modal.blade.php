<div id="checklistModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">
        <div class="bg-blue-600 px-6 py-4 flex justify-between items-center rounded-t-lg flex-shrink-0">
            <h3 class="text-white text-lg font-semibold" style="color:white">
                <i class="fas fa-list-check mr-2"></i>
                <span id="checklist_modal_title">Checklist</span>
            </h3>
            <button id="closeChecklistModal" class="text-white hover:text-gray-200" style="color:white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-6 overflow-y-auto flex-1">
            <div id="checklist_progress_wrap" class="mb-4 hidden">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                        <i class="fas fa-list-check mr-1 text-blue-500"></i>Checklist
                    </span>
                    <span id="checklist_progress_count" class="text-xs font-semibold text-gray-500"></span>
                </div>
                <div style="height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
                    <div id="checklist_progress_bar"
                        style="height:100%;width:0%;background:#2563eb;border-radius:999px;transition:width .25s ease;"></div>
                </div>
            </div>

            <div id="checklist_loading" class="text-center py-8 text-gray-400">
                <i class="fas fa-spinner fa-spin text-2xl"></i>
            </div>
            <div id="checklist_empty" class="text-center py-8 text-gray-400 hidden">
                <i class="fas fa-clipboard-list text-3xl mb-2 block opacity-40"></i>
                No checklist items found.
            </div>
            <ul id="checklist_items_list" class="space-y-2 hidden"></ul>
        </div>

        <div class="px-6 pb-4 flex-shrink-0 border-t pt-4 flex items-center justify-between">
            <div id="checklist_progress_text" class="text-sm text-gray-500"></div>
            <button type="button" id="cancelChecklist"
                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm">Close</button>
        </div>
    </div>
</div>
