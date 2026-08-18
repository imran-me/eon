<div id="viewOfficerModal" class="fixed inset-0 z-[9000] bg-slate-950/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-auto hidden">
    <div class="w-full max-w-xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-slate-700 to-slate-600 px-5 py-4 text-white">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-user-shield"></i></div>
            <div class="flex-1">
                <h3 class="text-sm font-bold">Officer Profile</h3>
                <p class="text-xs opacity-70">Flight operations assignment details</p>
            </div>
            <button type="button" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-view"><i class="fas fa-times text-xs"></i></button>
        </div>

        <div class="p-5 space-y-4">
            {{-- Profile summary --}}
            <div class="flex items-center gap-4 rounded-xl bg-gradient-to-br from-slate-50 to-slate-100 p-4">
                <div class="h-14 w-14 rounded-full bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center text-white text-xl flex-shrink-0 shadow-md">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div id="view_officer_name" class="text-base font-bold text-slate-900 truncate">-</div>
                    <div id="view_officer_status" class="mt-1 text-xs text-slate-500">-</div>
                </div>
            </div>

            {{-- Detail cards --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-blue-50 p-3">
                    <div class="mb-1 text-[10px] font-bold uppercase tracking-wide text-blue-600"><i class="fas fa-plane mr-1"></i>Airline / Desk</div>
                    <div id="view_officer_airline" class="text-sm font-semibold text-slate-800">-</div>
                </div>
                <div class="rounded-xl bg-violet-50 p-3">
                    <div class="mb-1 text-[10px] font-bold uppercase tracking-wide text-violet-600"><i class="fas fa-briefcase mr-1"></i>Work Roles</div>
                    <div id="view_officer_roles" class="text-sm font-semibold text-slate-800">-</div>
                </div>
                <div class="rounded-xl bg-emerald-50 p-3">
                    <div class="mb-1 text-[10px] font-bold uppercase tracking-wide text-emerald-600"><i class="fas fa-phone mr-1"></i>Contact</div>
                    <div id="view_officer_contact" class="text-sm font-semibold text-slate-800">-</div>
                </div>
                <div class="rounded-xl bg-amber-50 p-3">
                    <div class="mb-1 text-[10px] font-bold uppercase tracking-wide text-amber-600"><i class="fas fa-star mr-1"></i>Experience</div>
                    <div id="view_officer_experience" class="text-sm font-semibold text-slate-800">-</div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
            <button type="button" class="rounded-xl border bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 cursor-pointer modal-close-view">Close</button>
        </div>
    </div>
</div>
