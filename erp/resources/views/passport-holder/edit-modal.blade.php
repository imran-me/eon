{{-- Edit Passport Holder Modal --}}
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="modal-backdrop fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl z-10 flex flex-col max-h-[90vh]">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-violet-600 rounded-t-2xl flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-passport text-white"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-white">Edit Passport Holder</h3>
                    <p class="text-xs text-violet-100">Update the passport holder's information</p>
                </div>
            </div>
            <button type="button" class="modal-close-edit w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Scrollable Body --}}
        <div class="overflow-y-auto flex-1">
            <form id="editForm" method="POST" enctype="multipart/form-data"
                action="{{ route('role.passport-holder.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder' => 1]) }}">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id">

                <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">

                    {{-- Category --}}
                    <div class="md:col-span-2">
                        <label for="edit_category_id" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Passport Category <span class="text-red-500">*</span>
                        </label>
                        <select id="edit_category_id" name="category_id"
                            class="form-select select2 w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50"
                            style="width: 100%">
                            <option value="">Select category...</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a category</p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="edit_type" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Passport Holder Type <span class="text-red-500">*</span>
                        </label>
                        <select id="edit_type" name="type" class="form-select w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50">
                            @include('passport-holder.type-options')
                        </select>
                    </div>

                    {{-- Name --}}
                    <div>
                        <label for="edit_name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_name" name="name"
                            class="form-input w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition"
                            placeholder="Enter full name">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Name is required</p>
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label for="edit_phone" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Phone <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_phone" name="phone"
                            class="form-input w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition"
                            placeholder="+880 1XXX-XXXXXX">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Phone is required</p>
                    </div>

                    {{-- Passport No --}}
                    <div>
                        <label for="edit_passport_no" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Passport Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_passport_no" name="passport_no"
                            class="form-input w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition font-mono"
                            placeholder="e.g. AB1234567">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Passport number is required</p>
                    </div>

                    {{-- Nationality --}}
                    <div>
                        <label for="edit_nationality" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Nationality <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_nationality" name="nationality"
                            class="form-input w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition"
                            placeholder="e.g. Bangladeshi">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Nationality is required</p>
                    </div>

                    {{-- Divider --}}
                    <div class="md:col-span-2 border-t border-dashed border-gray-200 pt-1">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Passport Dates</p>
                    </div>

                    {{-- DOB --}}
                    <div>
                        <label for="edit_date_of_birth" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Date of Birth
                        </label>
                        <input type="date" id="edit_date_of_birth" name="date_of_birth"
                            class="form-input w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                    </div>

                    {{-- Issue Date --}}
                    <div>
                        <label for="edit_issue_date" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Issue Date
                        </label>
                        <input type="date" id="edit_issue_date" name="issue_date"
                            class="form-input w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                    </div>

                    {{-- Expiry Date --}}
                    <div>
                        <label for="edit_expiry_date" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Expiry Date
                        </label>
                        <input type="date" id="edit_expiry_date" name="expiry_date"
                            class="form-input w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                    </div>

                    {{-- Status --}}
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <div class="relative">
                                <input type="checkbox" name="status" id="edit_status" class="sr-only peer">
                                <div class="w-10 h-6 bg-gray-200 peer-checked:bg-violet-600 rounded-full transition-colors"></div>
                                <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-700">Active Status</p>
                                <p class="text-xs text-gray-400">Toggle to activate or deactivate</p>
                            </div>
                        </label>
                    </div>

                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl flex-shrink-0">
                    <button type="button" class="modal-close-edit px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button id="editSubmit" type="button"
                        data-action="{{ route('role.passport-holder.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder' => 1]) }}"
                        class="px-5 py-2 text-sm font-semibold text-white bg-violet-600 rounded-xl hover:bg-violet-700 transition shadow-sm">
                        <i class="fas fa-save mr-1.5"></i>Update Passport Holder
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
