<div id="editModal" class="fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
 <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl" onclick="event.stopPropagation()">
  <header class="flex items-center gap-3 bg-gradient-to-r from-violet-600 to-indigo-500 px-5 py-4 text-white">
   <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/20 text-lg"><i class="fas fa-pen"></i></span>
   <div>
    <h2 class="text-sm font-bold">Edit File Category</h2>
    <p class="text-[11px] text-violet-100">Update visa rate and required documents</p>
   </div>
   <button class="modal-close-edit ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white"><i class="fas fa-times text-xs"></i></button>
  </header>
  <form id="editeForm" method="POST" class="grid gap-4 p-6 sm:grid-cols-2">@csrf @method('PUT')
   <input type="hidden" id="editItemId" name="id">
   <div>
    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Category (Job) Name <span class="text-red-500">*</span></label>
    <input id="edit_name" name="name" class="h-11 w-full rounded-xl border border-slate-200 px-3.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100">
    <p id="edit_name_msg" class="error-message mt-1 hidden text-xs text-red-500">Name is required</p>
   </div>
   <div>
    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Country <span class="text-red-500">*</span></label>
    <select id="edit_country_id" name="country_id" class="h-11 w-full rounded-xl border border-slate-200 px-3.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100">
     <option value="">Select country</option>
     @foreach($countries as $country)<option value="{{ $country->id }}">{{ $country->name }}</option>@endforeach
    </select>
    <p id="edit_country_id_msg" class="error-message mt-1 hidden text-xs text-red-500">Country is required</p>
   </div>
   <div>
    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Visa Rate (BDT) <span class="text-red-500">*</span></label>
    <input type="number" id="edit_visa_rate" name="visa_rate" min="0" step="0.01" class="h-11 w-full rounded-xl border border-slate-200 px-3.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100">
    <p id="edit_visa_rate_msg" class="error-message mt-1 hidden text-xs text-red-500">Visa rate is required</p>
   </div>
   <div>
    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Status</label>
    <select id="edit_status" name="status" class="h-11 w-full rounded-xl border border-slate-200 px-3.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100">
     <option value="active">Active</option>
     <option value="inactive">Inactive</option>
    </select>
   </div>
   <div class="sm:col-span-2">
    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Required Documents</label>
    <textarea id="edit_required_documents" name="required_documents" rows="3" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"></textarea>
   </div>
  </form>
  <footer class="flex justify-end gap-2 border-t bg-slate-50 px-5 py-3">
   <button class="modal-close-edit rounded-xl border px-5 py-2 text-xs font-semibold text-slate-600">Cancel</button>
   <button id="editSubmit" class="rounded-xl bg-violet-600 px-5 py-2 text-xs font-semibold text-white hover:bg-violet-700"><i class="fas fa-save mr-1.5"></i>Update Category</button>
  </footer>
 </div>
</div>
