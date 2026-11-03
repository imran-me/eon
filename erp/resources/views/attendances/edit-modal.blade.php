<div id="editModal" class="modal fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="modal-backdrop fixed inset-0 bg-slate-950/55 backdrop-blur-sm"></div>
    <div class="modal-container relative z-50 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-white"><i class="fas fa-user-clock"></i></span>
                <div>
                    <h3 class="text-lg font-semibold text-white">Edit Attendance</h3>
                    <p class="text-xs text-blue-100">Update time, shift and attendance status</p>
                </div>
            </div>
            <button type="button" class="modal-close-edit flex h-9 w-9 items-center justify-center rounded-lg text-blue-100 transition hover:bg-white/15 hover:text-white" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>

        <form id="editForm" method="POST" enctype="multipart/form-data" action="{{ route('role.attendances.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'attendance' => 1]) }}">
            @csrf
            @method('PUT')
            <input type="hidden" id="editItemId" name="id">
            <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500"><i class="fas fa-id-card mr-1 text-blue-500"></i> Employee details</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="edit_user_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Employee <span class="text-red-500">*</span></label>
                            <select id="edit_user_id" name="user_id" class="form-select select2 w-full rounded-lg border border-slate-300 px-3 py-2.5" style="width:100%">
                                @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                            </select>
                            <p id="edit_user_msg" class="error-message mt-1 hidden text-xs text-red-500">Please select an employee.</p>
                        </div>
                        <div>
                            <label for="edit_company_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Company</label>
                            <select id="edit_company_id" name="company_id" class="form-select select2 w-full rounded-lg border border-slate-300 px-3 py-2.5" style="width:100%">
                                @foreach ($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500"><i class="fas fa-calendar-check mr-1 text-blue-500"></i> Attendance details</p>
                        <span id="editStatusPreview" class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Present</span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="edit_shift_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Shift</label>
                            <select id="edit_shift_id" name="shift_id" class="form-select select2 w-full rounded-lg border border-slate-300 px-3 py-2.5" style="width:100%">
                                @foreach ($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="edit_date" class="mb-1.5 block text-sm font-semibold text-slate-700">Date <span class="text-red-500">*</span></label>
                            <input type="date" id="edit_date" name="date" required class="form-input w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <p id="edit_date_msg" class="error-message mt-1 hidden text-xs text-red-500">Please select a date.</p>
                        </div>
                        <div>
                            <label for="edit_status" class="mb-1.5 block text-sm font-semibold text-slate-700">Present Status <span class="text-red-500">*</span></label>
                            <select id="edit_status" name="status" required class="form-select w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="leave">Leave</option>
                                <option value="holiday">Holiday</option>
                            </select>
                            <p id="edit_status_msg" class="error-message mt-1 hidden text-xs text-red-500">Please choose a status.</p>
                        </div>
                        <div class="hidden md:block"></div>
                        <div>
                            <label for="edit_check_in" class="mb-1.5 block text-sm font-semibold text-slate-700">Check-in time</label>
                            <input type="time" id="edit_check_in" name="check_in" class="form-input w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div>
                            <label for="edit_check_out" class="mb-1.5 block text-sm font-semibold text-slate-700">Check-out time</label>
                            <input type="time" id="edit_check_out" name="check_out" class="form-input w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <p id="edit_time_msg" class="error-message mt-1 hidden text-xs text-red-500">Check-out cannot be earlier than check-in.</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label for="edit_note" class="mb-1.5 block text-sm font-semibold text-slate-700">Note <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea id="edit_note" name="note" rows="3" maxlength="1000" placeholder="Add a reason or correction note" class="w-full resize-none rounded-lg border border-slate-300 p-3 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></textarea>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" class="modal-close-edit rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Cancel</button>
                <button data-action="{{ route('role.attendances.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'attendance' => 1]) }}" id="editSubmit" type="button" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"><i class="fas fa-check mr-1"></i> Update Attendance</button>
            </div>
        </form>
    </div>
</div>
