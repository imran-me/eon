<div id="deleteModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-red-600 to-rose-500 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0">
                <i class="fas fa-trash"></i>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-bold text-white">Confirm Delete</h3>
                <p class="text-xs text-red-100 mt-0.5">This action cannot be undone</p>
            </div>
            <button class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-delete">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div class="p-5">
            <p class="text-sm text-slate-700">Are you sure you want to delete <span id="deleteName" class="font-semibold"></span>?</p>
            <p class="text-red-500 mt-2 text-xs">This action cannot be undone.</p>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
            <button class="rounded-xl border border-slate-200 bg-slate-100 text-slate-700 px-4 py-2 text-sm font-semibold cursor-pointer modal-close-delete">Cancel</button>
            <button id="confirmDeleteBtn" data-action="{{ route('role.ticket-sales.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_sale' => 1]) }}" class="rounded-xl bg-red-600 text-white px-4 py-2 text-sm font-semibold cursor-pointer flex items-center gap-1.5">
                <i class="fas fa-trash text-xs"></i> Delete
            </button>
        </div>
    </div>
</div>
