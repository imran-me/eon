<div id="createOfficerModal" class="fixed inset-0 z-[9000] bg-slate-950/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-auto hidden">
    <div class="w-full max-w-2xl bg-white rounded-xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-indigo-600 to-blue-500 text-white px-5 py-4">
            <div class="h-9 w-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-user-plus"></i></div>
            <div class="flex-1">
                <h3 class="text-sm font-bold">Add Flight Officer</h3>
                <p class="text-xs opacity-80">Link an existing user to flight operations</p>
            </div><button type="button" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-create"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div class="p-5">
            <form id="createOfficerForm" action="{{ route('role.flight-officers.store',['role'=>$role]) }}" method="POST">@csrf<div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2"><label class="block text-xs font-semibold text-slate-500 mb-1">Existing User *</label><select id="create_officer_user_id" name="user_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Select user</option>@foreach($users as $item)<option value="{{ $item->id }}">{{ $item->name }}{{ $item->phone?' - '.$item->phone:'' }}</option>@endforeach
                        </select><p id="create_officer_user_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select an existing user.</p></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Airline / Desk</label><select id="create_officer_airline_id" name="airline_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">All / General</option>@foreach($airlines as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
                        </select></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Status *</label><select name="status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select></div>
                    <div class="col-span-2"><label class="block text-xs font-semibold text-slate-500 mb-1">Work Roles *</label>
                        <div class="flex flex-wrap gap-4 border border-slate-300 rounded-lg p-3"><label class="text-xs"><input type="checkbox" name="work_roles[]" value="boarding"> Boarding</label><label class="text-xs"><input type="checkbox" name="work_roles[]" value="immigration"> Immigration</label><label class="text-xs"><input type="checkbox" name="work_roles[]" value="offload"> Offload</label></div>
                        <p id="create_officer_work_roles_msg" class="text-red-500 text-xs mt-1 hidden error-message">Select at least one work role.</p>
                    </div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Contact</label><input name="contact" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Officer phone"></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Experience</label><input name="experience" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. 2 years"></div>
                </div>
            </form>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="rounded-lg border px-4 py-2 text-xs font-semibold cursor-pointer modal-close-create">Cancel</button><button type="button" id="createOfficerSubmit" class="rounded-lg bg-blue-600 text-white px-4 py-2 text-xs font-semibold cursor-pointer"><i class="fas fa-save mr-1"></i>Save Officer</button></div>
    </div>
</div>
