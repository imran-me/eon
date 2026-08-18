{{-- Detail View Modal — body is JS-populated by vsShowDetail() --}}
<div id="vsDetailModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto">
    <div class="w-full max-w-[760px] bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-blue-700 to-blue-500 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h2 id="vsdTitle" class="text-sm font-bold text-white truncate">Voucher Details</h2>
                <p id="vsdSubtitle" class="text-xs text-blue-100 mt-0.5"></p>
            </div>
            <button onclick="vsCloseDetail()" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center flex-shrink-0">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div id="vsdBody" class="p-5 max-h-[calc(100vh-120px)] overflow-y-auto">
            <div class="text-center py-12 text-slate-400">
                <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>
                Loading...
            </div>
        </div>
    </div>
</div>
