<div id="viewModal" class="fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
 <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl" onclick="event.stopPropagation()">
  <header class="flex items-center gap-3 bg-gradient-to-r from-slate-700 to-slate-600 px-5 py-4 text-white">
   <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/20 text-lg"><i class="fas fa-folder-open"></i></span>
   <div>
    <h2 id="view_name" class="text-sm font-bold">File Category</h2>
    <p class="text-[11px] text-slate-300">Category details and document checklist</p>
   </div>
   <button class="modal-close-view ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white"><i class="fas fa-times text-xs"></i></button>
  </header>
  <div class="grid gap-3 p-5 sm:grid-cols-2">
   <div class="rounded-xl bg-slate-50 p-3.5">
    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Code</span>
    <span id="view_code" class="text-sm font-semibold text-slate-800">-</span>
   </div>
   <div class="rounded-xl bg-slate-50 p-3.5">
    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Country</span>
    <span id="view_country" class="text-sm font-semibold text-slate-800">-</span>
   </div>
   <div class="rounded-xl bg-slate-50 p-3.5">
    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Visa Rate</span>
    <span id="view_visa_rate" class="text-sm font-bold text-slate-800">-</span>
   </div>
   <div class="rounded-xl bg-slate-50 p-3.5">
    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Status</span>
    <span id="view_status">-</span>
   </div>
   <div class="rounded-xl bg-slate-50 p-3.5">
    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Required Docs</span>
    <span id="view_docs_count" class="text-sm font-semibold text-slate-800">-</span>
   </div>
   <div class="sm:col-span-2 rounded-xl bg-slate-50 p-3.5">
    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Documents List</span>
    <span id="view_documents" class="text-sm text-slate-700">-</span>
   </div>
  </div>
  <footer class="flex justify-end border-t bg-slate-50 px-5 py-3">
   <button class="modal-close-view rounded-xl border px-5 py-2 text-xs font-semibold text-slate-600">Close</button>
  </footer>
 </div>
</div>
